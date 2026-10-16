<?php

namespace App\Models\Operation;

use App\Models\Maintenance\Bus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OperationClient extends Model
{
    protected $fillable = ['name'];

    public function buses(): BelongsToMany
    {
        return $this->belongsToMany(Bus::class, 'bus_operation_clients')->withTimestamps();
    }
}
