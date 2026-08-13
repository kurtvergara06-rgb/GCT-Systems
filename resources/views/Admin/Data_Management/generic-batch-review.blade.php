<x-layout.app
    title="FROMS - Batch Review"
    :assets="[
        'resources/css/Admin/Data_Management/batch-file-processing.css',
        'resources/css/Admin/Data_Management/generic-batch-review.css',
    ]"
>
    <x-layout.sidebar department="Admin" />

    <main class="main generic-batch-page">
        <x-layout.topbar
            title="Batch File Processing"
            subtitle="Review structured records before publishing them to the selected module."
            notification-count="6"
        />

        <section class="generic-review-card">
            <div class="generic-review-header">
                <div>
                    <h2>{{ $batch->data_type }} Review</h2>
                    <p>{{ $batch->file_name }}</p>
                </div>

                <div class="generic-review-actions">
                    <span class="generic-batch-status {{ $batch->status === 'Processed' ? 'processed' : '' }}">
                        {{ $batch->status }}
                    </span>

                    <a
                        href="{{ route('batch-file-processing', [], false) }}"
                        class="secondary-btn"
                    >
                        <i class="fa-solid fa-arrow-left"></i>
                        Back to Batch Processing
                    </a>
                </div>
            </div>

            <div class="generic-review-meta">
                <div class="generic-meta-item">
                    <span>Target Module</span>
                    <strong>{{ $batch->module }}</strong>
                </div>

                <div class="generic-meta-item">
                    <span>Data Type</span>
                    <strong>{{ $batch->data_type }}</strong>
                </div>

                <div class="generic-meta-item">
                    <span>File Type</span>
                    <strong>{{ strtoupper($batch->file_type) }}</strong>
                </div>

                <div class="generic-meta-item">
                    <span>Records</span>
                    <strong>{{ number_format($records->count()) }}</strong>
                </div>
            </div>

            <div class="generic-review-note">
                <i class="fa-solid fa-circle-info"></i>
                @if($batch->status === 'In Review')
                    Review and correct the extracted values below. Save changes first, then publish the batch to {{ $batch->module }}.
                @else
                    These records have already been published to {{ $batch->module }}.
                @endif
            </div>

            @if($records->isNotEmpty())
                <form
                    action="{{ route('batch-file-processing.generic.records.update', $batch, false) }}"
                    method="POST"
                    id="genericBatchReviewForm"
                    data-confirm-form
                    data-confirm-title="Save Batch Corrections?"
                    data-confirm-message="Save all reviewed values before publishing this batch?"
                    data-confirm-button="Yes, Save Changes"
                    data-confirm-type="update"
                >
                    @csrf
                    @method('PUT')

                    <div class="generic-record-table-wrap">
                        <table class="generic-record-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    @foreach($headers as $header)
                                        <th>{{ ucwords(str_replace('_', ' ', $header)) }}</th>
                                    @endforeach
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($records as $index => $record)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>

                                        @foreach($headers as $header)
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
                                                        {{ $batch->status === 'In Review' ? '' : 'disabled' }}
                                                    >{{ $displayValue }}</textarea>
                                                @else
                                                    <input
                                                        type="text"
                                                        name="records[{{ $record->id }}][{{ $header }}]"
                                                        value="{{ $displayValue }}"
                                                        {{ $batch->status === 'In Review' ? '' : 'disabled' }}
                                                    >
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="generic-review-footer">
                        <p>{{ $records->count() }} staged record(s) in this batch.</p>

                        @if($batch->status === 'In Review')
                            <div class="generic-review-actions">
                                <button type="submit" class="secondary-btn">
                                    <i class="fa-solid fa-floppy-disk"></i>
                                    Save Review Changes
                                </button>
                            </div>
                        @endif
                    </div>
                </form>

                @if($batch->status === 'In Review')
                    <form
                        action="{{ route('batch-file-processing.generic.confirm', $batch, false) }}"
                        method="POST"
                        class="generic-review-actions"
                        data-confirm-form
                        data-confirm-title="Publish Batch Records?"
                        data-confirm-message="This will publish the reviewed records to {{ $batch->module }}. Continue?"
                        data-confirm-button="Yes, Publish Batch"
                        data-confirm-type="approve"
                    >
                        @csrf
                        @method('PATCH')

                        <button type="submit" class="primary-btn">
                            <i class="fa-solid fa-circle-check"></i>
                            Publish to {{ $batch->module }}
                        </button>
                    </form>
                @endif
            @else
                <x-ui.empty-state
                    icon="fa-file-circle-xmark"
                    title="No staged records"
                    message="This batch does not contain reviewable structured records."
                />
            @endif
        </section>
    </main>
</x-layout.app>