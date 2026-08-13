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
                        href="{{ route('batch-file-processing', ['batch_id' => $batch->id], false) }}"
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
                            : ($batch->status === 'Failed' ? 'failed-badge' : 'review-badge') }}"
                        >
                            {{ $batch->status }}
                        </span>
                    </a>

                    <button
                        type="button"
                        class="delete-upload-btn"
                        title="Delete uploaded file"
                        data-delete-batch
                        data-delete-url="{{ route('batch-file-processing.destroy', $batch, false) }}"
                        data-delete-name="{{ $batch->file_name }}"
                    >
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            @empty
                <p class="empty-batch-message">No data files uploaded yet.</p>
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
                <small>Source: {{ $selectedRecord->batchUpload?->file_name }}</small>
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
                <p>Cleaned structured values from the selected record.</p>
            </div>

            @if($selectedBatch)
                <div class="parsed-fields-actions">
                    <button type="button" class="edit-record-btn" data-open-clean-data-modal>
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

<section data-ajax-region="records" class="table-card structured-records-card">
    <div class="section-header">
        <div>
            <h2>
                Structured Records · GPS Trip Profile
                @if($selectedBatch)
                    <span class="selected-batch-label">{{ $selectedBatch->file_name }}</span>
                @endif
            </h2>
            <p>{{ $tableSubtitle }}</p>
        </div>

        <div class="table-header-actions">
            <form method="GET" action="{{ route('batch-file-processing', [], false) }}" class="batch-search-form">
                @if($selectedBatch)
                    <input type="hidden" name="batch_id" value="{{ $selectedBatch->id }}">
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

    <div class="table-wrap structured-table-wrap">
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
                    </tr>
                @empty
                    <tr>
                        <td colspan="14" class="empty-users">
                            @if($selectedBatch && $selectedBatch->status !== 'Processed')
                                This selected file is still {{ $selectedBatch->status }}. Mark it as Processed first.
                            @else
                                Select a processed uploaded file to view its structured records here.
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
                    <span class="simple-page-button disabled">Previous</span>
                @else
                    <a href="{{ $records->previousPageUrl() }}" class="simple-page-button">Previous</a>
                @endif

                <span class="simple-page-info">
                    Page {{ $records->currentPage() }} of {{ $records->lastPage() }}
                </span>

                @if($records->hasMorePages())
                    <a href="{{ $records->nextPageUrl() }}" class="simple-page-button">Next</a>
                @else
                    <span class="simple-page-button disabled">Next</span>
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

                <button type="button" class="records-modal-close" id="closeRawUploadModal">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="records-modal-tools">
                <div class="modal-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="rawUploadSearch" placeholder="Search raw uploaded data...">
                </div>
            </div>

            <div class="records-modal-table-wrap">
                <table class="records-modal-table">
                    <thead>
                        <tr>
                            @foreach($rawHeaders as $rawHeader)
                                <th>{{ ucwords(str_replace(['_', '-'], ' ', $rawHeader)) }}</th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody id="rawUploadTableBody">
                        @foreach($allSelectedRecords as $record)
                            @php
                                $rawData = $rawRows[$record->id] ?? [];
                                $rawSearchText = collect($rawData)
                                    ->map(function ($value) {
                                        return is_array($value) ? json_encode($value) : (string) $value;
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
    <div class="records-modal-overlay" id="cleanDataModal">
        <div class="records-modal clean-data-modal">
            <div class="records-modal-header">
                <div>
                    <h2>Clean Data Preview</h2>
                    <p>
                        {{ $selectedBatch->file_name }}
                        · GPS Trip Records
                    </p>
                </div>

                <button type="button" class="records-modal-close" id="closeCleanDataModal">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="records-modal-tools clean-data-tools">
                <div class="modal-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="cleanDataSearch" placeholder="Search clean records...">
                </div>

                <div class="batch-editor-actions">
                    <span class="unsaved-changes-label">
                        {{ number_format($allSelectedRecords->count()) }} records
                    </span>

                    <span class="{{ $selectedBatch->status === 'Processed' ? 'processed-badge' : 'review-badge' }}">
                        {{ $selectedBatch->status }}
                    </span>

                    @if($selectedBatch->status === 'In Review')
                        <form
                            action="{{ route('batch-file-processing.confirm', $selectedBatch) }}"
                            method="POST"
                            id="confirmBatchForm"
                            class="clean-data-process-form"
                            data-confirm-form
                            data-confirm-title="Process Batch Records?"
                            data-confirm-message="Are you sure you want to process these reviewed GPS records?"
                            data-confirm-button="Yes, Process Records"
                            data-confirm-type="approve"
                        >
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="confirm-batch-btn" id="markBatchProcessedBtn">
                                <i class="fa-solid fa-gears"></i>
                                Process Records
                            </button>
                        </form>
                    @endif
                </div>
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
            All related extracted records will also be removed.
        </p>

        <form
            id="batchDeleteForm"
            method="POST"
            action=""
            data-index-url="{{ route('batch-file-processing', [], false) }}"
        >
            @csrf
            @method('DELETE')

            <div class="batch-delete-actions">
                <button type="button" class="batch-delete-cancel-btn" id="cancelBatchDelete">
                    Cancel
                </button>

                <button type="submit" class="batch-delete-confirm-btn">
                    <i class="fa-solid fa-trash"></i>
                    Yes, Delete
                </button>
            </div>
        </form>
    </div>
</div>