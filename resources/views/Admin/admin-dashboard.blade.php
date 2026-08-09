<x-layout.app
    title="FROMS - Admin Dashboard"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/admin-dashboard.css',
        'resources/js/Admin/admin-dashboard.js'
    ]"
>
    <div class="app admin-dashboard-page">
        <x-layout.sidebar
            department="Admin"
            subtitle="Administration Module"
            icon="fa-user-shield"
            :items="[
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'fa-table-cells-large'],
                [
                    'label' => 'User Management',
                    'icon' => 'fa-users',
                    'children' => [
                        ['label' => 'Users', 'route' => 'admin.users', 'icon' => 'fa-user'],
                        ['label' => 'Roles & Permissions', 'route' => 'admin.roles-permissions', 'icon' => 'fa-user-lock'],
                        ['label' => 'Account Requests', 'route' => 'admin.account-requests', 'icon' => 'fa-user-clock'],
                    ],
                ],
                [
                    'label' => 'System Monitoring',
                    'icon' => 'fa-desktop',
                    'children' => [
                        ['label' => 'Activity Logs', 'route' => 'admin.activity-logs', 'icon' => 'fa-clock-rotate-left'],
                        ['label' => 'Notifications', 'route' => 'admin.notifications', 'icon' => 'fa-bell'],
                    ],
                ],
                [
                    'label' => 'Data Management',
                    'icon' => 'fa-database',
                    'children' => [
                        ['label' => 'Batch File Processing', 'route' => 'admin.batch-file-processing', 'icon' => 'fa-file-import'],
                        ['label' => 'Import / Export', 'route' => 'admin.import-export', 'icon' => 'fa-right-left'],
                        ['label' => 'Data History', 'route' => 'admin.data-history', 'icon' => 'fa-clock-rotate-left'],
                    ],
                ],
                [
                    'label' => 'Analytics',
                    'icon' => 'fa-chart-line',
                    'children' => [
                        ['label' => 'Overview', 'route' => 'analytics.overview', 'icon' => 'fa-chart-pie'],
                        ['label' => 'Fleet & Trip', 'route' => 'analytics.fleet-trip', 'icon' => 'fa-route'],
                        ['label' => 'Fuel', 'route' => 'analytics.fuel', 'icon' => 'fa-gas-pump'],
                        ['label' => 'Bus Health', 'route' => 'analytics.bus-health', 'icon' => 'fa-heart-pulse'],
                        ['label' => 'Inventory', 'route' => 'analytics.inventory', 'icon' => 'fa-boxes-stacked'],
                        ['label' => 'Recommendations', 'route' => 'analytics.recommendations', 'icon' => 'fa-lightbulb'],
                    ],
                ],
                [
                    'label' => 'Settings',
                    'icon' => 'fa-gear',
                    'children' => [
                        ['label' => 'General Settings', 'route' => 'admin.settings.general', 'icon' => 'fa-sliders'],
                        ['label' => 'Notification Settings', 'route' => 'admin.settings.notifications', 'icon' => 'fa-bell'],
                        ['label' => 'Security Settings', 'route' => 'admin.settings.security', 'icon' => 'fa-shield-halved'],
                    ],
                ],
            ]"
        />

        <main class="main admin-dashboard-main">
            <x-layout.topbar
                title="Admin Dashboard"
                subtitle="Overview of system operations, department activity, and access management"
                notification-count="6"
            />

            <section class="admin-kpi-grid">
                <article class="admin-kpi-card">
                    <span class="admin-kpi-icon blue"><i class="fa-solid fa-truck"></i></span>
                    <div>
                        <span class="admin-kpi-label">Maintenance</span>
                        <strong>{{ number_format($departmentMetrics['maintenance'] ?? 0) }}</strong>
                        <small>Active job orders</small>
                    </div>
                </article>
                <article class="admin-kpi-card">
                    <span class="admin-kpi-icon green"><i class="fa-solid fa-warehouse"></i></span>
                    <div>
                        <span class="admin-kpi-label">Warehouse</span>
                        <strong>{{ number_format($departmentMetrics['warehouse'] ?? 0) }}</strong>
                        <small>Low / critical stock items</small>
                    </div>
                </article>
                <article class="admin-kpi-card">
                    <span class="admin-kpi-icon orange"><i class="fa-solid fa-cart-shopping"></i></span>
                    <div>
                        <span class="admin-kpi-label">Purchase</span>
                        <strong>{{ number_format($departmentMetrics['purchase'] ?? 0) }}</strong>
                        <small>Active purchase orders</small>
                    </div>
                </article>
                <article class="admin-kpi-card">
                    <span class="admin-kpi-icon purple"><i class="fa-solid fa-users-gear"></i></span>
                    <div>
                        <span class="admin-kpi-label">Operations</span>
                        <strong>{{ number_format($departmentMetrics['operations'] ?? 0) }}</strong>
                        <small>Attendance records today</small>
                    </div>
                </article>
            </section>

            <section class="admin-chart-grid">
                <article class="admin-panel admin-chart-card">
                    <div class="admin-panel-header">
                        <div><h2>Department Distribution</h2><p>Share of current records across core departments.</p></div>
                    </div>
                    <div class="chart-box donut-chart-box"><canvas id="departmentDistributionChart"></canvas></div>
                </article>
                <article class="admin-panel admin-chart-card">
                    <div class="admin-panel-header">
                        <div><h2>Monthly Activity Overview</h2><p>Requests and operational records created in the last 6 months.</p></div>
                        <span class="chart-period">Last 6 Months</span>
                    </div>
                    <div class="chart-box"><canvas id="monthlyActivityChart"></canvas></div>
                </article>
                <article class="admin-panel admin-chart-card">
                    <div class="admin-panel-header">
                        <div><h2>System Activity</h2><p>Combined activity across the last 7 days.</p></div>
                        <span class="chart-period">Last 7 Days</span>
                    </div>
                    <div class="chart-box"><canvas id="systemActivityChart"></canvas></div>
                </article>
            </section>

            <section class="admin-mid-grid">
                <article class="admin-panel">
                    <div class="admin-panel-header">
                        <div><h2>Department Access</h2><p>Quick access to department modules and admin tools.</p></div>
                    </div>
                    <div class="admin-access-grid">
                        <a href="{{ route('maintenance-dashboard') }}" class="admin-access-card">
                            <span class="admin-access-icon blue"><i class="fa-solid fa-truck"></i></span>
                            <div><strong>Maintenance</strong><small>Job orders, PMS and repairs</small></div><i class="fa-solid fa-chevron-right"></i>
                        </a>
                        <a href="{{ route('inventory') }}" class="admin-access-card">
                            <span class="admin-access-icon green"><i class="fa-solid fa-warehouse"></i></span>
                            <div><strong>Warehouse</strong><small>Inventory and stock movement</small></div><i class="fa-solid fa-chevron-right"></i>
                        </a>
                        <a href="{{ route('purchase-orders') }}" class="admin-access-card">
                            <span class="admin-access-icon orange"><i class="fa-solid fa-cart-shopping"></i></span>
                            <div><strong>Purchase</strong><small>Requests and purchase orders</small></div><i class="fa-solid fa-chevron-right"></i>
                        </a>
                        <a href="{{ route('dashboard-operation') }}" class="admin-access-card">
                            <span class="admin-access-icon purple"><i class="fa-solid fa-users-gear"></i></span>
                            <div><strong>Operations</strong><small>Attendance and scheduling</small></div><i class="fa-solid fa-chevron-right"></i>
                        </a>
                        <a href="{{ route('admin.batch-file-processing') }}" class="admin-access-card">
                            <span class="admin-access-icon blue"><i class="fa-solid fa-file-arrow-up"></i></span>
                            <div><strong>Batch Processing</strong><small>GPS files and structured records</small></div><i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </div>
                </article>

                <article class="admin-panel recent-activity-panel">
                    <div class="admin-panel-header">
                        <div><h2>Recent Activity</h2><p>Latest system-wide updates.</p></div>
                        <a href="{{ route('admin.activity-logs') }}" class="admin-panel-link">View All</a>
                    </div>
                    <div class="recent-activity-list">
                        @forelse($recentActivity as $activity)
                            <div class="recent-activity-item">
                                <span class="activity-dot"></span>
                                <div><strong>{{ $activity->message ?: 'System activity recorded.' }}</strong><small>{{ $activity->created_at?->diffForHumans() ?? 'Recently' }}</small></div>
                            </div>
                        @empty
                            <x-ui.empty-state icon="fa-clock-rotate-left" title="No recent activity" description="System updates will appear here." />
                        @endforelse
                    </div>
                </article>
            </section>

            <section class="admin-panel admin-users-panel">
                <div class="admin-panel-header">
                    <div><h2>User Management</h2><p>Recent system users and their current access.</p></div>
                    <div class="admin-user-actions">
                        <span class="admin-user-count">{{ number_format($totalUsers ?? 0) }} users</span>
                        <a href="{{ route('admin.users') }}" class="admin-panel-link">View All Users</a>
                        <a href="{{ route('admin.users') }}" class="admin-add-btn"><i class="fa-solid fa-plus"></i>New User</a>
                    </div>
                </div>

                <div class="admin-table-wrap">
                    <table>
                        <thead><tr><th>User</th><th>Department</th><th>Role</th><th>Status</th><th>Last Login</th><th>Access</th></tr></thead>
                        <tbody>
                            @forelse($recentUsers as $user)
                                @php
                                    $initials = collect(preg_split('/\s+/', trim($user->name ?? 'User')))
                                        ->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('');
                                    $isAdmin = strtolower($user->department ?? '') === 'admin';
                                @endphp
                                <tr>
                                    <td><div class="user-cell"><div class="user-avatar">{{ $initials ?: 'U' }}</div><div><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></div></div></td>
                                    <td>{{ $user->department ?: '—' }}</td>
                                    <td>{{ $user->role ? ucwords(str_replace('_', ' ', $user->role)) : '—' }}</td>
                                    <td><x-ui.status-badge :status="$user->status ?: 'Active'" /></td>
                                    <td>
                                        <div class="admin-last-login">
                                            @if($user->last_login_at)
                                                <strong>{{ $user->last_login_at->format('M d, Y') }}</strong><small>{{ $user->last_login_at->format('h:i A') }}</small>
                                            @else
                                                <span>Never</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td><span class="access-badge {{ $isAdmin ? 'full' : 'limited' }}">{{ $isAdmin ? 'Full Access' : 'Department Access' }}</span></td>
                                </tr>
                            @empty
                                <x-ui.empty-row colspan="6" message="No users found." />
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    @php
        $adminDashboardChartData = [
            'distribution' => $departmentDistribution ?? [],
            'monthLabels' => $monthLabels ?? [],
            'monthlyActivity' => $monthlyActivity ?? [],
            'dayLabels' => $dayLabels ?? [],
            'dailyActivity' => $dailyActivity ?? [],
        ];
    @endphp

    <script>
        window.adminDashboardData = @json($adminDashboardChartData);
    </script>
</x-layout.app>
