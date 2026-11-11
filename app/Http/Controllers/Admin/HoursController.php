<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\BusinessHour;
use App\Models\DayOff;
use App\Models\Setting;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HoursController extends Controller
{
    public function index()
    {
        $hours = BusinessHour::all()->keyBy('day_of_week');

        $closures = DayOff::whereDate('off_date', '>=', now()->toDateString())
            ->orderBy('off_date')
            ->get();

        // Bookings still sitting on closed days need attention.
        $affected = Appointment::whereIn('appointment_date', $closures->map(fn ($c) => $c->off_date->toDateString()))
            ->whereIn('status', ['pending', 'confirmed'])
            ->selectRaw('appointment_date, COUNT(*) as c')
            ->groupBy('appointment_date')
            ->pluck('c', 'appointment_date')
            ->mapWithKeys(fn ($c, $d) => [\Carbon\Carbon::parse($d)->toDateString() => (int) $c]);

        return view('admin.hours', compact('hours', 'closures', 'affected'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'days'              => 'required|array',
            'days.*.open'       => 'nullable|boolean',
            'days.*.from'       => 'nullable|date_format:H:i',
            'days.*.to'         => 'nullable|date_format:H:i',
            'days.*.break_from' => 'nullable|date_format:H:i',
            'days.*.break_to'   => 'nullable|date_format:H:i',
            'buffer_minutes'    => 'required|in:0,5,10,15,20,30',
        ]);

        // Validate every day first so nothing is half-saved.
        $errors = [];
        $rows = [];

        for ($d = 0; $d <= 6; $d++) {
            $row  = $request->input("days.$d", []);
            $open = !empty($row['open']);
            $from = $row['from'] ?? null;
            $to   = $row['to'] ?? null;
            $bFrom = $row['break_from'] ?? null;
            $bTo   = $row['break_to'] ?? null;
            $day  = BusinessHour::DAYS[$d];

            if ($open && (!$from || !$to || $from >= $to)) {
                $errors["days.$d.to"] = "$day: closing time must be after opening time.";
                continue;
            }

            if ($open && ($bFrom || $bTo)) {
                if (!$bFrom || !$bTo || $bFrom >= $bTo) {
                    $errors["days.$d.break_to"] = "$day: enter both break times, with the end after the start.";
                    continue;
                }
                if ($bFrom <= $from || $bTo >= $to) {
                    $errors["days.$d.break_to"] = "$day: the break must be inside opening hours ($from – $to).";
                    continue;
                }
            }

            $rows[$d] = [
                'is_closed'   => !$open,
                'open_time'   => $open ? $from . ':00' : null,
                'close_time'  => $open ? $to . ':00' : null,
                'break_start' => $open && $bFrom ? $bFrom . ':00' : null,
                'break_end'   => $open && $bTo ? $bTo . ':00' : null,
            ];
        }

        if ($errors) {
            return back()->withErrors($errors)->withInput();
        }

        $changed = [];
        foreach ($rows as $d => $values) {
            $hour = BusinessHour::updateOrCreate(['day_of_week' => $d], $values);
            if ($hour->wasChanged() || $hour->wasRecentlyCreated) {
                $changed[BusinessHour::DAYS[$d]] = $values['is_closed'] ? 'Closed'
                    : substr($values['open_time'], 0, 5) . '–' . substr($values['close_time'], 0, 5)
                        . ($values['break_start'] ? ', break ' . substr($values['break_start'], 0, 5) . '–' . substr($values['break_end'], 0, 5) : '');
            }
        }

        $oldBuffer = (string) setting('buffer_minutes', '0');
        Setting::updateOrCreate(['key' => 'buffer_minutes'], ['value' => (string) $request->input('buffer_minutes')]);
        setting('__flush');

        if ($oldBuffer !== (string) $request->input('buffer_minutes')) {
            $changed['Cleanup time'] = ['from' => $oldBuffer . ' min', 'to' => $request->input('buffer_minutes') . ' min'];
        }
        if ($changed) {
            SystemLog::record('settings.hours_updated', 'Opening hours updated: ' . implode(', ', array_keys($changed)), null, $changed);
        }

        return back()->with('success', 'Opening hours, breaks and cleanup time saved.');
    }

    public function storeClosure(Request $request)
    {
        $data = $request->validateWithBag('closure', [
            'off_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', Rule::unique('days_off', 'off_date')],
            'reason'   => 'nullable|string|max:150',
        ], [
            'off_date.unique' => 'That date is already marked as closed.',
        ]);

        $dayOff = DayOff::create($data);

        $label = \Carbon\Carbon::parse($data['off_date'])->format('D j M Y');
        SystemLog::record('settings.closure_added', "Salon closure added for {$label}" . (!empty($data['reason']) ? " ({$data['reason']})" : ''), $dayOff);
        $existing = Appointment::whereDate('appointment_date', $data['off_date'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();

        if ($existing) {
            return back()->with('error', "Closure added for {$label}, but {$existing} "
                . \Illuminate\Support\Str::plural('booking', $existing) . ' already exist on that day. Contact those customers and cancel or move them.');
        }

        return back()->with('success', 'Closure added for ' . $label . '. Online booking is now blocked for that day.');
    }

    public function destroyClosure(DayOff $dayOff)
    {
        SystemLog::record('settings.closure_removed', 'Salon closure removed for ' . \Carbon\Carbon::parse($dayOff->off_date)->format('D j M Y'), $dayOff);
        $dayOff->delete();

        return back()->with('success', 'Closure removed.');
    }
}
