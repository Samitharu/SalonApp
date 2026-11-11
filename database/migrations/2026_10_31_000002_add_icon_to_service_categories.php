<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Icon shown on the homepage category strip (key from ServiceCategory::ICONS).
        Schema::table('service_categories', function (Blueprint $table) {
            $table->string('icon', 40)->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
