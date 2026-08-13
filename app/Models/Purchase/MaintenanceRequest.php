<?php

namespace App\Models\Purchase;

use App\Services\PartParser;
use Illuminate\Database\Eloquent\Model;

class MaintenanceRequest extends Model
{
    protected $table = 'purchase_requests';

    protected $fillable = [
        'pr_no',
        'job_order_no',
        'bus_no',
        'item',
        'quantity',
        'status',
        'source_type',
        'remarks',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function getDisplayPrNoAttribute(): string
    {
        $prNo = trim((string) ($this->pr_no ?? ''));

        if ($prNo === '') {
            return '—';
        }

        // Warehouse may create an internal purchase-side copy such as
        // PR-2026-0010-P. Keep that internal key intact for relationships,
        // but never expose the implementation suffix in the user-facing ID.
        return preg_replace('/-P(?:\d+)?$/i', '', $prNo) ?: $prNo;
    }

    public function getFirstItemNameAttribute()
    {
        $parts = $this->parseItemParts();

        return $parts[0]['name'] ?? $this->item;
    }

    public function getFirstQuantityAttribute()
    {
        $parts = $this->parseItemParts();

        return $parts[0]['quantity'] ?? $this->quantity;
    }

    public function getFirstUnitAttribute()
    {
        $parts = $this->parseItemParts();

        return $parts[0]['unit'] ?? '—';
    }

    public function getFirstQuantityDisplayAttribute()
    {
        $quantity = $this->first_quantity ?? $this->quantity ?? 1;
        $unit = $this->first_unit ?? '—';

        return trim($quantity . ' ' . $unit);
    }

    public function getPartsBreakdownAttribute(): array
    {
        return $this->parseItemParts();
    }

    private function parseItemParts(): array
    {
        if (! $this->item) {
            return [];
        }

        $parser = new PartParser();

        return collect($parser->parsePartText($this->item))
            ->map(function ($part) {
                $quantity = $part['quantity'] ?? 1;
                $unit = $part['unit'] !== '' ? $part['unit'] : '—';

                if ($unit === '—' && $quantity === 1 && $this->quantity !== null) {
                    $quantity = $this->quantity;
                }

                return [
                    'name' => $part['name'] ?? '',
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'quantity_display' => trim($quantity . ' ' . $unit),
                ];
            })
            ->filter(fn ($part) => is_array($part) && ! empty($part['name']))
            ->values()
            ->toArray();
    }
}
