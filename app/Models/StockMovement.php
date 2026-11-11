<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    public const TYPES = [
        'opening'    => 'Opening stock',
        'purchase'   => 'Restock',
        'usage'      => 'Used',
        'return'     => 'Returned',
        'adjustment' => 'Adjustment',
    ];

    public const REASONS = [
        'count'    => 'Stock count correction',
        'damaged'  => 'Damaged or expired',
        'used'     => 'Used outside a booking',
        'returned' => 'Returned to supplier',
        'other'    => 'Other',
    ];

    protected $fillable = [
        'product_id', 'type', 'quantity', 'balance_after', 'total_cost', 'unit_cost', 'supplier', 'reference',
        'reason', 'note', 'appointment_id', 'occurred_on', 'created_by_type', 'created_by_id', 'created_by_name',
    ];

    protected $casts = [
        'quantity'      => 'float',
        'balance_after' => 'float',
        'total_cost'    => 'float',
        'unit_cost'     => 'float',
        'occurred_on'   => 'date',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function typeLabel(): string
    {
        return static::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function reasonLabel(): ?string
    {
        return $this->reason ? (static::REASONS[$this->reason] ?? $this->reason) : null;
    }
}
