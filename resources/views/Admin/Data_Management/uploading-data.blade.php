<x-layout.app
    title="FROMS - Import / Export"
    :assets="[
        'resources/css/Admin/Data_Management/uploading-data.css',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main import-export-page">
            <x-layout.topbar
                title="Import / Export"
                subtitle="Move already-structured records into FROMS or generate downloadable files from existing system data"
                notification-count="6"
            />

            <section data-ajax-region="summary" class="stats-grid import-export-stats">
                <x-ui.summary-card
                    label="Imports This Month"
                    :value="$transferStats['imports'] ?? 0"
                    small="Structured legacy and external files"
                    icon="fa-file-arrow-up"
                    color="blue"
                />

                <x-ui.summary-card
                    label="Exports This Month"
                    :value="$transferStats['exports'] ?? 0"
                    small="Generated from FROMS"
                    icon="fa-file-arrow-down"
                    color="green"
                />

                <x-ui.summary-card
                    label="Imported Records"
                    :value="number_format($transferStats['imported_records'] ?? 0)"
                    small="Successfully added records"
                    icon="fa-database"
                    color="yellow"
                />

                <x-ui.summary-card
                    label="Files Requiring Review"
                    :value="$transferStats['review'] ?? 0"
                    small="Failed or review-required transfers"
                    icon="fa-triangle-exclamation"
                    color="red"
                />
            </section>

            <section class="data-flow-guide">
                <article class="flow-card">
                    <div class="flow-icon import">
                        <i class="fa-solid fa-file-import"></i>
                    </div>

                    <div>
                        <span class="flow-label">IMPORT FLOW</span>
                        <h2>Structured External File to FROMS</h2>
                        <p>Use Import only when the file is already clean and mapped to a known FROMS record type.</p>

                        <div class="flow-steps">
                            <span>Structured File</span>
                            <i class="fa-solid fa-arrow-right"></i>
                            <span>Validate</span>
                            <i class="fa-solid fa-arrow-right"></i>
                            <span>Confirm</span>
                            <i class="fa-solid fa-arrow-right"></i>
                            <span>FROMS Database</span>
                        </div>
                    </div>
                </article>

                <article class="flow-card">
                    <div class="flow-icon export">
                        <i class="fa-solid fa-file-export"></i>
                    </div>

                    <div>
                        <span class="flow-label">EXPORT FLOW</span>
                        <h2>FROMS Data to Downloadable File</h2>
                        <p>Use Export for filtered reports, audit copies, and backups from records already stored inside FROMS.</p>

                        <div class="flow-steps">
                            <span>FROMS Database</span>
                            <i class="fa-solid fa-arrow-right"></i>
                            <span>Filter</span>
                            <i class="fa-solid fa-arrow-right"></i>
                            <span>Generate</span>
                            <i class="fa-solid fa-arrow-right"></i>
                            <span>Download</span>
                        </div>
                    </div>
                </article>
            </section>

            <section class="transfer-grid">
                <article class="transfer-card">
                    <div class="transfer-card-header">
                        <div class="transfer-icon import">
                            <i class="fa-solid fa-file-arrow-up"></i>
                        </div>

                        <div>
                            <h2>Import Structured Records</h2>
                            <p>Validate a clean CSV, TXT, XLS, or XLSX file before saving records to the selected module.</p>
                        </div>
                    </div>

                    <div class="transfer-form">
                        <div class="form-group">
                            <label for="importModule">Target Module</label>
                            <select id="importModule">
                                <option value="">Select Target Module</option>
                                <option>Operation</option>
                                <option>Maintenance</option>
                                <option>Warehouse</option>
                                <option>Purchase</option>
                                <option>Admin</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="importDataType">Data Type</label>
                            <select id="importDataType">
                                <option value="">Select Data Type</option>
                                <option>Bus Master Records</option>
                                <option>Driver Records</option>
                                <option>Historical Fuel Records</option>
                                <option>Inventory Items</option>
                                <option>Purchase Order Records</option>
                                <option>User Records</option>
                            </select>
                        </div>

                        <div class="import-source-note">
                            <div class="note-icon">
                                <i class="fa-solid fa-circle-info"></i>
                            </div>

                            <div>
                                <strong>Use Import only for structured files</strong>
                                <p>If the file is raw, messy, semi-structured, or needs PDF/text extraction first, send it to Batch File Processing.</p>
                            </div>
                        </div>

                        <div class="upload-zone">
                            <input
                                type="file"
                                id="importFile"
                                class="hidden-file-input"
                                accept=".csv,.xls,.xlsx,.txt"
                            >

                            <div class="upload-zone-icon">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                            </div>

                            <h3>Select a structured data file</h3>
                            <p>Drag and drop a file here or browse from your computer.</p>
                            <span>Supported formats: CSV, XLS, XLSX, TXT</span>

                            <label for="importFile" class="choose-file-btn">
                                <i class="fa-solid fa-folder-open"></i>
                                Choose File
                            </label>

                            <div id="selectedFileName" class="selected-file-name">
                                No file selected
                            </div>
                        </div>

                        <div class="validation-preview">
                            <div class="validation-header">
                                <div>
                                    <h3>Import Validation</h3>
                                    <p>Rows must pass the selected module/data-type mapping before any database write.</p>
                                </div>

                                <span class="validation-status waiting">
                                    Mapping not configured
                                </span>
                            </div>

                            <div class="validation-grid">
                                <div class="validation-item"><span>Total Rows</span><strong>—</strong></div>
                                <div class="validation-item"><span>Valid Records</span><strong>—</strong></div>
                                <div class="validation-item"><span>Invalid Records</span><strong>—</strong></div>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="primary-transfer-btn"
                            disabled
                            aria-disabled="true"
                            title="Module-specific import mappings must be implemented before validation can run."
                        >
                            <i class="fa-solid fa-lock"></i>
                            Import Mapping Required
                        </button>
                    </div>
                </article>

                <article class="transfer-card">
                    <div class="transfer-card-header">
                        <div class="transfer-icon export">
                            <i class="fa-solid fa-file-arrow-down"></i>
                        </div>

                        <div>
                            <h2>Export FROMS Records</h2>
                            <p>Generate a downloadable report from records already stored in FROMS.</p>
                        </div>
                    </div>

                    <div class="transfer-form">
                        <div class="form-group">
                            <label for="exportModule">Source Module</label>
                            <select id="exportModule">
                                <option value="">Select Source Module</option>
                                <option>Operation</option>
                                <option>Maintenance</option>
                                <option>Warehouse</option>
                                <option>Purchase</option>
                                <option>Admin</option>
                                <option>Analytics</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="exportDataType">Record Type</label>
                            <select id="exportDataType">
                                <option value="">Select Record Type</option>
                                <option>Bus Records</option>
                                <option>Driver Records</option>
                                <option>Job Orders</option>
                                <option>Fuel Reports</option>
                                <option>Purchase Requests</option>
                                <option>Purchase Orders</option>
                                <option>Inventory Records</option>
                                <option>Trip Records</option>
                                <option>Activity Logs</option>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="dateFrom">Date From</label>
                                <input type="date" id="dateFrom">
                            </div>

                            <div class="form-group">
                                <label for="dateTo">Date To</label>
                                <input type="date" id="dateTo">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="exportFormat">Export Format</label>
                            <select id="exportFormat">
                                <option>Excel (.xlsx)</option>
                                <option>CSV (.csv)</option>
                                <option>PDF (.pdf)</option>
                            </select>
                        </div>

                        <div class="export-options">
                            <p class="options-title">Include in Export</p>
                            <label class="option-item"><input type="checkbox" checked><span>Complete record details</span></label>
                            <label class="option-item"><input type="checkbox" checked><span>Status information</span></label>
                            <label class="option-item"><input type="checkbox"><span>Archived records</span></label>
                            <label class="option-item"><input type="checkbox"><span>Summary report</span></label>
                        </div>

                        <div class="export-source-preview">
                            <div class="database-icon">
                                <i class="fa-solid fa-database"></i>
                            </div>

                            <div>
                                <strong>Data Source</strong>
                                <p>The exported file will be generated from records currently saved in the FROMS database once the selected record mapping is implemented.</p>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="primary-transfer-btn export-btn"
                            disabled
                            aria-disabled="true"
                            title="Module-specific export mappings must be implemented before file generation can run."
                        >
                            <i class="fa-solid fa-lock"></i>
                            Export Mapping Required
                        </button>
                    </div>
                </article>
            </section>

            <section class="batch-processing-note">
                <div class="batch-note-icon">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>

                <div>
                    <h2>Does the file need extraction, cleaning, or parsing first?</h2>
                    <p>Send raw, messy, semi-structured, PDF, GPS, or inconsistent files to Batch File Processing before importing them into a module.</p>
                </div>

                <a href="{{ route('admin.batch-file-processing') }}" class="batch-processing-link">
                    Go to Batch File Processing
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </section>

            <section data-ajax-region="records" class="table-card recent-transfer-card">
                <div class="section-header">
                    <div>
                        <h2>Recent Import / Export Activity</h2>
                        <p>Live transfer activity recorded by the Data History subsystem.</p>
                    </div>

                    <span class="record-count">
                        {{ $recentTransferActivities->count() }} Recent
                    </span>
                </div>

                <div class="table-wrap">
                    <table class="transfer-table">
                        <thead>
                            <tr>
                                <th>File</th>
                                <th>Type</th>
                                <th>Module</th>
                                <th>Data Source</th>
                                <th>Records</th>
                                <th>Status</th>
                                <th>Date & Time</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($recentTransferActivities as $activity)
                                @php
                                    $moduleClass = strtolower($activity->module ?: 'admin');
                                    $typeClass = strtolower($activity->activity_type);
                                    $statusClass = strtolower(str_replace(' ', '-', $activity->status));
                                @endphp

                                <tr>
                                    <td>
                                        <div class="file-cell">
                                            <div class="file-icon {{ $typeClass }}">
                                                <i class="fa-solid {{ $activity->activity_type === 'Import' ? 'fa-file-arrow-up' : 'fa-file-arrow-down' }}"></i>
                                            </div>

                                            <div>
                                                <strong>{{ $activity->file_name ?: 'Generated Data File' }}</strong>
                                                @if($activity->data_type)
                                                    <small style="display:block;margin-top:3px;color:var(--muted);font-size:10px;">
                                                        {{ $activity->data_type }}
                                                    </small>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    <td><span class="transfer-type {{ $typeClass }}">{{ $activity->activity_type }}</span></td>
                                    <td><span class="module-badge {{ $moduleClass }}">{{ $activity->module ?: '—' }}</span></td>
                                    <td><span class="source-badge">{{ $activity->source ?: '—' }}</span></td>
                                    <td>{{ number_format($activity->total_records) }}</td>
                                    <td><span class="status-badge {{ $statusClass }}">{{ $activity->status }}</span></td>
                                    <td>
                                        <div class="date-time-cell">
                                            <span class="date-value">{{ $activity->created_at?->format('M d, Y') ?? '—' }}</span>
                                            <span class="time-value">{{ $activity->created_at?->format('g:i A') ?? '' }}</span>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="actions">
                                            <x-ui.action-button
                                                type="view"
                                                :href="route('admin.data-history', ['search' => $activity->file_name])"
                                                title="View in Data History"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" style="text-align:center;padding:34px;color:var(--muted);">
                                        No Import or Export activity has been recorded yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="table-footer">
                    <p>Showing {{ $recentTransferActivities->count() }} recent activities</p>

                    <a href="{{ route('admin.data-history') }}" class="batch-processing-link">
                        View Full Data History
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            </section>
        </main>
    </div>
</x-layout.app>