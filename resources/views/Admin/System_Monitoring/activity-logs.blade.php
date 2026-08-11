<x-layout.app
    title="FROMS - Activity Logs"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/System_Monitoring/activity-logs.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Admin/System_Monitoring/activity-logs.js',
    ]"
>

    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main activity-logs-page">
            <x-layout.topbar
                title="Activity Logs"
                subtitle="Monitor important user and system activities across FROMS"
                notification-count="6"
            />

            <x-ui.ajax-region name="summary" class="stats-grid activity-stats-grid">
                <x-ui.summary-card label="Activities Today" value="42" small="Recorded system events" icon="fa-clock-rotate-left" color="blue" />
                <x-ui.summary-card label="User Actions" value="31" small="Account-based activities" icon="fa-user-check" color="green" />
                <x-ui.summary-card label="System Events" value="8" small="Automated system activities" icon="fa-gears" color="yellow" />
                <x-ui.summary-card label="Security Events" value="3" small="Requires monitoring" icon="fa-shield-halved" color="red" />
            </x-ui.ajax-region>

            @php
                $logs = [
                    ['initials' => 'SA','user' => 'System Admin','role' => 'Admin Head','activity' => 'Updated user account','module' => 'Admin','reference' => 'USR-0012','type' => 'Updated','date' => 'Jul 25, 2026','time' => '9:42 PM','details' => 'Changed the account status of Juan Santos from Pending to Active.','ip' => '192.168.1.12','device' => 'Chrome / Windows 11'],
                    ['initials' => 'MS','user' => 'Marco Salazar','role' => 'Operation Head','activity' => 'Updated shuttle bus record','module' => 'Operation','reference' => 'BUS-012','type' => 'Updated','date' => 'Jul 25, 2026','time' => '8:15 PM','details' => 'Changed the operational status of BUS-012 to Under Maintenance.','ip' => '192.168.1.25','device' => 'Chrome / Windows 11'],
                    ['initials' => 'JM','user' => 'Juan Miguel Santos','role' => 'Maintenance Head','activity' => 'Approved purchase request','module' => 'Maintenance','reference' => 'PR-2026-0021','type' => 'Approval','date' => 'Jul 25, 2026','time' => '6:30 PM','details' => 'Approved the maintenance purchase request for requested replacement parts.','ip' => '192.168.1.31','device' => 'Edge / Windows 11'],
                    ['initials' => 'AC','user' => 'Andrea Cruz','role' => 'Warehouse Staff','activity' => 'Updated inventory item','module' => 'Warehouse','reference' => 'PART-0045','type' => 'Updated','date' => 'Jul 25, 2026','time' => '3:18 PM','details' => 'Updated the stock quantity of Engine Oil Filter in warehouse inventory.','ip' => '192.168.1.41','device' => 'Chrome / Windows 11'],
                    ['initials' => 'RP','user' => 'Rafael Perez','role' => 'Purchase Staff','activity' => 'Created purchase order','module' => 'Purchase','reference' => 'PO-2026-0015','type' => 'Created','date' => 'Jul 25, 2026','time' => '1:06 PM','details' => 'Created a purchase order from an approved maintenance purchase request.','ip' => '192.168.1.52','device' => 'Firefox / Windows 11'],
                    ['initials' => 'SA','user' => 'System Admin','role' => 'Admin Head','activity' => 'User login','module' => 'Admin','reference' => 'LOGIN-0725-01','type' => 'Login','date' => 'Jul 25, 2026','time' => '8:02 AM','details' => 'Successful administrator login to FROMS.','ip' => '192.168.1.12','device' => 'Chrome / Windows 11'],
                ];
            @endphp

            <x-ui.ajax-region name="records" class="table-card activity-table-card">
                <div class="section-header">
                    <div>
                        <h2>System Activity History</h2>
                        <p>Review important user actions and system events recorded across FROMS.</p>
                    </div>
                    <span id="activityResultCount" class="activity-count">{{ count($logs) }} Activities</span>
                </div>

                <x-ui.table-toolbar
                    action="{{ route('admin.activity-logs') }}"
                    class="activity-toolbar"
                    search-placeholder="Search user, activity, reference..."
                    :show-button="false"
                    id="activityFilterForm"
                    data-client-filter="true"
                    data-no-loading
                >
                    <div class="filter-group">
                        <select id="activityModuleFilter" name="module" aria-label="Filter by module">
                            <option value="all">All Modules</option>
                            <option value="Admin">Admin</option>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Warehouse">Warehouse</option>
                            <option value="Purchase">Purchase</option>
                            <option value="Operation">Operation</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <select id="activityEventFilter" name="event" aria-label="Filter by event type">
                            <option value="all">All Events</option>
                            <option value="Created">Created</option>
                            <option value="Updated">Updated</option>
                            <option value="Deleted">Deleted</option>
                            <option value="Approval">Approval</option>
                            <option value="Login">Login</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <select id="activityDateFilter" name="date" aria-label="Filter by date range">
                            <option value="all">All Time</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                        </select>
                    </div>
                </x-ui.table-toolbar>

                <div class="table-wrap activity-table-wrap">
                    <div id="activityTableLoading" class="activity-table-loading" hidden>
                        <x-ui.spinner size="sm" label="Filtering activity logs" />
                        <span>Filtering records...</span>
                    </div>

                    <table id="activityLogsTable" class="activity-logs-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Activity</th>
                                <th>Module</th>
                                <th>Reference</th>
                                <th>Event Type</th>
                                <th>Date & Time</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($logs as $log)
                                @php
                                    $moduleClass = strtolower($log['module']);
                                    $typeClass = match($log['type']) {
                                        'Created' => 'created',
                                        'Updated' => 'updated',
                                        'Deleted' => 'deleted',
                                        'Approval' => 'approval',
                                        'Login' => 'login',
                                        default => 'updated'
                                    };
                                    $searchValue = implode(' ', [
                                        $log['user'],
                                        $log['role'],
                                        $log['activity'],
                                        $log['module'],
                                        $log['reference'],
                                        $log['type'],
                                    ]);
                                @endphp
                                <tr
                                    data-activity-row
                                    data-search="{{ $searchValue }}"
                                    data-module="{{ $log['module'] }}"
                                    data-event="{{ $log['type'] }}"
                                    data-date="{{ $log['date'] }}"
                                >
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar">{{ $log['initials'] }}</div>
                                            <div>
                                                <strong>{{ $log['user'] }}</strong>
                                                <span>{{ $log['role'] }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><div class="activity-cell"><strong>{{ $log['activity'] }}</strong></div></td>
                                    <td><span class="module-badge {{ $moduleClass }}">{{ $log['module'] }}</span></td>
                                    <td><span class="reference-code">{{ $log['reference'] }}</span></td>
                                    <td><span class="event-badge {{ $typeClass }}">{{ $log['type'] }}</span></td>
                                    <td>
                                        <div class="date-time-cell">
                                            <span class="date-value">{{ $log['date'] }}</span>
                                            <span class="time-value">{{ $log['time'] }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button
                                                type="button"
                                                class="action-btn view open-log-modal"
                                                title="View Activity Details"
                                                data-user="{{ $log['user'] }}"
                                                data-role="{{ $log['role'] }}"
                                                data-activity="{{ $log['activity'] }}"
                                                data-module="{{ $log['module'] }}"
                                                data-reference="{{ $log['reference'] }}"
                                                data-type="{{ $log['type'] }}"
                                                data-date="{{ $log['date'] }}"
                                                data-time="{{ $log['time'] }}"
                                                data-details="{{ $log['details'] }}"
                                                data-ip="{{ $log['ip'] }}"
                                                data-device="{{ $log['device'] }}"
                                            >
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach

                            <x-ui.empty-row
                                id="activityEmptyRow"
                                :colspan="7"
                                message="No activities match the selected filters."
                                hidden
                            />
                        </tbody>
                    </table>
                </div>

                <div class="table-footer activity-client-footer">
                    <p id="activityFooterCount">Showing {{ count($logs) }} of {{ count($logs) }} activities</p>
                </div>
            </x-ui.ajax-region>
        </main>
    </div>

    <div id="activityDetailsModal" class="activity-modal-overlay">
        <div class="activity-modal">
            <div class="activity-modal-header">
                <div class="modal-heading">
                    <div class="modal-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <div>
                        <h2>Activity Details</h2>
                        <p>Complete information about the selected system activity.</p>
                    </div>
                </div>
                <button type="button" id="closeActivityModal" class="modal-close-btn">&times;</button>
            </div>

            <div class="activity-modal-body">
                <div class="modal-info-grid">
                    <div class="modal-info-item"><span>User</span><strong id="modalUser">—</strong></div>
                    <div class="modal-info-item"><span>Role</span><strong id="modalRole">—</strong></div>
                    <div class="modal-info-item"><span>Module</span><strong id="modalModule">—</strong></div>
                    <div class="modal-info-item"><span>Event Type</span><strong id="modalType">—</strong></div>
                    <div class="modal-info-item"><span>Reference</span><strong id="modalReference">—</strong></div>
                    <div class="modal-info-item"><span>Date & Time</span><strong id="modalDateTime">—</strong></div>
                </div>

                <div class="modal-detail-block"><span>Activity</span><strong id="modalActivity">—</strong></div>
                <div class="modal-detail-block"><span>Details</span><p id="modalDetails">—</p></div>

                <div class="security-info-grid">
                    <div class="security-info-item">
                        <div class="security-icon"><i class="fa-solid fa-network-wired"></i></div>
                        <div><span>IP Address</span><strong id="modalIp">—</strong></div>
                    </div>
                    <div class="security-info-item">
                        <div class="security-icon"><i class="fa-solid fa-laptop"></i></div>
                        <div><span>Device / Browser</span><strong id="modalDevice">—</strong></div>
                    </div>
                </div>
            </div>

            <div class="activity-modal-footer">
                <button type="button" id="closeActivityModalFooter" class="modal-done-btn">Close</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const search = document.querySelector('#activityFilterForm input[name="search"]');
            if (search) {
                search.id = 'activitySearch';
                search.setAttribute('autocomplete', 'off');
            }
        });
    </script>
</x-layout.app>