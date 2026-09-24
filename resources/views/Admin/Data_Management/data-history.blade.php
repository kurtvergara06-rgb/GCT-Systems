<x-layout.app
    title="FROMS - Data History"
    :assets="[
        'resources/css/Admin/Data_Management/data-history.css',
        'resources/js/Admin/Data_Management/data-history.js',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main data-history-page">
            <x-layout.topbar
                title="Data History"
                subtitle="Review batch processing, imports, exports, and their recorded results"
                notification-count="6"
            />

            <section data-ajax-region="summary" class="stats-grid history-stats-grid">
                <x-ui.summary-card
                    label="Total Data Activities"
                    :value="$stats['total'] ?? 0"
                    small="Recorded batch, import, and export activities"
                    icon="fa-database"
                    color="blue"
                />

                <x-ui.summary-card
                    label="Successful"
                    :value="$stats['successful'] ?? 0"
                    small="Completed operations"
                    icon="fa-circle-check"
                    color="green"
                />

                <x-ui.summary-card
                    label="Processed Files"
                    :value="$stats['processed_files'] ?? 0"
                    small="Batch processed or imported files"
                    icon="fa-file-circle-check"
                    color="yellow"
                />

                <x-ui.summary-card
                    label="Failed"
                    :value="$stats['failed'] ?? 0"
                    small="Operations requiring review"
                    icon="fa-triangle-exclamation"
                    color="red"
                />
            </section>

            <section data-ajax-region="records" class="table-card data-history-card">
                <div class="section-header">
                    <div>
                        <h2>Data Activity History</h2>
                        <p>One audit trail for Batch Processing, Import, and Export activities.</p>
                    </div>

                    <x-ui.id-badge :value="number_format($history->count()) . ' Records'" />
                </div>

                <form method="GET" action="{{ route('admin.data-history') }}" class="history-toolbar">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Search file, module, data type, source..."
                        >
                    </div>

                    <div class="filter-group">
                        <select name="type" onchange="this.form.submit()">
                            @foreach(['All Types', 'Batch Processing', 'Import', 'Export'] as $type)
                                <option value="{{ $type }}" {{ request('type', 'All Types') === $type ? 'selected' : '' }}>
                                    {{ $type }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <select name="module" onchange="this.form.submit()">
                            @foreach(['All Modules', 'Admin', 'Operation', 'Maintenance', 'Warehouse', 'Purchase'] as $module)
                                <option value="{{ $module }}" {{ request('module', 'All Modules') === $module ? 'selected' : '' }}>
                                    {{ $module }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <select name="status" onchange="this.form.submit()">
                            @foreach(['All Status', 'Completed', 'For Review', 'Needs Correction', 'Failed', 'Processing', 'Deleted'] as $status)
                                <option value="{{ $status }}" {{ request('status', 'All Status') === $status ? 'selected' : '' }}>
                                    {{ $status }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>

                <div class="table-wrap">
                    <table class="data-history-table">
                        <thead>
                            <tr>
                                <th>File</th>
                                <th>Type</th>
                                <th>Module</th>
                                <th>Data Source</th>
                                <th>Records</th>
                                <th>Status</th>
                                <th>Processed By</th>
                                <th>Date & Time</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($history as $item)
                                @php
                                    $typeClass = match($item->activity_type) {
                                        'Import' => 'import',
                                        'Export' => 'export',
                                        default => 'batch',
                                    };

                                    $moduleClass = strtolower($item->module ?: 'admin');
                                    $statusClass = match($item->status) {
                                        'Completed' => 'completed',
                                        'Failed', 'Needs Correction' => 'failed',
                                        'Deleted' => 'deleted',
                                        default => 'processing',
                                    };

                                    $processorName = $item->processor?->name ?? 'System';
                                    $activityDate = $item->created_at;
                                @endphp

                                <tr>
                                    <td>
                                        <div class="history-file-cell">
                                            <div class="history-file-icon {{ $typeClass }}">
                                                @if($item->activity_type === 'Import')
                                                    <i class="fa-solid fa-file-arrow-up"></i>
                                                @elseif($item->activity_type === 'Export')
                                                    <i class="fa-solid fa-file-arrow-down"></i>
                                                @else
                                                    <i class="fa-solid fa-file-import"></i>
                                                @endif
                                            </div>

                                            <div>
                                                <strong>{{ $item->file_name ?: 'System Data Activity' }}</strong>
                                                @if($item->data_type)
                                                    <small style="display:block;margin-top:3px;color:var(--muted);font-size:10px;">
                                                        {{ $item->data_type }}
                                                    </small>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="history-type {{ $typeClass }}">
                                            {{ $item->activity_type }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="module-badge {{ $moduleClass }}">
                                            {{ $item->module ?: '—' }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="source-badge">
                                            {{ $item->source ?: '—' }}
                                        </span>
                                    </td>

                                    <td>{{ number_format($item->total_records) }}</td>

                                    <td>
                                        <span class="history-status {{ $statusClass }}">
                                            {{ $item->status }}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="processed-by">
                                            <i class="fa-solid fa-user"></i>
                                            <span>{{ $processorName }}</span>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="date-time-cell">
                                            <span class="date-value">{{ $activityDate?->format('M d, Y') ?? '—' }}</span>
                                            <span class="time-value">{{ $activityDate?->format('g:i A') ?? '' }}</span>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="actions">
                                            <x-ui.action-button
                                                type="view"
                                                class="open-history-modal"
                                                title="View Details"
                                                data-id="{{ $item->id }}"
                                                data-url="{{ route('admin.data-history.show', $item) }}"
                                                data-file="{{ $item->file_name ?: 'System Data Activity' }}"
                                                data-type="{{ $item->activity_type }}"
                                                data-module="{{ $item->module ?: '—' }}"
                                                data-data-type="{{ $item->data_type ?: '—' }}"
                                                data-source="{{ $item->source ?: '—' }}"
                                                data-records="{{ $item->total_records }}"
                                                data-successful="{{ $item->successful_records }}"
                                                data-failed="{{ $item->failed_records }}"
                                                data-skipped="{{ $item->skipped_records }}"
                                                data-status="{{ $item->status }}"
                                                data-user="{{ $processorName }}"
                                                data-date="{{ $activityDate?->format('M d, Y') ?? '—' }}"
                                                data-time="{{ $activityDate?->format('g:i A') ?? '' }}"
                                                data-error="{{ $item->error_message ?: 'None' }}"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" style="text-align:center;padding:36px;color:var(--muted);">
                                        No Data Management activity has been recorded yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="table-footer">
                    <p>
                        Showing {{ number_format($history->count()) }} {{ \Illuminate\Support\Str::plural('record', $history->count()) }}
                    </p>
                </div>
            </section>
        </main>
    </div>

    <div id="historyDetailsModal" class="history-modal-overlay">
        <div class="history-modal">
            <div class="history-modal-header">
                <div class="history-modal-title">
                    <div class="history-modal-icon">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>

                    <div>
                        <h2>Data History Details</h2>
                        <p>Recorded result of the selected Data Management activity.</p>
                    </div>
                </div>

                <button type="button" id="closeHistoryModal" class="history-modal-close" aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="history-modal-body">
                <div class="history-modal-grid">
                    <div class="history-detail-item"><span>File</span><strong id="historyModalFile">—</strong></div>
                    <div class="history-detail-item"><span>Activity Type</span><strong id="historyModalType">—</strong></div>
                    <div class="history-detail-item"><span>Module</span><strong id="historyModalModule">—</strong></div>
                    <div class="history-detail-item"><span>Data Type</span><strong id="historyModalDataType">—</strong></div>
                    <div class="history-detail-item"><span>Data Source</span><strong id="historyModalSource">—</strong></div>
                    <div class="history-detail-item"><span>Total Records</span><strong id="historyModalRecords">—</strong></div>
                    <div class="history-detail-item"><span>Successful</span><strong id="historyModalSuccessful">—</strong></div>
                    <div class="history-detail-item"><span>Failed</span><strong id="historyModalFailed">—</strong></div>
                    <div class="history-detail-item"><span>Skipped</span><strong id="historyModalSkipped">—</strong></div>
                    <div class="history-detail-item"><span>Status</span><strong id="historyModalStatus">—</strong></div>
                    <div class="history-detail-item"><span>Processed By</span><strong id="historyModalUser">—</strong></div>
                    <div class="history-detail-item"><span>Date & Time</span><strong id="historyModalDateTime">—</strong></div>
                    <div class="history-detail-item" style="grid-column:1 / -1;"><span>Error / Notes</span><strong id="historyModalError">—</strong></div>
                </div>

                <div id="historyModalValidationContainer" style="display:none;margin-top:14px;">
                    <div style="font-size:11px;font-weight:700;color:#dc2626;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;">
                        Validation Errors (<span id="historyModalErrorCount">0</span>)
                    </div>
                    <div id="historyModalErrorsList" style="max-height:140px;overflow-y:auto;background:#fef2f2;border:1px solid #fee2e2;border-radius:10px;padding:10px 12px;font-size:12px;color:#991b1b;display:flex;flex-direction:column;gap:6px;">
                    </div>
                </div>
            </div>

            <div class="history-modal-footer" style="display:flex;justify-content:space-between;align-items:center;">
                <a id="historyModalActionLink" href="#" style="display:none;align-items:center;gap:6px;font-size:12px;font-weight:700;color:var(--dh-blue);text-decoration:none;">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    <span id="historyModalActionText">Go to Activity</span>
                </a>
                <button type="button" id="closeHistoryModalFooter" class="history-close-btn" style="margin-left:auto;">Close</button>
            </div>
        </div>
    </div>
</x-layout.app>