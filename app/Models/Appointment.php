<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Appointment extends Model
{
    public const STATUSES = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'];

    /** Statuses that occupy a stylist's time. */
    public const ACTIVE_STATUSES = ['pending', 'confirmed', 'in_progress', 'completed'];

    protected $fillable = [
        'appointment_no',
        'customer_id',
        'service_id',
        'offer_id',
        'offer_name',
        'discount_amount',
        'staff_id',
        'appointment_date',
        'slot_start',
        'slot_end',
        'type',
        'status',
        'notes',
        'contact_email',
        'confirmation_sent_at',
        'confirmation_error',
        'created_by_admin_id',
        'walk_in',
        'started_at',
        'completed_at',
        'job_number',
        'payment_status',
        'payment_method',
        'paid_at',
        'cancelled_by',
        'cancelled_at',
        'amount_paid',
        'cash_received',
        'rescheduled_at',
        'rescheduled_by',
        'reschedule_count',
    ];

    /** Customers can change a booking online this many times. */
    public const MAX_CUSTOMER_CHANGES = 3;

    protected $casts = [
        'walk_in'       => 'boolean',
        'started_at'    => 'datetime',
        'completed_at'  => 'datetime',
        'paid_at'       => 'datetime',
        'cancelled_at'  => 'datetime',
        'rescheduled_at' => 'datetime',
        'confirmation_sent_at' => 'datetime',
        'discount_amount' => 'decimal:2',
        'amount_paid'   => 'decimal:2',
        'cash_received' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // Stock: completing a visit (cashier, admin or stylist) uses the products its
        // service needs; moving it out of "completed" gives them back.
        static::saved(function (Appointment $a) {
            if ($a->wasRecentlyCreated ? $a->status === 'completed' : $a->wasChanged(['status', 'service_id'])) {
                \App\Services\InventoryService::syncAppointment($a);
            }

            // Email: however a booking becomes confirmed (staff, auto-confirm, re-confirming
            // a customer's change), the customer hears about it.
            if (($kind = $a->confirmationKind()) && $a->confirmationEmail()) {
                \App\Jobs\SendBookingConfirmation::schedule($a, $kind);
            }
        });
    }

    /** Where this booking's emails go: the address given for it, else the customer's. */
    public function confirmationEmail(): ?string
    {
        return $this->contact_email ?: $this->customer?->email;
    }

    /**
     * Does the save that just happened call for an email? "confirmed" when it
     * became confirmed, "updated" when a confirmed booking's details moved.
     * Stepping back from in progress / completed isn't news to the customer.
     */
    private function confirmationKind(): ?string
    {
        if ($this->status !== 'confirmed') {
            return null;
        }
        if ($this->wasRecentlyCreated && !$this->getChanges()) {
            return 'confirmed';
        }
        if ($this->wasChanged('status')) {
            return in_array($this->getOriginal('status'), ['in_progress', 'completed'], true) ? null : 'confirmed';
        }

        return $this->wasChanged(['appointment_date', 'slot_start', 'service_id', 'staff_id']) ? 'updated' : null;
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * SQL expression for an appointment's bill total: the amount charged once
     * fully paid, otherwise service price less any offer + extras (an advance
     * alone is not the bill). Needs a join on services and the "ai" extras subquery from
     * scopeWithBillTotals().
     */
    public const BILL_TOTAL_SQL = "CASE WHEN appointments.payment_status = 'paid' AND appointments.amount_paid IS NOT NULL THEN appointments.amount_paid ELSE (CASE WHEN services.price > appointments.discount_amount THEN services.price - appointments.discount_amount ELSE 0 END) + COALESCE(ai.extras, 0) END";

    public const PAYMENT_STATUSES = ['unpaid', 'partial', 'paid'];

    public function feedback()
    {
        return $this->hasOne(Feedback::class);
    }

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    /** What the offer takes off the service. Saved when booked, so it outlasts the offer. */
    public function discount(): float
    {
        return round(min((float) $this->discount_amount, (float) ($this->service->price ?? 0)), 2);
    }

    /** The service's price for this booking, after any offer. */
    public function servicePrice(): float
    {
        return round((float) ($this->service->price ?? 0) - $this->discount(), 2);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class)->orderBy('received_at')->orderBy('id');
    }

    /** Everything received so far (advances + settlement). */
    public function paidSoFar(): float
    {
        return round((float) $this->payments->sum('amount'), 2);
    }

    public function advancePaid(): float
    {
        return round((float) $this->payments->where('kind', 'advance')->sum('amount'), 2);
    }

    public function balanceDue(): float
    {
        return max(0, round($this->total() - $this->paidSoFar(), 2));
    }

    /** Advance the service asks for, and how much of it is still to come. */
    public function advanceRequired(): float
    {
        return $this->service ? $this->service->advanceAmount($this->servicePrice()) : 0.0;
    }

    public function advanceOutstanding(): float
    {
        return max(0, round($this->advanceRequired() - $this->advancePaid(), 2));
    }

    public function paymentMethodLabel(): string
    {
        return Payment::methodLabel($this->payment_method);
    }

    /** Admin can change service/stylist/time until the appointment starts. */
    public function isChangeable(): bool
    {
        return in_array($this->status, ['pending', 'confirmed'], true) && $this->payment_status !== 'paid';
    }

    /** Customer can change online before the cancellation cut-off, a few times. */
    public function isChangeableByCustomer(): bool
    {
        return $this->isChangeable()
            && $this->isCancellableByCustomer()
            && (int) $this->reschedule_count < static::MAX_CUSTOMER_CHANGES;
    }

    /** Feedback can be left once, after the visit is completed. */
    public function canLeaveFeedback(): bool
    {
        return $this->status === 'completed' && !$this->feedback()->exists();
    }

    /**
     * Signed "rate your visit" link for receipts. It only lets the holder
     * review this visit (never change or cancel it) and expires in 30 days.
     */
    public function feedbackUrl(): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute('review.link', now()->addDays(30), ['appointment' => $this->id]);
    }

    public function items()
    {
        return $this->hasMany(AppointmentItem::class)->orderBy('id');
    }

    /** Join services + summed extras so BILL_TOTAL_SQL can be used. */
    public function scopeWithBillTotals($query)
    {
        $extras = AppointmentItem::selectRaw('appointment_id, SUM(price * quantity) as extras')->groupBy('appointment_id');

        return $query->join('services', 'services.id', '=', 'appointments.service_id')
            ->leftJoinSub($extras, 'ai', 'ai.appointment_id', '=', 'appointments.id');
    }

    public function extrasTotal(): float
    {
        return round($this->items->sum(fn ($i) => $i->lineTotal()), 2);
    }

    /** Bill total: what was charged if paid, otherwise service (less any offer) + extras. */
    public function total(): float
    {
        if ($this->payment_status === 'paid' && $this->amount_paid !== null) {
            return (float) $this->amount_paid;
        }

        return round($this->servicePrice() + $this->extrasTotal(), 2);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public static function generateReference(): string
    {
        do {
            $ref = strtoupper(Str::random(8));
        } while (static::where('appointment_no', $ref)->exists());

        return $ref;
    }

    public static function generateJobNumber(): string
    {
        do {
            $num = 'J' . strtoupper(Str::random(5));
        } while (static::where('job_number', $num)->exists());

        return $num;
    }

    /**
     * Start of the appointment as a Carbon instance.
     */
    public function startsAt(): \Carbon\Carbon
    {
        $date = \Carbon\Carbon::parse($this->appointment_date)->toDateString();

        return \Carbon\Carbon::parse($date . ' ' . $this->slot_start);
    }

    /**
     * Whether a customer may still cancel this booking online.
     */
    public function isCancellableByCustomer(): bool
    {
        if (!in_array($this->status, ['pending', 'confirmed'], true)) {
            return false;
        }

        // Once an advance is paid, the salon handles cancellation (and any refund).
        if ($this->payments()->exists()) {
            return false;
        }

        $hours = (int) setting('cancellation_hours', 2);

        return now()->addHours($hours)->lte($this->startsAt());
    }
}
