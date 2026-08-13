<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\DataActivity;
use App\Models\Admin\GpsTripRecord;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\FuelReport;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Warehouse\InventoryItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;

class StructuredImportReviewController extends Controller
{
    private const PROFILES = [
        'Operation' => 'GPS Trip Records',
        'Maintenance' => 'Fuel Reports',
        'Warehouse' => 'Inventory Records',
        'Purchase' => 'Purchase Orders',
    ];

    public function stage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module' => ['required', Rule::in(array_keys(self::PROFILES))],
            'data_type' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'mimes:csv,txt,xls,xlsx', 'max:51200'],
        ]);

        $module = $validated['module'];
        $dataType = trim($validated['data_type']);
        $this->ensureProfile($module, $dataType);

        $file = $validated['file'];
        $rows = $this->extractRows($file->getRealPath(), strtolower($file->getClientOriginalExtension()));
        $payloads = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            try {
                $payloads[] = $this->normalizePayload($module, $dataType, $row);
            } catch (Throwable $exception) {
                $errors[] = ['row' => $index + 2, 'message' => $exception->getMessage()];
            }
        }

        $total = count($rows);
        $valid = count($payloads);
        $invalid = count($errors);

        if ($total === 0) {
            return response()->json([
                'message' => 'The selected file contains no data rows.',
                'validation' => ['total' => 0, 'valid' => 0, 'invalid' => 0],
            ], 422);
        }

        if ($invalid > 0) {
            $activity = DataActivity::create([
                'activity_type' => 'Import',
                'module' => $module,
                'data_type' => $dataType,
                'file_name' => $file->getClientOriginalName(),
                'source' => 'Structured File Import',
                'status' => 'Needs Correction',
                'total_records' => $total,
                'successful_records' => $valid,
                'failed_records' => $invalid,
                'skipped_records' => 0,
                'processed_by' => Auth::id(),
                'details' => [
                    'format' => strtoupper($file->getClientOriginalExtension()),
                    'validation_errors' => array_slice($errors, 0, 100),
                ],
                'error_message' => $errors[0]['message'] ?? 'Import validation failed.',
            ]);

            return response()->json([
                'message' => "{$invalid} row(s) need correction. Nothing was staged for approval.",
                'activity_id' => $activity->id,
                'status' => 'Needs Correction',
                'validation' => ['total' => $total, 'valid' => $valid, 'invalid' => $invalid],
                'errors' => array_slice($errors, 0, 25),
            ], 422);
        }

        $activity = DataActivity::create([
            'activity_type' => 'Import',
            'module' => $module,
            'data_type' => $dataType,
            'file_name' => $file->getClientOriginalName(),
            'source' => 'Structured File Import',
            'status' => 'For Review',
            'total_records' => $total,
            'successful_records' => $total,
            'failed_records' => 0,
            'skipped_records' => 0,
            'processed_by' => Auth::id(),
            'details' => [
                'format' => strtoupper($file->getClientOriginalExtension()),
                'staged_payloads' => $payloads,
                'validation_errors' => [],
            ],
        ]);

        return response()->json([
            'message' => "{$total} {$dataType} record(s) validated and are ready for approval.",
            'activity_id' => $activity->id,
            'status' => 'For Review',
            'validation' => ['total' => $total, 'valid' => $total, 'invalid' => 0],
        ]);
    }

    public function details(DataActivity $activity): JsonResponse
    {
        abort_unless(in_array($activity->activity_type, ['Import', 'Export'], true), 404);

        $details = $activity->details ?? [];

        return response()->json([
            'id' => $activity->id,
            'file_name' => $activity->file_name ?: 'System Data Activity',
            'activity_type' => $activity->activity_type,
            'module' => $activity->module ?: '—',
            'data_type' => $activity->data_type ?: '—',
            'source' => $activity->source ?: '—',
            'status' => $activity->status,
            'total_records' => $activity->total_records,
            'successful_records' => $activity->successful_records,
            'failed_records' => $activity->failed_records,
            'skipped_records' => $activity->skipped_records,
            'processed_by' => $activity->processor?->name ?? 'System',
            'date_time' => optional($activity->created_at)->format('M d, Y · g:i A'),
            'error_message' => $activity->error_message,
            'validation_errors' => array_values($details['validation_errors'] ?? []),
            'can_approve' => $activity->activity_type === 'Import'
                && $activity->status === 'For Review'
                && ! empty($details['staged_payloads']),
        ]);
    }

    public function approve(DataActivity $activity): JsonResponse
    {
        if ($activity->activity_type !== 'Import' || $activity->status !== 'For Review') {
            return response()->json(['message' => 'Only imports currently marked For Review can be approved.'], 422);
        }

        $details = $activity->details ?? [];
        $payloads = $details['staged_payloads'] ?? [];
        if (! is_array($payloads) || $payloads === []) {
            return response()->json(['message' => 'This review does not contain staged records to approve.'], 422);
        }

        $saved = 0;
        DB::transaction(function () use ($activity, $payloads, &$saved) {
            foreach ($payloads as $payload) {
                $this->publishRecord($activity->module, $activity->data_type, $payload);
                $saved++;
            }

            $details = $activity->details ?? [];
            unset($details['staged_payloads']);
            $details['approved_at'] = now()->toIso8601String();
            $details['approved_by'] = Auth::id();

            $activity->update([
                'status' => 'Completed',
                'successful_records' => $saved,
                'failed_records' => 0,
                'error_message' => null,
                'details' => $details,
                'completed_at' => now(),
            ]);
        });

        return response()->json([
            'message' => "{$saved} {$activity->data_type} record(s) approved and imported successfully.",
            'status' => 'Completed',
            'saved' => $saved,
        ]);
    }

    private function ensureProfile(string $module, string $dataType): void
    {
        if ((self::PROFILES[$module] ?? null) !== $dataType) {
            throw new RuntimeException('The selected module/data type mapping is not supported.');
        }
    }

    private function extractRows(string $path, string $extension): array
    {
        if (in_array($extension, ['xls', 'xlsx'], true)) {
            $spreadsheet = IOFactory::load($path);
            $sheetRows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            if (count($sheetRows) < 2) return [];
            $headers = array_map(fn ($v) => $this->normalizeHeader((string) $v), $sheetRows[0]);
            $rows = [];
            foreach (array_slice($sheetRows, 1) as $row) {
                if (! array_filter($row, fn ($v) => trim((string) $v) !== '')) continue;
                $row = array_pad($row, count($headers), null);
                $data = array_combine($headers, array_slice($row, 0, count($headers)));
                if (! $data) continue;
                foreach ($data as $key => $value) {
                    if (is_numeric($value) && (str_contains($key, 'date') || str_contains($key, 'time') || in_array($key, ['beginning', 'end', 'ending'], true))) {
                        try { $value = ExcelDate::excelToDateTimeObject($value)->format('Y-m-d H:i:s'); } catch (Throwable) {}
                    }
                    $data[$key] = $value;
                }
                $rows[] = $this->cleanRow($data);
            }
            return $rows;
        }

        $handle = fopen($path, 'r');
        if (! $handle) throw new RuntimeException('Unable to read the selected file.');
        $first = fgets($handle);
        rewind($handle);
        if ($first === false) { fclose($handle); return []; }
        $delimiter = $this->detectDelimiter($first);
        $headerRow = fgetcsv($handle, 0, $delimiter);
        if (! $headerRow) { fclose($handle); return []; }
        $headers = array_map(fn ($v) => $this->normalizeHeader((string) $v), $headerRow);
        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (! array_filter($row, fn ($v) => trim((string) $v) !== '')) continue;
            $row = array_pad($row, count($headers), null);
            $data = array_combine($headers, array_slice($row, 0, count($headers)));
            if ($data) $rows[] = $this->cleanRow($data);
        }
        fclose($handle);
        return $rows;
    }

    private function normalizePayload(string $module, string $dataType, array $row): array
    {
        return match ([$module, $dataType]) {
            ['Operation', 'GPS Trip Records'] => $this->mapGpsTrip($row),
            ['Maintenance', 'Fuel Reports'] => $this->mapFuelReport($row),
            ['Warehouse', 'Inventory Records'] => $this->mapInventoryRecord($row),
            ['Purchase', 'Purchase Orders'] => $this->mapPurchaseOrder($row),
            default => throw new RuntimeException('No import mapping is registered for this profile.'),
        };
    }

    private function mapGpsTrip(array $row): array
    {
        $busNo = $this->value($row, ['bus no', 'bus number', 'bus', 'vehicle no']);
        $beginning = $this->value($row, ['beginning', 'beginning at', 'start', 'start time']);
        $ending = $this->value($row, ['end', 'ending', 'ending at', 'end time']);
        if (! $busNo || ! $beginning) throw new RuntimeException('GPS Trip row requires Bus No. and Beginning time.');
        $this->assertBusExists($busNo);
        $start = Carbon::parse($beginning);
        $end = $ending ? Carbon::parse($ending) : null;
        $duration = $this->integer($this->value($row, ['duration minutes', 'duration', 'total time', 'total minutes']));
        if ($duration === null && $end) $duration = max(0, $start->diffInMinutes($end));
        return [
            'record_no' => $this->value($row, ['record no', 'record number']) ?: 'GPS-IMP-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(4)),
            'bus_no' => trim($busNo),
            'grouping' => $this->value($row, ['grouping', 'route', 'grouping route']),
            'trip_type' => $this->value($row, ['trip type', 'type']) ?: 'Mileage Report',
            'beginning_at' => $start->format('Y-m-d H:i:s'),
            'initial_location' => $this->value($row, ['initial location', 'start location']),
            'ending_at' => $end?->format('Y-m-d H:i:s'),
            'final_location' => $this->value($row, ['final location', 'end location']),
            'duration_minutes' => $duration,
            'total_minutes' => $this->integer($this->value($row, ['total minutes', 'total time'])) ?? $duration,
            'in_motion_minutes' => $this->integer($this->value($row, ['in motion', 'in motion minutes'])),
            'idling_minutes' => $this->integer($this->value($row, ['idling', 'idling minutes'])),
            'mileage_km' => $this->number($this->value($row, ['mileage', 'mileage km', 'distance km'])),
            'engine_hours' => $this->number($this->value($row, ['engine hours'])),
            'location' => $this->value($row, ['location']),
            'coordinates' => $this->value($row, ['coordinates']),
            'description' => $this->value($row, ['description', 'remarks', 'notes']),
            'severity' => $this->value($row, ['severity']) ?: 'Normal',
        ];
    }

    private function mapFuelReport(array $row): array
    {
        $date = $this->value($row, ['report date', 'date', 'fuel date']);
        $busNo = $this->value($row, ['bus no', 'bus number', 'bus', 'vehicle no', 'vehicle']);
        $driver = $this->value($row, ['driver name', 'driver', 'operator']);
        $distance = $this->number($this->value($row, ['distance km', 'distance', 'mileage km', 'mileage']));
        $liters = $this->number($this->value($row, ['fuel liters', 'liters', 'fuel', 'litres']));
        if (! $date || ! $busNo || $distance === null || $distance <= 0 || $liters === null || $liters <= 0) {
            throw new RuntimeException('Fuel row requires Report Date, Bus No., Distance KM, and Fuel Liters greater than zero.');
        }
        $this->assertBusExists($busNo);
        $efficiency = $distance / $liters;
        return [
            'report_date' => Carbon::parse($date)->toDateString(),
            'bus_no' => trim($busNo),
            'driver_name' => $driver,
            'distance_km' => round($distance, 2),
            'fuel_liters' => round($liters, 2),
            'km_per_liter' => round($efficiency, 2),
            'status' => $efficiency >= 6 ? 'Efficient' : ($efficiency >= 4 ? 'Normal' : 'Inefficient'),
            'remarks' => $this->value($row, ['remarks', 'notes', 'description']),
        ];
    }

    private function mapInventoryRecord(array $row): array
    {
        $code = $this->value($row, ['item code', 'code', 'part code', 'parts code', 'sku']);
        $name = $this->value($row, ['parts name', 'part name', 'item name', 'item', 'description']);
        $qty = $this->integer($this->value($row, ['on hand', 'quantity available', 'quantity', 'stock', 'qty'])) ?? 0;
        $reorder = $this->integer($this->value($row, ['reorder level', 'reorder', 'minimum stock', 'min stock'])) ?? 0;
        if (! $code && ! $name) throw new RuntimeException('Inventory row must contain Item Code or Item Name.');
        if ($qty < 0 || $reorder < 0) throw new RuntimeException('Inventory quantity values cannot be negative.');
        $unit = $this->value($row, ['unit', 'unit of measurement', 'uom']) ?: 'pcs';
        $location = $this->value($row, ['location', 'storage location', 'warehouse location']);
        return [
            'item_code' => $code,
            'item_name' => $name ?: $code,
            'parts_name' => $name ?: $code,
            'category' => $this->value($row, ['category', 'type']),
            'on_hand' => $qty,
            'quantity_available' => $qty,
            'unit' => $unit,
            'unit_of_measurement' => $unit,
            'reorder_level' => $reorder,
            'status' => $qty <= 0 ? 'Critical' : ($reorder > 0 && $qty <= $reorder ? 'Low Stock' : 'In Stock'),
            'supplier' => $this->value($row, ['supplier', 'supplier name']),
            'location' => $location,
            'storage_location' => $location,
        ];
    }

    private function mapPurchaseOrder(array $row): array
    {
        $supplier = $this->value($row, ['supplier name', 'supplier']);
        $description = $this->value($row, ['item description', 'description', 'item', 'part name']);
        $quantity = $this->number($this->value($row, ['quantity', 'qty'])) ?? 1;
        $cost = $this->number($this->value($row, ['cost', 'unit cost', 'price'])) ?? 0;
        if (! $supplier || ! $description || $quantity <= 0 || $cost < 0) {
            throw new RuntimeException('Purchase Order row requires Supplier, Item Description, valid Quantity, and Cost.');
        }
        $gross = $quantity * $cost;
        $delivery = $this->number($this->value($row, ['delivery fee', 'delivery'])) ?? 0;
        $discount = $this->number($this->value($row, ['discount'])) ?? 0;
        $vat = $this->number($this->value($row, ['vat', 'tax'])) ?? 0;
        return [
            'po_no' => $this->value($row, ['po no', 'po number', 'purchase order no', 'purchase order number']),
            'po_date' => Carbon::parse($this->value($row, ['po date', 'date', 'purchase date']) ?: now())->toDateString(),
            'supplier_name' => $supplier,
            'supplier_address_tel' => $this->value($row, ['supplier address tel', 'supplier address', 'address']),
            'terms' => $this->value($row, ['terms']),
            'terms_of_payment' => $this->value($row, ['terms of payment', 'payment terms']),
            'purpose' => $this->value($row, ['purpose', 'remarks', 'notes']),
            'items' => [[
                'pr_no' => $this->value($row, ['pr no', 'pr number']),
                'bus_no' => $this->value($row, ['bus no', 'bus number']),
                'employee' => $this->value($row, ['employee', 'requested by']),
                'item_description' => $description,
                'quantity' => $quantity,
                'unit' => $this->value($row, ['unit', 'uom']),
                'cost' => $cost,
            ]],
            'gross_amount' => round($gross, 2),
            'delivery_fee' => round($delivery, 2),
            'discount' => round($discount, 2),
            'vat' => round($vat, 2),
            'net_amount' => round(max(0, $gross + $delivery + $vat - $discount), 2),
            'status' => 'Ordered',
        ];
    }

    private function publishRecord(string $module, string $dataType, array $payload): void
    {
        match ([$module, $dataType]) {
            ['Operation', 'GPS Trip Records'] => GpsTripRecord::updateOrCreate(
                ['record_no' => $payload['record_no']],
                array_merge($payload, ['batch_upload_id' => null, 'source_format' => 'Structured Import', 'raw_data' => $payload])
            ),
            ['Maintenance', 'Fuel Reports'] => FuelReport::updateOrCreate(
                ['report_date' => $payload['report_date'], 'bus_no' => $payload['bus_no']],
                array_merge($payload, [
                    'gps_trip_record_id' => null,
                    'distance_source' => 'Structured Import',
                    'manual_distance_reason' => 'Approved through Admin Import / Export.',
                ])
            ),
            ['Warehouse', 'Inventory Records'] => ! empty($payload['item_code'])
                ? InventoryItem::updateOrCreate(['item_code' => $payload['item_code']], $payload)
                : InventoryItem::create($payload),
            ['Purchase', 'Purchase Orders'] => $this->publishPurchaseOrder($payload),
            default => throw new RuntimeException('No publisher is registered for this profile.'),
        };
    }

    private function publishPurchaseOrder(array $payload): void
    {
        $poNo = $payload['po_no'] ?: $this->generatePoNo();
        PurchaseOrder::updateOrCreate(['po_no' => $poNo], array_merge($payload, ['po_no' => $poNo]));
    }

    private function assertBusExists(string $busNo): void
    {
        if (! Bus::query()->whereRaw('UPPER(TRIM(bus_no)) = ?', [strtoupper(trim($busNo))])->exists()) {
            throw new RuntimeException("Bus {$busNo} does not exist in the Bus Master List.");
        }
    }

    private function generatePoNo(): string
    {
        do {
            $poNo = 'PO-IMP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));
        } while (PurchaseOrder::where('po_no', $poNo)->exists());
        return $poNo;
    }

    private function cleanRow(array $row): array
    {
        $clean = [];
        foreach ($row as $key => $value) {
            $key = $this->normalizeHeader((string) $key);
            $value = trim((string) ($value ?? ''));
            $clean[$key] = $value === '' ? null : $value;
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
            if (! array_key_exists($key, $row)) continue;
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') return $value;
        }
        return null;
    }

    private function number(?string $value): ?float
    {
        if ($value === null || trim($value) === '') return null;
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
        $best = ',';
        $highest = -1;
        foreach ([',', "\t", ';', '|'] as $delimiter) {
            $count = substr_count($line, $delimiter);
            if ($count > $highest) {
                $highest = $count;
                $best = $delimiter;
            }
        }
        return $best;
    }
}
