<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The app used to run on UTC; it now runs on the salon's time zone
 * (config app.timezone, default Asia/Colombo). Timestamps saved before the
 * switch (created_at, paid_at, completed_at, …) were written in UTC, so shift
 * them by the zone's offset to keep showing the right times.
 *
 * Calendar dates and slot times (appointment_date, slot_start, off_date …)
 * were always entered in salon time and are DATE/TIME columns, so they are
 * left alone. On a fresh database there is nothing to convert.
 */
return new class extends Migration
{
    private const SKIP_TABLES = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    public function up(): void
    {
        $this->shift($this->offsetMinutes());
    }

    public function down(): void
    {
        $this->shift(-$this->offsetMinutes());
    }

    private function offsetMinutes(): int
    {
        $zone = new DateTimeZone(config('app.timezone', 'UTC'));

        return intdiv($zone->getOffset(new DateTime('now', new DateTimeZone('UTC'))), 60);
    }

    /**
     * This app's own tables only. Schema::getTables() lists tables from every
     * database on the server (MySQL), so filter to the connection's schema —
     * otherwise a table name shared with another database is shifted once per
     * database that has it.
     */
    private function tables(string $driver): array
    {
        $schema = match ($driver) {
            'mysql', 'mariadb' => DB::connection()->getDatabaseName(),
            'sqlite'           => 'main',
            'pgsql'            => 'public',
            default            => null,
        };

        return collect(Schema::getTables())
            ->filter(fn ($t) => $schema === null || ($t['schema'] ?? $schema) === $schema)
            ->pluck('name')
            ->unique()
            ->reject(fn ($name) => in_array($name, self::SKIP_TABLES, true))
            ->values()
            ->all();
    }

    private function shift(int $minutes): void
    {
        if ($minutes === 0) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        foreach ($this->tables($driver) as $name) {
            foreach (Schema::getColumns($name) as $column) {
                if (!in_array(strtolower($column['type_name']), ['timestamp', 'datetime'], true)) {
                    continue;
                }

                $col = $column['name'];
                $expr = match ($driver) {
                    'mysql', 'mariadb' => DB::raw("DATE_ADD(`{$col}`, INTERVAL {$minutes} MINUTE)"),
                    'sqlite'           => DB::raw("datetime(\"{$col}\", '" . ($minutes >= 0 ? '+' : '') . "{$minutes} minutes')"),
                    'pgsql'            => DB::raw("\"{$col}\" + interval '{$minutes} minutes'"),
                    default            => null,
                };

                if ($expr) {
                    DB::table($name)->whereNotNull($col)->update([$col => $expr]);
                }
            }
        }
    }
};
