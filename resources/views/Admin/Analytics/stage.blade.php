@php
    $stageDescriptions = [
        'descriptive' => 'What happened across the operation based on recorded data.',
        'diagnostic' => 'What currently requires investigation and which measurable signals support the review.',
        'predictive' => 'What may happen next based on validated historical evidence and forecast readiness.',
        'prescriptive' => 'What actions should be considered, with the operator remaining in control.',
    ];

    $domains = [
        'all' => ['label' => 'All', 'icon' => 'fa-layer-group'],
        'fleet-trip' => ['label' => 'Fleet & Trip', 'icon' => 'fa-route'],
        'fuel' => ['label' => 'Fuel', 'icon' => 'fa-gas-pump'],
        'bus-health' => ['label' => 'Bus Health', 'icon' => 'fa-heart-pulse'],
        'inventory' => ['label' => 'Inventory', 'icon' => 'fa-boxes-stacked'],
    ];

    $stageUrl = fn (string $targetStage, ?string $targetDomain = null) => route(
        'analytics.stage',
        ['stage' => $targetStage],
        false
    ) . '?' . http_build_query(array_filter([
        'domain' => $targetDomain ?? $domain,
        'period' => $period,
        'bus' => $selectedBus !== 'all' ? $selectedBus : null,
    ]));

    $domainUrl = fn (string $targetDomain) => $stageUrl($stage, $targetDomain);
    $showFleet = in_array($domain, ['all', 'fleet-trip'], true);
    $showFuel = in_array($domain, ['all', 'fuel'], true);
    $showBusHealth = in_array($domain, ['all', 'bus-health'], true);
    $showInventory = in_array($domain, ['all', 'inventory'], true);

    $diagnostics = $fleet['diagnostics'] ?? null;
    $prediction = $fleet['prediction'] ?? null;
    $fuelReviewUnits = collect($fuel['reviewUnits'] ?? []);
    $fuelRecommendations = collect($fuel['recommendations'] ?? []);
    $fuelForecast = $fuel['forecast'] ?? null;
@endphp

