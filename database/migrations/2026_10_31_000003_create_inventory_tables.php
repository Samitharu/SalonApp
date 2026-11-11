<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Consumables the salon uses on clients (creams, packs, colour, wax…).
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('brand', 120)->nullable();
            $table->string('unit', 20);                                  // key of Product::UNITS
            $table->decimal('uses_per_unit', 8, 2)->default(1);          // clients one unit serves
            $table->decimal('stock', 12, 3)->default(0);                 // cached running balance
            $table->decimal('low_stock_level', 12, 3)->nullable();       // alert at or below this
            $table->decimal('cost_per_unit', 12, 4)->nullable();         // from the latest purchase
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });

        // How much of each product one client's service uses.
        Schema::create('product_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 10, 3);
            $table->timestamps();

            $table->unique(['product_id', 'service_id']);
        });

        // Every change to stock, so the balance can always be explained.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                                  // opening|purchase|usage|return|adjustment
            $table->decimal('quantity', 12, 3);                          // + in, − out
            $table->decimal('balance_after', 12, 3);
            $table->decimal('total_cost', 12, 2)->nullable();
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->string('supplier', 120)->nullable();
            $table->string('reference', 60)->nullable();                 // bill no. or booking reference
            $table->string('reason', 40)->nullable();                    // adjustments
            $table->string('note', 255)->nullable();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->date('occurred_on')->nullable();
            $table->string('created_by_type', 20)->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name', 120)->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index(['appointment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_service');
        Schema::dropIfExists('products');
    }
};
