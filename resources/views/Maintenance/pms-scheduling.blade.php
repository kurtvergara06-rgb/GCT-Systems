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
        <x-layout.sidebar
            department="Maintenance"
            subtitle="Department Module"
            icon="fa-truck"
            :items="[
                [
                    'label' => 'Dashboard',
                    'route' => 'maintenance-dashboard',
                    'icon' => 'fa-table-cells-large'
                ],
                [
                    'label' => 'Job Orders',
                    'route' => 'job-orders',
                    'icon' => 'fa-clipboard-list'
                ],
                [
                    'label' => 'Mechanic List',
                    'route' => 'mechanic-list',
                    'icon' => 'fa-bus'
                ],
                [
                    'label' => 'PMS Scheduling',
                    'route' => 'PMS-Scheduling',
                    'icon' => 'fa-calendar-check'
                ],
                [
                    'label' => 'Purchase Requests',
                    'route' => 'purchase-requests',
                    'icon' => 'fa-file-invoice'
                ],
                [
                    'label' => 'Fuel Reports',
                    'route' => 'fuel-reports',
                    'icon' => 'fa-gas-pump'
                ],
            ]"
        />


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
            <section class="stats-grid pms-stats-grid">

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
            <section class="table-card pms-card pms-table-card">

                <div class="section-header pms-header">

                    <div>

                        <h2>
                            Automated PMS Record
                        </h2>

                        <p>
                            One bus row can contain multiple PMS tasks.
                            Click the list icon to view the tasks.
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
                <form
                    method="GET"
                    action="{{ route('PMS-Scheduling') }}"
                >

                    <div class="toolbar pms-toolbar">

                        <div class="search-box">

                            <i class="fa-solid fa-magnifying-glass"></i>

                            <input
                                type="text"
                                name="search"
                                value="{{ request('search') }}"
                                placeholder="Search vehicle, PMS task, or status..."
                            >

                        </div>


                        <div class="filter-group">

                            <label for="pmsStatusFilter">
                            </label>

                            <select
                                name="status"
                                id="pmsStatusFilter"
                                onchange="this.form.submit()"
                            >

                                <option value="All Status">
                                    All Status
                                </option>

                                <option
                                    value="Upcoming"
                                    @selected(request('status') === 'Upcoming')
                                >
                                    Upcoming
                                </option>

                                <option
                                    value="Due Soon"
                                    @selected(request('status') === 'Due Soon')
                                >
                                    Due Soon
                                </option>

                                <option
                                    value="Overdue"
                                    @selected(request('status') === 'Overdue')
                                >
                                    Overdue
                                </option>

                            </select>

                        </div>

                    </div>

                </form>


                {{-- =================================================
                    MAIN TABLE
                ================================================== --}}
                <div class="table-wrap">

                    <table class="pms-table">

                        <thead>

                            <tr>
                                <th>Vehicle ID</th>
                                <th>GPS Report Date</th>
                                <th>Current KM</th>
                                <th>KM Traveled</th>
                                <th>Due PMS</th>
                                <th class="status-col">Overall Status</th>
                                <th>Action</th>
                            </tr>

                        </thead>


                        <tbody>

                            @forelse($rows as $row)

                                @php
                                    $modalId =
                                        'pmsTasksModal-' .
                                        $loop->iteration .
                                        '-' .
                                        preg_replace(
                                            '/[^A-Za-z0-9]/',
                                            '-',
                                            $row->bus_no
                                        );
                                @endphp


                                <tr>

                                    <td>
                                        {{ $row->bus_no }}
                                    </td>


                                    <td>
                                        {{ $row->gps_report_date
                                            ? \Carbon\Carbon::parse($row->gps_report_date)->format('M d, Y')
                                            : 'No processed GPS report'
                                        }}
                                    </td>


                                    <td>
                                        {{ $row->current_km !== null
                                            ? number_format($row->current_km, 2) . ' km'
                                            : '—'
                                        }}
                                    </td>


                                    <td>
                                        {{ $row->km_traveled !== null
                                            ? number_format($row->km_traveled, 2) . ' km'
                                            : '—'
                                        }}
                                    </td>


                                    <td>
                                        {{ $row->due_pms_count }}

                                        task{{ $row->due_pms_count === 1 ? '' : 's' }}
                                    </td>


                                    <td class="status-col">

                                        <span
                                            class="badge {{ strtolower(str_replace(' ', '-', $row->overall_status)) }}"
                                        >
                                            {{ $row->overall_status }}
                                        </span>

                                    </td>


                                    <td>

                                        <button
                                            type="button"
                                            class="pms-view-tasks-btn open-pms-tasks-modal"
                                            data-modal-target="{{ $modalId }}"
                                            title="View PMS Tasks"
                                        >
                                            <i class="fa-solid fa-list-check"></i>
                                        </button>

                                    </td>

                                </tr>


                            @empty

                                <x-ui.empty-row
                                    colspan="7"
                                    message="No PMS schedule records found. Add a bus PMS task first."
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
        VIEW PMS TASKS
        VIEW ONLY - NO UPDATE / CANCEL
    ========================================================== --}}
    @foreach($rows as $row)

        @php
            $modalId =
                'pmsTasksModal-' .
                $loop->iteration .
                '-' .
                preg_replace(
                    '/[^A-Za-z0-9]/',
                    '-',
                    $row->bus_no
                );
        @endphp


        <div
            class="pms-modal-overlay pms-tasks-popup"
            id="{{ $modalId }}"
        >

            <div class="pms-modal pms-wide-modal">

                <div class="pms-modal-header">

                    <div>

                        <h2>
                            PMS Tasks - {{ $row->bus_no }}
                        </h2>

                        <p>
                            Current KM:

                            {{ $row->current_km !== null
                                ? number_format($row->current_km, 2) . ' km'
                                : 'No processed GPS KM'
                            }}

                            • Overall Status:
                            {{ $row->overall_status }}
                        </p>

                    </div>


                    <button
                        type="button"
                        class="pms-close-btn close-pms-tasks-modal"
                        data-modal-target="{{ $modalId }}"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>

                </div>


                <div class="table-wrap pms-popup-table-wrap">

                    <table class="pms-table">

                        <thead>

                            <tr>
                                <th>PMS Type</th>
                                <th>Last PMS KM</th>
                                <th>Interval</th>
                                <th>Next PMS KM</th>
                                <th>Recommended Date</th>
                                <th class="status-col">Status</th>
                                <th>Action</th>
                            </tr>

                        </thead>


                        <tbody>

                            @forelse($row->tasks as $task)

                                <tr>

                                    <td>
                                        {{ $task->maintenance_type }}
                                    </td>


                                    <td>
                                        {{ number_format($task->last_pms_km, 2) }} km
                                    </td>


                                    <td>
                                        {{ number_format($task->pms_interval_km, 2) }} km
                                    </td>


                                    <td>
                                        {{ number_format($task->next_pms_km, 2) }} km
                                    </td>


                                    <td>
                                        {{ $task->status === 'Overdue'
                                            ? 'Immediate'
                                            : (
                                                $task->recommended_date
                                                    ? \Carbon\Carbon::parse(
                                                        $task->recommended_date
                                                    )->format('M d, Y')
                                                    : '—'
                                            )
                                        }}
                                    </td>


                                    <td class="status-col">

                                        <span
                                            class="badge {{ strtolower(str_replace(' ', '-', $task->status)) }}"
                                        >
                                            {{ $task->status }}
                                        </span>

                                    </td>


                                    <td>

                                        <div class="actions">

                                            {{-- CREATE JO --}}
                                            @if(in_array(
                                                $task->status,
                                                ['Due Soon', 'Overdue']
                                            ))

                                                <a
                                                    href="{{ route(
                                                        'pms-schedules.create-job-order',
                                                        $task->schedule
                                                    ) }}"
                                                    class="create-pms-jo-btn"
                                                    title="Create PMS Job Order"

                                                    data-confirm-action
                                                    data-confirm-title="Create PMS Job Order?"
                                                    data-confirm-message="Are you sure you want to create a Job Order from this PMS task?"
                                                    data-confirm-button="Yes, Create Job Order"
                                                    data-confirm-type="create"
                                                >
                                                    <i class="fa-solid fa-plus"></i>
                                                </a>

                                            @else

                                                <span
                                                    class="pms-action-placeholder"
                                                    title="Job Order available when Due Soon or Overdue"
                                                >
                                                    —
                                                </span>

                                            @endif


                                            {{-- EDIT --}}
                                            <button
                                                type="button"
                                                class="pms-edit-task-btn open-edit-pms"
                                                title="Edit PMS Task"

                                                data-update-url="{{ route('pms-schedules.update', $task->schedule, false) }}"

                                                data-bus-no="{{ $task->schedule->bus_no }}"

                                                data-maintenance-type="{{ $task->schedule->maintenance_type }}"

                                                data-last-pms-km="{{ $task->schedule->last_pms_km }}"

                                                data-pms-interval-km="{{ $task->schedule->pms_interval_km }}"

                                                data-current-km="{{ $row->current_km }}"

                                                data-gps-date="{{ $row->gps_report_date
                                                    ? \Carbon\Carbon::parse(
                                                        $row->gps_report_date
                                                    )->format('M d, Y')
                                                    : ''
                                                }}"

                                                data-gps-date-iso="{{ $row->gps_report_date
                                                    ? \Carbon\Carbon::parse(
                                                        $row->gps_report_date
                                                    )->format('Y-m-d')
                                                    : ''
                                                }}"

                                                data-recommended-date="{{ $task->schedule->recommended_date
                                                    ? \Carbon\Carbon::parse(
                                                        $task->schedule->recommended_date
                                                    )->format('Y-m-d')
                                                    : ''
                                                }}"
                                            >
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>


                                            {{-- DELETE --}}
                                            <form
                                                action="{{ route(
                                                    'pms-schedules.destroy',
                                                    $task->schedule
                                                ) }}"
                                                method="POST"

                                                data-confirm-form
                                                data-confirm-title="Delete PMS Task?"
                                                data-confirm-message="Are you sure you want to delete this PMS task? This action cannot be undone."
                                                data-confirm-button="Yes, Delete"
                                                data-confirm-type="delete"
                                            >

                                                @csrf
                                                @method('DELETE')


                                                <button
                                                    type="submit"
                                                    class="delete"
                                                    title="Delete PMS Task"
                                                >
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>


                            @empty

                                <x-ui.empty-row
                                    colspan="7"
                                    message="No PMS tasks found for this bus."
                                />

                            @endforelse

                        </tbody>

                    </table>

                </div>


                {{-- VIEW MODE HAS ONLY CLOSE --}}
                <div class="pms-view-footer">

                    <button
                        type="button"
                        class="ui-form-btn ui-form-btn-cancel close-pms-tasks-modal"
                        data-modal-target="{{ $modalId }}"
                    >
                        Close
                    </button>

                </div>

            </div>

        </div>

    @endforeach


    {{-- =========================================================
        ADD PMS
        GLOBAL FORM COMPONENT
    ========================================================== --}}
    <x-ui.form-modal
        id="addPmsModal"

        title="Add PMS Task"
        description="Select a bus with processed GPS mileage data."

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
    >

        <div class="ui-form-grid">

            {{-- BUS --}}
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
                                    ? \Carbon\Carbon::parse(
                                        $bus->gps_report_date
                                    )->format('M d, Y')
                                    : ''
                                }}"

                                data-gps-date-iso="{{ $bus->gps_report_date
                                    ? \Carbon\Carbon::parse(
                                        $bus->gps_report_date
                                    )->format('Y-m-d')
                                    : ''
                                }}"
                            >
                                {{ $bus->bus_no }}
                            </option>

                        @endforeach

                    </select>

                </div>

            </div>


            {{-- CURRENT KM --}}
            <x-ui.form-field
                label="Current GPS KM"
                name="display_current_gps_km"
                id="currentGpsKm"

                icon="fa-gauge-high"

                placeholder="Select a bus first"

                readonly
            />


            {{-- GPS DATE --}}
            <x-ui.form-field
                label="GPS Report Date"
                name="display_gps_report_date"
                id="gpsReportDate"

                icon="fa-calendar-day"

                placeholder="Select a bus first"

                readonly
            />


            {{-- LAST PMS KM --}}
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


            {{-- INTERVAL --}}
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


            {{-- NEXT PMS --}}
            <x-ui.form-field
                label="Next PMS KM"
                name="display_next_pms_km"
                id="nextPmsKm"

                icon="fa-forward"

                placeholder="Automatic"

                readonly
            />


            {{-- STATUS --}}
            <x-ui.form-field
                label="Predicted Status"
                name="display_predicted_status"
                id="pmsStatusPreview"

                icon="fa-chart-line"

                placeholder="Automatic"

                readonly
            />


            {{-- PMS TYPE --}}
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


            {{-- OTHER PMS --}}
            <div
                class="ui-form-group ui-form-full"
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


            {{-- RECOMMENDED DATE --}}
            <x-ui.form-field
                label="Recommended Date"
                name="recommended_date"
                id="recommendedDate"

                type="date"

                icon="fa-calendar-check"

                full
            />

        </div>

    </x-ui.form-modal>


    {{-- =========================================================
        EDIT PMS
        GLOBAL FORM COMPONENT
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
    >

        <div class="ui-form-grid">

            {{-- BUS --}}
            <x-ui.form-field
                label="Vehicle ID / Bus No."
                name="bus_no"
                id="editPmsBusNo"

                icon="fa-bus"

                readonly
                required
            />


            {{-- PMS TYPE --}}
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


            {{-- OTHER TYPE --}}
            <div
                class="ui-form-group ui-form-full"
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


            {{-- LAST PMS --}}
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


            {{-- INTERVAL --}}
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


            {{-- NEXT PMS --}}
            <x-ui.form-field
                label="Next PMS KM"
                name="display_edit_next_pms_km"
                id="editNextPmsKm"

                icon="fa-forward"

                placeholder="Automatic"

                readonly
            />


            {{-- RECOMMENDED --}}
            <x-ui.form-field
                label="Recommended Date"
                name="recommended_date"
                id="editRecommendedDate"

                type="date"

                icon="fa-calendar-check"
            />

        </div>

    </x-ui.form-modal>

</x-layout.app>