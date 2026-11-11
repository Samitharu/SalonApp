<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\SystemLog;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BookingService
{
    /**
     * Create an appointment after re-checking availability on the server.
     *
     * $data keys:
     *   name, phone, email?, notes?, date (Y-m-d), start (H:i), staff_id?,
     *   type (online|phone|counter), status, walk_in?, job_number?,
     *   started_at?, created_by_admin_id?
     * $slotOptions are passed to SlotService (not_before, step).
     *
     * @throws RuntimeException with a customer-safe message when the slot is gone.
     */
    public static function book(Service $service, array $data, array $slotOptions = []): Appointment
    {
        $date = Carbon::parse($data['date'])->toDateString();
        $slotOptions += ['buffer' => SlotService::bufferFor($service)];

        try {
            return Cache::lock('booking-lock:' . $date, 10)->block(5, function () use ($service, $data, $slotOptions, $date) {
                return DB::transaction(function () use ($service, $data, $slotOptions, $date) {
                    $requestedStaff = !empty($data['staff_id']) ? (int) $data['staff_id'] : null;

                    $slot = SlotService::findSlot($date, $data['start'], (int) $service->duration_minutes, $requestedStaff, $slotOptions);

                    if (!$slot) {
                        throw new RuntimeException($requestedStaff
                            ? 'Sorry, that stylist is no longer available at this time. Please choose another slot.'
                            : 'Sorry, that time slot is no longer available. Please choose another slot.');
                    }

                    static::assertNotPast($date, $slot['start'], !empty($data['walk_in']));

                    $staffId = $requestedStaff ?? SlotService::pickLeastBusy($slot['stylists'], $date);

                    // A walk-in booked for later today hasn't started yet.
                    if (!empty($data['walk_in']) && ($data['status'] ?? null) === 'in_progress'
                        && Carbon::parse($date . ' ' . $slot['start'])->gt(now()->addMinutes(15))) {
                        $data['status'] = 'confirmed';
                        $data['started_at'] = null;
                    }

                    $phone = normalize_phone($data['phone']);
                    $customer = Customer::firstOrCreate(['phone' => $phone], ['name' => trim($data['name'])]);

                    // One person can't be in two chairs at once.
                    $clash = SlotService::customerConflict($customer->id, $date, $slot['start'], $slot['end']);
                    if ($clash) {
                        throw new RuntimeException('This customer already has an appointment from '
                            . $clash['start']->format('g:i A') . ' to ' . $clash['raw_end']->format('g:i A')
                            . ' on this day. Please choose a time that doesn’t overlap.');
                    }

                    // Online, a typed email only fills a blank: anyone can type someone's phone
                    // number, and that mustn't redirect the customer's mail. Staff can correct it.
                    $email = !empty($data['email']) ? strtolower(trim($data['email'])) : null;
                    if ($email && (!$customer->email || ($data['type'] ?? 'online') !== 'online')) {
                        $customer->update(['email' => $email]);
                    }

                    $appointment = Appointment::create([
                        'appointment_no'      => Appointment::generateReference(),
                        'customer_id'         => $customer->id,
                        'service_id'          => $service->id,
                        ...static::offerFields($service, $date),
                        'staff_id'            => $staffId,
                        'appointment_date'    => $date,
                        'slot_start'          => $slot['start'] . ':00',
                        'slot_end'            => $slot['end'] . ':00',
                        'type'                => $data['type'] ?? 'online',
                        'status'              => $data['status'] ?? 'pending',
                        'notes'               => $data['notes'] ?? null,
                        'contact_email'       => $email ?: $customer->email,
                        'walk_in'             => (bool) ($data['walk_in'] ?? false),
                        'started_at'          => $data['started_at'] ?? null,
                        'job_number'          => $data['job_number'] ?? null,
                        'payment_status'      => 'unpaid',
                        'created_by_admin_id' => $data['created_by_admin_id'] ?? null,
                    ]);

                    $kind = $appointment->walk_in ? 'Walk-in' : ucfirst($appointment->type) . ' booking';
                    SystemLog::record(
                        'appointment.created',
                        "{$kind} {$appointment->appointment_no} for {$customer->name}: {$service->name} on "
                            . Carbon::parse($date)->format('D j M Y') . ' at ' . fmt_time($slot['start']),
                        $appointment,
                        array_filter(['service' => $service->name, 'date' => $date, 'start' => $slot['start'], 'staff_id' => $staffId, 'status' => $appointment->status, 'offer' => $appointment->offer_name, 'discount' => $appointment->offer_name ? (float) $appointment->discount_amount : null]),
                        actor: SystemLog::customerActor($customer),
                    );

                    return $appointment;
                });
            });
        } catch (LockTimeoutException $e) {
            throw new RuntimeException('We are handling a lot of bookings right now. Please try again in a moment.');
        }
    }

    /** The best offer for this service on the visit date, as saved on the booking. */
    private static function offerFields(Service $service, string $date): array
    {
        $offer = $service->offerOn($date);

        return [
            'offer_id'        => $offer?->id,
            'offer_name'      => $offer?->name,
            'discount_amount' => $offer?->discountOn((float) $service->price) ?? 0,
        ];
    }

    /** Walk-ins may take the slot that started a few minutes ago ("now"). */
    public const WALK_IN_GRACE_MINUTES = 15;

    /**
     * No appointment may start in the past — whichever screen it came from.
     *
     * @throws RuntimeException
     */
    public static function assertNotPast(string $date, string $start, bool $walkIn = false): void
    {
        $startsAt = Carbon::parse($date . ' ' . $start);
        $earliest = now()->startOfMinute()->subMinutes($walkIn ? static::WALK_IN_GRACE_MINUTES : 0);

        if ($startsAt->lt($earliest)) {
            throw new RuntimeException('That time has already passed. Please choose a later time.');
        }
    }

    /**
     * Change an existing booking's service, stylist, date and/or time.
     * The appointment's own current time doesn't count against itself;
     * every other rule (hours, breaks, cleanup, overlaps) applies as for a
     * new booking. Extras stay on the bill. A new service or date gets whatever
     * offer applies then; changing only the time or stylist keeps the price.
     *
     * $by: 'customer' | 'admin'. Customer changes go back to "pending"
     * unless online bookings are auto-confirmed.
     *
     * @throws RuntimeException with a safe message when the change isn't possible.
     */
    public static function reschedule(Appointment $appointment, Service $service, string $date, string $start, ?int $staffId, string $by, array $slotOptions = []): Appointment
    {
        $date = Carbon::parse($date)->toDateString();
        $slotOptions += ['buffer' => SlotService::bufferFor($service), 'ignore_id' => $appointment->id];

        try {
            return Cache::lock('booking-lock:' . $date, 10)->block(5, function () use ($appointment, $service, $date, $start, $staffId, $by, $slotOptions) {
                return DB::transaction(function () use ($appointment, $service, $date, $start, $staffId, $by, $slotOptions) {
                    $appointment->refresh();

                    if (!$appointment->isChangeable()) {
                        throw new RuntimeException('This booking can no longer be changed.');
                    }

                    $slot = SlotService::findSlot($date, $start, (int) $service->duration_minutes, $staffId, $slotOptions);

                    if (!$slot) {
                        throw new RuntimeException($staffId
                            ? 'That stylist isn’t available at this time. Please choose another time or stylist.'
                            : 'That time isn’t available. Please choose another time.');
                    }

                    static::assertNotPast($date, $slot['start']);

                    // Keep the current stylist when "any" was chosen and they're still free.
                    $freeIds = array_column($slot['stylists'], 'id');
                    $newStaff = $staffId
                        ?? (in_array($appointment->staff_id, $freeIds, false) ? $appointment->staff_id : SlotService::pickLeastBusy($slot['stylists'], $date));

                    $clash = SlotService::customerConflict($appointment->customer_id, $date, $slot['start'], $slot['end'], $appointment->id);
                    if ($clash) {
                        throw new RuntimeException(($by === 'customer' ? 'You already have' : 'This customer already has') . ' another appointment from '
                            . $clash['start']->format('g:i A') . ' to ' . $clash['raw_end']->format('g:i A') . ' on this day.');
                    }

                    $unchanged = (int) $appointment->service_id === (int) $service->id
                        && (int) $appointment->staff_id === (int) $newStaff
                        && Carbon::parse($appointment->appointment_date)->toDateString() === $date
                        && substr($appointment->slot_start, 0, 5) === $slot['start'];

                    if ($unchanged) {
                        throw new RuntimeException('Nothing changed — choose a different service, stylist or time.');
                    }

                    $status = $appointment->status;
                    if ($by === 'customer' && setting('auto_confirm', '0') !== '1') {
                        $status = 'pending'; // salon re-confirms customer changes
                    }

                    $before = [
                        'service' => $appointment->service?->name,
                        'staff'   => $appointment->staff?->name,
                        'date'    => Carbon::parse($appointment->appointment_date)->toDateString(),
                        'start'   => substr($appointment->slot_start, 0, 5),
                    ];

                    $samePrice = (int) $appointment->service_id === (int) $service->id && $before['date'] === $date;

                    $appointment->update(array_merge($samePrice ? [] : static::offerFields($service, $date), [
                        'service_id'       => $service->id,
                        'staff_id'         => $newStaff,
                        'appointment_date' => $date,
                        'slot_start'       => $slot['start'] . ':00',
                        'slot_end'         => $slot['end'] . ':00',
                        'status'           => $status,
                        'rescheduled_at'   => now(),
                        'rescheduled_by'   => $by,
                        // Only the customer's own changes count towards their online limit.
                        'reschedule_count' => (int) $appointment->reschedule_count + ($by === 'customer' ? 1 : 0),
                    ]));

                    $fresh = $appointment->fresh(['service', 'staff', 'customer']);
                    $after = ['service' => $service->name, 'staff' => $fresh->staff?->name, 'date' => $date, 'start' => $slot['start'], 'price' => $fresh->servicePrice()];

                    SystemLog::record(
                        'appointment.rescheduled',
                        "Booking {$fresh->appointment_no} changed by {$by}: {$service->name}, "
                            . Carbon::parse($date)->format('D j M') . ' at ' . fmt_time($slot['start'])
                            . ($fresh->staff ? ' with ' . $fresh->staff->name : ''),
                        $fresh,
                        ['before' => $before, 'after' => $after],
                        actor: SystemLog::customerActor($fresh->customer),
                    );

                    return $fresh;
                });
            });
        } catch (LockTimeoutException $e) {
            throw new RuntimeException('We are handling a lot of bookings right now. Please try again in a moment.');
        }
    }
}
