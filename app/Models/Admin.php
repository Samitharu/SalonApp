<?php

namespace App\Models;

use App\Support\Permissions;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Admin extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password', 'is_owner', 'is_active', 'permissions', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['is_owner' => false, 'is_active' => true];

    protected $casts = [
        'is_owner'      => 'boolean',
        'is_active'     => 'boolean',
        'permissions'   => 'array',
        'last_login_at' => 'datetime',
    ];

    /** Owners can do everything. A deactivated user can do nothing. */
    public function hasPermission(string $permission): bool
    {
        if (!$this->is_active) {
            return false;
        }

        return $this->is_owner || in_array($permission, $this->permissions ?? [], true);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /** Everything this user holds, spelled out. */
    public function effectivePermissions(): array
    {
        if (!$this->is_active) {
            return [];
        }

        return $this->is_owner ? Permissions::all() : Permissions::normalise($this->permissions ?? []);
    }

    /** "Owner", a preset name, or "Custom". */
    public function roleLabel(): string
    {
        if ($this->is_owner) {
            return 'Owner';
        }
        if (!$this->permissions) {
            return 'No access';
        }

        return Permissions::roleFor($this->permissions) ?? 'Custom';
    }
}
