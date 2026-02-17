<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];

    public function services()
    {
        return $this->hasMany(Service::class);
    }
}
