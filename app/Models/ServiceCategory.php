<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCategory extends Model
{
    /** Icons the admin can pick for the homepage category strip: key => [Font Awesome classes, label]. */
    public const ICONS = [
        'gem'       => ['fa-regular fa-gem', 'Gem'],
        'ring'      => ['fa-solid fa-ring', 'Ring'],
        'crown'     => ['fa-solid fa-crown', 'Crown'],
        'scissors'  => ['fa-solid fa-scissors', 'Scissors'],
        'styling'   => ['fa-solid fa-spray-can-sparkles', 'Styling'],
        'makeup'    => ['fa-solid fa-wand-magic-sparkles', 'Make-up'],
        'brush'     => ['fa-solid fa-paintbrush', 'Brush'],
        'nails'     => ['fa-solid fa-hand-sparkles', 'Hands & nails'],
        'spa'       => ['fa-solid fa-spa', 'Spa'],
        'face'      => ['fa-regular fa-face-smile', 'Face'],
        'leaf'      => ['fa-solid fa-leaf', 'Leaf'],
        'droplet'   => ['fa-solid fa-droplet', 'Droplet'],
        'heart'     => ['fa-regular fa-heart', 'Heart'],
        'eye'       => ['fa-regular fa-eye', 'Lashes & brows'],
        'groom'     => ['fa-solid fa-user-tie', 'Groom & men'],
        'kids'      => ['fa-solid fa-child', 'Kids'],
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'tagline',
        'image',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function services()
    {
        return $this->hasMany(Service::class, 'service_category_id');
    }

    public function galleryImages()
    {
        return $this->hasMany(GalleryImage::class, 'service_category_id');
    }

    /**
     * Categories shown on the website: switched on and with at least one
     * bookable service, with the package count and starting price loaded.
     */
    public function scopeShowcase($query)
    {
        return $query->where('is_active', 1)
            ->whereHas('services', fn ($q) => $q->bookable())
            ->withCount(['services as packages_count' => fn ($q) => $q->bookable()])
            ->withMin(['services as from_price' => fn ($q) => $q->bookable()], 'price')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function iconClass(): ?string
    {
        return static::ICONS[$this->icon][0] ?? null;
    }

    public function imageUrl(): ?string
    {
        return $this->image ? asset('storage/' . ltrim($this->image, '/')) : null;
    }
}
