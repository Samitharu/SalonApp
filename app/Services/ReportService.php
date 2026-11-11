<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Customer;
use App\Models\Feedback;
use App\Models\Payment;
use App\Models\Staff;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admin reports. Every figure uses the same definitions as the dashboard:
 *
 *  - Period: by appointment date (inclusive).
 *  - Revenue: bill total (amount charged when paid, else service + extras)
 *    of appointments that are completed or paid.
 *  - Collected: revenue already paid. Outstanding: completed but unpaid.
 *
 * Queries stick to SQL that works on both MySQL and SQLite; weekday/hour
 * breakdowns are done in PHP.
 */
class ReportService
{
    public const PRESETS = [
        'today'      => 'Today',
        '7d'         => 'Last 7 days',
        '30d'        => 'Last 30 days',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_year'  => 'This year',
        'all'        => 'All time',
    ];

    public function __construct(public readonly Carbon $from, public readonly Carbon $to)
    {
    }

    /** Build from a preset or explicit dates. */
    public static function forRange(?string $preset, ?string $from = null, ?string $to = null): static
    {
        $today = now()->startOfDay();

        if ($from && $to) {
            return new static(Carbon::parse($from)->startOfDay(), Carbon::parse($to)->startOfDay());
        }

        [$start, $end] = match ($preset) {
            'today'      => [$today->copy(), $today->copy()],
            '7d'         => [$today->copy()->subDays(6), $today->copy()],
            '30d'        => [$today->copy()->subDays(29), $today->copy()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'this_year'  => [$today->copy()->startOfYear(), $today->copy()],
            'all'        => [Carbon::parse(Appointment::min('appointment_date') ?? $today)->startOfDay(), $today->copy()],
            default      => [$today->copy()->startOfMonth(), $today->copy()],
        };

        return new static($start, $end);
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    /** Same-length period immediately before this one, for comparisons. */
    public function previous(): static
    {
        $len = $this->days();

        return new static($this->from->copy()->subDays($len), $this->from->copy()->subDay());
    }

    private function range(): array
    {
        return [$this->from->toDateString(), $this->to->toDateString()];
    }

    /** Appointments in the period that count towards revenue. */
    private function earning()
    {
        return Appointment::withBillTotals()
            ->whereBetween('appointments.appointment_date', $this->range())
            ->where(fn ($q) => $q->where('appointments.status', 'completed')->orWhere('appointments.payment_status', 'paid'));
    }

    private function extrasFor($appointmentQuery): float
    {
        return (float) AppointmentItem::whereIn('appointment_id', $appointmentQuery->select('appointments.id'))
            ->sum(DB::raw('price * quantity'));
    }

    // ── Sales ───────────────────────────────────────────────────────────

    public function sales(): array
    {
        $bill = DB::raw(Appointment::BILL_TOTAL_SQL);

        $revenue   = (float) $this->earning()->sum($bill);
        $bills     = (int) $this->earning()->count();
        $extras    = $this->extrasFor($this->earning());
        $previous  = (float) $this->previous()->earning()->sum($bill);

        // Money actually received for these visits — every payment line, so a
        // split bill counts under each method it used, and advances are included.
        $received = Payment::whereIn('appointment_id', $this->earning()->select('appointments.id'));
        $collected = (float) (clone $received)->sum('amount');
        $byMethod = (clone $received)->selectRaw('method, COUNT(*) as c, SUM(amount) as total')->groupBy('method')->get()->keyBy('method');
        $methods = collect(Payment::METHODS)->map(fn ($label, $key) => [
            'label' => $label,
            'count' => (int) ($byMethod[$key]->c ?? 0),
            'total' => round((float) ($byMethod[$key]->total ?? 0), 2),
        ])->all();

        // Advances already received for bookings that haven't been settled yet.
        $held = Payment::where('kind', 'advance')->whereHas('appointment', fn ($q) => $q
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])->where('payment_status', '!=', 'paid'));
        $advancesHeld = ['total' => round((float) (clone $held)->sum('amount'), 2), 'bookings' => (clone $held)->distinct()->count('appointment_id')];

        $daily = $this->earning()
            ->groupBy('appointments.appointment_date')
            ->get([DB::raw('appointments.appointment_date as d'), DB::raw('SUM(' . Appointment::BILL_TOTAL_SQL . ') as v'), DB::raw('COUNT(*) as c')])
            ->mapWithKeys(fn ($r) => [Carbon::parse($r->d)->toDateString() => ['v' => (float) $r->v, 'c' => (int) $r->c]]);

        $topServices = $this->earning()
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get([DB::raw('services.name as name'), DB::raw('COUNT(*) as bookings'), DB::raw('SUM(' . Appointment::BILL_TOTAL_SQL . ') as revenue')]);

        return [
            'revenue'     => $revenue,
            'previous'    => $previous,
            'change'      => $previous > 0 ? round(($revenue - $previous) / $previous * 100, 1) : null,
            'bills'       => $bills,
            'average'     => $bills ? round($revenue / $bills, 2) : 0,
            'collected'   => $collected,
            'outstanding' => max(0, round($revenue - $collected, 2)),
            'extras'      => $extras,
            'services'    => max(0, round($revenue - $extras, 2)),
            'methods'     => $methods,
            'advancesHeld' => $advancesHeld,
            'series'      => $this->series($daily),
            'topServices' => $topServices,
        ];
    }

