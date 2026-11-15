<x-layout.app
    title="FROMS - Import / Export"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/Data_Management/uploading-data.css',
        'resources/js/Main-js/sidebar.js'
    ]"
>

    <div class="app">

        <x-layout.sidebar
            department="Admin"
            subtitle="Administration Module"
            icon="fa-user-shield"
            :items="[
                [
                    'label' => 'Dashboard',
                    'route' => 'admin.dashboard',
                    'icon' => 'fa-table-cells-large'
                ],

                [
                    'label' => 'User Management',
                    'icon' => 'fa-users',
                    'children' => [
                        [
                            'label' => 'Users',
                            'route' => 'admin.users',
                            'icon' => 'fa-user'
                        ],
                        [
                            'label' => 'Roles & Permissions',
                            'route' => 'admin.roles-permissions',
                            'icon' => 'fa-user-lock'
                        ],
                        [
                            'label' => 'Account Requests',
                            'route' => 'admin.account-requests',
                            'icon' => 'fa-user-clock'
                        ],
                    ]
                ],

                [
                    'label' => 'System Monitoring',
                    'icon' => 'fa-desktop',
                    'children' => [
                        [
                            'label' => 'Activity Logs',
                            'route' => 'admin.activity-logs',
                            'icon' => 'fa-clock-rotate-left'
                        ],
                        [
                            'label' => 'Notifications',
                            'route' => 'admin.notifications',
                            'icon' => 'fa-bell'
                        ],
                    ]
                ],

                [
                    'label' => 'Data Management',
                    'icon' => 'fa-database',
                    'children' => [
                        [
                            'label' => 'Batch File Processing',
                            'route' => 'admin.batch-file-processing',
                            'icon' => 'fa-file-import'
                        ],
                        [
                            'label' => 'Import / Export',
                            'route' => 'admin.import-export',
                            'icon' => 'fa-right-left'
                        ],
                        [
                            'label' => 'Data History',
                            'route' => 'admin.data-history',
                            'icon' => 'fa-clock-rotate-left'
                        ],
                    ]
                ],

                [
                    'label' => 'Analytics',
                    'icon' => 'fa-chart-line',
                    'children' => [
                        [
                            'label' => 'Overview',
                            'route' => 'analytics.overview',
                            'icon' => 'fa-chart-pie'
                        ],
                        [
                            'label' => 'Fleet & Trip',
                            'route' => 'analytics.fleet-trip',
                            'icon' => 'fa-route'
                        ],
                        [
                            'label' => 'Fuel',
                            'route' => 'analytics.fuel',
                            'icon' => 'fa-gas-pump'
                        ],
                        [
                            'label' => 'Bus Health',
                            'route' => 'analytics.bus-health',
                            'icon' => 'fa-heart-pulse'
                        ],
                        [
                            'label' => 'Inventory',
                            'route' => 'analytics.inventory',
                            'icon' => 'fa-boxes-stacked'
                        ],
                        [
                            'label' => 'Recommendations',
                            'route' => 'analytics.recommendations',
                            'icon' => 'fa-lightbulb'
                        ],
                    ]
                ],

                [
                    'label' => 'Settings',
                    'icon' => 'fa-gear',
                    'children' => [
                        [
                            'label' => 'General Settings',
                            'route' => 'admin.settings.general',
                            'icon' => 'fa-sliders'
                        ],
                        [
                            'label' => 'Notification Settings',
                            'route' => 'admin.settings.notifications',
                            'icon' => 'fa-bell'
                        ],
                        [
                            'label' => 'Security Settings',
                            'route' => 'admin.settings.security',
                            'icon' => 'fa-shield-halved'
                        ],
                    ]
                ],
            ]"
        />

        <main class="main import-export-page">

            <x-layout.topbar
                title="Import / Export"
                subtitle="Import external records and export existing FROMS data for reporting, audit, and backup"
                notification-count="6"
            />

            {{-- =====================================================
                SUMMARY CARDS
            ====================================================== --}}
            <section class="stats-grid import-export-stats">

                <x-ui.summary-card
                    label="Imports This Month"
                    value="12"
                    small="Legacy and external files"
                    icon="fa-file-arrow-up"
                    color="blue"
                />

                <x-ui.summary-card
                    label="Exports This Month"
                    value="8"
                    small="Generated from FROMS"
                    icon="fa-file-arrow-down"
                    color="green"
                />

                <x-ui.summary-card
                    label="Imported Records"
                    value="1,426"
                    small="Added to the database"
                    icon="fa-database"
                    color="yellow"
                />

                <x-ui.summary-card
                    label="Files Requiring Review"
                    value="2"
                    small="Invalid or failed imports"
                    icon="fa-triangle-exclamation"
                    color="red"
                />

            </section>


            {{-- =====================================================
                FLOW GUIDE
            ====================================================== --}}
            <section class="data-flow-guide">

                <article class="flow-card">

                    <div class="flow-icon import">
                        <i class="fa-solid fa-file-import"></i>
                    </div>

                    <div>
                        <span class="flow-label">IMPORT FLOW</span>

                        <h2>External File to FROMS</h2>

                        <p>
                            Use Import for legacy, historical, offline, or external records
                            that are not yet stored in the system.
                        </p>

                        <div class="flow-steps">
                            <span>External File</span>
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

                        <p>
                            Use Export to generate Excel, CSV, or PDF files from records
                            already stored inside FROMS.
                        </p>

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


            {{-- =====================================================
                IMPORT / EXPORT PANELS
            ====================================================== --}}
            <section class="transfer-grid">

                {{-- =================================================
                    IMPORT
                ================================================== --}}
                <article class="transfer-card">

                    <div class="transfer-card-header">

                        <div class="transfer-icon import">
                            <i class="fa-solid fa-file-arrow-up"></i>
                        </div>

                        <div>
                            <h2>Import External Records</h2>

                            <p>
                                Add legacy or external structured records to the FROMS database.
                            </p>
                        </div>

                    </div>


                    <div class="transfer-form">

                        <div class="form-group">

                            <label for="importModule">
                                Target Module
                            </label>

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

                            <label for="importDataType">
                                Data Type
                            </label>

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
                                <strong>When should Import be used?</strong>

                                <p>
                                    Import only records that are not yet available in FROMS,
                                    such as old Excel files, historical records, offline records,
                                    or data from another system.
                                </p>
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

                            <p>
                                Drag and drop a file here or browse from your computer.
                            </p>

                            <span>
                                Supported formats: CSV, XLS, XLSX, TXT
                            </span>

                            <label
                                for="importFile"
                                class="choose-file-btn"
                            >
                                <i class="fa-solid fa-folder-open"></i>
                                Choose File
                            </label>

                            <div
                                id="selectedFileName"
                                class="selected-file-name"
                            >
                                No file selected
                            </div>

                        </div>


                        <div class="validation-preview">

                            <div class="validation-header">

                                <div>
                                    <h3>Import Validation</h3>

                                    <p>
                                        File records will be checked before they are saved.
                                    </p>
                                </div>

                                <span class="validation-status waiting">
                                    Waiting for file
                                </span>

                            </div>

                            <div class="validation-grid">

                                <div class="validation-item">
                                    <span>Total Rows</span>
                                    <strong>—</strong>
                                </div>

                                <div class="validation-item">
                                    <span>Valid Records</span>
                                    <strong>—</strong>
                                </div>

                                <div class="validation-item">
                                    <span>Invalid Records</span>
                                    <strong>—</strong>
                                </div>

                            </div>

                        </div>


                        <button
                            type="button"
                            class="primary-transfer-btn"
                        >
                            <i class="fa-solid fa-magnifying-glass"></i>
                            Validate Import File
                        </button>

                    </div>

                </article>


                {{-- =================================================
                    EXPORT
                ================================================== --}}
                <article class="transfer-card">

                    <div class="transfer-card-header">

                        <div class="transfer-icon export">
                            <i class="fa-solid fa-file-arrow-down"></i>
                        </div>

                        <div>
                            <h2>Export FROMS Records</h2>

                            <p>
                                Generate a downloadable report from data already stored in FROMS.
                            </p>
                        </div>

                    </div>


                    <div class="transfer-form">

                        <div class="form-group">

                            <label for="exportModule">
                                Source Module
                            </label>

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

                            <label for="exportDataType">
                                Record Type
                            </label>

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

                                <label for="dateFrom">
                                    Date From
                                </label>

                                <input
                                    type="date"
                                    id="dateFrom"
                                >

                            </div>

                            <div class="form-group">

                                <label for="dateTo">
                                    Date To
                                </label>

                                <input
                                    type="date"
                                    id="dateTo"
                                >

                            </div>

                        </div>


                        <div class="form-group">

                            <label for="exportFormat">
                                Export Format
                            </label>

                            <select id="exportFormat">
                                <option>Excel (.xlsx)</option>
                                <option>CSV (.csv)</option>
                                <option>PDF (.pdf)</option>
                            </select>

                        </div>


                        <div class="export-options">

                            <p class="options-title">
                                Include in Export
                            </p>

                            <label class="option-item">

                                <input
                                    type="checkbox"
                                    checked
                                >

                                <span>
                                    Complete record details
                                </span>

                            </label>

                            <label class="option-item">

                                <input
                                    type="checkbox"
                                    checked
                                >

                                <span>
                                    Status information
                                </span>

                            </label>

                            <label class="option-item">

                                <input
                                    type="checkbox"
                                >

                                <span>
                                    Archived records
                                </span>

                            </label>

                            <label class="option-item">

                                <input
                                    type="checkbox"
                                >

                                <span>
                                    Summary report
                                </span>

                            </label>

                        </div>


                        <div class="export-source-preview">

                            <div class="database-icon">
                                <i class="fa-solid fa-database"></i>
                            </div>

                            <div>
                                <strong>
                                    Data Source
                                </strong>

                                <p>
                                    The exported file will be generated directly from records
                                    currently saved in the FROMS database.
                                </p>
                            </div>

                        </div>


                        <button
                            type="button"
                            class="primary-transfer-btn export-btn"
                        >
                            <i class="fa-solid fa-download"></i>
                            Generate Export File
                        </button>

                    </div>

                </article>

            </section>


            {{-- =====================================================
                IMPORTANT SEPARATION
            ====================================================== --}}
            <section class="batch-processing-note">

                <div class="batch-note-icon">
                    <i class="fa-solid fa-route"></i>
                </div>

                <div>

                    <h2>Looking for GPS or Raw Trip File Processing?</h2>

                    <p>
                        GPS, raw trip, and unstructured operational files should be uploaded
                        through Batch File Processing because they require extraction,
                        parsing, validation, and conversion into structured trip records.
                    </p>

                </div>

                <a
                    href="{{ route('admin.batch-file-processing') }}"
                    class="batch-processing-link"
                >
                    Go to Batch File Processing

                    <i class="fa-solid fa-arrow-right"></i>
                </a>

            </section>


            {{-- =====================================================
                RECENT IMPORT / EXPORT ACTIVITY
            ====================================================== --}}
            <section class="table-card recent-transfer-card">

                <div class="section-header">

                    <div>
                        <h2>Recent Import / Export Activity</h2>

                        <p>
                            Review recently imported external records and generated exports.
                        </p>
                    </div>

                    <span class="record-count">
                        6 Recent
                    </span>

                </div>


                @php
                    $activities = [
                        [
                            'file' => 'inventory_legacy_2025.xlsx',
                            'type' => 'Import',
                            'module' => 'Warehouse',
                            'source' => 'External File',
                            'records' => '126',
                            'status' => 'Completed',
                            'date' => 'Jul 25, 2026',
                            'time' => '8:14 PM',
                        ],
                        [
                            'file' => 'bus_master_archive.xlsx',
                            'type' => 'Import',
                            'module' => 'Operation',
                            'source' => 'Legacy Records',
                            'records' => '18',
                            'status' => 'Completed',
                            'date' => 'Jul 25, 2026',
                            'time' => '5:48 PM',
                        ],
                        [
                            'file' => 'fuel_report_july_2026.pdf',
                            'type' => 'Export',
                            'module' => 'Maintenance',
                            'source' => 'FROMS Database',
                            'records' => '74',
                            'status' => 'Completed',
                            'date' => 'Jul 25, 2026',
                            'time' => '3:22 PM',
                        ],
                        [
                            'file' => 'driver_historical_records.csv',
                            'type' => 'Import',
                            'module' => 'Operation',
                            'source' => 'External File',
                            'records' => '34',
                            'status' => 'Completed',
                            'date' => 'Jul 24, 2026',
                            'time' => '4:08 PM',
                        ],
                        [
                            'file' => 'purchase_orders_july.xlsx',
                            'type' => 'Export',
                            'module' => 'Purchase',
                            'source' => 'FROMS Database',
                            'records' => '42',
                            'status' => 'Completed',
                            'date' => 'Jul 24, 2026',
                            'time' => '1:15 PM',
                        ],
                        [
                            'file' => 'old_inventory_update.csv',
                            'type' => 'Import',
                            'module' => 'Warehouse',
                            'source' => 'External File',
                            'records' => '0',
                            'status' => 'Failed',
                            'date' => 'Jul 23, 2026',
                            'time' => '10:42 AM',
                        ],
                    ];
                @endphp


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

                            @foreach($activities as $activity)

                                @php
                                    $moduleClass = strtolower($activity['module']);
                                    $typeClass = strtolower($activity['type']);
                                    $statusClass = strtolower($activity['status']);
                                @endphp

                                <tr>

                                    <td>

                                        <div class="file-cell">

                                            <div class="file-icon {{ $typeClass }}">

                                                <i class="fa-solid {{ $activity['type'] === 'Import'
                                                    ? 'fa-file-arrow-up'
                                                    : 'fa-file-arrow-down' }}"
                                                ></i>

                                            </div>

                                            <strong>
                                                {{ $activity['file'] }}
                                            </strong>

                                        </div>

                                    </td>

                                    <td>

                                        <span class="transfer-type {{ $typeClass }}">
                                            {{ $activity['type'] }}
                                        </span>

                                    </td>

                                    <td>

                                        <span class="module-badge {{ $moduleClass }}">
                                            {{ $activity['module'] }}
                                        </span>

                                    </td>

                                    <td>

                                        <span class="source-badge">
                                            {{ $activity['source'] }}
                                        </span>

                                    </td>

                                    <td>
                                        {{ $activity['records'] }}
                                    </td>

                                    <td>

                                        <span class="status-badge {{ $statusClass }}">
                                            {{ $activity['status'] }}
                                        </span>

                                    </td>

                                    <td>

                                        <div class="date-time-cell">

                                            <span class="date-value">
                                                {{ $activity['date'] }}
                                            </span>

                                            <span class="time-value">
                                                {{ $activity['time'] }}
                                            </span>

                                        </div>

                                    </td>

                                    <td>

                                        <div class="actions">

                                            <button
                                                type="button"
                                                class="action-btn view"
                                                title="View Details"
                                            >
                                                <i class="fa-solid fa-eye"></i>
                                            </button>

                                        </div>

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>


                <div class="table-footer">

                    <p>
                        Showing 1 to 6 of 6 recent activities
                    </p>

                    <div class="pagination">

                        <button
                            type="button"
                            class="page-btn disabled"
                            disabled
                        >
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>

                        <span class="page-number">
                            1
                        </span>

                        <button
                            type="button"
                            class="page-btn disabled"
                            disabled
                        >
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>

                    </div>

                </div>

            </section>

        </main>

    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const fileInput = document.getElementById('importFile');
            const selectedFileName = document.getElementById('selectedFileName');

            if (fileInput && selectedFileName) {
                fileInput.addEventListener('change', function () {
                    selectedFileName.textContent = fileInput.files.length
                        ? fileInput.files[0].name
                        : 'No file selected';
                });
            }
        });
    </script>

</x-layout.app>