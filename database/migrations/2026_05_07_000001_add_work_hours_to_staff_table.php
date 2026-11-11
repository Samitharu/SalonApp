<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            // Per-stylist working hours — null means "use business hours"
            $table->time('work_start')->nullable()->after('is_active');
            $table->time('work_end')->nullable()->after('work_start');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['work_start', 'work_end']);
        });
    }
};
