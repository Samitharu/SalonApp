<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendBookingConfirmation;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Staff;
use App\Models\SystemLog;
use App\Services\AppointmentCharges;
use App\Services\BookingService;
use App\Services\PaymentService;
use App\Services\SlotService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q'        => 'nullable|string|max:100',
            'date'     => 'nullable|date_format:Y-m-d',
            'status'   => ['nullable', Rule::in(Appointment::STATUSES)],
            'staff_id' => 'nullable|integer',
            'scope'    => 'nullable|in:upcoming,past,all',
        ]);

        $appointments = $this->filteredQuery($filters)
            ->with(['customer', 'service', 'staff'])
            ->paginate(20)
            ->withQueryString();

        $staffList = Staff::orderBy('name')->get(['id', 'name']);

        return view('admin.appointments.index', compact('appointments', 'staffList', 'filters'));
    }

    public function create(Request $request)
    {
        return view('admin.appointments.create', [
            'services' => Service::bookable()->orderBy('name')->get(),
            'staff'    => Staff::where('is_active', 1)->orderBy('name')->get(['id', 'name']),
            'date'     => $request->query('date', now()->toDateString()),
            'walkin'   => $request->boolean('walkin'),
        ]);
    }

    /** AJAX: slots for the admin booking form (no minimum-notice rule). */
    public function slots(Request $request)
    {
        $data = $request->validate([
            'service_id' => 'required|integer|exists:services,id',
            'date'       => 'required|date_format:Y-m-d',
            'staff_id'   => 'nullable|integer|exists:staff,id',
            'walkin'     => 'nullable|boolean',
            'ignore'     => 'nullable|integer',
        ]);

        $service = Service::findOrFail($data['service_id']);
        $walkin  = !empty($data['walkin']);

        $options = $this->slotOptions($walkin ? now()->toDateString() : $data['date'], $walkin) + ['buffer' => SlotService::bufferFor($service)];
        if (!empty($data['ignore'])) {
            $options['ignore_id'] = (int) $data['ignore']; // rescheduling: its own time counts as free
        }

        return response()->json(SlotService::availableSlots(
            $walkin ? now()->toDateString() : $data['date'],
            (int) $service->duration_minutes,
            $data['staff_id'] ?? null,
            $options
        ));
    }

    // ── Change service / stylist / date / time ──────────────────────────

    public function reschedule(Appointment $appointment)
    {
        if (!$appointment->isChangeable()) {
            return redirect()->route('admin.appointments.show', $appointment)
                ->with('error', 'Only pending or confirmed, unpaid bookings can be changed.');
        }

        $appointment->load(['customer', 'service', 'staff']);

        return view('admin.appointments.reschedule', [
            'appointment' => $appointment,
            'services'    => Service::bookable()->orderBy('name')->get(),
            'staff'       => Staff::where('is_active', 1)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function saveReschedule(Request $request, Appointment $appointment)
    {
        $data = $request->validate([
            'service_id' => 'required|integer|exists:services,id',
            'date'       => 'required|date_format:Y-m-d|after_or_equal:today',
            'start_time' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'staff_id'   => 'nullable|integer|exists:staff,id',
        ], ['start_time.required' => 'Please choose a time slot.']);

        $service = Service::bookable()->find($data['service_id']);
        if (!$service) {
            return back()->withInput()->with('error', 'That service can’t be booked on its own.');
        }

        try {
            $appointment = BookingService::reschedule(
                $appointment, $service, $data['date'], $data['start_time'],
                isset($data['staff_id']) ? (int) $data['staff_id'] : null,
                'admin', $this->slotOptions($data['date'], false)
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.appointments.show', $appointment)
            ->with('success', 'Booking ' . $appointment->appointment_no . ' changed to ' . $appointment->service->name . ', '
                . \Carbon\Carbon::parse($appointment->appointment_date)->format('D j M') . ' at ' . fmt_time($appointment->slot_start) . '.');
    }

    public function store(Request $request)
    {
        $walkin = $request->boolean('walk_in');

        $data = $request->validate([
            'name'       => 'required|string|min:2|max:120',
            'phone'      => ['required', 'string', 'max:20', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            // A booking for later gets a confirmation email; a walk-in is already here.
            'email'      => ($walkin ? 'nullable' : 'required') . '|email:rfc|max:120',
            'service_id' => 'required|integer|exists:services,id',
            'date'       => $walkin ? 'nullable' : 'required|date_format:Y-m-d|after_or_equal:today',
            'start_time' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'staff_id'   => 'nullable|integer|exists:staff,id',
            'notes'      => 'nullable|string|max:500',
            'type'       => 'required|in:phone,counter,online',
        ], [
            'start_time.required' => 'Please choose a time slot.',
            'phone.regex'         => 'Please enter a valid phone number.',
            'email.required'      => 'Enter the customer’s email so the confirmation can be sent.',
        ]);

        $service = Service::bookable()->find($data['service_id']);
        if (!$service) {
            return back()->withInput()->with('error', 'That service can’t be booked on its own.');
        }

        $date = $walkin ? now()->toDateString() : $data['date'];

        $booking = [
            'name'                => $data['name'],
            'phone'               => $data['phone'],
            'email'               => $data['email'] ?? null,
            'notes'               => $data['notes'] ?? null,
            'date'                => $date,
            'start'               => $data['start_time'],
            'staff_id'            => $data['staff_id'] ?? null,
            'type'                => $walkin ? 'counter' : $data['type'],
            'status'              => 'confirmed',
            'created_by_admin_id' => $request->user('admin')->id,
        ];

        // Walk-in: starts now with a job number, like the stylist portal.
        if ($walkin) {
            $booking = array_merge($booking, [
                'status'     => 'in_progress',
                'walk_in'    => true,
                'started_at' => now(),
                'job_number' => Appointment::generateJobNumber(),
            ]);
        }

        try {
            $appointment = BookingService::book($service, $booking, $this->slotOptions($date, $walkin));
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.appointments.show', $appointment)->with('success', $walkin
            ? 'Walk-in started for ' . $data['name'] . '. Job number ' . $appointment->job_number . '.'
            : 'Appointment ' . $appointment->appointment_no . ' created.' . $this->emailNote($appointment));
    }

    /** Tells staff, honestly, what happens with the confirmation email. */
    private function emailNote(Appointment $appointment): string
    {
        if (!$to = $appointment->confirmationEmail()) {
            return ' There’s no email on this booking, so the customer hasn’t been told. Add one below, or call them.';
        }

        return mail_is_live()
            ? " Confirmation email going to {$to}."
            : ' Email isn’t set up yet, so no confirmation was really sent.';
    }

    /** Send the confirmation again (e.g. after a failure or a corrected address). */
    public function resendConfirmation(Appointment $appointment)
    {
        if ($appointment->status !== 'confirmed') {
            return back()->with('error', 'Only confirmed bookings have a confirmation email.');
        }

        $error = SendBookingConfirmation::send($appointment);

        if ($error) {
            return back()->with('error', 'The email couldn’t be sent: ' . $error);
        }

        return back()->with('success', mail_is_live()
            ? 'Confirmation sent to ' . $appointment->confirmationEmail() . '.'
            : 'Email isn’t set up yet, so this was only written to the log.');
    }

    // ── Extras (add-ons) ────────────────────────────────────────────────

    public function addItem(Request $request, Appointment $appointment)
    {
        $data = $request->validateWithBag('extras', AppointmentCharges::RULES, AppointmentCharges::MESSAGES);

        try {
            $item = AppointmentCharges::add($appointment, $data, 'admin', $request->user('admin')->name);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Added “' . $item->name . '” to the bill.');
    }

    public function removeItem(Appointment $appointment, AppointmentItem $item)
    {
        abort_unless(AppointmentCharges::belongsTo($appointment, $item), 404);

        try {
            AppointmentCharges::remove($appointment, $item);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Removed “' . $item->name . '” from the bill.');
    }

    // ── Payments: advances before the visit, settlement after ───────────

    public function addPayment(Request $request, Appointment $appointment)
    {
        $data = $request->validateWithBag('payment', [
            'kind'      => 'required|in:advance,payment',
            'method'    => ['required', Rule::in(array_keys(Payment::METHODS))],
            'amount'    => 'required|numeric|min:0.01|max:100000000',
            'reference' => 'nullable|string|max:100',
            'note'      => 'nullable|string|max:255',
        ], ['amount.min' => 'Enter the amount received.']);

        try {
            $appointment = PaymentService::record($appointment, $data['kind'], [[
                'method'    => $data['method'],
                'amount'    => $data['amount'],
                'reference' => $data['reference'] ?? null,
            ]], 'admin', $request->user('admin')->name, null, $data['note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', ($data['kind'] === 'advance' ? 'Advance of ' : 'Payment of ') . money($data['amount']) . ' recorded. '
            . ($appointment->payment_status === 'paid' ? 'The bill is now paid.' : 'Balance due ' . money($appointment->balanceDue()) . '.'));
    }

    public function voidPayment(Appointment $appointment, Payment $payment)
    {
        abort_unless((int) $payment->appointment_id === (int) $appointment->id, 404);

        PaymentService::void($payment);

        return back()->with('success', 'Payment of ' . money($payment->amount) . ' removed.');
    }

    public function show(Appointment $appointment)
    {
        $appointment->load(['customer', 'service', 'staff', 'items', 'feedback', 'payments']);

        $history = Appointment::with('service')
            ->where('customer_id', $appointment->customer_id)
            ->where('id', '!=', $appointment->id)
            ->orderByDesc('appointment_date')
            ->take(5)
            ->get();

        $staff = Staff::where('is_active', 1)->orderBy('name')->get(['id', 'name']);

        $catalog = AppointmentCharges::catalog();

        $activity = SystemLog::where('subject_type', 'Appointment')->where('subject_id', $appointment->id)
            ->latest('id')->take(12)->get();

        // Net stock this visit used, per product (usage minus returns).
        $productsUsed = $appointment->stockMovements()->whereIn('type', ['usage', 'return'])->with('product')->get()
            ->groupBy('product_id')
            ->map(fn ($rows) => ['product' => $rows->first()->product, 'qty' => -$rows->sum('quantity')])
            ->filter(fn ($r) => $r['product'] && $r['qty'] > 0.0005)
            ->values();

        return view('admin.appointments.show', compact('appointment', 'history', 'staff', 'catalog', 'activity', 'productsUsed'));
    }

    /** Update stylist assignment and internal notes. */
    public function update(Request $request, Appointment $appointment)
    {
        $data = $request->validate([
            'staff_id' => 'nullable|integer|exists:staff,id',
            'notes'    => 'nullable|string|max:1000',
            'contact_email' => 'nullable|email:rfc|max:120',
        ]);

        $staffId = $data['staff_id'] ?? null;

        if ($staffId && (int) $staffId !== (int) $appointment->staff_id && $this->isActive($appointment)) {
            $clash = SlotService::stylistConflict((int) $staffId, $appointment->appointment_date, $appointment->slot_start, $appointment->slot_end, $appointment->id, SlotService::bufferFor($appointment->service));

            if ($clash) {
                return back()->with('error', SlotService::describeConflict($clash, 'That stylist'));
            }
        }

        $changes = ['staff_id' => $staffId, 'notes' => $data['notes'] ?? null];

        if ($request->has('contact_email')) {
            $email = isset($data['contact_email']) ? strtolower(trim($data['contact_email'])) : null;
            // A different address hasn't received anything yet.
            if ($email !== $appointment->confirmationEmail()) {
                $changes += ['contact_email' => $email, 'confirmation_sent_at' => null, 'confirmation_error' => null];
            }
        }

        $appointment->update($changes);

        if ($changed = SystemLog::changes($appointment)) {
            $what = isset($changed['staff_id'])
                ? 'stylist changed to ' . (Staff::find($staffId)->name ?? 'unassigned')
                : (isset($changed['contact_email']) ? 'email changed' : 'notes updated');
            SystemLog::record('appointment.updated', "Booking {$appointment->appointment_no}: {$what}", $appointment, $changed);
        }

        return back()->with('success', 'Appointment updated.');
    }

    public function updateStatus(Request $request, Appointment $appointment)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Appointment::STATUSES)],
        ]);

        $status  = $data['status'];
        $changes = ['status' => $status];

        // Reopening a cancelled/no-show booking: its time may have been rebooked since.
        if (!$this->isActive($appointment) && in_array($status, ['pending', 'confirmed', 'in_progress', 'completed'], true)) {
            if ($error = $this->reopenConflict($appointment)) {
                return back()->with('error', $error);
            }
            $changes['cancelled_at'] = null;
            $changes['cancelled_by'] = null;
        }

        if ($status === 'in_progress' && !$appointment->started_at) {
            $changes['started_at'] = now();
        }

        if ($status === 'completed') {
            $changes['completed_at'] = $appointment->completed_at ?? now();
            $changes['job_number']   = $appointment->job_number ?? Appointment::generateJobNumber();
        }

        if ($status === 'cancelled') {
            $changes['cancelled_by'] = 'admin';
            $changes['cancelled_at'] = now();
        }

        $from = $appointment->status;
        $appointment->update($changes);

        SystemLog::record(
            $status === 'cancelled' ? 'appointment.cancelled' : 'appointment.status_changed',
            "Booking {$appointment->appointment_no} marked as " . strtolower(status_label($status)) . ' (was ' . strtolower(status_label($from)) . ')',
            $appointment,
            ['status' => ['from' => $from, 'to' => $status]],
        );

        return back()->with('success', 'Appointment ' . $appointment->appointment_no . ' marked as ' . strtolower(status_label($status)) . '.'
            . ($status === 'confirmed' && $from !== 'confirmed' ? $this->emailNote($appointment) : ''));
    }

    public function destroy(Appointment $appointment)
    {
        $ref = $appointment->appointment_no;
        $appointment->loadMissing(['customer', 'service']);
        SystemLog::record('appointment.deleted', "Booking {$ref} deleted permanently", $appointment, [
            'customer' => $appointment->customer->name ?? null,
            'service'  => $appointment->service->name ?? null,
            'date'     => \Carbon\Carbon::parse($appointment->appointment_date)->toDateString(),
            'start'    => substr($appointment->slot_start, 0, 5),
            'status'   => $appointment->status,
        ], 'warning');
        $appointment->delete();

        return redirect()->route('admin.appointments.index')->with('success', 'Appointment ' . $ref . ' deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'q'        => 'nullable|string|max:100',
            'date'     => 'nullable|date_format:Y-m-d',
            'status'   => ['nullable', Rule::in(Appointment::STATUSES)],
            'staff_id' => 'nullable|integer',
            'scope'    => 'nullable|in:upcoming,past,all',
        ]);

        $query = $this->filteredQuery($filters)->with(['customer', 'service', 'staff', 'items']);

        SystemLog::record('appointment.exported', 'Exported ' . (clone $query)->count() . ' appointments to CSV', null, array_filter($filters));

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Date', 'Start', 'End', 'Customer', 'Phone', 'Service', 'Service price', 'Offer', 'Discount', 'Extras', 'Extras amount', 'Total', 'Stylist', 'Status', 'Type', 'Job #', 'Payment', 'Method', 'Amount paid']);

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $a) {
                    fputcsv($out, array_map([$this, 'csvSafe'], [
                        $a->appointment_no,
                        $a->appointment_date,
                        substr($a->slot_start, 0, 5),
                        substr($a->slot_end, 0, 5),
                        $a->customer->name ?? '',
                        $a->customer->phone ?? '',
                        $a->service->name ?? '',
                        $a->service->price ?? '',
                        $a->discount() > 0 ? $a->offer_name : '',
                        $a->discount() > 0 ? $a->discount() : '',
                        $a->items->map(fn ($i) => ($i->quantity > 1 ? $i->quantity . ' × ' : '') . $i->name)->implode('; '),
                        $a->extrasTotal(),
                        $a->total(),
                        $a->staff->name ?? 'Unassigned',
                        status_label($a->status),
                        $a->type,
                        $a->job_number,
                        $a->payment_status,
                        $a->payment_method ? $a->paymentMethodLabel() : '',
                        $a->amount_paid,
                    ]));
                }
            });

            fclose($out);
        }, 'appointments-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function isActive(Appointment $a): bool
    {
        return !in_array($a->status, ['cancelled', 'no_show'], true);
    }

    /**
     * Why a cancelled/no-show booking can't be reopened, or null if it can.
     */
    private function reopenConflict(Appointment $a): ?string
    {
        $date = \Carbon\Carbon::parse($a->appointment_date)->toDateString();

        if (SlotService::customerConflict((int) $a->customer_id, $date, $a->slot_start, $a->slot_end, $a->id)) {
            return 'Can’t reopen: the customer has another appointment at this time.';
        }

        $buffer = SlotService::bufferFor($a->service);

        if ($a->staff_id) {
            $clash = SlotService::stylistConflict((int) $a->staff_id, $date, $a->slot_start, $a->slot_end, $a->id, $buffer);

            return $clash ? 'Can’t reopen: ' . lcfirst(SlotService::describeConflict($clash)) . ' Assign another stylist first.' : null;
        }

        // Unassigned: at least one active stylist must be free.
        $staff = Staff::where('is_active', 1)->pluck('id');
        if ($staff->isEmpty()) {
            return null;
        }

        foreach ($staff as $id) {
            if (!SlotService::stylistConflict((int) $id, $date, $a->slot_start, $a->slot_end, $a->id, $buffer)) {
                return null;
            }
        }

        return 'Can’t reopen: no stylist is free at this time any more.';
    }

    /**
     * Walk-ins use a 15-minute grid and may start a slot that began moments
     * ago; other same-day bookings only offer times that haven't started.
     * BookingService::assertNotPast() enforces the same rule on save.
     */
    private function slotOptions(string $date, bool $walkin): array
    {
        if ($walkin) {
            return ['step' => 15, 'not_before' => now()->subMinutes(10)];
        }

        return ['not_before' => $date === now()->toDateString() ? now() : null];
    }

    private function filteredQuery(array $f)
    {
        $today = now()->toDateString();
        $scope = $f['scope'] ?? (empty($f['date']) ? 'upcoming' : 'all');

        $query = Appointment::query();

        if (!empty($f['q'])) {
            $term = '%' . trim($f['q']) . '%';
            $digits = preg_replace('/\D+/', '', $f['q']);
            $query->where(function ($q) use ($term, $digits) {
                $q->where('appointment_no', 'like', $term)
                    ->orWhere('job_number', 'like', $term)
                    ->orWhereHas('customer', function ($c) use ($term, $digits) {
                        $c->where('name', 'like', $term);
                        if (strlen($digits) >= 3) {
                            $c->orWhere('phone', 'like', '%' . $digits . '%');
                        }
                    });
            });
        }

        if (!empty($f['date'])) {
            $query->whereDate('appointment_date', $f['date']);
        }

        if (!empty($f['status'])) {
            $query->where('status', $f['status']);
        }

        if (!empty($f['staff_id'])) {
            $query->where('staff_id', $f['staff_id']);
        }

        if ($scope === 'upcoming') {
            $query->whereDate('appointment_date', '>=', $today)
                ->orderBy('appointment_date')->orderBy('slot_start');
        } elseif ($scope === 'past') {
            $query->whereDate('appointment_date', '<', $today)
                ->orderByDesc('appointment_date')->orderByDesc('slot_start');
        } else {
            $query->orderByDesc('appointment_date')->orderBy('slot_start');
        }

        return $query;
    }

    /** Prevent spreadsheet formula injection in exported values. */
    private function csvSafe($value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
