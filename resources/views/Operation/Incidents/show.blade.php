<x-layout.app
    title="FROMS - Incident Details"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Main-styles/form-components.css',
        'resources/css/Operation/Incidents/incidents.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Operation/Incidents/incidents.js',
    ]"
>
    @php
        $isResolved = $incident->status === 'Resolved';
        $isCancelled = $incident->status === 'Cancelled';
        $isClosed = $isResolved || $isCancelled;
        $isBreakdown = $incident->incident_type === 'Bus Breakdown';

        $statusFormOptions = match ($incident->status) {
            'Reported' => ['Reported', 'Monitoring', 'Responding', 'Resolved', 'Cancelled'],
            'Monitoring' => ['Monitoring', 'Responding', 'Resolved', 'Cancelled'],
            'Responding' => ['Responding', 'Resolved', 'Cancelled'],
            'Replacement Bus Dispatched' => ['Replacement Bus Dispatched', 'Responding', 'Resolved', 'Cancelled'],
            default => [$incident->status],
        };

        $eligibleReplacementBuses = $activeBuses
            ->reject(fn ($bus) => $incident->bus_id && $bus->id === $incident->bus_id)
            ->reject(fn ($bus) => $incident->replacement && $bus->id === $incident->replacement->replacement_bus_id);
        $eligibleReplacementDrivers = $eligibleReplacementDrivers ?? collect();
        $currentUser = auth()->user();
        $currentDepartment = strtolower(trim((string) ($currentUser?->department ?? '')));
        $currentRole = strtolower(trim((string) ($currentUser?->role ?? '')));
        $canEditIncident = (bool) $currentUser?->hasSystemPermission('operation', 'edit');
        $canReferToMaintenance = (in_array($currentDepartment, ['operation', 'operations'], true)
                && in_array($currentRole, ['head', 'admin', 'operation head', 'operations head', 'operation admin'], true))
            || ($currentDepartment === 'admin' && in_array($currentRole, ['head', 'admin', 'system admin'], true));
    @endphp

    @if(request()->boolean('embedded'))
        <style>
            html, body {
                overflow-y: auto !important;
                scrollbar-width: thin !important;
                scrollbar-color: #94a3b8 #f1f5f9 !important;
                background: #f8fafc !important;
            }
            html::-webkit-scrollbar,
            body::-webkit-scrollbar {
                display: block !important;
                width: 8px !important;
            }
            html::-webkit-scrollbar-track,
            body::-webkit-scrollbar-track {
                background: #f1f5f9 !important;
            }
            html::-webkit-scrollbar-thumb,
            body::-webkit-scrollbar-thumb {
                background: #94a3b8 !important;
                border-radius: 4px !important;
            }
            html::-webkit-scrollbar-thumb:hover,
            body::-webkit-scrollbar-thumb:hover {
                background: #64748b !important;
            }
            body:has(.inc-embedded-workflow) {
                overflow-y: auto !important;
                background: #f8fafc !important;
            }
            .inc-embedded-workflow {
                min-height: 100%;
                display: block !important;
                background: #f8fafc !important;
            }
            .inc-embedded-workflow > .sidebar { display: none !important; }
            .inc-embedded-workflow > main.inc-page {
                width: 100% !important;
                max-width: none !important;
                margin: 0 auto !important;
                padding: 0 !important;
                background: transparent !important;
            }
            .inc-embedded-workflow .topbar,
            .inc-embedded-workflow .main-topbar {
                display: none !important;
            }
            .inc-embedded-modal-footer {
                display: flex;
                justify-content: flex-end;
                padding-top: 20px;
                margin-top: 10px;
                border-top: 1px solid var(--inc-border);
            }
        </style>
    @endif
    <div class="app @if(request()->boolean('embedded')) inc-embedded-workflow @endif">
        <x-layout.sidebar department="Operation" />

        <main class="main inc-page">
            <x-layout.topbar
                title="Incident Details"
                subtitle="{{ $incident->incident_no }} — {{ $incident->incident_type }}"
            />

            @if (session('success') && !request()->boolean('embedded'))
                <div class="inc-alert inc-alert-success" role="alert">
                    <i class="fa-solid fa-circle-check"></i>
                    <div>
                        <strong>{{ session('success') }}</strong>
                    </div>
                </div>
            @endif

            @if (session('error') && !request()->boolean('embedded'))
                <div class="inc-alert inc-alert-error" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>
                        <strong>{{ session('error') }}</strong>
                    </div>
                </div>
            @endif

            @php
                $hasOicResponse = $incident->responses->count() > 1
                    || $incident->responses->contains(fn ($response) => $response->status !== 'Reported');
                $hasReferral = $incident->maintenanceReferral()->exists();
                $workflowSteps = [
                    ['label' => $isBreakdown ? 'Breakdown Reported' : 'Incident Reported', 'note' => 'Recorded by Operations after receiving the incident report.', 'done' => true, 'icon' => 'fa-file-circle-check'],
                    ['label' => 'OIC Coordination (External)', 'note' => 'Driver contacts OIC. OIC coordinates with Maintenance Manager by phone or radio; Operations records updates.', 'done' => $hasOicResponse, 'icon' => 'fa-phone-volume'],
                    ['label' => 'Reserve Bus Deployment', 'note' => $isBreakdown ? 'OIC selects and deploys the replacement unit. Operations records the actual deployment.' : 'Only applicable if a replacement bus is needed.', 'done' => (bool) $incident->replacement, 'icon' => 'fa-bus'],
                    ['label' => 'Trip Continuation', 'note' => 'Driver resumes the trip; Operations records the operational outcome.', 'done' => $isResolved, 'icon' => 'fa-route'],
                    ['label' => 'Maintenance Referral', 'note' => 'Maintenance Manager handles the affected vehicle and its repair workflow.', 'done' => $hasReferral, 'icon' => 'fa-screwdriver-wrench'],
                ];
            @endphp
            @if(request()->boolean('embedded'))
                @include('Operation.Incidents.partials.embedded-workflow')
            @else
            <div class="inc-workflow-overview">
                <div class="inc-workflow-overview-heading">
                    <div><h2><i class="fa-solid fa-diagram-project"></i> Incident Response Timeline</h2>
                    <p>Driver and OIC communicate outside the system. Authorized Operations personnel record updates here.</p></div>
                    <x-ui.status-badge :status="$incident->status" />
                </div>
                <div class="inc-workflow-step-list">
                    @foreach($workflowSteps as $stepIndex => $step)
                        @if($stepIndex === 3)
                            <div class="inc-workflow-parallel-heading"><i class="fa-solid fa-code-branch"></i><span>Parallel follow-up activities — independently recorded</span></div>
                        @endif
                        <div class="inc-workflow-step {{ $step['done'] ? 'is-complete' : '' }}">
                            <span class="inc-workflow-step-number">{{ $stepIndex + 1 }}</span>
                            <span class="inc-workflow-step-icon"><i class="fa-solid {{ $step['icon'] }}"></i></span>
                            <div class="inc-workflow-step-content">
                                <strong>{{ $step['label'] }}</strong>
                                <p>{{ $step['note'] }}</p>
                            </div>
                            <span class="inc-workflow-step-state">{{ $step['done'] ? 'Recorded' : 'Pending' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="inc-workflow-information-note"><i class="fa-solid fa-circle-info"></i>
                <span>Timeline states represent saved system records, not tasks assigned to the OIC. Reserve deployment and maintenance referral depend on the incident type and actual response.</span>
            </div>

            <div class="inc-show-layout">
                <!-- Main Details -->
                <section class="inc-card">
                    <div class="inc-card-header">
                        <div>
                            <h2>Incident Information</h2>
                            <p>Details captured at the time of reporting.</p>
                        </div>

                        <x-ui.id-badge :value="$incident->incident_no" size="large" />
                    </div>

                    <div class="inc-detail-grid">
                        <div class="inc-detail-item">
                            <span class="inc-detail-label">Status</span>
                            <x-ui.status-badge :status="$incident->status" />
                        </div>

                        <div class="inc-detail-item">
                            <span class="inc-detail-label">Incident Type</span>
                            <span class="inc-detail-value">{{ $incident->incident_type }}</span>
                        </div>

                        <div class="inc-detail-item">
                            <span class="inc-detail-label">Reported Time</span>
                            <span class="inc-detail-value">
                                {{ $incident->incident_reported_at?->format('M d, Y g:i A') }}
                            </span>
                        </div>

                        <div class="inc-detail-item">
                            <span class="inc-detail-label">Driver</span>
                            <span class="inc-detail-value">{{ $incident->driver_name ?: '—' }}</span>
                            @if($incident->driver_id)
                                <small class="inc-detail-sub">{{ $incident->driver_id }}</small>
                            @endif
                        </div>

                        <div class="inc-detail-item">
                            <span class="inc-detail-label">Bus</span>
                            <span class="inc-detail-value">{{ $incident->bus?->bus_no ?: '—' }}</span>
                            @if($incident->bus)
                                <small class="inc-detail-sub">{{ $incident->bus->plate_no }}</small>
                            @endif
                        </div>

                        <div class="inc-detail-item">
                            <span class="inc-detail-label">Trip</span>
                            @if($incident->tripSchedule)
                                <span class="inc-detail-value">
                                    <x-ui.id-badge :value="$incident->tripSchedule->trip_code" />
                                </span>
                                <small class="inc-detail-sub">
                                    {{ $incident->tripSchedule->trip_date?->format('M d, Y') }}
                                    &bull; {{ substr($incident->tripSchedule->departure_time, 0, 5) }}
                                    &rarr; {{ substr($incident->tripSchedule->estimated_arrival_time, 0, 5) }}
                                </small>
                            @else
                                <span class="inc-detail-value">—</span>
                            @endif
                        </div>

                        <div class="inc-detail-item">
                            <span class="inc-detail-label">Route</span>
                            @if($incident->tripSchedule?->shuttleRoute)
                                <span class="inc-detail-value">
                                    {{ $incident->tripSchedule->shuttleRoute->route_name }}
                                </span>
                                <small class="inc-detail-sub">
                                    {{ $incident->tripSchedule->shuttleRoute->origin }}
                                    &rarr;
                                    {{ $incident->tripSchedule->shuttleRoute->destination }}
                                </small>
                            @else
                                <span class="inc-detail-value">—</span>
                            @endif
                        </div>

                        <div class="inc-detail-item">
                            <span class="inc-detail-label">Location</span>
                            <span class="inc-detail-value">{{ $incident->location ?: '—' }}</span>
                        </div>

                        <div class="inc-detail-item">
                            <span class="inc-detail-label">Reported By</span>
                            <span class="inc-detail-value">{{ $incident->reporter?->name ?: '—' }}</span>
                        </div>

                        <div class="inc-detail-item full">
                            <span class="inc-detail-label">Description</span>
                            <span class="inc-detail-value" style="font-weight: 500; line-height: 1.55; white-space: pre-line;">
                                {{ $incident->description ?: '—' }}
                            </span>
                        </div>

                        @if($incident->resolution_notes)
                            <div class="inc-detail-item full">
                                <span class="inc-detail-label">Resolution Notes</span>
                                <span class="inc-detail-value" style="font-weight: 500; line-height: 1.55; white-space: pre-line;">
                                    {{ $incident->resolution_notes }}
                                </span>
                            </div>
                        @endif

                        @if($incident->resolved_at)
                            <div class="inc-detail-item">
                                <span class="inc-detail-label">Resolved At</span>
                                <span class="inc-detail-value">
                                    {{ $incident->resolved_at->format('M d, Y g:i A') }}
                                </span>
                            </div>

                            <div class="inc-detail-item">
                                <span class="inc-detail-label">Resolved By</span>
                                <span class="inc-detail-value">{{ $incident->resolver?->name ?: '—' }}</span>
                            </div>
                        @endif
                    </div>
                </section>

                <!-- Response History -->
                <section class="inc-card">
                    <div class="inc-card-header">
                        <div>
                            <h2>Response History</h2>
                            <p>Status changes and operational responses.</p>
                        </div>
                    </div>

                    @if($incident->responses->isNotEmpty())
                        <div class="inc-timeline">
                            @foreach($incident->responses->sortByDesc('created_at') as $response)
                                @php
                                    $dotClass = match ($response->status) {
                                        'Resolved' => 'success',
                                        'Cancelled' => 'inactive',
                                        'Replacement Bus Dispatched' => 'amber',
                                        'Reported' => 'danger',
                                        default => '',
                                    };
                                @endphp

                                <div class="inc-timeline-item">
                                    <span class="inc-timeline-dot {{ $dotClass }}">
                                        <i class="fa-solid fa-circle"></i>
                                    </span>

                                    <div class="inc-tl-head">
                                        <x-ui.status-badge :status="$response->status" class="inc-tl-status" />
                                        <span class="inc-tl-time">
                                            {{ $response->created_at?->format('M d, Y g:i A') }}
                                        </span>
                                    </div>

                                    @if($response->notes)
                                        <div class="inc-tl-notes">{{ $response->notes }}</div>
                                    @endif

                                    <div class="inc-tl-responder">
                                        {{ $response->responder?->name ?: 'System' }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="inc-no-match">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            <div>
                                <strong>No response recorded yet</strong>
                                <span>Status changes made here will appear as timeline entries.</span>
                            </div>
                        </div>
                    @endif

                    <div class="inc-form-note" style="margin-top: 16px;">
                        <i class="fa-solid fa-circle-info"></i>
                        <div>
                            <strong>Response log</strong>
                            <span>Every status update or note is recorded with its operator and time so the incident history stays auditable.</span>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Related Operational Records -->
            <section class="inc-card">
                <div class="inc-card-header">
                    <div>
                        <h2>Related Operational Records</h2>
                        <p>Connections to the trip schedule, affected vehicle, reserve bus deployment, and Maintenance referral.</p>
                    </div>
                </div>

                <div class="inc-wf-related-grid inc-wf-records-grid">
                    <article>
                        <i class="fa-solid fa-calendar-check"></i>
                        <span>Trip Schedule</span>
                        <strong>{{ $incident->tripSchedule?->trip_code ?: 'Not linked' }}</strong>
                        <small>{{ $incident->tripSchedule ? 'Linked schedule' : 'No trip schedule associated with this report' }}</small>
                    </article>
                    <article>
                        <i class="fa-solid fa-bus"></i>
                        <span>Affected Bus</span>
                        <strong>{{ $incident->bus?->bus_no ?: 'Not linked' }}</strong>
                        <small>{{ $incident->bus?->plate_no ?: 'Bus plate not recorded' }}</small>
                    </article>
                    <article>
                        <i class="fa-solid fa-truck-fast"></i>
                        <span>Replacement Bus</span>
                        <strong>{{ $incident->replacement?->replacementBus?->bus_no ?: 'Not recorded' }}</strong>
                        <small>{{ $incident->replacement ? 'Replacement deployment recorded' : 'No replacement deployment recorded' }}</small>
                    </article>
                    <article>
                        <i class="fa-solid fa-screwdriver-wrench"></i>
                        <span>Maintenance Referral</span>
                        <strong>{{ $hasReferral ? 'Recorded' : 'Not recorded' }}</strong>
                        <small>{{ $hasReferral ? 'Referral linked to this incident' : 'No maintenance referral linked' }}</small>
                    </article>
                </div>
            </section>

            @if($incident->replacement)
                <section class="inc-card">
                    <div class="inc-card-header">
                        <div>
                            <h2>Replacement Bus Dispatch</h2>
                            <p>Replacement bus sent out for this breakdown.</p>
                        </div>
                    </div>

                    <div class="inc-replacement-banner">
                        <i class="fa-solid fa-truck-fast"></i>
                        <div>
                            <span>
                                <x-ui.id-badge :value="$incident->replacement->originalBus?->bus_no" />
                                <i class="fa-solid fa-arrow-right" style="margin: 0 6px; font-size: 11px;"></i>
                                <x-ui.id-badge :value="$incident->replacement->replacementBus?->bus_no" />
                            </span>
                            <small>
                                Dispatched {{ $incident->replacement->dispatched_at?->format('M d, Y g:i A') }}
                                &middot;
                                {{ $incident->replacement->dispatcher?->name ?: 'Operations' }}
                            </small>
                        </div>
                    </div>

                    <div class="inc-detail-grid">
                        <div class="inc-detail-item">
                            <span class="inc-detail-label">Original Bus</span>
                            <span class="inc-detail-value">{{ $incident->replacement->originalBus?->bus_no }}</span>
                        </div>

                        <div class="inc-detail-item">
                            <span class="inc-detail-label">Replacement Bus</span>
                            <span class="inc-detail-value">{{ $incident->replacement->replacementBus?->bus_no }}</span>
                        </div>

                        <div class="inc-detail-item">
                            <span class="inc-detail-label">Continuing Driver</span>
                            <span class="inc-detail-value">
                                {{ $incident->tripAssignment?->driver_name ?: ($incident->driver_name ?: '—') }}
                            </span>
                        </div>

                        <div class="inc-detail-item">
                            <span class="inc-detail-label">Dispatch Time</span>
                            <span class="inc-detail-value">
                                {{ $incident->replacement->dispatched_at?->format('M d, Y g:i A') }}
                            </span>
                        </div>
                    </div>
                </section>
            @endif

            <!-- Operations Actions -->
            <section class="inc-card">
                <div class="inc-card-header">
                    <div>
                        <h2>Record Operational Updates</h2>
                        <p>Log information received from OIC and Maintenance. Neither OIC nor drivers need system accounts.</p>
                    </div>
                </div>

                <div class="inc-detail-grid">
                    <!-- Status update -->
                    <div class="inc-detail-item full">
                        @if(!$canEditIncident)
                            <div class="inc-no-match">
                                <i class="fa-solid fa-lock"></i>
                                <div>
                                    <strong>View-only access</strong>
                                    <span>Your role does not have permission to modify incident operations.</span>
                                </div>
                            </div>
                        @else
                        <form
                            method="POST"
                            action="{{ route('incidents.update', ['incident' => $incident->incident_no]) }}"
                            class="inc-status-form"
                        >
                            @csrf
                            @method('PUT')
                            @if(request()->boolean('embedded'))
                                <input type="hidden" name="embedded" value="1">
                            @endif

                            <div class="inc-form-group">
                                <label for="statusSelect">
                                    Incident Status
                                    <span class="ui-required">*</span>
                                </label>

                                <select name="status" id="statusSelect" required>
                                    @foreach($statusFormOptions as $statusOption)
                                        <option
                                            value="{{ $statusOption }}"
                                            @selected($incident->status === $statusOption)
                                            @disabled($isClosed && $incident->status !== $statusOption)
                                        >
                                            {{ $statusOption }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="inc-form-group">
                                @if($isBreakdown && $incident->trip_schedule_id)
                                <label for="trip_outcome">Verified Trip Outcome</label>
                                <select name="trip_outcome" id="trip_outcome">
                                    <option value="">Select when confirmed...</option>
                                    @foreach(['Continued','Interrupted','Cancelled','Unknown'] as $outcome)
                                        <option value="{{ $outcome }}" @selected(old('trip_outcome') === $outcome)>{{ $outcome }}</option>
                                    @endforeach
                                </select>
                                <small>This describes the trip response; it does not mark the scheduled trip Completed.</small>
                            @endif
                            <label for="resolution_notes">OIC / Maintenance Coordination Update</label>
                                <textarea
                                    name="resolution_notes"
                                    id="resolution_notes"
                                    placeholder="Record the update relayed by OIC, coordination with Maintenance Manager, and operational decisions..."
                                >{{ old('resolution_notes') }}</textarea>
                            </div>

                            <div class="inc-form-actions" style="margin-top: 4px; padding-top: 10px; border-top: none;">
                                <button type="submit" class="inc-primary-btn">
                                    <i class="fa-solid fa-arrow-right-arrow-left"></i>
                                    Update Status
                                </button>
                            </div>
                        </form>
                        @endif
                    </div>

                    <!-- Breakdown: dispatch replacement bus -->
                    @if($isBreakdown)
                        <div class="inc-detail-item full">
                            <div class="inc-form-group">
                                <label>
                                    Record OIC Reserve Bus Deployment
                                </label>

                                @if($incident->replacement)
                                    <div class="inc-no-match">
                                        <i class="fa-solid fa-circle-check" style="color: var(--inc-green);"></i>
                                        <div>
                                            <strong>Replacement bus already dispatched</strong>
                                            <span>
                                                {{ $incident->replacement->replacementBus?->bus_no }}
                                                was dispatched for this breakdown.
                                            </span>
                                        </div>
                                    </div>
                                @elseif($isClosed)
                                    <div class="inc-no-match">
                                        <i class="fa-solid fa-circle-info"></i>
                                        <div>
                                            <strong>Incident is {{ $incident->status }}</strong>
                                            <span>A replacement bus cannot be dispatched for this incident anymore.</span>
                                        </div>
                                    </div>
                                @elseif(!$canEditIncident)
                                    <div class="inc-no-match">
                                        <i class="fa-solid fa-lock"></i>
                                        <div>
                                            <strong>View-only access</strong>
                                            <span>Only authorized Operations personnel can record Reserve Bus deployment.</span>
                                        </div>
                                    </div>
                                @else
                                    <form
                                        method="POST"
                                        action="{{ route('incidents.dispatch', ['incident' => $incident->incident_no]) }}"
                                        class="inc-status-form"
                                    >
                                        @csrf
                                        @if(request()->boolean('embedded'))
                                            <input type="hidden" name="embedded" value="1">
                                        @endif

                                        <div class="inc-form-group">
                                            <label for="replacement_bus_id">Available Reserve Buses</label>
                                            <select name="replacement_bus_id" id="replacement_bus_id" required>
                                                <option value="">Select an available bus...</option>
                                                @forelse($eligibleReplacementBuses as $replacementBus)
                                                    <option value="{{ $replacementBus->id }}">
                                                        {{ $replacementBus->bus_no }}
                                                        @if($replacementBus->plate_no)
                                                            ({{ $replacementBus->plate_no }})
                                                        @endif
                                                    </option>
                                                @empty
                                                    <option value="" disabled>No active reserve buses available</option>
                                                @endforelse
                                            </select>
                                        </div>

                                        @if($incident->trip_schedule_id)
                                            <p class="inc-form-note" style="margin-top: 10px;">
                                                <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                                                The assigned driver ({{ $incident->tripAssignment?->driver_name ?: $incident->driver_name ?: 'original driver' }}) automatically continues with the Reserve Bus.
                                            </p>
                                        @endif

                                        <div class="inc-form-note" style="margin-top: 12px;">
                                            <i class="fa-solid fa-circle-info"></i>
                                            <div>
                                                <strong>Reserve replacement eligibility</strong>
                                                <span>Record only the Reserve Bus actually selected by OIC. Only active Reserve buses without an unresolved Maintenance Job Order or overlapping trip assignment are offered.</span>
                                            </div>
                                        </div>

                                        <div class="inc-form-group">
                                            <label style="display:flex;align-items:flex-start;gap:8px">
                                                <input type="checkbox" name="oic_authorized" value="1" required style="width:auto;margin-top:3px">
                                                I confirm the OIC authorized deployment of the selected Reserve Bus.
                                            </label>
                                        </div>
                                        <div class="inc-form-actions" style="margin-top: 4px; padding-top: 10px; border-top: none;">
                                            <button type="submit" class="inc-danger-btn" @disabled(!$hasOicResponse) title="{{ $hasOicResponse ? 'Confirm authorized reserve deployment' : 'Record OIC response first' }}">
                                                <i class="fa-solid fa-truck-fast"></i>
                                                Record Reserve Bus Deployment
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </section>

            @if(request()->boolean('embedded'))
                <div class="inc-embedded-modal-footer">
                    <button type="button" class="inc-secondary-btn" onclick="window.parent.postMessage('close-incident-modal', '*')">
                        <i class="fa-solid fa-xmark"></i>
                        Close Workflow
                    </button>
                </div>
            @else
                <div class="inc-form-actions inc-back-actions">
                    <a href="{{ route('incidents') }}" class="inc-secondary-btn">
                        <i class="fa-solid fa-arrow-left"></i>
                        Back to Incident List
                    </a>
                    <a href="{{ route('incidents.create') }}" class="inc-primary-btn">
                        <i class="fa-solid fa-plus"></i>
                        Report New Incident
                    </a>
                </div>
            @endif
            @endif
        </main>
    </div>

    @if(request()->boolean('embedded'))
        @php
            $incidentToastMessage = $errors->any()
                ? $errors->first()
                : (session('error') ?: session('success'));
            $incidentToastType = $errors->any() || session('error') ? 'error' : 'success';
        @endphp
        @if($incidentToastMessage)
            <script>
                window.addEventListener('DOMContentLoaded', () => {
                    if (window.parent === window) return;
                    window.parent.postMessage({
                        type: 'gct:incident-workflow-toast',
                        message: @json($incidentToastMessage),
                        level: @json($incidentToastType)
                    }, window.location.origin);
                }, { once: true });
            </script>
        @endif
    @endif
</x-layout.app>
