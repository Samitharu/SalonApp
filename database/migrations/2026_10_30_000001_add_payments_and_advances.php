<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every amount received for a booking: advances before the visit and the
        // final settlement, each line with its own method (split payments).
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);                       // advance | payment
            $table->string('method', 20);                     // cash | card | bank_transfer | qr
            $table->decimal('amount', 10, 2);
            $table->decimal('cash_received', 10, 2)->nullable(); // cash tendered, for change
            $table->string('reference', 100)->nullable();     // bank / QR transaction reference
            $table->string('note', 255)->nullable();
            $table->string('received_by', 20)->nullable();    // admin | cashier | staff
            $table->string('received_by_name', 120)->nullable();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['appointment_id', 'kind']);
            $table->index(['method', 'received_at']);
        });

        // "bank_transfer" and "split" don't fit the old 10-character column.
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable()->change();
        });

        // Optional advance for special packages (e.g. bridal dressing).
        Schema::table('services', function (Blueprint $table) {
            $table->string('advance_type', 10)->default('none')->after('buffer_minutes'); // none | fixed | percent
            $table->decimal('advance_value', 10, 2)->default(0)->after('advance_type');
        });

        // Bills paid before this change become one payment line each.
        $paid = DB::table('appointments')->where('payment_status', 'paid')->whereNotNull('amount_paid')
            ->get(['id', 'payment_method', 'amount_paid', 'cash_received', 'paid_at', 'updated_at']);

        foreach ($paid as $a) {
            $at = $a->paid_at ?? $a->updated_at ?? now();
            DB::table('payments')->insert([
                'appointment_id' => $a->id,
                'kind'           => 'payment',
                'method'         => in_array($a->payment_method, ['cash', 'card'], true) ? $a->payment_method : 'cash',
                'amount'         => $a->amount_paid,
                'cash_received'  => $a->payment_method === 'cash' ? $a->cash_received : null,
                'received_by'    => 'cashier',
                'received_by_name' => 'Cashier',
                'received_at'    => $at,
                'created_at'     => $at,
                'updated_at'     => $at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['advance_type', 'advance_value']);
        });
        Schema::dropIfExists('payments');
    }
};
