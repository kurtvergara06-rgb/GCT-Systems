<x-layout.app
    title="FROMS - Fleet & Trip Analytics"
    :assets="[
        'resources/css/Admin/Analytics/fleet-trip.css',
    ]"
>
    @php
        $allowedSections = ['overview', 'descriptive', 'diagnostic', 'predictive', 'prescriptive'];
        $section = strtolower((string) request('section', 'overview'));
        $section = in_array($section, $allowedSections, true) ? $section : 'overview';

        $sectionMeta = [
            'overview' => [
                'label' => 'Overview',
                'icon' => 'fa-table-columns',
                'subtitle' => 'A concise Fleet & Trip analytics summary and status of 5.1 to 5.4.',
            ],
            'descriptive' => [
                'label' => 'Descriptive',
                'icon' => 'fa-chart-column',
                'subtitle' => 'Review recorded distance, speed, idling, trip duration, routes, and fleet activity.',
            ],
            'diagnostic' => [
                'label' => 'Diagnostic',
                'icon' => 'fa-magnifying-glass-chart',
                'subtitle' => 'Review delay indicators, slow movement, high idling, and supporting evidence.',
            ],
            'predictive' => [
                'label' => 'Predictive',
                'icon' => 'fa-chart-line',
                'subtitle' => 'View Python forecasts for trip duration, ETA, delay risk, and historical slow periods.',
            ],
            'prescriptive' => [
                'label' => 'Prescriptive',
                'icon' => 'fa-lightbulb',
                'subtitle' => 'Decision-support recommendations based on validated findings and forecasts.',
            ],
        ];
    @endphp

    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main fleet-trip-page">
            <x-layout.topbar
                title="Fleet & Trip Analytics"
                :subtitle="$sectionMeta[$section]['subtitle']"
                notification-count="6"
            />

            <nav class="fleet-analytics-tabs" aria-label="Fleet and Trip analytics views">
                @foreach($sectionMeta as $key => $meta)
                    <a
                        href="{{ route('analytics.fleet-trip', [
                            'section' => $key,
                            'period' => $period,
                            'bus' => strtolower($selectedBus),
                        ]) }}"
                        class="fleet-analytics-tab {{ $section === $key ? 'active' : '' }}"
                        @if($section === $key) aria-current="page" @endif
                    >
                        <i class="fa-solid {{ $meta['icon'] }}"></i>
                        <span>{{ $meta['label'] }}</span>
                        @if($key === 'predictive' && $prediction->available)
                            <small>{{ number_format($prediction->predicted_target_count) }}</small>
                        @elseif($key === 'diagnostic')
                            <small>{{ number_format($diagnostics->review_count) }}</small>
                        @endif
                    </a>
                @endforeach
            </nav>

            <section class="fleet-filter-bar fleet-section-filter">
                <div>
                    <span class="section-kicker">{{ $sectionMeta[$section]['label'] }} Analytics</span>
                    <h2>{{ $sectionMeta[$section]['label'] }} View</h2>
                    <p>{{ $periodLabel }} · {{ $selectedBus === 'ALL' ? 'All Buses' : $selectedBus }}</p>
                </div>

                <form class="fleet-filters" method="GET" action="{{ route('analytics.fleet-trip') }}">
                    <input type="hidden" name="section" value="{{ $section }}">

                    <select name="period" aria-label="Analysis period">
                        <option value="this-month" @selected($period === 'this-month')>This Month</option>
                        <option value="last-30-days" @selected($period === 'last-30-days')>Last 30 Days</option>
                        <option value="last-3-months" @selected($period === 'last-3-months')>Last 3 Months</option>
                        <option value="this-year" @selected($period === 'this-year')>This Year</option>
                    </select>

                    <select name="bus" aria-label="Shuttle bus">
                        <option value="all" @selected($selectedBus === 'ALL')>All Buses</option>
                        @foreach($busOptions as $busOption)
                            <option
                                value="{{ $busOption->bus_no }}"
                                @selected($selectedBus === strtoupper(trim($busOption->bus_no)))
                            >
                                {{ $busOption->bus_no }}
                            </option>
                        @endforeach
                    </select>

                    <button type="submit" class="fleet-filter-button">
                        <i class="fa-solid fa-filter"></i>
                        Apply
                    </button>
                </form>
            </section>

            @include("Admin.Analytics.FleetTrip.sections.{$section}")
        </main>
    </div>
</x-layout.app>
