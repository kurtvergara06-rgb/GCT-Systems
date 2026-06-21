<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\BatchUpload;
use App\Models\Admin\GpsTripRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BatchFileProcessingController extends Controller
{
    public function index(Request $request)
    {
        $batches = BatchUpload::query()
            ->withCount('tripRecords')
            ->latest()
            ->get();

        $selectedBatchId = $request->integer('batch_id');

        if (! $selectedBatchId && $batches->isNotEmpty()) {
            $selectedBatchId = $batches->first()->id;
        }

        $recordsQuery = GpsTripRecord::query()
            ->with('batchUpload')
            ->when($selectedBatchId, function ($query) use ($selectedBatchId) {
                $query->where('batch_upload_id', $selectedBatchId);
            });

        if ($request->filled('search')) {
            $search = trim($request->search);

            $recordsQuery->where(function ($query) use ($search) {
                $query->where('bus_no', 'like', "%{$search}%")
                    ->orWhere('grouping', 'like', "%{$search}%")
                    ->orWhere('initial_location', 'like', "%{$search}%")
                    ->orWhere('final_location', 'like', "%{$search}%");
            });
        }

        $records = $recordsQuery
            ->latest('beginning_at')
            ->paginate(10)
            ->withQueryString();

        $selectedRecordId = $request->integer('selected_record');

        $selectedRecord = $selectedRecordId
            ? GpsTripRecord::find($selectedRecordId)
            : $records->first();

        $filesUploaded = BatchUpload::count();
        $processedBatches = BatchUpload::where('status', 'Processed')->count();
        $inReviewBatches = BatchUpload::where('status', 'In Review')->count();
        $recordsExtracted = GpsTripRecord::count();

        return view('Admin.batch-file-processing', compact(
            'batches',
            'records',
            'selectedRecord',
            'selectedBatchId',
            'filesUploaded',
            'processedBatches',
            'inReviewBatches',
            'recordsExtracted'
        ));
    }

    public function upload(Request $request)
    {
        $validated = $request->validate([
            'gps_file' => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
        ]);

        $file = $validated['gps_file'];

        $storedName = now()->format('YmdHis')
            . '_'
            . Str::random(10)
            . '.'
            . $file->getClientOriginalExtension();

        $filePath = $file->storeAs('gps-batches', $storedName, 'public');

        $batch = BatchUpload::create([
            'file_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'file_path' => $filePath,
            'file_type' => strtolower($file->getClientOriginalExtension()),
            'bus_no' => 'Multiple Buses',
            'uploaded_by' => auth()->id(),
            'status' => 'Processing',
            'total_records' => 0,
            'processed_records' => 0,
            'failed_records' => 0,
        ]);

        try {
            $result = $this->processCsvFile($batch);

            $batch->update([
                'status' => $result['failed'] > 0 ? 'In Review' : 'Processed',
                'total_records' => $result['total'],
                'processed_records' => $result['processed'],
                'failed_records' => $result['failed'],
                'error_message' => $result['failed'] > 0
                    ? 'Some rows could not be processed. Please review the GPS report.'
                    : null,
            ]);

            return redirect()
                ->route('batch-file-processing', [
                    'batch_id' => $batch->id,
                ])
                ->with(
                    'success',
                    "{$result['processed']} GPS trip record(s) processed successfully."
                );
        } catch (\Throwable $exception) {
            $batch->update([
                'status' => 'Failed',
                'error_message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('batch-file-processing')
                ->with('error', $exception->getMessage());
        }
    }

    public function export(Request $request): StreamedResponse
    {
        $query = GpsTripRecord::query()
            ->when($request->filled('batch_id'), function ($query) use ($request) {
                $query->where('batch_upload_id', $request->batch_id);
            });

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($builder) use ($search) {
                $builder->where('bus_no', 'like', "%{$search}%")
                    ->orWhere('grouping', 'like', "%{$search}%")
                    ->orWhere('initial_location', 'like', "%{$search}%")
                    ->orWhere('final_location', 'like', "%{$search}%");
            });
        }

        $fileName = 'gps-trip-records-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Bus No.',
                'Grouping',
                'Beginning',
                'Initial Location',
                'End',
                'Final Location',
                'Engine Hours',
                'Total Time (Minutes)',
                'In Motion (Minutes)',
                'Idling (Minutes)',
                'Mileage (KM)',
                'Severity',
            ]);

            $query
                ->orderByDesc('beginning_at')
                ->chunk(200, function ($records) use ($handle) {
                    foreach ($records as $record) {
                        fputcsv($handle, [
                            $record->bus_no,
                            $record->grouping,
                            $record->beginning_at?->format('Y-m-d h:i A'),
                            $record->initial_location,
                            $record->ending_at?->format('Y-m-d h:i A'),
                            $record->final_location,
                            $record->engine_hours,
                            $record->total_minutes,
                            $record->in_motion_minutes,
                            $record->idling_minutes,
                            $record->mileage_km,
                            $record->severity,
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function processCsvFile(BatchUpload $batch): array
    {
        $absolutePath = Storage::disk('public')->path($batch->file_path);

        $handle = fopen($absolutePath, 'r');

        if (! $handle) {
            throw new \RuntimeException('Unable to read the uploaded GPS report.');
        }

        $headers = fgetcsv($handle);

        if (! $headers) {
            fclose($handle);

            throw new \RuntimeException('The GPS report is empty or has no header row.');
        }

        $headers = array_map(
            fn ($header) => $this->normalizeHeader($header),
            $headers
        );

        $busColumnExists = collect($headers)->contains(
            fn ($header) => in_array($header, [
                'bus no',
                'bus number',
                'bus',
                'vehicle id',
                'vehicle',
                'vehicle number',
            ])
        );

        if (! $busColumnExists) {
            fclose($handle);

            throw new \RuntimeException(
                'The uploaded file must contain a Bus No. or Vehicle ID column for batch processing.'
            );
        }

        $total = 0;
        $processed = 0;
        $failed = 0;

        DB::transaction(function () use (
            $handle,
            $headers,
            $batch,
            &$total,
            &$processed,
            &$failed
        ) {
            while (($row = fgetcsv($handle)) !== false) {
                $hasValues = count(
                    array_filter($row, fn ($value) => trim((string) $value) !== '')
                ) > 0;

                if (! $hasValues) {
                    continue;
                }

                $total++;

                try {
                    $row = array_pad($row, count($headers), null);

                    $data = array_combine(
                        $headers,
                        array_slice($row, 0, count($headers))
                    );

                    $busNo = $this->valueFromRow($data, [
                        'bus no',
                        'bus number',
                        'bus',
                        'vehicle id',
                        'vehicle',
                        'vehicle number',
                    ]);

                    if (! $busNo) {
                        throw new \RuntimeException('Missing Bus Number in a trip row.');
                    }

                    $beginning = $this->parseDateTime(
                        $this->valueFromRow($data, [
                            'beginning',
                            'start',
                            'start time',
                        ])
                    );

                    $ending = $this->parseDateTime(
                        $this->valueFromRow($data, [
                            'end',
                            'ending',
                            'end time',
                        ])
                    );

                    $idlingMinutes = $this->durationToMinutes(
                        $this->valueFromRow($data, [
                            'idling',
                            'idle',
                            'idle time',
                        ])
                    );

                    GpsTripRecord::create([
                        'batch_upload_id' => $batch->id,
                        'bus_no' => strtoupper(trim($busNo)),

                        'grouping' => $this->valueFromRow($data, [
                            'grouping',
                            'group',
                            'route',
                            'route name',
                        ]),

                        'beginning_at' => $beginning,

                        'initial_location' => $this->valueFromRow($data, [
                            'initial location',
                            'start location',
                            'origin',
                        ]),

                        'ending_at' => $ending,

                        'final_location' => $this->valueFromRow($data, [
                            'final location',
                            'end location',
                            'destination',
                        ]),

                        'engine_hours' => $this->numericValue(
                            $this->valueFromRow($data, ['engine hours'])
                        ),

                        'total_minutes' => $this->durationToMinutes(
                            $this->valueFromRow($data, [
                                'total time',
                                'duration',
                            ])
                        ),

                        'in_motion_minutes' => $this->durationToMinutes(
                            $this->valueFromRow($data, [
                                'in motion',
                                'moving time',
                            ])
                        ),

                        'idling_minutes' => $idlingMinutes,

                        'mileage_km' => $this->numericValue(
                            $this->valueFromRow($data, [
                                'mileage',
                                'distance',
                            ])
                        ),

                        'severity' => $this->severityFromIdleMinutes($idlingMinutes),

                        'raw_data' => $data,
                    ]);

                    $processed++;
                } catch (\Throwable) {
                    $failed++;
                }
            }
        });

        fclose($handle);

        return compact('total', 'processed', 'failed');
    }

    private function normalizeHeader(?string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);

        $header = strtolower(trim($header));

        $header = str_replace(['_', '-', '.'], ' ', $header);

        return trim(preg_replace('/\s+/', ' ', $header));
    }

    private function valueFromRow(array $data, array $possibleHeaders): ?string
    {
        foreach ($possibleHeaders as $header) {
            $header = $this->normalizeHeader($header);

            if (array_key_exists($header, $data)) {
                return trim((string) $data[$header]);
            }
        }

        return null;
    }

    private function numericValue(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $cleanValue = preg_replace(
            '/[^0-9.\-]/',
            '',
            str_replace(',', '', $value)
        );

        return is_numeric($cleanValue)
            ? (float) $cleanValue
            : null;
    }

    private function durationToMinutes(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = strtolower(trim($value));

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $matches)) {
            $hours = (int) $matches[1];
            $minutes = (int) $matches[2];
            $seconds = isset($matches[3]) ? (int) $matches[3] : 0;

            return ($hours * 60)
                + $minutes
                + (int) round($seconds / 60);
        }

        preg_match('/(\d+)\s*(h|hour|hours)/', $value, $hourMatches);
        preg_match('/(\d+)\s*(m|min|mins|minute|minutes)/', $value, $minuteMatches);

        $hours = isset($hourMatches[1]) ? (int) $hourMatches[1] : 0;
        $minutes = isset($minuteMatches[1]) ? (int) $minuteMatches[1] : 0;

        if ($hours > 0 || $minutes > 0) {
            return ($hours * 60) + $minutes;
        }

        $numeric = $this->numericValue($value);

        return $numeric !== null ? (int) round($numeric) : null;
    }

    private function parseDateTime(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function severityFromIdleMinutes(?int $idleMinutes): string
    {
        if ($idleMinutes === null) {
            return 'Normal';
        }

        return match (true) {
            $idleMinutes >= 30 => 'High',
            $idleMinutes >= 20 => 'Medium',
            $idleMinutes >= 10 => 'Low',
            default => 'Normal',
        };
    }
}