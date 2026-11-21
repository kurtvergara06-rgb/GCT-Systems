<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class GpsNlpEnrichment extends Model
{
    protected $fillable = [
        'gps_trip_record_id', 'batch_upload_id', 'source',
        'entities', 'severity_suggestion', 'anomaly_suggestion',
    ];

    protected $casts = [
        'entities' => 'array',
        'severity_suggestion' => 'array',
        'anomaly_suggestion' => 'array',
    ];
}