    // ── Bookings ────────────────────────────────────────────────────────

    public function bookings(): array
    {
        $rows = Appointment::whereBetween('appointment_date', $this->range())
            ->get(['appointment_date', 'slot_start', 'status', 'type', 'walk_in', 'cancelled_by']);

        $total     = $rows->count();
        $byStatus  = collect(Appointment::STATUSES)->mapWithKeys(fn ($s) => [$s => $rows->where('status', $s)->count()]);
        $finished  = $byStatus['completed'] + $byStatus['cancelled'] + $byStatus['no_show'];
        $active    = $rows->whereNotIn('status', ['cancelled', 'no_show']);

        $channels = [
            'Online'      => $rows->where('walk_in', false)->where('type', 'online')->count(),
            'Phone'       => $rows->where('walk_in', false)->where('type', 'phone')->count(),
            'At the desk' => $rows->where('walk_in', false)->where('type', 'counter')->count(),
            'Walk-in'     => $rows->where('walk_in', true)->count(),
        ];

        $weekdays = array_fill_keys(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], 0);
        $hours = [];
        foreach ($active as $r) {
            $weekdays[Carbon::parse($r->appointment_date)->format('D')]++;
            $h = (int) substr((string) $r->slot_start, 0, 2);
            $hours[$h] = ($hours[$h] ?? 0) + 1;
        }
        ksort($hours);

        $daily = $rows->groupBy(fn ($r) => Carbon::parse($r->appointment_date)->toDateString())
            ->map(fn ($g) => ['v' => $g->count(), 'c' => $g->count()]);

