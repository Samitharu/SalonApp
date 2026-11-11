<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessHour extends Model
{
    public const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    protected $fillable = [
        'day_of_week',
        'open_time',
        'close_time',
        'break_start',
        'break_end',
        'is_closed',
    ];

    protected $casts = [
        'is_closed'   => 'boolean',
        'day_of_week' => 'integer',
    ];
}
