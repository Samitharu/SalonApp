<?php

namespace App\Services;

use App\Models\BusinessHour;
use App\Models\Appointment;
use App\Models\Staff;
use Carbon\Carbon;

class SlotService
{
    public static function availableSlots($date, $duration, $staffId = null)
{
    $day = Carbon::parse($date)->dayOfWeek;

    $hours = BusinessHour::where('day_of_week', $day)->first();
    if (!$hours || $hours->is_closed) return [];

    $interval = (int) setting('slot_interval_minutes', 15);

    $start = Carbon::parse("$date {$hours->open_time}");
    $end   = Carbon::parse("$date {$hours->close_time}");

    $slots = [];

    $totalStaff = Staff::where('is_active', 1)->count();

// fallback if no staff yet
if ($totalStaff === 0) {
    $totalStaff = (int) setting('max_parallel_bookings', 3);
}

    while ($start->copy()->addMinutes($duration) <= $end) {

        $slotStart = $start->format('H:i:s');
        $slotEnd   = $start->copy()->addMinutes($duration)->format('H:i:s');

        $appointments = Appointment::where('appointment_date', $date)
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($slotStart, $slotEnd) {
                $q->where('slot_start', '<', $slotEnd)
                  ->where('slot_end', '>', $slotStart);
            })
            ->get();

        if ($staffId) {
            // check specific staff conflict
            $conflict = $appointments
                ->where('staff_id', $staffId)
                ->count();

            if ($conflict == 0) {
                $slots[] = [$slotStart, $slotEnd];
            }

        } else {
            // count how many bookings exist in that slot
            $busyCount = $appointments->count();

            // allow up to total staff parallel bookings
            if ($busyCount < $totalStaff) {
                $slots[] = [$slotStart, $slotEnd];
            }
        }

        $start->addMinutes($interval);
    }

    return $slots;
}
}