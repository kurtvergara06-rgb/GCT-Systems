<x-layout.app
    title="FROMS - Fleet & Trip Analytics"
    :assets="[
        'resources/css/Admin/Analytics/fleet-trip.css',
        'resources/css/Admin/Analytics/fleet-trip-tabs.css',
        'resources/css/Admin/Analytics/fleet-trip-redesign.css',
        'resources/css/Admin/Analytics/fleet-trip-rankings.css',
    ]"
>
    @php
        $allowedSections = ['descriptive', 'diagnostic', 'predictive', 'prescriptive'];
        $section = strtolower((string) request('section', 'descriptive'));
        $section = in_array($section, $allowedSections, true) ? $section : 'descriptive';

        $sectionMeta = [
            'descriptive' => [
                'label' => 'Descriptive',
                'number' => '5.1',
                'subtitle' => 'Recorded performance: distance, speed, idling, trip duration, routes, and fleet activity.',
            ],
            'diagnostic' => [
                'label' => 'Diagnostic',
                'number' => '5.2',
                'subtitle' => 'Diagnostic review of delays, slow movement, idling, and operating patterns from processed trip records.',
            ],
            'predictive' => [
                'label' => 'Predictive',
                'number' => '5.3',
                'subtitle' => 'Forecasted trip duration, delay risk, ETA, and historical peak or slow periods from validated trip history.',
            ],
            'prescriptive' => [
                'label' => 'Prescriptive',
                'number' => '5.4',
                'subtitle' => 'Decision support: recommended shuttle, route, and schedule actions based on validated analytics.',
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

            <section class="fleet-analytics-toolbar">
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
                            <span class="fleet-tab-number">{{ $meta['number'] }}</span>
                            <span>{{ $meta['label'] }}</span>
                            @if($key === 'predictive' && $prediction->available && $prediction->predicted_target_count > 0)
                                <small>{{ number_format($prediction->predicted_target_count) }}</small>
                            @elseif($key === 'diagnostic' && $diagnostics->review_count > 0)
                                <small>{{ number_format($diagnostics->review_count) }}</small>
                            @endif
                        </a>
                    @endforeach
                </nav>

                <form class="fleet-filters fleet-toolbar-filters" method="GET" action="{{ route('analytics.fleet-trip') }}">
                    <input type="hidden" name="section" value="{{ $section }}">

                    <label class="fleet-toolbar-field">
                        <span>Period</span>
                        <select name="period" aria-label="Analysis period">
                            <option value="this-month" @selected($period === 'this-month')>This Month</option>
                            <option value="last-30-days" @selected($period === 'last-30-days')>Last 30 Days</option>
                            <option value="last-3-months" @selected($period === 'last-3-months')>Last 3 Months</option>
                            <option value="this-year" @selected($period === 'this-year')>This Year</option>
                        </select>
                    </label>

                    <label class="fleet-toolbar-field">
                        <span>Bus Group</span>
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
                    </label>

                    <button type="submit" class="fleet-filter-button fleet-toolbar-apply">
                        <i class="fa-solid fa-filter"></i>
                        Apply
                    </button>
                </form>
            </section>

            @include("Admin.Analytics.FleetTrip.sections.{$section}")
        </main>
    </div>
</x-layout.app>
