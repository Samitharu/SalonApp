<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite does not support modifying enum columns, so we use a string instead
        // This migration adds in_progress to status enum via a separate alter column approach.
        // Since the project uses SQLite, we must handle this via string type or raw SQL.
        // We'll add in_progress support by checking in the app layer — no DB change needed for SQLite.
        // Just add the missing new appointment columns.

        Schema::table('appointments', function (Blueprint $table) {
            // Walk-in flag (instant appointment created by hairdresser scanning own table QR)
            if (!Schema::hasColumn('appointments', 'walk_in')) {
                $table->boolean('walk_in')->default(false)->after('notes');
            }
            if (!Schema::hasColumn('appointments', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('walk_in');
            }
            if (!Schema::hasColumn('appointments', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('started_at');
            }
            if (!Schema::hasColumn('appointments', 'job_number')) {
                $table->string('job_number', 10)->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('appointments', 'payment_status')) {
                $table->string('payment_status', 10)->default('unpaid')->after('job_number');
            }
            if (!Schema::hasColumn('appointments', 'payment_method')) {
                $table->string('payment_method', 10)->nullable()->after('payment_status');
            }
            if (!Schema::hasColumn('appointments', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('payment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn([
                'walk_in', 'started_at', 'completed_at',
                'job_number', 'payment_status', 'payment_method', 'paid_at',
            ]);
        });
    }
};
