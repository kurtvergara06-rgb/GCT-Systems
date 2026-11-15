<?php

namespace App\Models\Maintenance;

use App\Models\Admin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobOrderAudit extends Model
{
    protected $fillable = [
        'job_order_id', 'user_id', 'action', 'from_status', 'to_status', 'notes', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
