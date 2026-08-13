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

    public function getStatusAttribute(?string $value): string
    {
        if (! $this->isStructuredImportActivity()) {
            return (string) $value;
        }

        $details = $this->details ?? [];
        $validationErrors = $details['validation_errors'] ?? [];

        if ($this->failed_records > 0 || (is_array($validationErrors) && $validationErrors !== [])) {
            return 'Needs Correction';
        }

        return (string) $value;
    }

    public function isStructuredImportActivity(): bool
    {
        if ($this->activity_type !== 'Import') {
            return false;
        }

        $details = $this->details ?? [];

        return $this->source === 'Structured File Import'
            || array_key_exists('validation_errors', $details)
            || array_key_exists('staged_payloads', $details);
    }
}
