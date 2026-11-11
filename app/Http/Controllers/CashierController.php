<?php

namespace App\Http\Controllers;

use App\Http\Middleware\CashierAccess;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\SystemLog;
use App\Services\AppointmentCharges;
use App\Services\BillChanged;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class CashierController extends Controller
{
    public function index()
    {
        $paidToday = Appointment::with(['customer', 'service', 'items'])
            ->where('payment_status', 'paid')
            ->where('paid_at', '>=', now()->startOfDay())
            ->latest('paid_at')
            ->take(8)
            ->get();

        $awaiting = Appointment::with(['customer', 'service', 'staff', 'items'])
            ->whereDate('appointment_date', now()->toDateString())
            ->where('status', 'completed')
            ->where('payment_status', '!=', 'paid')
            ->orderBy('completed_at')
            ->get();

        return view('cashier.index', [
            'paidToday' => $paidToday,
            'awaiting'  => $awaiting,
            'isAdmin'   => Auth::guard('admin')->check(),
            'catalog'   => AppointmentCharges::catalog(),
        ]);
    }

    // ── Station PIN ─────────────────────────────────────────────────────

    public function unlockForm()
    {
        // Only admins the cashier will actually let in: anyone else would loop back here.
        if (Auth::guard('admin')->user()?->hasPermission('payments.take')) {
            return redirect()->route('cashier.index');
        }

        return view('cashier.unlock', ['hasPin' => (bool) setting('cashier_pin_hash')]);
    }

    public function unlock(Request $request)
    {
        $request->validate(['pin' => 'required|digits_between:4,8']);

        $hash = setting('cashier_pin_hash');

        if (!$hash || !Hash::check($request->pin, $hash)) {
            SystemLog::record('auth.login_failed', 'Wrong PIN entered at the cashier station', level: 'warning', actor: ['guest', null, null]);

            return back()->with('error', 'Incorrect PIN.');
        }

        $request->session()->regenerate();
        $request->session()->put(CashierAccess::SESSION_KEY, now()->timestamp);
        SystemLog::record('auth.login', 'Cashier station unlocked with the PIN', actor: ['cashier', null, 'Cashier station']);

        return redirect()->route('cashier.index');
    }

    public function lock(Request $request)
    {
        $request->session()->forget(CashierAccess::SESSION_KEY);
        SystemLog::record('auth.logout', 'Cashier station locked');

        return redirect()->route(Auth::guard('admin')->check() ? 'admin.dashboard' : 'cashier.unlock');
    }

    // ── Bills ───────────────────────────────────────────────────────────

    /**
     * AJAX: look up an appointment by job number or booking reference.
     */
    public function lookup(Request $request)
    {
        $request->validate(['ref' => 'required|string|max:20']);

        $ref = strtoupper(trim($request->ref));

        $appt = Appointment::with(['customer', 'service', 'staff', 'items'])
            ->where(fn ($q) => $q->where('job_number', $ref)->orWhere('appointment_no', $ref))
            ->first();

        if (!$appt) {
            return response()->json(['error' => 'No appointment found for that reference.'], 404);
        }

        if (in_array($appt->status, ['cancelled', 'no_show'], true)) {
            return response()->json(['error' => 'This appointment was cancelled.'], 422);
        }

        return response()->json(['success' => true, 'appointment' => $this->formatAppt($appt)]);
    }

    public function addItem(Request $request, Appointment $appointment)
    {
        $data = $request->validate(AppointmentCharges::RULES, AppointmentCharges::MESSAGES);

        try {
            AppointmentCharges::add($appointment, $data, 'cashier', Auth::guard('admin')->user()->name ?? 'Cashier');
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'appointment' => $this->formatAppt($appointment->fresh(['customer', 'service', 'staff', 'items']))]);
    }

    public function removeItem(Appointment $appointment, AppointmentItem $item)
    {
        abort_unless(AppointmentCharges::belongsTo($appointment, $item), 404);

        try {
            AppointmentCharges::remove($appointment, $item);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'appointment' => $this->formatAppt($appointment->fresh(['customer', 'service', 'staff', 'items']))]);
    }

    /**
     * Settle a bill (kind=payment: lines must equal the balance due) or take an
     * advance (kind=advance: any amount up to the balance). Lines may be split
     * across cash, card, bank transfer and QR.
     *
     * Older clients may still send a single payment_method (+ cash_received);
     * that becomes one line for the full balance.
     */
    public function processPayment(Request $request, Appointment $appointment)
    {
        if (!$request->has('lines') && $request->filled('payment_method')) {
            $request->validate([
                'payment_method' => 'in:cash,card,bank_transfer,qr',
                'cash_received'  => 'nullable|numeric|min:0|max:100000000',
            ]);
            $appointment->load(['service', 'items', 'payments']);
            $request->merge([
                'kind'             => 'payment',
                'expected_balance' => $request->filled('expected_total')
                    ? max(0, round((float) $request->input('expected_total') - $appointment->paidSoFar(), 2)) : null,
                'lines'            => $appointment->balanceDue() > 0 ? [[
                    'method'        => $request->input('payment_method'),
                    'amount'        => $appointment->balanceDue(),
                    'cash_received' => $request->input('cash_received'),
                ]] : [],
            ]);
        }

        $data = $request->validate(PaymentService::rules() + [
            'kind'             => 'nullable|in:payment,advance',
            'expected_balance' => 'nullable|numeric',
        ], PaymentService::MESSAGES);

        try {
            $appointment = PaymentService::record(
                $appointment, $data['kind'] ?? 'payment', $data['lines'],
                Auth::guard('admin')->check() ? 'admin' : 'cashier',
                Auth::guard('admin')->user()->name ?? 'Cashier station',
                isset($data['expected_balance']) ? (float) $data['expected_balance'] : null,
                $data['note'] ?? null,
            );
        } catch (BillChanged $e) {
            return response()->json(['error' => $e->getMessage(), 'appointment' => $this->formatAppt($e->appointment->load(['customer', 'staff']))], 409);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'appointment' => $this->formatAppt($appointment)]);
    }

    private function formatAppt(Appointment $a): array
    {
        $charges = AppointmentCharges::summary($a);
        $a->loadMissing('payments');
        $settled = $a->payments->where('kind', 'payment');
        $change  = $a->payment_status === 'paid' ? round($settled->sum(fn ($p) => $p->change()), 2) : null;

        return [
            'id'             => $a->id,
            'appointment_no' => $a->appointment_no,
            'job_number'     => $a->job_number ?? '—',
            'customer_name'  => $a->customer->name ?? '—',
            'customer_phone' => $a->customer->phone ?? '—',
            'service_name'   => $a->service->name ?? '—',
            'base_price'     => $charges['base_price'],
            'base_label'     => $charges['base_label'],
            'offer_name'     => $charges['offer_name'],
            'discount_label' => $charges['discount_label'],
            'items'          => $charges['items'],
            'extras_total'   => $charges['extras_total'],
            'total'          => $charges['total'],
            'total_label'    => $charges['total_label'],
            'locked'         => $charges['locked'],
            'stylist_name'   => $a->staff->name ?? 'Unassigned',
            'date'           => \Carbon\Carbon::parse($a->appointment_date)->format('D j M Y'),
            'time'           => fmt_time($a->slot_start) . ' – ' . fmt_time($a->slot_end),
            'status'         => $a->status,
            'status_label'   => status_label($a->status),
            'walk_in'        => (bool) $a->walk_in,
            'payment_status' => $a->payment_status,
            'payment_method' => $a->payment_method,
            'payment_method_label' => $a->paymentMethodLabel(),
            'paid_at'        => $a->paid_at?->format('j M Y, g:i A'),
            'cash_received'  => $a->cash_received !== null ? (float) $a->cash_received : null,
            'change'         => $change,
            // Advances and split payments.
            'payments'       => $a->payments->map(fn ($p) => [
                'kind'         => $p->kind,
                'method'       => $p->method,
                'method_label' => $p->label(),
                'amount'       => (float) $p->amount,
                'amount_label' => money($p->amount),
                'reference'    => $p->reference,
                'cash_received' => $p->cash_received !== null ? (float) $p->cash_received : null,
                'change'       => $p->change(),
                'at'           => $p->received_at?->format('j M, g:i A'),
            ])->values()->all(),
            'paid_so_far'    => $a->paidSoFar(),
            'paid_so_far_label' => money($a->paidSoFar()),
            'advance_paid'   => $a->advancePaid(),
            'advance_required' => $a->advanceRequired(),
            'balance'        => $a->balanceDue(),
            'balance_label'  => money($a->balanceDue()),
            'can_take_advance' => !in_array($a->status, ['completed', 'cancelled', 'no_show'], true) && $a->payment_status !== 'paid',
            // Scan-to-rate link for the receipt, once the visit can be reviewed.
            'feedback_url'   => ($a->status === 'completed' || $a->payment_status === 'paid') && !$a->feedback()->exists()
                ? $a->feedbackUrl() : null,
        ];
    }
}
