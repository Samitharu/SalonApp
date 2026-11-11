<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\Request;

/**
 * Printable receipt for a visit. Served to the cashier station and to admins
 * (each route sits behind its own access middleware).
 */
class ReceiptController extends Controller
{
    public function show(Request $request, Appointment $appointment)
    {
        abort_if(in_array($appointment->status, ['cancelled', 'no_show'], true) && !$appointment->payments()->exists(), 404);

        $appointment->load(['customer', 'service', 'staff', 'items', 'payments' => fn ($q) => $q->orderBy('received_at')->orderBy('id')]);
        $a = $appointment;

        $lines = collect([[
            'name'     => $a->service->name ?? 'Service',
            'detail'   => collect([$a->service?->durationLabel(), $a->staff?->name])->filter()->implode(' · '),
            'quantity' => 1,
            'price'    => (float) ($a->service->price ?? 0),
            'total'    => (float) ($a->service->price ?? 0),
        ]])->concat($a->items->map(fn ($i) => [
            'name'     => $i->name,
            'detail'   => null,
            'quantity' => (int) $i->quantity,
            'price'    => (float) $i->price,
            'total'    => $i->lineTotal(),
        ]));

        $paid = $a->paidSoFar();
        $kind = $a->payment_status === 'paid' ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
        $issuedAt = $kind === 'paid' ? ($a->paid_at ?? now()) : ($a->payments->last()?->received_at ?? now());

        return view('receipts.show', [
            'a'         => $a,
            'lines'     => $lines,
            'subtotal'  => round($lines->sum('total'), 2),
            'discount'  => $a->discount(),
            'total'     => $a->total(),
            'paid'      => $paid,
            'balance'   => $a->balanceDue(),
            'change'    => round($a->payments->sum(fn ($p) => $p->change()), 2),
            'kind'      => $kind,
            'issuedAt'  => $issuedAt,
            'receiptNo' => $a->job_number ?: $a->appointment_no,
            'phone'     => static::maskPhone($a->customer->phone ?? null),
            'paper'     => in_array($request->query('paper'), ['a5', '80mm'], true) ? $request->query('paper') : setting('receipt_paper', 'a5'),
            'autoPrint' => $request->boolean('print'),
            // Scan-to-rate, once the visit can be reviewed and hasn't been yet.
            'feedbackUrl' => ($a->status === 'completed' || $a->payment_status === 'paid') && !$a->feedback()->exists() ? $a->feedbackUrl() : null,
            'backUrl'   => $request->routeIs('admin.*') ? route('admin.appointments.show', $a) : route('cashier.index'),
        ]);
    }

    /** "0771234567" → "077 ••• 4567" — receipts get left behind. */
    public static function maskPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (strlen($digits) < 7) {
            return $phone ?: null;
        }

        return substr($digits, 0, 3) . ' ••• ' . substr($digits, -4);
    }
}
