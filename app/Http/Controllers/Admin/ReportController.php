<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public const TABS = [
        'sales'     => ['Sales', 'fa-coins'],
        'bookings'  => ['Bookings', 'fa-calendar-check'],
        'staff'     => ['Staff performance', 'fa-id-badge'],
        'customers' => ['Customers', 'fa-user-group'],
        'services'  => ['Services', 'fa-scissors'],
    ];

    public function show(Request $request, string $tab = 'sales')
    {
        abort_unless(isset(self::TABS[$tab]), 404);

        [$report, $filters] = $this->report($request);

        $data = match ($tab) {
            'sales'     => ['sales' => $report->sales()],
            'bookings'  => ['bookings' => $report->bookings()],
            'staff'     => ['staff' => $report->staff()],
            'customers' => ['customers' => $report->customers($filters['min_visits'])],
            'services'  => $report->services(),
        };

        return view('admin.reports.' . $tab, $data + compact('tab', 'report', 'filters'));
    }

    public function export(Request $request, string $tab): StreamedResponse
    {
        abort_unless(isset(self::TABS[$tab]), 404);

        [$report, $filters] = $this->report($request);
        $period = $report->from->format('j M Y') . ' – ' . $report->to->format('j M Y');

        [$head, $rows] = match ($tab) {
            'sales' => [
                ['Date', 'Bills', 'Revenue'],
                collect($report->sales()['series'])->map(fn ($r) => [$r['key'], $r['count'], $r['value']]),
            ],
            'bookings' => [
                ['Date', 'Bookings'],
                collect($report->bookings()['series'])->map(fn ($r) => [$r['key'], $r['count']]),
            ],
            'staff' => [
                ['Stylist', 'Bookings', 'Completed', 'Cancelled', 'No-shows', 'Completion %', 'Walk-ins', 'Hours', 'Revenue', 'Extras sold', 'Avg rating', 'Reviews'],
                $report->staff()->map(fn ($s) => [$s['name'], $s['bookings'], $s['completed'], $s['cancelled'], $s['no_show'], $s['completion'], $s['walkins'], $s['hours'], $s['revenue'], $s['extras'], $s['rating'], $s['reviews']]),
            ],
            'customers' => [
                ['Customer', 'Phone', 'Visits', 'Total spent', 'Average bill', 'Last visit', 'Favourite service', 'Favourite stylist'],
                $report->customers($filters['min_visits'])['regulars']->map(fn ($c) => [$c['name'], $c['phone'], $c['visits'], $c['spent'], $c['average'], $c['last_visit'], $c['service'], $c['stylist']]),
            ],
            'services' => [
                ['Service', 'Price', 'Minutes', 'Bookings', 'Completed', 'Cancelled/no-show %', 'Revenue'],
                $report->services()['services']->map(fn ($s) => [$s['name'], $s['price'], $s['duration'], $s['bookings'], $s['completed'], $s['lostRate'], $s['revenue']]),
            ],
        };

        SystemLog::record('system.report_exported', self::TABS[$tab][0] . " report exported ({$period})", null, ['report' => $tab] + array_filter($filters));

        return response()->streamDownload(function () use ($head, $rows, $tab, $period) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [self::TABS[$tab][0] . ' report', $period]);
            fputcsv($out, $head);
            foreach ($rows as $row) {
                fputcsv($out, array_map([$this, 'csvSafe'], $row));
            }
            fclose($out);
        }, "{$tab}-report-{$report->from->toDateString()}-to-{$report->to->toDateString()}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: ReportService, 1: array} */
    private function report(Request $request): array
    {
        $filters = $request->validate([
            'range'      => ['nullable', Rule::in(array_keys(ReportService::PRESETS))],
            'from'       => 'nullable|required_with:to|date_format:Y-m-d|before_or_equal:today',
            'to'         => 'nullable|required_with:from|date_format:Y-m-d|after_or_equal:from|before_or_equal:today',
            'min_visits' => 'nullable|integer|min:2|max:50',
        ]);

        $filters['range'] = $filters['range'] ?? (!empty($filters['from']) ? 'custom' : 'this_month');
        $filters['min_visits'] = (int) ($filters['min_visits'] ?? 3);

        $report = ReportService::forRange($filters['range'], $filters['from'] ?? null, $filters['to'] ?? null);

        // Guard against accidental multi-year custom ranges.
        abort_if($report->days() > 3700, 422, 'Please choose a shorter date range.');

        return [$report, $filters];
    }

    /** Prevent spreadsheet formula injection in exported values. */
    private function csvSafe($value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
