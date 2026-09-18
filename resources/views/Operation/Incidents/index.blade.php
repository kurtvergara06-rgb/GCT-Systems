<x-layout.app
    title="FROMS - Incident Management"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Main-styles/form-components.css',
        'resources/css/Operation/Incidents/incidents.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Operation/Incidents/incidents.js',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Operation" />

        <main class="main inc-page">
            <x-layout.topbar
                title="Incident Management"
                subtitle="Monitor and respond to operational incidents reported during trips"
            />

            @if (session('success'))
                <div class="inc-alert inc-alert-success" role="alert">
                    <i class="fa-solid fa-circle-check"></i>
                    <div>
                        <strong>{{ session('success') }}</strong>
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="inc-alert inc-alert-error" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>
                        <strong>{{ session('error') }}</strong>
                    </div>
                </div>
            @endif

            <!-- Summary KPI Cards -->
            <section class="inc-summary-grid">
                <article class="inc-summary-card">
                    <div class="inc-summary-icon blue">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <p>Total Incidents</p>
                        <h2>{{ number_format($totalIncidents) }}</h2>
                        <small>All reported incidents</small>
                    </div>
                </article>

                <article class="inc-summary-card">
                    <div class="inc-summary-icon red">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                    <div>
                        <p>Active Incidents</p>
                        <h2>{{ number_format($activeIncidents) }}</h2>
                        <small>Reported / Monitoring / Responding</small>
                    </div>
                </article>

                <article class="inc-summary-card">
                    <div class="inc-summary-icon amber">
                        <i class="fa-solid fa-bus"></i>
                    </div>
                    <div>
                        <p>Active Breakdowns</p>
                        <h2>{{ number_format($breakdownIncidents) }}</h2>
                        <small>Bus breakdowns needing response</small>
                    </div>
                </article>

                <article class="inc-summary-card">
                    <div class="inc-summary-icon green">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div>
                        <p>Resolved Today</p>
                        <h2>{{ number_format($resolvedToday) }}</h2>
                        <small>Incidents closed today</small>
                    </div>
                </article>
            </section>

            <!-- Main Records Card -->
            <section class="inc-card">
                <div class="inc-card-header">
                    <div>
                        <h2>Incident Records</h2>
                        <p>Operational incidents reported by drivers during active trips.</p>
                    </div>

                    <a href="{{ route('incidents.create') }}" class="inc-new-btn">
                        <i class="fa-solid fa-plus"></i>
                        Report Incident
                    </a>
                </div>

                <!-- Filter & Search Toolbar -->
                <form method="GET" action="{{ route('incidents') }}" class="inc-toolbar">
                    <div class="inc-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Search by incident no, driver, bus, trip, location..."
                        />
                    </div>

                    <div class="inc-filter">
                        <label for="filterStatus">Status</label>
                        <select id="filterStatus" name="status" onchange="this.form.submit()">
                            <option value="all">All Statuses</option>
                            @foreach(['Reported', 'Monitoring', 'Responding', 'Replacement Bus Dispatched', 'Resolved', 'Cancelled'] as $statusOption)
                                <option value="{{ $statusOption }}" @selected(request('status') == $statusOption)>
                                    {{ $statusOption }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="inc-filter">
                        <label for="filterType">Incident Type</label>
                        <select id="filterType" name="type" onchange="this.form.submit()">
                            <option value="all">All Types</option>
                            @foreach(['Traffic', 'Bus Breakdown', 'Accident/Road Incident', 'Other'] as $typeOption)
                                <option value="{{ $typeOption }}" @selected(request('type') == $typeOption)>
                                    {{ $typeOption }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if(request()->anyFilled(['search', 'status', 'type']))
                        <a href="{{ route('incidents') }}" class="inc-clear-btn" title="Reset all filters">
                            <i class="fa-solid fa-rotate-left"></i> Reset
                        </a>
                    @endif
                </form>

                <!-- Incidents Table -->
                <div class="inc-table-wrap">
                    <table class="inc-table">
                        <thead>
                            <tr>
                                <th>Incident No.</th>
                                <th>Type</th>
                                <th>Trip</th>
                                <th>Bus</th>
                                <th>Driver</th>
                                <th>Location</th>
                                <th>Reported</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($incidents as $incident)
                                @php
                                    $reportedFmt = $incident->incident_reported_at
                                        ? $incident->incident_reported_at->format('M d, Y g:i A')
                                        : '—';

                                    $tripCode = $incident->tripSchedule?->trip_code ?: '—';
                                    $routeLabel = $incident->tripSchedule?->shuttleRoute
                                        ? $incident->tripSchedule->shuttleRoute->route_name
                                        : null;

                                    $typeKey = strtolower(str_replace(['/', ' '], '-', $incident->incident_type));
                                @endphp

                                <tr>
                                    <td>
                                        <x-ui.id-badge :value="$incident->incident_no" />
                                    </td>

                                    <td>
                                        <span class="inc-type-pill {{ $typeKey }}">
                                            <i class="fa-solid fa-circle"></i>
                                            {{ $incident->incident_type }}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="inc-driver-cell">
                                            <span>{{ $tripCode }}</span>
                                            @if($routeLabel)
                                                <small>{{ $routeLabel }}</small>
                                            @endif
                                        </div>
                                    </td>

                                    <td>
                                        @if($incident->bus)
                                            <x-ui.id-badge :value="$incident->bus->bus_no" />
                                        @else
                                            <span style="color: #94a3b8;">—</span>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="inc-driver-cell">
                                            @if($incident->driver_name)
                                                <span>{{ $incident->driver_name }}</span>
                                                @if($incident->driver_id)
                                                    <small>{{ $incident->driver_id }}</small>
                                                @endif
                                            @else
                                                <span style="color: #94a3b8;">—</span>
                                            @endif
                                        </div>
                                    </td>

                                    <td>
                                        <div class="inc-loc-cell">
                                            <span>{{ $incident->location ?: '—' }}</span>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="inc-driver-cell">
                                            <span>{{ $reportedFmt }}</span>
                                        </div>
                                    </td>

                                    <td>
                                        <x-ui.status-badge :status="$incident->status" />
                                    </td>

                                    <td>
                                        <div class="inc-actions">
                                            <a
                                                href="{{ route('incidents.show', ['incident' => $incident->incident_no]) }}"
                                                class="inc-action view"
                                                title="View Incident Details"
                                            >
                                                <i class="fa-regular fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 48px 20px;">
                                        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; color: var(--inc-muted);">
                                            <i class="fa-solid fa-triangle-exclamation" style="font-size: 32px; color: #cbd5e1;"></i>
                                            <strong style="font-size: 15px; color: var(--inc-navy);">No Incidents Found</strong>
                                            <p style="font-size: 13px; margin: 0; max-width: 420px;">
                                                No incidents matched your search or filter criteria. Try adjusting the status or type filters.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div class="inc-table-footer">
                    <span>
                        Showing {{ $incidents->firstItem() ?? 0 }} to {{ $incidents->lastItem() ?? 0 }} of {{ $incidents->total() }} incidents
                    </span>

                    <div class="inc-pagination">
                        @if ($incidents->onFirstPage())
                            <button type="button" class="disabled" disabled>
                                <i class="fa-solid fa-chevron-left"></i> Previous
                            </button>
                        @else
                            <a href="{{ $incidents->previousPageUrl() }}">
                                <i class="fa-solid fa-chevron-left"></i> Previous
                            </a>
                        @endif

                        <span>Page {{ $incidents->currentPage() }} of {{ $incidents->lastPage() }}</span>

                        @if ($incidents->hasMorePages())
                            <a href="{{ $incidents->nextPageUrl() }}">
                                Next <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        @else
                            <button type="button" class="disabled" disabled>
                                Next <i class="fa-solid fa-chevron-right"></i>
                            </button>
                        @endif
                    </div>
                </div>
            </section>
        </main>
    </div>
</x-layout.app>