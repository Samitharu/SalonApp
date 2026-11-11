<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\BusinessHour;
use App\Models\DayOff;
use App\Models\Service;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Availability and overlap rules shared by every booking path.
 *
 * A slot is bookable for a stylist when:
 *   - the salon is open that day (business hours, no closure),
 *   - it fits inside the salon's hours and the stylist's working hours,
 *   - it doesn't cross the salon lunch break or the stylist's own break,
 *   - it doesn't overlap another appointment, counting the cleanup time
 *     after each appointment (per service, or the salon default).
 */
class SlotService
{
    /**
     * Return available slots for a given date + service duration.
     *
     * When $staffId is provided  → only check that one stylist.
     * When $staffId is null      → check ALL active stylists; return every
     *                              slot where at least one stylist is free.
     *
     * Options:
     *   'not_before' => Carbon   Skip slots starting before this moment.
     *   'step'       => int      Minutes between slot starts. Defaults to the
     *                            "slot_step" setting, or the service duration.
     *   'buffer'     => int      Cleanup minutes after the new appointment.
     *                            Defaults to the salon setting.
     *   'ignore_id'  => int      Appointment being changed — its own time
     *                            doesn't count as busy.
     *
     * Each returned element:
     *   ['start' => 'HH:MM', 'end' => 'HH:MM', 'stylists' => [['id' => 1, 'name' => 'Amaya'], ...]]
     */
    public static function availableSlots(string $date, int $duration, ?int $staffId = null, array $options = []): array
    {
        if ($duration <= 0) {
            return [];
        }

        $date = Carbon::parse($date)->toDateString();

        // ── 1. Closures and salon business hours ─────────────────────────
        if (DayOff::whereDate('off_date', $date)->exists()) {
            return [];
        }

        $hours = BusinessHour::where('day_of_week', Carbon::parse($date)->dayOfWeek)->first();

        if (!$hours || $hours->is_closed || !$hours->open_time || !$hours->close_time) {
            return [];
        }

        $salonOpen  = Carbon::parse("$date {$hours->open_time}");
        $salonClose = Carbon::parse("$date {$hours->close_time}");
        $salonBreak = static::range($date, $hours->break_start, $hours->break_end);

        $step = (int) ($options['step'] ?? setting('slot_step', 0));
        if ($step <= 0) {
            $step = $duration;
        }

        $buffer = isset($options['buffer']) ? max(0, (int) $options['buffer']) : static::bufferMinutes();

        /** @var Carbon|null $notBefore */
        $notBefore = $options['not_before'] ?? null;

        // ── 2. Active stylists ───────────────────────────────────────────
        $stylists = Staff::where('is_active', 1)
            ->when($staffId, fn ($q) => $q->where('id', $staffId))
            ->orderBy('name')
            ->get(['id', 'name', 'work_start', 'work_end', 'break_start', 'break_end']);

        if ($staffId && $stylists->isEmpty()) {
            return [];
        }

        $noStaff = $stylists->isEmpty();

        // ── 3. Existing appointments that occupy time ────────────────────
        // Ends already include each appointment's cleanup and any late running.
        $appointments = static::busyRanges($date, $options['ignore_id'] ?? null);

        $maxParallel = (int) setting('max_parallel_bookings', 3);

        // ── 4. Generate slots ────────────────────────────────────────────
        $slots  = [];
        $cursor = $salonOpen->copy();

        while ($cursor->copy()->addMinutes($duration)->lte($salonClose)) {
            $slotStart = $cursor->copy();
            $slotEnd   = $cursor->copy()->addMinutes($duration);
            $cursor->addMinutes($step);

            if ($notBefore && $slotStart->lt($notBefore)) {
                continue;
            }

            // The service itself can't run across the salon lunch break.
            if ($salonBreak && static::overlaps($slotStart, $slotEnd, $salonBreak[0], $salonBreak[1])) {
                continue;
            }

            // Overlap with appointments: the new booking also needs its own cleanup.
            $slotEndBuffered = $slotEnd->copy()->addMinutes($buffer);
            $clashes = fn (array $appt): bool => $appt['start']->lt($slotEndBuffered) && $appt['end']->gt($slotStart);

            if ($noStaff) {
                if ($appointments->filter($clashes)->count() < $maxParallel) {
                    $slots[] = ['start' => $slotStart->format('H:i'), 'end' => $slotEnd->format('H:i'), 'stylists' => []];
                }
                continue;
            }

            $free = [];

            foreach ($stylists as $stylist) {
                if (static::stylistUnavailable($stylist, $date, $slotStart, $slotEnd, $salonOpen, $salonClose)) {
                    continue;
                }

                // Unassigned appointments block everyone — they could land on anyone.
                $busy = $appointments->contains(fn (array $appt): bool => $clashes($appt)
                    && ($appt['staff_id'] === null || (int) $appt['staff_id'] === (int) $stylist->id));

                if (!$busy) {
                    $free[] = ['id' => $stylist->id, 'name' => $stylist->name];
                }
            }

            if ($free) {
                $slots[] = ['start' => $slotStart->format('H:i'), 'end' => $slotEnd->format('H:i'), 'stylists' => $free];
            }
        }

        return $slots;
    }

