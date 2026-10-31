@php
    $orderedResponses = $incident->responses->sortByDesc('created_at')->values();
    $firstPendingStep = collect($workflowSteps)->search(fn ($step) => ! $step['done']);
    $firstPendingStep = $firstPendingStep === false ? null : $firstPendingStep;
    $route = $incident->tripSchedule?->shuttleRoute;
    $scheduledTime = $incident->tripSchedule?->departure_time
        ? substr((string) $incident->tripSchedule->departure_time, 0, 5)
        : null;
    $workflowStatus = $isClosed ? $incident->status : ($hasOicResponse ? 'In Progress' : 'Waiting for OIC response');
    $currentUser = auth()->user();
    $currentDepartment = strtolower(trim((string) ($currentUser?->department ?? '')));
    $currentRole = strtolower(trim((string) ($currentUser?->role ?? '')));
    $canEditIncident = (bool) $currentUser?->hasSystemPermission('operation', 'edit');
    $canReferToMaintenance = (in_array($currentDepartment, ['operation', 'operations'], true)
            && in_array($currentRole, ['head', 'admin', 'operation head', 'operations head', 'operation admin'], true))
        || ($currentDepartment === 'admin' && in_array($currentRole, ['head', 'admin', 'system admin'], true));
@endphp

<section class="inc-workflow-board" data-embedded-incident-workflow>
    <header class="inc-wf-identity">
        <div class="inc-wf-identity-main">
            <div class="inc-wf-incident-icon {{ $isBreakdown ? 'is-breakdown' : '' }}">
                <i class="fa-solid {{ $isBreakdown ? 'fa-bus' : 'fa-triangle-exclamation' }}"></i>
            </div>
            <div class="inc-wf-identity-copy">
                <div class="inc-wf-title-row">
                    <h1>{{ $incident->incident_no }}</h1>
                    <x-ui.status-badge :status="$incident->status" />
                </div>
                <strong class="inc-wf-type">{{ $incident->incident_type }}</strong>
                <p>
                    Reported {{ $incident->incident_reported_at?->format('g:i A') ?? '—' }}
                    <span aria-hidden="true">|</span>
                    {{ $incident->location ?: 'Location not recorded' }}
                </p>
            </div>
        </div>

    </header>

    @if(session('error') || $errors->any())
        <div class="inc-wf-flash is-error" role="alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ session('error') ?? $errors->first() }}</span>
        </div>
    @endif

    <nav class="inc-wf-tabs" role="tablist" aria-label="Incident workflow sections">
        <button type="button" class="is-active" role="tab" aria-selected="true" data-wf-tab="workflow">
            <i class="fa-solid fa-diagram-project"></i>Workflow
        </button>
        <button type="button" role="tab" aria-selected="false" data-wf-tab="details">
            <i class="fa-solid fa-file-lines"></i>Details
        </button>
        <button type="button" role="tab" aria-selected="false" data-wf-tab="updates">
            <i class="fa-regular fa-clock"></i>Updates
        </button>
        <button type="button" role="tab" aria-selected="false" data-wf-tab="records">
            <i class="fa-solid fa-link"></i>Related Records
        </button>
    </nav>

    <div class="inc-wf-content">
        <main class="inc-wf-main">
            <section class="inc-wf-panel is-active" role="tabpanel" data-wf-panel="workflow">
                <div class="inc-wf-section-heading">
                    <span><i class="fa-solid fa-route"></i></span>
                    <div>
                        <h2>Incident Workflow</h2>
                        <p>Track the response using verified updates received from OIC and Maintenance.</p>
                    </div>
                </div>

                <div class="inc-wf-timeline">
                    @foreach($workflowSteps as $stepIndex => $step)
                        @if($stepIndex === 3)
                            <div class="inc-wf-parallel-heading">
                                <i class="fa-solid fa-code-branch" aria-hidden="true"></i>
                                <span><strong>Parallel follow-up activities</strong><small>Trip continuation and maintenance referral are tracked independently.</small></span>
                            </div>
                            <div class="inc-wf-parallel-steps">
                        @endif
                        @php
                            $isActiveStep = !$step['done'] && $firstPendingStep === $stepIndex;
                            $stepState = $step['done'] ? 'Completed' : ($isActiveStep ? 'In Progress' : 'Pending');
                        @endphp
                        <article class="inc-wf-step {{ $step['done'] ? 'is-complete' : '' }} {{ $isActiveStep ? 'is-active' : '' }} {{ $stepIndex >= 3 ? 'is-parallel' : '' }}">
                            <div class="inc-wf-step-rail">
                                <span>{{ $step['done'] ? '✓' : $stepIndex + 1 }}</span>
                            </div>
                            <div class="inc-wf-step-card">
                                <div class="inc-wf-step-head">
                                    <span class="inc-wf-step-icon"><i class="fa-solid {{ $step['icon'] }}"></i></span>
                                    <div>
                                        <h3>{{ $step['label'] }}</h3>
                                        <p>{{ $step['note'] }}</p>
                                    </div>
                                    <span class="inc-wf-step-state {{ $step['done'] ? 'is-complete' : ($isActiveStep ? 'is-active' : '') }}">
                                        <i class="fa-solid {{ $step['done'] ? 'fa-circle-check' : ($isActiveStep ? 'fa-circle-dot' : 'fa-clock') }}"></i>
                                        {{ $stepState }}
                                    </span>
                                </div>

                                @if($stepIndex === 0)
                                    <div class="inc-wf-step-note">
                                        <i class="fa-solid fa-user"></i>
                                        <span><strong>Reported by:</strong> {{ $incident->reporter?->name ?: 'Operations' }}<br>{{ $incident->incident_reported_at?->format('M d, Y · g:i A') }} · {{ $incident->location ?: 'Location not recorded' }}</span>
                                    </div>
                                @elseif($stepIndex === 1 && $canEditIncident && !$isClosed)
                                    <form method="POST" action="{{ route('incidents.response', ['incident' => $incident->incident_no]) }}" class="inc-wf-inline-form">
                                        @csrf
                                        <input type="hidden" name="embedded" value="1">
                                        <label for="oic_response_notes">OIC response received</label>
                                        <div class="inc-wf-inline-fields">
                                            <textarea id="oic_response_notes" name="notes" rows="2" required maxlength="2000" placeholder="Record the response relayed by OIC..."></textarea>
                                            <button type="submit" class="inc-wf-primary-btn"><i class="fa-solid fa-pen"></i>Record OIC Response</button>
                                        </div>
                                    </form>
                                @elseif($stepIndex === 2 && $isBreakdown)
                                    @if($incident->replacement)
                                        <div class="inc-wf-step-note is-success">
                                            <i class="fa-solid fa-bus"></i>
                                            <span><strong>{{ $incident->replacement->replacementBus?->bus_no }}</strong> deployed {{ $incident->replacement->dispatched_at?->format('M d, Y · g:i A') }} by {{ $incident->replacement->dispatcher?->name ?: 'Operations' }}.</span>
                                        </div>
                                    @elseif($canEditIncident && !$isClosed)
                                        <form method="POST" action="{{ route('incidents.dispatch', ['incident' => $incident->incident_no]) }}" class="inc-wf-inline-form">
                                            @csrf
                                            <input type="hidden" name="embedded" value="1">
                                            <label for="replacement_bus_id">Record the reserve bus actually selected by OIC</label>
                                            <div class="inc-wf-inline-fields is-select">
                                                <select name="replacement_bus_id" id="replacement_bus_id" required>
                                                    <option value="">Select an eligible reserve bus...</option>
                                                    @forelse($eligibleReplacementBuses as $replacementBus)
                                                        <option value="{{ $replacementBus->id }}">{{ $replacementBus->bus_no }}{{ $replacementBus->plate_no ? ' · '.$replacementBus->plate_no : '' }}</option>
                                                    @empty
                                                        <option value="" disabled>No eligible reserve buses available</option>
                                                    @endforelse
                                                </select>
                                                <button type="submit" class="inc-wf-outline-btn" @disabled($eligibleReplacementBuses->isEmpty() || !$hasOicResponse)><i class="fa-solid fa-bus"></i>Record Bus Deployment</button>
                                            </div>
                                            @if($incident->trip_schedule_id)
                                                <p class="inc-wf-step-note" style="margin-top:8px;">
                                                    <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                                                    Original assigned driver ({{ $incident->tripAssignment?->driver_name ?: $incident->driver_name ?: 'Assigned Driver' }}) continues automatically with the Reserve Bus.
                                                </p>
                                            @endif
                                            @unless($hasOicResponse)
                                                <p class="inc-wf-step-note">Record the OIC response above before confirming deployment.</p>
                                            @endunless
                                            <label class="inc-wf-authorization-check" style="display:flex;align-items:flex-start;gap:8px;margin-top:10px;font-size:12px">
                                                <input type="checkbox" name="oic_authorized" value="1" required style="width:auto;margin-top:2px">
                                                I confirm the OIC authorized deployment of the selected Reserve Bus.
                                            </label>
                                        </form>
                                    @endif
                                @elseif($stepIndex === 3 && $canEditIncident && !$isClosed)
                                    <button type="button" class="inc-wf-outline-btn inc-wf-step-action" data-wf-show-panel="updates" data-wf-focus="statusSelect"><i class="fa-solid fa-route"></i>Update Incident Status</button>
                                @elseif($stepIndex === 4 && $isBreakdown)
                                    @if($hasReferral)
                                        <div class="inc-wf-step-note is-success"><i class="fa-solid fa-screwdriver-wrench"></i><span>Maintenance referral is already recorded and protected as operational history.</span></div>
                                    @elseif($canReferToMaintenance && !$isClosed)
                                        <form method="POST" action="{{ route('incidents.maintenance-referral.store', $incident) }}" class="inc-wf-inline-form">
                                            @csrf
                                            <label for="maintenance_referral_notes">Maintenance referral notes</label>
                                            <div class="inc-wf-inline-fields">
                                                <textarea id="maintenance_referral_notes" name="notes" rows="2" maxlength="2000" placeholder="Describe the inspection or repair required..."></textarea>
                                                <button type="submit" class="inc-wf-outline-btn"><i class="fa-solid fa-screwdriver-wrench"></i>Record Maintenance Referral</button>
                                            </div>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </article>
                        @if($stepIndex === 4)
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>

            <section class="inc-wf-panel inc-wf-details-panel" role="tabpanel" data-wf-panel="details" hidden>
                <div class="inc-wf-section-heading">
                    <span><i class="fa-solid fa-file-lines"></i></span>
                    <div><h2>Incident Details</h2><p>A clear, read-only summary of the information recorded for this incident.</p></div>
                </div>

                <div class="inc-wf-details-highlight">
                    <span class="inc-wf-details-highlight-icon"><i class="fa-solid {{ $isBreakdown ? 'fa-bus' : 'fa-triangle-exclamation' }}"></i></span>
                    <div class="inc-wf-details-highlight-main">
                        <span class="inc-wf-details-eyebrow">Incident Reference</span>
                        <h3>{{ $incident->incident_no }}</h3>
                        <p>{{ $incident->incident_type }} <span aria-hidden="true">·</span> {{ $incident->location ?: 'Location not recorded' }}</p>
                    </div>
                    <x-ui.status-badge :status="$incident->status" />
                </div>

                <div class="inc-wf-details-section-title"><i class="fa-solid fa-circle-info"></i><span>Report Information</span></div>
                <dl class="inc-wf-detail-list inc-wf-details-grid">
                    <div><dt><i class="fa-regular fa-calendar"></i> Reported On</dt><dd>{{ $incident->incident_reported_at?->format('M d, Y') ?: 'Not recorded' }}</dd></div>
                    <div><dt><i class="fa-regular fa-clock"></i> Reported Time</dt><dd>{{ $incident->incident_reported_at?->format('g:i A') ?: 'Not recorded' }}</dd></div>
                    <div><dt><i class="fa-solid fa-user-shield"></i> Encoded By</dt><dd>{{ $incident->reporter?->name ?: 'Not recorded' }}</dd></div>
                    <div><dt><i class="fa-solid fa-location-dot"></i> Location</dt><dd>{{ $incident->location ?: 'Not recorded' }}</dd></div>
                </dl>

                <div class="inc-wf-details-section-title"><i class="fa-solid fa-bus"></i><span>Vehicle &amp; Trip</span></div>
                <dl class="inc-wf-detail-list inc-wf-details-grid">
                    <div><dt><i class="fa-solid fa-bus-simple"></i> Affected Bus</dt><dd>{{ $incident->bus?->bus_no ?: 'Not recorded' }} @if($incident->bus?->plate_no)<small>{{ $incident->bus->plate_no }}</small>@endif</dd></div>
                    <div><dt><i class="fa-solid fa-user"></i> Original Driver</dt><dd>{{ $incident->driver_name ?: 'Not recorded' }}</dd></div>
                    <div><dt><i class="fa-solid fa-route"></i> Route</dt><dd>{{ $route?->route_name ?: 'Not recorded' }}</dd></div>
                    <div><dt><i class="fa-solid fa-clock"></i> Scheduled Departure</dt><dd>{{ $scheduledTime ?: 'Not recorded' }}</dd></div>
                </dl>

                <div class="inc-wf-details-section-title"><i class="fa-solid fa-align-left"></i><span>Incident Description</span></div>
                <div class="inc-wf-details-description">{{ $incident->description ?: 'No description recorded.' }}</div>

                @if($incident->replacement)
                    <div class="inc-wf-details-section-title"><i class="fa-solid fa-truck-fast"></i><span>Replacement Information</span></div>
                    <div class="inc-wf-details-replacement">
                        <i class="fa-solid fa-circle-check"></i>
                        <div><strong>{{ $incident->replacement->replacementBus?->bus_no ?: 'Replacement Bus' }} recorded</strong>
                            <span>{{ $incident->replacement->dispatched_at?->format('M d, Y · g:i A') ?: 'Dispatch time not recorded' }}</span>
                        </div>
                    </div>
                @endif
                <p class="inc-wf-details-footnote"><i class="fa-solid fa-lock"></i> Read-only information. Operational updates are recorded in the Updates tab.</p>
            </section>

            <section class="inc-wf-panel inc-wf-updates-panel" role="tabpanel" data-wf-panel="updates" hidden>
                <div class="inc-wf-section-heading"><span><i class="fa-regular fa-clock"></i></span><div><h2>Record Operational Update</h2><p>Capture verified information received from OIC or Maintenance.</p></div></div>
                <div class="inc-wf-update-context">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <div><strong>Operations records the update.</strong><p>The driver and OIC communicate outside the system. Select the actual incident status and enter what was confirmed. Saved updates appear in Recent Updates.</p></div>
                </div>
                @if(!$canEditIncident)
                    <div class="inc-wf-empty"><i class="fa-solid fa-lock"></i><strong>View-only access.</strong><span>Your role cannot modify incident operations.</span></div>
                @elseif($isClosed)
                    <div class="inc-wf-empty"><i class="fa-solid fa-lock"></i><strong>This incident is {{ $incident->status }}.</strong><span>Further operational updates are protected.</span></div>
                @else
                    <form method="POST" action="{{ route('incidents.update', ['incident' => $incident->incident_no]) }}" class="inc-wf-update-form inc-wf-update-card">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="embedded" value="1">
                        <div class="inc-wf-update-form-heading"><i class="fa-solid fa-pen-to-square"></i><div><strong>Update Incident Record</strong><small>Current status: {{ $incident->status }}</small></div></div>
                        <div class="inc-wf-update-field">
                            <label for="statusSelect">Incident Status <span>*</span></label>
                            <select name="status" id="statusSelect" required>
                                @foreach($statusFormOptions as $statusOption)
                                    <option value="{{ $statusOption }}" @selected($incident->status === $statusOption)>{{ $statusOption }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="inc-wf-update-field">
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
                            <textarea name="resolution_notes" id="resolution_notes" rows="5" maxlength="2000" placeholder="Record the verified response, operational decision, trip continuation, or resolution details...">{{ old('resolution_notes') }}</textarea>
                            <small>Record only information actually confirmed by OIC or Maintenance.</small>
                        </div>
                        <div class="inc-wf-update-footer">
                            <span><i class="fa-solid fa-clock-rotate-left"></i> Changes are recorded in the incident history.</span>
                            <button type="submit" class="inc-wf-primary-btn"><i class="fa-solid fa-floppy-disk"></i>Save Operational Update</button>
                        </div>
                    </form>
                @endif
            </section>

            <section class="inc-wf-panel inc-wf-records-panel" role="tabpanel" data-wf-panel="records" hidden>
                <div class="inc-wf-section-heading"><span><i class="fa-solid fa-link"></i></span><div><h2>Related Records</h2><p>View connections to the trip, affected vehicle, replacement, and Maintenance.</p></div></div>
                <div class="inc-wf-records-overview">
                    <i class="fa-solid fa-network-wired"></i>
                    <div><strong>Incident Record Connections</strong><p>These records reflect the existing links in GCT-Systems. Missing links are shown as not recorded; none are created from this tab.</p></div>
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
                <div class="inc-wf-records-footnote"><i class="fa-solid fa-circle-info"></i> This tab is read-only. Manage linked records through their respective modules and permissions.</div>
            </section>
        </main>

        <aside class="inc-wf-sidebar">
            <section class="inc-wf-side-card">
                <h2><i class="fa-solid fa-circle-info"></i>Incident Information</h2>
                <dl class="inc-wf-side-details">
                    <div><dt>Incident ID</dt><dd>{{ $incident->incident_no }}</dd></div>
                    <div><dt>Incident Type</dt><dd>{{ $incident->incident_type }}</dd></div>
                    <div><dt>Affected Bus</dt><dd>{{ $incident->bus?->bus_no ?: 'Not recorded' }}{{ $incident->bus?->plate_no ? ' ('.$incident->bus->plate_no.')' : '' }}</dd></div>
                    <div><dt>Original Driver</dt><dd>{{ $incident->driver_name ?: 'Not recorded' }}</dd></div>
                    <div><dt>Route</dt><dd>{{ $route?->route_name ?: 'Not recorded' }}</dd></div>
                    <div><dt>Scheduled Time</dt><dd>{{ $scheduledTime ?: 'Not recorded' }}</dd></div>
                    <div><dt>Location</dt><dd>{{ $incident->location ?: 'Not recorded' }}</dd></div>
                    <div><dt>Remarks</dt><dd>{{ $incident->description ?: 'No remarks recorded.' }}</dd></div>
                </dl>
            </section>

            <section class="inc-wf-side-card">
                <h2><i class="fa-solid fa-wave-square"></i>Current Status</h2>
                <p class="inc-wf-progress-caption">Initial response</p>
                <div class="inc-wf-mini-progress">
                    @foreach(collect($workflowSteps)->take(3) as $stepIndex => $step)
                        <span class="{{ $step['done'] ? 'is-complete' : ($firstPendingStep === $stepIndex ? 'is-active' : '') }}" title="{{ $step['label'] }}">{{ $step['done'] ? '✓' : $stepIndex + 1 }}</span>
                        @if(!$loop->last)<i class="{{ $step['done'] ? 'is-complete' : '' }}"></i>@endif
                    @endforeach
                </div>
                <p class="inc-wf-progress-caption">Independent follow-ups</p>
                <div class="inc-wf-followup-summary">
                    @foreach(collect($workflowSteps)->slice(3) as $stepIndex => $step)
                        <div><i class="fa-solid {{ $step['icon'] }}"></i><span>{{ $step['label'] }}</span><strong class="{{ $step['done'] ? 'is-complete' : 'is-pending' }}">{{ $step['done'] ? 'Recorded' : 'Pending' }}</strong></div>
                    @endforeach
                </div>
                <div class="inc-wf-current-message {{ $isClosed ? 'is-closed' : '' }}"><i class="fa-solid fa-circle-info"></i><span><strong>{{ $workflowStatus }}.</strong><br>{{ $isClosed ? 'This incident workflow is protected.' : 'Operations can record the next verified update.' }}</span></div>
            </section>

            <section class="inc-wf-side-card">
                <h2><i class="fa-regular fa-clock"></i>Recent Updates</h2>
                <div class="inc-wf-recent-list">
                    @forelse($orderedResponses->take(4) as $response)
                        <article><span></span><div><strong>{{ $response->status }}</strong><small>{{ $response->notes ?: 'Status update recorded.' }}</small></div><time>{{ $response->created_at?->format('M d, Y') }}<br>{{ $response->created_at?->format('g:i A') }}</time></article>
                    @empty
                        <p>No updates recorded yet.</p>
                    @endforelse
                </div>
            </section>

            @if($canEditIncident || $canReferToMaintenance)
            <section class="inc-wf-side-card inc-wf-actions-card">
                <h2><i class="fa-solid fa-bolt"></i>Actions (Operations Only)</h2>
                @if($canEditIncident && !$isClosed)
                    <button type="button" class="inc-wf-primary-btn" data-wf-show-panel="workflow" data-wf-focus="oic_response_notes"><i class="fa-solid fa-pen"></i>Record OIC Response</button>
                @endif
                @if($canEditIncident && $isBreakdown && !$incident->replacement && !$isClosed)
                    <button type="button" class="inc-wf-outline-btn" data-wf-show-panel="workflow" data-wf-focus="replacement_bus_id"><i class="fa-solid fa-bus"></i>Record Bus Deployment</button>
                @endif
                @if($canEditIncident && !$isClosed)
                    <button type="button" class="inc-wf-outline-btn" data-wf-show-panel="updates" data-wf-focus="statusSelect"><i class="fa-solid fa-route"></i>Update Incident Status</button>
                @endif
                @if($canReferToMaintenance && $isBreakdown && !$hasReferral && !$isClosed)
                    <button type="button" class="inc-wf-outline-btn" data-wf-show-panel="workflow" data-wf-focus="maintenance_referral_notes"><i class="fa-solid fa-screwdriver-wrench"></i>Record Maintenance Referral</button>
                @endif
            </section>
            @endif
        </aside>
    </div>
</section>
