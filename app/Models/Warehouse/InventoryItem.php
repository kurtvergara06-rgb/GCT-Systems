<?php

namespace App\Models\Warehouse;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $table = 'inventory_items';

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

    protected $casts = [
        'quantity_available' => 'integer',
        'reorder_level' => 'integer',
    ];

    public function getStockStatusAttribute()
    {
        if ((int) $this->quantity_available <= 0) {
            return 'Critical';
        }

        if ((int) $this->quantity_available <= (int) $this->reorder_level) {
            return 'Low Stock';
        }

        return 'In Stock';
    }

    /*
     * Compatibility aliases.
     * These help if other controllers/pages still call old names.
     */
    public function getPartsNameAttribute()
    {
        return $this->item_name;
    }

    public function getOnHandAttribute()
    {
        return $this->quantity_available;
    }

    public function getUnitAttribute()
    {
        return $this->unit_of_measurement;
    }
}