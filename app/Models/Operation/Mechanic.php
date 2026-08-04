<?php

namespace App\Models\Operation;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mechanic extends Model
{
    use HasFactory;

    protected $fillable = [
        'mechanic_id',
        'mechanic_name',
        'shift',
        'specialization',
        'contact_number',
        'employment_status',
    ];

    public function attendances(): HasMany
    {
        return $this->hasMany(MechanicAttendance::class, 'mechanic_id', 'mechanic_id');
    }
}
