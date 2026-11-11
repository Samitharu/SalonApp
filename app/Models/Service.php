<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Service extends Model
{
    protected $fillable = [
        'service_category_id',
        'name',
        'slug',
        'price',
        'duration_minutes',
        'buffer_minutes',
        'description',
        'image',
        'is_active',
        'is_addon',
        'sort_order',
        'advance_type',
        'advance_value',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'is_addon'         => 'boolean',
        'price'            => 'decimal:2',
        'duration_minutes' => 'integer',
        'advance_value'    => 'decimal:2',
    ];

    /** The offer this service gets on a date (today by default), if any. */
    public function offerOn(\Carbon\Carbon|string|null $date = null): ?Offer
    {
        return app(\App\Services\OfferPricing::class)->best($this, $date);
    }

    public function discountOn(\Carbon\Carbon|string|null $date = null): float
    {
        return $this->offerOn($date)?->discountOn((float) $this->price) ?? 0.0;
    }

    public function priceOn(\Carbon\Carbon|string|null $date = null): float
    {
        return round((float) $this->price - $this->discountOn($date), 2);
    }

    /**
     * Advance the customer pays before the visit (0 = none). Worked out on the
     * price they're paying, and never more than it.
     */
    public function advanceAmount(?float $price = null): float
    {
        $price ??= (float) $this->price;
        $value = (float) $this->advance_value;

        return match ($this->advance_type) {
            'fixed'   => round(min($price, max(0, $value)), 2),
            'percent' => round($price * min(100, max(0, $value)) / 100, 2),
            default   => 0.0,
        };
    }

    public function requiresAdvance(): bool
    {
        return $this->advanceAmount() > 0;
    }

    public function advanceLabel(): ?string
    {
        $amount = $this->advanceAmount($this->priceOn());

        if ($amount <= 0) {
            return null;
        }

        return $this->advance_type === 'percent'
            ? rtrim(rtrim(number_format((float) $this->advance_value, 2), '0'), '.') . '% advance (' . money($amount) . ')'
            : money($amount) . ' advance';
    }

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    /** Products one client's service uses (pivot `quantity`, in the product's unit). */
    public function products()
    {
        return $this->belongsToMany(Product::class)->withPivot('quantity')->withTimestamps();
    }

    /** Services customers can book on their own (excludes add-on only items). */
    public function scopeBookable($query)
    {
        return $query->where('is_active', 1)->where('is_addon', 0);
    }

    public function imageUrl(): ?string
    {
        // asset() follows the address the site is opened on (host, port, sub-folder),
        // unlike the disk URL, which is fixed to APP_URL.
        return $this->image ? asset('storage/' . ltrim($this->image, '/')) : null;
    }

    public function durationLabel(): string
    {
        $m = (int) $this->duration_minutes;
        if ($m < 60) {
            return $m . ' min';
        }

        return intdiv($m, 60) . ' hr' . ($m % 60 ? ' ' . ($m % 60) . ' min' : '');
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'service';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
