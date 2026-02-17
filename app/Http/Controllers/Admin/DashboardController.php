<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();

        $appointmentsToday = Appointment::where('appointment_date', $today)->count();

        return view('admin.dashboard', compact('appointmentsToday'));
    }
}
