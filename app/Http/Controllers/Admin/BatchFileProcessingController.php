<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\BatchUpload;
use App\Models\Admin\GpsTripRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

        $selectedBatch = $selectedBatchId
            ? BatchUpload::with(['tripRecords' => function ($query) {
                $query->orderBy('beginning_at');
            }])->find($selectedBatchId)
            : null;

        $recordsQuery = GpsTripRecord::query()
            ->with('batchUpload')
            ->whereHas('batchUpload', function ($query) {
                $query->where('status', 'Processed');
            });

        if ($selectedBatchId) {
            $recordsQuery->where('batch_upload_id', $selectedBatchId);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);

            $recordsQuery->where(function ($query) use ($search) {
                $query->where('record_no', 'like', "%{$search}%")
                    ->orWhere('bus_no', 'like', "%{$search}%")
                    ->orWhere('grouping', 'like', "%{$search}%")
                    ->orWhere('trip_type', 'like', "%{$search}%")
                    ->orWhere('initial_location', 'like', "%{$search}%")
                    ->orWhere('final_location', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('coordinates', 'like', "%{$search}%");
            });
        }

        $records = $recordsQuery
            ->latest('beginning_at')
            ->paginate(10)
            ->withQueryString()
            ->appends([
                'batch_id' => $selectedBatchId,
                'search' => $request->query('search'),
                'selected_record' => $request->query('selected_record'),
            ]);

        $selectedRecordId = $request->integer('selected_record');
        $selectedRecord = $selectedRecordId
            ? GpsTripRecord::with('batchUpload')->find($selectedRecordId)
            : ($selectedBatch?->tripRecords()->latest('beginning_at')->first());

        $allSelectedRecords = $selectedBatch
            ? $selectedBatch->tripRecords()->orderBy('beginning_at')->get()
            : collect();

        $filesUploaded = BatchUpload::count();
        $processedBatches = BatchUpload::query()->where('status', 'Processed')->count();
        $inReviewBatches = BatchUpload::query()->where('status', 'In Review')->count();
        $recordsExtracted = GpsTripRecord::count();

        return view('Admin.batch-file-processing', compact(
            'batches',
            'records',
            'selectedRecord',
            'selectedBatchId',
            'selectedBatch',
            'allSelectedRecords',
            'filesUploaded',
            'processedBatches',
            'inReviewBatches',
            'recordsExtracted'
        ));
    }

    public function upload(Request $request)
    {
        $validated = $request->validate([
            'gps_file' => ['required', 'file', 'mimes:csv,txt,pdf', 'max:51200'],
        ]);

        $file = $validated['gps_file'];
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = now()->format('YmdHis') . '_' . Str::random(10) . '.' . $extension;
        $filePath = $file->storeAs('gps-batches', $storedName, 'public');

        $batch = BatchUpload::create([
            'file_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'file_path' => $filePath,
            'file_type' => $extension,
            'bus_no' => 'Multiple Buses',
            'uploaded_by' => auth()->id(),
            'status' => 'Processing',
            'total_records' => 0,
            'processed_records' => 0,
            'failed_records' => 0,
        ]);

        try {
            $result = $extension === 'pdf'
                ? $this->processPdfFile($batch)
                : $this->processCsvFile($batch);

            $batch->update([
                'status' => 'In Review',
                'total_records' => $result['total'],
                'processed_records' => $result['processed'],
                'failed_records' => $result['failed'],
                'error_message' => $result['failed'] > 0
                    ? ($result['first_error'] ?? 'Some rows could not be processed. Please review the report data.')
                    : null,
            ]);

            return redirect()
                ->route('batch-file-processing', ['batch_id' => $batch->id])
                ->with('success', "{$result['processed']} GPS trip record(s) uploaded and waiting for review.");
        } catch (\Throwable $exception) {
            $batch->update([
                'status' => 'Failed',
                'error_message' => $exception->getMessage(),
            ]);

            return redirect()->route('batch-file-processing')->with('error', $exception->getMessage());
        }
    }

    public function confirm(BatchUpload $batchUpload)
    {
        if ($batchUpload->status === 'Failed') {
            return redirect()->route('batch-file-processing')->with('error', 'A failed upload cannot be marked as processed.');
        }

        $batchUpload->update(['status' => 'Processed']);

        return redirect()->route('batch-file-processing', ['batch_id' => $batchUpload->id])->with('success', 'Batch data was reviewed and marked as Processed.');
    }

    public function destroy(BatchUpload $batchUpload)
    {
        Storage::disk('public')->delete($batchUpload->file_path);
        $batchUpload->delete();

        return redirect()->route('batch-file-processing')->with('success', 'Uploaded file and all related trip records were deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $query = GpsTripRecord::query()
            ->whereHas('batchUpload', function ($query) {
                $query->where('status', 'Processed');
            });

        if ($request->filled('batch_id')) {
            $query->where('batch_upload_id', $request->batch_id);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($builder) use ($search) {
                $builder->where('record_no', 'like', "%{$search}%")
                    ->orWhere('bus_no', 'like', "%{$search}%")
                    ->orWhere('grouping', 'like', "%{$search}%")
                    ->orWhere('trip_type', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('coordinates', 'like', "%{$search}%");
            });
        }

        $fileName = 'gps-trip-records-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Record No.',
                'Bus No.',
                'Grouping',
                'Type',
                'Beginning',
                'End',
                'Duration',
                'Mileage',
                'Source Format',
                'Severity',
            ]);

            $query->orderByDesc('beginning_at')->chunk(200, function ($records) use ($handle) {
                foreach ($records as $record) {
                    fputcsv($handle, [
                        $record->record_no,
                        $record->bus_no,
                        $record->grouping,
                        $record->trip_type,
                        $record->beginning_at?->format('Y-m-d h:i A'),
                        $record->ending_at?->format('Y-m-d h:i A'),
                        $record->duration_minutes,
                        $record->mileage_km,
                        $record->source_format,
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

        $headers = array_map(fn ($header) => $this->normalizeHeader($header), $headers);

        $total = 0;
        $processed = 0;
        $failed = 0;
        $firstFailureMessage = null;
        $seenSignatures = [];

        DB::transaction(function () use ($handle, $headers, $batch, &$total, &$processed, &$failed, &$firstFailureMessage, &$seenSignatures) {
            while (($row = fgetcsv($handle)) !== false) {
                $hasValues = count(array_filter($row, fn ($value) => trim((string) $value) !== '')) > 0;

                if (! $hasValues) {
                    continue;
                }

                $total++;

                try {
                    $row = array_pad($row, count($headers), null);
                    $data = array_combine($headers, array_slice($row, 0, count($headers)));
                    $normalizedData = [];

                    foreach ($data as $key => $value) {
                        $normalizedData[$key] = $this->cleanValue($value);
                    }

                    $payload = $this->mapUnifiedRecord($normalizedData, 'CSV');
                    $signature = $this->fingerprintRecord($payload);

                    if (in_array($signature, $seenSignatures, true)) {
                        throw new \RuntimeException('Duplicate row skipped during batch processing.');
                    }

                    $seenSignatures[] = $signature;
                    $this->saveRecord($batch, $payload, $normalizedData);
                    $processed++;
                } catch (\Throwable $exception) {
                    $failed++;
                    if ($firstFailureMessage === null) {
                        $firstFailureMessage = $exception->getMessage();
                    }
                }
            }
        });

        fclose($handle);

        return [
            'total' => $total,
            'processed' => $processed,
            'failed' => $failed,
            'first_error' => $firstFailureMessage,
        ];
    }

    private function processPdfFile(BatchUpload $batch): array
    {
        $absolutePath = Storage::disk('public')->path($batch->file_path);
        $apiUrl = rtrim(config('services.nlp.api_url', env('NLP_API_URL', 'http://127.0.0.1:8000')), '/');

        if (! file_exists($absolutePath)) {
            throw new \RuntimeException('Unable to read the uploaded PDF report.');
        }

        try {
            $response = Http::timeout(120)
                ->attach(
                    'pdf_file',
                    file_get_contents($absolutePath),
                    $batch->file_name,
                    ['Content-Type' => 'application/pdf']
                )
                ->post($apiUrl . '/nlp/extract-pdf');
        } catch (\Exception $exception) {
            $errorMessage = $exception->getMessage();
            if (str_contains($errorMessage, 'timeout') || str_contains($errorMessage, 'Connection')) {
                throw new \RuntimeException('Python NLP service is unavailable. Start the Python Engine and try again.');
            }
            throw new \RuntimeException('Failed to connect to Python NLP service: ' . $errorMessage);
        }

        if ($response->failed()) {
            $statusCode = $response->status();
            $responseBody = $response->body();
            
            if ($statusCode === 422) {
                $errorDetails = $response->json('detail') ?? $responseBody ?? 'Validation error from Python NLP service';
                throw new \RuntimeException('PDF processing validation error: ' . $errorDetails);
            }
            
            throw new \RuntimeException('Python NLP service returned error ' . $statusCode . ': ' . $responseBody);
        }

        $payload = $response->json();

        if (empty($payload['success']) || empty($payload['records']) || ! is_array($payload['records'])) {
            return [
                'total' => 0,
                'processed' => 0,
                'failed' => 0,
                'first_error' => null,
            ];
        }

        $total = count($payload['records']);
        $processed = 0;
        $failed = 0;
        $firstFailureMessage = null;
        $seenSignatures = [];

        DB::transaction(function () use ($batch, $payload, &$processed, &$failed, &$firstFailureMessage, &$seenSignatures) {
            foreach ($payload['records'] as $item) {
                try {
                    $recordPayload = $this->mapUnifiedRecord($this->normalizePdfItem($item), $payload['source_format'] ?? 'GPS Report');
                    $signature = $this->fingerprintRecord($recordPayload);

                    if (in_array($signature, $seenSignatures, true)) {
                        throw new \RuntimeException('Duplicate row skipped during batch processing.');
                    }

                    $seenSignatures[] = $signature;
                    $this->saveRecord($batch, $recordPayload, $item);
                    $processed++;
                } catch (\Throwable $exception) {
                    $failed++;
                    if ($firstFailureMessage === null) {
                        $firstFailureMessage = $exception->getMessage();
                    }
                }
            }
        });

        return [
            'total' => $total,
            'processed' => $processed,
            'failed' => $failed,
            'first_error' => $firstFailureMessage,
        ];
    }

    private function saveRecord(BatchUpload $batch, array $payload, array $rawData): void
    {
        GpsTripRecord::create([
            'batch_upload_id' => $batch->id,
            'record_no' => $payload['record_no'] ?? null,
            'bus_no' => $payload['bus_no'] ?? null,
            'grouping' => $payload['grouping'] ?? null,
            'trip_type' => $payload['trip_type'] ?? null,
            'beginning_at' => $payload['beginning_at'] ?? null,
            'initial_location' => $payload['initial_location'] ?? null,
            'ending_at' => $payload['ending_at'] ?? null,
            'final_location' => $payload['final_location'] ?? null,
            'duration_minutes' => $payload['duration_minutes'] ?? null,
            'total_minutes' => $payload['total_minutes'] ?? null,
            'in_motion_minutes' => $payload['in_motion_minutes'] ?? null,
            'idling_minutes' => $payload['idling_minutes'] ?? null,
            'mileage_km' => $payload['mileage_km'] ?? null,
            'engine_hours' => $payload['engine_hours'] ?? null,
            'location' => $payload['location'] ?? null,
            'coordinates' => $payload['coordinates'] ?? null,
            'description' => $payload['description'] ?? null,
            'source_format' => $payload['source_format'] ?? 'GPS Report',
            'severity' => $payload['severity'] ?? 'Normal',
            'raw_data' => array_merge($rawData, ['source_format' => $payload['source_format'] ?? 'GPS Report']),
        ]);
    }

    private function mapUnifiedRecord(array $data, string $sourceFormat): array
    {
        $beginning = $this->parseDateTime($this->valueFromRow($data, ['beginning', 'beginning at', 'start time', 'start', 'departure']));
        $ending = $this->parseDateTime($this->valueFromRow($data, ['end', 'ending', 'end time', 'arrival']));

        $durationMinutes = $this->durationToMinutes($this->valueFromRow($data, ['duration', 'duration minutes', 'total time', 'total duration', 'trip duration']));
        $totalMinutes = $this->durationToMinutes($this->valueFromRow($data, ['total minutes', 'total time', 'total duration']));
        $inMotionMinutes = $this->durationToMinutes($this->valueFromRow($data, ['in motion', 'moving time', 'in motion minutes']));
        $idlingMinutes = $this->durationToMinutes($this->valueFromRow($data, ['idling', 'idle', 'idle time', 'idle duration']));

        $busNo = $this->valueFromRow($data, ['bus no', 'bus number', 'bus', 'vehicle id', 'vehicle', 'vehicle number']);
        $recordNo = $this->valueFromRow($data, ['record no', 'record number', 'no', 'record']);

        return [
            'record_no' => $this->cleanValue($recordNo),
            'bus_no' => $this->cleanValue($busNo),
            'grouping' => $this->cleanValue($this->valueFromRow($data, ['grouping', 'group', 'route', 'route name'])),
            'trip_type' => $this->cleanValue($this->valueFromRow($data, ['type', 'trip type', 'trip'])),
            'beginning_at' => $beginning,
            'initial_location' => $this->cleanValue($this->valueFromRow($data, ['initial location', 'start location', 'origin'])),
            'ending_at' => $ending,
            'final_location' => $this->cleanValue($this->valueFromRow($data, ['final location', 'end location', 'destination'])),
            'duration_minutes' => $durationMinutes,
            'total_minutes' => $totalMinutes ?? $durationMinutes,
            'in_motion_minutes' => $inMotionMinutes,
            'idling_minutes' => $idlingMinutes,
            'mileage_km' => $this->numericValue($this->valueFromRow($data, ['mileage', 'distance', 'mileage km'])),
            'engine_hours' => $this->numericValue($this->valueFromRow($data, ['engine hours', 'engine hour'])),
            'location' => $this->cleanValue($this->valueFromRow($data, ['location', 'location name', 'site'])),
            'coordinates' => $this->cleanValue($this->valueFromRow($data, ['coordinates', 'coordinate', 'lat lng', 'gps coordinates'])),
            'description' => $this->cleanValue($this->valueFromRow($data, ['description', 'remarks', 'comment', 'notes'])),
            'source_format' => $sourceFormat,
            'severity' => $this->severityFromIdleMinutes($idlingMinutes),
        ];
    }

    private function normalizePdfItem(array $item): array
    {
        $normalized = [];

        foreach ($item as $key => $value) {
            $normalized[$this->normalizeHeader((string) $key)] = $this->cleanValue(is_array($value) ? json_encode($value) : (string) $value);
        }

        return $normalized;
    }

    private function normalizeHeader(?string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);
        $header = mb_strtolower(trim($header), 'UTF-8');
        $header = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $header);

        return trim(preg_replace('/\s+/', ' ', $header));
    }

    private function cleanValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function valueFromRow(array $data, array $possibleHeaders): ?string
    {
        foreach ($possibleHeaders as $header) {
            $header = $this->normalizeHeader($header);

            if (array_key_exists($header, $data)) {
                return $data[$header];
            }
        }

        return null;
    }

    private function fingerprintRecord(array $payload): string
    {
        $values = [
            $payload['record_no'] ?? null,
            $payload['bus_no'] ?? null,
            $payload['grouping'] ?? null,
            $payload['trip_type'] ?? null,
            $payload['beginning_at'] ? $payload['beginning_at']->toDateTimeString() : null,
            $payload['initial_location'] ?? null,
            $payload['ending_at'] ? $payload['ending_at']->toDateTimeString() : null,
            $payload['final_location'] ?? null,
            $payload['duration_minutes'] ?? null,
            $payload['location'] ?? null,
            $payload['coordinates'] ?? null,
            $payload['description'] ?? null,
        ];

        return md5(implode('|', array_map(fn ($value) => (string) ($value ?? ''), $values)));
    }

    private function numericValue(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $cleanValue = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $value));

        return is_numeric($cleanValue) ? (float) $cleanValue : null;
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

            return ($hours * 60) + $minutes + (int) round($seconds / 60);
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