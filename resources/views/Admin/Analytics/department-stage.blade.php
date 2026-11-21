@php
    $stageLabel = ucfirst($stage);
    $stageDescriptions = [
        'descriptive' => 'Recorded departmental activity and status distribution',
        'diagnostic' => 'Recorded activity and status patterns; cause analysis is not yet available here',
        'predictive' => 'Historical data readiness; department-scoped predictions are not yet connected',
        'prescriptive' => 'Historical data readiness; authorized recommendations are not yet connected',
    ];
    $icons = ['fa-screwdriver-wrench', 'fa-chart-column', 'fa-database', 'fa-chart-simple'];
    $availableMetrics = collect($metrics)->where('available', true);
    $allCount = $availableMetrics->sum('total');
    $statusHighlights = $availableMetrics->filter(fn ($metric) => $metric['statuses']->isNotEmpty())->take(2);
    $stageTabs = [
        'descriptive' => ['Descriptive Analytics', 'fa-chart-column', 'view'],
        'diagnostic' => ['Diagnostic Analytics', 'fa-magnifying-glass-chart', 'diagnostic'],
        'predictive' => ['Predictive Analytics', 'fa-chart-line', 'predictive'],
        'prescriptive' => ['Prescriptive Analytics', 'fa-lightbulb', 'recommendations'],
    ];
    $allowedStageTabs = collect($stageTabs)->filter(
        fn ($tab) => auth()->user()->hasSystemPermission('analytics', $tab[2])
    );
    $departmentStageLinks = $allowedStageTabs->mapWithKeys(
        fn ($tab, $tabStage) => [
            $tabStage => route('analytics.department.stage', [
                'stage' => $tabStage, 'from' => $from, 'to' => $to,
            ]),
        ]
    )->all();
    $stageControlTabs = $allowedStageTabs->map(
        fn ($tab) => [$tab[0], $tab[1]]
    )->all();
@endphp

<x-layout.app
    title="FROMS - {{ $stageLabel }} Analytics"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/Analytics/descriptive/descriptive-rebuild.css',
        'resources/css/Admin/Analytics/design-system.css',
        'resources/css/Admin/Analytics/department-stage.css',
        'resources/js/Admin/Analytics/department-stage.js'
    ]"
