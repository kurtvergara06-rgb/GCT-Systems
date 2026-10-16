@php
    $visibleTrips = collect($assignmentHistoryTrips->items());
    $outcomeFor = static function ($trip): string {
        $assigned = $trip->assignments->count() + $trip->externalRentals->where('status', 'Assigned')->count();
        $required = max(1, (int) $trip->required_buses);

        return match (true) {
            $trip->status === 'Cancelled' => 'Cancelled',
            $trip->status === 'Completed' => 'Completed',
            $trip->status === 'Missed' => 'Missed',
            $assigned === 0 => 'Missed',
            $assigned < $required => 'Partially Missed',
            default => 'Scheduled Done',
        };
    };
    $pageDone = $visibleTrips->filter(fn ($trip) => $outcomeFor($trip) === 'Scheduled Done')->count();
    $pagePartial = $visibleTrips->filter(fn ($trip) => $outcomeFor($trip) === 'Partially Missed')->count();
    $pageMissed = $visibleTrips->filter(fn ($trip) => $outcomeFor($trip) === 'Missed')->count();
@endphp

<x-operation.trip-history-layout active-view="assignments" heading="Assignment History" description="Read-only bus and driver assignments, required slots, and scheduling outcomes.">
    <x-slot:kpis>
        <article class="trip-summary-card"><div class="trip-summary-icon blue"><i class="fa-solid fa-clock-rotate-left"></i></div><div><p>Historical Trips</p><h2>{{ number_format($assignmentHistoryTrips->total()) }}</h2><small>All matching records</small></div></article>
        <article class="trip-summary-card"><div class="trip-summary-icon green"><i class="fa-solid fa-calendar-check"></i></div><div><p>Scheduled Done</p><h2>{{ $pageDone }}</h2><small>On this page · scheduled outcome</small></div></article>
        <article class="trip-summary-card"><div class="trip-summary-icon yellow"><i class="fa-solid fa-triangle-exclamation"></i></div><div><p>Partially Missed</p><h2>{{ $pagePartial }}</h2><small>On this page · missing bus slots</small></div></article>
        <article class="trip-summary-card"><div class="trip-summary-icon purple"><i class="fa-solid fa-calendar-xmark"></i></div><div><p>Missed</p><h2>{{ $pageMissed }}</h2><small>On this page · no bus assigned</small></div></article>
    </x-slot:kpis>

    <x-slot:headerAction>
        <a class="trip-export-btn trip-export-header" href="{{ route('trip-records.export', request()->except('view', 'page')) }}">
            <i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export CSV
        </a>
    </x-slot:headerAction>

    <x-slot:toolbar>
        <x-ui.table-toolbar
            action="{{ route('trip-records', ['view' => 'assignments'], false) }}"
            form-id="assignmentHistoryFilters"
            search-label="Search Assignment History"
            search-placeholder="Search trip, bus, or driver..."
            :filter-count="(int) request()->filled('trip_date')"
            filter-title="Filter Assignment History"
            :sort-options="['trip_date' => 'Trip Date', 'trip_code' => 'Trip ID', 'departure_time' => 'Departure Time', 'status' => 'Trip Status']"
            default-sort="trip_date"
            default-direction="desc"
        >
            <x-slot:hidden><input type="hidden" name="view" value="assignments"></x-slot:hidden>
            <x-slot:filters><x-ui.table-toolbar-field label="Trip Date" name="trip_date" type="date" /></x-slot:filters>
        </x-ui.table-toolbar>
    </x-slot:toolbar>

    <x-slot:helper><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Scheduling outcomes reflect assignments, not verified vehicle arrivals.</x-slot:helper>

    <table class="trip-table trip-history-table">
        <thead><tr><th>Trip ID</th><th>Schedule</th><th>Route</th><th>Assigned Resources</th><th>Bus Slots</th><th>Outcome</th></tr></thead>
        <tbody>
            @forelse($assignmentHistoryTrips as $trip)
                @php
                    $assigned = $trip->assignments->count() + $trip->externalRentals->where('status', 'Assigned')->count();
                    $required = max(1, (int) $trip->required_buses);
                    $outcome = $outcomeFor($trip);
                    $resources = $trip->assignments->map(fn ($assignment) => [
                        'driver' => $assignment->driver_name ?: 'Driver unavailable',
                        'bus' => $assignment->bus?->bus_no ?? 'Bus unavailable',
                    ])->concat($trip->externalRentals->where('status', 'Assigned')->map(fn ($rental) => [
                        'driver' => $rental->driver_name ?: 'External driver',
                        'bus' => $rental->plate_number ?: 'External rental',
                    ]))->values();
                    $firstResource = $resources->first();
                @endphp
                <tr>
                    <td><x-ui.id-badge :value="$trip->trip_code" /></td>
                    <td><strong>{{ $trip->trip_date?->format('M d, Y') }}</strong><small>{{ $trip->departureDateTime()->format('g:i A') }} – {{ $trip->estimatedArrivalDateTime()->format('g:i A') }}</small></td>
                    <td>{{ $trip->route_name_snapshot ?: ($trip->shuttleRoute?->route_name ?? 'Route unavailable') }}</td>
                    <td>
                        @if($firstResource)
                            <div class="trip-resource-compact">
                                <span class="trip-resource-icon"><i class="fa-solid fa-bus" aria-hidden="true"></i></span>
                                <span class="trip-resource-primary"><strong>{{ $firstResource['driver'] }}</strong><small>{{ $firstResource['bus'] }}</small></span>
                                @if($resources->count() > 1)
                                    <button type="button" class="trip-resource-more" style="anchor-name: --trip-resource-{{ $trip->id }}" popovertarget="tripResource-{{ $trip->id }}" aria-label="Show {{ $resources->count() - 1 }} more assignments for {{ $trip->trip_code }}">+{{ $resources->count() - 1 }} more <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>
                                    <div id="tripResource-{{ $trip->id }}" popover="auto" class="trip-resource-popover" style="position-anchor: --trip-resource-{{ $trip->id }}">
                                        <div class="trip-resource-popover-title">Other assigned resources</div>
                                        @foreach($resources->skip(1) as $resource)
                                            <div class="trip-resource-popover-row"><i class="fa-solid fa-bus" aria-hidden="true"></i><span><strong>{{ $resource['driver'] }}</strong><small>{{ $resource['bus'] }}</small></span></div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @else
                            <span class="trip-resource-empty">Not assigned</span>
                        @endif
                    </td>
                    <td><span class="trip-history-slots {{ $assigned >= $required ? 'is-full' : ($assigned > 0 ? 'is-partial' : 'is-empty') }}">{{ $assigned }}/{{ $required }}</span></td>
                    <td><x-ui.status-badge class="trip-history-outcome" :status="$outcome" :title="$outcome === 'Scheduled Done' ? 'Scheduled window ended; actual arrival has not been verified.' : 'Scheduling fulfillment outcome'" /></td>
                </tr>
            @empty
                <x-ui.empty-row colspan="6" icon="fa-clock-rotate-left" message="No Assignment History Found" description="No historical assignments matched your search or filter criteria." />
            @endforelse
        </tbody>
    </table>

    <x-slot:pagination><x-ui.table-footer :items="$assignmentHistoryTrips" data-lazy-pagination="true" /></x-slot:pagination>
</x-operation.trip-history-layout>
