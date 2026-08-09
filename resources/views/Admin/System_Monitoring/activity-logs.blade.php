<x-layout.app
    title="FROMS - Activity Logs"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/System_Monitoring/activity-logs.css',
        'resources/js/Main-js/sidebar.js',
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

        <main class="main activity-logs-page">

            <x-layout.topbar
                title="Activity Logs"
                subtitle="Monitor important user and system activities across FROMS"
                notification-count="6"
            />

            <section class="stats-grid activity-stats-grid">
                <x-ui.summary-card label="Activities Today" value="42" small="Recorded system events" icon="fa-clock-rotate-left" color="blue" />
                <x-ui.summary-card label="User Actions" value="31" small="Account-based activities" icon="fa-user-check" color="green" />
                <x-ui.summary-card label="System Events" value="8" small="Automated system activities" icon="fa-gears" color="yellow" />
                <x-ui.summary-card label="Security Events" value="3" small="Requires monitoring" icon="fa-shield-halved" color="red" />
            </section>

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

            <section class="table-card activity-table-card">
                <div class="section-header">
                    <div><h2>System Activity History</h2><p>Review important user actions and system events recorded across FROMS.</p></div>
                    <span class="activity-count">42 Activities</span>
                </div>

                <div class="activity-toolbar">
                    <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="text" placeholder="Search user, activity, reference..."></div>
                    <div class="filter-group"><select><option>All Modules</option><option>Admin</option><option>Maintenance</option><option>Warehouse</option><option>Purchase</option><option>Operation</option></select></div>
                    <div class="filter-group"><select><option>All Events</option><option>Created</option><option>Updated</option><option>Deleted</option><option>Approval</option><option>Login</option></select></div>
                    <div class="filter-group"><select><option>Today</option><option>This Week</option><option>This Month</option><option>All Time</option></select></div>
                </div>

                <div class="table-wrap">
                    <table class="activity-logs-table">
                        <thead><tr><th>User</th><th>Activity</th><th>Module</th><th>Reference</th><th>Event Type</th><th>Date & Time</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach($logs as $index => $log)
                                @php
                                    $moduleClass = strtolower($log['module']);
                                    $typeClass = match($log['type']) {'Created' => 'created','Updated' => 'updated','Deleted' => 'deleted','Approval' => 'approval','Login' => 'login',default => 'updated'};
                                @endphp
                                <tr>
                                    <td><div class="user-cell"><div class="user-avatar">{{ $log['initials'] }}</div><div><strong>{{ $log['user'] }}</strong><span>{{ $log['role'] }}</span></div></div></td>
                                    <td><div class="activity-cell"><strong>{{ $log['activity'] }}</strong></div></td>
                                    <td><span class="module-badge {{ $moduleClass }}">{{ $log['module'] }}</span></td>
                                    <td><span class="reference-code">{{ $log['reference'] }}</span></td>
                                    <td><span class="event-badge {{ $typeClass }}">{{ $log['type'] }}</span></td>
                                    <td><div class="date-time-cell"><span class="date-value">{{ $log['date'] }}</span><span class="time-value">{{ $log['time'] }}</span></div></td>
                                    <td><div class="actions"><button type="button" class="action-btn view open-log-modal" title="View Activity Details" data-user="{{ $log['user'] }}" data-role="{{ $log['role'] }}" data-activity="{{ $log['activity'] }}" data-module="{{ $log['module'] }}" data-reference="{{ $log['reference'] }}" data-type="{{ $log['type'] }}" data-date="{{ $log['date'] }}" data-time="{{ $log['time'] }}" data-details="{{ $log['details'] }}" data-ip="{{ $log['ip'] }}" data-device="{{ $log['device'] }}"><i class="fa-solid fa-eye"></i></button></div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="table-footer"><p>Showing 1 to 6 of 42 activities</p><div class="pagination"><button type="button" class="page-btn disabled" disabled><i class="fa-solid fa-chevron-left"></i></button><span class="page-number">1</span><button type="button" class="page-btn"><i class="fa-solid fa-chevron-right"></i></button></div></div>
            </section>
        </main>
    </div>

    <div id="activityDetailsModal" class="activity-modal-overlay">
        <div class="activity-modal">
            <div class="activity-modal-header"><div class="modal-heading"><div class="modal-icon"><i class="fa-solid fa-clock-rotate-left"></i></div><div><h2>Activity Details</h2><p>Complete information about the selected system activity.</p></div></div><button type="button" id="closeActivityModal" class="modal-close-btn">&times;</button></div>
            <div class="activity-modal-body">
                <div class="modal-info-grid"><div class="modal-info-item"><span>User</span><strong id="modalUser">—</strong></div><div class="modal-info-item"><span>Role</span><strong id="modalRole">—</strong></div><div class="modal-info-item"><span>Module</span><strong id="modalModule">—</strong></div><div class="modal-info-item"><span>Event Type</span><strong id="modalType">—</strong></div><div class="modal-info-item"><span>Reference</span><strong id="modalReference">—</strong></div><div class="modal-info-item"><span>Date & Time</span><strong id="modalDateTime">—</strong></div></div>
                <div class="modal-detail-block"><span>Activity</span><strong id="modalActivity">—</strong></div>
                <div class="modal-detail-block"><span>Details</span><p id="modalDetails">—</p></div>
                <div class="security-info-grid"><div class="security-info-item"><div class="security-icon"><i class="fa-solid fa-network-wired"></i></div><div><span>IP Address</span><strong id="modalIp">—</strong></div></div><div class="security-info-item"><div class="security-icon"><i class="fa-solid fa-laptop"></i></div><div><span>Device / Browser</span><strong id="modalDevice">—</strong></div></div></div>
            </div>
            <div class="activity-modal-footer"><button type="button" id="closeActivityModalFooter" class="modal-done-btn">Close</button></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('activityDetailsModal');
            const closeButton = document.getElementById('closeActivityModal');
            const closeFooterButton = document.getElementById('closeActivityModalFooter');
            const openButtons = document.querySelectorAll('.open-log-modal');
            function openModal(button) {
                document.getElementById('modalUser').textContent = button.dataset.user || '—';
                document.getElementById('modalRole').textContent = button.dataset.role || '—';
                document.getElementById('modalModule').textContent = button.dataset.module || '—';
                document.getElementById('modalType').textContent = button.dataset.type || '—';
                document.getElementById('modalReference').textContent = button.dataset.reference || '—';
                document.getElementById('modalDateTime').textContent = `${button.dataset.date || '—'} ${button.dataset.time || ''}`;
                document.getElementById('modalActivity').textContent = button.dataset.activity || '—';
                document.getElementById('modalDetails').textContent = button.dataset.details || '—';
                document.getElementById('modalIp').textContent = button.dataset.ip || '—';
                document.getElementById('modalDevice').textContent = button.dataset.device || '—';
                modal.classList.add('show');
                document.body.classList.add('activity-modal-open');
            }
            function closeModal() { modal.classList.remove('show'); document.body.classList.remove('activity-modal-open'); }
            openButtons.forEach(function (button) { button.addEventListener('click', function () { openModal(button); }); });
            closeButton.addEventListener('click', closeModal);
            closeFooterButton.addEventListener('click', closeModal);
            modal.addEventListener('click', function (event) { if (event.target === modal) { closeModal(); } });
            document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && modal.classList.contains('show')) { closeModal(); } });
        });
    </script>
</x-layout.app>