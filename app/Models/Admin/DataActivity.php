<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'activity_type',
        'module',
        'data_type',
        'file_name',
        'source',
        'status',
        'total_records',
        'successful_records',
        'failed_records',
        'skipped_records',
        'processed_by',
        'reference_type',
        'reference_id',
        'details',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'total_records' => 'integer',
        'successful_records' => 'integer',
        'failed_records' => 'integer',
        'skipped_records' => 'integer',
        'details' => 'array',
        'completed_at' => 'datetime',
    ];

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
