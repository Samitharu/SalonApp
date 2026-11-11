<?php

use App\Models\Setting;

if (!function_exists('setting')) {
    /**
     * Read a salon setting. Values are loaded once per request and the
     * function never throws, so it is safe to call from error pages.
     */
    function setting(string $key, $default = null)
    {
        // Cached on the application container (not a static) so it is scoped
        // to one app instance and never leaks between tests or workers.
        if ($key === '__flush') {
            app()->forgetInstance('salon.settings');
            return null;
        }

        if (!app()->bound('salon.settings')) {
            try {
                $loaded = Setting::pluck('value', 'key')->all();
            } catch (\Throwable $e) {
                $loaded = [];
            }
            app()->instance('salon.settings', $loaded);
        }

        $settings = app('salon.settings');

        $value = $settings[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }
}

if (!function_exists('asset_v')) {
    /**
     * Public asset URL with a cache-busting version based on file mtime.
     */
    function asset_v(string $path): string
    {
        $full = public_path($path);
        $v = is_file($full) ? filemtime($full) : '1';

        return asset($path) . '?v=' . $v;
    }
}

if (!function_exists('upload_limit')) {
    /**
     * Largest upload PHP accepts, in bytes: 'file' per file, 'total' per request.
     * $appMaxKb caps the per-file figure at the app's own validation limit.
     */
    function upload_limit(string $which = 'file', ?int $appMaxKb = null): int
    {
        $toBytes = function (string $v): int {
            $n = (int) $v;
            return match (strtolower(substr(trim($v), -1))) {
                'g' => $n * 1024 ** 3, 'm' => $n * 1024 ** 2, 'k' => $n * 1024, default => $n,
            };
        };
        $post = $toBytes((string) ini_get('post_max_size')) ?: PHP_INT_MAX;
        // Leave room for the other form fields.
        $total = max(0, $post - 64 * 1024);

        if ($which === 'total') {
            return $total;
        }

        $file = min($toBytes((string) ini_get('upload_max_filesize')) ?: PHP_INT_MAX, $total);

        return $appMaxKb ? min($file, $appMaxKb * 1024) : $file;
    }
}

if (!function_exists('admin_can')) {
    /** May the signed-in admin do this? Also available in views as @perm('...'). */
    function admin_can(string ...$permissions): bool
    {
        return (bool) auth('admin')->user()?->hasAnyPermission($permissions);
    }
}

if (!function_exists('mail_is_live')) {
    /** False while mail is only written to the log (or kept in memory), not really sent. */
    function mail_is_live(): bool
    {
        return !in_array(config('mail.default'), ['log', 'array'], true);
    }
}

if (!function_exists('money')) {
    function money($amount): string
    {
        return setting('currency', 'LKR') . ' ' . number_format((float) $amount, 2);
    }
}

if (!function_exists('fmt_time')) {
    /**
     * "14:30:00" → "2:30 PM"
     */
    function fmt_time(?string $time): string
    {
        if (!$time) {
            return '—';
        }

        return \Carbon\Carbon::parse($time)->format('g:i A');
    }
}

if (!function_exists('status_label')) {
    function status_label(?string $status): string
    {
        return match ($status) {
            'pending'     => 'Pending',
            'confirmed'   => 'Confirmed',
            'in_progress' => 'In progress',
            'completed'   => 'Completed',
            'cancelled'   => 'Cancelled',
            'no_show'     => 'No-show',
            default       => ucfirst((string) $status),
        };
    }
}

if (!function_exists('initials')) {
    function initials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $out = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $out .= mb_strtoupper(mb_substr($p, 0, 1));
        }

        return $out ?: '?';
    }
}

if (!function_exists('normalize_phone')) {
    function normalize_phone(?string $phone): string
    {
        $phone = trim((string) $phone);
        $plus = str_starts_with($phone, '+') ? '+' : '';

        return $plus . preg_replace('/\D+/', '', $phone);
    }
}
