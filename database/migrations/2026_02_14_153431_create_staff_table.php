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
    Schema::create('staff', function (Blueprint $table) {
        $table->id();

        $table->string('name', 120);
        $table->string('phone', 20)->nullable();
        $table->string('email', 120)->nullable();

        $table->string('photo', 255)->nullable();
        $table->text('bio')->nullable();

        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
