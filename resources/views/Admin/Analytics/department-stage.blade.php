@php
    $stageLabel = ucfirst($stage);
    $stageDescriptions = [
        'descriptive' => 'Recorded departmental activity and status distribution',
        'diagnostic' => 'Recorded activity and status patterns; cause analysis is not yet available here',
        'predictive' => 'Historical data readiness; department-scoped predictions are not yet connected',
        'prescriptive' => 'Historical data readiness; authorized recommendations are not yet connected',
    ];
    $icons = ['fa-screwdriver-wrench', 'fa-chart-column', 'fa-database', 'fa-chart-simple'];
    $allCount = collect($metrics)->sum('total');
    $stageTabs = [
        'descriptive' => ['Descriptive Analytics', 'fa-chart-column', 'view'],
        'diagnostic' => ['Diagnostic Analytics', 'fa-magnifying-glass-chart', 'analyze'],
        'predictive' => ['Predictive Analytics', 'fa-chart-line', 'analyze'],
        'prescriptive' => ['Prescriptive Analytics', 'fa-lightbulb', 'recommendations'],
    ];
    $allowedStageTabs = collect($stageTabs)->filter(
        fn ($tab) => auth()->user()->hasSystemPermission('analytics', $tab[2])
    );
@endphp

<x-layout.app
    title="FROMS - {{ $stageLabel }} Analytics"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/Analytics/design-system.css',
        'resources/css/Admin/Analytics/department-stage.css',
        'resources/js/Admin/Analytics/department-stage.js'
    ]"
>
    <div class="app">
        <x-layout.sidebar :department="auth()->user()->department" />
        <main class="main analytics-stage-page department-analytics-page">
            <x-layout.topbar
                :title="$stageLabel.' Analytics'"
                :subtitle="ucfirst($department).' Department · '.$stageDescriptions[$stage]"
            />

            {{-- Shared analytics navigation pattern; each link stays on the
                 permission-checked department route, never the Admin route. --}}
            <nav class="department-analytics-tabs" aria-label="Department analytics stages">
                @foreach($allowedStageTabs as $tabStage => $tab)
                    <a href="{{ route('analytics.department.stage', ['stage' => $tabStage]) }}"
                       class="{{ $stage === $tabStage ? 'is-current' : '' }}"
                       @if($stage === $tabStage) aria-current="page" @endif>
                        <i class="fa-solid {{ $tab[1] }}" aria-hidden="true"></i>
                        {{ $tab[0] }}
                    </a>
                @endforeach
            </nav>

            {{-- Reuse the same KPI Blade component used by System Admin Analytics. --}}
            <section class="analytics-kpi-strip" aria-label="Department summary">
                <x-analytics.kpi label="Recorded Entries" :value="number_format($allCount)"
                    description="Across permitted department sources"
                    icon="fa-database" icon-variant="blue" />
                @foreach($metrics as $metric)
                    <x-analytics.kpi :label="$metric['label']"
                        :value="number_format($metric['total'])"
                        description="Recorded entries" :icon="$icons[$loop->index % count($icons)]"
                        icon-variant="blue" />
                @endforeach
            </section>

            @if($stage === 'descriptive')
                <section class="department-analytics-chart-grid" aria-label="Department historical charts">
                    <x-analytics.card title="Monthly Department Activity" description="Recorded entries created in the last six calendar months; not a forecast.">
                        <div class="department-analytics-chart-area">
                            <canvas id="departmentActivityChart" role="img" aria-label="Monthly recorded department activity"></canvas>
                        </div>
                    </x-analytics.card>
                    <x-analytics.card title="Recorded Source Distribution" description="Composition of departmental records from authorized sources.">
                        <div class="department-analytics-chart-area">
                            <canvas id="departmentSourcesChart" role="img" aria-label="Distribution of department records by source"></canvas>
                        </div>
                    </x-analytics.card>
                </section>
                <script type="application/json" id="departmentAnalyticsChartData">{!! json_encode([
                    'months' => collect($monthlyActivity ?? [])->pluck('month')->values(),
                    'monthly' => collect($monthlyActivity ?? [])->pluck('total')->values(),
                    'sources' => collect($metrics)->pluck('label')->values(),
                    'counts' => collect($metrics)->pluck('total')->values(),
                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
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

            <section class="department-analytics-panels" aria-label="Department records breakdown">
                @forelse($metrics as $metric)
                    <x-analytics.card class="department-analytics-panel" :title="$metric['label']" description="Recorded status distribution" :badge="number_format($metric['total']).' records'">
                        @if($metric['statuses']->isNotEmpty())
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
        </main>
    </div>
</x-layout.app>
