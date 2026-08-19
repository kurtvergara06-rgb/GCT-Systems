@php
    $activePct = $totalBuses > 0 ? ($activeBuses / $totalBuses) * 100 : 0.0;
    $maintenancePct = $totalBuses > 0 ? ($underMaintenance / $totalBuses) * 100 : 0.0;
    $inactivePct = $totalBuses > 0 ? ($inactiveBuses / $totalBuses) * 100 : 0.0;
    $unspecifiedBuses = max(0, $totalBuses - $activeBuses - $underMaintenance - $inactiveBuses);
    $unspecifiedPct = $totalBuses > 0 ? ($unspecifiedBuses / $totalBuses) * 100 : 0.0;
    $recognizedBuses = max(0, $totalBuses - $unspecifiedBuses);
    $statusCoverage = $totalBuses > 0 ? ($recognizedBuses / $totalBuses) * 100 : 0.0;
    $maintenanceEndPct = $activePct + $maintenancePct;
    $attentionBuses = $buses
        ->filter(fn ($bus) => in_array((string) $bus->status, ['Under Maintenance', 'Inactive'], true))
        ->take(5);
@endphp

<section class="bus-health-kpi-strip">
    <article class="bus-health-kpi-card tone-green">
        <span class="bus-health-kpi-icon"><i class="fa-solid fa-bus"></i></span>
        <div>
            <span>Active Buses</span>
            <strong>{{ number_format($activeBuses) }}</strong>
            <small>Available buses</small>
            <em>{{ number_format($activePct, 1) }}% of total fleet</em>
        </div>
    </article>

    <article class="bus-health-kpi-card tone-yellow">
        <span class="bus-health-kpi-icon"><i class="fa-solid fa-screwdriver-wrench"></i></span>
        <div>
            <span>Under Maintenance</span>
            <strong>{{ number_format($underMaintenance) }}</strong>
            <small>Current Bus Master List status</small>
            <em>{{ number_format($maintenancePct, 1) }}% of total fleet</em>
        </div>
    </article>

    <article class="bus-health-kpi-card tone-red">
        <span class="bus-health-kpi-icon"><i class="fa-solid fa-circle-exclamation"></i></span>
        <div>
            <span>Inactive Buses</span>
            <strong>{{ number_format($inactiveBuses) }}</strong>
            <small>Unavailable buses</small>
            <em>{{ number_format($inactivePct, 1) }}% of total fleet</em>
        </div>
    </article>

    <article class="bus-health-kpi-card tone-blue">
        <span class="bus-health-kpi-icon"><i class="fa-solid fa-chart-pie"></i></span>
        <div>
            <span>Fleet Availability</span>
            <strong>{{ number_format($fleetAvailability, 1) }}%</strong>
            <small>Active share of Bus Master List</small>
            <em>{{ number_format($activeBuses) }} of {{ number_format($totalBuses) }} buses active</em>
        </div>
    </article>
</section>

<section class="bus-health-dashboard-grid">
    <x-analytics.panel
        class="bus-health-panel bus-health-status-panel"
        title="Fleet Status Overview"
        description="Current operational status from the Bus Master List"
        :badge="$totalBuses . ' buses'"
    >
        <div class="bus-health-status-overview">
            <div
                class="bus-health-donut"
                style="--active-pct: {{ number_format($activePct, 2, '.', '') }}; --maintenance-end-pct: {{ number_format($maintenanceEndPct, 2, '.', '') }};"
            >
                <div class="bus-health-donut-center">
                    <strong>{{ number_format($fleetAvailability, 1) }}%</strong>
                    <span>Fleet Availability</span>
                </div>
            </div>

            <div class="bus-health-status-list">
                <div class="bus-health-status-row">
                    <span><i class="status-dot active"></i>Active</span>
                    <strong>{{ $activeBuses }} <small>{{ number_format($activePct, 1) }}%</small></strong>
                </div>
                <div class="bus-health-status-row">
                    <span><i class="status-dot maintenance"></i>Under Maintenance</span>
                    <strong>{{ $underMaintenance }} <small>{{ number_format($maintenancePct, 1) }}%</small></strong>
                </div>
                <div class="bus-health-status-row">
                    <span><i class="status-dot inactive"></i>Inactive</span>
                    <strong>{{ $inactiveBuses }} <small>{{ number_format($inactivePct, 1) }}%</small></strong>
                </div>
                <div class="bus-health-status-total">
                    <span>Total Buses</span>
                    <strong>{{ $totalBuses }}</strong>
                </div>
            </div>
        </div>
    </x-analytics.panel>

    <x-analytics.panel
        class="bus-health-panel bus-health-mix-panel"
        title="Current Status Mix"
        description="Current fleet distribution; no historical health trend is inferred"
    >
        <div class="bus-health-mix-list">
            <div class="bus-health-mix-row tone-green">
                <div class="bus-health-mix-heading"><span>Active</span><strong>{{ $activeBuses }} <small>{{ number_format($activePct, 1) }}%</small></strong></div>
                <div class="bus-health-progress"><span style="width: {{ min(100, $activePct) }}%"></span></div>
            </div>
            <div class="bus-health-mix-row tone-yellow">
                <div class="bus-health-mix-heading"><span>Under Maintenance</span><strong>{{ $underMaintenance }} <small>{{ number_format($maintenancePct, 1) }}%</small></strong></div>
                <div class="bus-health-progress"><span style="width: {{ min(100, $maintenancePct) }}%"></span></div>
            </div>
            <div class="bus-health-mix-row tone-red">
                <div class="bus-health-mix-heading"><span>Inactive</span><strong>{{ $inactiveBuses }} <small>{{ number_format($inactivePct, 1) }}%</small></strong></div>
                <div class="bus-health-progress"><span style="width: {{ min(100, $inactivePct) }}%"></span></div>
            </div>
        </div>

        <div class="bus-health-availability-callout">
            <span class="bus-health-callout-icon"><i class="fa-solid fa-chart-line"></i></span>
            <div>
                <strong>{{ number_format($fleetAvailability, 1) }}% currently available</strong>
                <small>{{ number_format($activeBuses) }} active buses out of {{ number_format($totalBuses) }} recorded units</small>
            </div>
        </div>
    </x-analytics.panel>

    <x-analytics.panel
        class="bus-health-panel bus-health-units-panel"
        title="Current Fleet Units"
        description="Real-time status snapshot from the Bus Master List"
        :badge="'5 of ' . $totalBuses"
    >
        <div class="bus-health-unit-list">
            @forelse($buses->take(5) as $bus)
                @php
                    $statusClass = strtolower(str_replace(' ', '-', trim((string) ($bus->status ?: 'Unspecified'))));
                @endphp
                <div class="bus-health-unit-row">
                    <span class="bus-health-unit-icon"><i class="fa-solid fa-bus"></i></span>
                    <div class="bus-health-unit-copy">
                        <strong>{{ $bus->bus_no }}</strong>
                        <span>{{ $bus->bus_model ?: 'Model not recorded' }}{{ $bus->plate_no ? ' · ' . $bus->plate_no : '' }}</span>
                    </div>
                    <span class="bus-health-unit-status {{ $statusClass }}">{{ $bus->status ?: 'Unspecified' }}</span>
                </div>
            @empty
                <div class="analytics-compact-empty"><i class="fa-solid fa-bus"></i><span>No buses are recorded in the Bus Master List.</span></div>
            @endforelse
        </div>
    </x-analytics.panel>
