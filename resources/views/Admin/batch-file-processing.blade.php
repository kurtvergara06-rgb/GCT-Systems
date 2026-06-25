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
            'Grouping' => $selectedRecord?->grouping,
            'Beginning' => $selectedRecord?->beginning_at?->format('M d, Y h:i A'),
            'Initial Location' => $selectedRecord?->initial_location,
            'End' => $selectedRecord?->ending_at?->format('M d, Y h:i A'),
            'Final Location' => $selectedRecord?->final_location,
            'Engine Hours' => $selectedRecord?->engine_hours,
            'Total Time' => $selectedRecord?->total_minutes
                ? $selectedRecord->total_minutes . ' mins'
                : null,
            'In Motion' => $selectedRecord?->in_motion_minutes
                ? $selectedRecord->in_motion_minutes . ' mins'
                : null,
            'Idling' => $selectedRecord?->idling_minutes
                ? $selectedRecord->idling_minutes . ' mins'
                : null,
            'Mileage' => $selectedRecord?->mileage_km
                ? $selectedRecord->mileage_km . ' km'
                : null,
            'Severity' => $selectedRecord?->severity,
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
                if (!in_array($rawHeader, $rawHeaders, true)) {
                    $rawHeaders[] = $rawHeader;
                }
            }
        }

        $tableSubtitle = 'Select an uploaded GPS file to view trip records.';

        if ($selectedBatch?->status === 'In Review') {
            $tableSubtitle = 'Uploaded GPS trip records waiting for admin review.';
        } elseif ($selectedBatch?->status === 'Processed') {
            $tableSubtitle = 'Approved GPS trip records ready for analytics and reporting.';
        } elseif ($selectedBatch?->status === 'Failed') {
            $tableSubtitle = 'This upload failed validation and needs correction.';
        }
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

            @if(session('success') || session('error') || $errors->any())
                @php
                    $feedbackMessage = session('success')
                        ?? session('error')
                        ?? $errors->first();

                    $isSuccess = session('success') !== null;
                @endphp

                <div class="batch-feedback-overlay show" id="batchFeedbackModal">
                    <div class="batch-feedback-modal">
                        <div class="batch-feedback-icon {{ $isSuccess ? 'success' : 'error' }}">
                            <i class="fa-solid {{ $isSuccess ? 'fa-circle-check' : 'fa-circle-exclamation' }}"></i>
                        </div>

                        <h2>{{ $isSuccess ? 'Success' : 'Upload Failed' }}</h2>

                        <p>{{ $feedbackMessage }}</p>

                        @if(! $isSuccess)
                            <div class="batch-error-tip">
                                <i class="fa-solid fa-circle-info"></i>
                                Make sure the uploaded CSV contains a
                                <strong>Bus No.</strong> or
                                <strong>Vehicle ID</strong> column.
                            </div>
                        @endif

                        <button
                            type="button"
                            class="batch-feedback-close-btn"
                            id="closeBatchFeedbackModal"
                        >
                            Okay
                        </button>
                    </div>
                </div>
            @endif

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

                            <p>Upload CSV or TXT reports for extraction and review.</p>
                        </div>
                    </div>

                    <input
                        type="file"
                        id="gpsFileInput"
                        name="gps_file"
                        accept=".csv,.txt"
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
                            CSV and TXT supported
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

                            <p>Overview of your latest uploaded GPS files.</p>
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

                            <p>Select a file to review all extracted records.</p>
                        </div>
                    </div>

                    <div class="uploaded-file-list">
                        @forelse($batches as $batch)
                            <div class="uploaded-file-row">
                                <a
                                    href="{{ route('batch-file-processing', ['batch_id' => $batch->id]) }}"
                                    class="uploaded-file {{ $selectedBatchId == $batch->id ? 'active-file' : '' }}"
                                >
                                    <div class="file-icon csv">
                                        <i class="fa-solid fa-file-csv"></i>
                                    </div>

                                    <div class="file-info">
                                        <strong>{{ $batch->file_name }}</strong>

                                        <span>
                                            {{ $batch->created_at->format('M d, Y h:i A') }}
                                            · {{ $batch->processed_records }} record(s)
                                        </span>
                                    </div>

                                    <span class="
                                        {{ $batch->status === 'Processed'
                                            ? 'processed-badge'
                                            : ($batch->status === 'Failed'
                                                ? 'failed-badge'
                                                : 'review-badge') }}
                                    ">
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

                    @if($selectedBatch && $allSelectedRecords->isNotEmpty())
                        <button
                            type="button"
                            class="view-files-btn"
                            data-open-records-modal
                        >
                            View Cleaned Records
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    @endif
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
                            @if($records->total() > 0)
                                <span>
                                    Record {{ $records->firstItem() ?? 1 }}
                                    of {{ $records->total() }}
                                </span>
                            @endif

                            @if($selectedBatch && count($rawHeaders) > 0)
                                <button
                                    type="button"
                                    class="view-all-records-btn"
                                    data-open-raw-modal
                                >
                                    <i class="fa-solid fa-file-lines"></i>
                                    View Full Raw Upload
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

                            <p>Cleaned and structured values from the selected trip record.</p>
                        </div>

                        @if($selectedBatch && $allSelectedRecords->isNotEmpty())
                            <button
                                type="button"
                                class="view-all-records-btn"
                                data-open-records-modal
                            >
                                <i class="fa-solid fa-table-list"></i>
                                View Cleaned Records
                            </button>
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
                        <h2>Structured Trip Records</h2>
                        <p>Processed GPS trip records ready for analytics and reporting.</p>
                    </div>

                    <div class="table-header-actions">
                        <form
                            method="GET"
                            action="{{ route('batch-file-processing') }}"
                            class="batch-search-form"
                        >
                            @if($selectedBatchId)
                                <input
                                    type="hidden"
                                    name="batch_id"
                                    value="{{ $selectedBatchId }}"
                                >
                            @endif

                            <div class="mini-search">
                                <i class="fa-solid fa-magnifying-glass"></i>

                                <input
                                    type="text"
                                    name="search"
                                    value="{{ request('search') }}"
                                    placeholder="Search bus, route, or location..."
                                >
                            </div>
                        </form>

                        <a
                            href="{{ route('batch-file-processing.export', array_filter(request()->only(['batch_id', 'search']))) }}"
                            class="primary-btn export-btn"
                        >
                            <i class="fa-solid fa-file-export"></i>
                            Export CSV
                        </a>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="batch-records-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Bus No.</th>
                                <th>Grouping</th>
                                <th>Beginning</th>
                                <th>End</th>
                                <th>In Motion</th>
                                <th>Idling</th>
                                <th>Mileage</th>
                                <th>Severity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($records as $record)
                                <tr>
                                    <td>{{ ($records->firstItem() ?? 1) + $loop->index }}</td>
                                    <td><strong>{{ $record->bus_no }}</strong></td>
                                    <td>{{ $record->grouping ?? '—' }}</td>
                                    <td>{{ $record->beginning_at?->format('M d, Y h:i A') ?? '—' }}</td>
                                    <td>{{ $record->ending_at?->format('M d, Y h:i A') ?? '—' }}</td>
                                    <td>{{ $record->in_motion_minutes ?? 0 }} mins</td>
                                    <td>{{ $record->idling_minutes ?? 0 }} mins</td>
                                    <td>{{ $record->mileage_km ?? 0 }} km</td>

                                    <td>
                                        <span class="severity-{{ strtolower($record->severity ?? 'normal') }}">
                                            {{ $record->severity ?? 'Normal' }}
                                        </span>
                                    </td>

                                    <td>
                                        <a
                                            href="{{ route('batch-file-processing', [
                                                'batch_id' => $selectedBatchId,
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
                                    <td colspan="10" class="empty-users">
                                        No trip records found yet.
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
                        <div class="batch-pagination">
                            @if($records->onFirstPage())
                                <span class="page-arrow disabled">&lsaquo;</span>
                            @else
                                <a href="{{ $records->previousPageUrl() }}" class="page-arrow">
                                    &lsaquo;
                                </a>
                            @endif

                            @for($page = 1; $page <= $records->lastPage(); $page++)
                                <a
                                    href="{{ $records->url($page) }}"
                                    class="page-number {{ $records->currentPage() === $page ? 'active' : '' }}"
                                >
                                    {{ $page }}
                                </a>
                            @endfor

                            @if($records->hasMorePages())
                                <a href="{{ $records->nextPageUrl() }}" class="page-arrow">
                                    &rsaquo;
                                </a>
                            @else
                                <span class="page-arrow disabled">&rsaquo;</span>
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
                                        <th>#</th>

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
                                            <td>{{ $loop->iteration }}</td>

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
                    <div class="records-modal">
                        <div class="records-modal-header">
                            <div>
                                <h2>Cleaned Structured Records</h2>

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
                                    placeholder="Search cleaned records..."
                                >
                            </div>

                            @if($selectedBatch->status === 'In Review')
                                <form
                                    action="{{ route('batch-file-processing.confirm', $selectedBatch) }}"
                                    method="POST"
                                >
                                    @csrf
                                    @method('PATCH')

                                    <button type="submit" class="confirm-batch-btn">
                                        <i class="fa-solid fa-circle-check"></i>
                                        Mark as Processed
                                    </button>
                                </form>
                            @endif
                        </div>

                        <div class="records-modal-table-wrap">
                            <table class="records-modal-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Bus No.</th>
                                        <th>Grouping</th>
                                        <th>Beginning</th>
                                        <th>Initial Location</th>
                                        <th>End</th>
                                        <th>Final Location</th>
                                        <th>Engine Hours</th>
                                        <th>Total Time</th>
                                        <th>In Motion</th>
                                        <th>Idling</th>
                                        <th>Mileage</th>
                                        <th>Severity</th>
                                    </tr>
                                </thead>

                                <tbody id="allBatchRecordsTableBody">
                                    @foreach($allSelectedRecords as $record)
                                        <tr
                                            data-search="{{ strtolower(
                                                ($record->bus_no ?? '') . ' ' .
                                                ($record->grouping ?? '') . ' ' .
                                                ($record->initial_location ?? '') . ' ' .
                                                ($record->final_location ?? '')
                                            ) }}"
                                        >
                                            <td>{{ $loop->iteration }}</td>
                                            <td><strong>{{ $record->bus_no }}</strong></td>
                                            <td>{{ $record->grouping ?? '—' }}</td>
                                            <td>{{ $record->beginning_at?->format('M d, Y h:i A') ?? '—' }}</td>
                                            <td>{{ $record->initial_location ?? '—' }}</td>
                                            <td>{{ $record->ending_at?->format('M d, Y h:i A') ?? '—' }}</td>
                                            <td>{{ $record->final_location ?? '—' }}</td>
                                            <td>{{ $record->engine_hours ?? '—' }}</td>
                                            <td>{{ $record->total_minutes ?? 0 }} mins</td>
                                            <td>{{ $record->in_motion_minutes ?? 0 }} mins</td>
                                            <td>{{ $record->idling_minutes ?? 0 }} mins</td>
                                            <td>{{ $record->mileage_km ?? 0 }} km</td>

                                            <td>
                                                <span class="severity-{{ strtolower($record->severity ?? 'normal') }}">
                                                    {{ $record->severity ?? 'Normal' }}
                                                </span>
                                            </td>
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