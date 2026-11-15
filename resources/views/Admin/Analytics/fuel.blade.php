<x-layout.app
    title="FROMS - Fuel Analytics"
    :assets="[
        'resources/css/Admin/Analytics/fuel.css',
    ]"
>
    @php
        $sections = [
            'descriptive' => ['number' => '5.1', 'label' => 'Descriptive'],
            'diagnostic' => ['number' => '5.2', 'label' => 'Diagnostic'],
            'predictive' => ['number' => '5.3', 'label' => 'Predictive'],
            'prescriptive' => ['number' => '5.4', 'label' => 'Prescriptive'],
        ];
        $section = strtolower((string) request('section', 'descriptive'));
        $section = array_key_exists($section, $sections) ? $section : 'descriptive';
        $trendMax = max(1, (float) ($trend->max('fuel_liters') ?? 0));
        $bestUnit = $busSummaries->first();
        $lowestUnit = $busSummaries->filter(fn ($row) => $row->km_per_liter > 0)->sortBy('km_per_liter')->first();
        $gaugeAngle = $bestUnit && $bestUnit->km_per_liter > 0
            ? min(330, max(24, ($fleetAverage / $bestUnit->km_per_liter) * 300))
            : 0;
    @endphp

    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main fuel-analytics-page">
            <x-layout.topbar
                title="Fuel Analytics"
                subtitle="Analyze recorded fuel use, efficiency signals, short-term demand, and advisory actions"
                notification-count="6"
            />

            <section class="fuel-toolbar">
                <nav class="fuel-tabs" aria-label="Fuel analytics views">
                    @foreach($sections as $key => $meta)
                        <a
                            href="{{ route('analytics.fuel', ['section' => $key, 'period' => $period, 'bus' => $selectedBus]) }}"
                            class="fuel-tab {{ $section === $key ? 'active' : '' }}"
                            @if($section === $key) aria-current="page" @endif
                        >
                            <span>{{ $meta['number'] }}</span>
                            {{ $meta['label'] }}
                            @if($key === 'diagnostic' && $reviewUnits->isNotEmpty())
                                <small>{{ $reviewUnits->count() }}</small>
                            @elseif($key === 'predictive' && $forecast->available)
                                <small>Ready</small>
                            @endif
                        </a>
                    @endforeach
                </nav>

                <form class="fuel-filters" method="GET" action="{{ route('analytics.fuel') }}">
                    <input type="hidden" name="section" value="{{ $section }}">
                    <label>
                        <span>Period</span>
                        <select name="period" onchange="this.form.submit()">
                            <option value="this-month" @selected($period === 'this-month')>This Month</option>
                            <option value="last-30-days" @selected($period === 'last-30-days')>Last 30 Days</option>
                            <option value="last-3-months" @selected($period === 'last-3-months')>Last 3 Months</option>
                            <option value="this-year" @selected($period === 'this-year')>This Year</option>
                        </select>
                    </label>

                    <label>
                        <span>Bus</span>
                        <select name="bus" onchange="this.form.submit()">
                            <option value="all" @selected($selectedBus === 'all')>All Buses</option>
                            @foreach($buses as $bus)
                                <option value="{{ $bus->bus_no }}" @selected($selectedBus === $bus->bus_no)>
                                    {{ $bus->bus_no }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </form>
            </section>

            <section class="stats-grid fuel-summary-grid">
                <x-ui.summary-card
                    label="Fuel Used"
                    :value="number_format($totalFuel, 1) . ' L'"
                    small="Recorded fuel volume in the selected period"
                    icon="fa-gas-pump"
                    color="blue"
                />
                <x-ui.summary-card
                    label="Distance Traveled"
                    :value="number_format($totalDistance, 1) . ' km'"
                    small="Distance linked to selected fuel reports"
                    icon="fa-road"
                    color="blue"
                />
                <x-ui.summary-card
                    label="Average Efficiency"
                    :value="number_format($fleetAverage, 2) . ' km/L'"
                    small="Weighted distance divided by recorded fuel"
                    icon="fa-gauge-high"
                    color="green"
                />
                <x-ui.summary-card
                    label="Units for Review"
                    :value="number_format($reviewUnits->count())"
                    small="Evidence-based efficiency or idling review signals"
                    icon="fa-triangle-exclamation"
                    color="yellow"
                />
            </section>

            @if($section === 'descriptive')
                <section class="fuel-grid fuel-grid-primary">
                    <article class="fuel-card analytics-card">
                        <div class="fuel-card-header">
                            <div>
                                <span class="fuel-eyebrow">5.1 Descriptive</span>
                                <h2>Fuel Consumption Trend</h2>
                                <p>Actual recorded fuel totals grouped across the selected period.</p>
                            </div>
                            <span class="fuel-chip">{{ $records->count() }} reports</span>
                        </div>

                        @if($trend->isNotEmpty())
                            <div class="fuel-bar-chart" style="--fuel-column-count: {{ max(1, $trend->count()) }};">
                                @foreach($trend as $point)
                                    @php $height = max(8, ($point->fuel_liters / $trendMax) * 100); @endphp
                                    <div class="fuel-bar-column">
                                        <div class="fuel-bar-value">{{ number_format($point->fuel_liters, 0) }} L</div>
                                        <div class="fuel-bar-track">
                                            <div class="fuel-bar-fill" style="--bar-height: {{ $height }}%;"></div>
                                        </div>
                                        <strong>{{ $point->label }}</strong>
                                        <span>{{ number_format($point->efficiency, 2) }} km/L</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <x-ui.empty-state
                                icon="fa-chart-column"
                                title="No fuel trend yet"
                                message="No fuel reports match the selected period and bus filter."
                            />
                        @endif
                    </article>

                    <article class="fuel-card analytics-card">
                        <div class="fuel-card-header">
                            <div>
                                <span class="fuel-eyebrow">Weighted efficiency</span>
                                <h2>Fuel Efficiency Overview</h2>
                                <p>Distance per liter across all selected fuel records.</p>
                            </div>
                        </div>

                        <div class="fuel-efficiency-layout">
                            <div class="fuel-efficiency-ring" style="--fuel-angle: {{ $gaugeAngle }}deg;">
                                <div>
                                    <strong>{{ number_format($fleetAverage, 2) }}</strong>
                                    <span>km/L</span>
                                </div>
                            </div>

                            <div class="fuel-metric-list">
                                <div>
                                    <span>Best recorded unit</span>
                                    <strong>{{ $bestUnit ? $bestUnit->bus_no . ' · ' . number_format($bestUnit->km_per_liter, 2) . ' km/L' : 'No data' }}</strong>
                                </div>
                                <div>
                                    <span>Fleet weighted average</span>
                                    <strong>{{ number_format($fleetAverage, 2) }} km/L</strong>
                                </div>
                                <div>
                                    <span>Lowest recorded unit</span>
                                    <strong>{{ $lowestUnit ? $lowestUnit->bus_no . ' · ' . number_format($lowestUnit->km_per_liter, 2) . ' km/L' : 'No data' }}</strong>
                                </div>
                            </div>
                        </div>
                    </article>
                </section>

                <section class="fuel-card analytics-card">
                    <div class="fuel-card-header">
                        <div>
                            <h2>Bus Fuel Efficiency Comparison</h2>
                            <p>Real distance, fuel volume, weighted efficiency, and comparison with the selected fleet average.</p>
                        </div>
                        <span class="fuel-chip">{{ $busSummaries->count() }} units</span>
                    </div>

                    <div class="table-wrap">
                        <table class="fuel-table">
                            <thead>
                                <tr>
                                    <th>Bus</th>
                                    <th>Distance</th>
                                    <th>Fuel</th>
                                    <th>Efficiency</th>
                                    <th>Vs Avg</th>
                                    <th>Reports</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($busSummaries as $row)
                                    <tr>
                                        <td><strong>{{ $row->bus_no }}</strong></td>
                                        <td>{{ number_format($row->distance_km, 1) }} km</td>
                                        <td>{{ number_format($row->fuel_liters, 1) }} L</td>
                                        <td><strong>{{ number_format($row->km_per_liter, 2) }} km/L</strong></td>
                                        <td class="{{ $row->vs_average < 0 ? 'fuel-negative' : 'fuel-positive' }}">
                                            {{ $row->vs_average >= 0 ? '+' : '' }}{{ number_format($row->vs_average, 1) }}%
                                        </td>
                                        <td>{{ $row->entries }}</td>
                                        <td><span class="fuel-status {{ str($row->status)->slug() }}">{{ $row->status }}</span></td>
                                    </tr>
                                @empty
                                    <x-ui.empty-row colspan="7" message="No fuel records match the current filters." />
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @elseif($section === 'diagnostic')
                <section class="fuel-grid">
                    <article class="fuel-card analytics-card">
                        <div class="fuel-card-header">
                            <div>
                                <span class="fuel-eyebrow">5.2 Diagnostic</span>
                                <h2>Units Requiring Review</h2>
                                <p>Signals identify records for investigation; they do not prove fuel wastage.</p>
                            </div>
                            <span class="fuel-chip warning">{{ $reviewUnits->count() }} flagged</span>
                        </div>

                        <div class="fuel-review-list" data-scroll-records>
                            @forelse($reviewUnits as $row)
                                <article class="fuel-review-row" data-scroll-record>
                                    <div class="fuel-review-icon"><i class="fa-solid fa-bus"></i></div>
                                    <div class="fuel-review-body">
                                        <div class="fuel-review-head">
                                            <div>
                                                <strong>{{ $row->bus_no }}</strong>
                                                <span>{{ number_format($row->distance_km, 1) }} km · {{ number_format($row->fuel_liters, 1) }} L</span>
                                            </div>
                                            <div class="fuel-review-value">
                                                <strong>{{ number_format($row->km_per_liter, 2) }} km/L</strong>
                                                <span>{{ $row->status }}</span>
                                            </div>
                                        </div>
                                        <div class="fuel-signal-list">
                                            @foreach($row->signals as $signal)
                                                <span><i class="fa-solid fa-circle-exclamation"></i>{{ $signal }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                </article>
                            @empty
                                <x-ui.empty-state icon="fa-circle-check" title="No review signals" message="No selected bus currently crosses the diagnostic review rules." />
                            @endforelse
                        </div>
                    </article>

                    <article class="fuel-card analytics-card">
                        <div class="fuel-card-header">
                            <div>
                                <h2>Diagnostic Evidence Rules</h2>
                                <p>Transparent rules used before a unit appears in the review list.</p>
                            </div>
                        </div>

                        <div class="fuel-rule-list">
                            <div>
                                <i class="fa-solid fa-gauge-high"></i>
                                <span>Low efficiency</span>
                                <strong>&gt;10% below selected fleet average</strong>
                            </div>
                            <div>
                                <i class="fa-solid fa-hourglass-half"></i>
                                <span>High idling candidate</span>
                                <strong>&gt;25% above fleet median and ≥15 min</strong>
                            </div>
                            <div>
                                <i class="fa-solid fa-location-dot"></i>
                                <span>GPS source</span>
                                <strong>Processed GPS records only</strong>
                            </div>
                            <div>
                                <i class="fa-solid fa-shield-halved"></i>
                                <span>Interpretation</span>
                                <strong>Review signal, not confirmed cause</strong>
                            </div>
                        </div>

                        <div class="fuel-center-stat">
                            <strong>{{ number_format($idlingMedian, 1) }}</strong>
                            <span>median idling minutes per 100 km</span>
                        </div>
                    </article>
                </section>
            @elseif($section === 'predictive')
                <section class="fuel-grid">
                    <article class="fuel-card analytics-card">
                        <div class="fuel-card-header">
                            <div>
                                <span class="fuel-eyebrow">5.3 Predictive</span>
                                <h2>7-Day Fuel Demand Baseline</h2>
                                <p>Explainable short-term projection from recent recorded operating days.</p>
                            </div>
                            <span class="fuel-chip {{ $forecast->available ? 'ready' : '' }}">{{ $forecast->available ? 'Ready' : 'Needs data' }}</span>
                        </div>

                        @if($forecast->available)
                            <div class="fuel-forecast-hero">
                                <span>Projected next 7 days</span>
                                <strong>{{ number_format($forecast->projected_liters, 1) }} L</strong>
                                <small>{{ $forecast->method }} · {{ $forecast->sample_days }} recent recorded days</small>
                            </div>

                            <div class="fuel-metric-list compact">
                                <div><span>Recent recorded-day average</span><strong>{{ number_format($forecast->recent_average, 1) }} L/day</strong></div>
                                <div><span>Previous recorded-day average</span><strong>{{ number_format($forecast->previous_average, 1) }} L/day</strong></div>
                                <div>
                                    <span>Baseline change</span>
                                    <strong class="{{ ($forecast->change_percent ?? 0) > 0 ? 'fuel-negative' : 'fuel-positive' }}">
                                        {{ $forecast->change_percent === null ? 'No comparison' : (($forecast->change_percent >= 0 ? '+' : '') . number_format($forecast->change_percent, 1) . '%') }}
                                    </strong>
                                </div>
                            </div>
                        @else
                            <x-ui.empty-state
                                icon="fa-chart-line"
                                title="Forecast needs more recent coverage"
                                message="At least four recorded fuel days in the latest seven-day window are required for this baseline forecast."
                            />
                        @endif
                    </article>

                    <article class="fuel-card analytics-card">
                        <div class="fuel-card-header">
                            <div>
                                <h2>Forecast Readiness</h2>
                                <p>The forecast is statistical and intentionally conservative; it is not presented as machine learning.</p>
                            </div>
                        </div>

                        <div class="fuel-readiness-ring {{ $forecast->available ? 'ready' : '' }}">
                            <div>
                                <strong>{{ $forecast->sample_days }}/7</strong>
                                <span>recent days</span>
                            </div>
                        </div>

                        <div class="fuel-note">
                            <i class="fa-solid fa-circle-info"></i>
                            <p>Projection assumes the recent recorded-day fuel baseline continues for the next seven days. It does not yet model route mix, passenger demand, or fuel price.</p>
                        </div>
                    </article>
                </section>
            @else
                <section class="fuel-card fuel-advisory-boundary">
                    <div>
                        <span class="fuel-eyebrow">5.4 Prescriptive</span>
                        <h2>Advisory decision support only</h2>
                        <p>Recommendations explain what to review next. They never create maintenance work, change routes, or authorize fuel automatically.</p>
                    </div>
                    <div class="fuel-boundary-badges">
                        <span>Decision Mode: Advisory</span>
                        <span>Auto-execute: No</span>
                    </div>
                </section>

                <section class="fuel-recommendation-grid">
                    @foreach($recommendations as $recommendation)
                        <article class="fuel-recommendation-card {{ $recommendation->tone }} analytics-card">
                            <div class="fuel-recommendation-icon"><i class="fa-solid {{ $recommendation->icon }}"></i></div>
                            <span>Recommended review</span>
                            <h3>{{ $recommendation->title }}</h3>
                            <p>{{ $recommendation->reason }}</p>
                        </article>
                    @endforeach
                </section>

                <section class="fuel-card analytics-card">
                    <div class="fuel-card-header">
                        <div>
                            <h2>Evidence Used for Recommendations</h2>
                            <p>Prescriptive output only uses analytics already visible in the preceding sections.</p>
                        </div>
                    </div>
                    <div class="fuel-rule-list">
                        <div><i class="fa-solid fa-gas-pump"></i><span>Fuel reports</span><strong>{{ $records->count() }} selected records</strong></div>
                        <div><i class="fa-solid fa-gauge-high"></i><span>Efficiency baseline</span><strong>{{ number_format($fleetAverage, 2) }} km/L</strong></div>
                        <div><i class="fa-solid fa-triangle-exclamation"></i><span>Diagnostic review</span><strong>{{ $reviewUnits->count() }} unit(s)</strong></div>
                        <div><i class="fa-solid fa-chart-line"></i><span>Forecast layer</span><strong>{{ $forecast->available ? 'Available' : 'Insufficient recent data' }}</strong></div>
                    </div>
                </section>
            @endif
        </main>
    </div>
</x-layout.app>