</section>

<section class="bus-health-lower-grid">
    <x-analytics.panel
        class="bus-health-panel bus-health-coverage-panel"
        title="Status Coverage"
        description="How completely current fleet units are classified"
    >
        <div class="bus-health-coverage-score">
            <div>
                <strong>{{ number_format($statusCoverage, 1) }}%</strong>
                <span>recognized status coverage</span>
            </div>
            <small>{{ $recognizedBuses }} of {{ $totalBuses }} buses use Active, Under Maintenance, or Inactive status.</small>
        </div>

        <div class="bus-health-coverage-list">
            <div><span><i class="status-dot active"></i>Active</span><strong>{{ $activeBuses }}</strong></div>
            <div><span><i class="status-dot maintenance"></i>Under Maintenance</span><strong>{{ $underMaintenance }}</strong></div>
            <div><span><i class="status-dot inactive"></i>Inactive</span><strong>{{ $inactiveBuses }}</strong></div>
            <div><span><i class="status-dot unspecified"></i>Unspecified</span><strong>{{ $unspecifiedBuses }}</strong></div>
        </div>
    </x-analytics.panel>

    <x-analytics.panel
        class="bus-health-panel bus-health-attention-panel"
        title="Buses Requiring Attention"
        description="Current units that are not in Active status"
        :badge="($underMaintenance + $inactiveBuses) . ' buses'"
    >
        <div class="bus-health-attention-list">
            @forelse($attentionBuses as $bus)
                @php
                    $statusClass = strtolower(str_replace(' ', '-', trim((string) ($bus->status ?: 'Unspecified'))));
                @endphp
                <div class="bus-health-attention-row">
                    <span class="bus-health-attention-icon {{ $statusClass }}"><i class="fa-solid {{ $bus->status === 'Inactive' ? 'fa-circle-exclamation' : 'fa-screwdriver-wrench' }}"></i></span>
                    <div>
                        <strong>{{ $bus->bus_no }}</strong>
                        <small>{{ $bus->bus_model ?: 'Model not recorded' }}{{ $bus->plate_no ? ' · ' . $bus->plate_no : '' }}</small>
                    </div>
                    <span class="bus-health-unit-status {{ $statusClass }}">{{ $bus->status ?: 'Unspecified' }}</span>
                </div>
            @empty
                <div class="analytics-compact-empty"><i class="fa-solid fa-circle-check"></i><span>No buses currently require status follow-up.</span></div>
            @endforelse
        </div>
    </x-analytics.panel>

    <x-analytics.panel
        class="bus-health-panel bus-health-boundary-panel"
        title="Health Data Boundary Insights"
        description="What this descriptive view can verify from current records"
    >
        <div class="bus-health-boundary-list">
            <div class="bus-health-boundary-row verified">
                <span><i class="fa-solid fa-circle-check"></i></span>
                <div><strong>Operational status is available</strong><small>Active, Under Maintenance, and Inactive come directly from the Bus Master List.</small></div>
                <b>Verified</b>
            </div>
            <div class="bus-health-boundary-row neutral">
                <span><i class="fa-solid fa-shield-heart"></i></span>
                <div><strong>Mechanical condition is not inferred</strong><small>This page does not label a bus mechanically healthy without PMS or maintenance-condition evidence.</small></div>
                <b>No Assumption</b>
            </div>
            <div class="bus-health-boundary-row recorded">
                <span><i class="fa-solid fa-database"></i></span>
                <div><strong>Current status is record-based</strong><small>Counts shown here reflect the current Bus Master List status values available to this descriptive view.</small></div>
                <b>As Recorded</b>
            </div>
        </div>
    </x-analytics.panel>
</section>