    // ── Overlap rules ───────────────────────────────────────────────────

    /** Salon default cleanup/turnover minutes after every appointment. */
    public static function bufferMinutes(): int
    {
        return max(0, min(120, (int) setting('buffer_minutes', 0)));
    }

    /** Cleanup after a given service: its own value, or the salon default. */
    public static function bufferFor(?Service $service): int
    {
        if ($service && $service->buffer_minutes !== null) {
            return max(0, min(120, (int) $service->buffer_minutes));
        }

        return static::bufferMinutes();
    }

    /**
     * Time each active appointment occupies on a date.
     *
     * - Cancelled and no-show appointments free their time.
     * - Each appointment keeps its service's cleanup time after it.
     * - In-progress jobs that started late are assumed to finish late, and
     *   a job still running past its end keeps the stylist busy until now.
     *
     * Each item: ['id', 'staff_id', 'customer_id', 'start', 'end' (incl. cleanup), 'raw_end']
     */
    public static function busyRanges(string $date, ?int $ignoreId = null): Collection
    {
        $date    = Carbon::parse($date)->toDateString();
        $default = static::bufferMinutes();
        $now     = now();
        $isToday = $date === $now->toDateString();

        return Appointment::with('service:id,buffer_minutes')
            ->whereDate('appointment_date', $date)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->get(['id', 'service_id', 'staff_id', 'customer_id', 'slot_start', 'slot_end', 'status', 'started_at'])
            ->map(function ($a) use ($date, $default, $now, $isToday) {
                $start = Carbon::parse("$date {$a->slot_start}");
                $end   = Carbon::parse("$date {$a->slot_end}");

                if ($a->status === 'in_progress') {
                    if ($a->started_at) {
                        $projected = $a->started_at->copy()->addMinutes((int) abs($start->diffInMinutes($end)));
                        if ($projected->gt($end)) {
                            $end = $projected;
                        }
                    }
                    if ($isToday && $now->gt($end)) {
                        // Still running: busy until at least the next 5 minutes.
                        $end = $now->copy()->second(0)->addMinutes(5 - ($now->minute % 5));
                    }
                }

                $buffer = ($a->service && $a->service->buffer_minutes !== null) ? (int) $a->service->buffer_minutes : $default;

                return [
                    'id'          => $a->id,
                    'staff_id'    => $a->staff_id,
                    'customer_id' => $a->customer_id,
                    'start'       => $start,
                    'raw_end'     => $end->copy(),
                    'end'         => $end->copy()->addMinutes($buffer),
                ];
            });
    }

    /**
     * Why this stylist can't work $start–$end on $date, or null if they can.
     * Checks working hours, salon hours/break, the stylist's break and other
     * appointments. Returns ['start', 'raw_end', 'reason' => appointment|break|hours].
     */
    public static function stylistConflict(int $staffId, string $date, string $start, string $end, ?int $ignoreId = null, ?int $buffer = null): ?array
    {
        $date = Carbon::parse($date)->toDateString();
        $s = Carbon::parse("$date $start");
        $e = Carbon::parse("$date $end");

        $staff = Staff::find($staffId);
        $hours = BusinessHour::where('day_of_week', Carbon::parse($date)->dayOfWeek)->first();

        if ($staff && $hours && !$hours->is_closed && $hours->open_time) {
            $open  = Carbon::parse("$date {$hours->open_time}");
            $close = Carbon::parse("$date {$hours->close_time}");

            if ($salonBreak = static::range($date, $hours->break_start, $hours->break_end)) {
                if (static::overlaps($s, $e, $salonBreak[0], $salonBreak[1])) {
                    return ['start' => $salonBreak[0], 'raw_end' => $salonBreak[1], 'reason' => 'break'];
                }
            }

            if ($reason = static::stylistUnavailable($staff, $date, $s, $e, $open, $close, true)) {
                return $reason;
            }
        }

        $eBuffered = $e->copy()->addMinutes($buffer ?? static::bufferMinutes());

        $clash = static::busyRanges($date, $ignoreId)->first(function (array $r) use ($staffId, $s, $eBuffered) {
            return ($r['staff_id'] === null || (int) $r['staff_id'] === $staffId)
                && $r['start']->lt($eBuffered) && $r['end']->gt($s);
        });

        return $clash ? $clash + ['reason' => 'appointment'] : null;
    }

