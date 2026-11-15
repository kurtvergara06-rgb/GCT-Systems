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

        $statusFormOptions = [
            'Reported',
            'Monitoring',
            'Responding',
            'Replacement Bus Dispatched',
            'Resolved',
            'Cancelled',
        ];

        $eligibleReplacementBuses = $activeBuses
            ->reject(fn ($bus) => $incident->bus_id && $bus->id === $incident->bus_id)
            ->reject(fn ($bus) => $incident->replacement && $bus->id === $incident->replacement->replacement_bus_id);
    @endphp

    <div class="app">
        <x-layout.sidebar department="Operation" />

        <main class="main inc-page">
            <x-layout.topbar
                title="Incident Details"
                subtitle="{{ $incident->incident_no }} &mdash; {{ $incident->incident_type }}"
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

                        @if($incident->reporter)
                            <div class="inc-detail-item">
                                <span class="inc-detail-label">Reported By</span>
                                <span class="inc-detail-value">{{ $incident->reporter->name }}</span>
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
                                        <span class="inc-tl-status">{{ $response->status }}</span>
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
                        <h2>Operations Actions</h2>
                        <p>Respond to and manage this incident.</p>
                    </div>
                </div>

                <div class="inc-detail-grid">
                    <!-- Status update -->
                    <div class="inc-detail-item full">
                        <form
                            method="POST"
                            action="{{ route('incidents.update', ['incident' => $incident->incident_no]) }}"
                            class="inc-status-form"
                        >
                            @csrf
                            @method('PUT')

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
                                <label for="resolution_notes">Response / Resolution Notes</label>
                                <textarea
                                    name="resolution_notes"
                                    id="resolution_notes"
                                    placeholder="Notes about the response, monitoring plan, or resolution details..."
                                >{{ old('resolution_notes') }}</textarea>
                            </div>

                            <div class="inc-form-actions" style="margin-top: 4px; padding-top: 10px; border-top: none;">
                                <button type="submit" class="inc-primary-btn">
                                    <i class="fa-solid fa-arrow-right-arrow-left"></i>
                                    Update Status
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Breakdown: dispatch replacement bus -->
                    @if($isBreakdown)
                        <div class="inc-detail-item full">
                            <div class="inc-form-group">
                                <label>
                                    Dispatch Replacement Bus
                                    <span class="ui-required">*</span>
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
                                @else
                                    <form
                                        method="POST"
                                        action="{{ route('incidents.dispatch', ['incident' => $incident->incident_no]) }}"
                                        class="inc-status-form"
                                    >
                                        @csrf

                                        <div class="inc-form-group">
                                            <label for="replacement_bus_id">Available Active Buses</label>
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
                                                    <option value="" disabled>No active buses available</option>
                                                @endforelse
                                            </select>
                                        </div>

                                        <div class="inc-form-note" style="margin-top: 12px;">
                                            <i class="fa-solid fa-circle-info"></i>
                                            <div>
                                                <strong>Eligibility reuses existing bus logic</strong>
                                                <span>Only buses marked {{ "'Active'" }} are offered, matching the same availability used by the driver &amp; bus assignment module. Buses with an overlapping assigned trip at this time are excluded.</span>
                                            </div>
                                        </div>

                                        <div class="inc-form-actions" style="margin-top: 4px; padding-top: 10px; border-top: none;">
                                            <button type="submit" class="inc-danger-btn">
                                                <i class="fa-solid fa-truck-fast"></i>
                                                Dispatch Replacement Bus
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </section>

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
        </main>
    </div>
</x-layout.app>