<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\SystemLog;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class EmployeeController extends Controller
{
    public function index()
    {
        $employees = Staff::withCount(['appointments as upcoming_count' => function ($q) {
            $q->whereDate('appointment_date', '>=', now()->toDateString())
                ->whereIn('status', ['pending', 'confirmed']);
        }])->withCount('feedback')->withAvg('feedback', 'rating')
            ->orderByDesc('is_active')->orderBy('name')->paginate(20);

        return view('admin.employees.index', compact('employees'));
    }

    public function create()
    {
        return view('admin.employees.form', ['employee' => new Staff(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['table_qr_token'] = Str::random(40);

        $employee = Staff::create($data);
        SystemLog::record('staff.created', "Staff member {$employee->name} added", $employee);

        return redirect()->route('admin.employees.index')
            ->with('success', $employee->name . ' was added. Set their portal login from the team list.');
    }

    public function edit(Staff $employee)
    {
        return view('admin.employees.form', compact('employee'));
    }

    public function update(Request $request, Staff $employee)
    {
        $employee->update($this->validated($request));
        if ($changed = SystemLog::changes($employee)) {
            SystemLog::record('staff.updated', "{$employee->name}'s profile updated: " . implode(', ', array_keys($changed)), $employee, $changed);
        }

        return redirect()->route('admin.employees.index')->with('success', $employee->name . "'s profile was updated.");
    }

    public function destroy(Staff $employee)
    {
        // Keep booking history intact: staff with appointments are deactivated.
        if ($employee->appointments()->exists()) {
            $employee->update(['is_active' => false]);
            SystemLog::record('staff.deactivated', "{$employee->name} deactivated (has appointment history)", $employee, level: 'warning');

            return back()->with('success', $employee->name . ' has appointment history, so they were deactivated instead of deleted.');
        }

        SystemLog::record('staff.deleted', "Staff member {$employee->name} removed", $employee, level: 'warning');
        $employee->delete();

        return back()->with('success', $employee->name . ' was removed.');
    }

    public function setLogin(Request $request, Staff $employee)
    {
        $data = $request->validateWithBag('login', [
            'login_username' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('staff', 'login_username')->ignore($employee->id)],
            'password'       => ['required', 'confirmed', Password::min(8)],
        ], [
            'login_username.regex' => 'Use letters, numbers, dots, dashes or underscores only.',
        ]);

        $employee->update([
            'login_username' => $data['login_username'],
            'password'       => Hash::make($data['password']),
            'table_qr_token' => $employee->table_qr_token ?: Str::random(40),
        ]);
        SystemLog::record('staff.login_set', "Portal login set for {$employee->name} (username {$employee->login_username})", $employee);

        return back()->with('success', 'Portal login saved for ' . $employee->name . '.');
    }

    public function regenerateQr(Staff $employee)
    {
        $hadQr = (bool) $employee->table_qr_token;
        $employee->regenerateTableQr();
        SystemLog::record('staff.qr_generated', ($hadQr ? 'Walk-in QR replaced for ' : 'Walk-in QR created for ') . $employee->name, $employee);

        return back()->with('success', $hadQr
            ? 'A new walk-in QR was generated for ' . $employee->name . '. Reprint it — the old one no longer works.'
            : 'Walk-in QR created for ' . $employee->name . '. Click “QR” to view and print it.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'       => 'required|string|max:120',
            'title'      => 'nullable|string|max:80',
            'phone'      => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'email'      => 'nullable|email|max:120',
            'bio'        => 'nullable|string|max:500',
            'work_start' => 'nullable|date_format:H:i',
            'work_end'    => 'nullable|date_format:H:i|after:work_start',
            'break_start' => 'nullable|required_with:break_end|date_format:H:i',
            'break_end'   => 'nullable|required_with:break_start|date_format:H:i|after:break_start',
            'portal_permissions'   => 'nullable|array',
            'portal_permissions.*' => 'string',
        ], [
            'work_end.after'            => 'End time must be after the start time.',
            'break_end.after'           => 'The break must end after it starts.',
            'break_start.required_with' => 'Enter when the break starts.',
            'break_end.required_with'   => 'Enter when the break ends.',
        ]);

        // A break must sit inside the stylist's working hours when those are set.
        if (!empty($data['break_start']) && !empty($data['work_start']) && !empty($data['work_end'])
            && ($data['break_start'] <= $data['work_start'] || $data['break_end'] >= $data['work_end'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'break_start' => 'The break must be inside working hours (' . $data['work_start'] . ' – ' . $data['work_end'] . ').',
            ]);
        }

        $data['is_active'] = $request->boolean('is_active');
        foreach (['work_start', 'work_end', 'break_start', 'break_end'] as $field) {
            $data[$field] = !empty($data[$field]) ? $data[$field] . ':00' : null;
        }

        // The form marks that it sent these, since no ticks at all posts nothing. All ticked goes back to the default.
        if ($request->has('portal_form')) {
            $chosen = array_values(array_intersect(array_keys(Permissions::PORTAL), $data['portal_permissions'] ?? []));
            $data['portal_permissions'] = count($chosen) === count(Permissions::PORTAL) ? null : $chosen;
        } else {
            unset($data['portal_permissions']);
        }

        return $data;
    }
}
