<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Each panel is only worked out for someone allowed to see it.
        $canBookings  = admin_can('appointments.view');
        $canRevenue   = admin_can('reports.view');
        $canCustomers = admin_can('customers.view');
        $canStock     = admin_can('stock.view');

        $today      = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd   = now()->endOfMonth()->toDateString();

        $active = fn ($q) => $q->whereNotIn('status', ['cancelled', 'no_show']);

        $todayAppointments = $canBookings
            ? Appointment::with(['customer', 'service', 'staff'])->whereDate('appointment_date', $today)->orderBy('slot_start')->get()
            : collect();

        $stats = [
            'today'          => $todayAppointments->whereNotIn('status', ['cancelled', 'no_show'])->count(),
            'today_done'     => $todayAppointments->where('status', 'completed')->count(),
            'pending'        => $canBookings ? Appointment::where('status', 'pending')->whereDate('appointment_date', '>=', $today)->count() : 0,
            'revenue_today'  => $canRevenue ? $this->revenue($today, $today) : 0,
            'revenue_month'  => $canRevenue ? $this->revenue($monthStart, $monthEnd) : 0,
            'customers'      => $canCustomers ? Customer::count() : 0,
            'customers_new'  => $canCustomers ? Customer::where('created_at', '>=', now()->startOfMonth())->count() : 0,
        ];

        // Bookings per day for the next 7 days (operational look-ahead).
        $counts = !$canBookings ? collect() : Appointment::where($active)
            ->whereBetween('appointment_date', [$today, now()->addDays(6)->toDateString()])
            ->selectRaw('appointment_date as d, COUNT(*) as c')
            ->groupBy('appointment_date')
            ->pluck('c', 'd')
            ->mapWithKeys(fn ($c, $d) => [Carbon::parse($d)->toDateString() => (int) $c]);

        $week = [];
        for ($i = 0; $i < 7; $i++) {
            $d = now()->addDays($i);
            $week[] = [
                'label' => $i === 0 ? 'Today' : $d->format('D'),
                'date'  => $d->format('j M'),
                'count' => $counts[$d->toDateString()] ?? 0,
            ];
        }

        $topServices = !$canRevenue ? collect() : Appointment::where($active)
            ->join('services', 'services.id', '=', 'appointments.service_id')
            ->whereBetween('appointment_date', [$monthStart, $monthEnd])
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('c')
            ->limit(5)
            ->get([DB::raw('services.name as name'), DB::raw('COUNT(*) as c')]);

        $recent = $canBookings ? Appointment::with(['customer', 'service'])->latest()->take(6)->get() : collect();

        // Out of stock first, then lowest relative to the alert level.
        $lowStockCount = $canStock ? Product::lowStock()->count() : 0;
        $lowStock = $canStock ? Product::lowStock()->orderByRaw('CASE WHEN stock <= 0 THEN 0 ELSE 1 END')->orderBy('stock')->take(6)->get() : collect();

        return view('admin.dashboard', compact('stats', 'todayAppointments', 'week', 'topServices', 'recent', 'lowStock', 'lowStockCount', 'canBookings', 'canRevenue', 'canCustomers', 'canStock'));
    }

    /**
     * Value of completed or paid appointments in a date range, including
     * extras. Paid bills use the amount actually charged.
     */
    private function revenue(string $from, string $to): float
    {
        return (float) Appointment::withBillTotals()
            ->whereBetween('appointment_date', [$from, $to])
            ->where(fn ($q) => $q->where('appointments.status', 'completed')->orWhere('appointments.payment_status', 'paid'))
            ->sum(DB::raw(Appointment::BILL_TOTAL_SQL));
    }
}
