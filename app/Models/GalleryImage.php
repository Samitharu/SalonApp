<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GalleryImage extends Model
{
    protected $fillable = [
        'image',
        'thumb',
        'width',
        'height',
        'caption',
        'service_category_id',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'width'      => 'integer',
        'height'     => 'integer',
        'sort_order' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_active', 1);
    }

    /** Display order: manual order first, newest first within the same order. */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderByDesc('id');
    }

    public function url(): string
    {
        return asset('storage/' . ltrim($this->image, '/'));
    }

    /** Smaller copy for grids; the full image when no thumbnail was needed. */
    public function thumbUrl(): string
    {
        return asset('storage/' . ltrim($this->thumb ?: $this->image, '/'));
    }

    /** Thumbnail size, scaled from the stored full size, for width/height attributes (no layout shift). */
    public function thumbSize(int $thumbWidth = 640): array
    {
        if (!$this->width || !$this->height) {
            return [null, null];
        }
        if (!$this->thumb || $this->width <= $thumbWidth) {
            return [$this->width, $this->height];
        }

        return [$thumbWidth, (int) round($this->height * $thumbWidth / $this->width)];
    }

    public function altText(): string
    {
        return $this->caption ?: ($this->category ? $this->category->name . ' at ' . setting('salon_name', 'our salon') : 'Work by ' . setting('salon_name', 'our salon'));
    }
}
