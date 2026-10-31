<?php

namespace App\Models\Maintenance;

use App\Models\Admin\User;
use App\Models\Operation\Mechanic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobOrderMechanic extends Model
{
    protected $fillable = [
        'job_order_id', 'mechanic_id', 'mechanic_name', 'specialization_snapshot', 'assigned_by',
    ];

    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(Mechanic::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
