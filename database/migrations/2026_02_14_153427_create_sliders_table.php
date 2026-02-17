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
    Schema::create('sliders', function (Blueprint $table) {
        $table->id();
        $table->string('title', 150);
        $table->string('subtitle', 200)->nullable();
        $table->string('image', 255); // path
        $table->string('button_text', 30)->nullable();
        $table->string('button_link', 255)->nullable();
        $table->unsignedInteger('sort_order')->default(0);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sliders');
    }
};
