<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\DataActivity;
use App\Models\Admin\GpsTripRecord;
use App\Models\Maintenance\FuelReport;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Warehouse\InventoryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminImportExportController extends Controller
{
    private const PROFILES = [
        'Operation' => 'GPS Trip Records',
        'Maintenance' => 'Fuel Reports',
        'Warehouse' => 'Inventory Records',
        'Purchase' => 'Purchase Orders',
    ];

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'module' => ['required', Rule::in(array_keys(self::PROFILES))],
            'data_type' => ['required', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'format' => ['required', Rule::in(['csv', 'xlsx'])],
        ]);

        $module = $validated['module'];
        $dataType = trim($validated['data_type']);
        $this->ensureProfile($module, $dataType);

        [$headers, $rows] = $this->exportRows(
            $module,
            $dataType,
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null
        );

        if ($rows === []) {
            return response()->json([
                'message' => 'No records found for the selected module, record type, and date filters.',
                'records' => 0,
            ], 422);
        }

        $extension = $validated['format'];
        $fileName = Str::slug($module . '-' . $dataType) . '-' . now()->format('Ymd-His') . '.' . $extension;
        $count = count($rows);

        DataActivity::create([
            'activity_type' => 'Export',
            'module' => $module,
            'data_type' => $dataType,
            'file_name' => $fileName,
            'source' => 'FROMS Database',
            'status' => 'Completed',
            'total_records' => $count,
            'successful_records' => $count,
            'failed_records' => 0,
            'skipped_records' => 0,
            'processed_by' => Auth::id(),
            'details' => [
                'format' => strtoupper($extension),
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
            ],
            'completed_at' => now(),
        ]);

        $headersOut = [
            'X-FROMS-Export-Count' => (string) $count,
        ];

        if ($extension === 'csv') {
            return response()->streamDownload(function () use ($headers, $rows) {
                $handle = fopen('php://output', 'w');
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, $headers);
                foreach ($rows as $row) fputcsv($handle, $row);
                fclose($handle);
            }, $fileName, array_merge(['Content-Type' => 'text/csv; charset=UTF-8'], $headersOut));
        }

        return response()->streamDownload(function () use ($headers, $rows) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray($headers, null, 'A1');
            $sheet->fromArray($rows, null, 'A2');
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $fileName, array_merge([
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ], $headersOut));
    }

    private function ensureProfile(string $module, string $dataType): void
    {
        if ((self::PROFILES[$module] ?? null) !== $dataType) {
            throw new RuntimeException('The selected module/data type mapping is not supported.');
        }
    }

    private function exportRows(string $module, string $dataType, ?string $from, ?string $to): array
    {
        if ($module === 'Operation') {
            $query = GpsTripRecord::query()->orderBy('beginning_at');
            if ($from) $query->whereDate('beginning_at', '>=', $from);
            if ($to) $query->whereDate('beginning_at', '<=', $to);
            $headers = ['Record No', 'Bus No', 'Grouping', 'Trip Type', 'Beginning', 'Initial Location', 'End', 'Final Location', 'Duration Minutes', 'Total Minutes', 'In Motion Minutes', 'Idling Minutes', 'Mileage KM', 'Engine Hours', 'Severity'];
            $rows = $query->get()->map(fn ($r) => [
                $r->record_no, $r->bus_no, $r->grouping, $r->trip_type, $r->beginning_at?->format('Y-m-d H:i:s'),
                $r->initial_location, $r->ending_at?->format('Y-m-d H:i:s'), $r->final_location,
                $r->duration_minutes, $r->total_minutes, $r->in_motion_minutes, $r->idling_minutes,
                $r->mileage_km, $r->engine_hours, $r->severity,
            ])->all();
            return [$headers, $rows];
        }

        if ($module === 'Maintenance') {
            $query = FuelReport::query()->orderBy('report_date');
            if ($from) $query->whereDate('report_date', '>=', $from);
            if ($to) $query->whereDate('report_date', '<=', $to);
            $headers = ['Report Date', 'Bus No', 'Driver Name', 'Distance KM', 'Fuel Liters', 'KM Per Liter', 'Status', 'Remarks'];
            $rows = $query->get()->map(fn ($r) => [$r->report_date?->format('Y-m-d'), $r->bus_no, $r->driver_name, $r->distance_km, $r->fuel_liters, $r->km_per_liter, $r->status, $r->remarks])->all();
            return [$headers, $rows];
        }

        if ($module === 'Warehouse') {
            $query = InventoryItem::query()->orderBy('item_code');
            if ($from) $query->whereDate('created_at', '>=', $from);
            if ($to) $query->whereDate('created_at', '<=', $to);
            $headers = ['Item Code', 'Item Name', 'Category', 'On Hand', 'Unit', 'Reorder Level', 'Status', 'Supplier', 'Location'];
            $rows = $query->get()->map(fn ($r) => [$r->item_code, $r->parts_name ?? $r->item_name, $r->category, $r->on_hand, $r->unit, $r->reorder_level, $r->status, $r->supplier, $r->location ?? $r->storage_location])->all();
            return [$headers, $rows];
        }

        if ($module === 'Purchase') {
            $query = PurchaseOrder::query()->orderBy('po_date');
            if ($from) $query->whereDate('po_date', '>=', $from);
            if ($to) $query->whereDate('po_date', '<=', $to);
            $headers = ['PO No', 'PO Date', 'Supplier Name', 'Purpose', 'Gross Amount', 'Delivery Fee', 'Discount', 'VAT', 'Net Amount', 'Status', 'Items JSON'];
            $rows = $query->get()->map(fn ($r) => [$r->po_no, $r->po_date?->format('Y-m-d'), $r->supplier_name, $r->purpose, $r->gross_amount, $r->delivery_fee, $r->discount, $r->vat, $r->net_amount, $r->status, json_encode($r->items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)])->all();
            return [$headers, $rows];
        }

        throw new RuntimeException('No export mapping is registered for this profile.');
    }
}
