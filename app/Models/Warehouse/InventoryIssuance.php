<?php

namespace App\Models\Warehouse;

use Illuminate\Database\Eloquent\Model;
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
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Admin\User::class, 'issued_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryIssuanceItem::class);
    }
}