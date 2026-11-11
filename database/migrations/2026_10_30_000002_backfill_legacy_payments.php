<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Paid bills from before "amount_paid" existed have no amount stored, so the
 * previous migration skipped them. Give every paid booking without a payment
 * line one, using the bill total (service price + extras). Safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        $paid = DB::table('appointments')
            ->leftJoin('payments', 'payments.appointment_id', '=', 'appointments.id')
            ->join('services', 'services.id', '=', 'appointments.service_id')
            ->where('appointments.payment_status', 'paid')
            ->whereNull('payments.id')
            ->get(['appointments.id', 'appointments.payment_method', 'appointments.amount_paid', 'appointments.cash_received',
                'appointments.paid_at', 'appointments.updated_at', 'services.price']);

        foreach ($paid as $a) {
            $extras = (float) DB::table('appointment_items')->where('appointment_id', $a->id)->sum(DB::raw('price * quantity'));
            $amount = $a->amount_paid !== null ? (float) $a->amount_paid : round((float) $a->price + $extras, 2);
            $at = $a->paid_at ?? $a->updated_at ?? now();

            DB::table('payments')->insert([
                'appointment_id'   => $a->id,
                'kind'             => 'payment',
                'method'           => in_array($a->payment_method, ['cash', 'card', 'bank_transfer', 'qr'], true) ? $a->payment_method : 'cash',
                'amount'           => $amount,
                'cash_received'    => $a->payment_method === 'cash' ? $a->cash_received : null,
                'received_by'      => 'cashier',
                'received_by_name' => 'Cashier',
                'received_at'      => $at,
                'created_at'       => $at,
                'updated_at'       => $at,
            ]);

            if ($a->amount_paid === null) {
                DB::table('appointments')->where('id', $a->id)->update(['amount_paid' => $amount]);
            }
        }
    }

    public function down(): void
    {
        // Data backfill only; nothing to undo structurally.
    }
};
