<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\SystemLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Money received for a booking. No gateway: staff record what was paid.
 *
 *  - advance:  before the visit, any amount up to the balance (e.g. bridal
 *              deposit by bank transfer). Bill stays open → status "partial".
 *  - payment:  the settlement at the visit. Lines (split across methods)
 *              must add up exactly to the balance due → status "paid",
 *              appointment completed.
 */
class PaymentService
{
    public const MAX_LINES = 4;

    public static function rules(): array
    {
        return [
            'lines'                 => 'present|array|max:' . static::MAX_LINES,
            'lines.*.method'        => ['required', Rule::in(array_keys(Payment::METHODS))],
            'lines.*.amount'        => 'required|numeric|min:0.01|max:100000000',
            'lines.*.reference'     => 'nullable|string|max:100',
            'lines.*.cash_received' => 'nullable|numeric|min:0|max:100000000',
            'note'                  => 'nullable|string|max:255',
        ];
    }

    public const MESSAGES = [
        'lines.*.amount.min'  => 'Each payment line needs an amount.',
        'lines.*.method.in'   => 'Choose cash, card, bank transfer or QR.',
        'lines.max'           => 'Split a bill across at most 4 payments.',
    ];

    /**
     * @param array $lines [['method' => 'cash', 'amount' => 1000, 'cash_received' => 1500, 'reference' => null], …]
     * @param float|null $expectedBalance what the screen showed; a mismatch throws BillChanged
     *
     * @throws RuntimeException|BillChanged
     */
    public static function record(Appointment $appointment, string $kind, array $lines, string $by, ?string $byName = null, ?float $expectedBalance = null, ?string $note = null): Appointment
    {
        if (!in_array($kind, Payment::KINDS, true)) {
            throw new RuntimeException('Unknown payment type.');
        }

        return DB::transaction(function () use ($appointment, $kind, $lines, $by, $byName, $expectedBalance, $note) {
            // Lock the row so two tills can't take the same money.
            $a = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            $a->load(['service', 'items', 'payments', 'customer']);

            if (in_array($a->status, ['cancelled', 'no_show'], true)) {
                throw new RuntimeException('Cancelled appointments can’t take payments.');
            }
            if ($a->payment_status === 'paid') {
                throw new RuntimeException('This appointment has already been paid.');
            }

            $balance = $a->balanceDue();

            if ($expectedBalance !== null && abs($expectedBalance - $balance) > 0.009) {
                throw new BillChanged($a, 'The amount due changed to ' . money($balance) . ' while this was open. Please check it and confirm again.');
            }

            $clean = static::normalise($lines);
            $sum = round(array_sum(array_column($clean, 'amount')), 2);

            if ($kind === 'advance') {
                if ($a->status === 'completed') {
                    throw new RuntimeException('This visit is already completed — take the payment at the cashier instead of an advance.');
                }
                if ($sum <= 0) {
                    throw new RuntimeException('Enter the advance amount.');
                }
                if ($sum > $balance + 0.009) {
                    throw new RuntimeException('The advance can’t be more than the amount due (' . money($balance) . ').');
                }
            } elseif (abs($sum - $balance) > 0.009) {
                throw new RuntimeException($sum < $balance
                    ? 'The payments add up to ' . money($sum) . ' — ' . money($balance - $sum) . ' is still due.'
                    : 'The payments add up to ' . money($sum) . ', more than the ' . money($balance) . ' due.');
            }

            foreach ($clean as $line) {
                $a->payments()->create($line + [
                    'kind'             => $kind,
                    'note'             => $note,
                    'received_by'      => $by,
                    'received_by_name' => $byName,
                    'received_at'      => now(),
                ]);
            }

            $a->load('payments');
            $methods = $a->payments->pluck('method')->unique()->values();
            $cashTendered = collect($clean)->where('method', 'cash')->sum(fn ($l) => $l['cash_received'] ?? $l['amount']);

            $changes = [
                'amount_paid'    => $a->paidSoFar(),
                'payment_method' => $methods->count() === 1 ? $methods->first() : ($methods->isEmpty() ? null : 'split'),
            ];

            if ($kind === 'payment') {
                $changes += [
                    'payment_status' => 'paid',
                    'paid_at'        => now(),
                    'cash_received'  => $cashTendered > 0 ? round($cashTendered, 2) : null,
                    'status'         => 'completed',
                    'completed_at'   => $a->completed_at ?? now(),
                    'job_number'     => $a->job_number ?: Appointment::generateJobNumber(),
                ];
            } else {
                $changes['payment_status'] = 'partial';
            }

            $a->update($changes);

            $summary = collect($clean)->map(fn ($l) => Payment::methodLabel($l['method']) . ' ' . money($l['amount']))->implode(' + ');
            SystemLog::record(
                $kind === 'advance' ? 'payment.advance' : 'payment.received',
                ($kind === 'advance' ? 'Advance of ' : 'Payment of ') . money($sum) . " for {$a->appointment_no} ({$summary})"
                    . ($kind === 'payment' && $a->advancePaid() > 0 ? ' after ' . money($a->advancePaid()) . ' advance' : ''),
                $a,
                ['amount' => $sum, 'lines' => $clean, 'balance_before' => $balance, 'kind' => $kind],
            );

            return $a->fresh(['service', 'items', 'payments', 'customer', 'staff']);
        });
    }

