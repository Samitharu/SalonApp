<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DayOff extends Model
{
    protected $fillable = [
    'off_date',
    'reason',
];
}
