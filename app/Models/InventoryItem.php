<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = [
        'item_code',
        'item_name',
        'category',
        'quantity_available',
        'unit_of_measurement',
        'reorder_level',
        'supplier',
        'storage_location',
    ];

    public function getStockStatusAttribute()
    {
        if ($this->quantity_available <= 0) {
            return 'Critical';
        }

        if ($this->quantity_available <= $this->reorder_level) {
            return 'Low Stock';
        }

        return 'In Stock';
    }
}