    /** Remove a payment recorded by mistake (or refunded). Recomputes the booking. */
    public static function void(Payment $payment): Appointment
    {
        return DB::transaction(function () use ($payment) {
            $a = Appointment::whereKey($payment->appointment_id)->lockForUpdate()->firstOrFail();

            SystemLog::record('payment.voided', 'Removed ' . strtolower($payment->kind === 'advance' ? 'advance' : 'payment') . ' of ' . money($payment->amount)
                . ' (' . $payment->label() . ($payment->reference ? ', ref ' . $payment->reference : '') . ") from {$a->appointment_no}", $a,
                ['amount' => (float) $payment->amount, 'method' => $payment->method, 'reference' => $payment->reference, 'kind' => $payment->kind], 'warning');

            $payment->delete();
            $a->load('payments');

            $left = $a->payments;
            $methods = $left->pluck('method')->unique()->values();
            $a->update([
                'amount_paid'    => $left->isEmpty() ? null : $a->paidSoFar(),
                'payment_method' => $methods->count() === 1 ? $methods->first() : ($methods->isEmpty() ? null : 'split'),
                'payment_status' => $left->isEmpty() ? 'unpaid' : 'partial',
                'paid_at'        => null,
                'cash_received'  => null,
            ]);

            return $a->fresh(['service', 'items', 'payments']);
        });
    }

    /** Validate/clean payment lines. Only one cash line; tendered cash must cover it. */
    private static function normalise(array $lines): array
    {
        $out = [];
        $cash = 0;

        foreach ($lines as $line) {
            $method = (string) ($line['method'] ?? '');
            $amount = round((float) ($line['amount'] ?? 0), 2);

            if (!isset(Payment::METHODS[$method])) {
                throw new RuntimeException('Choose cash, card, bank transfer or QR for each payment.');
            }
            if ($amount <= 0) {
                throw new RuntimeException('Each payment line needs an amount.');
            }

            $row = ['method' => $method, 'amount' => $amount, 'reference' => null, 'cash_received' => null];

            if ($method === 'cash') {
                if (++$cash > 1) {
                    throw new RuntimeException('Put all the cash in one line.');
                }
                if (isset($line['cash_received']) && $line['cash_received'] !== '' && $line['cash_received'] !== null) {
                    $tendered = round((float) $line['cash_received'], 2);
                    if ($tendered + 0.009 < $amount) {
                        throw new RuntimeException('Cash received is less than the cash amount (' . money($amount) . ').');
                    }
                    $row['cash_received'] = $tendered;
                }
            } else {
                $ref = trim((string) ($line['reference'] ?? ''));
                $row['reference'] = $ref !== '' ? mb_substr($ref, 0, 100) : null;
            }

            $out[] = $row;
        }

        return $out;
    }
}
