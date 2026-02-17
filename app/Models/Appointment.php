<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
    'appointment_no',
    'customer_id',
    'service_id',
    'staff_id',
    'appointment_date',
    'slot_start',
    'slot_end',
    'type',
    'status',
    'notes',
    'created_by_admin_id',
];
    public function customer()
{
    return $this->belongsTo(Customer::class);
}

public function service()
{
    return $this->belongsTo(Service::class);
}

public function staff()
{
    return $this->belongsTo(Staff::class);
}
}
