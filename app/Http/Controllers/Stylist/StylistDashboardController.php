<?php

namespace App\Http\Controllers\Stylist;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Service;
use App\Models\Staff;
use App\Models\SystemLog;
use App\Services\AppointmentCharges;
use App\Services\BookingService;
use App\Services\SlotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class StylistDashboardController extends Controller
{
    /**
     * Today's schedule for the signed-in stylist.
     */
    public function index()
    {
        /** @var Staff $stylist */
        $stylist = Auth::guard('staff')->user();
        $today   = now()->toDateString();

        $appointments = Appointment::with(['customer', 'service', 'items'])
            ->where('staff_id', $stylist->id)
            ->whereDate('appointment_date', $today)
            ->orderBy('slot_start')
            ->get();

        // Unassigned bookings today that any stylist may pick up.
        $openBookings = Appointment::with(['customer', 'service'])
            ->whereNull('staff_id')
            ->whereDate('appointment_date', $today)
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('slot_start')
            ->get();

        $upcoming = Appointment::with(['customer', 'service'])
            ->where('staff_id', $stylist->id)
            ->whereDate('appointment_date', '>', $today)
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('appointment_date')
            ->orderBy('slot_start')
            ->take(10)
            ->get();

        $catalog = AppointmentCharges::catalog();

        $rating = $stylist->feedback()->selectRaw('COUNT(*) as n, AVG(rating) as avg')->first();

        return view('stylist.dashboard', compact('stylist', 'appointments', 'openBookings', 'upcoming', 'catalog', 'rating'));
    }

    // ── Extras (add-ons) on the stylist's own appointments ──────────────

    public function charges(Appointment $appointment)
    {
        $this->authorizeOwn($appointment);

        return response()->json(AppointmentCharges::summary($appointment));
    }

    public function addItem(Request $request, Appointment $appointment)
    {
        $this->authorizeOwn($appointment);
        $data = $request->validate(AppointmentCharges::RULES, AppointmentCharges::MESSAGES);

        try {
            AppointmentCharges::add($appointment, $data, 'stylist', Auth::guard('staff')->user()->name);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(AppointmentCharges::summary($appointment));
    }

    public function removeItem(Appointment $appointment, AppointmentItem $item)
    {
        $this->authorizeOwn($appointment);
        abort_unless(AppointmentCharges::belongsTo($appointment, $item), 404);

        try {
            AppointmentCharges::remove($appointment, $item);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(AppointmentCharges::summary($appointment));
    }

    private function authorizeOwn(Appointment $appointment): void
    {
        if ((int) $appointment->staff_id !== (int) Auth::guard('staff')->id()) {
            abort(response()->json(['error' => 'You can only change your own appointments.'], 403));
        }
    }

    /**
     * AJAX: start a job from a scanned (or typed) appointment reference.
     */
    public function scanCustomerQr(Request $request)
    {
        $request->validate(['appointment_no' => 'required|string|max:20']);

        $stylist = Auth::guard('staff')->user();

        $appt = Appointment::with(['customer', 'service'])
            ->where('appointment_no', strtoupper(trim($request->appointment_no)))
            ->first();

        if (!$appt) {
            return response()->json(['error' => 'No appointment matches that reference.'], 404);
        }

        if ($appt->staff_id && (int) $appt->staff_id !== (int) $stylist->id) {
            return response()->json(['error' => 'This appointment is assigned to another stylist.'], 403);
        }

        if ($appt->status === 'completed') {
            return response()->json(['error' => 'This appointment is already completed.'], 422);
        }

        if (in_array($appt->status, ['cancelled', 'no_show'], true)) {
            return response()->json(['error' => 'This appointment was cancelled.'], 422);
        }

        if (\Carbon\Carbon::parse($appt->appointment_date)->toDateString() !== now()->toDateString()) {
            return response()->json(['error' => 'This appointment is for ' . \Carbon\Carbon::parse($appt->appointment_date)->format('D j M') . ', not today.'], 422);
        }

        // Taking an unassigned booking: make sure it fits this stylist's schedule.
        if (!$appt->staff_id) {
            $clash = SlotService::stylistConflict((int) $stylist->id, now()->toDateString(), $appt->slot_start, $appt->slot_end, $appt->id, SlotService::bufferFor($appt->service));
            if ($clash) {
                return response()->json(['error' => SlotService::describeConflict($clash, 'You') . ' Ask the front desk to assign this booking to someone else.'], 422);
            }
        }

        if ($appt->status !== 'in_progress') {
            $appt->update([
                'staff_id'   => $appt->staff_id ?? $stylist->id,
                'status'     => 'in_progress',
                'started_at' => now(),
            ]);
            SystemLog::record('appointment.started', "{$stylist->name} started {$appt->appointment_no} for " . ($appt->customer->name ?? 'customer'), $appt);
        }

        return response()->json(['success' => true, 'appointment' => $this->apptData($appt->fresh(['customer', 'service']))]);
    }

    /**
     * AJAX: mark one of the stylist's own jobs as completed.
     */
    public function completeJob(Appointment $appointment)
    {
        $stylist = Auth::guard('staff')->user();

        if ((int) $appointment->staff_id !== (int) $stylist->id) {
            return response()->json(['error' => 'You can only complete your own appointments.'], 403);
        }

        if (!in_array($appointment->status, ['in_progress', 'confirmed', 'pending'], true)) {
            return response()->json(['error' => 'This appointment cannot be completed.'], 422);
        }

        $jobNumber = $appointment->job_number ?: Appointment::generateJobNumber();

        $appointment->update([
            'status'       => 'completed',
            'completed_at' => now(),
            'started_at'   => $appointment->started_at ?? now(),
            'job_number'   => $jobNumber,
        ]);

        SystemLog::record('appointment.completed', "{$stylist->name} completed {$appointment->appointment_no} (job {$jobNumber})", $appointment);

        return response()->json(['success' => true, 'job_number' => $jobNumber]);
    }

    // ── Walk-in instant appointment ─────────────────────────────────────

    /**
     * Opened from the dashboard or by scanning the stylist's own table QR.
     */
    public function walkinForm(Request $request)
    {
        $loggedIn = Auth::guard('staff')->user();

        if ($request->filled('token')) {
            $owner = Staff::where('table_qr_token', (string) $request->token)->where('is_active', 1)->first();

            if (!$owner || $owner->id !== $loggedIn->id) {
                return redirect()->route('stylist.dashboard')
                    ->with('error', 'That walk-in QR belongs to a different stylist or is no longer valid.');
            }
        }

        $canWalkin = $loggedIn->portalCan('portal.walkin');
        $canLater  = $loggedIn->portalCan('portal.bookings');

        if (!$canWalkin && !$canLater) {
            return redirect()->route('stylist.dashboard')
                ->with('error', 'You don’t have permission to add bookings. Ask the salon owner if you need it.');
        }

        $services = Service::bookable()->orderBy('name')->get();
        $staff = Staff::where('is_active', 1)->orderBy('name')->get(['id', 'name']);

        return view('stylist.walkin', [
            'stylist'  => $loggedIn,
            'services' => $services,
            'staff'    => $staff,
            'mode'     => ($request->query('mode') === 'later' && $canLater) || !$canWalkin ? 'later' : 'walkin',
            'canWalkin' => $canWalkin,
            'canLater'  => $canLater,
            'maxDate'  => now()->addDays(SlotService::maxAdvanceDays() - 1)->toDateString(),
        ]);
    }

    /**
     * AJAX: free slots. Walk-in = today from now, with me. Later = any date
     * in the booking window, with me, a colleague or whoever is free.
     */
    public function walkinSlots(Request $request)
    {
        $data = $request->validate([
            'service_id' => 'required|integer|exists:services,id',
            'mode'       => 'nullable|in:walkin,later',
            'date'       => 'required_if:mode,later|nullable|date_format:Y-m-d',
            'staff_id'   => 'nullable|integer|exists:staff,id',
        ]);

        $this->authorizeMode(($data['mode'] ?? 'walkin') === 'later');

        $stylist = Auth::guard('staff')->user();
        $service = Service::findOrFail($data['service_id']);
        $buffer  = ['buffer' => SlotService::bufferFor($service)];

        if (($data['mode'] ?? 'walkin') === 'walkin') {
            return response()->json(SlotService::availableSlots(
                now()->toDateString(), (int) $service->duration_minutes, $stylist->id, $this->walkinOptions() + $buffer
            ));
        }

        if (!$this->inBookingWindow($data['date'])) {
            return response()->json([]);
        }

        return response()->json(SlotService::availableSlots(
            $data['date'], (int) $service->duration_minutes, $data['staff_id'] ?? null, $this->laterOptions($data['date']) + $buffer
        ));
    }

    public function walkinStore(Request $request)
    {
        $later = $request->input('mode') === 'later';
        $this->authorizeMode($later);

        $data = $request->validate([
            'customer_name'  => 'required|string|min:2|max:120',
            'customer_phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            // A booking for later gets a confirmation email; a walk-in is already here.
            'customer_email' => ($later ? 'required' : 'nullable') . '|email:rfc|max:120',
            'service_id'     => 'required|integer|exists:services,id',
            'start_time'     => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'date'           => $later ? 'required|date_format:Y-m-d' : 'nullable',
            'staff_id'       => 'nullable|integer|exists:staff,id',
            'type'           => $later ? 'required|in:phone,counter' : 'nullable',
            'notes'          => 'nullable|string|max:500',
        ], [
            'customer_phone.regex'    => 'Please enter a valid phone number.',
            'customer_email.required' => 'Enter the customer’s email so the confirmation can be sent.',
            'customer_email.email'    => 'Please enter a valid email address.',
            'start_time.required'     => 'Please choose a time.',
        ]);

        $stylist = Auth::guard('staff')->user();
        $service = Service::bookable()->find($data['service_id']);

        if (!$service) {
            return response()->json(['error' => 'That service can’t be booked on its own.'], 422);
        }

        if ($later && !$this->inBookingWindow($data['date'])) {
            return response()->json(['error' => 'Please choose a date between today and ' . now()->addDays(SlotService::maxAdvanceDays() - 1)->format('j M Y') . '.'], 422);
        }

        if (!empty($data['staff_id']) && !Staff::where('id', $data['staff_id'])->where('is_active', 1)->exists()) {
            return response()->json(['error' => 'That stylist isn’t taking bookings.'], 422);
        }

        $booking = [
            'name'  => $data['customer_name'],
            'phone' => $data['customer_phone'],
            'email' => $data['customer_email'] ?? null,
            'start' => $data['start_time'],
            'notes' => $data['notes'] ?? null,
        ];

        try {
            $appointment = $later
                ? BookingService::book($service, $booking + [
                    'date'     => $data['date'],
                    'staff_id' => $data['staff_id'] ?? null,   // null = whoever is free
                    'type'     => $data['type'],
                    'status'   => 'confirmed',
                ], $this->laterOptions($data['date']))
                : BookingService::book($service, $booking + [
                    'date'       => now()->toDateString(),
                    'staff_id'   => $stylist->id,
                    'type'       => 'counter',
                    'status'     => 'in_progress',   // becomes "confirmed" if it starts later today
                    'walk_in'    => true,
                    'started_at' => now(),
                    'job_number' => Appointment::generateJobNumber(),
                ], $this->walkinOptions());
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $appointment->load(['customer', 'service', 'staff']);

        return response()->json([
            'success'        => true,
            'mode'           => $later ? 'later' : 'walkin',
            'id'             => $appointment->id,
            'job_number'     => $appointment->job_number,
            'appointment_no' => $appointment->appointment_no,
            'status'         => $appointment->status,
            'customer_name'  => $appointment->customer->name,
            'service_name'   => $appointment->service->name,
            'stylist_name'   => $appointment->staff->name ?? null,
            'when'           => \Carbon\Carbon::parse($appointment->appointment_date)->format('D j M Y') . ' at ' . fmt_time($appointment->slot_start),
            'mine'           => (int) $appointment->staff_id === (int) $stylist->id,
            'email'          => $appointment->status === 'confirmed' ? $appointment->confirmationEmail() : null,
        ]);
    }

    private function authorizeMode(bool $later): void
    {
        if (!Auth::guard('staff')->user()->portalCan($later ? 'portal.bookings' : 'portal.walkin')) {
            abort(response()->json(['error' => 'You don’t have permission to do this. Ask the salon owner if you need it.'], 403));
        }
    }

    private function inBookingWindow(string $date): bool
    {
        $d = \Carbon\Carbon::parse($date)->startOfDay();

        return $d->gte(now()->startOfDay()) && $d->lt(now()->startOfDay()->addDays(SlotService::maxAdvanceDays()));
    }

    /** Phone/later bookings: same-day slots that have already passed are skipped. */
    private function laterOptions(string $date): array
    {
        return ['not_before' => $date === now()->toDateString() ? now() : null];
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /** Walk-ins use a 15-minute grid and may start a slot that began moments ago. */
    private function walkinOptions(): array
    {
        return ['step' => 15, 'not_before' => now()->subMinutes(10)];
    }

    private function apptData(Appointment $a): array
    {
        return [
            'id'             => $a->id,
            'appointment_no' => $a->appointment_no,
            'job_number'     => $a->job_number,
            'customer_name'  => $a->customer->name ?? '—',
            'customer_phone' => $a->customer->phone ?? '—',
            'service_name'   => $a->service->name ?? '—',
            'slot_start'     => fmt_time($a->slot_start),
            'slot_end'       => fmt_time($a->slot_end),
            'status'         => $a->status,
        ];
    }
}
