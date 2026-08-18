<section class="analytics-kpi-strip analytics-domain-kpi-four">
    <x-analytics.kpi label="Active" :value="$activeBuses" small="Available buses" icon="fa-bus" tone="green" />
    <x-analytics.kpi label="Under Maintenance" :value="$underMaintenance" small="Current Bus Master List status" icon="fa-screwdriver-wrench" tone="yellow" />
    <x-analytics.kpi label="Inactive" :value="$inactiveBuses" small="Unavailable buses" icon="fa-circle-pause" tone="red" />
    <x-analytics.kpi label="Fleet Availability" :value="number_format($fleetAvailability, 1) . '%'" small="Active share of Bus Master List" icon="fa-chart-pie" />
</section>

<section class="analytics-domain-content">
    <div class="analytics-domain-grid">
        <x-analytics.panel title="Fleet Status Distribution" description="Current operational status from the Bus Master List" :badge="$totalBuses . ' buses'">
            <div class="analytics-status-overview"><div class="analytics-status-ring" style="--ring-active: {{ $activeAngle }}deg; --ring-maintenance: {{ $maintenanceAngle }}deg;"><div><strong>{{ number_format($fleetAvailability, 1) }}%</strong><span>currently active</span></div></div><div class="analytics-status-legend"><div class="analytics-status-legend-row"><span><i class="analytics-status-dot green"></i>Active</span><strong>{{ $activeBuses }}</strong></div><div class="analytics-status-legend-row"><span><i class="analytics-status-dot yellow"></i>Under Maintenance</span><strong>{{ $underMaintenance }}</strong></div><div class="analytics-status-legend-row"><span><i class="analytics-status-dot red"></i>Inactive</span><strong>{{ $inactiveBuses }}</strong></div></div></div>
        </x-analytics.panel>

        <x-analytics.panel title="Current Fleet Units" description="Status snapshot; this is not a mechanical diagnosis">
            <div class="analytics-record-list">@forelse($buses->take(8) as $bus)<div class="analytics-record-row"><span class="analytics-record-icon"><i class="fa-solid fa-bus"></i></span><div class="analytics-record-copy"><strong>{{ $bus->bus_no }}</strong><span>{{ $bus->bus_model ?: 'Model not recorded' }}{{ $bus->plate_no ? ' · ' . $bus->plate_no : '' }}</span></div><span class="analytics-record-value">{{ $bus->status ?: 'Unspecified' }}</span></div>@empty<div class="analytics-compact-empty"><i class="fa-solid fa-bus"></i><span>No buses are recorded in the Bus Master List.</span></div>@endforelse</div>
        </x-analytics.panel>
    </div>

    <x-analytics.panel title="Bus Health Data Boundary" description="What this descriptive view can verify from current records">
        <div class="analytics-record-list"><div class="analytics-record-row"><span class="analytics-record-icon"><i class="fa-solid fa-circle-check"></i></span><div class="analytics-record-copy"><strong>Operational status is available</strong><span>Active, Under Maintenance, and Inactive come directly from the Bus Master List.</span></div><span class="analytics-record-value">Verified</span></div><div class="analytics-record-row"><span class="analytics-record-icon"><i class="fa-solid fa-shield-heart"></i></span><div class="analytics-record-copy"><strong>Mechanical condition is not inferred</strong><span>This page does not label a bus mechanically healthy without PMS or maintenance-condition evidence.</span></div><span class="analytics-record-value">No assumption</span></div></div>
    </x-analytics.panel>
</section>
