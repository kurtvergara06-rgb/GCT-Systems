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
        <x-layout.sidebar department="Admin" />

        <main class="main admin-dashboard-main">
            <x-layout.topbar
                title="Admin Dashboard"
                subtitle="Overview of system operations, department activity, and access management"
                notification-count="6"
            />

            <!-- Top KPI Cards: Balanced Modern Layout (NO top lines) -->
            <section class="admin-kpi-grid">
                <a href="{{ route('job-orders') }}" class="admin-kpi-card">
                    <div class="kpi-header-row">
                        <span class="kpi-title">Maintenance</span>
                        <span class="kpi-icon blue"><i class="fa-solid fa-truck"></i></span>
                    </div>
                    <div class="kpi-metric-row">
                        <strong class="kpi-number">{{ number_format($departmentMetrics['maintenance'] ?? 0) }}</strong>
                    </div>
                    <div class="kpi-footer-row">
                        <span class="kpi-subtext">Active job orders</span>
                        <span class="kpi-chip blue">Active</span>
                    </div>
                </a>

                <a href="{{ route('warehouse.dashboard') }}" class="admin-kpi-card">
                    <div class="kpi-header-row">
                        <span class="kpi-title">Warehouse</span>
                        <span class="kpi-icon green"><i class="fa-solid fa-warehouse"></i></span>
                    </div>
                    <div class="kpi-metric-row">
                        <strong class="kpi-number">{{ number_format($departmentMetrics['warehouse'] ?? 0) }}</strong>
                    </div>
                    <div class="kpi-footer-row">
                        <span class="kpi-subtext">Low / critical stock items</span>
                        <span class="kpi-chip green">Inventory</span>
                    </div>
                </a>

                <a href="{{ route('purchase-orders') }}" class="admin-kpi-card">
                    <div class="kpi-header-row">
                        <span class="kpi-title">Purchase</span>
                        <span class="kpi-icon orange"><i class="fa-solid fa-cart-shopping"></i></span>
                    </div>
                    <div class="kpi-metric-row">
                        <strong class="kpi-number">{{ number_format($departmentMetrics['purchase'] ?? 0) }}</strong>
                    </div>
                    <div class="kpi-footer-row">
                        <span class="kpi-subtext">Active purchase orders</span>
                        <span class="kpi-chip orange">Orders</span>
                    </div>
                </a>

                <a href="{{ route('dashboard-operation') }}" class="admin-kpi-card">
                    <div class="kpi-header-row">
                        <span class="kpi-title">Operations</span>
                        <span class="kpi-icon purple"><i class="fa-solid fa-users-gear"></i></span>
                    </div>
                    <div class="kpi-metric-row">
                        <strong class="kpi-number">{{ number_format($departmentMetrics['operations'] ?? 0) }}</strong>
                    </div>
                    <div class="kpi-footer-row">
                        <span class="kpi-subtext">Attendance records today</span>
                        <span class="kpi-chip purple">Roster</span>
                    </div>
                </a>
            </section>

            <!-- Chart Row (NO top lines) -->
            <section class="admin-chart-grid">
                <article class="admin-panel admin-chart-card">
                    <div class="admin-panel-header">
                        <div>
                            <h2>Department Distribution</h2>
                            <p>Share of current records across core departments.</p>
                        </div>
                    </div>
                    <div class="chart-box donut-chart-box"><canvas id="departmentDistributionChart"></canvas></div>
                    <div class="donut-summary-row">
                        <div class="donut-pill blue"><span class="dot"></span> <span>Maint:</span> <strong>{{ number_format($departmentDistribution['Maintenance'] ?? 0) }}</strong></div>
                        <div class="donut-pill green"><span class="dot"></span> <span>Whse:</span> <strong>{{ number_format($departmentDistribution['Warehouse'] ?? 0) }}</strong></div>
                        <div class="donut-pill orange"><span class="dot"></span> <span>Purch:</span> <strong>{{ number_format($departmentDistribution['Purchase'] ?? 0) }}</strong></div>
                        <div class="donut-pill purple"><span class="dot"></span> <span>Ops:</span> <strong>{{ number_format($departmentDistribution['Operations'] ?? 0) }}</strong></div>
                    </div>
                </article>

                <article class="admin-panel admin-chart-card">
                    <div class="admin-panel-header">
                        <div>
                            <h2>Monthly Activity Overview</h2>
                            <p>Requests and operational records created in the last 6 months.</p>
                        </div>
                        <span class="chart-period">Last 6 Months</span>
                    </div>
                    <div class="chart-box"><canvas id="monthlyActivityChart"></canvas></div>
                </article>

                <article class="admin-panel admin-chart-card">
                    <div class="admin-panel-header">
                        <div>
                            <h2>System Activity</h2>
                            <p>Combined activity across the last 7 days.</p>
                        </div>
                        <span class="chart-period">Last 7 Days</span>
                    </div>
                    <div class="chart-box"><canvas id="systemActivityChart"></canvas></div>
                </article>
            </section>

            <!-- Mid Row: Module Gateways & Recent Activity (NO top lines) -->
            <section class="admin-mid-grid">
                <article class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <h2>Admin Module Gateways</h2>
                            <p>Direct governance shortcuts and access control hubs.</p>
                        </div>
                        <span class="system-status-pill">
                            <span class="status-indicator-dot"></span> All Systems Operational
                        </span>
                    </div>
                    <div class="admin-access-grid">
                        <a href="{{ route('admin.users') }}" class="admin-gateway-card">
                            <div class="gateway-header">
                                <span class="gateway-icon blue"><i class="fa-solid fa-users-gear"></i></span>
                                <span class="gateway-badge blue">Accounts</span>
                            </div>
                            <div class="gateway-body">
                                <strong>User Directory</strong>
                                <small>Manage system users, credentials & role assignments</small>
                            </div>
                            <div class="gateway-footer">
                                <span>{{ number_format($totalUsers ?? 0) }} Users</span>
                                <span class="gateway-arrow">Manage <i class="fa-solid fa-arrow-right"></i></span>
                            </div>
                        </a>

                        <a href="{{ route('admin.roles-permissions') }}" class="admin-gateway-card">
                            <div class="gateway-header">
                                <span class="gateway-icon purple"><i class="fa-solid fa-shield-halved"></i></span>
                                <span class="gateway-badge purple">Security</span>
                            </div>
                            <div class="gateway-body">
                                <strong>Roles & Permissions</strong>
                                <small>Configure privilege matrices & department access</small>
                            </div>
                            <div class="gateway-footer">
                                <span>Access Matrix</span>
                                <span class="gateway-arrow">Configure <i class="fa-solid fa-arrow-right"></i></span>
                            </div>
                        </a>

                        <a href="{{ route('admin.activity-logs') }}" class="admin-gateway-card">
                            <div class="gateway-header">
                                <span class="gateway-icon orange"><i class="fa-solid fa-clock-rotate-left"></i></span>
                                <span class="gateway-badge orange">Audit</span>
                            </div>
                            <div class="gateway-body">
                                <strong>Activity Logs</strong>
                                <small>Inspect system audit trails, exports & security events</small>
                            </div>
                            <div class="gateway-footer">
                                <span>Audit Trail</span>
                                <span class="gateway-arrow">Inspect <i class="fa-solid fa-arrow-right"></i></span>
                            </div>
                        </a>

                        <a href="{{ route('admin.batch-file-processing') }}" class="admin-gateway-card">
                            <div class="gateway-header">
                                <span class="gateway-icon green"><i class="fa-solid fa-database"></i></span>
                                <span class="gateway-badge green">Data Hub</span>
                            </div>
                            <div class="gateway-body">
                                <strong>Data Management</strong>
                                <small>Batch file imports, sync and historical records</small>
                            </div>
                            <div class="gateway-footer">
                                <span>Data Hub</span>
                                <span class="gateway-arrow">Open Hub <i class="fa-solid fa-arrow-right"></i></span>
                            </div>
                        </a>
                    </div>
                </article>

                <article class="admin-panel recent-activity-panel">
                    <div class="admin-panel-header">
                        <div>
                            <h2>Recent Activity</h2>
                            <p>Latest system-wide updates.</p>
                        </div>
                        <a href="{{ route('admin.activity-logs') }}" class="admin-panel-link">View All</a>
                    </div>
                    <div class="recent-activity-list">
                        @forelse($recentActivity as $activity)
                            @php
                                $msg = $activity->message ?: 'System activity recorded.';
                                $isWarning = str_contains(strtolower($msg), 'exceeded') 
                                    || str_contains(strtolower($msg), 'duration') 
                                    || str_contains(strtolower($msg), 'critical')
                                    || str_contains(strtolower($msg), 'warning')
                                    || str_contains(strtolower($msg), 'alert');
                                $isInventory = str_contains(strtolower($msg), 'stock') || str_contains(strtolower($msg), 'inventory');
                                $isPurchase = str_contains(strtolower($msg), 'purchase') || str_contains(strtolower($msg), 'po-');
                                $isUser = str_contains(strtolower($msg), 'user') || str_contains(strtolower($msg), 'login') || str_contains(strtolower($msg), 'password');
                                
                                $badgeType = $isWarning ? 'warning' : ($isInventory ? 'inventory' : ($isPurchase ? 'purchase' : ($isUser ? 'user' : 'default')));
                                $badgeIcon = $isWarning ? 'fa-triangle-exclamation' : ($isInventory ? 'fa-box-archive' : ($isPurchase ? 'fa-cart-shopping' : ($isUser ? 'fa-user-shield' : 'fa-bell')));
                                
                                $formattedMsg = preg_replace(
                                    '/([A-Z]{2,3}-\d{4}-\d{4})/',
                                    '<span class="activity-code">$1</span>',
                                    e($msg)
                                );
                            @endphp
                            <div class="recent-activity-item">
                                <span class="activity-icon-bubble {{ $badgeType }}"><i class="fa-solid {{ $badgeIcon }}"></i></span>
                                <div class="activity-content">
                                    <p class="activity-text">{!! $formattedMsg !!}</p>
                                    <span class="activity-time"><i class="fa-regular fa-clock"></i> {{ $activity->created_at?->diffForHumans() ?? 'Recently' }}</span>
                                </div>
                            </div>
                        @empty
                            <x-ui.empty-state icon="fa-clock-rotate-left" title="No recent activity" description="System updates will appear here." />
                        @endforelse
                    </div>
                </article>
            </section>

            <!-- User Management Table (NO top lines) -->
            <section class="admin-panel admin-users-panel">
                <div class="admin-panel-header">
                    <div>
                        <h2>User Management</h2>
                        <p>Recent system users and their current access.</p>
                    </div>
                    <div class="admin-user-actions">
                        <span class="admin-user-count">{{ number_format($totalUsers ?? 0) }} users</span>
                        <a href="{{ route('admin.users') }}" class="admin-panel-link">View All Users</a>
                        <a href="{{ route('admin.users') }}" class="admin-add-btn"><i class="fa-solid fa-plus"></i>New User</a>
                    </div>
                </div>

                <div class="admin-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Department</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Access</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentUsers as $user)
                                @php
                                    $dept = strtolower(trim($user->department ?? ''));
                                    $avatarType = match(true) {
                                        str_contains($dept, 'admin') => 'admin',
                                        str_contains($dept, 'purchase') => 'purchase',
                                        str_contains($dept, 'operation') => 'operations',
                                        str_contains($dept, 'warehouse') => 'warehouse',
                                        str_contains($dept, 'maintenance') => 'maintenance',
                                        default => 'default'
                                    };
                                    $initials = collect(preg_split('/\s+/', trim($user->name ?? 'User')))
                                        ->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('');
                                    $isAdmin = $dept === 'admin';
                                @endphp
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar avatar-{{ $avatarType }}">{{ $initials ?: 'U' }}</div>
                                            <div class="user-info">
                                                <strong>{{ $user->name }}</strong>
                                                <small>{{ $user->email }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="dept-chip dept-{{ $avatarType }}">{{ $user->department ?: '—' }}</span></td>
                                    <td><span class="role-badge">{{ $user->role ? ucwords(str_replace('_', ' ', $user->role)) : '—' }}</span></td>
                                    <td>
                                        <span class="status-indicator active">
                                            <span class="dot"></span> Active
                                        </span>
                                    </td>
                                    <td>
                                        <div class="admin-last-login">
                                            @if($user->last_login_at)
                                                <strong>{{ $user->last_login_at->format('M d, Y') }}</strong>
                                                <small>{{ $user->last_login_at->format('h:i A') }}</small>
                                            @else
                                                <span class="never-badge">Never</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="access-pill {{ $isAdmin ? 'full' : 'limited' }}">
                                            <i class="fa-solid {{ $isAdmin ? 'fa-shield-halved' : 'fa-building-user' }}"></i>
                                            {{ $isAdmin ? 'Full Access' : 'Department Access' }}
                                        </span>
                                    </td>
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
