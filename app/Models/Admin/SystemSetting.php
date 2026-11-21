<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'organization_name', 'organization_email', 'organization_phone',
        'organization_address', 'updated_by_name',
    ];

    protected function casts(): array
    {
        return ['security_policy' => 'array'];
    }

    public static function current(): self
    {
        return static::query()->find(1) ?? new static([
            'organization_name' => 'GCT Transport Services, Inc.',
        ]);
    }
}
