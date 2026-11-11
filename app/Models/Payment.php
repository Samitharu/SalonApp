<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One amount received for a booking. A bill can have several: advances
 * before the visit, and a final settlement that may be split across methods.
 */
class Payment extends Model
{
    public const METHODS = [
        'cash'          => 'Cash',
        'card'          => 'Card',
        'bank_transfer' => 'Bank transfer',
        'qr'            => 'QR payment',
    ];

    public const ICONS = [
        'cash'          => 'fa-money-bill-wave',
        'card'          => 'fa-credit-card',
        'bank_transfer' => 'fa-building-columns',
        'qr'            => 'fa-qrcode',
    ];

    public const KINDS = ['advance', 'payment'];

    protected $fillable = [
        'appointment_id', 'kind', 'method', 'amount', 'cash_received',
        'reference', 'note', 'received_by', 'received_by_name', 'received_at',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'cash_received' => 'decimal:2',
        'received_at'   => 'datetime',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public static function methodLabel(?string $method): string
    {
        return match ($method) {
            null, ''  => '—',
            'split'   => 'Split payment',
            default   => static::METHODS[$method] ?? ucfirst($method),
        };
    }

    public function label(): string
    {
        return static::methodLabel($this->method);
    }

    public function change(): float
    {
        return $this->method === 'cash' && $this->cash_received !== null
            ? max(0, round((float) $this->cash_received - (float) $this->amount, 2))
            : 0.0;
    }
}
