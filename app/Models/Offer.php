<?php

namespace App\Models;

use App\Services\OfferPricing;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * A discount for visits between two dates. It applies by the date of the visit,
 * not the day the booking is made, and the best offer wins: they never stack.
 */
class Offer extends Model
{
    protected $fillable = ['name', 'description', 'discount_type', 'discount_value', 'starts_on', 'ends_on', 'applies_to', 'is_active'];

    protected $attributes = ['applies_to' => 'all', 'is_active' => true];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'starts_on'      => 'date',
        'ends_on'        => 'date',
        'is_active'      => 'boolean',
    ];

    protected static function booted(): void
    {
        $flush = fn () => app(OfferPricing::class)->flush();
        static::saved($flush);
        static::deleted($flush);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    /** Switched on and covering this date. */
    public function scopeRunningOn($query, Carbon|string $date)
    {
        $date = Carbon::parse($date)->toDateString();

        return $query->where('is_active', true)->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date);
    }

    public function coversAll(): bool
    {
        return $this->applies_to === 'all';
    }

    /** What it takes off this price. Never more than the price itself. */
    public function discountOn(float $price): float
    {
        $value = (float) $this->discount_value;

        return $this->discount_type === 'percent'
            ? round($price * min(100, max(0, $value)) / 100, 2)
            : round(min($price, max(0, $value)), 2);
    }

    /** "20% off" or "LKR 500 off". */
    public function label(): string
    {
        $value = rtrim(rtrim(number_format((float) $this->discount_value, 2), '0'), '.');

        return $this->discount_type === 'percent' ? $value . '% off' : setting('currency', 'LKR') . ' ' . $value . ' off';
    }

    public function periodLabel(): string
    {
        if ($this->starts_on->isSameDay($this->ends_on)) {
            return $this->ends_on->format('j M Y');
        }

        return $this->starts_on->format($this->starts_on->year === $this->ends_on->year ? 'j M' : 'j M Y') . ' – ' . $this->ends_on->format('j M Y');
    }

    /** paused | upcoming | running | ended */
    public function status(): string
    {
        $today = now()->startOfDay();

        return match (true) {
            $this->ends_on->lt($today)   => 'ended',
            !$this->is_active            => 'paused',
            $this->starts_on->gt($today) => 'upcoming',
            default                      => 'running',
        };
    }
}
