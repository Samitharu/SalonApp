<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentItem extends Model
{
    protected $fillable = [
        'appointment_id',
        'service_id',
        'name',
        'price',
        'quantity',
        'added_by',
        'added_by_name',
    ];

    protected $casts = [
        'price'    => 'decimal:2',
        'quantity' => 'integer',
    ];

    protected static function booted(): void
    {
        // Extras added to (or removed from) a completed visit adjust stock too.
        $sync = function (AppointmentItem $item) {
            if ($item->service_id && ($a = Appointment::find($item->appointment_id)) && $a->status === 'completed') {
                \App\Services\InventoryService::syncAppointment($a);
            }
        };
        static::saved($sync);
        static::deleted($sync);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function lineTotal(): float
    {
        return round((float) $this->price * (int) $this->quantity, 2);
    }
}
