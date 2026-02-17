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
    Schema::create('appointments', function (Blueprint $table) {
        $table->id();

        $table->string('appointment_no', 20)->unique();

        $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
        $table->foreignId('service_id')->constrained()->cascadeOnDelete();
        $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();

        $table->date('appointment_date');

        $table->time('slot_start');
        $table->time('slot_end');

        $table->enum('type', ['online', 'counter', 'phone'])->default('online');
        $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending');

        $table->text('notes')->nullable();

        $table->unsignedBigInteger('created_by_admin_id')->nullable(); // later for admin login
        $table->timestamps();

        $table->index(['appointment_date', 'slot_start']);
        $table->index(['staff_id', 'appointment_date']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
