<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\BusinessHour;
use App\Models\DayOff;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Day view: one column per stylist, appointments placed on a time grid.
 */
class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['date' => 'nullable|date_format:Y-m-d']);

        $date  = Carbon::parse($request->query('date', now()->toDateString()))->startOfDay();
        $hours = BusinessHour::where('day_of_week', $date->dayOfWeek)->first();
        $closure = DayOff::whereDate('off_date', $date)->first();

        $appointments = Appointment::with(['customer', 'service'])
            ->whereDate('appointment_date', $date)
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('slot_start')
            ->get();

        $staff = Staff::where('is_active', 1)->orderBy('name')->get(['id', 'name', 'work_start', 'work_end', 'break_start', 'break_end']);

        // Grid bounds: business hours, widened to fit any appointment outside them.
        $startMin = $hours && !$hours->is_closed && $hours->open_time ? $this->minutes($hours->open_time) : 9 * 60;
        $endMin   = $hours && !$hours->is_closed && $hours->close_time ? $this->minutes($hours->close_time) : 18 * 60;

        foreach ($appointments as $a) {
            $startMin = min($startMin, $this->minutes($a->slot_start));
            $endMin   = max($endMin, $this->minutes($a->slot_end));
        }

        $startMin = intdiv($startMin, 60) * 60;
        $endMin   = (int) ceil($endMin / 60) * 60;

        // Shaded break bands: salon lunch for everyone + each stylist's own break.
        $salonBreak = ($hours && $hours->break_start && $hours->break_end && !$hours->is_closed)
            ? [$this->minutes($hours->break_start), $this->minutes($hours->break_end), 'Lunch break']
            : null;

        $columns = $staff->map(fn ($s) => [
            'id'     => $s->id,
            'name'   => $s->name,
            'items'  => $appointments->where('staff_id', $s->id)->values(),
            'breaks' => array_values(array_filter([
                $salonBreak,
                ($s->break_start && $s->break_end) ? [$this->minutes($s->break_start), $this->minutes($s->break_end), 'Break'] : null,
            ])),
        ])->values();

        $unassigned = $appointments->whereNull('staff_id')->values();
        if ($unassigned->isNotEmpty()) {
            $columns->push(['id' => null, 'name' => 'Unassigned', 'items' => $unassigned, 'breaks' => array_filter([$salonBreak])]);
        }

        // Appointments for stylists who have since been deactivated.
        $known = $staff->pluck('id')->all();
        $other = $appointments->filter(fn ($a) => $a->staff_id && !in_array($a->staff_id, $known))->values();
        if ($other->isNotEmpty()) {
            $columns->push(['id' => null, 'name' => 'Inactive staff', 'items' => $other, 'breaks' => []]);
        }

        return view('admin.schedule', [
            'date'        => $date,
            'hours'       => $hours,
            'closure'     => $closure,
            'columns'     => $columns,
            'startMin'    => $startMin,
            'endMin'      => $endMin,
            'total'       => $appointments->whereNotIn('status', ['no_show'])->count(),
        ]);
    }

    private function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }
}
