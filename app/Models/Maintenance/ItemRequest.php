<?php

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemRequest extends Model
{
    protected $table = 'maintenance_item_requests';

    protected $fillable = ['job_order_id', 'requested_by', 'status', 'requested_parts', 'remarks'];

    protected $casts = ['requested_parts' => 'array'];

    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class, 'job_order_id');
    }
}
