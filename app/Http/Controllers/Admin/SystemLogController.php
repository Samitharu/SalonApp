<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only view of the system log. Entries can't be edited or deleted
 * here; old ones are removed only by the retention job (salon:prune-logs).
 */
class SystemLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->filters($request);

        $logs = $this->query($filters)->paginate(50)->withQueryString();

        // Which referenced bookings still exist (deleted ones can't be linked).
        $liveAppointments = Appointment::whereIn('id', $logs->where('subject_type', 'Appointment')->pluck('subject_id')->unique())
            ->pluck('id')->flip();

        $summary = [
            'today'        => SystemLog::where('created_at', '>=', now()->startOfDay())->count(),
            'errors'       => SystemLog::whereIn('level', ['error', 'critical'])->where('created_at', '>=', now()->subDays(7))->count(),
            'failed_login' => SystemLog::where('action', 'auth.login_failed')->where('created_at', '>=', now()->subDay())->count(),
            'retention'    => (int) setting('log_retention_days', 180),
        ];

        return view('admin.logs.index', compact('logs', 'filters', 'summary', 'liveAppointments'));
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $query = $this->query($filters);

        SystemLog::record('system.log_exported', 'System log exported to CSV', null, array_filter($filters));

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Time', 'Level', 'Category', 'Action', 'Description', 'By', 'Subject', 'IP', 'Details']);

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $log) {
                    fputcsv($out, array_map([$this, 'csvSafe'], [
                        $log->created_at->format('Y-m-d H:i:s'),
                        $log->level,
                        $log->categoryLabel(),
                        $log->action,
                        $log->description,
                        $log->actorLabel(),
                        $log->subject_type ? $log->subject_type . ' #' . $log->subject_id : '',
                        $log->ip_address,
                        $log->properties ? json_encode($log->properties, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '',
                    ]));
                }
            });

            fclose($out);
        }, 'system-log-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'q'        => 'nullable|string|max:100',
            'category' => ['nullable', Rule::in(array_keys(SystemLog::CATEGORIES))],
            'level'    => ['nullable', Rule::in(SystemLog::LEVELS)],
            'actor'    => ['nullable', Rule::in(SystemLog::ACTOR_TYPES)],
            'from'     => 'nullable|date_format:Y-m-d',
            'to'       => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'subject'  => 'nullable|string|max:40',
            'id'       => 'nullable|integer',
        ]);
    }

    private function query(array $f)
    {
        $query = SystemLog::query()->latest('id');

        if (!empty($f['q'])) {
            $term = '%' . trim($f['q']) . '%';
            $query->where(fn ($q) => $q->where('description', 'like', $term)
                ->orWhere('actor_name', 'like', $term)
                ->orWhere('action', 'like', $term)
                ->orWhere('ip_address', 'like', $term));
        }

        foreach (['category' => 'category', 'level' => 'level', 'actor' => 'actor_type'] as $key => $column) {
            if (!empty($f[$key])) {
                $query->where($column, $f[$key]);
            }
        }

        if (!empty($f['from'])) {
            $query->where('created_at', '>=', $f['from'] . ' 00:00:00');
        }
        if (!empty($f['to'])) {
            $query->where('created_at', '<=', $f['to'] . ' 23:59:59');
        }
        if (!empty($f['subject']) && !empty($f['id'])) {
            $query->where('subject_type', $f['subject'])->where('subject_id', $f['id']);
        }

        return $query;
    }

    /** Prevent spreadsheet formula injection in exported values. */
    private function csvSafe($value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
