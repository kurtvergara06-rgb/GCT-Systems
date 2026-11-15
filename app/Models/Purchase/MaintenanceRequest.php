<?php

namespace App\Models\Purchase;

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

        return collect(explode(',', $this->item))
            ->map(function ($part) {
                $part = trim($part);

                if ($part === '') {
                    return null;
                }

                /*
                |--------------------------------------------------------------------------
                | Format: Engine Oil - Qty: 10 liter
                |--------------------------------------------------------------------------
                */
                if (str_contains(strtolower($part), ' - qty:')) {
                    [$name, $quantityWithUnit] = preg_split('/ - qty:/i', $part, 2);

                    $parsed = $this->splitQuantityAndUnit($quantityWithUnit);

                    return [
                        'name' => trim($name),
                        'quantity' => $parsed['quantity'],
                        'unit' => $parsed['unit'],
                        'quantity_display' => trim($parsed['quantity'] . ' ' . $parsed['unit']),
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | Format: Engine Oil (10 liter)
                |--------------------------------------------------------------------------
                */
                if (preg_match('/^(.*?)\s*\((\d+\s*[^)]*)\)$/', $part, $matches)) {
                    $parsed = $this->splitQuantityAndUnit($matches[2] ?? '1');

                    return [
                        'name' => trim($matches[1] ?? $part),
                        'quantity' => $parsed['quantity'],
                        'unit' => $parsed['unit'],
                        'quantity_display' => trim($parsed['quantity'] . ' ' . $parsed['unit']),
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | Format: Engine Oil 10 liter
                |--------------------------------------------------------------------------
                */
                if (preg_match('/^(.*?)\s+(\d+)\s*(liter|liters|litre|litres|ltr|ltrs|pcs|pc|piece|pieces|set|sets|bottle|bottles|box|boxes|pack|packs|pair|pairs|gallon|gallons|kg|meter|meters)$/i', $part, $matches)) {
                    $unit = $this->normalizeUnit($matches[3] ?? '');

                    return [
                        'name' => trim($matches[1] ?? $part),
                        'quantity' => trim($matches[2] ?? '1'),
                        'unit' => $unit,
                        'quantity_display' => trim(($matches[2] ?? '1') . ' ' . $unit),
                    ];
                }

                return [
                    'name' => $part,
                    'quantity' => $this->quantity ?? '1',
                    'unit' => '—',
                    'quantity_display' => trim(($this->quantity ?? '1') . ' —'),
                ];
            })
            ->filter(fn ($part) => is_array($part) && ! empty($part['name']))
            ->values()
            ->toArray();
    }

    private function splitQuantityAndUnit(?string $value): array
    {
        $value = trim($value ?? '');

        if ($value === '') {
            return [
                'quantity' => '1',
                'unit' => '—',
            ];
        }

        if (preg_match('/^(\d+)\s*(.*)$/', $value, $matches)) {
            return [
                'quantity' => trim($matches[1] ?? '1'),
                'unit' => $this->normalizeUnit($matches[2] ?? ''),
            ];
        }

        return [
            'quantity' => $value,
            'unit' => '—',
        ];
    }

    private function normalizeUnit(?string $unit): string
    {
        $unit = strtolower(trim($unit ?? ''));

        return match ($unit) {
            'liter', 'liters', 'litre', 'litres', 'ltr', 'ltrs', 'l' => 'liter',
            'piece', 'pieces', 'pc', 'pcs' => 'pcs',
            'set', 'sets' => 'set',
            'bottle', 'bottles' => 'bottle',
            'box', 'boxes' => 'box',
            'pack', 'packs' => 'pack',
            'pair', 'pairs' => 'pair',
            'gallon', 'gallons', 'gal' => 'gallon',
            'kg', 'kilogram', 'kilograms' => 'kg',
            'meter', 'meters', 'm' => 'meter',
            default => $unit !== '' ? $unit : '—',
        };
    }
}