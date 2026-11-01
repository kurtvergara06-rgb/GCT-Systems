<x-layout.app
    title="FROMS - PMS Scheduling"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Main-styles/form-components.css',
        'resources/css/Maintenance/pms-scheduling.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Maintenance/pms-scheduling.js'
    ]"
>

    <div class="app">

        {{-- =====================================================
            SIDEBAR
        ====================================================== --}}
        <x-layout.sidebar department="Maintenance" />


        <main class="main pms-page">

            {{-- =====================================================
                TOPBAR
            ====================================================== --}}
            <x-layout.topbar
                title="PMS Scheduling"
                subtitle="Monitor bus-level preventive maintenance thresholds and available mileage estimates."
                notification-count="6"
            />


            {{-- =====================================================
                SUMMARY
            ====================================================== --}}
            <section data-ajax-region="summary" class="stats-grid pms-stats-grid">

                <x-ui.summary-card
                    label="GPS Records Today"
                    value="{{ $gpsRecordsToday }}"
                    small="Processed mileage reports"
                    icon="fa-file-lines"
                    color="blue"
                />

                <x-ui.summary-card
                    label="Upcoming PMS"
                    value="{{ $upcomingCount }}"
                    small="Verified future monitoring thresholds"
                    icon="fa-calendar-check"
                    color="green"
                />

                <x-ui.summary-card
                    label="Mileage Unverified"
                    value="{{ $dueSoonCount }}"
                    small="Requires verified current odometer"
                    icon="fa-clock"
                    color="yellow"
                />

                <x-ui.summary-card
                    label="PMS Due"
                    value="{{ $overdueCount }}"
                    small="Calendar threshold reached"
                    icon="fa-triangle-exclamation"
                    color="red"
                />

            </section>


            {{-- =====================================================
                PMS TABLE CARD
            ====================================================== --}}
            <section data-ajax-region="records" class="table-card pms-card pms-table-card">

                <div class="section-header pms-header">

                    <div>

                        <h2>
                            Bus PMS Monitoring
                        </h2>

                        <p>
                            Monitor configured service baselines, due dates, and available trip-distance estimates.
                        </p>

                    </div>


                    @if($canConfigurePms)
                    <button
                        type="button"
                        class="pms-add-btn open-pms-tasks-modal"
                        data-modal-target="pmsConfigModal"
                    >
                        <i class="fa-solid fa-plus"></i>

                        Configure PMS
                    </button>
                    @endif

                </div>


                {{-- =================================================
                    TOOLBAR
                ================================================== --}}
                <x-ui.table-toolbar action="{{ route('PMS-Scheduling') }}" search-label="Search PMS Records" search-placeholder="Search vehicle, PMS task, or status..." :filter-count="(int) (request()->filled('status') && request('status') !== 'All Status')" filter-title="Filter PMS Records" :sort-options="['bus_no' => 'Vehicle ID', 'overall_status' => 'PMS Status']" default-sort="bus_no">
                    <x-slot:filters><x-ui.table-toolbar-field label="Status" name="status" default="All Status"><option value="All Status">All Statuses</option>@foreach(['Not Configured','Mileage Unverified','PMS Due','Inactive','Upcoming'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</x-ui.table-toolbar-field></x-slot:filters>
                </x-ui.table-toolbar>


                {{-- =================================================
                    MAIN TABLE
                ================================================== --}}
                <div class="table-wrap">

                    <table class="pms-table">

                        <thead>

                            <tr>
                                <th>Vehicle ID</th>
                                <th>Last PMS Date</th>
                                <th>Current Odometer</th>
                                <th>Next PMS KM</th>
                                <th>Next PMS Date</th>
                                <th class="status-col">PMS Status</th>
                                <th>Action</th>
                            </tr>

                        </thead>


                        <tbody>

                            @forelse($rows as $row)
                                @php $config = $monitoringByBus->get(strtoupper(trim((string)$row->bus_no))); @endphp

                                <tr>

                                    <td>
                                        <span class="bus-badge-pill">
                                            <i class="fa-solid fa-bus"></i> {{ $row->bus_no }}
                                        </span>
                                    </td>


                                    <td>{{ $config?->last_completed_pms_date?->format('M d, Y') ?? '—' }}</td>


                                    <td>
                                        @php $ddrEstimate = $config ? $ddrMileageByBus->get($config->bus_id) : null; @endphp
                                        @if($ddrEstimate)
                                            <span title="DDR-estimated odometer from recorded trip distances after the last PMS; not verified against physical odometer">{{ number_format($ddrEstimate['estimated_odometer_km'], 2) }} km <small>(DDR est.)</small></span>
                                        @else
                                            <span title="No complete DDR mileage available after last PMS, or mileage not yet verified">—</span>
                                        @endif
                                    </td>


                                    <td>{{ $config ? number_format($config->nextDueOdometerKm(), 2).' km' : '—' }}</td>

                                    <td>
                                        {{ $config?->nextDueDate()->format('M d, Y') ?? '—' }}
                                    </td>


                                    <td class="status-col">

                                        @php
                                            $monitorStatus = $config?->status() ?? 'Not Configured';
                                            $monitorTone = match ($monitorStatus) {
                                                'PMS Due' => 'is-due',
                                                'Upcoming' => 'is-upcoming',
                                                'Inactive' => 'is-inactive',
                                                'Mileage Unverified' => 'is-unverified',
                                                default => 'is-not-configured',
                                            };
                                            $monitorIcon = match ($monitorStatus) {
                                                'PMS Due' => 'fa-circle-exclamation',
                                                'Upcoming' => 'fa-calendar-check',
                                                'Inactive' => 'fa-circle-pause',
                                                'Mileage Unverified' => 'fa-gauge',
                                                default => 'fa-circle-info',
                                            };
                                        @endphp
                                        <span class="pms-monitoring-badge {{ $monitorTone }}"><i class="fa-solid {{ $monitorIcon }}" aria-hidden="true"></i> {{ $monitorStatus }}</span>

                                    </td>
                                    <td><button class="pms-view-tasks-btn open-pms-tasks-modal" type="button" data-modal-target="pmsDetails-{{ $loop->index }}" aria-label="View PMS details for {{ $row->bus_no }}"><i class="fa-solid fa-eye"></i></button></td>




                                </tr>


                            @empty

                                <x-ui.empty-row
                                    colspan="7"
                                    message="No PMS schedule records found."
                                />

                            @endforelse

                        </tbody>

                    </table>

                </div>


                <x-ui.table-footer :items="$rows" />

            </section>

        </main>

    </div>


    {{-- Bus-level PMS modals: presentation only; old task records are preserved. --}}
    @foreach($rows as $row)
        @php $config = $monitoringByBus->get(strtoupper(trim((string)$row->bus_no))); @endphp
        <div class="pms-modal-overlay pms-tasks-popup pms-monitor-dialog" id="pmsDetails-{{ $loop->index }}">
            <div class="pms-modal pms-monitor-panel" role="dialog" aria-modal="true" aria-labelledby="pmsDetailsHeading-{{ $loop->index }}">
                <div class="pms-modal-header pms-monitor-header">
                    <div class="pms-monitor-heading"><span class="pms-monitor-header-icon"><i class="fa-solid fa-bus-simple" aria-hidden="true"></i></span><div><h2 id="pmsDetailsHeading-{{ $loop->index }}">PMS Details — {{ $row->bus_no }}</h2><p>Preventive maintenance schedule and verified service baseline</p></div></div>
                    <button type="button" class="pms-close-btn close-pms-tasks-modal" data-modal-target="pmsDetails-{{ $loop->index }}" aria-label="Close PMS details"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </div>
                <div class="pms-monitor-body">
                    <div class="pms-monitor-summary">
                        <div><span>Monitoring status</span><strong>{{ $config?->status() ?? 'Not Configured' }}</strong></div>
                        <div><span>Maintenance interval</span><strong>10,000 km / 3 months</strong></div>
                    </div>
                    @if(!$config)
                        <div class="pms-monitor-empty"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><div><strong>No PMS baseline configured</strong><p>Record the verified date and odometer of the last completed PMS to activate calendar monitoring.</p></div></div>
                    @endif
                    <h3 class="pms-monitor-section-title"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i> Service history &amp; next schedule</h3>
                    <div class="pms-monitor-info-grid">
                        <div><span>Last completed PMS</span><strong>{{ $config?->last_completed_pms_date?->format('M d, Y') ?? 'Not recorded' }}</strong></div>
                        <div><span>Odometer at last service</span><strong>{{ $config ? number_format((float)$config->last_completed_pms_odometer_km, 2).' km' : 'Not recorded' }}</strong></div>
                        <div><span>Next PMS mileage</span><strong>{{ $config ? number_format($config->nextDueOdometerKm(), 2).' km' : '—' }}</strong></div>
                        <div><span>Next PMS due date</span><strong>{{ $config?->nextDueDate()->format('M d, Y') ?? '—' }}</strong></div>
                    </div>
                    @php $ddrEstimate = $config ? $ddrMileageByBus->get($config->bus_id) : null; @endphp
                    <div class="pms-monitor-source-note"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i><div><strong>Current odometer: Not verified</strong><p>@if($ddrEstimate) DDR-based estimate: {{ number_format($ddrEstimate['estimated_odometer_km'], 2) }} km from {{ $ddrEstimate['trip_count'] }} recorded trips ({{ number_format($ddrEstimate['distance_km'], 2) }} km since last completed PMS). @else Complete DDR trip mileage is not available after the service baseline. @endif GPS integration remains unchanged. DDR mileage is an estimate and is not a verified vehicle odometer.</p></div></div>
                </div>
                <div class="pms-view-footer pms-monitor-footer"><button type="button" class="ui-form-btn ui-form-btn-cancel close-pms-tasks-modal" data-modal-target="pmsDetails-{{ $loop->index }}">Close</button></div>
            </div>
        </div>
    @endforeach
    @if($canConfigurePms)
        <div class="pms-modal-overlay pms-tasks-popup pms-monitor-dialog" id="pmsConfigModal">
            <div class="pms-modal pms-monitor-panel" role="dialog" aria-modal="true" aria-labelledby="pmsConfigHeading">
                <div class="pms-modal-header pms-monitor-header">
                    <div class="pms-monitor-heading"><span class="pms-monitor-header-icon"><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i></span><div><h2 id="pmsConfigHeading">Configure Bus PMS</h2><p>Set the last verified preventive maintenance baseline</p></div></div>
                    <button type="button" class="pms-close-btn close-pms-tasks-modal" data-modal-target="pmsConfigModal" aria-label="Close PMS configuration"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </div>
                <form class="pms-monitoring-form" method="POST" action="{{ route('pms-monitoring.store') }}">
                    @csrf
                    <div class="pms-monitor-body">
                        <div class="pms-monitor-rule"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><div><strong>Automatic PMS interval</strong><p>10,000 km or 3 months after the last completed PMS, whichever comes first.</p></div></div>
                        <div class="pms-monitor-field pms-bus-picker"><label for="monitor-bus-search">Vehicle <span aria-hidden="true">*</span></label>
                            <input type="hidden" id="monitor-bus" name="bus_id" value="{{ old('bus_id') }}">
                            <div class="pms-bus-search-wrap">
                                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                <input type="search" id="monitor-bus-search" placeholder="Search and select a bus..." autocomplete="off" aria-controls="pms-bus-options" aria-expanded="false" aria-autocomplete="list" role="combobox" required>
                            </div>
                            <div id="pms-bus-options" class="pms-bus-options" role="listbox" hidden>
                                @foreach($configurableBuses as $bus)
                                    @if(!$monitoringByBus->has(strtoupper(trim((string)$bus->bus_no))))
                                        <button type="button" role="option" class="pms-bus-option" data-bus-id="{{ $bus->id }}" data-bus-label="{{ $bus->bus_no }}">{{ $bus->bus_no }}</button>
                                    @endif
                                @endforeach
                                <p class="pms-bus-no-results" hidden>No matching buses</p>
                            </div>
                        </div>
                        <h3 class="pms-monitor-section-title"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Last completed PMS</h3>
                        <div class="pms-monitor-field-grid">
                            <div class="pms-monitor-field"><label for="monitor-date">Service completion date <span aria-hidden="true">*</span></label><input id="monitor-date" name="last_completed_pms_date" type="date" data-picker-placement="below" value="{{ old('last_completed_pms_date') }}" max="{{ now()->toDateString() }}" required></div>
                            <div class="pms-monitor-field"><label for="monitor-odo">Verified odometer (km) <span aria-hidden="true">*</span></label><input id="monitor-odo" name="last_completed_pms_odometer_km" type="number" value="{{ old('last_completed_pms_odometer_km') }}" min="0" step="0.01" placeholder="e.g. 45000.00" required></div>
                        </div>
                        <p class="pms-monitor-help">Use the actual service record. Do not enter GPS trip distance as an odometer reading.</p>
                    </div>
                    <div class="pms-view-footer pms-monitor-footer"><button type="button" class="ui-form-btn ui-form-btn-cancel close-pms-tasks-modal" data-modal-target="pmsConfigModal">Cancel</button><button class="ui-form-btn pms-monitor-save" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save Baseline</button></div>
                </form>
            </div>
        </div>
    @endif
    {{-- =========================================================
        ADD PMS
        REDESIGNED MODAL
    ========================================================== --}}
    <x-ui.form-modal
        id="addPmsModal"
        title="Add PMS Task"
        description="Select a bus with processed GPS mileage data to create a preventive maintenance task."
        icon="fa-calendar-plus"
        size="large"
        form-id="addPmsForm"
        :action="route('pms-schedules.store')"
        submit-text="Save PMS Task"
        submit-icon="fa-floppy-disk"
        close-id="closeAddPmsModal"
        cancel-id="cancelAddPmsModal"
        close-data-attribute="data-close-add-pms"
        :confirm="true"
        confirm-title="Create PMS Task?"
        confirm-message="Are you sure you want to create this PMS task?"
        confirm-button="Yes, Create PMS Task"
        confirm-type="create"
        class="pms-form-modal-overlay pms-add-modal-overlay"
    >
        <div class="pms-form-layout">

            <section class="pms-form-section pms-section-blue">
                <div class="pms-form-section-header">
                    <span class="pms-form-section-icon">
                        <i class="fa-solid fa-bus"></i>
                    </span>

                    <div>
                        <h3>Bus Information</h3>
                        <p>Select the bus to view its latest processed GPS mileage data.</p>
                    </div>
                </div>

                <div class="pms-form-section-body pms-form-grid-2">
                    <div class="ui-form-group">
                        <label for="pmsBusSelect">
                            Vehicle ID / Bus No.
                            <span class="ui-required">*</span>
                        </label>

                        <div class="ui-input-wrap has-icon">
                            <span class="ui-input-icon">
                                <i class="fa-solid fa-bus"></i>
                            </span>

                            <select
                                name="bus_no"
                                id="pmsBusSelect"
                                required
                            >
                                <option value="">
                                    Select processed GPS bus
                                </option>

                                @foreach($processedBuses as $bus)
                                    <option
                                        value="{{ $bus->bus_no }}"
                                        data-current-km="{{ $bus->current_km }}"
                                        data-gps-date="{{ $bus->gps_report_date
                                            ? \Carbon\Carbon::parse($bus->gps_report_date)->format('M d, Y')
                                            : ''
                                        }}"
                                        data-gps-date-iso="{{ $bus->gps_report_date
                                            ? \Carbon\Carbon::parse($bus->gps_report_date)->format('Y-m-d')
                                            : ''
                                        }}"
                                    >
                                        {{ $bus->bus_no }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <x-ui.form-field
                        label="Current GPS KM"
                        name="display_current_gps_km"
                        id="currentGpsKm"
                        icon="fa-gauge-high"
                        placeholder="Select a bus first"
                        readonly
                    />
                </div>
            </section>

            <section class="pms-form-section pms-section-green">
                <div class="pms-form-section-header">
                    <span class="pms-form-section-icon">
                        <i class="fa-solid fa-road"></i>
                    </span>

                    <div>
                        <h3>PMS Mileage Information</h3>
                        <p>Set the completed mileage and service interval, then review the next PMS target.</p>
                    </div>
                </div>

                <div class="pms-form-section-body">
                    <div class="pms-form-grid-2">
                        <x-ui.form-field
                            label="Last PMS KM"
                            name="last_pms_km"
                            id="lastPmsKm"
                            type="number"
                            icon="fa-gauge"
                            placeholder="Enter last completed PMS KM"
                            min="0"
                            step="0.01"
                            required
                        />

                        <x-ui.form-field
                            label="PMS Interval KM"
                            name="pms_interval_km"
                            id="pmsIntervalKm"
                            type="number"
                            icon="fa-road"
                            value="5000"
                            min="1"
                            step="0.01"
                            required
                        />
                    </div>

                    <div class="pms-mileage-highlight-grid">
                        <div class="pms-mileage-highlight pms-next-mileage-highlight">
                            <span class="pms-mileage-highlight-icon">
                                <i class="fa-solid fa-flag"></i>
                            </span>

                            <div>
                                <span>Next PMS KM</span>

                                <div class="pms-highlight-field">
                                    <x-ui.form-field
                                        label=""
                                        name="display_next_pms_km"
                                        id="nextPmsKm"
                                        icon="fa-forward"
                                        placeholder="Automatic"
                                        readonly
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="pms-mileage-highlight pms-status-highlight">
                            <span class="pms-mileage-highlight-icon">
                                <i class="fa-solid fa-circle-info"></i>
                            </span>

                            <div>
                                <span>Predicted Status</span>

                                <div class="pms-highlight-field">
                                    <x-ui.form-field
                                        label=""
                                        name="display_predicted_status"
                                        id="pmsStatusPreview"
                                        icon="fa-chart-line"
                                        placeholder="Automatic"
                                        readonly
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="pms-form-bottom-grid">
                <section class="pms-form-section pms-section-blue pms-form-section-compact">
                    <div class="pms-form-section-header">
                        <span class="pms-form-section-icon">
                            <i class="fa-solid fa-gears"></i>
                        </span>

                        <div>
                            <h3>PMS Details</h3>
                            <p>Specify the preventive maintenance task.</p>
                        </div>
                    </div>

                    <div class="pms-form-section-body">
                        <x-ui.form-select
                            label="PMS Type"
                            name="maintenance_type_option"
                            id="maintenanceType"
                            icon="fa-screwdriver-wrench"
                            :options="[
                                'Change Oil' => 'Change Oil',
                                'Oil Filter' => 'Oil Filter',
                                'Brake Check' => 'Brake Check',
                                'Air Filter' => 'Air Filter',
                                'Full PMS' => 'Full PMS',
                                'Other' => 'Other',
                            ]"
                            required
                        />

                        <div
                            class="ui-form-group"
                            id="customMaintenanceTypeGroup"
                            hidden
                        >
                            <label for="customMaintenanceType">
                                Other PMS Type
                            </label>

                            <div class="ui-input-wrap has-icon">
                                <span class="ui-input-icon">
                                    <i class="fa-solid fa-pen"></i>
                                </span>

                                <input
                                    type="text"
                                    id="customMaintenanceType"
                                    name="custom_maintenance_type"
                                    maxlength="255"
                                    placeholder="Example: Transmission Fluid Replacement"
                                >
                            </div>
                        </div>

                        <input
                            type="hidden"
                            id="finalMaintenanceType"
                            name="maintenance_type"
                        >
                    </div>
                </section>

                <section class="pms-form-section pms-section-purple pms-form-section-compact">
                    <div class="pms-form-section-header">
                        <span class="pms-form-section-icon">
                            <i class="fa-solid fa-calendar-days"></i>
                        </span>

                        <div>
                            <h3>Schedule &amp; Report</h3>
                            <p>Review the GPS report date and recommended schedule.</p>
                        </div>
                    </div>

                    <div class="pms-form-section-body">
                        <x-ui.form-field
                            label="GPS Report Date"
                            name="display_gps_report_date"
                            id="gpsReportDate"
                            icon="fa-calendar-day"
                            placeholder="Select a bus first"
                            readonly
                        />

                        <x-ui.form-field
                            label="Recommended Date"
                            name="recommended_date"
                            id="recommendedDate"
                            type="date"
                            icon="fa-calendar-check"
                        />
                    </div>
                </section>
            </div>
        </div>
    </x-ui.form-modal>


    {{-- =========================================================
        EDIT PMS
        REDESIGNED MODAL
    ========================================================== --}}
    <x-ui.form-modal
        id="editPmsModal"
        title="Edit PMS Task"
        description="Update the selected preventive maintenance task."
        icon="fa-calendar-check"
        size="large"
        form-id="editPmsForm"
        action="#"
        method="PUT"
        submit-text="Update PMS Task"
        submit-icon="fa-floppy-disk"
        close-id="closeEditPmsModal"
        cancel-id="cancelEditPmsModal"
        close-data-attribute="data-close-edit-pms"
        :confirm="true"
        confirm-title="Update PMS Task?"
        confirm-message="Are you sure you want to update this PMS task?"
        confirm-button="Yes, Update PMS Task"
        confirm-type="update"
        class="pms-form-modal-overlay pms-edit-modal-overlay"
    >
        <div class="pms-form-layout">

            <section class="pms-form-section pms-section-blue">
                <div class="pms-form-section-header">
                    <span class="pms-form-section-icon">
                        <i class="fa-solid fa-bus"></i>
                    </span>

                    <div>
                        <h3>Vehicle &amp; Task Information</h3>
                        <p>Review the vehicle and update the PMS task type.</p>
                    </div>
                </div>

                <div class="pms-form-section-body">
                    <div class="pms-form-grid-2">
                        <x-ui.form-field
                            label="Vehicle ID / Bus No."
                            name="bus_no"
                            id="editPmsBusNo"
                            icon="fa-bus"
                            readonly
                            required
                        />

                        <x-ui.form-select
                            label="PMS Type"
                            name="maintenance_type_option"
                            id="editPmsMaintenanceType"
                            icon="fa-screwdriver-wrench"
                            :options="[
                                'Change Oil' => 'Change Oil',
                                'Oil Filter' => 'Oil Filter',
                                'Brake Check' => 'Brake Check',
                                'Air Filter' => 'Air Filter',
                                'Full PMS' => 'Full PMS',
                                'Other' => 'Other',
                            ]"
                            required
                        />
                    </div>

                    <div
                        class="ui-form-group pms-edit-other-type"
                        id="editCustomMaintenanceTypeGroup"
                        hidden
                    >
                        <label for="editCustomMaintenanceType">
                            Other PMS Type
                        </label>

                        <div class="ui-input-wrap has-icon">
                            <span class="ui-input-icon">
                                <i class="fa-solid fa-pen"></i>
                            </span>

                            <input
                                type="text"
                                id="editCustomMaintenanceType"
                                name="custom_maintenance_type"
                                maxlength="255"
                                placeholder="Enter custom PMS type"
                            >
                        </div>
                    </div>

                    <input
                        type="hidden"
                        id="editFinalMaintenanceType"
                        name="maintenance_type"
                    >
                </div>
            </section>

            <section class="pms-form-section pms-section-blue pms-edit-mileage-section">
                <div class="pms-form-section-header">
                    <span class="pms-form-section-icon">
                        <i class="fa-solid fa-gauge-high"></i>
                    </span>

                    <div>
                        <h3>Mileage Information</h3>
                        <p>Update the completed mileage and interval, then review the next PMS target.</p>
                    </div>
                </div>

                <div class="pms-form-section-body">
                    <div class="pms-form-grid-2">
                        <x-ui.form-field
                            label="Last PMS KM"
                            name="last_pms_km"
                            id="editLastPmsKm"
                            type="number"
                            icon="fa-gauge"
                            min="0"
                            step="0.01"
                            required
                        />

                        <x-ui.form-field
                            label="PMS Interval KM"
                            name="pms_interval_km"
                            id="editPmsIntervalKm"
                            type="number"
                            icon="fa-road"
                            min="1"
                            step="0.01"
                            required
                        />

                        <div class="pms-edit-next-km-card">
                            <span class="pms-edit-next-km-icon">
                                <i class="fa-solid fa-flag"></i>
                            </span>

                            <div>
                                <span class="pms-edit-next-km-label">Next PMS KM</span>

                                <x-ui.form-field
                                    label=""
                                    name="display_edit_next_pms_km"
                                    id="editNextPmsKm"
                                    icon="fa-forward"
                                    placeholder="Automatic"
                                    readonly
                                />
                            </div>
                        </div>

                        <x-ui.form-field
                            label="Recommended Date"
                            name="recommended_date"
                            id="editRecommendedDate"
                            type="date"
                            icon="fa-calendar-check"
                        />
                    </div>
                </div>
            </section>
        </div>
    </x-ui.form-modal>

</x-layout.app>
