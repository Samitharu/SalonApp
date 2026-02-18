<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index()
    {
        $employees = Staff::latest()->paginate(10);
        return view('admin.employees.index', compact('employees'));
    }

    public function create()
    {
        return view('admin.employees.create');
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|max:120',
            'phone' => 'nullable|max:20',
            'email' => 'nullable|email|max:120',
            'bio' => 'nullable'
        ]);

        Staff::create($data);

        return redirect()->route('employees.index')
            ->with('success', 'Employee created');
    }

    public function edit(Staff $employee)
    {
        return view('admin.employees.edit', compact('employee'));
    }

    public function update(Request $r, Staff $employee)
    {
        $data = $r->validate([
            'name' => 'required|max:120',
            'phone' => 'nullable|max:20',
            'email' => 'nullable|email|max:120',
            'bio' => 'nullable'
        ]);

        $employee->update($data);

        return redirect()->route('employees.index')
            ->with('success', 'Employee updated');
    }

    public function destroy(Staff $employee)
    {
        $employee->delete();

        return back()->with('success', 'Employee deleted');
    }
}