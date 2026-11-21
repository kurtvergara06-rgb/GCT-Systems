<?php

namespace App\Services\Warehouse;

use App\Models\Warehouse\InventoryItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Read-only threshold resolution. ML predicts genuine weekly demand; the
 * replenishment rule converts it to a threshold only when a lead time has
 * been explicitly configured. Never changes stock, reservations or PRs.
 */
class InventoryReorderPointService
{
    public function __construct(private readonly InventoryThresholdCalculator $calculator) {}

    /**
     * @param Collection<int, InventoryItem> $items
     * @param array<int|string, array<string, mixed>> $rules
     * @return array<int|string, array{reorder_point:?int,source:string,reason:string}>
     */
    public function resolveMany(Collection $items, array $rules = []): array
    {
        $result = [];
        foreach ($items as $item) {
            $rule = $rules[$item->getKey()] ?? $this->calculator->calculate($item);
            $calculated = $rule['reorder_point'] ?? null;
            $stored = (int) ($item->reorder_level ?? 0);
            $threshold = $calculated !== null ? (int) $calculated : ($stored > 0 ? $stored : null);

            $result[$item->getKey()] = [
                'reorder_point' => $threshold,
                'source' => $calculated !== null ? 'signed_issuances' : ($stored > 0 ? 'existing_rule' : 'pending'),
                'reason' => $calculated !== null
                    ? 'Based on three completed months of confirmed and signed parts issuances.'
                    : ($stored > 0
                        ? 'Existing recorded threshold retained until signed issuance history is sufficient.'
                        : 'Not calculated: insufficient signed issuance history and no eligible ML prediction or existing reorder threshold. This does not mean the item is out of stock.'),
            ];
        }

        $leadDays = min(365, max(0, (int) config('services.inventory_ml.replenishment_lead_days', 0)));
        $enabled = (bool) config('services.inventory_ml.enabled', true);
        $maxParts = max(1, (int) config('services.inventory_ml.max_batch_parts', 100));
        if (! $enabled || $leadDays <= 0 || $items->isEmpty() || $items->count() > $maxParts) {
            return $result;
        }

        $url = rtrim((string) config('services.nlp.api_url'), '/');
        if ($url === '') {
            return $result;
        }

        $cacheScope = sha1($url);
        $ready = Cache::remember("warehouse:inventory-ml:genuine-status:{$cacheScope}", now()->addMinutes(3), function () use ($url): bool {
            try {
                $response = Http::acceptJson()->connectTimeout(1)->timeout(2)->get($url.'/inventory/status');
                $status = $response->successful() ? $response->json() : null;

                return is_array($status)
                    && ($status['model_ready'] ?? false) === true
                    && ($status['is_production_model'] ?? false) === true
                    && ($status['source'] ?? '') === 'genuine'
                    && ($status['data_source'] ?? '') === 'genuine'
                    && ($status['forecast_scope'] ?? '') === 'fleet_part';
            } catch (\Throwable $e) {
                Log::notice('Inventory ML status unavailable; using verified reorder rules.', ['message' => $e->getMessage()]);
                return false;
            }
        });

        if (! $ready) {
            return $result;
        }

        // Prediction includes current on-hand as an input; cached values are
        // keyed to both stock quantity and calendar week, avoiding stale use.
        $pending = [];
        $predictions = [];
        $week = now('Asia/Manila')->format('oW');
        foreach ($items as $item) {
            $onHand = max(0, (int) ($item->quantity_available ?? $item->on_hand ?? 0));
            $key = "warehouse:inventory-ml:forecast:{$cacheScope}:{$week}:".$item->getKey().":{$onHand}";
            $cached = Cache::get($key);
            if (is_array($cached)) {
                $predictions[$item->getKey()] = $cached;
                continue;
            }

            $pending[$item->getKey()] = [
                'key' => $key,
                'part_id' => (string) $item->getKey(),
                'current_stock' => $onHand,
            ];
        }

        if ($pending !== []) {
            try {
                $response = Http::acceptJson()->connectTimeout(1)->timeout(4)
                    ->post($url.'/inventory/predict/batch', [
                        'parts' => array_map(
                            fn (array $part): array => [
                                'part_id' => $part['part_id'],
                                'current_stock' => $part['current_stock'],
                            ],
                            array_values($pending)
                        ),
                    ]);

                $body = $response->successful() ? $response->json() : null;
                if (is_array($body)
                    && ($body['success'] ?? false) === true
                    && ($body['data_source'] ?? '') === 'genuine'
                    && ($body['forecast_scope'] ?? '') === 'fleet_part') {
                    foreach (($body['results'] ?? []) as $prediction) {
                        if (! is_array($prediction)) {
                            continue;
                        }
                        $id = (string) ($prediction['part_id'] ?? '');
                        if (! isset($pending[$id])) {
                            continue;
                        }
                        $predictions[$id] = $prediction;
                        Cache::put($pending[$id]['key'], $prediction, now()->addMinutes(10));
                    }
                }
            } catch (\Throwable $e) {
                Log::notice('Inventory ML predictions unavailable; using verified reorder rules.', ['message' => $e->getMessage()]);
            }
        }

        $safetyPct = min(100, max(0, (int) config('services.inventory_ml.safety_percent', 0)));
        foreach ($items as $item) {
            $id = $item->getKey();
            $prediction = $predictions[$id] ?? null;
            if (! is_array($prediction)
                || ($prediction['success'] ?? false) !== true
                || ($prediction['model_ready'] ?? false) !== true
                || ($prediction['is_production_model'] ?? false) !== true
                || ($prediction['data_source'] ?? '') !== 'genuine'
                || ($prediction['forecast_scope'] ?? '') !== 'fleet_part'
                || ($prediction['forecast_period'] ?? '') !== 'next_week'
                || (string) ($prediction['part_id'] ?? '') !== (string) $id
                || ! is_numeric($prediction['predicted_quantity_issued'] ?? null)
                || strcasecmp((string) ($prediction['unit'] ?? ''), (string) ($item->unit_of_measurement ?? '')) !== 0) {
                continue;
            }

            $weekly = (float) $prediction['predicted_quantity_issued'];
            if (! is_finite($weekly) || $weekly <= 0 || $weekly > 1000000) {
                continue;
            }

            $mlThreshold = (int) ceil($weekly * $leadDays / 7 * (1 + $safetyPct / 100));
            if ($mlThreshold <= (int) ($result[$id]['reorder_point'] ?? 0)) {
                continue;
            }

            // ML may increase a trusted historical level but must never lower it.
            $result[$id] = [
                'reorder_point' => max($mlThreshold, (int) ($result[$id]['reorder_point'] ?? 0)),
                'source' => 'ml_prediction',
                'reason' => "Genuine weekly demand forecast converted using configured {$leadDays}-day replenishment lead time and {$safetyPct}% safety margin; never below the existing verified rule.",
            ];
        }

        return $result;
    }
}
