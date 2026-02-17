<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'service_category_id',
        'name',
        'slug',
        'price',
        'duration_minutes',
        'description',
        'image',
        'is_active',
    ];
    public function category()
{
    return $this->belongsTo(ServiceCategory::class, 'service_category_id');
}
}
