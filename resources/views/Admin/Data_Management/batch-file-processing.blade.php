<x-layout.app
    title="FROMS - Batch File Processing"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/Data_Management/batch-file-processing.css',
        'resources/css/Admin/Data_Management/generic-batch-review.css',
        'resources/js/Admin/Data_Management/batch-file-processing.js',
    ]"
>
    @php
        $authUser = auth()->user();

        $sidebarName = $authUser?->name ?? 'System Admin';

        $sidebarDepartment = trim($authUser?->department ?? 'Admin');
        $sidebarRoleValue = strtolower(trim($authUser?->role ?? 'head'));

        $sidebarRole = strtolower($sidebarDepartment) === 'admin'
            ? ($sidebarRoleValue === 'head' ? 'System Admin' : 'Admin Staff')
            : $sidebarDepartment . ' ' . ucfirst($sidebarRoleValue ?: 'staff');

        $selectedRawData = $selectedRecord?->raw_data;

        if (is_string($selectedRawData)) {
            $selectedRawData = json_decode($selectedRawData, true);
        }

        $selectedRawData = is_array($selectedRawData)
            ? $selectedRawData
            : [];

        $rawPreview = 'Select an uploaded data file to view the original extracted values.';

        if ($selectedRecord && count($selectedRawData) > 0) {
            $rawPreviewLines = [];

            foreach ($selectedRawData as $key => $value) {
                $label = ucwords(str_replace(['_', '-'], ' ', $key));

                if (is_array($value)) {
                    $value = json_encode($value);
                }

                $displayValue = ($value === null || $value === '')
                    ? '—'
                    : $value;

                $rawPreviewLines[] = str_pad($label . ':', 20) . $displayValue;
            }

            $rawPreview = implode("\n", $rawPreviewLines);
        }

        $fields = [
            'Bus No.' => $selectedRecord?->bus_no,
            'Record No.' => $selectedRecord?->record_no,
            'Grouping' => $selectedRecord?->grouping,
            'Type' => $selectedRecord?->trip_type,
            'Beginning' => $selectedRecord?->beginning_at?->format('M d, Y h:i A'),
            'Initial Location' => $selectedRecord?->initial_location,
            'End' => $selectedRecord?->ending_at?->format('M d, Y h:i A'),
            'Final Location' => $selectedRecord?->final_location,
            'Duration' => $selectedRecord?->duration_minutes !== null
                ? $selectedRecord->duration_minutes . ' mins'
                : null,
            'Total Time' => $selectedRecord?->total_minutes !== null
                ? $selectedRecord->total_minutes . ' mins'
                : null,
            'In Motion' => $selectedRecord?->in_motion_minutes !== null
                ? $selectedRecord->in_motion_minutes . ' mins'
                : null,
            'Idling' => $selectedRecord?->idling_minutes !== null
                ? $selectedRecord->idling_minutes . ' mins'
                : null,
            'Mileage' => $selectedRecord?->mileage_km !== null
                ? $selectedRecord->mileage_km . ' km'
                : null,
            'Engine Hours' => $selectedRecord?->engine_hours,
            'Recorded Location' => $selectedRecord?->location,
            'Recorded Coordinates' => $selectedRecord?->coordinates,
            'Remarks' => $selectedRecord?->description,
        ];

        $rawHeaders = [];
        $rawRows = [];

        foreach ($allSelectedRecords ?? collect() as $rawRecord) {
            $recordRawData = $rawRecord->raw_data;

            if (is_string($recordRawData)) {
                $recordRawData = json_decode($recordRawData, true);
            }

            $recordRawData = is_array($recordRawData)
                ? $recordRawData
                : [];

            $rawRows[$rawRecord->id] = $recordRawData;

            foreach (array_keys($recordRawData) as $rawHeader) {
                if (! in_array($rawHeader, $rawHeaders, true)) {
                    $rawHeaders[] = $rawHeader;
                }
            }
        }

        $tableSubtitle = $selectedBatch
            ? (
                $selectedBatch->status === 'Processed'
                    ? 'Showing processed structured records from: ' . $selectedBatch->file_name
                    : 'Review the selected upload, then mark it as Processed to publish its structured records.'
            )
            : 'Select a processed uploaded file to view its structured records.';

        $latestBatch = $batches->first();
        $latestActivity = $latestBatch?->dataActivity;
        $latestCompletedAt = $latestActivity?->completed_at;
        $latestProcessedBy = $latestActivity?->processor?->name;
        $latestTimestamp = $latestCompletedAt ?? $latestBatch?->created_at;
        $latestTimestampLabel = $latestCompletedAt ? 'Processed' : 'Uploaded';
        $latestTotal = (int) ($latestBatch?->total_records ?? 0);
        $latestSuccessful = (int) ($latestBatch?->processed_records ?? 0);
        $latestFailed = (int) ($latestBatch?->failed_records ?? 0);
        $latestSkipped = max($latestTotal - $latestSuccessful - $latestFailed, 0);
        $latestQuality = $latestTotal > 0
            ? min(100, (int) round(($latestSuccessful / $latestTotal) * 100))
            : 0;
    @endphp

    <x-layout.sidebar department="Admin" />

    <main class="main batch-main batch-processing-page">
        <x-layout.topbar
            title="Batch File Processing"
            subtitle="Convert raw, messy, or semi-structured files into reviewed structured records."
            notification-count="6"
        />

        <section class="batch-top-grid">
            <form
                action="{{ route('batch-file-processing.upload', [], false) }}"
                method="POST"
                enctype="multipart/form-data"
                class="upload-card"
                id="gpsUploadForm"
                data-confirm-form
                data-confirm-title="Upload Data File?"
                data-confirm-message="Are you sure you want to upload this file for extraction and review?"
                data-confirm-button="Yes, Upload File"
                data-confirm-type="create"
            >
                @csrf

                <div class="upload-card-header">
                    <div>
                        <h2>
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            Upload Data Files
                        </h2>

                        <p>Select a processor profile first. Supported file formats depend on the selected data type.</p>
                    </div>
                </div>

                <div class="batch-profile-grid">
                    <label class="batch-profile-field">
                        <span>Target Module</span>
                        <select name="module" required>
                            <option value="Operation">Operation — GPS Trip Records</option>
                            <option value="Maintenance">Maintenance — Fuel Reports</option>
                            <option value="Warehouse">Warehouse — Inventory Records</option>
                            <option value="Purchase">Purchase — Purchase Orders</option>
                        </select>
                    </label>

                    <label class="batch-profile-field">
                        <span>Data Type</span>
                        <select name="data_type" required>
                            <option value="GPS Trip Records">GPS Trip Records</option>
                        </select>
                    </label>
                </div>

                <input
                    type="file"
                    id="gpsFileInput"
                    name="gps_file"
                    accept=".csv,.txt,.pdf,.xls,.xlsx"
                    hidden
                    required
                >

                <div class="compact-dropzone" id="gpsDropzone">
                    <div class="dropzone-icon">
                        <i class="fa-solid fa-file-arrow-up"></i>
                    </div>

                    <div class="dropzone-content">
                        <strong id="selectedFileName">
                            Drag and drop data files here
                        </strong>

                        <span>or choose a file from your device</span>
                    </div>

                    <button
                        type="button"
                        class="browse-btn"
                        id="chooseGpsFileBtn"
                    >
                        <i class="fa-solid fa-folder-open"></i>
                        Choose File
                    </button>
                </div>

                <div class="upload-details">
                    <span>
                        <i class="fa-solid fa-gears"></i>
                        Operation · GPS Trip Records · PDF, CSV, TXT, XLS, XLSX
                    </span>

                    <span>
                        <i class="fa-solid fa-file-pdf"></i>
                        PDF is available only for GPS Trip Records
                    </span>

                    <span>
                        <i class="fa-solid fa-hard-drive"></i>
                        Maximum file size: 50 MB
                    </span>
                </div>

                <div class="upload-action-wrap">
                    <button
                        type="submit"
                        class="upload-data-btn"
                        id="uploadGpsFileBtn"
                        disabled
                    >
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        Upload File
                    </button>
                </div>
            </form>

            <div class="batch-summary-card">
                <div class="batch-summary-header">
                    <div>
                        <h2>
                            <i class="fa-solid fa-layer-group"></i>
                            Current Batch Summary
                        </h2>

                        <p>Overview of uploaded files and extracted records.</p>
                    </div>

                    <a href="{{ route('admin.data-history') }}" class="secondary-btn">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        Processing History
                    </a>
                </div>

                <div class="batch-summary-stats">
                    <div class="batch-stat">
                        <div class="batch-stat-icon blue">
                            <i class="fa-solid fa-file-arrow-up"></i>
                        </div>

                        <div>
                            <span>Files Uploaded</span>
                            <strong>{{ $filesUploaded }}</strong>
                        </div>
                    </div>

                    <div class="batch-stat">
                        <div class="batch-stat-icon green">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>

                        <div>
                            <span>Processed</span>
                            <strong>{{ $processedBatches }}</strong>
                        </div>
                    </div>

                    <div class="batch-stat">
                        <div class="batch-stat-icon yellow">
                            <i class="fa-solid fa-clock"></i>
                        </div>

                        <div>
                            <span>In Review</span>
                            <strong>{{ $inReviewBatches }}</strong>
                        </div>
                    </div>

                    <div class="batch-stat">
                        <div class="batch-stat-icon navy">
                            <i class="fa-solid fa-database"></i>
                        </div>

                        <div>
                            <span>Records Extracted</span>
                            <strong>{{ number_format($recordsExtracted) }}</strong>
                        </div>
                    </div>
                </div>

                @if($latestBatch)
                    <div class="latest-batch-card">
                        <div class="latest-batch-header">
                            <div class="latest-batch-title">
                                <span>Latest Batch</span>
                                <strong>{{ $latestBatch->file_name }}</strong>
                            </div>

                            <span class="latest-batch-status {{ strtolower(str_replace(' ', '-', $latestBatch->status)) }}">
                                {{ $latestBatch->status }}
                            </span>
                        </div>

                        <div class="latest-batch-meta">
                            <span><i class="fa-solid fa-building"></i>{{ $latestBatch->module ?? 'Operation' }}</span>
                            <span><i class="fa-solid fa-table-list"></i>{{ $latestBatch->data_type ?? 'GPS Trip Records' }}</span>
                            <span><i class="fa-solid fa-file"></i>{{ strtoupper($latestBatch->file_type ?? '—') }}</span>
                            @if($latestProcessedBy)
                                <span><i class="fa-solid fa-user-check"></i>{{ $latestProcessedBy }}</span>
                            @endif
                            <span>
                                <i class="fa-regular fa-clock"></i>
                                {{ $latestTimestampLabel }}: {{ $latestTimestamp?->format('M d, Y · h:i A') ?? '—' }}
                            </span>
                        </div>

                        <div class="latest-batch-counts">
                            <div><span>Total</span><strong>{{ number_format($latestTotal) }}</strong></div>
                            <div><span>Successful</span><strong>{{ number_format($latestSuccessful) }}</strong></div>
                            <div><span>Failed</span><strong>{{ number_format($latestFailed) }}</strong></div>
                            <div><span>Skipped</span><strong>{{ number_format($latestSkipped) }}</strong></div>
                        </div>

                        <div class="latest-batch-quality">
                            <div class="latest-batch-quality-label">
                                <span>Processing Quality</span>
                                <strong>{{ $latestQuality }}%</strong>
                            </div>
                            <div class="latest-batch-quality-track">
                                <span style="width: {{ $latestQuality }}%;"></span>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="latest-batch-empty">
                        <i class="fa-solid fa-inbox"></i>
                        Upload your first batch to see processing details here.
                    </div>
                @endif
            </div>
        </section>

        @if($genericStructuredBatch)
            @include('Admin.Data_Management.partials.generic-processed-workspace')
        @else
            @include('Admin.Data_Management.partials.batch-file-processing-workspace')
        @endif

        @include('Admin.Data_Management.partials.generic-batch-review-modal')
    </main>
</x-layout.app>
