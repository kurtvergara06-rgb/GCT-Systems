<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\BatchUpload;
use App\Models\Maintenance\FuelReport;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Warehouse\InventoryItem;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GenericBatchRecordController extends Controller
{
    public function update(
        Request $request,
        BatchUpload $batchUpload
    ): RedirectResponse {
        if ($batchUpload->status !== 'In Review') {
            return back()->with('error', 'Only an In Review batch can be edited.');
        }

        $validated = $this->validateRecords($request);

        try {
            DB::transaction(function () use ($validated, $batchUpload) {
                $this->saveReviewedRecords($batchUpload, $validated['records']);
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'Unable to save batch corrections: ' . $exception->getMessage()
            );
        }

        return back()->with('success', 'Batch review changes saved successfully.');
    }

    public function saveAndProcess(
        Request $request,
        BatchUpload $batchUpload
    ): RedirectResponse {
        if ($batchUpload->status !== 'In Review') {
            return back()->with('error', 'Only an In Review batch can be processed.');
        }

        $validated = $this->validateRecords($request);
        $published = 0;

        try {
            DB::transaction(function () use (
                $validated,
                $batchUpload,
                &$published
            ) {
                $this->saveReviewedRecords(
                    $batchUpload,
                    $validated['records']
                );

                $records = $batchUpload->processedRecords()
                    ->orderBy('id')
                    ->get();

                if ($records->isEmpty()) {
                    throw new RuntimeException(
                        'This batch has no staged records to process.'
                    );
                }

                foreach ($records as $record) {
                    $destination = $this->publishRecord(
                        $batchUpload->module,
                        $batchUpload->data_type,
                        $record->payload ?? []
                    );

                    $record->update([
                        'status' => 'Processed',
                        'destination_type' => $destination::class,
                        'destination_id' => $destination->getKey(),
                        'error_message' => null,
                    ]);

                    $published++;
                }

                $batchUpload->update([
                    'status' => 'Processed',
                    'processed_records' => $published,
                    'failed_records' => 0,
                    'error_message' => null,
                ]);
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'Unable to save and process this batch: ' . $exception->getMessage()
            );
        }

        return redirect()
            ->route('batch-file-processing', [
                'generic_batch_id' => $batchUpload->id,
            ])
            ->with(
                'success',
                "{$published} {$batchUpload->data_type} record(s) were saved and processed successfully."
            );
    }

    private function validateRecords(Request $request): array
    {
        return $request->validate([
            'records' => ['required', 'array', 'min:1'],
            'records.*' => ['required', 'array'],
        ]);
    }

    private function saveReviewedRecords(
        BatchUpload $batchUpload,
        array $submittedRecords
    ): void {
        foreach ($submittedRecords as $recordId => $submittedPayload) {
            $record = $batchUpload->processedRecords()
                ->whereKey((int) $recordId)
                ->firstOrFail();

            $record->update([
                'payload' => $this->normalizeEditedPayload(
                    $batchUpload->module,
                    $batchUpload->data_type,
                    $submittedPayload,
                    $record->payload ?? []
                ),
                'status' => 'In Review',
                'error_message' => null,
            ]);
        }
    }

    private function normalizeEditedPayload(
        string $module,
        string $dataType,
        array $submitted,
        array $existing
    ): array {
        return match ([$module, $dataType]) {
            ['Maintenance', 'Fuel Reports'] => $this->normalizeFuel($submitted, $existing),
            ['Warehouse', 'Inventory Records'] => $this->normalizeInventory($submitted, $existing),
            ['Purchase', 'Purchase Orders'] => $this->normalizePurchase($submitted, $existing),
            default => throw new RuntimeException(
                "No review normalizer is registered for {$module} / {$dataType}."
            ),
        };
    }

    private function normalizeFuel(array $submitted, array $existing): array
    {
        $payload = array_merge($existing, $submitted);
        $payload['report_date'] = Carbon::parse($payload['report_date'])->toDateString();
        $payload['distance_km'] = $this->positiveNumber($payload['distance_km'] ?? null, 'Distance KM');
        $payload['fuel_liters'] = $this->positiveNumber($payload['fuel_liters'] ?? null, 'Fuel Liters');

        $efficiency = $payload['distance_km'] / $payload['fuel_liters'];
        $payload['km_per_liter'] = round($efficiency, 2);
        $payload['status'] = match (true) {
            $efficiency >= 6 => 'Efficient',
            $efficiency >= 4 => 'Normal',
            $efficiency > 0 => 'Inefficient',
            default => 'No Data',
        };

        $payload['bus_no'] = trim((string) ($payload['bus_no'] ?? ''));

        if ($payload['bus_no'] === '') {
            throw new RuntimeException('Bus No. is required.');
        }

        $payload['driver_name'] = $this->nullableText($payload['driver_name'] ?? null);
        $payload['remarks'] = $this->nullableText($payload['remarks'] ?? null);

        return $payload;
    }

    private function normalizeInventory(array $submitted, array $existing): array
    {
        $payload = array_merge($existing, $submitted);
        $payload['item_code'] = $this->nullableText($payload['item_code'] ?? null);
        $payload['item_name'] = $this->nullableText($payload['item_name'] ?? null);
        $payload['parts_name'] = $this->nullableText($payload['parts_name'] ?? $payload['item_name'] ?? null);

        if (! $payload['item_code'] && ! $payload['item_name']) {
            throw new RuntimeException('Item Code or Item Name is required.');
        }

        $onHand = $this->nonNegativeInteger($payload['on_hand'] ?? 0, 'On Hand');
        $reorderLevel = $this->nonNegativeInteger($payload['reorder_level'] ?? 0, 'Reorder Level');
        $unit = $this->nullableText($payload['unit'] ?? $payload['unit_of_measurement'] ?? null) ?: 'pcs';
        $location = $this->nullableText($payload['location'] ?? $payload['storage_location'] ?? null);

        $payload['on_hand'] = $onHand;
        $payload['quantity_available'] = $onHand;
        $payload['reorder_level'] = $reorderLevel;
        $payload['unit'] = $unit;
        $payload['unit_of_measurement'] = $unit;
        $payload['location'] = $location;
        $payload['storage_location'] = $location;
        $payload['category'] = $this->nullableText($payload['category'] ?? null);
        $payload['supplier'] = $this->nullableText($payload['supplier'] ?? null);
        $payload['status'] = match (true) {
            $onHand <= 0 => 'Critical',
            $reorderLevel > 0 && $onHand <= $reorderLevel => 'Low Stock',
            default => 'In Stock',
        };

        return $payload;
    }

    private function normalizePurchase(array $submitted, array $existing): array
    {
        $payload = array_merge($existing, $submitted);
        $payload['po_no'] = $this->nullableText($payload['po_no'] ?? null);
        $payload['po_date'] = Carbon::parse($payload['po_date'] ?? now())->toDateString();
        $payload['supplier_name'] = trim((string) ($payload['supplier_name'] ?? ''));

        if ($payload['supplier_name'] === '') {
            throw new RuntimeException('Supplier Name is required.');
        }

        $items = $payload['items'] ?? [];

        if (is_string($items)) {
            $items = json_decode($items, true);
        }

        if (! is_array($items) || $items === []) {
            throw new RuntimeException('Purchase Order Items must contain valid JSON records.');
        }

        $normalizedItems = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $description = trim((string) ($item['item_description'] ?? $item['description'] ?? ''));
            $quantity = (float) ($item['quantity'] ?? $item['qty'] ?? 0);
            $cost = (float) ($item['cost'] ?? $item['price'] ?? 0);

            if ($description === '' || $quantity <= 0 || $cost < 0) {
                continue;
            }

            $normalizedItems[] = [
                'pr_no' => $item['pr_no'] ?? null,
                'bus_no' => $item['bus_no'] ?? null,
                'employee' => $item['employee'] ?? null,
                'item_description' => $description,
                'quantity' => $quantity,
                'unit' => $item['unit'] ?? null,
                'cost' => $cost,
            ];
        }

        if ($normalizedItems === []) {
            throw new RuntimeException('Purchase Order contains no valid items.');
        }

        $gross = collect($normalizedItems)->sum(
            fn ($item) => ((float) $item['quantity']) * ((float) $item['cost'])
        );
        $deliveryFee = $this->nonNegativeNumber($payload['delivery_fee'] ?? 0, 'Delivery Fee');
        $discount = $this->nonNegativeNumber($payload['discount'] ?? 0, 'Discount');
        $vat = $this->nonNegativeNumber($payload['vat'] ?? 0, 'VAT');

        $payload['items'] = $normalizedItems;
        $payload['gross_amount'] = round($gross, 2);
        $payload['delivery_fee'] = round($deliveryFee, 2);
        $payload['discount'] = round($discount, 2);
        $payload['vat'] = round($vat, 2);
        $payload['net_amount'] = round(max(0, $gross + $deliveryFee + $vat - $discount), 2);
        $payload['status'] = $this->purchaseStatus((string) ($payload['status'] ?? 'Ordered'));
        $payload['supplier_address_tel'] = $this->nullableText($payload['supplier_address_tel'] ?? null);
        $payload['terms'] = $this->nullableText($payload['terms'] ?? null);
        $payload['terms_of_payment'] = $this->nullableText($payload['terms_of_payment'] ?? null);
        $payload['purpose'] = $this->nullableText($payload['purpose'] ?? null);

        return $payload;
    }

    private function publishRecord(string $module, string $dataType, array $payload)
    {
        return match ([$module, $dataType]) {
            ['Maintenance', 'Fuel Reports'] => $this->publishFuelReport($payload),
            ['Warehouse', 'Inventory Records'] => $this->publishInventoryRecord($payload),
            ['Purchase', 'Purchase Orders'] => $this->publishPurchaseOrder($payload),
            default => throw new RuntimeException(
                "No publisher is registered for {$module} / {$dataType}."
            ),
        };
    }

    private function publishFuelReport(array $payload): FuelReport
    {
        return FuelReport::updateOrCreate(
            [
                'report_date' => $payload['report_date'],
                'bus_no' => $payload['bus_no'],
            ],
            [
                'driver_name' => $payload['driver_name'] ?? null,
                'gps_trip_record_id' => null,
                'distance_km' => $payload['distance_km'],
                'distance_source' => 'Batch Import',
                'fuel_liters' => $payload['fuel_liters'],
                'km_per_liter' => $payload['km_per_liter'],
                'status' => $payload['status'],
                'remarks' => $payload['remarks'] ?? null,
                'manual_distance_reason' => 'Imported through Batch File Processing.',
            ]
        );
    }

    private function publishInventoryRecord(array $payload): InventoryItem
    {
        if (! empty($payload['item_code'])) {
            return InventoryItem::updateOrCreate(
                ['item_code' => $payload['item_code']],
                $payload
            );
        }

        return InventoryItem::create($payload);
    }

    private function publishPurchaseOrder(array $payload): PurchaseOrder
    {
        if (empty($payload['po_no'])) {
            $payload['po_no'] = $this->generateBatchPoNo();
        }

        return PurchaseOrder::updateOrCreate(
            ['po_no' => $payload['po_no']],
            $payload
        );
    }

    private function generateBatchPoNo(): string
    {
        do {
            $poNo = 'PO-BATCH-'
                . now()->format('Ymd')
                . '-'
                . strtoupper(Str::random(5));
        } while (PurchaseOrder::where('po_no', $poNo)->exists());

        return $poNo;
    }

    private function purchaseStatus(string $status): string
    {
        foreach (['Ordered', 'For Pick-up', 'For Delivery', 'Delivered', 'Picked Up'] as $allowed) {
            if (strcasecmp(trim($status), $allowed) === 0) {
                return $allowed;
            }
        }

        return 'Ordered';
    }

    private function positiveNumber($value, string $label): float
    {
        $number = $this->number($value);

        if ($number === null || $number <= 0) {
            throw new RuntimeException("{$label} must be greater than zero.");
        }

        return $number;
    }

    private function nonNegativeNumber($value, string $label): float
    {
        $number = $this->number($value) ?? 0;

        if ($number < 0) {
            throw new RuntimeException("{$label} cannot be negative.");
        }

        return $number;
    }

    private function nonNegativeInteger($value, string $label): int
    {
        return (int) round($this->nonNegativeNumber($value, $label));
    }

    private function number($value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $clean = preg_replace(
            '/[^0-9.\-]/',
            '',
            str_replace(',', '', (string) $value)
        );

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function nullableText($value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
