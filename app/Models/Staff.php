<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'photo',
        'bio',
        'is_active',
    ];
}
