<?php

use App\Models\Admin;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;

/*
| Create (or reset) an admin account. Replaces the old public /setup page.
|   php artisan salon:admin
|   php artisan salon:admin owner@salon.lk --name="Salon Owner"
*/
Artisan::command('salon:admin {email?} {--name=}', function () {
    $email = $this->argument('email') ?: $this->ask('Admin email');
    $name  = $this->option('name') ?: $this->ask('Display name', 'Salon Admin');
    $password = $this->secret('Password (min 8 characters, letters and numbers)');
    $confirm  = $this->secret('Confirm password');

    $validator = Validator::make(
        ['email' => $email, 'name' => $name, 'password' => $password, 'password_confirmation' => $confirm],
        [
            'email'    => 'required|email|max:120',
            'name'     => 'required|string|max:120',
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)->letters()->numbers()],
        ]
    );

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    // This command is the way back in if access is ever lost, so it always makes an active owner.
    $admin = Admin::updateOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make($password), 'is_owner' => true, 'is_active' => true]);

    $this->info(($admin->wasRecentlyCreated ? 'Created' : 'Updated') . " admin account for {$email}. Sign in at /admin/login");

    return 0;
})->purpose('Create or reset a salon admin account');

/*
| Delete system log entries older than the retention period
| (Admin → Settings, default 180 days). Runs nightly via the scheduler:
|   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
*/
Artisan::command('salon:prune-logs {--days=}', function () {
    $days = (int) ($this->option('days') ?: setting('log_retention_days', 180));
    $days = max(30, $days);
    $cutoff = now()->subDays($days);

    $deleted = 0;
    do {
        // Small batches keep table locks short on a busy database.
        $batch = SystemLog::where('created_at', '<', $cutoff)->limit(1000)->delete();
        $deleted += $batch;
    } while ($batch > 0);

    if ($deleted) {
        SystemLog::record('system.logs_pruned', "Deleted {$deleted} system log entries older than {$days} days", null, ['days' => $days, 'deleted' => $deleted]);
    }

    $this->info("Deleted {$deleted} entries older than {$days} days.");

    return 0;
})->purpose('Delete system log entries older than the retention period');

Schedule::command('salon:prune-logs')->dailyAt('03:15')->withoutOverlapping();
