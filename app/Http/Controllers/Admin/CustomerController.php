<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['search' => 'nullable|string|max:100']);

        $query = Customer::withCount('appointments')
            ->withMax('appointments', 'appointment_date')
            ->latest();

        if ($request->filled('search')) {
            $search = trim($request->search);
            $digits = preg_replace('/\D+/', '', $search);
            $query->where(function ($q) use ($search, $digits) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
                if (strlen($digits) >= 3) {
                    $q->orWhere('phone', 'like', "%$digits%");
                }
            });
        }

        $customers = $query->paginate(20)->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    public function show(Customer $customer)
    {
        $appointments = $customer->appointments()
            ->with(['service', 'staff', 'items'])
            ->orderByDesc('appointment_date')
            ->orderByDesc('slot_start')
            ->paginate(15);

        $totals = [
            'visits' => $customer->appointments()->where('status', 'completed')->count(),
            'spent'  => (float) \App\Models\Appointment::withBillTotals()
                ->where('appointments.customer_id', $customer->id)
                ->where(fn ($q) => $q->where('appointments.status', 'completed')->orWhere('appointments.payment_status', 'paid'))
                ->sum(\Illuminate\Support\Facades\DB::raw(\App\Models\Appointment::BILL_TOTAL_SQL)),
            'no_shows'  => $customer->appointments()->where('status', 'no_show')->count(),
            'cancelled' => $customer->appointments()->where('status', 'cancelled')->count(),
        ];

        return view('admin.customers.show', compact('customer', 'appointments', 'totals'));
    }

    public function edit(Customer $customer)
    {
        return view('admin.customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:120',
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'email' => 'nullable|email|max:120',
            'notes' => 'nullable|string|max:2000',
        ]);

        $data['phone'] = normalize_phone($data['phone']);

        $request->merge(['phone' => $data['phone']]);
        $request->validate([
            'phone' => [Rule::unique('customers', 'phone')->ignore($customer->id)],
        ], ['phone.unique' => 'Another customer already uses this phone number.']);

        $customer->update($data);
        if ($changed = SystemLog::changes($customer)) {
            SystemLog::record('customer.updated', "Customer {$customer->name} updated: " . implode(', ', array_keys($changed)), $customer, $changed);
        }

        return redirect()->route('admin.customers.show', $customer)->with('success', 'Customer details saved.');
    }

    public function destroy(Customer $customer)
    {
        $name = $customer->name;
        SystemLog::record('customer.deleted', "Customer {$name} and their booking history deleted", $customer,
            ['phone' => $customer->phone, 'bookings' => $customer->appointments()->count()], 'warning');
        $customer->delete(); // appointments cascade

        return redirect()->route('admin.customers.index')->with('success', $name . ' and their booking history were removed.');
    }
}
