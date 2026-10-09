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

                <nav class="inc-record-tabs inc-header-tabs" role="tablist" aria-label="Incident record groups">
                    <a href="{{ route('incidents', array_merge(request()->except(['tab', 'incident_page', 'status']), ['tab' => 'active'])) }}" class="inc-record-tab {{ $tab === 'active' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $tab === 'active' ? 'true' : 'false' }}"><i class="fa-solid fa-list-check" aria-hidden="true"></i> Active</a>
                    <a href="{{ route('incidents', array_merge(request()->except(['tab', 'incident_page', 'status']), ['tab' => 'history'])) }}" class="inc-record-tab {{ $tab === 'history' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $tab === 'history' ? 'true' : 'false' }}"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> History</a>
                </nav>
                </div>

                <form method="GET" action="{{ route('incidents') }}" class="inc-toolbar">
                    <input type="hidden" name="tab" value="{{ $tab }}">
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

                    <button type="button" id="openIncidentReportModal" class="inc-new-btn">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        Report Incident
                    </button>

                    @if(request()->filled('search') || (request()->filled('status') && request('status') !== 'all') || (request()->filled('type') && request('type') !== 'all'))
                        <a href="{{ route('incidents', ['tab' => $tab]) }}" class="inc-clear-btn" title="Reset all filters">
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
                                <th>Trip / Route</th>
                                <th>Bus</th>
                                <th>Driver</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($incidents as $incident)
                                @php
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
                                    $canModifyIncident = $incident->status === 'Reported' && !$maintenanceReferral && !$incident->replacement && $incident->responses->count() <= 1;
                                    $canArchiveIncident = $canModifyIncident && $incident->incident_type !== 'Bus Breakdown';
                                @endphp

                                <tr data-incident-record
                                    data-incident-no="{{ $incident->incident_no }}"
                                    data-incident-type="{{ $incident->incident_type }}"
                                    data-incident-status="{{ $incident->status }}"
                                    data-incident-bus="{{ $incident->bus?->bus_no ?? '' }}"
                                    data-incident-plate="{{ $incident->bus?->plate_no ?? '' }}"
                                    data-incident-trip="{{ $tripCode }}"
                                    data-incident-route="{{ $routeLabel ?? '' }}"
                                    data-incident-driver="{{ $incident->driver_name ?? '' }}"
                                    data-incident-driver-id="{{ $incident->driver_id ?? '' }}"
                                    data-incident-location="{{ $incident->location }}"
                                    data-incident-description="{{ $incident->description }}"
                                    data-incident-reported="{{ $incident->incident_reported_at?->format('M d, Y g:i A') ?? '' }}">
                                    <td><x-ui.id-badge :value="$incident->incident_no" /></td>
                                    <td>
                                        <span class="inc-type-pill {{ $typeKey === 'bus-breakdown' ? 'breakdown' : ($typeKey === 'accident-road-incident' ? 'accident' : $typeKey) }}">
                                            <i class="fa-solid fa-circle"></i>
                                            {{ $incident->incident_type }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="inc-driver-cell">
                                            <span>{{ $tripCode }}</span>
                                            @if($routeLabel)<small title="{{ $routeLabel }}">{{ $routeLabel }}</small>@endif
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
                                    <td><x-ui.status-badge :status="$incident->status" /></td>
                                    <td>
                                        <div class="inc-actions inc-record-actions">
                                            <button type="button" class="action-btn view inc-action" data-incident-modal-action="view" aria-label="View incident details" title="View Incident Details"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
                                            @if($canModifyIncident)
                                                <button type="button" class="action-btn edit inc-action inc-edit-action" data-incident-modal-action="edit" data-update-url="{{ route('incidents.details.update', ['incident' => $incident->incident_no]) }}" aria-label="Edit incident" title="Edit Incident"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></button>
                                            @else
                                                <button type="button" class="inc-action inc-edit-action inc-action-disabled" disabled title="Editing is locked once incident processing begins" aria-label="Edit unavailable"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></button>
                                            @endif
                                            @if($canArchiveIncident)
                                                <form method="POST" action="{{ route('incidents.destroy', ['incident' => $incident->incident_no]) }}" onsubmit="return confirm('Archive this unprocessed incident? This action will remove it from active records while preserving its audit history.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-ui.action-button type="delete" button-type="submit" class="inc-action inc-delete-action" title="Archive Incident" aria-label="Archive incident" />
                                                </form>
                                            @else
                                                <button type="button" class="inc-action inc-delete-action inc-action-disabled" disabled title="Archive unavailable: incident is a breakdown or has workflow activity" aria-label="Archive unavailable"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>
                                            @endif

                                            @if($incident->incident_type === 'Bus Breakdown' && $canReferToMaintenance && !$maintenanceReferral)
                                                <form method="POST" action="{{ route('incidents.maintenance-referral.store', $incident) }}">
                                                    @csrf
                                                    <button type="submit" class="inc-action view" title="Refer to Maintenance" aria-label="Refer incident to Maintenance">
                                                        <i class="fa-solid fa-screwdriver-wrench"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="empty-row">
                                    <td colspan="7" style="text-align:center;padding:48px 20px;">
                                        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;color:var(--inc-muted);">
                                            <i class="fa-solid fa-triangle-exclamation" style="font-size:32px;color:#cbd5e1;"></i>
                                            <strong style="font-size:15px;color:var(--inc-navy);">{{ $tab === 'history' ? 'No Incident History Found' : 'No Active Incidents Found' }}</strong>
                                            <p style="font-size:13px;margin:0;max-width:420px;">{{ request()->filled('search') || (request()->filled('status') && request('status') !== 'all') || (request()->filled('type') && request('type') !== 'all') ? 'No records match your current filters. Try adjusting the search, status, or incident type.' : ($tab === 'history' ? 'Resolved and cancelled incidents will appear here.' : 'No ongoing incidents need attention right now.') }}</p>
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


    <!-- Body-level modal: backdrop click is intentionally not a dismissal action. -->
    <div class="inc-record-modal-backdrop" id="incidentRecordModal" hidden>
        <section class="inc-record-modal" role="dialog" aria-modal="true" aria-labelledby="incidentRecordModalTitle" tabindex="-1">
            <header class="inc-record-modal-header">
                <div><h2 id="incidentRecordModalTitle">Incident Details</h2><p id="incidentRecordModalSubtitle"></p></div>
                <button type="button" class="inc-record-modal-close" data-incident-modal-close aria-label="Close incident modal"><i class="fa-solid fa-xmark"></i></button>
            </header>
            <div class="inc-record-modal-body">
                <div data-incident-view-panel>
                    <div class="inc-record-detail-grid">
                        <div><small>Incident No.</small><strong data-incident-display="no"></strong></div>
                        <div><small>Status</small><strong data-incident-display="status"></strong></div>
                        <div><small>Incident Type</small><strong data-incident-display="type"></strong></div>
                        <div><small>Reported</small><strong data-incident-display="reported"></strong></div>
                        <div><small>Bus / Plate</small><strong data-incident-display="bus"></strong></div>
                        <div><small>Trip / Route</small><strong data-incident-display="trip"></strong></div>
                        <div><small>Driver</small><strong data-incident-display="driver"></strong></div>
                        <div><small>Location</small><strong data-incident-display="location"></strong></div>
                    </div>
                    <div class="inc-record-description"><small>Description / Details</small><p data-incident-display="description"></p></div>
                    <p class="inc-record-modal-helper">For response history, replacement dispatch, status changes, and Maintenance referral details, use the complete incident workflow.</p>
                    <a href="#" class="inc-record-full-details" data-incident-full-link>Open Complete Incident Workflow</a>
                </div>
                <form data-incident-edit-panel hidden>
                    @csrf
                    @method('PATCH')
                    <p class="inc-record-modal-helper">Only location and description can be corrected before the incident enters processing.</p>
                    <label for="incidentEditLocation">Current Location <span class="ui-required">*</span></label>
                    <input id="incidentEditLocation" name="location" required maxlength="255" />
                    <label for="incidentEditDescription">Description / Details</label>
                    <textarea id="incidentEditDescription" name="description" maxlength="2000" rows="5"></textarea>
                    <div class="inc-record-modal-error" data-incident-modal-error role="alert" hidden></div>
                    <div class="inc-record-modal-footer"><button type="button" class="inc-record-modal-cancel" data-incident-modal-close>Cancel</button><button type="submit" class="inc-record-modal-save">Save Changes</button></div>
                </form>
            </div>
        </section>
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
