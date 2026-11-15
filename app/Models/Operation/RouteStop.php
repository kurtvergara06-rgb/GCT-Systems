<?php

namespace App\Models\Operation;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteStop extends Model
{
    use HasFactory;

    protected $fillable = [
        'shuttle_route_id',
        'stop_name',
        'stop_order',
    ];

    protected $casts = [
        'stop_order' => 'integer',
    ];

    public function shuttleRoute(): BelongsTo
    {
        return $this->belongsTo(ShuttleRoute::class);
    }
}