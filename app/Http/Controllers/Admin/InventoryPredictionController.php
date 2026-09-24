<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Warehouse\InventoryItem;
use App\Services\InventoryPredictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryPredictionController extends Controller
{
    public function index(
        Request $request,
        InventoryPredictionService $inventoryService
    ): JsonResponse {
        $validated = $request->validate([
            'item_codes' => ['required', 'array', 'min:1', 'max:12'],
            'item_codes.*' => ['required', 'string', 'max:100'],
        ]);

        $itemCodes = collect($validated['item_codes'])
            ->map(fn ($code): string => trim((string) $code))
            ->filter()
            ->unique()
            ->values();

        $items = InventoryItem::query()
            ->whereIn('item_code', $itemCodes)
            ->get()
            ->keyBy('item_code');

        $status = $inventoryService->status();
        $modelReady = ($status['model_ready'] ?? false) === true;

        $payloads = [];
        foreach ($itemCodes as $itemCode) {
            /** @var InventoryItem|null $item */
            $item = $items->get($itemCode);

            if (! $item) {
                continue;
            }

            $payloads[$itemCode] = [
                'part_id' => (string) $item->getKey(),
                'current_stock' => (float) $item->on_hand,
            ];
        }

        $responses = $modelReady && $payloads !== []
            ? $inventoryService->predictBatch($payloads)
            : array_fill_keys(array_keys($payloads), null);

        $predictions = [];

        foreach ($itemCodes as $itemCode) {
            /** @var InventoryItem|null $item */
            $item = $items->get($itemCode);
            $prediction = $responses[$itemCode] ?? null;

            $predictions[$itemCode] = [
                'item_code' => $itemCode,
                'item_found' => $item !== null,
                'available' => is_array($prediction),
                'prediction' => $prediction,
                'context' => $item ? [
                    'part_id' => (string) $item->getKey(),
                    'part_name' => $item->parts_name ?? $item->item_name ?? $itemCode,
                    'category' => $item->category,
                    'on_hand' => (int) $item->on_hand,
                    'reorder_level' => (int) $item->reorder_level,
                    'unit' => $item->unit,
                ] : null,
            ];
        }

        return response()->json([
            'success' => true,
            'model_ready' => $modelReady,
            'dataset_type' => $status['dataset_type'] ?? null,
            'data_source' => $status['data_source'] ?? null,
            'is_production_model' => (bool) ($status['is_production_model'] ?? false),
            'model_type' => $status['model_type'] ?? null,
            'runtime_mode' => $status['runtime_mode'] ?? null,
            'message' => $status['message'] ?? null,
            'disclaimer' => $status['disclaimer'] ?? null,
            'predictions' => $predictions,
        ]);
    }
}
