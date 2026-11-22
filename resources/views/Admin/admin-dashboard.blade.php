<x-layout.app
    title="FROMS - Admin Dashboard"
    :assets="[
        'resources/css/Admin/admin-dashboard.css',
        'resources/js/Admin/admin-dashboard.js'
    ]"
>
    <div class="app admin-dashboard-page">
        <x-layout.sidebar department="Admin" />

        <main class="main admin-dashboard-main">
            <x-layout.topbar
                title="Admin Dashboard"
                subtitle="Central Governance, Security Telemetry, Data Pipelines, and Multi-Department Operations"
            />

            <!-- 1. Executive Command Center & Welcome Header Strip -->
            <section class="admin-welcome-banner" aria-label="Administration Overview & Quick Actions">
                <div class="welcome-copy">
                    <div class="welcome-badge-row">
                        <span class="admin-role-badge">
                            <i class="fa-solid fa-shield-halved"></i>
                            <span>{{ auth()->user()->role === 'head' ? 'System Administrator' : 'Administrator' }}</span>
                        </span>
                        <span class="sys-health-pill">
                            <span class="live-pulse-dot"></span>
                            <span>5 Department Modules</span>
                        </span>
                        <span class="sys-time-badge">
                            <i class="fa-regular fa-clock"></i>
                            <span>{{ now(config('app.business_timezone', 'Asia/Manila'))->format('M d, Y • h:i A') }}</span>
                        </span>
                    </div>
                    <h1 class="welcome-title">
                        Welcome back, {{ auth()->user()->name ?? 'Administrator' }}
                    </h1>
                    <p class="welcome-subtitle">
                        Central command for User Identity, System Audit Trails, GPS Batch Pipelines, Analytics Intelligence, and Cross-Department Workflow Supervision.
                    </p>
                </div>

                <div class="welcome-actions">
                    @if(($pendingApplications ?? 0) > 0)
                        <a href="{{ route('admin.applications') }}" class="admin-quick-btn warning-glow" title="Applicant accounts awaiting review">
                            <i class="fa-solid fa-user-clock"></i>
                            <span>{{ $pendingApplications }} Pending Application{{ $pendingApplications > 1 ? 's' : '' }}</span>
                        </a>
                    @endif
                    <a href="{{ route('admin.users') }}" class="admin-quick-btn primary">
                        <i class="fa-solid fa-user-plus"></i>
                        <span>New User</span>
                    </a>
                    <a href="{{ route('admin.batch-file-processing') }}" class="admin-quick-btn secondary">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span>Upload Batch File</span>
                    </a>
                    <a href="{{ route('admin.activity-logs') }}" class="admin-quick-btn secondary">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span>Audit Trail</span>
                    </a>
                </div>
            </section>


            @php
                $reachableModelGroups = collect($mlModelStatuses ?? [])->filter(fn ($model) => (bool) ($model->reachable ?? false))->count();
                $pendingActionTotal = (int) ($pendingApplications ?? 0) + (int) ($batchMetrics['in_review'] ?? 0) + (int) ($departmentMetrics['warehouse'] ?? 0);
            @endphp
            <section class="admin-command-metrics" aria-label="System Command Center Metrics">
                <a href="{{ route('admin.users') }}" class="admin-command-metric admin-command-metric--users">
                    <span class="admin-command-metric-head"><span class="admin-command-metric-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span><span class="admin-command-metric-label">Active Users</span></span>
                    <strong class="admin-command-metric-value">{{ number_format($activeUsers ?? 0) }}</strong>
                    <small class="admin-command-metric-description"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>{{ number_format($totalUsers ?? 0) }} total accounts</small>
                </a>
                <a href="{{ route('admin.applications') }}" class="admin-command-metric admin-command-metric--applications">
                    <span class="admin-command-metric-head"><span class="admin-command-metric-icon"><i class="fa-solid fa-user-clock" aria-hidden="true"></i></span><span class="admin-command-metric-label">Pending Applications</span></span>
                    <strong class="admin-command-metric-value">{{ number_format($pendingApplications ?? 0) }}</strong>
                    <small class="admin-command-metric-description"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>Account reviews required</small>
                </a>
                <a href="{{ route('admin.batch-file-processing') }}" class="admin-command-metric admin-command-metric--imports">
                    <span class="admin-command-metric-head"><span class="admin-command-metric-icon"><i class="fa-solid fa-file-import" aria-hidden="true"></i></span><span class="admin-command-metric-label">Imports in Review</span></span>
                    <strong class="admin-command-metric-value">{{ number_format($batchMetrics['in_review'] ?? 0) }}</strong>
                    <small class="admin-command-metric-description"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>{{ number_format($batchMetrics['total'] ?? 0) }} total uploads</small>
                </a>
                <a href="{{ route('analytics.overview') }}" class="admin-command-metric admin-command-metric--ml">
                    <span class="admin-command-metric-head"><span class="admin-command-metric-icon"><i class="fa-solid fa-server" aria-hidden="true"></i></span><span class="admin-command-metric-label">ML Services Reachable</span></span>
                    <strong class="admin-command-metric-value">{{ $reachableModelGroups }}<em>/5</em></strong>
                    <small class="admin-command-metric-description"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>Endpoint response, not ML readiness</small>
                </a>
            </section>

            <!-- AI/ML health: real service states only, no fabricated accuracy percentages. -->
            @php
                $modelHealthByKey = collect($mlModelStatuses ?? [])->keyBy('key');
                $modelHealthCards = [
                    ['key' => 'eta', 'title' => 'ETA Prediction', 'description' => 'Trip duration and arrival estimates', 'icon' => 'fa-clock', 'accent' => 'eta'],
                    ['key' => 'fuel', 'title' => 'Fuel Prediction', 'description' => 'Fuel consumption per trip', 'icon' => 'fa-gas-pump', 'accent' => 'fuel'],
                    ['key' => 'delay', 'title' => 'Delay Prediction', 'description' => 'Potential arrival delays', 'icon' => 'fa-hourglass-half', 'accent' => 'delay'],
                    ['key' => 'inventory', 'title' => 'Inventory Forecast', 'description' => 'Spare parts demand', 'icon' => 'fa-boxes-stacked', 'accent' => 'inventory'],
                    ['key' => 'scheduling', 'title' => 'Auto Scheduling', 'description' => 'Bus and driver recommendations', 'icon' => 'fa-bus', 'accent' => 'scheduling'],
                ];
            @endphp
            <section class="admin-ml-section" aria-labelledby="admin-ml-title">
                <header class="admin-ml-header">
                    <div class="admin-ml-heading">
                        <span class="admin-ml-heading-icon" aria-hidden="true"><i class="fa-solid fa-brain"></i></span>
                        <div>
                            <h2 id="admin-ml-title">AI &amp; Machine Learning Health</h2>
                            <p>Model readiness, training data, and prediction services</p>
                        </div>
                    </div>
                    <div class="admin-ml-actions">
                        <span class="admin-ml-count"><span class="admin-ml-count-dot"></span>5 Model Groups</span>
                        <a class="admin-ml-view-all" href="{{ route('analytics.overview') }}">View Details <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    </div>
                </header>

                <div class="admin-ml-grid">
                    @foreach($modelHealthCards as $definition)
                        @php
                            $model = $modelHealthByKey->get($definition['key']);
                            $hasOperationalRecords = ($mlOperationalRecords[$definition['key']] ?? null);
                            $awaitingData = $hasOperationalRecords === false;
                            $reachable = ($model->reachable ?? false) === true;
                            $state = $awaitingData ? 'Awaiting data' : ($reachable ? (string) $model->state : 'Status not confirmed');
                            $trainingLabel = $reachable ? ($model->count_label ?? 'Verification Pending') : 'Training status not confirmed';
                            $tone = (string) ($model->tone ?? 'offline');
                            if ($awaitingData) $tone = 'not-ready';
                            $statusClass = in_array($tone, ['ready', 'partial', 'warning', 'offline', 'not-ready'], true) ? $tone : 'not-ready';
                            $samples = $model ? (int) ($model->sample_count ?? 0) : null;
                            $details = $model->details ?? [];
                        @endphp
                        <article class="admin-ml-card admin-ml-card--{{ $definition['accent'] }}">
                            <div class="admin-ml-card-heading">
                                <span class="admin-ml-card-icon" aria-hidden="true"><i class="fa-solid {{ $definition['icon'] }}"></i></span>
                                <h3>{{ $definition['title'] }}</h3>
                            </div>
                            <span class="admin-ml-state admin-ml-state--{{ $statusClass }}">
                                <i class="fa-solid {{ $statusClass === 'ready' ? 'fa-circle-check' : ($statusClass === 'partial' ? 'fa-circle-half-stroke' : ($statusClass === 'offline' ? 'fa-plug-circle-xmark' : 'fa-circle-info')) }}" aria-hidden="true"></i>
                                {{ $state }}
                            </span>
                            <p class="admin-ml-description">{{ $definition['description'] }}</p>
                            @if($awaitingData)
                                <p class="admin-ml-description">No operational data yet</p>
                            @endif
                            <p class="admin-ml-description">{{ $reachable ? ($model->availability ?? 'Service reachable') : 'ML service status not confirmed' }}</p>
                            <div class="admin-ml-card-foot">
                                <span class="admin-ml-samples">
                                    <i class="fa-solid fa-database" aria-hidden="true"></i>
                                    {{ $awaitingData ? 'Waiting for new records' : $trainingLabel }}
                                </span>
                                <button type="button" class="admin-ml-info"
                                    aria-label="Details for {{ $definition['title'] }}"
                                    data-ml-details
                                    data-ml-title="{{ $definition['title'] }}"
                                    data-ml-status="{{ $state }}"
                                    data-ml-source="{{ $model->data_source ?? 'Unavailable' }}"
                                    data-ml-dataset="{{ $model->dataset_type ?? 'Not reported' }}"
                                    data-ml-samples="{{ $trainingLabel }}"
                                    data-ml-version="{{ $model->model_version ?? '' }}"
                                    data-ml-reason="{{ ($awaitingData ? 'No operational records are currently available for this model group. New records do not automatically verify saved training data. ' : '') . ($model->reason ?? 'The ML service status check has not completed successfully.') }}"
                                    data-ml-components="{{ collect($details)->map(fn ($part) => ($part->label ?? '') . ': ' . number_format((int) ($part->reported_sample_count ?? 0)) . ' reported training rows — unverified; ' . ($part->value ?? 'Availability not reported'))->implode('; ') }}">
                                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                </button>
                            </div>
                        </article>
                    @endforeach
                </div>
                <p class="admin-ml-disclaimer">Training-data verification is pending. Reported training rows are not verified operational records. Service reachability and model availability do not establish production suitability.</p>
            </section>

            <dialog id="adminMlDetailsDialog" class="admin-ml-dialog" aria-labelledby="adminMlDialogTitle">
                <div class="admin-ml-dialog-head">
                    <span class="admin-ml-dialog-symbol" aria-hidden="true"><i class="fa-solid fa-layer-group"></i></span>
                    <div class="admin-ml-dialog-heading">
                        <span class="admin-ml-dialog-eyebrow">Model information</span>
                        <h2 id="adminMlDialogTitle">Model Details</h2>
                    </div>
                    <button type="button" class="admin-ml-dialog-close" data-ml-close aria-label="Close model details"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </div>
                <dl class="admin-ml-dialog-facts">
                    <div class="admin-ml-dialog-fact"><span class="admin-ml-dialog-fact-icon"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i></span><div><dt>Current status</dt><dd data-ml-field="status">—</dd></div></div>
                    <div class="admin-ml-dialog-fact"><span class="admin-ml-dialog-fact-icon"><i class="fa-solid fa-database" aria-hidden="true"></i></span><div><dt>Training source</dt><dd data-ml-field="source">—</dd></div></div>
                    <div class="admin-ml-dialog-fact"><span class="admin-ml-dialog-fact-icon"><i class="fa-solid fa-table" aria-hidden="true"></i></span><div><dt>Dataset</dt><dd data-ml-field="dataset">—</dd></div></div>
                    <div class="admin-ml-dialog-fact"><span class="admin-ml-dialog-fact-icon"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span><div><dt>Training-data verification</dt><dd data-ml-field="samples">—</dd></div></div>
                    <div class="admin-ml-dialog-fact"><span class="admin-ml-dialog-fact-icon"><i class="fa-solid fa-code" aria-hidden="true"></i></span><div><dt>Model version</dt><dd data-ml-field="version">—</dd></div></div>
                    <div class="admin-ml-dialog-fact"><span class="admin-ml-dialog-fact-icon"><i class="fa-solid fa-puzzle-piece" aria-hidden="true"></i></span><div><dt>Components</dt><dd data-ml-field="components">—</dd></div></div>
                    <div class="admin-ml-dialog-reason"><span class="admin-ml-dialog-fact-icon"><i class="fa-solid fa-circle-info" aria-hidden="true"></i></span><div><dt>Readiness explanation</dt><dd data-ml-field="reason">—</dd></div></div>
                </dl>
                <footer class="admin-ml-dialog-footer"><button type="button" class="admin-ml-dialog-done" data-ml-close>Close</button></footer>
            </dialog>


            <section class="admin-command-content" aria-label="System Activity Trends and Action Required">
                                <article class="admin-panel admin-velocity-panel">
                    <div class="admin-panel-header">
                        <div>
                            <span class="panel-eyebrow"><i class="fa-solid fa-chart-line"></i> Activity Velocity</span>
                            <h2>System Activity & Trends</h2>
                            <p>Requests, transactions, and operational records over time.</p>
                        </div>

                        <!-- Accessible Tab Switcher -->
                        <div class="chart-tab-group" role="tablist" aria-label="System activity timeframe">
                            <button
                                type="button"
                                role="tab"
                                id="tab-monthly"
                                class="chart-tab-btn active"
                                aria-selected="true"
                                aria-controls="panel-monthly"
                                data-view="monthly"
                            >
                                <i class="fa-solid fa-chart-column"></i> Monthly (6 Mo)
                            </button>
                            <button
                                type="button"
                                role="tab"
                                id="tab-daily"
                                class="chart-tab-btn"
                                aria-selected="false"
                                aria-controls="panel-daily"
                                data-view="daily"
                            >
                                <i class="fa-solid fa-chart-line"></i> Daily (7 Days)
                            </button>
                        </div>
                    </div>

                    <!-- Dual Chart Containers with seamless toggle -->
                    <div class="velocity-chart-wrapper">
                        <div
                            id="panel-monthly"
                            class="velocity-chart-view active"
                            role="tabpanel"
                            aria-labelledby="tab-monthly"
                        >
                            <canvas id="monthlyActivityChart"></canvas>
                        </div>
                        <div
                            id="panel-daily"
                            class="velocity-chart-view"
                            role="tabpanel"
                            aria-labelledby="tab-daily"
                            style="display: none;"
                        >
                            <canvas id="systemActivityChart"></canvas>
                        </div>
                    </div>

                </article>

                <article class="admin-panel admin-command-attention">
                    <div class="admin-panel-header">
                        <div><span class="panel-eyebrow"><i class="fa-solid fa-clipboard-check"></i> Attention Queue</span><h2>Action Required</h2><p>Outstanding tasks from actual system records.</p></div>
                        <span class="admin-command-count">{{ number_format($pendingActionTotal) }} items</span>
                    </div>
                    <div class="admin-command-attention-items">
                        <a href="{{ route('admin.applications') }}"><i class="fa-solid fa-user-clock"></i><span><strong>Pending applications</strong><small>Review new account requests</small></span><b>{{ number_format($pendingApplications ?? 0) }}</b></a>
                        <a href="{{ route('admin.batch-file-processing') }}"><i class="fa-solid fa-file-import"></i><span><strong>Imports awaiting review</strong><small>Check pending and processing batches</small></span><b>{{ number_format($batchMetrics['in_review'] ?? 0) }}</b></a>
                        <a href="{{ route('warehouse.dashboard') }}"><i class="fa-solid fa-boxes-stacked"></i><span><strong>Low / critical stock</strong><small>Warehouse items requiring attention</small></span><b>{{ number_format($departmentMetrics['warehouse'] ?? 0) }}</b></a>
                    </div>
                </article>
            </section>
            <section class="admin-command-content" aria-label="Recent Activity and Quick Actions">
                <article class="admin-panel admin-command-recent">
                    <div class="admin-panel-header">
                        <div><span class="panel-eyebrow"><i class="fa-solid fa-clock-rotate-left"></i> Audit &amp; Notifications</span><h2>Recent Activity</h2><p>Latest recorded system notifications.</p></div>
                        <a class="admin-panel-link" href="{{ route('admin.activity-logs') }}">View Audit Trail <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                    <div class="admin-command-feed">
                        @forelse(collect($recentActivity ?? [])->take(5) as $activity)
                            <div class="admin-command-feed-item"><i class="fa-solid fa-bell"></i><div><strong>{{ $activity->message ?: 'System activity recorded.' }}</strong><small>{{ $activity->created_at?->diffForHumans() ?? 'Recently' }}</small></div></div>
                        @empty
                            <x-ui.empty-state icon="fa-clock-rotate-left" title="No recent activity" description="System notifications will appear here." />
                        @endforelse
                    </div>
                </article>
                <article class="admin-panel admin-command-quick">
                    <div class="admin-panel-header"><div><span class="panel-eyebrow"><i class="fa-solid fa-grip"></i> Administration</span><h2>Quick Actions</h2><p>Manage accounts, policies, audit and data.</p></div></div>
                    <div class="admin-command-shortcuts">
                        <a href="{{ route('admin.users') }}"><i class="fa-solid fa-users"></i><strong>Accounts</strong><small>User directory</small></a>
                        <a href="{{ route('admin.roles-permissions') }}"><i class="fa-solid fa-shield-halved"></i><strong>Permissions</strong><small>Access policy</small></a>
                        <a href="{{ route('admin.activity-logs') }}"><i class="fa-solid fa-list-check"></i><strong>Audit Logs</strong><small>Activity history</small></a>
                        <a href="{{ route('admin.batch-file-processing') }}"><i class="fa-solid fa-database"></i><strong>Data Hub</strong><small>Batch processing</small></a>
                    </div>
                    <div class="admin-command-lastbatch">
                        <span><i class="fa-solid fa-file-arrow-up"></i> Latest Batch</span>
                        @if(collect($recentBatches ?? [])->isNotEmpty())
                            @php $lastBatch = collect($recentBatches)->first(); @endphp
                            <a href="{{ route('admin.batch-file-processing') }}"><strong>{{ Str::limit($lastBatch->file_name ?? 'Uploaded file', 36) }}</strong><small>{{ $lastBatch->status ?? 'Pending' }} · {{ number_format($lastBatch->total_records ?? 0) }} records</small></a>
                        @else
                            <small>No recent uploads</small>
                        @endif
                    </div>
                </article>
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
            'totalRecords' => $totalRecords ?? array_sum($departmentDistribution ?? []),
        ];
    @endphp

    <script>
        window.adminDashboardData = @json($adminDashboardChartData);
    </script>
</x-layout.app>
