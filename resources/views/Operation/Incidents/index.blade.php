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
                    <div><strong>{{ session('success') }}</strong></div>
                </div>
            @endif

            @if (session('error'))
                <div class="inc-alert inc-alert-error" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div><strong>{{ session('error') }}</strong></div>
                </div>
            @endif

            <section class="inc-summary-grid">
                <article class="inc-summary-card">
                    <div class="inc-summary-icon blue"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div><p>Total Incidents</p><h2>{{ number_format($totalIncidents) }}</h2><small>All reported incidents</small></div>
                </article>
                <article class="inc-summary-card">
                    <div class="inc-summary-icon red"><i class="fa-solid fa-hourglass-half"></i></div>
                    <div><p>Active Incidents</p><h2>{{ number_format($activeIncidents) }}</h2><small>Reported / Monitoring / Responding</small></div>
                </article>
                <article class="inc-summary-card">
                    <div class="inc-summary-icon amber"><i class="fa-solid fa-bus"></i></div>
                    <div><p>Active Breakdowns</p><h2>{{ number_format($breakdownIncidents) }}</h2><small>Bus breakdowns needing response</small></div>
                </article>
                <article class="inc-summary-card">
                    <div class="inc-summary-icon green"><i class="fa-solid fa-circle-check"></i></div>
                    <div><p>Resolved Today</p><h2>{{ number_format($resolvedToday) }}</h2><small>Incidents closed today</small></div>
                </article>
            </section>

            <section class="inc-card">
                <div class="inc-card-header">
                    <div>
                        <h2>Incident Records</h2>
                        <p>Operational incidents reported by drivers during active trips.</p>
                    </div>

                    <button type="button" id="openIncidentReportModal" class="inc-new-btn">
                        <i class="fa-solid fa-plus"></i>
                        Report Incident
                    </button>
                </div>

                <form method="GET" action="{{ route('incidents') }}" class="inc-toolbar">
                    <div class="inc-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by incident no, driver, bus, trip, location..." />
                    </div>

                    <div class="inc-filter">
                        <label for="filterStatus">Status</label>
                        <select id="filterStatus" name="status" onchange="this.form.submit()">
                            <option value="all">All Statuses</option>
                            @foreach(['Reported', 'Monitoring', 'Responding', 'Replacement Bus Dispatched', 'Resolved', 'Cancelled'] as $statusOption)
                                <option value="{{ $statusOption }}" @selected(request('status') == $statusOption)>{{ $statusOption }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="inc-filter">
                        <label for="filterType">Incident Type</label>
                        <select id="filterType" name="type" onchange="this.form.submit()">
                            <option value="all">All Types</option>
                            @foreach(['Traffic', 'Bus Breakdown', 'Accident/Road Incident', 'Other'] as $typeOption)
                                <option value="{{ $typeOption }}" @selected(request('type') == $typeOption)>{{ $typeOption }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if(request()->anyFilled(['search', 'status', 'type']))
                        <a href="{{ route('incidents') }}" class="inc-clear-btn" title="Reset all filters">
                            <i class="fa-solid fa-rotate-left"></i> Reset
                        </a>
                    @endif
                </form>

                <div class="table-wrap inc-table-wrap">
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
                                    $routeLabel = $incident->tripSchedule?->shuttleRoute?->route_name;
                                    $typeKey = strtolower(str_replace(['/', ' '], '-', $incident->incident_type));

                                    $currentUser = auth()->user();
                                    $currentDepartment = strtolower(trim((string) ($currentUser?->department ?? '')));
                                    $currentRole = strtolower(trim((string) ($currentUser?->role ?? '')));
                                    $canReferToMaintenance = (in_array($currentDepartment, ['operation', 'operations'], true)
                                            && in_array($currentRole, ['head', 'admin', 'operation head', 'operations head', 'operation admin'], true))
                                        || ($currentDepartment === 'admin' && in_array($currentRole, ['head', 'admin', 'system admin'], true));
                                    $maintenanceReferral = $incident->maintenanceReferral;
                                @endphp

                                <tr>
                                    <td><x-ui.id-badge :value="$incident->incident_no" /></td>
                                    <td>
                                        <span class="inc-type-pill {{ $typeKey }}">
                                            <i class="fa-solid fa-circle"></i>
                                            {{ $incident->incident_type }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="inc-driver-cell">
                                            <span>{{ $tripCode }}</span>
                                            @if($routeLabel)<small>{{ $routeLabel }}</small>@endif
                                        </div>
                                    </td>
                                    <td>
                                        @if($incident->bus)
                                            <x-ui.id-badge :value="$incident->bus->bus_no" />
                                        @else
                                            <span style="color:#94a3b8;">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="inc-driver-cell">
                                            @if($incident->driver_name)
                                                <span>{{ $incident->driver_name }}</span>
                                                @if($incident->driver_id)<small>{{ $incident->driver_id }}</small>@endif
                                            @else
                                                <span style="color:#94a3b8;">—</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td><div class="inc-loc-cell"><span>{{ $incident->location ?: '—' }}</span></div></td>
                                    <td><div class="inc-driver-cell"><span>{{ $reportedFmt }}</span></div></td>
                                    <td><x-ui.status-badge :status="$incident->status" /></td>
                                    <td>
                                        <div class="inc-actions" style="display:flex;gap:6px;align-items:center;">
                                            <a href="{{ route('incidents.show', ['incident' => $incident->incident_no]) }}" class="inc-action view" title="View Incident Details">
                                                <i class="fa-regular fa-eye"></i>
                                            </a>

                                            @if($incident->incident_type === 'Bus Breakdown' && $canReferToMaintenance && !$maintenanceReferral)
                                                <form method="POST" action="{{ route('incidents.maintenance-referral.store', $incident) }}">
                                                    @csrf
                                                    <button type="submit" class="inc-action view" title="Refer to Maintenance" style="border:0;cursor:pointer;">
                                                        <i class="fa-solid fa-screwdriver-wrench"></i>
                                                    </button>
                                                </form>
                                            @elseif($maintenanceReferral)
                                                <span title="Maintenance referral status" style="font-size:10px;font-weight:700;white-space:nowrap;">
                                                    {{ $maintenanceReferral->status }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="empty-row">
                                    <td colspan="9" style="text-align:center;padding:48px 20px;">
                                        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;color:var(--inc-muted);">
                                            <i class="fa-solid fa-triangle-exclamation" style="font-size:32px;color:#cbd5e1;"></i>
                                            <strong style="font-size:15px;color:var(--inc-navy);">No Incidents Found</strong>
                                            <p style="font-size:13px;margin:0;max-width:420px;">No incidents matched your search or filter criteria. Try adjusting the status or type filters.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-ui.table-footer :items="$incidents" />
            </section>
        </main>
    </div>

    <x-ui.form-modal
        id="incidentReportModal"
        title="Report Incident"
        description="Report an operational incident encountered during an active trip."
        icon="fa-triangle-exclamation"
        size="wide"
        form-id="incidentReportForm"
        :action="route('incidents.store', [], false)"
        method="POST"
        submit-text="Save Incident"
        submit-id="incidentReportSubmit"
        submit-icon="fa-floppy-disk"
        cancel-text="Cancel"
        cancel-id="cancelIncidentReport"
        close-id="closeIncidentReport"
    >
        @if ($errors->any() || session('error'))
            <div class="inc-alert inc-alert-error inc-modal-alert" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <div>
                    <strong>{{ session('error') ?? 'Unable to save the incident report.' }}</strong>
                    <span>Please review the highlighted fields below.</span>
                </div>
            </div>
        @endif

        <input type="hidden" name="incident_modal" value="1">
        <input type="hidden" name="search" value="{{ request('search') }}">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <input type="hidden" name="type" value="{{ request('type') }}">

        <div class="inc-form-grid inc-modal-form-grid">
            @include('Operation.Incidents._form-fields', ['formPrefix' => 'modal-'])
        </div>

        <div class="inc-form-note inc-modal-note">
            <i class="fa-solid fa-circle-info"></i>
            <div>
                <strong>Incidents are timestamped automatically.</strong>
                <span>The reported time is captured when you submit. A unique incident number is generated and Operations is notified immediately.</span>
            </div>
        </div>
    </x-ui.form-modal>
</x-layout.app>
