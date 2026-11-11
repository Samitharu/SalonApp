<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extra charges (add-ons) attached to an appointment on top of its main service.
        // Name and price are copied at the time they're added so receipts never change.
        Schema::create('appointment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->decimal('price', 10, 2);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('added_by', 20)->nullable();       // admin | stylist | cashier
            $table->string('added_by_name', 120)->nullable();
            $table->timestamps();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->decimal('amount_paid', 10, 2)->nullable()->after('paid_at');
            $table->decimal('cash_received', 10, 2)->nullable()->after('amount_paid');
        });

        // Add-on only services: chargeable as extras, not bookable on their own.
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('is_addon')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_items');

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['amount_paid', 'cash_received']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('is_addon');
        });
    }
};
