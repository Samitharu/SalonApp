<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Salon-wide lunch/break per weekday — nothing can be booked across it.
        Schema::table('business_hours', function (Blueprint $table) {
            $table->time('break_start')->nullable()->after('close_time');
            $table->time('break_end')->nullable()->after('break_start');
        });

        // Individual stylist break (e.g. staggered lunches).
        Schema::table('staff', function (Blueprint $table) {
            $table->time('break_start')->nullable()->after('work_end');
            $table->time('break_end')->nullable()->after('break_start');
        });

        // Cleanup/turnover after this service; null = use the salon default.
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedSmallInteger('buffer_minutes')->nullable()->after('duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('business_hours', fn (Blueprint $t) => $t->dropColumn(['break_start', 'break_end']));
        Schema::table('staff', fn (Blueprint $t) => $t->dropColumn(['break_start', 'break_end']));
        Schema::table('services', fn (Blueprint $t) => $t->dropColumn('buffer_minutes'));
    }
};
