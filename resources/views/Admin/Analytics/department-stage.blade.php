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
@endphp

<x-layout.app
    title="FROMS - {{ $stageLabel }} Analytics"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/Analytics/design-system.css',
        'resources/css/Admin/Analytics/department-stage.css'
    ]"
>
    <div class="app">
        <x-layout.sidebar :department="auth()->user()->department" />
        <main class="main analytics-stage-page department-analytics-page">
            <x-layout.topbar
                :title="$stageLabel.' Analytics'"
                :subtitle="ucfirst($department).' Department · '.$stageDescriptions[$stage]"
            />

            <section class="department-analytics-intro" aria-label="Scope and methodology">
                <div class="department-analytics-intro-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <div>
                    <strong>{{ ucfirst($department) }} Department Data</strong>
                    <p>Only authorized departmental records appear here. Counts and status distributions describe recorded activity; they are not predictions, anomaly diagnoses, or recommended actions.</p>
                </div>
                <span class="department-analytics-scope">Department scope</span>
            </section>

            <section class="analytics-kpi-strip" aria-label="Department summary">
                <article class="analytics-kpi-card">
                    <div class="analytics-kpi-card__icon"><i class="fa-solid fa-database"></i></div>
                    <div class="analytics-kpi-card__content">
                        <span class="analytics-kpi-card__label">Recorded Entries</span>
                        <strong class="analytics-kpi-card__value">{{ number_format($allCount) }}</strong>
                        <span class="analytics-kpi-card__description">Across permitted department sources</span>
                    </div>
                </article>
                @foreach($metrics as $metric)
                    <article class="analytics-kpi-card">
                        <div class="analytics-kpi-card__icon"><i class="fa-solid {{ $icons[$loop->index % count($icons)] }}"></i></div>
                        <div class="analytics-kpi-card__content">
                            <span class="analytics-kpi-card__label">{{ $metric['label'] }}</span>
                            <strong class="analytics-kpi-card__value">{{ number_format($metric['total']) }}</strong>
                            <span class="analytics-kpi-card__description">Recorded entries</span>
                        </div>
                    </article>
                @endforeach
            </section>

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
                    <article class="department-analytics-panel">
                        <div class="department-analytics-panel-heading">
                            <div>
                                <h2>{{ $metric['label'] }}</h2>
                                <p>Recorded status distribution</p>
                            </div>
                            <span>{{ number_format($metric['total']) }} records</span>
                        </div>
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
                    </article>
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