<x-layout.app
    title="FROMS - {{ $stageLabel }} Analytics"
    :assets="[
        'resources/css/Admin/Analytics/fleet-trip.css',
        'resources/css/Admin/Analytics/fleet-trip-redesign.css',
        'resources/css/Admin/Analytics/analytics-stage-hub.css',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main analytics-stage-page fleet-trip-page">
            <x-layout.topbar
                :title="'Analytics — ' . $stageLabel"
                :subtitle="$stageDescriptions[$stage]"
            />

            <section class="analytics-stage-switcher" aria-label="Analytics method">
                @foreach([
                    'descriptive' => '5.1 Descriptive',
                    'diagnostic' => '5.2 Diagnostic',
                    'predictive' => '5.3 Predictive',
                    'prescriptive' => '5.4 Prescriptive',
                ] as $stageKey => $label)
                    <a href="{{ $stageUrl($stageKey) }}" class="{{ $stage === $stageKey ? 'active' : '' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </section>

            <section class="analytics-domain-toolbar">
                <nav class="analytics-domain-tabs" aria-label="Analytics domain">
                    @foreach($domains as $domainKey => $domainMeta)
                        <a href="{{ $domainUrl($domainKey) }}" class="{{ $domain === $domainKey ? 'active' : '' }}">
                            <i class="fa-solid {{ $domainMeta['icon'] }}"></i>
                            <span>{{ $domainMeta['label'] }}</span>
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
                @if($showFleet)
                    <section class="analytics-domain-section">
                        <div class="analytics-domain-heading">
                            <div><span>Fleet & Trip</span><h2>Recorded operational performance</h2></div>
                            <a href="{{ route('analytics.fleet-trip', [], false) }}?section=descriptive&period={{ $period }}&bus={{ strtoupper($selectedBus) }}">Open detailed workspace <i class="fa-solid fa-arrow-right"></i></a>
                        </div>

                        <section class="analytics-kpi-strip">
                            <x-analytics.kpi label="Processed Trips" :value="number_format($fleet['tripCount'] ?? 0)" small="Processed GPS trip records" icon="fa-bus" />
                            <x-analytics.kpi label="Distance Traveled" :value="number_format($fleet['totalDistance'] ?? 0, 1) . ' km'" small="Sum of processed GPS mileage" icon="fa-road" tone="green" />
                            <x-analytics.kpi label="Average Speed" :value="number_format($fleet['averageSpeed'] ?? 0, 1) . ' km/h'" small="Distance over recorded motion time" icon="fa-gauge-high" tone="purple" />
                            <x-analytics.kpi label="Idle Time" :value="number_format(($fleet['totalIdleMinutes'] ?? 0) / 60, 1) . ' hrs'" small="Total recorded idling" icon="fa-hourglass-half" tone="yellow" />
                        </section>
                    </section>
                @endif

                @if($showFuel)
                    <section class="analytics-domain-section">
                        <div class="analytics-domain-heading">
                            <div><span>Fuel</span><h2>Recorded fuel performance</h2></div>
                            <a href="{{ route('analytics.fuel', [], false) }}?period={{ $period }}&bus={{ $selectedBus }}">Open detailed workspace <i class="fa-solid fa-arrow-right"></i></a>
                        </div>

                        <section class="analytics-kpi-strip">
                            <x-analytics.kpi label="Fuel Used" :value="number_format($fuel['totalFuel'] ?? 0, 1) . ' L'" small="Recorded fuel volume" icon="fa-gas-pump" />
                            <x-analytics.kpi label="Linked Distance" :value="number_format($fuel['totalDistance'] ?? 0, 1) . ' km'" small="Distance attached to fuel reports" icon="fa-road" tone="green" />
                            <x-analytics.kpi label="Weighted Efficiency" :value="number_format($fuel['fleetAverage'] ?? 0, 2) . ' km/L'" small="Distance divided by recorded fuel" icon="fa-gauge-high" tone="purple" />
                            <x-analytics.kpi label="Units Recorded" :value="number_format(collect($fuel['busSummaries'] ?? [])->count())" small="Buses represented in selected records" icon="fa-bus" tone="yellow" />
                        </section>
                    </section>
                @endif
            @elseif($stage === 'diagnostic')
                @if($showFleet && $diagnostics)
                    <section class="analytics-domain-section">
                        <div class="analytics-domain-heading">
                            <div><span>Fleet & Trip</span><h2>Trip review signals</h2></div>
                            <a href="{{ route('analytics.fleet-trip', [], false) }}?section=diagnostic&period={{ $period }}&bus={{ strtoupper($selectedBus) }}">Investigate records <i class="fa-solid fa-arrow-right"></i></a>
                        </div>

                        <section class="analytics-kpi-strip">
                            <x-analytics.kpi label="Trips Requiring Review" :value="number_format($diagnostics->review_count)" small="At least one review indicator" icon="fa-magnifying-glass-chart" />
                            <x-analytics.kpi label="Delay Indicators" :value="number_format($diagnostics->delay_count)" small="Above route delay threshold" icon="fa-clock-rotate-left" tone="red" />
                            <x-analytics.kpi label="Slow Movement" :value="number_format($diagnostics->slow_movement_count)" small="Below route speed baseline" icon="fa-gauge-simple-low" tone="yellow" />
                            <x-analytics.kpi label="High Idling" :value="number_format($diagnostics->high_idle_count)" small="Strict idle-duration/share rule" icon="fa-hourglass-half" tone="purple" />
                        </section>
                    </section>
                @endif

                @if($showFuel)
                    <section class="analytics-domain-section">
                        <div class="analytics-domain-heading">
                            <div><span>Fuel</span><h2>Fuel efficiency review signals</h2></div>
                            <a href="{{ route('analytics.fuel', [], false) }}?period={{ $period }}&bus={{ $selectedBus }}">Review fuel evidence <i class="fa-solid fa-arrow-right"></i></a>
                        </div>

                        <section class="analytics-kpi-strip analytics-kpi-strip-three">
                            <x-analytics.kpi label="Units for Review" :value="number_format($fuelReviewUnits->count())" small="Efficiency or idling signals" icon="fa-triangle-exclamation" tone="red" />
                            <x-analytics.kpi label="High-Idling Units" :value="number_format(collect($fuel['highIdlingUnits'] ?? [])->count())" small="Above selected idling baseline" icon="fa-hourglass-half" tone="yellow" />
                            <x-analytics.kpi label="Fleet Efficiency" :value="number_format($fuel['fleetAverage'] ?? 0, 2) . ' km/L'" small="Comparison baseline for selected records" icon="fa-gauge-high" tone="green" />
                        </section>
                    </section>
                @endif
            @elseif($stage === 'predictive')
                @if($showFleet && $prediction)
                    <section class="analytics-domain-section">
                        <div class="analytics-domain-heading">
                            <div><span>Fleet & Trip</span><h2>Upcoming trip forecasts</h2></div>
                            <a href="{{ route('analytics.fleet-trip', [], false) }}?section=predictive&period={{ $period }}&bus={{ strtoupper($selectedBus) }}">Open forecast detail <i class="fa-solid fa-arrow-right"></i></a>
                        </div>

                        <section class="analytics-kpi-strip">
                            <x-analytics.kpi label="Historical Sample" :value="number_format($prediction->historical_records)" small="Processed trips used as history" icon="fa-database" />
                            <x-analytics.kpi label="Upcoming Targets" :value="number_format($prediction->target_count)" small="Scheduled or ready next-7-day trips" icon="fa-bullseye" tone="green" />
                            <x-analytics.kpi label="Predicted Trips" :value="number_format($prediction->predicted_target_count)" small="Targets with comparable history" icon="fa-arrow-trend-up" tone="purple" />
                            <x-analytics.kpi label="Slow Periods" :value="number_format($prediction->peak_periods->count())" small="Historical time blocks outside baseline" icon="fa-clock" tone="yellow" />
                        </section>
                    </section>
                @endif

                @if($showFuel)
                    <section class="analytics-domain-section">
                        <div class="analytics-domain-heading">
                            <div><span>Fuel</span><h2>Short-term fuel demand</h2></div>
                            <a href="{{ route('analytics.fuel', [], false) }}?period={{ $period }}&bus={{ $selectedBus }}">Open forecast evidence <i class="fa-solid fa-arrow-right"></i></a>
                        </div>

                        <section class="analytics-kpi-strip analytics-kpi-strip-three">
                            <x-analytics.kpi label="Forecast Status" :value="$fuelForecast?->available ? 'Ready' : 'Waiting'" :small="$fuelForecast?->available ? 'Sufficient recent recorded-day sample' : 'Needs at least four recent recorded days'" icon="fa-chart-line" tone="green" />
                            <x-analytics.kpi label="Projected 7-Day Fuel" :value="$fuelForecast?->projected_liters !== null ? number_format($fuelForecast->projected_liters, 1) . ' L' : '—'" small="7-day recorded-day baseline" icon="fa-gas-pump" tone="purple" />
                            <x-analytics.kpi label="Recent Sample Days" :value="number_format($fuelForecast?->sample_days ?? 0)" small="Recorded days used for recent baseline" icon="fa-calendar-days" tone="yellow" />
                        </section>
                    </section>
                @endif
            @else
                @if($showFleet && $diagnostics && $prediction)
                    <section class="analytics-domain-section">
                        <div class="analytics-domain-heading">
                            <div><span>Fleet & Trip</span><h2>Decision-support readiness</h2></div>
                            <a href="{{ route('analytics.fleet-trip', [], false) }}?section=prescriptive&period={{ $period }}&bus={{ strtoupper($selectedBus) }}">Open Fleet recommendations <i class="fa-solid fa-arrow-right"></i></a>
                        </div>

                        <section class="analytics-kpi-strip analytics-kpi-strip-three">
                            <x-analytics.kpi label="Diagnostic Inputs" :value="number_format($diagnostics->review_count)" small="Trips currently requiring review" icon="fa-magnifying-glass-chart" />
                            <x-analytics.kpi label="Predictive Inputs" :value="number_format($prediction->predicted_target_count)" small="Upcoming trips with forecasts" icon="fa-chart-line" tone="green" />
                            <x-analytics.kpi label="Decision Mode" value="Advisory" small="Operator approval remains required" icon="fa-user-check" tone="yellow" />
                        </section>
                    </section>
                @endif

                @if($showFuel)
                    <section class="analytics-domain-section">
                        <div class="analytics-domain-heading">
                            <div><span>Fuel</span><h2>Fuel advisory actions</h2></div>
                            <a href="{{ route('analytics.fuel', [], false) }}?period={{ $period }}&bus={{ $selectedBus }}">Open Fuel evidence <i class="fa-solid fa-arrow-right"></i></a>
                        </div>

                        <div class="analytics-recommendation-grid">
                            @foreach($fuelRecommendations as $recommendation)
                                <article class="analytics-card analytics-recommendation-card">
                                    <div class="analytics-recommendation-icon"><i class="fa-solid {{ $recommendation->icon }}"></i></div>
                                    <div><strong>{{ $recommendation->title }}</strong><p>{{ $recommendation->reason }}</p></div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endif

            @if($showBusHealth)
                <section class="analytics-domain-section analytics-domain-pending">
                    <div class="analytics-domain-heading">
                        <div><span>Bus Health</span><h2>{{ $stageLabel }} integration</h2></div>
                        <a href="{{ route('analytics.bus-health', [], false) }}">Open current Bus Health page <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                    <div class="analytics-card analytics-pending-card">
                        <i class="fa-solid fa-heart-pulse"></i>
                        <div><strong>Bus Health has not yet been connected to the unified {{ strtolower($stageLabel) }} layer.</strong><p>No values are being invented here. Its maintenance/PMS evidence will be connected when that domain is implemented.</p></div>
                    </div>
                </section>
            @endif

            @if($showInventory)
                <section class="analytics-domain-section analytics-domain-pending">
                    <div class="analytics-domain-heading">
                        <div><span>Inventory</span><h2>{{ $stageLabel }} integration</h2></div>
                        <a href="{{ route('analytics.inventory', [], false) }}">Open current Inventory page <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                    <div class="analytics-card analytics-pending-card">
                        <i class="fa-solid fa-boxes-stacked"></i>
                        <div><strong>Inventory has not yet been connected to the unified {{ strtolower($stageLabel) }} layer.</strong><p>The unified page will use real stock, movement, and threshold evidence once the Inventory analytics domain is implemented.</p></div>
                    </div>
                </section>
            @endif
        </main>
    </div>
</x-layout.app>