>
    <div class="app">
        <x-layout.sidebar :department="auth()->user()->department" />
        <main class="main analytics-stage-page descriptive-rebuild department-analytics-page">
            <x-layout.topbar
                :title="$stageLabel.' Analytics'"
                :subtitle="ucfirst($department).' Department · '.$stageDescriptions[$stage]"
            />

            {{-- Shared Admin Analytics stage toolbar, with department-only stage links
                 and date range controls. No Admin-wide routes or datasets are used. --}}
            <x-analytics.stage-controls
                :url="route('analytics.department.stage', ['stage' => $stage])"
                :tabs="$stageControlTabs"
                :tab-links="$departmentStageLinks"
                tabs-label="Department analytics stages"
                :active="$stage"
                :period="'custom'"
                :period-options="[]"
                :show-bus="false"
                :date-range="true"
                :from-date="$from"
                :to-date="$to"
            />
            @if($errors->any())
                <p class="department-analytics-notice" role="alert">{{ $errors->first() }}</p>
            @endif

            {{-- Reuse the Admin Descriptive Analytics card grid and operational chart
                 layout; data is sourced only from DepartmentAnalyticsService. --}}
            <section class="stats-grid descriptive-stats-grid department-descriptive-stats" aria-label="Department summary">
                <x-ui.summary-card label="Verified Department Records" :value="number_format($allCount)"
                    small="Within the selected reporting period" icon="fa-database" color="blue" />
                @foreach($metrics as $metric)
                    <x-ui.summary-card :label="$metric['label']"
                        :value="$metric['available'] ? number_format($metric['total']) : 'Unavailable'"
                        :small="$metric['available'] ? 'Verified department records' : 'Source not verified'"
                        :icon="$icons[$loop->index % count($icons)]"
                        :color="['green', 'yellow', 'purple', 'blue'][$loop->index % 4]" />
                @endforeach
                @foreach($statusHighlights as $metric)
                    @php
                        $topStatus = $metric['statuses']->first();
                    @endphp
                    <x-ui.summary-card
                        :label="$metric['label'].' · '.($topStatus->status ?: 'Unspecified')"
                        :value="number_format($topStatus->total)"
                        small="Most frequent recorded status"
                        icon="fa-list-check"
                        color="cyan" />
                @endforeach
            </section>

            @if($stage === 'descriptive')
                {{-- Same first row as Admin: activity trend plus donut and distribution list. --}}
                <div class="descriptive-grid grid-wide-left">
                    <x-analytics.descriptive-panel
                        eyebrow="Department Activity"
                        title="Monthly Department Activity"
                        description="Verified departmental entries within the selected reporting period."
                        icon="fa-chart-column">
                        <x-slot:actions>
                            <div class="chart-header-actions">
                                <div class="chart-kpi-pill"><i class="fa-solid fa-database text-blue" aria-hidden="true"></i>
                                    <span>Records: <strong>{{ number_format($allCount) }}</strong></span>
                                </div>
                            </div>
                        </x-slot:actions>
                        @if($allCount > 0)
                            <div class="descriptive-chart-shell">
                                <canvas id="departmentActivityChart" role="img" aria-label="Monthly recorded department activity"></canvas>
                            </div>
                        @else
                            <div class="dashboard-empty-state">
                                <div class="dashboard-empty-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></div>
                                <h3>No Verified Activity</h3>
                                <p>No verified departmental records match this reporting period.</p>
                            </div>
                        @endif
                    </x-analytics.descriptive-panel>
                    <x-analytics.descriptive-panel
                        eyebrow="Source Distribution"
                        title="Recorded Source Distribution"
                        description="Verified entries by authorized department source."
                        icon="fa-chart-pie">
                        @if($allCount > 0)
                            <div class="fleet-health-visual-wrap">
                                <div class="fleet-health-chart-container">
                                    <canvas id="departmentSourcesChart" role="img" aria-label="Distribution of department records by source"></canvas>
                                    <div class="fleet-health-chart-center" aria-hidden="true">
                                        <strong>{{ number_format($allCount) }}</strong><span>Entries</span>
                                    </div>
                                </div>
                                <div class="fleet-health-status-stack">
                                    @foreach($availableMetrics as $metric)
                                        <div class="fleet-health-row">
                                            <div class="fleet-health-left">
                                                <span class="status-indicator-dot" style="background: {{ ['#3b82f6','#22c55e','#f59e0b','#a78bfa'][$loop->index % 4] }}"></span>
                                                <div class="status-name-desc"><strong>{{ $metric['label'] }}</strong>
                                                    <small>Verified department source</small></div>
                                            </div>
                                            <div class="fleet-health-right">
                                                <span class="fleet-count">{{ number_format($metric['total']) }}</span>
                                                <small>{{ $allCount ? number_format(($metric['total'] / $allCount) * 100, 1) : 0 }}%</small>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="dashboard-empty-state">
                                <div class="dashboard-empty-icon"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i></div>
                                <h3>No Source Distribution</h3>
                                <p>No verified records are available for charting in this period.</p>
                            </div>
                        @endif
                    </x-analytics.descriptive-panel>
                </div>

                {{-- Same second chart row as Admin, using actual department statuses. --}}
                <div class="descriptive-grid">
                    @foreach($metrics as $metric)
                        <x-analytics.descriptive-panel
                            eyebrow="Record Breakdown"
                            :title="$metric['label'].' Status'"
                            description="Recorded status distribution for the selected reporting period."
                            icon="fa-list-check">
                            @if($metric['available'] && $metric['statuses']->isNotEmpty())
                                <div class="fleet-health-visual-wrap">
                                    <div class="fleet-health-chart-container">
                                        <canvas id="departmentStatusChart{{ $loop->index }}"
                                            role="img" aria-label="{{ $metric['label'] }} status distribution"></canvas>
                                        <div class="fleet-health-chart-center" aria-hidden="true">
                                            <strong>{{ number_format($metric['total']) }}</strong><span>Records</span>
                                        </div>
                                    </div>
                                    <div class="fleet-health-status-stack">
                                        @foreach($metric['statuses'] as $recordStatus)
                                            <div class="fleet-health-row">
                                                <div class="fleet-health-left">
                                                    <span class="status-indicator-dot" style="background: {{ ['#3b82f6','#22c55e','#f59e0b','#a78bfa','#ef4444','#14b8a6','#f97316','#64748b'][$loop->index % 8] }}"></span>
                                                    <div class="status-name-desc"><strong>{{ $recordStatus->status ?: 'Unspecified' }}</strong>
                                                        <small>Recorded entries</small></div>
                                                </div>
                                                <div class="fleet-health-right">
                                                    <span class="fleet-count">{{ number_format($recordStatus->total) }}</span>
                                                    <small>{{ $metric['total'] ? number_format(($recordStatus->total / $metric['total']) * 100, 1) : 0 }}%</small>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="dashboard-empty-state">
                                    <div class="dashboard-empty-icon"><i class="fa-solid fa-list-check" aria-hidden="true"></i></div>
                                    <h3>{{ $metric['available'] ? 'No Status Records' : 'Source Unavailable' }}</h3>
                                    <p>{{ $metric['available'] ? 'This source has no status breakdown for the selected period.' : 'This data source could not be verified.' }}</p>
                                </div>
                            @endif
                        </x-analytics.descriptive-panel>
                    @endforeach
                </div>

                {{-- Admin-style recent-record cards, populated only with safely scoped
                     references, never unrestricted cross-department tables. --}}
                @foreach($recentBySource as $recentGroup)
                    <div class="descriptive-dashboard-card full-width">
                        <div class="dashboard-card-header">
                            <div>
                                <span class="dashboard-eyebrow">RECENT VERIFIED RECORDS</span>
                                <h2><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Recent {{ $recentGroup['label'] }}</h2>
                                <p>Latest verified departmental records in the selected period.</p>
                            </div>
                        </div>
                        @if($recentGroup['available'] && $recentGroup['records']->isNotEmpty())
                            <div class="recent-descriptive-list">
                                @foreach($recentGroup['records'] as $record)
                                    <div class="recent-descriptive-item">
                                        <div class="item-icon-box blue"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></div>
                                        <div class="item-main">
                                            <div class="item-header-row">
                                                <strong class="item-user-name">{{ $recentGroup['label'] }} · ID {{ $record->record_id }}</strong>
                                                @if($record->record_status !== null)
                                                    <span class="badge-chip">{{ $record->record_status }}</span>
                                                @endif
                                            </div>
                                            <div class="item-details-row">
                                                <span><i class="fa-regular fa-calendar" aria-hidden="true"></i> {{ $record->recorded_at }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="dashboard-empty-state">
                                <div class="dashboard-empty-icon"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></div>
                                <h3>No Recent Records</h3>
                                <p>{{ $recentGroup['available'] ? 'No verified records match this reporting period.' : 'This data source is unavailable.' }}</p>
                            </div>
                        @endif
                    </div>
                @endforeach

                <script type="application/json" id="departmentAnalyticsChartData">{!! json_encode([
                    'months' => collect($monthlyActivity ?? [])->pluck('month')->values(),
                    'monthly' => collect($monthlyActivity ?? [])->pluck('total')->values(),
                    'sources' => $availableMetrics->pluck('label')->values(),
                    'counts' => $availableMetrics->pluck('total')->values(),
                    'statuses' => collect($metrics)->map(fn ($metric) => [
                        'labels' => $metric['statuses']->map(fn ($row) => $row->status ?: 'Unspecified')->values(),
                        'counts' => $metric['statuses']->pluck('total')->values(),
                    ])->values(),
                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
            @endif

            @if(collect($metrics)->contains(fn ($metric) => ! $metric['available']))
                <p class="department-analytics-notice" role="status">Some verified data sources are unavailable. Their values are not included in totals or charts.</p>
            @endif

            @if($stage === 'predictive')
                <section class="department-analytics-panels" aria-label="Department model connectivity">
                    @forelse($modelReadiness as $model)
                        <x-analytics.card :title="$model['name']" description="Read-only status check; no operational records sent to the model.">
                            <dl class="department-model-status">
                                <dt>Service connectivity</dt><dd>{{ $model['reachable'] ? 'Reachable' : 'Unavailable' }}</dd>
                                <dt>Model reports ready</dt><dd>{{ $model['model_ready'] ? 'Yes' : 'Not verified' }}</dd>
                                <dt>Genuine production training</dt><dd>{{ $model['genuine_training'] ? 'Reported by service' : 'Not verified' }}</dd>
                                <dt>Department forecast contract</dt><dd>Not verified - forecasts unavailable</dd>
                            </dl>
                        </x-analytics.card>
                    @empty
                        <x-analytics.card title="No verified department model" description="No prediction endpoint is configured for this department." />
                    @endforelse
                </section>
            @endif

            @if($stage !== 'descriptive')
                <section class="department-analytics-notice">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <div>
                        <strong>{{ $stageLabel }} analysis not available in the scoped dashboard</strong>
                        <p>The {{ $stage }} calculations and visualizations used by System Admin have not been verified for department-only use. The historical summaries below are provided for context, not presented as {{ $stage }} outputs.</p>
                    </div>
                </section>
            @endif

            @if($stage !== 'descriptive')
            <section class="department-analytics-panels" aria-label="Department records breakdown">
                @forelse($metrics as $metric)
                    <x-analytics.card class="department-analytics-panel" :title="$metric['label']" description="Recorded status distribution" :badge="$metric['available'] ? number_format($metric['total']).' records' : 'Unavailable'">
                        @if(! $metric['available'])
                            <p role="status">This source could not be verified or queried. No zero value is assumed.</p>
                        @elseif($metric['statuses']->isNotEmpty())
                            <div class="department-analytics-statuses">
                                @foreach($metric['statuses'] as $status)
                                    @php
                                        $percentage = $metric['total'] > 0
                                            ? min(100, max(0, ((int) $status->total / $metric['total']) * 100))
                                            : 0;
                                    @endphp
                                    <div class="department-analytics-status">
                                        <div class="department-analytics-status-label">
                                            <span>{{ $status->status ?: 'Unspecified' }}</span>
                                            <strong>{{ number_format($status->total) }} <small>({{ number_format($percentage, 1) }}%)</small></strong>
                                        </div>
                                        <div class="department-analytics-track" aria-hidden="true">
                                            <span style="width: {{ $percentage }}%"></span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="department-analytics-empty">
                                <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                                <p>No status breakdown is available for this data source.</p>
                            </div>
                        @endif
                    </x-analytics.card>
                @empty
                    <div class="department-analytics-panel department-analytics-empty">
                        <i class="fa-solid fa-database" aria-hidden="true"></i>
                        <p>No eligible department records are currently available.</p>
                    </div>
                @endforelse
            </section>
            @endif
        </main>
    </div>
</x-layout.app>
