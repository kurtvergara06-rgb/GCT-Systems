<?php

namespace App\Services;

class PartParser
{
    /**
     * Normalize raw request part input into a consistent parts array.
     *
     * @param  array  $requestParts
     * @return array
     */
    public function normalizePartsInput(array $requestParts): array
    {
        $parts = [];

        foreach ($requestParts as $part) {
            $name = trim($part['name'] ?? '');
            $quantity = (int) ($part['quantity'] ?? 1);
            $unit = trim($part['unit'] ?? '');

            if ($name === '') {
                continue;
            }

            $parts[] = [
                'name' => $name,
                'quantity' => $quantity > 0 ? $quantity : 1,
                'unit' => $unit,
            ];
        }

        return $parts;
    }

    /**
     * Format an array of parts into a single part string.
     *
     * @param  array  $parts
     * @return string
     */
    public function formatParts(array $parts): string
    {
        $formattedParts = [];

        foreach ($parts as $part) {
            $name = trim($part['name'] ?? '');
            $quantity = (int) ($part['quantity'] ?? 1);
            $unit = trim($part['unit'] ?? '');

            if ($name === '') {
                continue;
            }

            if ($unit !== '') {
                $formattedParts[] = "{$name} - Qty: {$quantity} {$unit}";
            } else {
                $formattedParts[] = "{$name} - Qty: {$quantity}";
            }
        }

        return implode(', ', $formattedParts);
    }

    /**
     * Calculate total quantity from a normalized parts array.
     *
     * @param  array  $parts
     * @return int
     */
    public function calculateTotalQuantity(array $parts): int
    {
        $total = 0;

        foreach ($parts as $part) {
            $quantity = (int) ($part['quantity'] ?? 1);
            $total += $quantity > 0 ? $quantity : 1;
        }

        return $total > 0 ? $total : 1;
    }

    /**
     * Parse a part string into an array of normalized parts.
     *
     * Supported formats:
     * - Part Name - Qty: 2 pcs
     * - Part Name (2 pcs)
     * - Part Name 2 pcs
     *
     * @param  string|null  $text
     * @return array
     */
    public function parsePartText(?string $text): array
    {
        if (! $text) {
            return [];
        }

        return collect(explode(',', $text))
            ->map(function ($part) {
                $part = trim($part);

                if ($part === '') {
                    return null;
                }

                if (str_contains(strtolower($part), ' - qty:')) {
                    [$name, $quantityWithUnit] = preg_split('/ - qty:/i', $part, 2);
                    $parsed = $this->splitQuantityAndUnit($quantityWithUnit);

                    return [
                        'name' => trim($name),
                        'quantity' => $parsed['quantity'],
                        'unit' => $parsed['unit'],
                    ];
                }

                if (preg_match('/^(.*?)\s*\((\d+\s*[^)]*)\)$/', $part, $matches)) {
                    $parsed = $this->splitQuantityAndUnit($matches[2] ?? '1');

                    return [
                        'name' => trim($matches[1] ?? $part),
                        'quantity' => $parsed['quantity'],
                        'unit' => $parsed['unit'],
                    ];
                }

                if (preg_match('/^(.*?)\s+(\d+)\s*(liter|liters|litre|litres|ltr|ltrs|pcs|pc|piece|pieces|set|sets|bottle|bottles|box|boxes|pack|packs|pair|pairs|gallon|gallons|kg|meter|meters)$/i', $part, $matches)) {
                    return [
                        'name' => trim($matches[1] ?? $part),
                        'quantity' => (int) ($matches[2] ?? 1),
                        'unit' => $this->normalizeUnit($matches[3] ?? ''),
                    ];
                }

                return [
                    'name' => $part,
                    'quantity' => 1,
                    'unit' => '',
                ];
            })
            ->filter(fn ($item) => is_array($item) && $item['name'] !== '')
            ->values()
            ->toArray();
    }

    /**
     * Split a quantity/unit string into normalized parts.
     *
     * @param  string  $value
     * @return array
     */
    public function splitQuantityAndUnit(string $value): array
    {
        $value = trim($value);

        if ($value === '') {
            return [
                'quantity' => 1,
                'unit' => '',
            ];
        }

        if (preg_match('/^(\d+)\s*(.*)$/', $value, $matches)) {
            return [
                'quantity' => (int) ($matches[1] ?? 1),
                'unit' => $this->normalizeUnit($matches[2] ?? ''),
            ];
        }

        return [
            'quantity' => 1,
            'unit' => $this->normalizeUnit($value),
        ];
    }

    /**
     * Normalize unit names to a canonical form.
     *
     * @param  string|null  $unit
     * @return string
     */
    public function normalizeUnit(?string $unit): string
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
            default => $unit,
        };
    }

    /**
     * Clean a part name by removing trailing quantity/unit expressions.
     *
     * @param  string|null  $name
     * @return string
     */
    public function cleanPartName(?string $name): string
    {
        $name = trim($name ?? '');
        $name = preg_replace('/\s*\(\d+\s*[^)]*\)$/', '', $name);

        return trim($name);
    }
}
