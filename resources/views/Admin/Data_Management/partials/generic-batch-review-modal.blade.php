@if($genericReviewBatch)
    <div
        class="records-modal-overlay show generic-batch-review-overlay"
        id="genericBatchReviewModal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="genericBatchReviewTitle"
    >
        <div class="records-modal generic-batch-review-modal">
            <div class="records-modal-header generic-review-modal-header">
                <div>
                    <div class="generic-review-title-row">
                        <h2 id="genericBatchReviewTitle">
                            {{ $genericReviewBatch->data_type }} Review
                        </h2>

                        <span class="generic-batch-status {{ $genericReviewBatch->status === 'Processed' ? 'processed' : '' }}">
                            {{ $genericReviewBatch->status }}
                        </span>
                    </div>

                    <p>
                        {{ $genericReviewBatch->file_name }}
                        · {{ number_format($genericReviewRecords->count()) }} record(s)
                    </p>
                </div>

                <a
                    href="{{ route('batch-file-processing', [], false) }}"
                    class="records-modal-close"
                    id="closeGenericBatchReviewModal"
                    aria-label="Close batch review"
                    title="Close"
                >
                    <i class="fa-solid fa-xmark"></i>
                </a>
            </div>

            <div class="generic-review-meta generic-review-modal-meta">
                <div class="generic-meta-item">
                    <span>Target Module</span>
                    <strong>{{ $genericReviewBatch->module }}</strong>
                </div>

                <div class="generic-meta-item">
                    <span>Data Type</span>
                    <strong>{{ $genericReviewBatch->data_type }}</strong>
                </div>

                <div class="generic-meta-item">
                    <span>File Type</span>
                    <strong>{{ strtoupper($genericReviewBatch->file_type) }}</strong>
                </div>

                <div class="generic-meta-item">
                    <span>Records</span>
                    <strong>{{ number_format($genericReviewRecords->count()) }}</strong>
                </div>
            </div>

            <div class="generic-review-note generic-review-modal-note">
                <i class="fa-solid fa-circle-info"></i>
                @if($genericReviewBatch->status === 'In Review')
                    Review the extracted values below. When everything is correct, click Save & Process Records to save the edits and send them to {{ $genericReviewBatch->module }}.
                @else
                    These records have already been processed and published to {{ $genericReviewBatch->module }}.
                @endif
            </div>

            @if($genericReviewRecords->isNotEmpty())
                <form
                    action="{{ route('batch-file-processing.generic.save-process', $genericReviewBatch, false) }}"
                    method="POST"
                    id="genericBatchReviewForm"
                    class="generic-review-process-form"
                    data-confirm-form
                    data-confirm-title="Save & Process Records?"
                    data-confirm-message="This will save all reviewed values and process them into {{ $genericReviewBatch->module }}. Continue?"
                    data-confirm-button="Yes, Save & Process"
                    data-confirm-type="approve"
                >
                    @csrf
                    @method('PATCH')

                    <div class="records-modal-table-wrap">
                        <table class="records-modal-table generic-record-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    @foreach($genericReviewHeaders as $header)
                                        <th>{{ ucwords(str_replace('_', ' ', $header)) }}</th>
                                    @endforeach
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($genericReviewRecords as $index => $record)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>

                                        @foreach($genericReviewHeaders as $header)
                                            @php
                                                $value = $record->payload[$header] ?? null;
                                                $displayValue = is_array($value)
                                                    ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                                                    : $value;
                                                $isLong = is_array($value)
                                                    || strlen((string) $displayValue) > 80;
                                            @endphp

                                            <td>
                                                @if($isLong)
                                                    <textarea
                                                        name="records[{{ $record->id }}][{{ $header }}]"
                                                        {{ $genericReviewBatch->status === 'In Review' ? '' : 'disabled' }}
                                                    >{{ $displayValue }}</textarea>
                                                @else
                                                    <input
                                                        type="text"
                                                        name="records[{{ $record->id }}][{{ $header }}]"
                                                        value="{{ $displayValue }}"
                                                        {{ $genericReviewBatch->status === 'In Review' ? '' : 'disabled' }}
                                                    >
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="batch-table-footer generic-review-modal-footer">
                        <p>
                            <strong>{{ number_format($genericReviewRecords->count()) }}</strong>
                            record(s) ready for review and processing.
                        </p>

                        @if($genericReviewBatch->status === 'In Review')
                            <button type="submit" class="upload-data-btn">
                                <i class="fa-solid fa-gears"></i>
                                Save & Process Records
                            </button>
                        @else
                            <span class="processed-badge">
                                <i class="fa-solid fa-circle-check"></i>
                                Processing Completed
                            </span>
                        @endif
                    </div>
                </form>
            @else
                <x-ui.empty-state
                    icon="fa-file-circle-xmark"
                    title="No staged records"
                    message="This batch does not contain reviewable structured records."
                />
            @endif
        </div>
    </div>
@endif
