<?php

namespace App\Models\Warehouse;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryIssuance extends Model
{
    protected $fillable = [
        'issue_no',
        'issued_to',
        'purpose',
        'reference_no',
        'issued_by',
        'issued_at',
        'signed_slip_path',
        'received_by',
        'handover_confirmed_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'handover_confirmed_at' => 'datetime',
    ];

    /**
     * Only genuine, confirmed SWIS handovers count toward IR fulfillment.
     * Older/demo ledger rows without signed evidence remain preserved, but
     * must never masquerade as confirmed physical handovers.
     */
    public function scopeConfirmedSigned(Builder $query): Builder
    {
        return $query->whereNotNull('signed_slip_path')
            ->where('signed_slip_path', '!=', '')
            ->whereNotNull('handover_confirmed_at')
            ->whereNotNull('received_by')
            ->where('received_by', '!=', '');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Admin\User::class, 'issued_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryIssuanceItem::class);
    }
}