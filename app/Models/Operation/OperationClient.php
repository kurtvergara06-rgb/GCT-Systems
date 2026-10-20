<?php

namespace App\Models\Operation;

use App\Models\Maintenance\Bus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationClient extends Model
{
    protected $fillable = ['name', 'status'];

    public function isActive(): bool
    {
        return $this->status === 'Active';
    }

    public function buses(): BelongsToMany
    {
        return $this->belongsToMany(Bus::class, 'bus_operation_clients')->withTimestamps();
    }

    public function tripTimeConfigurations(): HasMany
    {
        return $this->hasMany(ClientTripTimeConfiguration::class, 'operation_client_id');
    }
}
