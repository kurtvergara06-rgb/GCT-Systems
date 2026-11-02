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
                        <x-ui.file-thumbnail
                            :filename="$batch->file_name"
                            :type="$batch->file_type"
                        />

                        <div class="file-info">
                            <strong>{{ $batch->file_name }}</strong>
                            <span>
                                {{ $batch->created_at->format('M d, Y h:i A') }}
                                · {{ $batch->processed_records }} record(s)
                            </span>
                            @if($batch->status === 'Processing' && $batch->file_type === 'pdf')
                                <div class="gps-file-progress" data-gps-progress-id="{{ $batch->id }}" aria-label="PDF extraction {{ $batch->progress_percentage }} percent">
                                    <span class="gps-file-progress-label"><span data-gps-progress-stage>{{ $batch->progress_percentage < 1 ? 'Queued' : ($batch->progress_percentage < 5 ? 'Extracting PDF text' : 'Parsing GPS records') }}</span> <strong data-gps-progress-value>{{ $batch->progress_percentage }}%</strong></span>
                                    <span class="gps-file-progress-track"><span data-gps-progress-fill style="width: {{ $batch->progress_percentage }}%"></span></span>
                                </div>
                            @endif
                        </div>

                        <x-ui.status-badge
                            :status="$batch->status"
                            class="{{ $batch->status === 'Processed'
                                ? 'processed-badge'
                                : ($batch->status === 'Failed' ? 'failed-badge' : 'review-badge') }}"
                        />
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

    <div class="panel-card gps-fleet-coverage">
        <div class="panel-card-header">
            <div>
                <h3><i class="fa-solid fa-bus-simple"></i> GPS Fleet Coverage</h3>
                <p>Vehicles identified in the selected GPS report.</p>
            </div>
            @if($selectedBatch?->status)
                <x-ui.status-badge :status="$selectedBatch->status" />
            @endif
        </div>

        <div class="gps-fleet-metrics">
            <div class="gps-fleet-metric">
                <span>Vehicles Detected</span>
                <strong>{{ number_format($fleetVehicleCount) }}</strong>
            </div>
            <div class="gps-fleet-metric">
                <span>Records Extracted</span>
                <strong>{{ number_format($fleetRecordCount) }}</strong>
            </div>
        </div>

        <div class="gps-fleet-list-heading">
            <h4>Vehicle Breakdown</h4>
            @if($fleetVehicleCount > 0)
                <span>{{ number_format($fleetVehicleCount) }} vehicles</span>
            @endif
        </div>
        @if($fleetVehicleCount > 0)
            <div class="gps-fleet-vehicle-list">
                @foreach($fleetCoverage as $vehicle)
                    <div class="gps-fleet-vehicle">
                        <div class="gps-fleet-vehicle-info">
                            <span><i class="fa-solid fa-bus" aria-hidden="true"></i> {{ $vehicle->vehicle }}</span>
                            <small>{{ number_format($vehicle->record_count) }} records</small>
                        </div>
                        <div class="gps-fleet-vehicle-track" role="meter" aria-label="Records for {{ $vehicle->vehicle }}" aria-valuemin="0" aria-valuemax="{{ max(1, $fleetCoverage->max('record_count')) }}" aria-valuenow="{{ $vehicle->record_count }}">
                            <span style="width: {{ round(100 * $vehicle->record_count / max(1, $fleetCoverage->max('record_count')), 2) }}%"></span>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="gps-fleet-coverage-empty">
                <i class="fa-solid fa-bus-simple" aria-hidden="true"></i>
                <p>{{ $selectedBatch?->status === 'Processing' ? 'Extracting vehicle records. Coverage will appear after processing.' : 'Select an uploaded file with extracted GPS records to view its fleet coverage.' }}</p>
            </div>
        @endif
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
            @if($selectedBatch?->status === 'In Review')
                <form action="{{ route('batch-file-processing.confirm', $selectedBatch) }}"
                    method="POST" id="confirmBatchForm" class="clean-data-process-form"
                    data-confirm-form
                    data-confirm-title="Confirm GPS Batch?"
                    data-confirm-message="Publish the extracted records after verifying the batch. Invalid or missing rows will not be included."
                    data-confirm-button="Confirm Processing"
                    data-confirm-type="approve">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="confirm-batch-btn" id="markBatchProcessedBtn">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i> Confirm Processing
                    </button>
                </form>
            @endif

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
                        @if(!$selectedBatch || $records->isEmpty()) disabled aria-disabled="true" @endif
                    >
                </div>
            </form>

            @if($records->count() > 0)
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

    @if($records->isNotEmpty())
    <div class="table-wrap structured-table-wrap">
        <table class="batch-records-table">
            <thead>
                <tr>
                    <th>Bus No.</th>
                    <th>Record No.</th>
                    <th>Beginning</th>
                    <th>End</th>
                    <th>Mileage</th>
                    <th>Engine Hours</th>
                </tr>
            </thead>

            <tbody>
                @forelse($records as $record)
                    <tr class="gps-compact-record-row">
                        <td><x-ui.id-badge :value="$record->bus_no" /></td>
                        <td><x-ui.id-badge :value="$record->record_no" /></td>
                        <td>{{ $record->beginning_at?->format('M d, Y h:i A') ?? '—' }}</td>
                        <td>{{ $record->ending_at?->format('M d, Y h:i A') ?? '—' }}</td>
                        <td>{{ $record->mileage_km !== null ? $record->mileage_km . ' km' : '—' }}</td>
                        <td>{{ $record->engine_hours ?? '—' }}</td>
                    </tr>
                @empty
                @endforelse
            </tbody>
        </table>
    </div>

    @else
        <div class="gps-records-empty" role="status">
            <div class="gps-records-empty-icon"><i class="fa-solid fa-file-circle-question" aria-hidden="true"></i></div>
            <h3>{{ !$selectedBatch ? 'No GPS records selected' : ($selectedBatch->status === 'Processing' ? 'GPS extraction in progress' : 'No matching GPS records') }}</h3>
            <p>
                @if(!$selectedBatch)
                    Choose a GPS file from Uploaded Files to view its extracted records.
                @elseif($selectedBatch->status === 'Processing')
                    Records will appear here automatically when PDF extraction finishes.
                @else
                    No records match the selected file and search. Try another search or select a different batch.
                @endif
            </p>
        </div>
    @endif

    <div class="batch-table-footer gps-infinite-footer"
        data-gps-next-url="{{ $records->nextPageUrl() ?? '' }}"
        data-gps-total="{{ $records->total() }}"
        data-gps-loaded="{{ $records->count() }}">
        <p data-gps-loaded-label>Showing {{ number_format($records->count()) }} of {{ number_format($records->total()) }} records</p>
        @if($records->hasMorePages())
            <span class="gps-load-state" role="status" aria-live="polite">Scroll to load more</span>
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

            @if($allSelectedRecords->hasPages())
                <nav class="batch-simple-pagination" aria-label="Raw record preview pages">
                    <a
                        class="simple-page-button {{ $allSelectedRecords->onFirstPage() ? 'disabled' : '' }}"
                        href="{{ $allSelectedRecords->onFirstPage() ? '#' : route('batch-file-processing', array_merge(request()->query(), ['preview_page' => $allSelectedRecords->currentPage() - 1, 'preview' => 'raw']), false) }}"
                        @if($allSelectedRecords->onFirstPage()) aria-disabled="true" tabindex="-1" @endif
                    >Previous</a>
                    <span class="simple-page-info">Page {{ $allSelectedRecords->currentPage() }} of {{ $allSelectedRecords->lastPage() }}</span>
                    <a
                        class="simple-page-button {{ $allSelectedRecords->hasMorePages() ? '' : 'disabled' }}"
                        href="{{ $allSelectedRecords->hasMorePages() ? route('batch-file-processing', array_merge(request()->query(), ['preview_page' => $allSelectedRecords->currentPage() + 1, 'preview' => 'raw']), false) : '#' }}"
                        @unless($allSelectedRecords->hasMorePages()) aria-disabled="true" tabindex="-1" @endunless
                    >Next</a>
                </nav>
            @endif
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
