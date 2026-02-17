<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Service;
use App\Models\Customer;
use App\Models\Appointment;
use App\Models\Staff;
use App\Services\SlotService;

class BookingController extends Controller
{
    
    public function index()
    {
        $services = Service::where('is_active', 1)->get();
        $staff = Staff::where('is_active', 1)->get();
        return view('booking.index', compact('services', 'staff'));
    }

    /**
     * AJAX: get available slots
     */
    public function availableSlots(Request $request)
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'date' => 'required|date',
        ]);

        $service = Service::findOrFail($request->service_id);

        $slots = SlotService::availableSlots(
            $request->date,
            $service->duration_minutes,
            $request->staff_id ?? null
        );

        return response()->json($slots);
    }

    /**
     * Store booking with HARD conflict lock
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|max:120',
            'phone' => 'required|max:20',
            'service_id' => 'required|exists:services,id',
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
        ]);

        // create or reuse customer
        $customer = Customer::firstOrCreate(
            ['phone' => $request->phone],
            ['name' => $request->name]
        );

        // HARD CONFLICT LOCK
        $conflict = Appointment::where('appointment_date', $request->date)
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($request) {
                $q->whereBetween('slot_start', [$request->start_time, $request->end_time])
                  ->orWhereBetween('slot_end', [$request->start_time, $request->end_time]);
            });

        if ($request->staff_id) {
            $conflict->where('staff_id', $request->staff_id);

            if ($conflict->exists()) {
                return response()->json([
                    'error' => 'This staff is already booked'
                ], 422);
            }
        } else {
            if ($conflict->count() >= setting('max_parallel_bookings', 3)) {
                return response()->json([
                    'error' => 'Salon fully booked at this time'
                ], 422);
            }
        }

        Appointment::create([
            'appointment_no' => strtoupper(Str::random(8)),
            'customer_id' => $customer->id,
            'service_id' => $request->service_id,
            'appointment_date' => $request->date,
            'slot_start' => $request->start_time,
            'slot_end' => $request->end_time,
            'type' => 'online',
            'status' => 'pending',
        ]);

        return response()->json(['success' => true]);
    }
}