<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\BatchProcessedRecord;
use App\Models\Admin\BatchUpload;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\FuelReport;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Warehouse\InventoryItem;
use App\Traits\SystemDataUpdateBroadcaster;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;

class GenericBatchFileProcessingController extends Controller
{
    use SystemDataUpdateBroadcaster;

    private const GENERIC_PROFILES = [
        'Maintenance' => 'Fuel Reports',
        'Warehouse' => 'Inventory Records',
        'Purchase' => 'Purchase Orders',
    ];

    public function upload(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'module' => ['required', Rule::in(array_keys(self::GENERIC_PROFILES))],
            'data_type' => ['required', 'string', 'max:100'],
            'gps_file' => [
                'required',
                'file',
                'mimes:csv,txt,xls,xlsx',
                'max:51200',
            ],
        ]);

        $module = $validated['module'];
        $dataType = trim($validated['data_type']);

        if ((self::GENERIC_PROFILES[$module] ?? null) !== $dataType) {
            return back()->with(
                'error',
                "The selected {$module} batch profile is not supported."
            );
        }

        if (! BatchUpload::supportsProcessor($module, $dataType)) {
            return back()->with(
                'error',
                "Unsupported batch processor profile: {$module} / {$dataType}."
            );
        }

        $file = $validated['gps_file'];
        $batch = null;
        $filePath = null;

        try {
            if (! $file->isValid()) {
                throw new RuntimeException('The uploaded file is invalid or incomplete.');
            }

            $extension = strtolower($file->getClientOriginalExtension());
            $directory = 'batch-files';

            if (! Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            $storedName = now()->format('YmdHis')
                . '_'
                . Str::random(10)
                . '.'
                . $extension;

            $filePath = $file->storeAs(
                $directory,
                $storedName,
                'public'
            );

            if (! $filePath || ! Storage::disk('public')->exists($filePath)) {
                throw new RuntimeException('The uploaded file could not be saved on the server.');
            }

            $batch = BatchUpload::create([
                'file_name' => $file->getClientOriginalName(),
                'stored_name' => $storedName,
                'file_path' => $filePath,
                'file_type' => $extension,
                'module' => $module,
                'data_type' => $dataType,
                'bus_no' => null,
                'uploaded_by' => Auth::id(),
                'status' => 'Processing',
                'total_records' => 0,
                'processed_records' => 0,
                'failed_records' => 0,
                'error_message' => null,
            ]);

            $rows = $this->extractRows($batch);
            $total = count($rows);
            $staged = 0;
            $failed = 0;
            $firstError = null;

            DB::transaction(function () use (
                $rows,
                $batch,
                $module,
                $dataType,
                &$staged,
                &$failed,
                &$firstError
            ) {
                foreach ($rows as $row) {
                    try {
                        $payload = $this->normalizePayload(
                            $module,
                            $dataType,
                            $row
                        );

                        BatchProcessedRecord::create([
                            'batch_upload_id' => $batch->id,
                            'payload' => $payload,
                            'raw_data' => $row,
                            'status' => 'In Review',
                        ]);

                        $staged++;
                    } catch (Throwable $exception) {
                        $failed++;
                        $firstError ??= $exception->getMessage();
                    }
                }
            });

            if ($staged === 0) {
                throw new RuntimeException(
                    $firstError ?: 'No valid records were found in the uploaded file.'
                );
            }

            $batch->update([
                'status' => 'In Review',
                'total_records' => $total,
                'processed_records' => $staged,
                'failed_records' => $failed,
                'error_message' => $failed > 0
                    ? ($firstError ?: 'Some rows require review.')
                    : null,
            ]);

            $this->broadcastSystemDataUpdated(
                'Admin',
                'BatchUpload',
                'created',
                $batch->id,
                "A {$module} batch file was uploaded for review."
            );

            $message = "{$staged} {$dataType} record(s) staged for review.";

            if ($failed > 0) {
                $message .= " {$failed} row(s) could not be parsed.";
            }

            return redirect()
                ->route('batch-file-processing.generic.review', $batch)
                ->with('success', $message);
        } catch (Throwable $exception) {
            report($exception);

            if ($batch) {
                try {
                    $batch->update([
                        'status' => 'Failed',
                        'error_message' => Str::limit($exception->getMessage(), 1000),
                    ]);
                } catch (Throwable $updateException) {
                    report($updateException);
                }
            } elseif ($filePath) {
                Storage::disk('public')->delete($filePath);
            }

            return redirect()
                ->route('batch-file-processing')
                ->with('error', 'Batch processing failed: ' . $exception->getMessage());
        }
    }

    public function review(BatchUpload $batchUpload): View|RedirectResponse
    {
        if ($batchUpload->data_type === 'GPS Trip Records') {
            return redirect()->route('batch-file-processing', [
                'batch_id' => $batchUpload->id,
            ]);
        }

        $batchUpload->load([
            'processedRecords' => fn ($query) => $query->orderBy('id'),
            'dataActivity.processor',
        ]);

        $records = $batchUpload->processedRecords;
        $headers = $records
            ->flatMap(fn (BatchProcessedRecord $record) => array_keys($record->payload ?? []))
            ->unique()
            ->values();

        return view('Admin.Data_Management.generic-batch-review', [
            'batch' => $batchUpload,
            'records' => $records,
            'headers' => $headers,
        ]);
    }

    public function updateRecords(
        Request $request,
        BatchUpload $batchUpload
    ): RedirectResponse {
        if ($batchUpload->status !== 'In Review') {
            return back()->with('error', 'Only an In Review batch can be edited.');
        }

        $validated = $request->validate([
            'records' => ['required', 'array', 'min:1'],
            'records.*' => ['required', 'array'],
        ]);

        DB::transaction(function () use ($validated, $batchUpload) {
            foreach ($validated['records'] as $recordId => $submittedPayload) {
                $record = $batchUpload->processedRecords()
                    ->whereKey((int) $recordId)
                    ->firstOrFail();

                $payload = $this->normalizePayload(
                    $batchUpload->module,
                    $batchUpload->data_type,
                    $submittedPayload
                );

                $record->update([
                    'payload' => $payload,
                    'status' => 'In Review',
                    'error_message' => null,
                ]);
            }
        });

        return back()->with('success', 'Batch review changes saved successfully.');
    }

    public function confirm(BatchUpload $batchUpload): RedirectResponse
    {
        if ($batchUpload->status !== 'In Review') {
            return back()->with('error', 'Only an In Review batch can be processed.');
        }

        $records = $batchUpload->processedRecords()->orderBy('id')->get();

        if ($records->isEmpty()) {
            return back()->with('error', 'This batch has no staged records to process.');
        }

        $published = 0;

        try {
            DB::transaction(function () use ($records, $batchUpload, &$published) {
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
                'Unable to publish this batch: ' . $exception->getMessage()
            );
        }

        $this->broadcastSystemDataUpdated(
            $batchUpload->module,
            $batchUpload->data_type,
            'updated',
            $batchUpload->id,
            "{$published} batch record(s) were published."
        );

        return back()->with(
            'success',
            "{$published} {$batchUpload->data_type} record(s) were published successfully."
        );
    }

    private function extractRows(BatchUpload $batch): array
    {
        return match (strtolower($batch->file_type)) {
            'csv', 'txt' => $this->extractDelimitedRows($batch),
            'xls', 'xlsx' => $this->extractExcelRows($batch),
            default => throw new RuntimeException(
                'This processor currently supports CSV, TXT, XLS, and XLSX files.'
            ),
        };
    }

    private function extractDelimitedRows(BatchUpload $batch): array
    {
        $absolutePath = Storage::disk('public')->path($batch->file_path);
        $handle = fopen($absolutePath, 'r');

        if (! $handle) {
            throw new RuntimeException('Unable to read the uploaded data file.');
        }

        $firstLine = fgets($handle);
        rewind($handle);

        if ($firstLine === false) {
            fclose($handle);
            throw new RuntimeException('The uploaded file is empty.');
        }

        $delimiter = $this->detectDelimiter($firstLine);
        $headerRow = fgetcsv($handle, 0, $delimiter);

        if (! $headerRow) {
            fclose($handle);
            throw new RuntimeException('The uploaded file does not contain a header row.');
        }

        $headers = array_map(
            fn ($header) => $this->normalizeHeader((string) $header),
            $headerRow
        );

        $rows = [];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (! array_filter($row, fn ($value) => trim((string) $value) !== '')) {
                continue;
            }

            $row = array_pad($row, count($headers), null);
            $data = array_combine($headers, array_slice($row, 0, count($headers)));

            if ($data) {
                $rows[] = $this->cleanRow($data);
            }
        }

        fclose($handle);

        return $rows;
    }

    private function extractExcelRows(BatchUpload $batch): array
    {
        $absolutePath = Storage::disk('public')->path($batch->file_path);
        $spreadsheet = IOFactory::load($absolutePath);
        $sheetRows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if (count($sheetRows) < 2) {
            throw new RuntimeException('The Excel file is empty or has no data rows.');
        }

        $headers = array_map(
            fn ($header) => $this->normalizeHeader((string) $header),
            $sheetRows[0]
        );

        $rows = [];

        foreach (array_slice($sheetRows, 1) as $row) {
            if (! array_filter($row, fn ($value) => trim((string) $value) !== '')) {
                continue;
            }

            $row = array_pad($row, count($headers), null);
            $data = array_combine($headers, array_slice($row, 0, count($headers)));

            if (! $data) {
                continue;
            }

            foreach ($data as $key => $value) {
                if (is_numeric($value) && $this->looksLikeDateField($key)) {
                    try {
                        $value = ExcelDate::excelToDateTimeObject($value)
                            ->format('Y-m-d H:i:s');
                    } catch (Throwable) {
                        // Keep the original value when it is not an Excel date serial.
                    }
                }

                $data[$key] = $value;
            }

            $rows[] = $this->cleanRow($data);
        }

        return $rows;
    }

    private function normalizePayload(
        string $module,
        string $dataType,
        array $row
    ): array {
        return match ([$module, $dataType]) {
            ['Maintenance', 'Fuel Reports'] => $this->mapFuelReport($row),
            ['Warehouse', 'Inventory Records'] => $this->mapInventoryRecord($row),
            ['Purchase', 'Purchase Orders'] => $this->mapPurchaseOrder($row),
            default => throw new RuntimeException(
                "No parser is registered for {$module} / {$dataType}."
            ),
        };
    }

    private function mapFuelReport(array $row): array
    {
        $reportDate = $this->value($row, ['report date', 'date', 'fuel date']);
        $busNo = $this->value($row, ['bus no', 'bus number', 'bus', 'vehicle no', 'vehicle']);
        $driverName = $this->value($row, ['driver name', 'driver', 'operator']);
        $distanceKm = $this->number($this->value($row, ['distance km', 'distance', 'mileage km', 'mileage']));
        $fuelLiters = $this->number($this->value($row, ['fuel liters', 'liters', 'fuel', 'litres']));
        $remarks = $this->value($row, ['remarks', 'notes', 'description']);

        if (! $reportDate) {
            throw new RuntimeException('Fuel Report row is missing Report Date.');
        }

        if (! $busNo) {
            throw new RuntimeException('Fuel Report row is missing Bus No.');
        }

        if (! Bus::query()->whereRaw('UPPER(TRIM(bus_no)) = ?', [strtoupper(trim($busNo))])->exists()) {
            throw new RuntimeException("Bus {$busNo} does not exist in the Bus Master List.");
        }

        if ($fuelLiters === null || $fuelLiters <= 0) {
            throw new RuntimeException('Fuel Report row must contain Fuel Liters greater than zero.');
        }

        if ($distanceKm === null || $distanceKm <= 0) {
            throw new RuntimeException('Fuel Report row must contain Distance KM greater than zero.');
        }

        $date = Carbon::parse($reportDate)->toDateString();
        $efficiency = $distanceKm / $fuelLiters;

        return [
            'report_date' => $date,
            'bus_no' => trim($busNo),
            'driver_name' => $driverName,
            'distance_km' => round($distanceKm, 2),
            'fuel_liters' => round($fuelLiters, 2),
            'km_per_liter' => round($efficiency, 2),
            'status' => $this->fuelStatus($efficiency),
            'remarks' => $remarks,
        ];
    }

    private function mapInventoryRecord(array $row): array
    {
        $itemCode = $this->value($row, ['item code', 'code', 'part code', 'parts code', 'sku']);
        $itemName = $this->value($row, ['parts name', 'part name', 'item name', 'item', 'description']);
        $category = $this->value($row, ['category', 'type']);
        $onHand = $this->integer($this->value($row, ['on hand', 'quantity available', 'quantity', 'stock', 'qty'])) ?? 0;
        $unit = $this->value($row, ['unit', 'unit of measurement', 'uom']) ?: 'pcs';
        $reorderLevel = $this->integer($this->value($row, ['reorder level', 'reorder', 'minimum stock', 'min stock'])) ?? 0;
        $supplier = $this->value($row, ['supplier', 'supplier name']);
        $location = $this->value($row, ['location', 'storage location', 'warehouse location']);

        if (! $itemCode && ! $itemName) {
            throw new RuntimeException('Inventory row must contain Item Code or Item Name.');
        }

        if ($onHand < 0 || $reorderLevel < 0) {
            throw new RuntimeException('Inventory quantity values cannot be negative.');
        }

        return [
            'item_code' => $itemCode,
            'item_name' => $itemName ?: $itemCode,
            'parts_name' => $itemName ?: $itemCode,
            'category' => $category,
            'on_hand' => $onHand,
            'quantity_available' => $onHand,
            'unit' => $unit,
            'unit_of_measurement' => $unit,
            'reorder_level' => $reorderLevel,
            'status' => $this->inventoryStatus($onHand, $reorderLevel),
            'supplier' => $supplier,
            'location' => $location,
            'storage_location' => $location,
        ];
    }

    private function mapPurchaseOrder(array $row): array
    {
        $poNo = $this->value($row, ['po no', 'po number', 'purchase order no', 'purchase order number']);
        $poDate = $this->value($row, ['po date', 'date', 'purchase date']) ?: now()->toDateString();
        $supplierName = $this->value($row, ['supplier name', 'supplier']);
        $supplierAddress = $this->value($row, ['supplier address tel', 'supplier address', 'address tel', 'address']);
        $terms = $this->value($row, ['terms']);
        $termsOfPayment = $this->value($row, ['terms of payment', 'payment terms']);
        $purpose = $this->value($row, ['purpose', 'remarks', 'notes']);
        $status = $this->normalizePurchaseStatus($this->value($row, ['status']) ?: 'Ordered');

        if (! $supplierName) {
            throw new RuntimeException('Purchase Order row is missing Supplier Name.');
        }

        $itemsValue = $this->value($row, ['items', 'items json']);
        $items = null;

        if ($itemsValue) {
            $decoded = json_decode($itemsValue, true);
            if (is_array($decoded)) {
                $items = $this->normalizePurchaseItems($decoded);
            }
        }

        if (! $items) {
            $description = $this->value($row, ['item description', 'description', 'item', 'part name']);
            $quantity = $this->number($this->value($row, ['quantity', 'qty'])) ?? 1;
            $cost = $this->number($this->value($row, ['cost', 'unit cost', 'price'])) ?? 0;

            if (! $description) {
                throw new RuntimeException(
                    'Purchase Order row must contain Items JSON or an Item Description.'
                );
            }

            if ($quantity <= 0 || $cost < 0) {
                throw new RuntimeException('Purchase Order quantity/cost values are invalid.');
            }

            $items = [[
                'pr_no' => $this->value($row, ['pr no', 'pr number']),
                'bus_no' => $this->value($row, ['bus no', 'bus number']),
                'employee' => $this->value($row, ['employee', 'requested by']),
                'item_description' => $description,
                'quantity' => $quantity,
                'unit' => $this->value($row, ['unit', 'uom']),
                'cost' => $cost,
            ]];
        }

        $grossAmount = collect($items)->sum(
            fn ($item) => ((float) ($item['quantity'] ?? 0)) * ((float) ($item['cost'] ?? 0))
        );
        $deliveryFee = $this->number($this->value($row, ['delivery fee', 'delivery'])) ?? 0;
        $discount = $this->number($this->value($row, ['discount'])) ?? 0;
        $vat = $this->number($this->value($row, ['vat', 'tax'])) ?? 0;
        $netAmount = max(0, $grossAmount + $deliveryFee + $vat - $discount);

        return [
            'po_no' => $poNo,
            'po_date' => Carbon::parse($poDate)->toDateString(),
            'supplier_name' => $supplierName,
            'supplier_address_tel' => $supplierAddress,
            'terms' => $terms,
            'terms_of_payment' => $termsOfPayment,
            'purpose' => $purpose,
            'items' => $items,
            'gross_amount' => round($grossAmount, 2),
            'delivery_fee' => round($deliveryFee, 2),
            'discount' => round($discount, 2),
            'vat' => round($vat, 2),
            'net_amount' => round($netAmount, 2),
            'status' => $status,
        ];
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

    private function normalizePurchaseItems(array $items): array
    {
        $normalized = [];

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

            $normalized[] = [
                'pr_no' => $item['pr_no'] ?? null,
                'bus_no' => $item['bus_no'] ?? null,
                'employee' => $item['employee'] ?? null,
                'item_description' => $description,
                'quantity' => $quantity,
                'unit' => $item['unit'] ?? null,
                'cost' => $cost,
            ];
        }

        if ($normalized === []) {
            throw new RuntimeException('Purchase Order Items JSON contains no valid items.');
        }

        return $normalized;
    }

    private function normalizePurchaseStatus(string $status): string
    {
        $statuses = [
            'Ordered',
            'For Pick-up',
            'For Delivery',
            'Delivered',
            'Picked Up',
        ];

        foreach ($statuses as $allowed) {
            if (strcasecmp(trim($status), $allowed) === 0) {
                return $allowed;
            }
        }

        return 'Ordered';
    }

    private function generateBatchPoNo(): string
    {
        do {
            $poNo = 'PO-BATCH-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));
        } while (PurchaseOrder::where('po_no', $poNo)->exists());

        return $poNo;
    }

    private function inventoryStatus(int $onHand, int $reorderLevel): string
    {
        if ($onHand <= 0) {
            return 'Critical';
        }

        if ($reorderLevel > 0 && $onHand <= $reorderLevel) {
            return 'Low Stock';
        }

        return 'In Stock';
    }

    private function fuelStatus(float $kmPerLiter): string
    {
        return match (true) {
            $kmPerLiter >= 6 => 'Efficient',
            $kmPerLiter >= 4 => 'Normal',
            $kmPerLiter > 0 => 'Inefficient',
            default => 'No Data',
        };
    }

    private function cleanRow(array $row): array
    {
        $clean = [];

        foreach ($row as $key => $value) {
            $normalizedKey = $this->normalizeHeader((string) $key);

            if (is_array($value)) {
                $clean[$normalizedKey] = $value;
                continue;
            }

            $value = trim((string) ($value ?? ''));
            $clean[$normalizedKey] = $value === '' ? null : $value;
        }

        return $clean;
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        $header = mb_strtolower(trim($header), 'UTF-8');
        $header = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $header);

        return trim(preg_replace('/\s+/', ' ', $header));
    }

    private function value(array $row, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            $key = $this->normalizeHeader($alias);

            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];

            if (is_array($value)) {
                return json_encode($value);
            }

            $value = trim((string) ($value ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function number(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $value));

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function integer(?string $value): ?int
    {
        $number = $this->number($value);

        return $number === null ? null : (int) round($number);
    }

    private function detectDelimiter(string $line): string
    {
        $delimiters = [',', "\t", ';', '|'];
        $best = ',';
        $highest = -1;

        foreach ($delimiters as $delimiter) {
            $count = substr_count($line, $delimiter);

            if ($count > $highest) {
                $highest = $count;
                $best = $delimiter;
            }
        }

        return $best;
    }

    private function looksLikeDateField(string $field): bool
    {
        return str_contains($field, 'date')
            || str_contains($field, 'time')
            || in_array($field, ['beginning', 'end', 'ending'], true);
    }
}
