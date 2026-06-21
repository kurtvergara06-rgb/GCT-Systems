```blade
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

        if (strtolower($sidebarDepartment) === 'admin') {
            $sidebarRole = $sidebarRoleValue === 'head'
                ? 'System Admin'
                : 'Admin Staff';
        } else {
            $sidebarRole = $sidebarDepartment . ' ' . ucfirst($sidebarRoleValue ?: 'staff');
        }

        $rawPreview = $selectedRecord
            ? "Bus No:        {$selectedRecord->bus_no}\n"
                . "Grouping:      " . ($selectedRecord->grouping ?? '—') . "\n"
                . "Beginning:     " . ($selectedRecord->beginning_at?->format('M d, Y h:i A') ?? '—') . "\n"
                . "Initial Loc.:  " . ($selectedRecord->initial_location ?? '—') . "\n"
                . "End:           " . ($selectedRecord->ending_at?->format('M d, Y h:i A') ?? '—') . "\n"
                . "Final Loc.:    " . ($selectedRecord->final_location ?? '—') . "\n"
                . "Engine Hours:  " . ($selectedRecord->engine_hours ?? '—') . "\n"
                . "Total Time:    " . ($selectedRecord->total_minutes ?? '—') . " mins\n"
                . "In Motion:     " . ($selectedRecord->in_motion_minutes ?? '—') . " mins\n"
                . "Idling:        " . ($selectedRecord->idling_minutes ?? '—') . " mins\n"
                . "Mileage:       " . ($selectedRecord->mileage_km ?? '—') . " km"
            : 'Select an uploaded GPS batch to view extracted trip data.';
    @endphp

    <div class="app">

        <x-layout.sidebar
            department="Admin"
            subtitle="System Management"
            icon="fa-shield-halved"
            :user-name="$sidebarName"
            :user-role="$sidebarRole"
            :items="[
                [
                    'label' => 'Dashboard',
                    'route' => 'admin.dashboard',
                    'icon' => 'fa-table-cells-large'
                ],
                [
                    'label' => 'User Management',
                    'route' => 'admin.users',
                    'icon' => 'fa-users-gear'
                ],
                [
                    'label' => 'Permissions',
                    'route' => 'admin.permissions',
                    'icon' => 'fa-lock'
                ],
                [
                    'label' => 'Batch File Processing',
                    'route' => 'batch-file-processing',
                    'icon' => 'fa-file-arrow-up'
                ],
            ]"
        />

        <main class="main batch-main">

            <x-layout.topbar
                title="Batch File Processing"
                subtitle="Upload GPS files and convert them into structured trip records using NLP."
                notification-count="6"
            />

            @if(session('success') || session('error') || $errors->any())
                <div class="batch-feedback {{ session('success') ? 'success' : 'error' }}">
                    <i class="fa-solid {{ session('success') ? 'fa-circle-check' : 'fa-circle-exclamation' }}"></i>

                    <span>
                        {{ session('success') ?? session('error') ?? $errors->first() }}
                    </span>
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

                            <p>Upload PDF, TXT, or CSV files for extraction and processing.</p>
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

                            <span>
                                or choose files from your device
                            </span>
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

                        <button type="button" class="secondary-btn processing-history-btn">
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

                            <p>Select a processed file to review extracted records.</p>
                        </div>
                    </div>

                    <div class="uploaded-file-list">
                        @forelse($batches as $batch)
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
                                    </span>
                                </div>

                                <span class="{{ $batch->status === 'Processed' ? 'processed-badge' : 'review-badge' }}">
                                    {{ $batch->status }}
                                </span>
                            </a>
                        @empty
                            <p class="empty-batch-message">
                                No GPS reports uploaded yet.
                            </p>
                        @endforelse
                    </div>

                    @if($batches->count() > 3)
                        <button type="button" class="view-files-btn">
                            View All Uploads
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

                            <p>Raw text recognized from the selected uploaded file.</p>
                        </div>

                        @if($records->total() > 0)
                            <div class="record-navigation">
                                <span>
                                    Record {{ $records->firstItem() ?? 1 }} of {{ $records->total() }}
                                </span>
                            </div>
                        @endif
                    </div>

                    <div class="text-preview">
<pre>{{ $rawPreview }}</pre>
                    </div>

                    @if($selectedRecord)
                        <div class="confidence-row">
                            <span>
                                <i class="fa-solid fa-circle-check"></i>
                                Record Status: <strong>Processed</strong>
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

                            <p>Structured data extracted from the selected record.</p>
                        </div>
                    </div>

                    @php
                        $fields = [
                            'Bus No.' => $selectedRecord?->bus_no,
                            'Grouping' => $selectedRecord?->grouping,
                            'Beginning' => $selectedRecord?->beginning_at?->format('M d, Y h:i A'),
                            'Initial Location' => $selectedRecord?->initial_location,
                            'End' => $selectedRecord?->ending_at?->format('M d, Y h:i A'),
                            'Final Location' => $selectedRecord?->final_location,
                            'Engine Hours' => $selectedRecord?->engine_hours,
                            'Total Time' => $selectedRecord?->total_minutes ? $selectedRecord->total_minutes . ' mins' : null,
                            'In Motion' => $selectedRecord?->in_motion_minutes ? $selectedRecord->in_motion_minutes . ' mins' : null,
                            'Idling' => $selectedRecord?->idling_minutes ? $selectedRecord->idling_minutes . ' mins' : null,
                            'Mileage' => $selectedRecord?->mileage_km ? $selectedRecord->mileage_km . ' km' : null,
                        ];
                    @endphp

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
                        <p>Processed GPS trip records ready for analytics, reporting, and export.</p>
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
                                    placeholder="Search bus, route, or date..."
                                >
                            </div>
                        </form>

                        <button type="button" class="secondary-btn">
                            <i class="fa-solid fa-filter"></i>
                            Filters
                        </button>

                        <a
                            href="{{ route('batch-file-processing.export', request()->only(['batch_id', 'search'])) }}"
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
                                    <td>
                                        {{ ($records->firstItem() ?? 1) + $loop->index }}
                                    </td>

                                    <td>
                                        <strong>{{ $record->bus_no }}</strong>
                                    </td>

                                    <td>{{ $record->grouping ?? '—' }}</td>

                                    <td>
                                        {{ $record->beginning_at?->format('M d, Y h:i A') ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $record->ending_at?->format('M d, Y h:i A') ?? '—' }}
                                    </td>

                                    <td>{{ $record->in_motion_minutes ?? 0 }} mins</td>
                                    <td>{{ $record->idling_minutes ?? 0 }} mins</td>
                                    <td>{{ $record->mileage_km ?? 0 }} km</td>

                                    <td>
                                        <span class="severity-{{ strtolower($record->severity) }}">
                                            {{ $record->severity }}
                                        </span>
                                    </td>

                                    <td>
                                        <a
                                            href="{{ route('batch-file-processing', [
                                                'batch_id' => $selectedBatchId,
                                                'selected_record' => $record->id,
                                            ]) }}"
                                            class="table-action"
                                            title="View Record"
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

                    <div>
                        {{ $records->links() }}
                    </div>
                </div>

            </section>

        </main>
    </div>
</x-layout.app>
```
