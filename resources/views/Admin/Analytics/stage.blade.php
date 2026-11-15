@php
    $stageDescription = match ($stage) {
        'predictive' => 'What may happen next based on validated historical evidence and forecast readiness.',
        'prescriptive' => 'What actions should be considered, with the operator remaining in control.',
        default => 'Analytics stage overview.',
    };

    $tabs = [
        'all' => ['All', 'fa-table-cells-large'],
        'fleet-trip' => ['Fleet & Trip', 'fa-route'],
        'fuel' => ['Fuel', 'fa-gas-pump'],
        'bus-health' => ['Bus Health', 'fa-heart-pulse'],
        'inventory' => ['Inventory', 'fa-boxes-stacked'],
    ];

    $activeDomain = array_key_exists($domain, $tabs) ? $domain : 'all';
    $stageUrl = route('analytics.stage', ['stage' => $stage], false);
    $normalizedSelectedBus = strtolower(trim((string) $selectedBus));
    $pageAssets = [
        'resources/css/Admin/Analytics/analytics-stage-hub.css',
    ];
@endphp

<x-layout.app title="FROMS - {{ $stageLabel }} Analytics" :assets="$pageAssets">
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main analytics-stage-page {{ $stage }}-analytics-page {{ $stage }}-domain-{{ $activeDomain }}">
            <x-layout.topbar :title="$stageLabel . ' Analytics'" :subtitle="$stageDescription" />

            <section class="analytics-domain-toolbar">
                <nav class="analytics-domain-tabs" aria-label="{{ $stageLabel }} analytics domains">
                    @foreach($tabs as $key => $tab)
                        <a
                            href="{{ $stageUrl }}?{{ http_build_query(['domain' => $key, 'period' => $period, 'bus' => $normalizedSelectedBus !== 'all' ? $selectedBus : null]) }}"
                            class="{{ $activeDomain === $key ? 'active' : '' }}"
                        >
                            <i class="fa-solid {{ $tab[1] }}"></i>{{ $tab[0] }}
                        </a>
                    @endforeach
                </nav>

                <form method="GET" action="{{ $stageUrl }}" class="analytics-stage-filters">
                    <input type="hidden" name="domain" value="{{ $activeDomain }}">

                    <label>
                        <span>Period</span>
                        <select name="period">
                            <option value="this-month" @selected($period === 'this-month')>This Month</option>
                            <option value="last-30-days" @selected($period === 'last-30-days')>Last 30 Days</option>
                            <option value="last-3-months" @selected($period === 'last-3-months')>Last 3 Months</option>
                            <option value="this-year" @selected($period === 'this-year')>This Year</option>
                        </select>
                    </label>

                    <label>
                        <span>Bus</span>
                        <select name="bus">
                            <option value="all" @selected($normalizedSelectedBus === 'all')>All Buses</option>
                            @foreach($busOptions as $busNo)
                                <option value="{{ $busNo }}" @selected(strtoupper((string) $selectedBus) === strtoupper((string) $busNo))>{{ $busNo }}</option>
                            @endforeach
                        </select>
                    </label>

                    <button type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
                </form>
            </section>

            <section class="analytics-domain-section analytics-domain-pending">
                <div class="analytics-card analytics-pending-card">
                    <i class="fa-solid {{ $activeDomain === 'all' ? 'fa-chart-line' : $tabs[$activeDomain][1] }}"></i>
                    <div>
                        <strong>{{ ucfirst($stageLabel) }} {{ $activeDomain === 'all' ? '' : ($tabs[$activeDomain][0] . ' ') }}analytics is not yet connected.</strong>
                        <p>No values are being invented. This stage will appear here once its supporting evidence is implemented.</p>
                    </div>
                </div>
            </section>
        </main>
    </div>
</x-layout.app>