    /**
     * An existing appointment for the same customer that overlaps
     * $start–$end on $date (cleanup not applied — it's the customer's time).
     */
    public static function customerConflict(int $customerId, string $date, string $start, string $end, ?int $ignoreId = null): ?array
    {
        $date = Carbon::parse($date)->toDateString();
        $s = Carbon::parse("$date $start");
        $e = Carbon::parse("$date $end");

        return static::busyRanges($date, $ignoreId)->first(function (array $r) use ($customerId, $s, $e) {
            return (int) $r['customer_id'] === $customerId && $r['start']->lt($e) && $r['raw_end']->gt($s);
        });
    }

    /** Human-readable explanation of a stylistConflict() result. */
    public static function describeConflict(array $clash, string $who = 'The stylist'): string
    {
        $from = $clash['start']->format('g:i A');
        $to   = $clash['raw_end']->format('g:i A');

        return match ($clash['reason'] ?? 'appointment') {
            'break' => "$who is on a break from $from to $to.",
            'hours' => "$who isn't working at that time (works $from – $to).",
            default => "$who is busy from $from to $to"
                . (static::bufferMinutes() ? ' plus cleanup time' : '') . '.',
        };
    }

    /**
     * Find one specific slot. Returns the slot array (with free stylists) or
     * null when that start time is not bookable.
     */
    public static function findSlot(string $date, string $start, int $duration, ?int $staffId = null, array $options = []): ?array
    {
        $start = substr($start, 0, 5);

        foreach (static::availableSlots($date, $duration, $staffId, $options) as $slot) {
            if ($slot['start'] === $start) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * Pick the stylist with the fewest bookings that day from a list of
     * free stylists, so "any stylist" bookings are spread evenly.
     */
    public static function pickLeastBusy(array $stylists, string $date): ?int
    {
        if (!$stylists) {
            return null;
        }

        $ids = array_column($stylists, 'id');

        $load = Appointment::whereDate('appointment_date', $date)
            ->whereIn('staff_id', $ids)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->selectRaw('staff_id, COUNT(*) as c')
            ->groupBy('staff_id')
            ->pluck('c', 'staff_id');

        usort($ids, fn ($a, $b) => ($load[$a] ?? 0) <=> ($load[$b] ?? 0));

        return $ids[0];
    }

    /** Maximum number of days ahead customers can book. */
    public static function maxAdvanceDays(): int
    {
        return max(1, min(365, (int) setting('max_advance_days', 90)));
    }

    /**
     * Dates customers can book: today through the max-advance window.
     * Each item: ['date' => 'Y-m-d', 'open' => bool]
     */
    public static function bookableDates(): array
    {
        $days   = static::maxAdvanceDays();
        $start  = now()->startOfDay();
        $end    = $start->copy()->addDays($days - 1);
        $hours  = BusinessHour::all()->keyBy('day_of_week');
        $closed = DayOff::whereBetween('off_date', [$start->toDateString(), $end->toDateString()])
            ->pluck('off_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->flip();

        $out = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $h = $hours[$d->dayOfWeek] ?? null;
            $out[] = [
                'date' => $d->toDateString(),
                'open' => $h && !$h->is_closed && $h->open_time && !isset($closed[$d->toDateString()]),
            ];
        }

        return $out;
    }

    // ── Internals ───────────────────────────────────────────────────────

    /** [start, end] Carbon pair for a time range, or null if incomplete/invalid. */
    private static function range(string $date, ?string $from, ?string $to): ?array
    {
        if (!$from || !$to) {
            return null;
        }

        $a = Carbon::parse("$date $from");
        $b = Carbon::parse("$date $to");

        return $b->gt($a) ? [$a, $b] : null;
    }

    private static function overlaps(Carbon $s, Carbon $e, Carbon $a, Carbon $b): bool
    {
        return $s->lt($b) && $e->gt($a);
    }

    /**
     * Whether a stylist is off for $s–$e (outside working hours or on their break).
     * Returns false/null when available; with $detail, returns a conflict array.
     */
    private static function stylistUnavailable($stylist, string $date, Carbon $s, Carbon $e, Carbon $salonOpen, Carbon $salonClose, bool $detail = false)
    {
        $workStart = $stylist->work_start ? Carbon::parse("$date {$stylist->work_start}") : $salonOpen;
        $workEnd   = $stylist->work_end ? Carbon::parse("$date {$stylist->work_end}") : $salonClose;

        if ($s->lt($workStart) || $e->gt($workEnd)) {
            return $detail ? ['start' => $workStart, 'raw_end' => $workEnd, 'reason' => 'hours'] : true;
        }

        if ($break = static::range($date, $stylist->break_start, $stylist->break_end)) {
            if (static::overlaps($s, $e, $break[0], $break[1])) {
                return $detail ? ['start' => $break[0], 'raw_end' => $break[1], 'reason' => 'break'] : true;
            }
        }

        return $detail ? null : false;
    }
}
