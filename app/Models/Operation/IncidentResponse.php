<?php

namespace App\Models\Operation;

use App\Models\Admin\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentResponse extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'incident_id',
        'status',
        'notes',
        'responded_by',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(
            Incident::class,
            'incident_id'
        );
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'responded_by'
        );
    }
}
