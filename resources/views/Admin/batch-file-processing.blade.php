<x-layout.app
    title="FROMS - Batch File Processing"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/batch-file-processing.css',
        'resources/js/Admin/batch-file-processing.js'
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

        $rawPreview = 'Select an uploaded GPS batch to view the original uploaded data.';

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
                    ? 'Showing processed trip records from: ' . $selectedBatch->file_name
                    : 'Select a processed uploaded file to view its structured trip records.'
            )
            : 'Select a processed uploaded file to view its structured trip records.';
    @endphp

    <div class="app">
        <x-layout.sidebar
            department="Admin"
            subtitle="System Management"
            icon="fa-shield-halved"
            :user-name="$sidebarName"
            :user-role="$sidebarRole"
            :items="[
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'fa-table-cells-large'],
                ['label' => 'User Management', 'route' => 'admin.users', 'icon' => 'fa-users-gear'],
                ['label' => 'Permissions', 'route' => 'admin.permissions', 'icon' => 'fa-lock'],
                ['label' => 'Batch File Processing', 'route' => 'batch-file-processing', 'icon' => 'fa-file-arrow-up'],
            ]"
        />

        <main class="main batch-main">
            <x-layout.topbar
                title="Batch File Processing"
                subtitle="Upload GPS files and convert them into structured trip records using NLP."
                notification-count="6"
            />

            <section class="batch-top-grid">
                <form
                    action="{{ route('batch-file-processing.upload') }}"
                    method="POST"
                    enctype="multipart/form-data"
                    class="upload-card"
                    id="gpsUploadForm"
                >
                    @csrf

                    <div class="upload-card-header">
                        <div>
                            <h2>
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                Upload GPS Files
                            </h2>

                            <p>Upload PDF, CSV, TXT, XLS, or XLSX reports for extraction and review.</p>
                        </div>
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
                                Drag and drop GPS files here
                            </strong>

                            <span>or choose files from your device</span>
                        </div>

                        <button
                            type="button"
                            class="browse-btn"
                            id="chooseGpsFileBtn"
                        >
                            <i class="fa-solid fa-folder-open"></i>
                            Choose Files
                        </button>
                    </div>

                    <div class="upload-details">
                        <span>
                            <i class="fa-solid fa-file-lines"></i>
                            PDF, CSV, TXT, XLS, and XLSX supported
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

                            <p>Overview of uploaded GPS files.</p>
                        </div>

                        <button type="button" class="secondary-btn">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            Processing History
                        </button>
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

                    @if($batches->isNotEmpty())
                        <div class="batch-last-update">
                            <i class="fa-solid fa-arrows-rotate"></i>
                            Last processed:
                            {{ $batches->first()->created_at->format('M d, Y · h:i A') }}
                        </div>
                    @endif
                </div>
            </section>

            <section class="processing-grid">
                <div class="panel-card uploaded-files-card">
                    <div class="panel-card-header">
                        <div>
                            <h3>
                                <i class="fa-solid fa-folder-open"></i>
                                Uploaded Files
                            </h3>

                            <p>Select a file to review extracted records.</p>
                        </div>
                    </div>

                    <div class="uploaded-file-list">
                        @forelse($batches as $batch)
                            <div class="uploaded-file-row">
                                <a
                                    href="{{ route('batch-file-processing', ['batch_id' => $batch->id]) }}"
                                    class="uploaded-file {{ $selectedBatchId == $batch->id ? 'active-file' : '' }}"
                                >
                                    <div class="file-icon {{ strtolower($batch->file_type ?? 'csv') }}">
                                        <i class="fa-solid fa-file"></i>
                                    </div>

                                    <div class="file-info">
                                        <strong>{{ $batch->file_name }}</strong>

                                        <span>
                                            {{ $batch->created_at->format('M d, Y h:i A') }}
                                            · {{ $batch->processed_records }} record(s)
                                        </span>
                                    </div>

                                    <span class="{{ $batch->status === 'Processed'
                                        ? 'processed-badge'
                                        : ($batch->status === 'Failed'
                                            ? 'failed-badge'
                                            : 'review-badge') }}"
                                    >
                                        {{ $batch->status }}
                                    </span>
                                </a>

                                <button
                                    type="button"
                                    class="delete-upload-btn"
                                    title="Delete uploaded file"
                                    data-delete-batch
                                    data-delete-url="{{ route('batch-file-processing.destroy', $batch) }}"
                                    data-delete-name="{{ $batch->file_name }}"
                                >
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        @empty
                            <p class="empty-batch-message">
                                No GPS reports uploaded yet.
                            </p>
                        @endforelse
                    </div>

                </div>

                <div class="panel-card extracted-preview-card">
                    <div class="panel-card-header">
                        <div>
                            <h3>
                                <i class="fa-solid fa-file-waveform"></i>
                                Extracted Text Preview
                            </h3>

                            <p>Original values from the selected uploaded record.</p>
                        </div>

                        <div class="record-navigation">
                            @if($selectedBatch)
                                <button
                                    type="button"
                                    class="edit-record-btn"
                                    data-open-records-modal
                                >
                                    <i class="fa-solid fa-pen-to-square"></i>
                                    Edit Extracted Data
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="text-preview">
                        <pre>{{ $rawPreview }}</pre>
                    </div>

                    @if($selectedRecord)
                        <div class="confidence-row">
                            <span>
                                <i class="fa-solid fa-circle-check"></i>
                                Record Status:
                                <strong>{{ $selectedBatch?->status ?? 'In Review' }}</strong>
                            </span>

                            <small>
                                Source: {{ $selectedRecord->batchUpload?->file_name }}
                            </small>
                        </div>
                    @endif
                </div>

                <div class="panel-card parsed-fields-card">
                    <div class="panel-card-header">
                        <div>
                            <h3>
                                <i class="fa-solid fa-code"></i>
                                Parsed Fields
                            </h3>

                            <p>Cleaned structured values from the selected trip record.</p>
                        </div>

                        @if($selectedBatch)
                            <div class="parsed-fields-actions">
                                <button
                                    type="button"
                                    class="edit-record-btn"
                                    data-open-clean-data-modal
                                >
                                    <i class="fa-solid fa-eye"></i>
                                    View Clean Data
                                </button>
                            </div>
                        @endif
                    </div>

                    <div class="parsed-fields-list">
                        @foreach($fields as $label => $value)
                            <div class="parsed-field">
                                <span>{{ $label }}</span>
                                <strong>{{ $value ?? '—' }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="table-card structured-records-card">
                <div class="section-header">
                    <div>
                        <h2>
                            Structured Trip Records
                            @if($selectedBatch)
                                <span class="selected-batch-label">
                                    {{ $selectedBatch->file_name }}
                                </span>
                            @endif
                        </h2>
                        <p>{{ $tableSubtitle }}</p>
                    </div>

                    <div class="table-header-actions">
                        <form
                            method="GET"
                            action="{{ route('batch-file-processing') }}"
                            class="batch-search-form"
                        >
                            @if($selectedBatch)
                                <input
                                    type="hidden"
                                    name="batch_id"
                                    value="{{ $selectedBatch->id }}"
                                >
                            @endif

                            <div class="mini-search">
                                <i class="fa-solid fa-magnifying-glass"></i>

                                <input
                                    type="text"
                                    name="search"
                                    value="{{ request('search') }}"
                                    placeholder="Search record, group, or location..."
                                >
                            </div>
                        </form>

                        @if($records->total() > 0)
                            <a
                                href="{{ route('batch-file-processing.export', [
                                    'batch_id' => $selectedBatch?->id,
                                    'search' => request('search'),
                                ]) }}"
                                class="primary-btn export-btn"
                            >
                                <i class="fa-solid fa-file-export"></i>
                                Export CSV
                            </a>
                        @endif
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="batch-records-table">
                        <thead>
                            <tr>
                                <th>Bus No.</th>
                                <th>Record No.</th>
                                <th>Grouping</th>
                                <th>Type</th>
                                <th>Beginning</th>
                                <th>Initial Location</th>
                                <th>End</th>
                                <th>Final Location</th>
                                <th>Duration</th>
                                <th>Total Time</th>
                                <th>In Motion</th>
                                <th>Idling</th>
                                <th>Mileage</th>
                                <th>Engine Hours</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($records as $record)
                                <tr>
                                    <td><strong>{{ $record->bus_no ?? '—' }}</strong></td>
                                    <td>{{ $record->record_no ?? '—' }}</td>
                                    <td>{{ $record->grouping ?? '—' }}</td>
                                    <td>{{ $record->trip_type ?? '—' }}</td>
                                    <td>{{ $record->beginning_at?->format('M d, Y h:i A') ?? '—' }}</td>
                                    <td>{{ $record->initial_location ?? '—' }}</td>
                                    <td>{{ $record->ending_at?->format('M d, Y h:i A') ?? '—' }}</td>
                                    <td>{{ $record->final_location ?? '—' }}</td>
                                    <td>{{ $record->duration_minutes !== null ? $record->duration_minutes . ' mins' : '—' }}</td>
                                    <td>{{ $record->total_minutes !== null ? $record->total_minutes . ' mins' : '—' }}</td>
                                    <td>{{ $record->in_motion_minutes !== null ? $record->in_motion_minutes . ' mins' : '—' }}</td>
                                    <td>{{ $record->idling_minutes !== null ? $record->idling_minutes . ' mins' : '—' }}</td>
                                    <td>{{ $record->mileage_km !== null ? $record->mileage_km . ' km' : '—' }}</td>
                                    <td>{{ $record->engine_hours ?? '—' }}</td>

                                    <td>
                                        <a
                                            href="{{ route('batch-file-processing', [
                                                'batch_id' => $selectedBatch?->id,
                                                'selected_record' => $record->id,
                                                'search' => request('search'),
                                            ]) }}"
                                            class="table-action"
                                            title="View record"
                                        >
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="15" class="empty-users">
                                        @if($selectedBatch && $selectedBatch->status !== 'Processed')
                                            This selected file is still {{ $selectedBatch->status }}. Mark it as Processed first.
                                        @else
                                            Select a processed uploaded file to view its trip records here.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="batch-table-footer">
                    <p>
                        Showing {{ $records->firstItem() ?? 0 }}
                        to {{ $records->lastItem() ?? 0 }}
                        of {{ $records->total() }} records
                    </p>

                    @if($records->hasPages())
                        <div class="batch-simple-pagination">
                            @if($records->onFirstPage())
                                <span class="simple-page-button disabled">
                                    Previous
                                </span>
                            @else
                                <a
                                    href="{{ $records->previousPageUrl() }}"
                                    class="simple-page-button"
                                >
                                    Previous
                                </a>
                            @endif

                            <span class="simple-page-info">
                                Page {{ $records->currentPage() }} of {{ $records->lastPage() }}
                            </span>

                            @if($records->hasMorePages())
                                <a
                                    href="{{ $records->nextPageUrl() }}"
                                    class="simple-page-button"
                                >
                                    Next
                                </a>
                            @else
                                <span class="simple-page-button disabled">
                                    Next
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            </section>

            @if($selectedBatch && count($rawHeaders) > 0)
                <div class="records-modal-overlay" id="rawUploadModal">
                    <div class="records-modal">
                        <div class="records-modal-header">
                            <div>
                                <h2>Full Raw Uploaded Data</h2>

                                <p>
                                    Original rows from {{ $selectedBatch->file_name }}
                                    before cleaning and formatting.
                                </p>
                            </div>

                            <button
                                type="button"
                                class="records-modal-close"
                                id="closeRawUploadModal"
                            >
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <div class="records-modal-tools">
                            <div class="modal-search">
                                <i class="fa-solid fa-magnifying-glass"></i>

                                <input
                                    type="text"
                                    id="rawUploadSearch"
                                    placeholder="Search raw uploaded data..."
                                >
                            </div>
                        </div>

                        <div class="records-modal-table-wrap">
                            <table class="records-modal-table">
                                <thead>
                                    <tr>
                                        @foreach($rawHeaders as $rawHeader)
                                            <th>
                                                {{ ucwords(str_replace(['_', '-'], ' ', $rawHeader)) }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>

                                <tbody id="rawUploadTableBody">
                                    @foreach($allSelectedRecords as $record)
                                        @php
                                            $rawData = $rawRows[$record->id] ?? [];

                                            $rawSearchText = collect($rawData)
                                                ->map(function ($value) {
                                                    return is_array($value)
                                                        ? json_encode($value)
                                                        : (string) $value;
                                                })
                                                ->implode(' ');
                                        @endphp

                                        <tr data-raw-search="{{ strtolower($rawSearchText) }}">
                                            @foreach($rawHeaders as $rawHeader)
                                                @php
                                                    $rawValue = $rawData[$rawHeader] ?? '—';

                                                    if (is_array($rawValue)) {
                                                        $rawValue = json_encode($rawValue);
                                                    }

                                                    if ($rawValue === null || $rawValue === '') {
                                                        $rawValue = '—';
                                                    }
                                                @endphp

                                                <td>{{ $rawValue }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            @if($selectedBatch && $allSelectedRecords->isNotEmpty())
                <div class="records-modal-overlay" id="allBatchRecordsModal">
                    <div class="records-modal batch-editor-modal">
                        <div class="records-modal-header">
                            <div>
                                <h2>
                                    {{ $selectedBatch->status === 'In Review'
                                        ? 'Review and Edit GPS Records'
                                        : 'Cleaned Structured Records'
                                    }}
                                </h2>

                                <p>
                                    {{ $selectedBatch->file_name }}
                                    · {{ $allSelectedRecords->count() }} record(s)
                                </p>
                            </div>

                            <button
                                type="button"
                                class="records-modal-close"
                                id="closeAllBatchRecordsModal"
                            >
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <div class="records-modal-tools">
                            <div class="modal-search">
                                <i class="fa-solid fa-magnifying-glass"></i>

                                <input
                                    type="text"
                                    id="allBatchRecordsSearch"
                                    placeholder="Search records..."
                                >
                            </div>

                            @if($selectedBatch->status === 'In Review')
                                <div class="batch-editor-actions">
                                    <span class="unsaved-changes-label" id="unsavedChangesLabel">
                                        All changes saved
                                    </span>

                                    <button
                                        type="button"
                                        class="save-all-records-btn"
                                        id="saveAllBatchRecordsBtn"
                                        disabled
                                    >
                                        <i class="fa-solid fa-floppy-disk"></i>
                                        Save All Changes
                                    </button>

                                </div>
                            @endif
                        </div>

                        @if($selectedBatch->status === 'In Review')
                            <div class="batch-editor-note">
                                <i class="fa-solid fa-circle-info"></i>
                                Click a field to edit it. Edited rows are highlighted. Save all corrections when you are finished.
                            </div>
                        @endif

                        <form
                            action="{{ route('batch-file-processing.records.bulk-update', $selectedBatch) }}"
                            method="POST"
                            id="bulkUpdateRecordsForm"
                        >
                            @csrf
                            @method('PUT')

                            <div class="records-modal-table-wrap">
                                <table class="records-modal-table batch-editor-table">
                                    <thead>
                                        <tr>
                                            <th>Bus No.</th>
                                            <th>Record No.</th>
                                            <th>Grouping / Route</th>
                                            <th>Trip Type</th>
                                            <th>Beginning</th>
                                            <th>Initial Location</th>
                                            <th>End</th>
                                            <th>Final Location</th>
                                            <th>Duration</th>
                                            <th>Total</th>
                                            <th>In Motion</th>
                                            <th>Idling</th>
                                            <th>Mileage</th>
                                            <th>Engine Hours</th>
                                            <th>Location</th>
                                            <th>Coordinates</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>

                                    <tbody id="allBatchRecordsTableBody">
                                        @foreach($allSelectedRecords as $index => $record)
                                            <tr
                                                class="batch-edit-row"
                                                data-search="{{ strtolower(
                                                    ($record->record_no ?? '') . ' ' .
                                                    ($record->bus_no ?? '') . ' ' .
                                                    ($record->grouping ?? '') . ' ' .
                                                    ($record->initial_location ?? '') . ' ' .
                                                    ($record->final_location ?? '')
                                                ) }}"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="records[{{ $index }}][id]"
                                                    value="{{ $record->id }}"
                                                >

                                                <td>
                                                    @if($selectedBatch->status === 'In Review')
                                                        <input
                                                            class="batch-edit-input"
                                                            type="text"
                                                            name="records[{{ $index }}][bus_no]"
                                                            value="{{ $record->bus_no }}"
                                                            required
                                                        >
                                                    @else
                                                        <strong>{{ $record->bus_no ?? '—' }}</strong>
                                                    @endif
                                                </td>

                                                <td>
                                                    @if($selectedBatch->status === 'In Review')
                                                        <input
                                                            class="batch-edit-input"
                                                            type="text"
                                                            name="records[{{ $index }}][record_no]"
                                                            value="{{ $record->record_no }}"
                                                        >
                                                    @else
                                                        {{ $record->record_no ?? '—' }}
                                                    @endif
                                                </td>

                                                <td>
                                                    @if($selectedBatch->status === 'In Review')
                                                        <input
                                                            class="batch-edit-input"
                                                            type="text"
                                                            name="records[{{ $index }}][grouping]"
                                                            value="{{ $record->grouping }}"
                                                        >
                                                    @else
                                                        {{ $record->grouping ?? '—' }}
                                                    @endif
                                                </td>

                                                <td>
                                                    @if($selectedBatch->status === 'In Review')
                                                        <input
                                                            class="batch-edit-input"
                                                            type="text"
                                                            name="records[{{ $index }}][trip_type]"
                                                            value="{{ $record->trip_type }}"
                                                        >
                                                    @else
                                                        {{ $record->trip_type ?? '—' }}
                                                    @endif
                                                </td>

                                                <td>
                                                    @if($selectedBatch->status === 'In Review')
                                                        <input
                                                            class="batch-edit-input datetime-input"
                                                            type="datetime-local"
                                                            name="records[{{ $index }}][beginning_at]"
                                                            value="{{ $record->beginning_at?->format('Y-m-d\TH:i') }}"
                                                        >
                                                    @else
                                                        {{ $record->beginning_at?->format('M d, Y h:i A') ?? '—' }}
                                                    @endif
                                                </td>

                                                <td>
                                                    @if($selectedBatch->status === 'In Review')
                                                        <input
                                                            class="batch-edit-input"
                                                            type="text"
                                                            name="records[{{ $index }}][initial_location]"
                                                            value="{{ $record->initial_location }}"
                                                        >
                                                    @else
                                                        {{ $record->initial_location ?? '—' }}
                                                    @endif
                                                </td>

                                                <td>
                                                    @if($selectedBatch->status === 'In Review')
                                                        <input
                                                            class="batch-edit-input datetime-input"
                                                            type="datetime-local"
                                                            name="records[{{ $index }}][ending_at]"
                                                            value="{{ $record->ending_at?->format('Y-m-d\TH:i') }}"
                                                        >
                                                    @else
                                                        {{ $record->ending_at?->format('M d, Y h:i A') ?? '—' }}
                                                    @endif
                                                </td>

                                                <td>
                                                    @if($selectedBatch->status === 'In Review')
                                                        <input
                                                            class="batch-edit-input"
                                                            type="text"
                                                            name="records[{{ $index }}][final_location]"
                                                            value="{{ $record->final_location }}"
                                                        >
                                                    @else
                                                        {{ $record->final_location ?? '—' }}
                                                    @endif
                                                </td>

                                                @foreach([
                                                    'duration_minutes' => $record->duration_minutes,
                                                    'total_minutes' => $record->total_minutes,
                                                    'in_motion_minutes' => $record->in_motion_minutes,
                                                    'idling_minutes' => $record->idling_minutes,
                                                    'mileage_km' => $record->mileage_km,
                                                    'engine_hours' => $record->engine_hours,
                                                ] as $field => $value)
                                                    <td>
                                                        @if($selectedBatch->status === 'In Review')
                                                            <input
                                                                class="batch-edit-input number-input"
                                                                type="number"
                                                                step="0.01"
                                                                min="0"
                                                                name="records[{{ $index }}][{{ $field }}]"
                                                                value="{{ $value }}"
                                                            >
                                                        @else
                                                            {{ $value ?? '—' }}
                                                        @endif
                                                    </td>
                                                @endforeach

                                                <td>
                                                    @if($selectedBatch->status === 'In Review')
                                                        <input
                                                            class="batch-edit-input"
                                                            type="text"
                                                            name="records[{{ $index }}][location]"
                                                            value="{{ $record->location }}"
                                                        >
                                                    @else
                                                        {{ $record->location ?? '—' }}
                                                    @endif
                                                </td>

                                                <td>
                                                    @if($selectedBatch->status === 'In Review')
                                                        <input
                                                            class="batch-edit-input"
                                                            type="text"
                                                            name="records[{{ $index }}][coordinates]"
                                                            value="{{ $record->coordinates }}"
                                                        >
                                                    @else
                                                        {{ $record->coordinates ?? '—' }}
                                                    @endif
                                                </td>

                                                <td>
                                                    @if($selectedBatch->status === 'In Review')
                                                        <textarea
                                                            class="batch-edit-input batch-edit-textarea"
                                                            name="records[{{ $index }}][description]"
                                                            rows="2"
                                                        >{{ $record->description }}</textarea>
                                                    @else
                                                        {{ $record->description ?? '—' }}
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    </div>
                </div>
            @endif


            @if($selectedBatch && $allSelectedRecords->isNotEmpty())
                <div class="records-modal-overlay" id="cleanDataModal">
                    <div class="records-modal clean-data-modal">
                        <div class="records-modal-header">
                            <div>
                                <h2>View Clean Data</h2>

                                <p>
                                    {{ $selectedBatch->file_name }}
                                    · {{ $allSelectedRecords->count() }} record(s)
                                </p>
                            </div>

                            <button
                                type="button"
                                class="records-modal-close"
                                id="closeCleanDataModal"
                            >
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <div class="records-modal-tools clean-data-tools">
                            <div class="modal-search">
                                <i class="fa-solid fa-magnifying-glass"></i>

                                <input
                                    type="text"
                                    id="cleanDataSearch"
                                    placeholder="Search clean records..."
                                >
                            </div>

                            @if($selectedBatch->status === 'In Review')
                                <form
                                    action="{{ route('batch-file-processing.confirm', $selectedBatch) }}"
                                    method="POST"
                                    id="confirmBatchForm"
                                    class="clean-data-process-form"
                                >
                                    @csrf
                                    @method('PATCH')

                                    <button
                                        type="submit"
                                        class="confirm-batch-btn"
                                        id="markBatchProcessedBtn"
                                    >
                                        <i class="fa-solid fa-check"></i>
                                        Mark as Processed
                                    </button>
                                </form>
                            @endif
                        </div>

                        <div class="batch-editor-note clean-data-note">
                            <i class="fa-solid fa-circle-info"></i>
                            These cleaned structured values are generated automatically from the extracted data. Use Edit Extracted Data to make corrections before processing.
                        </div>

                        <div class="records-modal-table-wrap">
                            <table class="records-modal-table clean-data-table">
                                <thead>
                                    <tr>
                                        <th>Bus No.</th>
                                        <th>Record No.</th>
                                        <th>Grouping / Route</th>
                                        <th>Trip Type</th>
                                        <th>Beginning</th>
                                        <th>Initial Location</th>
                                        <th>End</th>
                                        <th>Final Location</th>
                                        <th>Duration</th>
                                        <th>Total</th>
                                        <th>In Motion</th>
                                        <th>Idling</th>
                                        <th>Mileage</th>
                                        <th>Engine Hours</th>
                                        <th>Location</th>
                                        <th>Coordinates</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>

                                <tbody id="cleanDataTableBody">
                                    @foreach($allSelectedRecords as $record)
                                        <tr
                                            data-clean-search="{{ strtolower(
                                                ($record->record_no ?? '') . ' ' .
                                                ($record->bus_no ?? '') . ' ' .
                                                ($record->grouping ?? '') . ' ' .
                                                ($record->trip_type ?? '') . ' ' .
                                                ($record->initial_location ?? '') . ' ' .
                                                ($record->final_location ?? '')
                                            ) }}"
                                        >
                                            <td><strong>{{ $record->bus_no ?? '—' }}</strong></td>
                                            <td>{{ $record->record_no ?? '—' }}</td>
                                            <td>{{ $record->grouping ?? '—' }}</td>
                                            <td>{{ $record->trip_type ?? '—' }}</td>
                                            <td>{{ $record->beginning_at?->format('M d, Y h:i A') ?? '—' }}</td>
                                            <td>{{ $record->initial_location ?? '—' }}</td>
                                            <td>{{ $record->ending_at?->format('M d, Y h:i A') ?? '—' }}</td>
                                            <td>{{ $record->final_location ?? '—' }}</td>
                                            <td>{{ $record->duration_minutes ?? '—' }}</td>
                                            <td>{{ $record->total_minutes ?? '—' }}</td>
                                            <td>{{ $record->in_motion_minutes ?? '—' }}</td>
                                            <td>{{ $record->idling_minutes ?? '—' }}</td>
                                            <td>{{ $record->mileage_km ?? '—' }}</td>
                                            <td>{{ $record->engine_hours ?? '—' }}</td>
                                            <td>{{ $record->location ?? '—' }}</td>
                                            <td>{{ $record->coordinates ?? '—' }}</td>
                                            <td>{{ $record->description ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <div class="batch-delete-modal-overlay" id="batchDeleteModal">
                <div class="batch-delete-modal">
                    <div class="batch-delete-icon">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>

                    <h2>Delete Uploaded File?</h2>

                    <p>
                        Are you sure you want to delete
                        <strong id="batchDeleteFileName">this uploaded file</strong>?
                        All related trip records will also be removed.
                    </p>

                    <form id="batchDeleteForm" method="POST" action="">
                        @csrf
                        @method('DELETE')

                        <div class="batch-delete-actions">
                            <button
                                type="button"
                                class="batch-delete-cancel-btn"
                                id="cancelBatchDelete"
                            >
                                Cancel
                            </button>

                            <button
                                type="submit"
                                class="batch-delete-confirm-btn"
                            >
                                <i class="fa-solid fa-trash"></i>
                                Yes, Delete
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</x-layout.app>