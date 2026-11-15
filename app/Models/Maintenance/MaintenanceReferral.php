<?php

namespace App\Models\Maintenance;

use App\Models\Admin\User;
use App\Models\Operation\Incident;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MaintenanceReferral extends Model
{
    protected $fillable = [
        'incident_id',
        'status',
        'notes',
        'referred_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'incident_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function jobOrder(): HasOne
    {
        return $this->hasOne(JobOrder::class, 'maintenance_referral_id');
    }
}
