<x-layout.app
    title="FROMS - Data History"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/Data_Management/data-history.css',
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

        <main class="main data-history-page">

            <x-layout.topbar
                title="Data History"
                subtitle="Review processed files, imports, exports, and historical data activity"
                notification-count="6"
            />

            {{-- =====================================================
                SUMMARY CARDS
            ====================================================== --}}
            <section class="stats-grid history-stats-grid">

                <x-ui.summary-card
                    label="Total Data Activities"
                    value="28"
                    small="Logged import, export, and batch activities"
                    icon="fa-database"
                    color="blue"
                />

                <x-ui.summary-card
                    label="Successful"
                    value="23"
                    small="Completed operations"
                    icon="fa-circle-check"
                    color="green"
                />

                <x-ui.summary-card
                    label="Processed Files"
                    value="18"
                    small="Imported or processed files"
                    icon="fa-file-circle-check"
                    color="yellow"
                />

                <x-ui.summary-card
                    label="Failed"
                    value="2"
                    small="Requires review"
                    icon="fa-triangle-exclamation"
                    color="red"
                />

            </section>

            @php
                $history = [
                    [
                        'file' => 'gps_trip_records_july.csv',
                        'type' => 'Batch Processing',
                        'module' => 'Operation',
                        'source' => 'GPS Raw Data',
                        'records' => '1,248',
                        'status' => 'Completed',
                        'user' => 'System Admin',
                        'date' => 'Jul 25, 2026',
                        'time' => '9:12 PM',
                    ],
                    [
                        'file' => 'inventory_items.xlsx',
                        'type' => 'Import',
                        'module' => 'Warehouse',
                        'source' => 'External File',
                        'records' => '126',
                        'status' => 'Completed',
                        'user' => 'System Admin',
                        'date' => 'Jul 25, 2026',
                        'time' => '8:14 PM',
                    ],
                    [
                        'file' => 'bus_master_list.xlsx',
                        'type' => 'Import',
                        'module' => 'Operation',
                        'source' => 'Legacy Records',
                        'records' => '18',
                        'status' => 'Completed',
                        'user' => 'System Admin',
                        'date' => 'Jul 25, 2026',
                        'time' => '5:48 PM',
                    ],
                    [
                        'file' => 'driver_records.csv',
                        'type' => 'Import',
                        'module' => 'Operation',
                        'source' => 'External File',
                        'records' => '34',
                        'status' => 'Completed',
                        'user' => 'System Admin',
                        'date' => 'Jul 24, 2026',
                        'time' => '4:08 PM',
                    ],
                    [
                        'file' => 'fuel_report_july.pdf',
                        'type' => 'Export',
                        'module' => 'Maintenance',
                        'source' => 'FROMS Database',
                        'records' => '74',
                        'status' => 'Completed',
                        'user' => 'System Admin',
                        'date' => 'Jul 24, 2026',
                        'time' => '2:26 PM',
                    ],
                    [
                        'file' => 'gps_raw_data_0719.txt',
                        'type' => 'Batch Processing',
                        'module' => 'Operation',
                        'source' => 'GPS Raw Data',
                        'records' => '0',
                        'status' => 'Failed',
                        'user' => 'System Admin',
                        'date' => 'Jul 23, 2026',
                        'time' => '10:42 AM',
                    ],
                ];
            @endphp

            {{-- =====================================================
                HISTORY TABLE
            ====================================================== --}}
            <section class="table-card data-history-card">

                <div class="section-header">

                    <div>
                        <h2>Data Activity History</h2>

                        <p>
                            Track imported files, generated exports, batch processing, and their results.
                        </p>
                    </div>

                    <span class="history-count">
                        28 Records
                    </span>

                </div>

                {{-- =================================================
                    FILTERS
                ================================================== --}}
                <div class="history-toolbar">

                    <div class="search-box">

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            placeholder="Search file, module, source..."
                        >

                    </div>

                    <div class="filter-group">

                        <select>
                            <option>All Types</option>
                            <option>Batch Processing</option>
                            <option>Import</option>
                            <option>Export</option>
                        </select>

                    </div>

                    <div class="filter-group">

                        <select>
                            <option>All Modules</option>
                            <option>Admin</option>
                            <option>Operation</option>
                            <option>Maintenance</option>
                            <option>Warehouse</option>
                            <option>Purchase</option>
                        </select>

                    </div>

                    <div class="filter-group">

                        <select>
                            <option>All Status</option>
                            <option>Completed</option>
                            <option>Failed</option>
                            <option>Processing</option>
                        </select>

                    </div>

                </div>

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

                            @foreach($history as $index => $item)

                                @php
                                    $typeClass = match($item['type']) {
                                        'Import' => 'import',
                                        'Export' => 'export',
                                        'Batch Processing' => 'batch',
                                        default => 'batch',
                                    };

                                    $moduleClass = strtolower($item['module']);
                                    $statusClass = strtolower($item['status']);
                                @endphp

                                <tr>

                                    <td>

                                        <div class="history-file-cell">

                                            <div class="history-file-icon {{ $typeClass }}">

                                                @if($item['type'] === 'Import')
                                                    <i class="fa-solid fa-file-arrow-up"></i>
                                                @elseif($item['type'] === 'Export')
                                                    <i class="fa-solid fa-file-arrow-down"></i>
                                                @else
                                                    <i class="fa-solid fa-file-import"></i>
                                                @endif

                                            </div>

                                            <strong>
                                                {{ $item['file'] }}
                                            </strong>

                                        </div>

                                    </td>

                                    <td>
                                        <span class="history-type {{ $typeClass }}">
                                            {{ $item['type'] }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="module-badge {{ $moduleClass }}">
                                            {{ $item['module'] }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="source-badge">
                                            {{ $item['source'] }}
                                        </span>
                                    </td>

                                    <td>
                                        {{ $item['records'] }}
                                    </td>

                                    <td>
                                        <span class="history-status {{ $statusClass }}">
                                            {{ $item['status'] }}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="processed-by">
                                            <i class="fa-solid fa-user"></i>

                                            <span>
                                                {{ $item['user'] }}
                                            </span>
                                        </div>
                                    </td>

                                    <td>

                                        <div class="date-time-cell">

                                            <span class="date-value">
                                                {{ $item['date'] }}
                                            </span>

                                            <span class="time-value">
                                                {{ $item['time'] }}
                                            </span>

                                        </div>

                                    </td>

                                    <td>

                                        <div class="actions">

                                            <button
                                                type="button"
                                                class="action-btn view open-history-modal"
                                                title="View Details"
                                                data-file="{{ $item['file'] }}"
                                                data-type="{{ $item['type'] }}"
                                                data-module="{{ $item['module'] }}"
                                                data-source="{{ $item['source'] }}"
                                                data-records="{{ $item['records'] }}"
                                                data-status="{{ $item['status'] }}"
                                                data-user="{{ $item['user'] }}"
                                                data-date="{{ $item['date'] }}"
                                                data-time="{{ $item['time'] }}"
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
                        Showing 1 to 6 of 28 records
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
                            class="page-btn"
                        >
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>

                    </div>

                </div>

            </section>

        </main>

    </div>


    {{-- =============================================================
        DETAILS MODAL
    ============================================================== --}}
    <div
        id="historyDetailsModal"
        class="history-modal-overlay"
    >

        <div class="history-modal">

            <div class="history-modal-header">

                <div class="history-modal-title">

                    <div class="history-modal-icon">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>

                    <div>
                        <h2>Data History Details</h2>

                        <p>
                            Complete information about the selected data activity.
                        </p>
                    </div>

                </div>

                <button
                    type="button"
                    id="closeHistoryModal"
                    class="history-modal-close"
                >
                    &times;
                </button>

            </div>

            <div class="history-modal-body">

                <div class="history-modal-grid">

                    <div class="history-detail-item">
                        <span>File</span>
                        <strong id="historyModalFile">—</strong>
                    </div>

                    <div class="history-detail-item">
                        <span>Activity Type</span>
                        <strong id="historyModalType">—</strong>
                    </div>

                    <div class="history-detail-item">
                        <span>Module</span>
                        <strong id="historyModalModule">—</strong>
                    </div>

                    <div class="history-detail-item">
                        <span>Data Source</span>
                        <strong id="historyModalSource">—</strong>
                    </div>

                    <div class="history-detail-item">
                        <span>Records</span>
                        <strong id="historyModalRecords">—</strong>
                    </div>

                    <div class="history-detail-item">
                        <span>Status</span>
                        <strong id="historyModalStatus">—</strong>
                    </div>

                    <div class="history-detail-item">
                        <span>Processed By</span>
                        <strong id="historyModalUser">—</strong>
                    </div>

                    <div class="history-detail-item">
                        <span>Date & Time</span>
                        <strong id="historyModalDateTime">—</strong>
                    </div>

                </div>

            </div>

            <div class="history-modal-footer">

                <button
                    type="button"
                    id="closeHistoryModalFooter"
                    class="history-close-btn"
                >
                    Close
                </button>

            </div>

        </div>

    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const modal = document.getElementById('historyDetailsModal');
            const buttons = document.querySelectorAll('.open-history-modal');

            const closeTop = document.getElementById('closeHistoryModal');
            const closeFooter = document.getElementById('closeHistoryModalFooter');

            function openModal(button) {

                document.getElementById('historyModalFile').textContent =
                    button.dataset.file || '—';

                document.getElementById('historyModalType').textContent =
                    button.dataset.type || '—';

                document.getElementById('historyModalModule').textContent =
                    button.dataset.module || '—';

                document.getElementById('historyModalSource').textContent =
                    button.dataset.source || '—';

                document.getElementById('historyModalRecords').textContent =
                    button.dataset.records || '—';

                document.getElementById('historyModalStatus').textContent =
                    button.dataset.status || '—';

                document.getElementById('historyModalUser').textContent =
                    button.dataset.user || '—';

                document.getElementById('historyModalDateTime').textContent =
                    `${button.dataset.date || '—'} ${button.dataset.time || ''}`;

                modal.classList.add('show');

                document.body.classList.add('history-modal-open');
            }

            function closeModal() {

                modal.classList.remove('show');

                document.body.classList.remove('history-modal-open');
            }

            buttons.forEach(function (button) {

                button.addEventListener('click', function () {
                    openModal(button);
                });

            });

            closeTop.addEventListener('click', closeModal);
            closeFooter.addEventListener('click', closeModal);

            modal.addEventListener('click', function (event) {

                if (event.target === modal) {
                    closeModal();
                }

            });

            document.addEventListener('keydown', function (event) {

                if (
                    event.key === 'Escape' &&
                    modal.classList.contains('show')
                ) {
                    closeModal();
                }

            });

        });
    </script>

</x-layout.app>