<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DayOff extends Model
{
    protected $table = 'days_off';

    protected $fillable = [
        'off_date',
        'reason',
    ];

    protected $casts = [
        'off_date' => 'date',
    ];
}
