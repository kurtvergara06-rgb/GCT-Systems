<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BatchUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name',
        'stored_name',
        'file_path',
        'file_type',
        'bus_no',
        'uploaded_by',
        'status',
        'total_records',
        'processed_records',
        'failed_records',
        'error_message',
    ];

    protected $casts = [
        'total_records' => 'integer',
        'processed_records' => 'integer',
        'failed_records' => 'integer',
    ];

    public function tripRecords(): HasMany
    {
        return $this->hasMany(GpsTripRecord::class, 'batch_upload_id');
    }
}