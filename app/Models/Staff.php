<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class Staff extends Authenticatable
{
    protected $table = 'staff';

    protected $fillable = [
        'name',
        'title',
        'phone',
        'email',
        'photo',
        'bio',
        'is_active',
        'work_start',
        'work_end',
        'break_start',
        'break_end',
        'login_username',
        'password',
        'table_qr_token',
        'portal_permissions',
    ];

    protected $hidden = ['password', 'remember_token', 'table_qr_token'];

    protected $casts = [
        'is_active' => 'boolean',
        'portal_permissions' => 'array',
    ];

    /** What this stylist may do in the portal. Everything, until the admin says otherwise. */
    public function portalCan(string $permission): bool
    {
        return $this->portal_permissions === null || in_array($permission, $this->portal_permissions, true);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function feedback()
    {
        return $this->hasMany(Feedback::class);
    }

    public function regenerateTableQr(): void
    {
        $this->update(['table_qr_token' => Str::random(40)]);
    }

    /** "09:00:00" → "09:00" for <input type="time"> */
    public function timeInput(?string $value): string
    {
        return $value ? substr($value, 0, 5) : '';
    }
}
