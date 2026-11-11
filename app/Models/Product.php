<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A consumable used on clients. `stock` is a cached balance; every change
 * goes through InventoryService and leaves a StockMovement.
 */
class Product extends Model
{
    /** unit key => [singular, plural, countable] */
    public const UNITS = [
        'packet' => ['packet', 'packets', true],
        'sachet' => ['sachet', 'sachets', true],
        'piece'  => ['piece', 'pieces', true],
        'bottle' => ['bottle', 'bottles', true],
        'tube'   => ['tube', 'tubes', true],
        'jar'    => ['jar', 'jars', true],
        'box'    => ['box', 'boxes', true],
        'pair'   => ['pair', 'pairs', true],
        'sheet'  => ['sheet', 'sheets', true],
        'ml'     => ['ml', 'ml', false],
        'l'      => ['litre', 'litres', false],
        'g'      => ['g', 'g', false],
        'kg'     => ['kg', 'kg', false],
    ];

    protected $fillable = [
        'name',
        'brand',
        'unit',
        'uses_per_unit',
        'low_stock_level',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'uses_per_unit'   => 'float',
        'stock'           => 'float',
        'low_stock_level' => 'float',
        'cost_per_unit'   => 'float',
        'is_active'       => 'boolean',
    ];

    public function services()
    {
        return $this->belongsToMany(Service::class)->withPivot('quantity')->withTimestamps();
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Active products at or below their alert level (or out of stock). */
    public function scopeLowStock($query)
    {
        return $query->where('is_active', 1)->where(function ($q) {
            $q->where('stock', '<=', 0)
              ->orWhere(fn ($q) => $q->whereNotNull('low_stock_level')->whereColumn('stock', '<=', 'low_stock_level'));
        });
    }

    /** ok | low | out */
    public function stockStatus(?float $stock = null): string
    {
        $stock ??= (float) $this->stock;

        if ($stock <= 0) {
            return 'out';
        }

        return $this->low_stock_level !== null && $stock <= (float) $this->low_stock_level ? 'low' : 'ok';
    }

    public function displayName(): string
    {
        return $this->brand ? "{$this->name} ({$this->brand})" : $this->name;
    }

    public function unitLabel(?float $qty = null): string
    {
        $u = static::UNITS[$this->unit] ?? [$this->unit, $this->unit];

        return $qty !== null && abs($qty - 1) < 0.0005 ? $u[0] : $u[1];
    }

    /** "12.5 packets", "250 ml" */
    public function qty(?float $qty): string
    {
        $qty = (float) $qty;

        return static::number($qty) . ' ' . $this->unitLabel(abs($qty));
    }

    /** Rough number of clients the current stock covers, when units serve several clients. */
    public function clientsLeft(): ?int
    {
        if ($this->uses_per_unit <= 1 || $this->stock <= 0) {
            return null;
        }

        return (int) floor($this->stock * $this->uses_per_unit + 0.0001);
    }

    /** Suggested amount per client when linking to a service: one unit ÷ clients it serves. */
    public function defaultPerClient(): float
    {
        return round(1 / max(1, (float) $this->uses_per_unit), 3);
    }

    public function stockValue(): ?float
    {
        return $this->cost_per_unit && $this->stock > 0 ? round($this->stock * $this->cost_per_unit, 2) : null;
    }

    /** 12 → "12", 12.5 → "12.5", 0.333 → "0.333" */
    public static function number(float $n): string
    {
        $s = rtrim(rtrim(number_format($n, 3, '.', ','), '0'), '.');

        return $s === '-0' ? '0' : $s;
    }
}
