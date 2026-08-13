<section class="processing-grid">
    <div class="panel-card uploaded-files-card">
        <div class="panel-card-header">
            <div>
                <h3>
                    <i class="fa-solid fa-folder-open"></i>
                    Uploaded Files
                </h3>
                <p>Processed batches open directly in the structured records table below.</p>
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
                                {{ $batch->module }} · {{ $batch->data_type }}
                                · {{ $batch->created_at->format('M d, Y h:i A') }}
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

    @php
        $previewRecord = $genericStructuredRecords->first();
        $previewRaw = $previewRecord?->raw_data ?? [];
        $previewPayload = $previewRecord?->payload ?? [];
    @endphp

    <div class="panel-card extracted-preview-card">
        <div class="panel-card-header">
            <div>
                <h3>
                    <i class="fa-solid fa-file-waveform"></i>
                    Extracted Data Preview
                </h3>
                <p>Original values from the selected processed batch.</p>
            </div>
        </div>

        <div class="text-preview">
            <pre>@if(count($previewRaw) > 0)@foreach($previewRaw as $key => $value){{ ucwords(str_replace('_', ' ', $key)) }}: {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ($value ?? '—') }}
@endforeach @else No extracted source values are available for this record. @endif</pre>
        </div>

        <div class="confidence-row">
            <span>
                <i class="fa-solid fa-circle-check"></i>
                Record Status:
                <strong>{{ $genericStructuredBatch->status }}</strong>
            </span>
            <small>Source: {{ $genericStructuredBatch->file_name }}</small>
        </div>
    </div>

    <div class="panel-card parsed-fields-card">
        <div class="panel-card-header">
            <div>
                <h3>
                    <i class="fa-solid fa-code"></i>
                    Parsed Fields
                </h3>
                <p>Structured values from the selected processed record.</p>
            </div>
        </div>

        <div class="parsed-fields-list">
            @forelse($previewPayload as $key => $value)
                <div class="parsed-field">
                    <span>{{ ucwords(str_replace('_', ' ', $key)) }}</span>
                    <strong>{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ($value ?? '—') }}</strong>
                </div>
            @empty
                <p class="empty-batch-message">No structured values are available.</p>
            @endforelse
        </div>
    </div>
</section>

<section data-ajax-region="records" class="table-card structured-records-card">
    <div class="section-header">
        <div>
            <h2>
                Structured Records · {{ $genericStructuredBatch->data_type }}
                <span class="selected-batch-label">{{ $genericStructuredBatch->file_name }}</span>
            </h2>
            <p>
                Showing processed structured records from: {{ $genericStructuredBatch->file_name }}
                · {{ $genericStructuredBatch->module }}
            </p>
        </div>

        <div class="table-header-actions">
            <form method="GET" action="{{ route('batch-file-processing', [], false) }}" class="batch-search-form">
                <input type="hidden" name="batch_id" value="{{ $genericStructuredBatch->id }}">
                <div class="mini-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search structured records..."
                    >
                </div>
            </form>
        </div>
    </div>

    <div class="table-wrap structured-table-wrap">
        <table class="batch-records-table generic-processed-table">
            <thead>
                <tr>
                    @foreach($genericStructuredHeaders as $header)
                        <th>{{ ucwords(str_replace('_', ' ', $header)) }}</th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @forelse($genericStructuredRecords as $record)
                    <tr>
                        @foreach($genericStructuredHeaders as $header)
                            @php
                                $value = $record->payload[$header] ?? null;
                                $displayValue = is_array($value)
                                    ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                                    : $value;
                            @endphp
                            <td>{{ $displayValue === null || $displayValue === '' ? '—' : $displayValue }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ max(1, $genericStructuredHeaders->count()) }}" class="empty-users">
                            No processed records matched this batch/search.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="batch-table-footer">
        <p>
            Showing {{ number_format($genericStructuredRecords->count()) }} processed record(s)
            from {{ $genericStructuredBatch->data_type }}.
        </p>
    </div>
</section>

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
