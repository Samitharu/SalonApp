<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\SystemLog;
use App\Services\BookingService;
use App\Services\CustomerAccess;
use App\Services\FeedbackService;
use App\Services\SlotService;
use Illuminate\Http\Request;
use RuntimeException;

class BookingController extends Controller
{
    public function index()
    {
        $categories = ServiceCategory::with(['services' => fn ($q) => $q->bookable()->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn ($c) => $c->services->isNotEmpty());

        $uncategorised = Service::bookable()->whereNull('service_category_id')->orderBy('name')->get();

        $staff = Staff::where('is_active', 1)->orderBy('name')->get(['id', 'name', 'title', 'bio']);

        return view('booking.index', [
            'categories'    => $categories,
            'uncategorised' => $uncategorised,
            'staff'         => $staff,
            'dates'         => SlotService::bookableDates(),
        ]);
    }

    /**
     * AJAX: available slots for a service on a date.
     * Returns: [{ start, end, stylists: [{id, name}] }, ...]
     */
    public function availableSlots(Request $request)
    {
        $data = $request->validate([
            'service_id' => 'required|integer|exists:services,id',
            'date'       => 'required|date_format:Y-m-d',
            'staff_id'   => 'nullable|integer|exists:staff,id',
        ]);

        $service = Service::bookable()->find($data['service_id']);

        if (!$service || !$this->dateIsBookable($data['date'])) {
            return response()->json([]);
        }

        $options = ['not_before' => $this->earliestStart(), 'buffer' => SlotService::bufferFor($service)];

        // When changing a booking, its own current time shouldn't block itself.
        if ($request->boolean('editing') && ($editing = $this->verifiedAppointment($request))) {
            $options['ignore_id'] = $editing->id;
        }

        $slots = SlotService::availableSlots(
            $data['date'],
            (int) $service->duration_minutes,
            isset($data['staff_id']) ? (int) $data['staff_id'] : null,
            $options
        );

        return response()->json($slots);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|min:2|max:120',
            'phone'      => ['required', 'string', 'max:20', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'email'      => 'required|email:rfc|max:120',
            'notes'      => 'nullable|string|max:500',
            'service_id' => 'required|integer|exists:services,id',
            'date'       => 'required|date_format:Y-m-d',
            'start_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'staff_id'   => 'nullable|integer|exists:staff,id',
        ], [
            'phone.regex'      => 'Please enter a valid phone number.',
            'email.required'   => 'Please enter your email so we can send your confirmation.',
            'email.email'      => 'Please enter a valid email address.',
            'start_time.regex' => 'Please choose a time slot.',
        ]);

        $service = Service::bookable()->find($data['service_id']);

        if (!$service) {
            return response()->json(['error' => 'This service is no longer available.'], 422);
        }

        if (!$this->dateIsBookable($data['date'])) {
            return response()->json(['error' => 'Please choose a date within the booking window.'], 422);
        }

        try {
            $appointment = BookingService::book($service, [
                'name'     => $data['name'],
                'phone'    => $data['phone'],
                'email'    => $data['email'],
                'notes'    => $data['notes'] ?? null,
                'date'     => $data['date'],
                'start'    => $data['start_time'],
                'staff_id' => $data['staff_id'] ?? null,
                'type'     => 'online',
                // Services that need an advance wait for it before being confirmed.
                'status'   => setting('auto_confirm', '0') === '1' && $service->advanceAmount($service->priceOn($data['date'])) <= 0 ? 'confirmed' : 'pending',
            ], ['not_before' => $this->earliestStart()]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $appointment->load(['service', 'staff']);

        return response()->json([
            'success'        => true,
            'appointment_no' => $appointment->appointment_no,
            'status'         => $appointment->status,
            'date'           => $appointment->appointment_date,
            'start'          => substr($appointment->slot_start, 0, 5),
            'end'            => substr($appointment->slot_end, 0, 5),
            'stylist'        => $appointment->staff->name ?? null,
            'price'          => money($appointment->servicePrice()),
            'offer'          => $appointment->discount() > 0 ? $appointment->offer_name : null,
            'advance'        => $appointment->advanceRequired() > 0
                ? ['amount' => $appointment->advanceRequired(), 'label' => money($appointment->advanceRequired())]
                : null,
        ]);
    }

    // ── Manage booking (customer self-service) ──────────────────────────
    // A correct reference + phone unlocks the booking for this browser
    // session for 30 minutes, so the customer can change, cancel or review
    // it without re-entering details.


    public function manage(Request $request)
    {
        $appointment = $this->verifiedAppointment($request);

        return view('booking.manage', ['appointment' => $appointment]);
    }

    public function lookup(Request $request)
    {
        $data = $request->validate([
            'reference' => 'required|string|max:20',
            'phone'     => 'required|string|max:20',
        ]);

        $appointment = $this->findForCustomer($data['reference'], $data['phone']);

        if (!$appointment) {
            return back()->withInput()->withErrors(['reference' => 'We could not find a booking with that reference and phone number.']);
        }

        $this->rememberVerified($request, $appointment);

        return redirect()->route('booking.manage');
    }

    public function forget(Request $request)
    {
        CustomerAccess::forget($request);

        return redirect()->route('booking.manage');
    }

    public function cancel(Request $request)
    {
        // Either the unlocked session booking, or reference + phone.
        $appointment = $this->verifiedAppointment($request);

        if (!$appointment) {
            $data = $request->validate([
                'reference' => 'required|string|max:20',
                'phone'     => 'required|string|max:20',
            ]);
            $appointment = $this->findForCustomer($data['reference'], $data['phone']);
            if ($appointment) {
                $this->rememberVerified($request, $appointment);
            }
        }

        if (!$appointment) {
            return redirect()->route('booking.manage')->with('error', 'We could not find that booking.');
        }

        if (!$appointment->isCancellableByCustomer()) {
            return redirect()->route('booking.manage')->with('error', 'This booking can no longer be cancelled online. Please call us.');
        }

        $appointment->update([
            'status'       => 'cancelled',
            'cancelled_by' => 'customer',
            'cancelled_at' => now(),
        ]);

        SystemLog::record('appointment.cancelled', "Booking {$appointment->appointment_no} cancelled online by the customer", $appointment,
            actor: ['customer', $appointment->customer_id, $appointment->customer->name ?? null]);

        return redirect()->route('booking.manage')->with('success', 'Your booking has been cancelled. We hope to see you another time.');
    }

    /** Booking page in "change" mode, prefilled with the current booking. */
    public function edit(Request $request)
    {
        $appointment = $this->verifiedAppointment($request);

        if (!$appointment) {
            return redirect()->route('booking.manage')->with('error', 'Please look up your booking first.');
        }

        if (!$appointment->isChangeableByCustomer()) {
            return redirect()->route('booking.manage')->with('error', $this->changeBlockedReason($appointment));
        }

        return $this->index()->with('editing', $appointment);
    }

    /** AJAX: apply a customer's change. Same response shape as store(). */
    public function update(Request $request)
    {
        $appointment = $this->verifiedAppointment($request);

        if (!$appointment) {
            return response()->json(['error' => 'Your session expired. Please look up your booking again.'], 401);
        }

        if (!$appointment->isChangeableByCustomer()) {
            return response()->json(['error' => $this->changeBlockedReason($appointment)], 422);
        }

        $data = $request->validate([
            'service_id' => 'required|integer|exists:services,id',
            'date'       => 'required|date_format:Y-m-d',
            'start_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'staff_id'   => 'nullable|integer|exists:staff,id',
        ], ['start_time.regex' => 'Please choose a time slot.']);

        $service = Service::bookable()->find($data['service_id']);

        if (!$service) {
            return response()->json(['error' => 'This service is no longer available.'], 422);
        }

        if (!$this->dateIsBookable($data['date'])) {
            return response()->json(['error' => 'Please choose a date within the booking window.'], 422);
        }

        try {
            $appointment = BookingService::reschedule(
                $appointment, $service, $data['date'], $data['start_time'], $data['staff_id'] ?? null, 'customer',
                ['not_before' => $this->earliestStart()]
            );
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success'        => true,
            'appointment_no' => $appointment->appointment_no,
            'status'         => $appointment->status,
            'date'           => $appointment->appointment_date,
            'start'          => substr($appointment->slot_start, 0, 5),
            'end'            => substr($appointment->slot_end, 0, 5),
            'stylist'        => $appointment->staff->name ?? null,
            'price'          => money($appointment->servicePrice()),
            'offer'          => $appointment->discount() > 0 ? $appointment->offer_name : null,
        ]);
    }

    public function feedback(Request $request)
    {
        $appointment = $this->verifiedAppointment($request);

        if (!$appointment) {
            return redirect()->route('booking.manage')->with('error', 'Please look up your booking first.');
        }

        if ($reason = FeedbackService::blockedReason($appointment)) {
            return redirect()->route('booking.manage')->with('error', $reason);
        }

        $data = $request->validateWithBag('feedback', FeedbackService::RULES, FeedbackService::MESSAGES);
        FeedbackService::submit($appointment, $data + ['allow_public' => $request->boolean('allow_public')]);

        return redirect()->route('booking.manage')->with('success', 'Thank you for your feedback!');
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function rememberVerified(Request $request, Appointment $appointment): void
    {
        CustomerAccess::remember($request, $appointment);
    }

    /** The booking this browser unlocked with reference + phone, if still valid. */
    private function verifiedAppointment(Request $request): ?Appointment
    {
        return CustomerAccess::verified($request);
    }

    private function changeBlockedReason(Appointment $a): string
    {
        if (!$a->isChangeable()) {
            return 'This booking can no longer be changed.';
        }
        if ((int) $a->reschedule_count >= Appointment::MAX_CUSTOMER_CHANGES) {
            return 'This booking has already been changed ' . Appointment::MAX_CUSTOMER_CHANGES . ' times. Please call us to make further changes.';
        }

        return 'It’s too close to your appointment to change it online. Please call us'
            . (setting('salon_phone') ? ' on ' . setting('salon_phone') : '') . '.';
    }

    private function findForCustomer(string $reference, string $phone): ?Appointment
    {
        return CustomerAccess::find($reference, $phone);
    }

    private function earliestStart(): \Carbon\Carbon
    {
        return now()->addMinutes(max(0, (int) setting('min_notice_minutes', 30)));
    }

    private function dateIsBookable(string $date): bool
    {
        $d = \Carbon\Carbon::parse($date)->startOfDay();
        $days = SlotService::maxAdvanceDays();

        return $d->gte(now()->startOfDay()) && $d->lt(now()->startOfDay()->addDays($days));
    }
}
