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
                subtitle="Monitor preventive maintenance tasks based on processed GPS vehicle mileage data."
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
                    small="Scheduled maintenance tasks"
                    icon="fa-calendar-check"
                    color="green"
                />

                <x-ui.summary-card
                    label="Due Soon"
                    value="{{ $dueSoonCount }}"
                    small="Within 500 KM of PMS"
                    icon="fa-clock"
                    color="yellow"
                />

                <x-ui.summary-card
                    label="Overdue"
                    value="{{ $overdueCount }}"
                    small="Needs immediate action"
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
                            Automated PMS Record
                        </h2>

                        <p>
                            Monitor each bus using its available PMS mileage thresholds and GPS records.
                        </p>

                    </div>


                    <button
                        type="button"
                        class="pms-add-btn"
                        data-open-add-pms
                    >
                        <i class="fa-solid fa-plus"></i>

                        Add PMS Task
                    </button>

                </div>


                {{-- =================================================
                    TOOLBAR
                ================================================== --}}
                <x-ui.table-toolbar action="{{ route('PMS-Scheduling') }}" search-label="Search PMS Records" search-placeholder="Search vehicle, PMS task, or status..." :filter-count="(int) (request()->filled('status') && request('status') !== 'All Status')" filter-title="Filter PMS Records" :sort-options="['bus_no' => 'Vehicle ID', 'current_km' => 'Current KM', 'next_pms_km' => 'Next PMS KM', 'next_pms_date' => 'Next PMS Date', 'overall_status' => 'PMS Status']" default-sort="bus_no">
                    <x-slot:filters><x-ui.table-toolbar-field label="Status" name="status" default="All Status"><option value="All Status">All Statuses</option>@foreach(['Upcoming','Due Soon','Overdue'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</x-ui.table-toolbar-field></x-slot:filters>
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
                                <th>Current KM</th>
                                <th>Next PMS KM</th>
                                <th>Next PMS Date</th>
                                <th class="status-col">PMS Status</th>
                            </tr>

                        </thead>


                        <tbody>

                            @forelse($rows as $row)

                                <tr>

                                    <td>
                                        <span class="bus-badge-pill">
                                            <i class="fa-solid fa-bus"></i> {{ $row->bus_no }}
                                        </span>
                                    </td>


                                    <td><span title="Verified last PMS completion date is not yet recorded">—</span></td>


                                    <td>
                                        {{ $row->current_km !== null
                                            ? number_format($row->current_km, 2) . ' km'
                                            : '—'
                                        }}
                                    </td>


                                    <td>{{ $row->next_pms_km !== null ? number_format($row->next_pms_km, 2) . ' km' : '—' }}</td>

                                    <td>
                                        @if($row->next_pms_date)
                                            <span title="Estimated from GPS mileage; not a configured calendar-based PMS deadline">{{ \Carbon\Carbon::parse($row->next_pms_date)->format('M d, Y') }} <small>(Est.)</small></span>
                                        @else
                                            <span title="No estimated date available">—</span>
                                        @endif
                                    </td>


                                    <td class="status-col">

                                        @if(!$row->has_processed_gps)
                                            <span class="pms-data-needed-badge" title="No processed GPS mileage available; PMS due status cannot be verified"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Data Needed</span>
                                        @else
                                            <x-ui.status-badge :status="$row->overall_status" />
                                        @endif

                                    </td>




                                </tr>


                            @empty

                                <x-ui.empty-row
                                    colspan="6"
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
