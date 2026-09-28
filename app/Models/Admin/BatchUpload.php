<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

class BatchUpload extends Model
{
    use HasFactory;

    public const SUPPORTED_PROCESSORS = [
        'Operation' => [
            'GPS Trip Records',
        ],
    ];

    public const DATA_ORIGINS = [
        'genuine',
        'demo',
        'sample',
        'synthetic',
        'unknown',
    ];

    protected $fillable = [
        'file_name',
        'stored_name',
        'file_path',
        'file_type',
        'module',
        'data_type',
        'data_origin',
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

    protected static function booted(): void
    {
        static::creating(function (BatchUpload $batchUpload) {
            $module = trim((string) ($batchUpload->module
                ?: request()->input('module', 'Operation')));

            $dataType = trim((string) ($batchUpload->data_type
                ?: request()->input('data_type', 'GPS Trip Records')));

            if (! self::supportsProcessor($module, $dataType)) {
                throw new InvalidArgumentException(
                    "Unsupported batch processor profile: {$module} / {$dataType}."
                );
            }

            $origin = strtolower(trim((string) $batchUpload->data_origin));
            if ($origin === '') {
                // Eloquent-created batches come from the authenticated upload
                // workflow. Seeders that insert directly with DB::table bypass
                // this hook and retain the migration's fail-closed `unknown`.
                $origin = 'genuine';
            }
            if (! in_array($origin, self::DATA_ORIGINS, true)) {
                throw new InvalidArgumentException(
                    "Unsupported batch data origin: {$origin}."
                );
            }

            $batchUpload->module = $module;
            $batchUpload->data_type = $dataType;
            $batchUpload->data_origin = $origin;
        });
    }

    public static function supportsProcessor(string $module, string $dataType): bool
    {
        return in_array(
            $dataType,
            self::SUPPORTED_PROCESSORS[$module] ?? [],
            true
        );
    }

    public function tripRecords(): HasMany
    {
        return $this->hasMany(GpsTripRecord::class, 'batch_upload_id');
    }

    public function processedRecords(): HasMany
    {
        return $this->hasMany(BatchProcessedRecord::class, 'batch_upload_id');
    }

    public function dataActivity(): HasOne
    {
        return $this->hasOne(DataActivity::class, 'reference_id')
            ->where('reference_type', 'batch_upload');
    }
}
