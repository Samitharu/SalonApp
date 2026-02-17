<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessHour extends Model
{
    protected $fillable = [
    'day_of_week',
    'open_time',
    'close_time',
    'is_closed',
];
}
