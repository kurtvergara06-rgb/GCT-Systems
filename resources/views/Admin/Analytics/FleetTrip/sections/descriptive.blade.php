<section class="analytics-stage analytics-stage-clean">
    <section class="analytics-kpi-strip">
        <x-analytics.kpi label="Distance Traveled" :value="number_format($totalDistance, 1) . ' km'" small="Sum of processed GPS mileage" icon="fa-road" />
        <x-analytics.kpi label="Average Speed" :value="number_format($averageSpeed, 1) . ' km/h'" small="Distance divided by recorded motion time" icon="fa-gauge-high" tone="green" />
        <x-analytics.kpi label="Idle Time" :value="number_format($totalIdleMinutes / 60, 1) . ' hrs'" small="Total recorded idling time" icon="fa-hourglass-half" tone="yellow" />
        <x-analytics.kpi label="Avg. Trip Duration" :value="number_format($averageTripDuration, 1) . ' min'" small="Average recorded trip duration" icon="fa-clock" tone="purple" />
    </section>

    @php
        $trendMax = max(1, (int) $trend->max('count'));
        $trendCount = max(1, $trend->count());
        $linePoints = $trend->map(function ($bucket, $index) use ($trendMax, $trendCount) {
            $x = $trendCount > 1 ? 42 + (($index / ($trendCount - 1)) * 636) : 360;
            $y = 194 - (($bucket->count / $trendMax) * 150);
            return ['x' => round($x, 1), 'y' => round($y, 1), 'label' => $bucket->label, 'count' => $bucket->count];
        });
        $polyline = $linePoints->map(fn ($point) => $point['x'] . ',' . $point['y'])->implode(' ');
        $areaPoints = $polyline . ' 678,194 42,194';
    @endphp

    <section class="analytics-main-grid analytics-main-grid-balanced">
        <article class="analytics-card analytics-reference-chart-card">
            <x-analytics.card-header
                title="Processed Trip Activity"
                description="Trip-record volume across the selected analysis window."
                :badge="$tripGrowth !== null ? (($tripGrowth >= 0 ? '+' : '') . number_format($tripGrowth, 1) . '% vs prior period') : 'No prior baseline'"
            />

            <div class="reference-line-chart" role="img" aria-label="Processed trip activity trend">
                <svg viewBox="0 0 720 230" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="tripActivityFill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#2f6ee5" stop-opacity="0.24" />
                            <stop offset="100%" stop-color="#2f6ee5" stop-opacity="0.02" />
                        </linearGradient>
                    </defs>
                    @foreach([44, 81.5, 119, 156.5, 194] as $gridY)
                        <line x1="42" y1="{{ $gridY }}" x2="678" y2="{{ $gridY }}" class="reference-chart-grid" />
                    @endforeach
                    <polygon points="{{ $areaPoints }}" fill="url(#tripActivityFill)" />
                    @if($linePoints->count() > 1)
                        <polyline points="{{ $polyline }}" class="reference-chart-line" />
                    @endif
                    @foreach($linePoints as $point)
                        <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5" class="reference-chart-dot" />
                        <text x="{{ $point['x'] }}" y="{{ max(18, $point['y'] - 12) }}" text-anchor="middle" class="reference-chart-value">{{ $point['count'] }}</text>
                        <text x="{{ $point['x'] }}" y="218" text-anchor="middle" class="reference-chart-label">{{ $point['label'] }}</text>
                    @endforeach
                </svg>
            </div>
            <div class="reference-chart-legend"><span><i></i> Trips Processed</span></div>
        </article>

        <article class="analytics-card">
            <x-analytics.card-header title="Fleet Availability" description="Current Bus Master List status." :badge="number_format($totalBuses) . ' buses'" />
            <div class="analytics-availability-layout">
                <div class="availability-score"><div class="availability-ring" style="--availability-angle: {{ min(360, max(0, $fleetAvailability * 3.6)) }}deg;"><div class="availability-ring-center"><strong>{{ number_format($fleetAvailability, 1) }}%</strong><span>Active</span></div></div></div>
                <div class="availability-breakdown">
                    <div class="availability-row"><div><span class="availability-dot operational"></span><span>Active</span></div><strong>{{ $activeBuses }}</strong></div>
                    <div class="availability-row"><div><span class="availability-dot maintenance"></span><span>Under Maintenance</span></div><strong>{{ $underMaintenance }}</strong></div>
                    <div class="availability-row"><div><span class="availability-dot inactive"></span><span>Inactive</span></div><strong>{{ $inactiveBuses }}</strong></div>
                    <div class="availability-total"><span>Total Buses</span><strong>{{ $totalBuses }}</strong></div>
                </div>
            </div>
        </article>
    </section>

    <section class="analytics-list-grid descriptive-ranking-grid">
        <article class="analytics-card ranking-card">
            <x-analytics.card-header title="Top Routes by Trips" :description="$periodLabel . ' · highest-volume corridors'" :badge="'Top ' . $routes->count()" />
            <div class="ranking-list refined-ranking-list">
                @forelse($routes as $route)
                    <div class="refined-ranking-row">
                        <span class="refined-rank-number">{{ $loop->iteration }}</span>
                        <div class="refined-ranking-main">
                            <div class="refined-ranking-title-row"><strong>{{ $route->label }}</strong><span>{{ number_format($route->trips) }} trips</span></div>
                            <div class="metric-bar refined-metric-bar"><span style="width: {{ $route->progress }}%"></span></div>
                            <div class="refined-ranking-meta"><span><i class="fa-regular fa-clock"></i>{{ number_format($route->average_duration, 1) }} min avg.</span><span><i class="fa-solid fa-chart-pie"></i>{{ number_format($route->share, 1) }}% of trips</span></div>
                        </div>
                    </div>
                @empty
                    <p class="ranking-empty">No route records match the current filter.</p>
                @endforelse
            </div>
        </article>

        <article class="analytics-card ranking-card">
            <x-analytics.card-header title="Busiest Buses" :description="$periodLabel . ' · highest recorded trip activity'" :badge="'Top ' . $busActivity->count()" />
            <div class="ranking-list refined-ranking-list">
                @forelse($busActivity as $bus)
                    <div class="refined-ranking-row">
                        <span class="refined-rank-number">{{ $loop->iteration }}</span>
                        <div class="refined-ranking-main">
                            <div class="refined-ranking-title-row"><strong>{{ $bus->bus }}</strong><span>{{ number_format($bus->trips) }} trips</span></div>
                            <div class="metric-bar refined-metric-bar"><span style="width: {{ min(100, max(4, $bus->share * 6)) }}%"></span></div>
                            <div class="refined-ranking-meta"><span><i class="fa-solid fa-road"></i>{{ number_format($bus->distance, 1) }} km</span><span><i class="fa-solid fa-chart-pie"></i>{{ number_format($bus->share, 1) }}% trip share</span></div>
                        </div>
                    </div>
                @empty
                    <p class="ranking-empty">No bus activity matches the current filter.</p>
                @endforelse
            </div>
        </article>
    </section>
</section>
