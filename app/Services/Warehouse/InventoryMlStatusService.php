<?php

namespace App\Services\Warehouse;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Present Inventory Model #4 reachability separately from its eligibility
 * to supply reorder thresholds. This is read-only and never blocks Inventory.
 */
class InventoryMlStatusService
{
    /** @return array{state:string,label:string,message:string} */
    public function summary(): array
    {
        if (! (bool) config('services.inventory_ml.enabled', true)) {
            return [
                'state' => 'fallback',
                'label' => 'ML Disabled',
                'message' => 'Rules-based reorder calculation is active.',
            ];
        }

        $baseUrl = rtrim((string) config('services.nlp.api_url', ''), '/');

        if ($baseUrl === '') {
            return $this->offline();
        }

        // Short cache avoids repeated remote requests during partial navigation.
        $cacheKey = 'warehouse:inventory-ml:display-status:'.sha1($baseUrl);

        try {
            $status = Cache::remember($cacheKey, now()->addSeconds(45), function () use ($baseUrl): ?array {
                $response = Http::acceptJson()
                    ->connectTimeout(1)
                    ->timeout(2)
                    ->get($baseUrl.'/inventory/status');

                if (! $response->successful()) {
                    return null;
                }

                $json = $response->json();

                return is_array($json) && ($json['success'] ?? false) === true ? $json : null;
            });
        } catch (\Throwable) {
            return $this->offline();
        }

        if (! is_array($status)) {
            return $this->offline();
        }

        $ready = ($status['model_ready'] ?? false) === true
            && ($status['is_production_model'] ?? false) === true
            && ($status['data_source'] ?? '') === 'genuine'
            && ($status['source'] ?? '') === 'genuine'
            && ($status['forecast_scope'] ?? '') === 'fleet_part';

        if (! $ready) {
            return [
                'state' => 'fallback',
                'label' => 'ML Not Ready',
                'message' => 'Python engine connected · Verified inventory rules are active.',
            ];
        }

        if ((int) config('services.inventory_ml.replenishment_lead_days', 0) <= 0) {
            return [
                'state' => 'online',
                'label' => 'ML Online',
                'message' => 'Model ready · Set supplier lead time to enable ML reorder thresholds.',
            ];
        }

        return [
            'state' => 'online',
            'label' => 'ML Online',
            'message' => 'Genuine Inventory Model #4 ready · ML reorder predictions enabled.',
        ];
    }

    /** @return array{state:string,label:string,message:string} */
    private function offline(): array
    {
        return [
            'state' => 'offline',
            'label' => 'ML Offline',
            'message' => 'Python engine unavailable · Rules-based fallback active.',
        ];
    }
}