        return [
            'total'      => $total,
            'previous'   => Appointment::whereBetween('appointment_date', $this->previous()->range())->count(),
            'status'     => $byStatus->all(),
            'completion' => $finished ? round($byStatus['completed'] / $finished * 100, 1) : null,
            'cancelRate' => $total ? round($byStatus['cancelled'] / $total * 100, 1) : null,
            'noShowRate' => $total ? round($byStatus['no_show'] / $total * 100, 1) : null,
            'cancelledByCustomer' => $rows->where('status', 'cancelled')->where('cancelled_by', 'customer')->count(),
            'channels'   => $channels,
            'weekdays'   => $weekdays,
            'hours'      => collect($hours)->mapWithKeys(fn ($c, $h) => [Carbon::createFromTime($h)->format('g A') => $c])->all(),
            'series'     => $this->series($daily),
        ];
    }

    // ── Staff performance ───────────────────────────────────────────────

    public function staff(): Collection
    {
        $range = $this->range();

        $counts = Appointment::whereBetween('appointment_date', $range)
            ->groupBy('staff_id', 'status')
            ->get([DB::raw('staff_id'), DB::raw('status'), DB::raw('COUNT(*) as c')])
            ->groupBy(fn ($r) => (int) $r->staff_id);

        $money = $this->earning()
            ->groupBy('appointments.staff_id')
            ->get([
                DB::raw('appointments.staff_id as staff_id'),
                DB::raw('SUM(' . Appointment::BILL_TOTAL_SQL . ') as revenue'),
                DB::raw('SUM(COALESCE(ai.extras, 0)) as extras'),
                DB::raw('SUM(services.duration_minutes) as minutes'),
            ])->keyBy(fn ($r) => (int) $r->staff_id);

        $walkins = Appointment::whereBetween('appointment_date', $range)->where('walk_in', true)
            ->selectRaw('staff_id, COUNT(*) as c')->groupBy('staff_id')->get()
            ->mapWithKeys(fn ($r) => [(int) $r->staff_id => (int) $r->c]);

        $ratings = Feedback::join('appointments', 'appointments.id', '=', 'feedback.appointment_id')
            ->whereBetween('appointments.appointment_date', $range)
            ->groupBy('feedback.staff_id')
            ->get([DB::raw('feedback.staff_id as staff_id'), DB::raw('AVG(feedback.rating) as avg'), DB::raw('COUNT(*) as n')])
            ->keyBy(fn ($r) => (int) $r->staff_id);

        $ids = $counts->keys()->merge($money->keys())->unique()->filter();
        $staff = Staff::whereIn('id', $ids)->orWhere('is_active', true)->orderBy('name')->get(['id', 'name', 'title', 'is_active']);

        $rows = $staff->map(fn ($s) => $this->staffRow($s->id, $s->name, $s->title ?: 'Stylist', $counts, $money, $walkins, $ratings, (bool) $s->is_active));

        if ($counts->has(0)) { // bookings nobody was assigned to
            $rows->push($this->staffRow(0, 'Unassigned', 'No stylist chosen', $counts, $money, $walkins, $ratings, true));
        }

        return $rows->sortByDesc('revenue')->values();
    }

    private function staffRow(int $id, string $name, string $title, $counts, $money, $walkins, $ratings, bool $active): array
    {
        $by = ($counts[$id] ?? collect())->pluck('c', 'status');
        $completed = (int) ($by['completed'] ?? 0);
        $cancelled = (int) ($by['cancelled'] ?? 0);
        $noShow    = (int) ($by['no_show'] ?? 0);
        $finished  = $completed + $cancelled + $noShow;

        return [
            'id'         => $id,
            'name'       => $name,
            'title'      => $title,
            'active'     => $active,
            'bookings'   => (int) $by->sum(),
            'completed'  => $completed,
            'cancelled'  => $cancelled,
            'no_show'    => $noShow,
            'completion' => $finished ? round($completed / $finished * 100) : null,
            'walkins'    => (int) ($walkins[$id] ?? 0),
            'revenue'    => round((float) ($money[$id]->revenue ?? 0), 2),
            'extras'     => round((float) ($money[$id]->extras ?? 0), 2),
            'hours'      => round(((int) ($money[$id]->minutes ?? 0)) / 60, 1),
            'rating'     => isset($ratings[$id]) ? round((float) $ratings[$id]->avg, 1) : null,
            'reviews'    => (int) ($ratings[$id]->n ?? 0),
        ];
    }

    // ── Customers ───────────────────────────────────────────────────────

    public function customers(int $minVisits = 3): array
    {
        $range = $this->range();

        $visits = $this->earning()
            ->groupBy('appointments.customer_id')
            ->havingRaw('COUNT(*) >= ?', [$minVisits])
            ->orderByDesc('visits')->orderByDesc('spent')
            ->limit(50)
            ->get([
                DB::raw('appointments.customer_id as customer_id'),
                DB::raw('COUNT(*) as visits'),
                DB::raw('SUM(' . Appointment::BILL_TOTAL_SQL . ') as spent'),
                DB::raw('MAX(appointments.appointment_date) as last_visit'),
            ]);

        $customers = Customer::whereIn('id', $visits->pluck('customer_id'))->get(['id', 'name', 'phone'])->keyBy('id');
        $favourites = $this->favourites($visits->pluck('customer_id')->all());

        $regulars = $visits->map(fn ($v) => [
            'id'         => (int) $v->customer_id,
            'name'       => $customers[$v->customer_id]->name ?? 'Deleted customer',
            'phone'      => $customers[$v->customer_id]->phone ?? '',
            'visits'     => (int) $v->visits,
            'spent'      => round((float) $v->spent, 2),
            'average'    => round((float) $v->spent / max(1, (int) $v->visits), 2),
            'last_visit' => Carbon::parse($v->last_visit)->toDateString(),
            'service'    => $favourites[$v->customer_id]['service'] ?? null,
            'stylist'    => $favourites[$v->customer_id]['stylist'] ?? null,
        ]);

        // Who came in the period, and whether it was their first visit ever.
        $served = $this->earning()->distinct()->pluck('appointments.customer_id');
        $firstVisits = Appointment::whereIn('customer_id', $served)
            ->where(fn ($q) => $q->where('status', 'completed')->orWhere('payment_status', 'paid'))
            ->selectRaw('customer_id, MIN(appointment_date) as first')->groupBy('customer_id')->pluck('first', 'customer_id');
        $new = $firstVisits->filter(fn ($d) => Carbon::parse($d)->toDateString() >= $range[0])->count();

        // Repeat = visited more than once in total, up to the end of the period.
        $repeat = Appointment::whereIn('customer_id', $served)
            ->where(fn ($q) => $q->where('status', 'completed')->orWhere('payment_status', 'paid'))
            ->where('appointment_date', '<=', $range[1])
            ->selectRaw('customer_id, COUNT(*) as c')->groupBy('customer_id')
            ->havingRaw('COUNT(*) >= 2')->get()->count();

        return [
            'served'    => $served->count(),
            'new'       => $new,
            'returning' => $repeat,
            'repeatRate' => $served->count() ? round($repeat / $served->count() * 100) : null,
            'regulars'  => $regulars,
            'lapsed'    => $this->lapsedRegulars($minVisits),
            'minVisits' => $minVisits,
        ];
    }

    /** Most-booked service and stylist per customer (in the period). */
    private function favourites(array $customerIds): array
    {
        if (!$customerIds) {
            return [];
        }

        $rows = $this->earning()
            ->leftJoin('staff', 'staff.id', '=', 'appointments.staff_id')
            ->whereIn('appointments.customer_id', $customerIds)
            ->get([DB::raw('appointments.customer_id as cid'), DB::raw('services.name as service'), DB::raw('staff.name as stylist')]);

        return $rows->groupBy('cid')->map(fn ($g) => [
            'service' => $g->countBy('service')->sortDesc()->keys()->first(),
            'stylist' => $g->whereNotNull('stylist')->countBy('stylist')->sortDesc()->keys()->first(),
        ])->all();
    }

    /** Regulars (all-time) who haven't visited for 60+ days: a win-back list. */
    public function lapsedRegulars(int $minVisits = 3, int $days = 60): Collection
    {
        $cutoff = now()->subDays($days)->toDateString();

        $rows = Appointment::where(fn ($q) => $q->where('status', 'completed')->orWhere('payment_status', 'paid'))
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) >= ?', [$minVisits])
            ->havingRaw('MAX(appointment_date) < ?', [$cutoff])
            ->orderByDesc('visits')
            ->limit(20)
            ->get([DB::raw('customer_id'), DB::raw('COUNT(*) as visits'), DB::raw('MAX(appointment_date) as last_visit')]);

        // Skip anyone who already has an upcoming booking.
        $booked = Appointment::whereIn('customer_id', $rows->pluck('customer_id'))
            ->whereIn('status', ['pending', 'confirmed'])->whereDate('appointment_date', '>=', now()->toDateString())
            ->pluck('customer_id')->flip();

        $customers = Customer::whereIn('id', $rows->pluck('customer_id'))->get(['id', 'name', 'phone'])->keyBy('id');

        return $rows->reject(fn ($r) => $booked->has($r->customer_id))->map(fn ($r) => [
            'id'         => (int) $r->customer_id,
            'name'       => $customers[$r->customer_id]->name ?? 'Deleted customer',
            'phone'      => $customers[$r->customer_id]->phone ?? '',
            'visits'     => (int) $r->visits,
            'last_visit' => Carbon::parse($r->last_visit)->toDateString(),
            'days_ago'   => (int) Carbon::parse($r->last_visit)->diffInDays(now()->startOfDay()),
        ])->values();
    }

    // ── Services ────────────────────────────────────────────────────────

    public function services(): array
    {
        $range = $this->range();

        $booked = Appointment::join('services', 'services.id', '=', 'appointments.service_id')
            ->whereBetween('appointments.appointment_date', $range)
            ->groupBy('services.id', 'services.name', 'services.price', 'services.duration_minutes')
            ->get([
                DB::raw('services.id as id'), DB::raw('services.name as name'),
                DB::raw('services.price as price'), DB::raw('services.duration_minutes as duration'),
                DB::raw('COUNT(*) as bookings'),
                DB::raw("SUM(CASE WHEN appointments.status = 'completed' THEN 1 ELSE 0 END) as completed"),
                DB::raw("SUM(CASE WHEN appointments.status IN ('cancelled', 'no_show') THEN 1 ELSE 0 END) as lost"),
            ])->keyBy('id');

        // Service revenue excludes extras added to the same bill.
        $earned = $this->earning()
            ->groupBy('services.id')
            ->get([DB::raw('services.id as id'), DB::raw('SUM(' . Appointment::BILL_TOTAL_SQL . ' - COALESCE(ai.extras, 0)) as revenue')])
            ->pluck('revenue', 'id');

        $services = $booked->map(fn ($s) => [
            'name'      => $s->name,
            'price'     => (float) $s->price,
            'duration'  => (int) $s->duration,
            'bookings'  => (int) $s->bookings,
            'completed' => (int) $s->completed,
            'lostRate'  => $s->bookings ? round($s->lost / $s->bookings * 100) : 0,
            'revenue'   => round(max(0, (float) ($earned[$s->id] ?? 0)), 2),
        ])->sortByDesc('revenue')->values();

        $extras = AppointmentItem::whereIn('appointment_id', $this->earning()->select('appointments.id'))
            ->groupBy('name')
            ->orderByDesc('revenue')
            ->limit(15)
            ->get([DB::raw('name'), DB::raw('SUM(quantity) as qty'), DB::raw('SUM(price * quantity) as revenue')])
            ->map(fn ($r) => ['name' => $r->name, 'qty' => (int) $r->qty, 'revenue' => round((float) $r->revenue, 2)]);

        return ['services' => $services, 'extras' => $extras];
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /**
     * Fill every day (or month, for long ranges) so gaps show as zero.
     * Returns [['key', 'label', 'value', 'count']].
     */
    private function series(Collection $daily): array
    {
        if ($this->days() > 62) {
            $byMonth = $daily->groupBy(fn ($v, $d) => substr($d, 0, 7), true);
            $out = [];
            foreach (CarbonPeriod::create($this->from->copy()->startOfMonth(), '1 month', $this->to) as $m) {
                $g = $byMonth[$m->format('Y-m')] ?? collect();
                $out[] = ['key' => $m->format('Y-m'), 'label' => $m->format('M Y'), 'short' => $m->format('M'), 'value' => $g->sum('v'), 'count' => $g->sum('c')];
            }

            return $out;
        }

        $out = [];
        foreach (CarbonPeriod::create($this->from, $this->to) as $d) {
            $k = $d->toDateString();
            $out[] = ['key' => $k, 'label' => $d->format('D j M'), 'short' => $d->format('j'), 'value' => $daily[$k]['v'] ?? 0, 'count' => $daily[$k]['c'] ?? 0];
        }

        return $out;
    }
}
