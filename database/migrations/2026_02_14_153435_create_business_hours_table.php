<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('business_hours', function (Blueprint $table) {
        $table->id();

        $table->tinyInteger('day_of_week'); 
        // 0 = Sunday ... 6 = Saturday

        $table->time('open_time')->nullable();
        $table->time('close_time')->nullable();

        $table->boolean('is_closed')->default(false);

        $table->timestamps();

        $table->unique('day_of_week');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_hours');
    }
};
