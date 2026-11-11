<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The original enum created a CHECK constraint (on SQLite) that rejects
        // "in_progress" and "no_show". Use a plain string; the app validates values.
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->change();
            $table->string('type', 20)->default('online')->change();
        });

        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'cancelled_at')) {
                $table->string('cancelled_by', 20)->nullable()->after('paid_at');
                $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            }
            $table->index('job_number');
            $table->index('status');
        });

        // Admin can create services without a category and sliders without images.
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedBigInteger('service_category_id')->nullable()->change();
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
        });

        Schema::table('sliders', function (Blueprint $table) {
            $table->string('image', 255)->nullable()->change();
        });

        Schema::table('staff', function (Blueprint $table) {
            if (!Schema::hasColumn('staff', 'title')) {
                $table->string('title', 80)->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['job_number']);
            $table->dropIndex(['status']);
            $table->dropColumn(['cancelled_by', 'cancelled_at']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
