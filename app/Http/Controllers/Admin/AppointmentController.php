<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Appointment::with(['customer', 'service', 'staff'])
            ->latest();

        if ($request->date) {
            $query->where('appointment_date', $request->date);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $appointments = $query->paginate(15);

        return view('admin.appointments.index', compact('appointments'));
    }

    public function updateStatus(Request $request, Appointment $appointment)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,completed,cancelled'
        ]);

        $appointment->update([
            'status' => $request->status
        ]);

        return back()->with('success', 'Status updated');
    }

    public function destroy(Appointment $appointment)
    {
        $appointment->delete();

        return back()->with('success', 'Deleted');
    }
}