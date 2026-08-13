@php
$stageDescription = match ($stage) {
    'descriptive' => 'What happened across the operation based on recorded data.',
    'diagnostic' => 'What currently requires investigation and which measurable signals support the review.',
    'predictive' => 'What may happen next based on validated historical evidence and forecast readiness.',
    default => 'What actions should be considered, with the operator remaining in control.',
};
$domains = [
    'all' => ['All', 'fa-layer-group'],
    'fleet-trip' => ['Fleet & Trip', 'fa-route'],
    'fuel' => ['Fuel', 'fa-gas-pump'],
    'bus-health' => ['Bus Health', 'fa-heart-pulse'],
    'inventory' => ['Inventory', 'fa-boxes-stacked'],
];
extract($fleet, EXTR_SKIP);
@endphp

<x-layout.app
    title="FROMS - {{ $stageLabel }} Analytics"
    :assets="[
        'resources/css/Admin/Analytics/fleet-trip.css',
        'resources/css/Admin/Analytics/fleet-trip-redesign.css',
        'resources/css/Admin/Analytics/fleet-trip-rankings.css',
        'resources/css/Admin/Analytics/analytics-stage-hub.css',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main analytics-stage-page fleet-trip-page">
            <x-layout.topbar :title="$stageLabel . ' Analytics'" :subtitle="$stageDescription" />

            <section class="analytics-domain-toolbar">
                <nav class="analytics-domain-tabs" aria-label="Analytics domain">
                    @foreach($domains as $key => $meta)
                        <a
                            href="{{ route('analytics.stage', ['stage' => $stage], false) }}?{{ http_build_query(['domain' => $key, 'period' => $period, 'bus' => $selectedBus !== 'all' ? $selectedBus : null]) }}"
                            class="{{ $domain === $key ? 'active' : '' }}"
                        >
                            <i class="fa-solid {{ $meta[1] }}"></i>
                            <span>{{ $meta[0] }}</span>
                        </a>
                    @endforeach
                </nav>

                <form method="GET" action="{{ route('analytics.stage', ['stage' => $stage], false) }}" class="analytics-stage-filters">
                    <input type="hidden" name="domain" value="{{ $domain }}">

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
                            <option value="all">All Buses</option>
                            @foreach($busOptions as $busNo)
                                <option value="{{ $busNo }}" @selected(strtoupper($selectedBus) === $busNo)>{{ $busNo }}</option>
                            @endforeach
                        </select>
                    </label>

                    <button type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
                </form>
            </section>

            @if($stage === 'descriptive')
                @include('Admin.Analytics.sections.descriptive')
            @elseif($domain === 'fuel')
                @include('Admin.Analytics.sections.fuel-' . $stage)
            @elseif(in_array($domain, ['bus-health', 'inventory'], true))
                <section class="analytics-domain-section analytics-domain-pending">
                    <div class="analytics-card analytics-pending-card">
                        <i class="fa-solid {{ $domain === 'bus-health' ? 'fa-heart-pulse' : 'fa-boxes-stacked' }}"></i>
                        <div>
                            <strong>{{ $domain === 'bus-health' ? 'Bus Health' : 'Inventory' }} {{ strtolower($stageLabel) }} analytics is not yet connected.</strong>
                            <p>No values are being invented. This domain will appear here once its supporting evidence is implemented.</p>
                        </div>
                    </div>
                </section>
            @else
                @include('Admin.Analytics.sections.' . $stage)
                @if($domain === 'all')
                    @include('Admin.Analytics.sections.fuel-' . $stage)
                @endif
            @endif
        </main>
    </div>
</x-layout.app>
