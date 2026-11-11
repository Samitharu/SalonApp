<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    protected $table = 'feedback';

    protected $fillable = [
        'appointment_id',
        'customer_id',
        'staff_id',
        'service_id',
        'rating',
        'comment',
        'allow_public',
        'is_public',
    ];

    protected $casts = [
        'rating'       => 'integer',
        'allow_public' => 'boolean',
        'is_public'    => 'boolean',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /** Reviews shown on the website: customer agreed and admin published. */
    public function scopePublished($query)
    {
        return $query->where('is_public', true)->where('allow_public', true);
    }

    /** "Dilini Perera" → "Dilini P." for public display. */
    public function publicName(): string
    {
        $parts = preg_split('/\s+/', trim((string) ($this->customer->name ?? 'Guest'))) ?: ['Guest'];
        $first = $parts[0];
        $last  = count($parts) > 1 ? ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.' : '';

        return $first . $last;
    }
}
