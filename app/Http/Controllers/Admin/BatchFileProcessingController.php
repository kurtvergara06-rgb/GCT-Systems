<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\BatchUpload;
use App\Models\Admin\GpsTripRecord;
use App\Traits\SystemDataUpdateBroadcaster;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BatchFileProcessingController extends Controller
{
    use SystemDataUpdateBroadcaster;

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

        /*
        |--------------------------------------------------------------------------
        | Main Structured Trip Records Table
        |--------------------------------------------------------------------------
        | Only Processed records are shown here.
        | In Review records remain inside View Cleaned Records for editing.
        |--------------------------------------------------------------------------
        */
        $recordsQuery = GpsTripRecord::query()
            ->with('batchUpload')
            ->whereHas('batchUpload', function ($query) {
                $query->where('status', 'Processed');
            });

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

        if (
            $selectedRecord &&
            $selectedBatchId &&
            $selectedRecord->batch_upload_id !== $selectedBatchId
        ) {
            $selectedRecord = $selectedBatch?->tripRecords()
                ->latest('beginning_at')
                ->first();
        }

        $allSelectedRecords = $selectedBatch
            ? $selectedBatch->tripRecords()->orderBy('beginning_at')->get()
            : collect();

        $filesUploaded = BatchUpload::count();

        $processedBatches = BatchUpload::query()
            ->where('status', 'Processed')
            ->count();

        $inReviewBatches = BatchUpload::query()
            ->where('status', 'In Review')
            ->count();

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
            'gps_file' => [
                'required',
                'file',
                'mimes:csv,txt,pdf,xls,xlsx',
                'max:51200',
            ],
        ]);

        $file = $validated['gps_file'];
        $extension = strtolower($file->getClientOriginalExtension());

        $storedName = now()->format('YmdHis')
            . '_'
            . Str::random(10)
            . '.'
            . $extension;

        $filePath = $file->storeAs(
            'gps-batches',
            $storedName,
            'public'
        );

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
            if ($extension === 'pdf') {
                $result = $this->processPdfFile($batch);
            } elseif (in_array($extension, ['xls', 'xlsx'], true)) {
                $result = $this->processExcelFile($batch);
            } else {
                $result = $this->processCsvFile($batch);
            }

            $batch->update([
                'status' => 'In Review',
                'total_records' => $result['total'],
                'processed_records' => $result['processed'],
                'failed_records' => $result['failed'],
                'error_message' => $result['failed'] > 0
                    ? (
                        $result['first_error']
                        ?? 'Some rows could not be processed. Please review the report data.'
                    )
                    : null,
            ]);

            $this->broadcastSystemDataUpdated(
                'Admin',
                'BatchUpload',
                'created',
                $batch->id,
                'A GPS batch file was uploaded and processed.'
            );

            $message = "{$result['processed']} valid record(s) saved";

            if (! empty($result['skipped_headers'])) {
                $message .= ", {$result['skipped_headers']} header row(s) skipped";
            }

            if (! empty($result['skipped_no_bus_no'])) {
                $message .= ", {$result['skipped_no_bus_no']} row(s) skipped (no Bus No.)";
            }

            if ($result['failed'] > 0) {
                $message .= ", {$result['failed']} failed";
            }

            return redirect()
                ->route('batch-file-processing', ['batch_id' => $batch->id])
                ->with('success', $message . '.');
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

    public function updateRecord(
        Request $request,
        GpsTripRecord $gpsTripRecord
    ) {
        $gpsTripRecord->load('batchUpload');

        if ($gpsTripRecord->batchUpload?->status !== 'In Review') {
            return back()->with(
                'error',
                'Only records with In Review status can be edited.'
            );
        }

        $validated = $request->validate($this->recordValidationRules());

        $validated = $this->prepareRecordDates($validated);

        $gpsTripRecord->update($validated);

        $this->broadcastSystemDataUpdated(
            'Admin',
            'GpsTripRecord',
            'updated',
            $gpsTripRecord->id,
            'A GPS trip record was updated during review.'
        );

        return redirect()
            ->route('batch-file-processing', [
                'batch_id' => $gpsTripRecord->batch_upload_id,
                'selected_record' => $gpsTripRecord->id,
            ])
            ->with('success', 'GPS trip record updated successfully.');
    }

    public function bulkUpdateRecords(
        Request $request,
        BatchUpload $batchUpload
    ) {
        if ($batchUpload->status !== 'In Review') {
            return back()->with(
                'error',
                'Only records with In Review status can be edited.'
            );
        }

        $validated = $request->validate([
            'records' => ['required', 'array', 'min:1'],

            'records.*.id' => ['required', 'integer'],
            'records.*.bus_no' => ['required', 'string', 'max:100'],
            'records.*.record_no' => ['nullable', 'string', 'max:100'],
            'records.*.grouping' => ['nullable', 'string', 'max:255'],
            'records.*.trip_type' => ['nullable', 'string', 'max:100'],
            'records.*.beginning_at' => ['nullable', 'date'],
            'records.*.initial_location' => ['nullable', 'string', 'max:255'],
            'records.*.ending_at' => ['nullable', 'date'],
            'records.*.final_location' => ['nullable', 'string', 'max:255'],
            'records.*.duration_minutes' => ['nullable', 'numeric', 'min:0'],
            'records.*.total_minutes' => ['nullable', 'numeric', 'min:0'],
            'records.*.in_motion_minutes' => ['nullable', 'numeric', 'min:0'],
            'records.*.idling_minutes' => ['nullable', 'numeric', 'min:0'],
            'records.*.mileage_km' => ['nullable', 'numeric', 'min:0'],
            'records.*.engine_hours' => ['nullable', 'numeric', 'min:0'],
            'records.*.location' => ['nullable', 'string', 'max:255'],
            'records.*.coordinates' => ['nullable', 'string', 'max:255'],
            'records.*.description' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($validated, $batchUpload) {
            foreach ($validated['records'] as $recordData) {
                $recordId = $recordData['id'];

                $record = $batchUpload->tripRecords()
                    ->whereKey($recordId)
                    ->firstOrFail();

                unset($recordData['id']);

                $recordData = $this->prepareRecordDates($recordData);

                $record->update($recordData);
            }
        });

        $this->broadcastSystemDataUpdated(
            'Admin',
            'GpsTripRecord',
            'updated',
            $batchUpload->id,
            'GPS trip records were bulk updated during review.'
        );

        return redirect()
            ->route('batch-file-processing', [
                'batch_id' => $batchUpload->id,
            ])
            ->with(
                'success',
                'All edited GPS trip records were saved successfully.'
            );
    }

    public function confirm(BatchUpload $batchUpload)
    {
        if ($batchUpload->status === 'Failed') {
            return redirect()
                ->route('batch-file-processing')
                ->with(
                    'error',
                    'A failed upload cannot be marked as processed.'
                );
        }

        if ($batchUpload->status !== 'In Review') {
            return redirect()
                ->route('batch-file-processing', [
                    'batch_id' => $batchUpload->id,
                ])
                ->with(
                    'error',
                    'Only an In Review batch can be marked as Processed.'
                );
        }

        if ($batchUpload->tripRecords()->count() === 0) {
            return redirect()
                ->route('batch-file-processing', [
                    'batch_id' => $batchUpload->id,
                ])
                ->with(
                    'error',
                    'This batch has no valid trip records to process.'
                );
        }

        $batchUpload->update([
            'status' => 'Processed',
        ]);

        $this->broadcastSystemDataUpdated(
            'Admin',
            'BatchUpload',
            'updated',
            $batchUpload->id,
            'A GPS batch upload was marked as Processed.'
        );

        return redirect()
            ->route('batch-file-processing', [
                'batch_id' => $batchUpload->id,
            ])
            ->with(
                'success',
                'Batch data was reviewed and marked as Processed.'
            );
    }

    public function destroy(BatchUpload $batchUpload)
    {
        Storage::disk('public')->delete($batchUpload->file_path);

        $batchId = $batchUpload->id;

        $batchUpload->delete();

        $this->broadcastSystemDataUpdated(
            'Admin',
            'BatchUpload',
            'deleted',
            $batchId,
            'A GPS batch upload was deleted.'
        );

        return redirect()
            ->route('batch-file-processing')
            ->with(
                'success',
                'Uploaded file and all related trip records were deleted.'
            );
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

        $fileName = 'gps-trip-records-'
            . now()->format('Y-m-d-His')
            . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Record No.',
                'Bus No.',
                'Grouping',
                'Type',
                'Beginning',
                'Initial Location',
                'End',
                'Final Location',
                'Duration',
                'Total Time',
                'In Motion',
                'Idling',
                'Mileage',
                'Engine Hours',
                'Recorded Location',
                'Coordinates',
                'Remarks',
                'Source Format',
                'Severity',
            ]);

            $query
                ->orderByDesc('beginning_at')
                ->chunk(200, function ($records) use ($handle) {
                    foreach ($records as $record) {
                        fputcsv($handle, [
                            $record->record_no,
                            $record->bus_no,
                            $record->grouping,
                            $record->trip_type,
                            $record->beginning_at?->format('Y-m-d h:i A'),
                            $record->initial_location,
                            $record->ending_at?->format('Y-m-d h:i A'),
                            $record->final_location,
                            $record->duration_minutes,
                            $record->total_minutes,
                            $record->in_motion_minutes,
                            $record->idling_minutes,
                            $record->mileage_km,
                            $record->engine_hours,
                            $record->location,
                            $record->coordinates,
                            $record->description,
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
            throw new \RuntimeException(
                'Unable to read the uploaded GPS report.'
            );
        }

        $headers = fgetcsv($handle);

        if (! $headers) {
            fclose($handle);

            throw new \RuntimeException(
                'The GPS report is empty or has no header row.'
            );
        }

        $headers = array_map(
            fn ($header) => $this->normalizeHeader($header),
            $headers
        );

        $total = 0;
        $processed = 0;
        $failed = 0;
        $firstFailureMessage = null;
        $seenSignatures = [];

        DB::transaction(function () use (
            $handle,
            $headers,
            $batch,
            &$total,
            &$processed,
            &$failed,
            &$firstFailureMessage,
            &$seenSignatures
        ) {
            while (($row = fgetcsv($handle)) !== false) {
                $hasValues = count(array_filter(
                    $row,
                    fn ($value) => trim((string) $value) !== ''
                )) > 0;

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

                    $normalizedData = [];

                    foreach ($data as $key => $value) {
                        $normalizedData[$key] = $this->cleanValue($value);
                    }

                    $payload = $this->mapUnifiedRecord(
                        $normalizedData,
                        'CSV'
                    );

                    $signature = $this->fingerprintRecord($payload);

                    if (in_array($signature, $seenSignatures, true)) {
                        throw new \RuntimeException(
                            'Duplicate row skipped during batch processing.'
                        );
                    }

                    $seenSignatures[] = $signature;

                    $this->saveRecord(
                        $batch,
                        $payload,
                        $normalizedData
                    );

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

    private function processExcelFile(BatchUpload $batch): array
    {
        $absolutePath = Storage::disk('public')->path($batch->file_path);

        if (! file_exists($absolutePath)) {
            throw new \RuntimeException(
                'Unable to read the uploaded Excel file.'
            );
        }

        $spreadsheet = IOFactory::load($absolutePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $rows = $worksheet->toArray(
            null,
            true,
            true,
            false
        );

        if (count($rows) < 2) {
            throw new \RuntimeException(
                'The Excel file is empty or has no data rows.'
            );
        }

        $headers = array_map(
            fn ($header) => $this->normalizeHeader((string) $header),
            $rows[0]
        );

        $total = 0;
        $processed = 0;
        $failed = 0;
        $firstFailureMessage = null;
        $seenSignatures = [];

        DB::transaction(function () use (
            $rows,
            $headers,
            $batch,
            &$total,
            &$processed,
            &$failed,
            &$firstFailureMessage,
            &$seenSignatures
        ) {
            foreach (array_slice($rows, 1) as $row) {
                $hasValues = count(array_filter(
                    $row,
                    fn ($value) => trim((string) $value) !== ''
                )) > 0;

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

                    $normalizedData = [];

                    foreach ($data as $key => $value) {
                        if (
                            is_numeric($value) &&
                            $this->looksLikeExcelDateColumn($key)
                        ) {
                            try {
                                $value = ExcelDate::excelToDateTimeObject(
                                    $value
                                )->format('Y-m-d H:i:s');
                            } catch (\Throwable) {
                                // Keep original value.
                            }
                        }

                        $normalizedData[$key] = $this->cleanValue($value);
                    }

                    $payload = $this->mapUnifiedRecord(
                        $normalizedData,
                        'Excel'
                    );

                    $signature = $this->fingerprintRecord($payload);

                    if (in_array($signature, $seenSignatures, true)) {
                        throw new \RuntimeException(
                            'Duplicate row skipped during Excel processing.'
                        );
                    }

                    $seenSignatures[] = $signature;

                    $this->saveRecord(
                        $batch,
                        $payload,
                        $normalizedData
                    );

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

    private function looksLikeExcelDateColumn(string $header): bool
    {
        $header = $this->normalizeHeader($header);

        return in_array($header, [
            'beginning',
            'beginning at',
            'start',
            'start time',
            'start date',
            'departure',
            'end',
            'ending',
            'end time',
            'end date',
            'arrival',
        ], true);
    }

    private function processPdfFile(BatchUpload $batch): array
    {
        $absolutePath = Storage::disk('public')->path($batch->file_path);

        $apiUrl = rtrim(
            config(
                'services.nlp.api_url',
                env('NLP_API_URL', 'http://127.0.0.1:8000')
            ),
            '/'
        );

        if (! file_exists($absolutePath)) {
            throw new \RuntimeException(
                'Unable to read the uploaded PDF report.'
            );
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

            if (
                str_contains($errorMessage, 'timeout') ||
                str_contains($errorMessage, 'Connection')
            ) {
                throw new \RuntimeException(
                    'Python NLP service is unavailable. Start the Python Engine and try again.'
                );
            }

            throw new \RuntimeException(
                'Failed to connect to Python NLP service: ' . $errorMessage
            );
        }

        if ($response->failed()) {
            $statusCode = $response->status();
            $responseBody = $response->body();

            if ($statusCode === 422) {
                $errorDetails = $response->json('detail')
                    ?? $responseBody
                    ?? 'Validation error from Python NLP service';

                throw new \RuntimeException(
                    'PDF processing validation error: ' . $errorDetails
                );
            }

            throw new \RuntimeException(
                'Python NLP service returned error '
                . $statusCode
                . ': '
                . $responseBody
            );
        }

        $payload = $response->json();

        if (
            empty($payload['success']) ||
            ! isset($payload['records']) ||
            ! is_array($payload['records'])
        ) {
            return [
                'total' => 0,
                'processed' => 0,
                'failed' => 0,
                'skipped_headers' => 0,
                'skipped_no_bus_no' => 0,
                'first_error' => 'No records found in PDF.',
            ];
        }

        $records = $payload['records'];
        $total = count($records);
        $processed = 0;
        $failed = 0;
        $skippedNoBusNo = 0;
        $firstFailureMessage = null;
        $seenSignatures = [];

        DB::transaction(function () use (
            $batch,
            $records,
            &$processed,
            &$failed,
            &$skippedNoBusNo,
            &$firstFailureMessage,
            &$seenSignatures
        ) {
            foreach ($records as $item) {
                try {
                    $busNo = $item['bus_no'] ?? null;

                    if (! $busNo || trim($busNo) === '') {
                        $skippedNoBusNo++;
                        continue;
                    }

                    $beginningAt = ! empty($item['beginning'])
                        ? $this->parseDateTime($item['beginning'])
                        : null;

                    $endingAt = ! empty($item['ending'])
                        ? $this->parseDateTime($item['ending'])
                        : null;

                    $recordPayload = [
                        'record_no' => $item['record_no'] ?? null,
                        'bus_no' => $busNo,
                        'grouping' => $item['grouping'] ?? null,
                        'trip_type' => $item['trip_type'] ?? null,
                        'beginning_at' => $beginningAt,
                        'initial_location' => $item['initial_location'] ?? null,
                        'ending_at' => $endingAt,
                        'final_location' => $item['final_location'] ?? null,
                        'duration_minutes' => ! empty($item['duration_minutes'])
                            ? (float) $item['duration_minutes']
                            : null,
                        'total_minutes' => ! empty($item['total_minutes'])
                            ? (float) $item['total_minutes']
                            : null,
                        'in_motion_minutes' => ! empty($item['in_motion_minutes'])
                            ? (float) $item['in_motion_minutes']
                            : null,
                        'idling_minutes' => ! empty($item['idling_minutes'])
                            ? (float) $item['idling_minutes']
                            : null,
                        'mileage_km' => ! empty($item['mileage_km'])
                            ? (float) $item['mileage_km']
                            : null,
                        'engine_hours' => ! empty($item['engine_hours'])
                            ? (float) $item['engine_hours']
                            : null,
                        'location' => $item['location'] ?? null,
                        'coordinates' => $item['coordinates'] ?? null,
                        'description' => $item['description'] ?? null,
                        'source_format' => 'GPS Report',
                        'severity' => 'Normal',
                    ];

                    $signature = $this->fingerprintRecord($recordPayload);

                    if (in_array($signature, $seenSignatures, true)) {
                        throw new \RuntimeException(
                            'Duplicate PDF record skipped during batch processing.'
                        );
                    }

                    $seenSignatures[] = $signature;

                    $rawData = $item['raw_data'] ?? $item;

                    if (! is_array($rawData)) {
                        $rawData = ['raw_value' => $rawData];
                    }

                    $this->saveRecord(
                        $batch,
                        $recordPayload,
                        $rawData
                    );

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
            'skipped_headers' => 0,
            'skipped_no_bus_no' => $skippedNoBusNo,
            'first_error' => $firstFailureMessage,
        ];
    }

    private function saveRecord(
        BatchUpload $batch,
        array $payload,
        array $rawData
    ): void {
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
            'raw_data' => array_merge(
                $rawData,
                [
                    'source_format' => $payload['source_format']
                        ?? 'GPS Report',
                ]
            ),
        ]);
    }

    private function mapUnifiedRecord(
        array $data,
        string $sourceFormat
    ): array {
        $beginning = $this->parseDateTime(
            $this->valueFromRow(
                $data,
                [
                    'beginning',
                    'beginning at',
                    'start time',
                    'start',
                    'departure',
                    'start date',
                ]
            )
        );

        $ending = $this->parseDateTime(
            $this->valueFromRow(
                $data,
                [
                    'end',
                    'ending',
                    'end time',
                    'arrival',
                    'end date',
                ]
            )
        );

        $durationMinutes = $this->durationToMinutes(
            $this->valueFromRow(
                $data,
                [
                    'duration',
                    'duration minutes',
                    'trip duration',
                ]
            )
        );

        $totalMinutes = $this->durationToMinutes(
            $this->valueFromRow(
                $data,
                [
                    'total time',
                    'total minutes',
                    'total mins',
                    'total duration',
                ]
            )
        );

        $inMotionMinutes = $this->durationToMinutes(
            $this->valueFromRow(
                $data,
                [
                    'move time',
                    'moving time',
                    'in motion',
                    'in motion minutes',
                    'moving mins',
                    'moving minutes',
                ]
            )
        );

        $idlingMinutes = $this->durationToMinutes(
            $this->valueFromRow(
                $data,
                [
                    'idling',
                    'idle',
                    'idle time',
                    'idle duration',
                    'idle mins',
                    'idle minutes',
                ]
            )
        );

        $busNo = $this->valueFromRow(
            $data,
            [
                'bus no',
                'bus number',
                'bus',
                'vehicle id',
                'vehicle',
                'vehicle number',
                'vehicle no',
                'unit no',
                'unit number',
                'unit',
                'fleet no',
                'fleet number',
            ]
        );

        $recordNo = $this->valueFromRow(
            $data,
            [
                'record no',
                'record number',
                'no',
                'record',
            ]
        );

        return [
            'record_no' => $this->cleanValue($recordNo),
            'bus_no' => $this->cleanValue($busNo),
            'grouping' => $this->cleanValue(
                $this->valueFromRow(
                    $data,
                    [
                        'grouping',
                        'groupings',
                        'group',
                        'route',
                        'route name',
                    ]
                )
            ),
            'trip_type' => $this->cleanValue(
                $this->valueFromRow(
                    $data,
                    [
                        'type',
                        'trip type',
                        'trip',
                    ]
                )
            ),
            'beginning_at' => $beginning,
            'initial_location' => $this->cleanValue(
                $this->valueFromRow(
                    $data,
                    [
                        'initial location',
                        'origin',
                        'from',
                        'start location',
                    ]
                )
            ),
            'ending_at' => $ending,
            'final_location' => $this->cleanValue(
                $this->valueFromRow(
                    $data,
                    [
                        'final location',
                        'destination',
                        'to',
                        'end location',
                    ]
                )
            ),
            'duration_minutes' => $durationMinutes,
            'total_minutes' => $totalMinutes ?? $durationMinutes,
            'in_motion_minutes' => $inMotionMinutes,
            'idling_minutes' => $idlingMinutes,
            'mileage_km' => $this->numericValue(
                $this->valueFromRow(
                    $data,
                    [
                        'mileage',
                        'mileage km',
                        'mileage in trips',
                        'distance',
                        'km',
                    ]
                )
            ),
            'engine_hours' => $this->numericValue(
                $this->valueFromRow(
                    $data,
                    [
                        'engine hours',
                        'engine hour',
                        'hrs',
                    ]
                )
            ),
            'location' => $this->cleanValue(
                $this->valueFromRow(
                    $data,
                    [
                        'location',
                        'location name',
                        'site',
                    ]
                )
            ),
            'coordinates' => $this->cleanValue(
                $this->valueFromRow(
                    $data,
                    [
                        'coordinates',
                        'coordinate',
                        'lat lng',
                        'gps coordinates',
                        'gps',
                    ]
                )
            ),
            'description' => $this->cleanValue(
                $this->valueFromRow(
                    $data,
                    [
                        'description',
                        'remarks',
                        'comments',
                        'comment',
                        'notes',
                    ]
                )
            ),
            'source_format' => $sourceFormat,
            'severity' => $this->severityFromIdleMinutes($idlingMinutes),
        ];
    }

    private function recordValidationRules(): array
    {
        return [
            'bus_no' => ['required', 'string', 'max:100'],
            'record_no' => ['nullable', 'string', 'max:100'],
            'grouping' => ['nullable', 'string', 'max:255'],
            'trip_type' => ['nullable', 'string', 'max:100'],
            'beginning_at' => ['nullable', 'date'],
            'initial_location' => ['nullable', 'string', 'max:255'],
            'ending_at' => ['nullable', 'date'],
            'final_location' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['nullable', 'numeric', 'min:0'],
            'total_minutes' => ['nullable', 'numeric', 'min:0'],
            'in_motion_minutes' => ['nullable', 'numeric', 'min:0'],
            'idling_minutes' => ['nullable', 'numeric', 'min:0'],
            'mileage_km' => ['nullable', 'numeric', 'min:0'],
            'engine_hours' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'coordinates' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function prepareRecordDates(array $data): array
    {
        $data['beginning_at'] = ! empty($data['beginning_at'])
            ? Carbon::parse($data['beginning_at'])
            : null;

        $data['ending_at'] = ! empty($data['ending_at'])
            ? Carbon::parse($data['ending_at'])
            : null;

        return $data;
    }

    private function normalizeHeader(?string $header): string
    {
        $header = preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            (string) $header
        );

        $header = mb_strtolower(trim($header), 'UTF-8');

        $header = preg_replace(
            '/[^\p{L}\p{N}]+/u',
            ' ',
            $header
        );

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

    private function valueFromRow(
        array $data,
        array $possibleHeaders
    ): ?string {
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
        $beginning = $payload['beginning_at'] ?? null;
        $ending = $payload['ending_at'] ?? null;

        $values = [
            $payload['record_no'] ?? null,
            $payload['bus_no'] ?? null,
            $payload['grouping'] ?? null,
            $payload['trip_type'] ?? null,
            $beginning instanceof Carbon
                ? $beginning->toDateTimeString()
                : $beginning,
            $payload['initial_location'] ?? null,
            $ending instanceof Carbon
                ? $ending->toDateTimeString()
                : $ending,
            $payload['final_location'] ?? null,
            $payload['duration_minutes'] ?? null,
            $payload['location'] ?? null,
            $payload['coordinates'] ?? null,
            $payload['description'] ?? null,
        ];

        return md5(
            implode(
                '|',
                array_map(
                    fn ($value) => (string) ($value ?? ''),
                    $values
                )
            )
        );
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

        if (
            preg_match(
                '/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/',
                $value,
                $matches
            )
        ) {
            $hours = (int) $matches[1];
            $minutes = (int) $matches[2];
            $seconds = isset($matches[3])
                ? (int) $matches[3]
                : 0;

            return ($hours * 60)
                + $minutes
                + (int) round($seconds / 60);
        }

        preg_match(
            '/(\d+)\s*(h|hour|hours)/',
            $value,
            $hourMatches
        );

        preg_match(
            '/(\d+)\s*(m|min|mins|minute|minutes)/',
            $value,
            $minuteMatches
        );

        $hours = isset($hourMatches[1])
            ? (int) $hourMatches[1]
            : 0;

        $minutes = isset($minuteMatches[1])
            ? (int) $minuteMatches[1]
            : 0;

        if ($hours > 0 || $minutes > 0) {
            return ($hours * 60) + $minutes;
        }

        $numeric = $this->numericValue($value);

        return $numeric !== null
            ? (int) round($numeric)
            : null;
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