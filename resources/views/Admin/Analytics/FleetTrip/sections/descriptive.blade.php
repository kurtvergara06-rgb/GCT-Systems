<section class="analytics-stage analytics-stage-clean">
    <section class="analytics-kpi-strip">
        <article class="analytics-kpi">
            <div class="analytics-kpi-icon"><i class="fa-solid fa-road"></i></div>
            <div><span>Distance Traveled</span><strong>{{ number_format($totalDistance, 1) }} km</strong><small>Sum of processed GPS mileage</small></div>
        </article>
        <article class="analytics-kpi">
            <div class="analytics-kpi-icon green"><i class="fa-solid fa-gauge-high"></i></div>
            <div><span>Average Speed</span><strong>{{ number_format($averageSpeed, 1) }} km/h</strong><small>Distance divided by recorded motion time</small></div>
        </article>
        <article class="analytics-kpi">
            <div class="analytics-kpi-icon yellow"><i class="fa-solid fa-hourglass-half"></i></div>
            <div><span>Idle Time</span><strong>{{ number_format($totalIdleMinutes / 60, 1) }} hrs</strong><small>Total recorded idling time</small></div>
        </article>
        <article class="analytics-kpi">
            <div class="analytics-kpi-icon purple"><i class="fa-solid fa-clock"></i></div>
            <div><span>Avg. Trip Duration</span><strong>{{ number_format($averageTripDuration, 1) }} min</strong><small>Average recorded trip duration</small></div>
        </article>
    </section>

    <section class="analytics-main-grid analytics-main-grid-balanced">
        <article class="analytics-card analytics-chart-card">
            <div class="analytics-card-header">
                <div><h3>Processed Trip Activity</h3><p>Trip-record volume across the selected analysis window.</p></div>
                @if($tripGrowth !== null)
                    <span class="analytics-card-badge">{{ $tripGrowth >= 0 ? '+' : '' }}{{ number_format($tripGrowth, 1) }}% vs prior period</span>
                @else
                    <span class="analytics-card-badge">No prior baseline</span>
                @endif
            </div>
            @php
                $trendMax = max(1, (int) $trend->max('count'));
                $scale = [$trendMax, (int) round($trendMax * .75), (int) round($trendMax * .5), (int) round($trendMax * .25), 0];
            @endphp
            <div class="trip-chart-area">
                <div class="chart-scale">@foreach($scale as $scaleValue)<span>{{ $scaleValue }}</span>@endforeach</div>
                <div class="trip-bars">
                    <div class="chart-grid-line grid-1"></div><div class="chart-grid-line grid-2"></div><div class="chart-grid-line grid-3"></div><div class="chart-grid-line grid-4"></div>
                    @foreach($trend as $bucket)
                        <div class="trip-bar-column"><span class="bar-value">{{ $bucket->count }}</span><div class="trip-bar" style="height: {{ $bucket->height }}%;"></div><small>{{ $bucket->label }}</small></div>
                    @endforeach
                </div>
            </div>
        </article>

        <article class="analytics-card">
            <div class="analytics-card-header"><div><h3>Fleet Availability</h3><p>Current Bus Master List status.</p></div><span class="analytics-card-badge">{{ number_format($totalBuses) }} buses</span></div>
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

    <section class="analytics-list-grid">
        <article class="analytics-card">
            <div class="analytics-card-header"><div><h3>Top Routes by Trips</h3><p>{{ $periodLabel }}</p></div></div>
            <div class="table-responsive">
                <table class="analytics-table analytics-ranking-table">
                    <thead><tr><th>#</th><th>Route</th><th>Trips</th><th>Avg. Duration</th><th>Share</th></tr></thead>
                    <tbody>
                        @forelse($routes as $route)
                            <tr><td><span class="analytics-rank-index">{{ $loop->iteration }}</span></td><td><strong>{{ $route->label }}</strong><div class="metric-bar"><span style="width: {{ $route->progress }}%"></span></div></td><td>{{ number_format($route->trips) }}</td><td>{{ number_format($route->average_duration, 1) }} min</td><td>{{ number_format($route->share, 1) }}%</td></tr>
                        @empty
                            <tr><td colspan="5">No route records match the current filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="analytics-card">
            <div class="analytics-card-header"><div><h3>Busiest Buses</h3><p>{{ $periodLabel }}</p></div></div>
            <div class="table-responsive">
                <table class="analytics-table analytics-ranking-table">
                    <thead><tr><th>#</th><th>Bus</th><th>Trips</th><th>Distance</th><th>Trip Share</th></tr></thead>
                    <tbody>
                        @forelse($busActivity as $bus)
                            <tr><td><span class="analytics-rank-index">{{ $loop->iteration }}</span></td><td><strong>{{ $bus->bus }}</strong></td><td>{{ number_format($bus->trips) }}</td><td>{{ number_format($bus->distance, 1) }} km</td><td>{{ number_format($bus->share, 1) }}%</td></tr>
                        @empty
                            <tr><td colspan="5">No bus activity matches the current filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </section>
</section>
