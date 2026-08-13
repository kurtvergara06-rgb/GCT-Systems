<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchProcessedRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_upload_id',
        'payload',
        'raw_data',
        'status',
        'destination_type',
        'destination_id',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'raw_data' => 'array',
        'destination_id' => 'integer',
    ];

    public function batchUpload(): BelongsTo
    {
        return $this->belongsTo(BatchUpload::class);
    }
}
