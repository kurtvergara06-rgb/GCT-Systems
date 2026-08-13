<?php

namespace App\Models\Admin;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'department',
    'role',
    'status',
    'last_login_at',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function permissionRoleKey(): string
    {
        $department = strtolower(trim((string) $this->department));
        $role = strtolower(trim((string) $this->role));

        if ($department === 'admin' && $role === 'head') {
            return 'admin_head';
        }

        return str_replace(' ', '_', $department) . '_' . $role;
    }

    public function rolePermission(): ?RolePermission
    {
        return RolePermission::where('role_key', $this->permissionRoleKey())->first();
    }

    public function hasSystemPermission(string $module, string $capability): bool
    {
        $rolePermission = $this->rolePermission();

        if (! $rolePermission) {
            return false;
        }

        return (bool) data_get(
            $rolePermission->permissions,
            $module . '.' . $capability,
            false
        );
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
