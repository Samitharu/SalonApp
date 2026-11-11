<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Slider extends Model
{
    protected $fillable = [
        'title',
        'subtitle',
        'image',
        'button_text',
        'button_link',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function imageUrl(): ?string
    {
        // asset() follows the address the site is opened on (host, port, sub-folder),
        // unlike the disk URL, which is fixed to APP_URL.
        return $this->image ? asset('storage/' . ltrim($this->image, '/')) : null;
    }
}
