<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    protected $fillable = [
        'role_key',
        'label',
        'department',
        'role_type',
        'permissions',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }
}
