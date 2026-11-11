<?php

namespace App\Logging;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

/**
 * Copies application errors (level "error" and above by default) into the
 * system_logs table so admins can see them without server access. The file
 * log stays the source of truth if the database itself is the problem.
 */
class DatabaseLogHandler extends AbstractProcessingHandler
{
    private static bool $writing = false;

    public function __construct(int|string|Level $level = Level::Error, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        if (self::$writing) {
            return; // a failure while logging must not log itself again
        }

        self::$writing = true;

        try {
            $context = $record->context;
            $props = [];

            if (($e = $context['exception'] ?? null) instanceof Throwable) {
                $props['exception'] = get_class($e);
                $props['file'] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $e->getFile()) . ':' . $e->getLine();
                $props['trace'] = collect(explode("\n", $e->getTraceAsString()))->take(12)->implode("\n");
                unset($context['exception']);
            }

            if ($context) {
                $props['context'] = json_decode(json_encode($context, JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '[]', true);
            }

            $request = app()->runningInConsole() && !app()->runningUnitTests() ? null : request();
            if ($request) {
                $props['url'] = $request->method() . ' ' . Str::limit($request->fullUrl(), 300);
            }

            DB::table('system_logs')->insert([
                'level'       => $record->level->value >= Level::Critical->value ? 'critical' : ($record->level->value >= Level::Error->value ? 'error' : 'warning'),
                'category'    => 'system',
                'action'      => 'system.' . strtolower($record->level->getName()),
                'description' => Str::limit($record->message, 497),
                'actor_type'  => $request ? null : 'system',
                'actor_name'  => $request ? null : 'System',
                'properties'  => json_encode($props, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'ip_address'  => $request?->ip(),
                'user_agent'  => $request ? Str::limit((string) $request->userAgent(), 252) : null,
                'created_at'  => now(),
            ]);
        } catch (Throwable) {
            // Database unavailable or table missing: the file channel still has it.
        } finally {
            self::$writing = false;
        }
    }
}
