<?php

namespace App\Models\Operation;

use App\Models\Maintenance\Bus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationClient extends Model
{
    protected $fillable = [
        'name', 'status', 'company_address', 'contact_person', 'contact_position',
        'contact_number', 'contact_email', 'service_start_date', 'logo_path',
    ];

    protected function casts(): array
    {
        return ['service_start_date' => 'date'];
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->logo_path)
            : null;
    }

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
