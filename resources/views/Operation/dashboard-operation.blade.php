<x-layout.app
    title="FROMS - Operation Dashboard"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Operation/dashboard-operation.css',
        'resources/js/Main-js/sidebar.js',
    ]"
>

    <div class="app operation-dashboard-page">

        {{-- =====================================================
            SIDEBAR
        ====================================================== --}}
        <x-layout.sidebar
            department="Operation"
            subtitle="Operation Module"
            icon="fa-bus"
            :items="[
                [
                    'label' => 'Dashboard',
                    'route' => 'dashboard-operation',
                    'icon' => 'fa-table-cells-large'
                ],

                [
                    'label' => 'Routes',
                    'route' => 'operation.routes',
                    'icon' => 'fa-route'
                ],

                [
                    'label' => 'Scheduling',
                    'icon' => 'fa-calendar-days',
                    'children' => [
                        [
                            'label' => 'Trip Schedule',
                            'route' => 'trip-schedule',
                            'icon' => 'fa-calendar-days'
                        ],
                        [
                            'label' => 'Driver & Bus Assignment',
                            'route' => 'driver-bus-assignment',
                            'icon' => 'fa-user-tie'
                        ],
                        [
                            'label' => 'Auto Scheduling',
                            'route' => 'auto-scheduling',
                            'icon' => 'fa-wand-magic-sparkles'
                        ],
                    ]
                ],

                [
                    'label' => 'Attendance',
                    'icon' => 'fa-calendar-check',
                    'children' => [
                        [
                            'label' => 'Driver Attendance',
                            'route' => 'driver-attendance',
                            'icon' => 'fa-id-card'
                        ],
                        [
                            'label' => 'Mechanic Attendance',
                            'route' => 'mechanic-attendance',
                            'icon' => 'fa-users-gear'
                        ],
                    ]
                ],

                [
                    'label' => 'Bus Master List',
                    'route' => 'bus-master-list',
                    'icon' => 'fa-bus'
                ],
            ]"
        />


        <main class="main">

            {{-- =====================================================
                TOPBAR
            ====================================================== --}}
            <x-layout.topbar
                title="Operation Dashboard"
                subtitle="Monitor daily trips, fleet availability, attendance, and dispatch activity"
                notification-count="6"
            />


            {{-- =====================================================
                SUMMARY CARDS
            ====================================================== --}}
            <section class="stats-grid operation-stats">

                <x-ui.summary-card
                    label="Total Buses"
                    value="15"
                    small="Registered fleet"
                    icon="fa-bus"
                    color="blue"
                />

                <x-ui.summary-card
                    label="Available Buses"
                    value="11"
                    small="Ready for dispatch"
                    icon="fa-circle-check"
                    color="green"
                />

                <x-ui.summary-card
                    label="Trips Today"
                    value="8"
                    small="Scheduled trips"
                    icon="fa-route"
                    color="yellow"
                />

                <x-ui.summary-card
                    label="Drivers On Duty"
                    value="7"
                    small="Currently assigned"
                    icon="fa-id-card"
                    color="red"
                />

            </section>


            {{-- =====================================================
                MAIN DASHBOARD GRID
            ====================================================== --}}
            <section class="operation-dashboard-grid">

                {{-- =================================================
                    TODAY'S TRIPS
                ================================================== --}}
                <div class="dashboard-card trips-card">

                    <div class="dashboard-card-header">

                        <div>
                            <span class="section-eyebrow">
                                TODAY'S OPERATIONS
                            </span>

                            <h2>
                                Trip Overview
                            </h2>

                            <p>
                                Current shuttle schedules and dispatch status.
                            </p>
                        </div>

                        <a
                            href="{{ route('trip-schedule') }}"
                            class="view-all-link"
                        >
                            View Schedule

                            <i class="fa-solid fa-arrow-right"></i>
                        </a>

                    </div>


                    <div class="trip-list">

                        <div class="trip-item">

                            <div class="trip-icon active">
                                <i class="fa-solid fa-bus"></i>
                            </div>

                            <div class="trip-details">

                                <div class="trip-title-row">
                                    <h3>TRIP-001</h3>

                                    <span class="trip-status dispatched">
                                        Dispatched
                                    </span>
                                </div>

                                <p>
                                    Batangas City → Lipa
                                </p>

                                <div class="trip-meta">

                                    <span>
                                        <i class="fa-regular fa-clock"></i>
                                        06:30 AM
                                    </span>

                                    <span>
                                        <i class="fa-solid fa-bus-simple"></i>
                                        BUS-001
                                    </span>

                                    <span>
                                        <i class="fa-solid fa-user"></i>
                                        Rowell Amano
                                    </span>

                                </div>

                            </div>

                        </div>


                        <div class="trip-item">

                            <div class="trip-icon scheduled">
                                <i class="fa-solid fa-route"></i>
                            </div>

                            <div class="trip-details">

                                <div class="trip-title-row">
                                    <h3>TRIP-002</h3>

                                    <span class="trip-status scheduled">
                                        Scheduled
                                    </span>
                                </div>

                                <p>
                                    Malvar → Tanauan
                                </p>

                                <div class="trip-meta">

                                    <span>
                                        <i class="fa-regular fa-clock"></i>
                                        08:00 AM
                                    </span>

                                    <span>
                                        <i class="fa-solid fa-bus-simple"></i>
                                        BUS-003
                                    </span>

                                    <span>
                                        <i class="fa-solid fa-user"></i>
                                        Cardo Mendoza
                                    </span>

                                </div>

                            </div>

                        </div>


                        <div class="trip-item">

                            <div class="trip-icon pending">
                                <i class="fa-solid fa-hourglass-half"></i>
                            </div>

                            <div class="trip-details">

                                <div class="trip-title-row">
                                    <h3>TRIP-003</h3>

                                    <span class="trip-status pending">
                                        Pending
                                    </span>
                                </div>

                                <p>
                                    Lipa → Calamba
                                </p>

                                <div class="trip-meta">

                                    <span>
                                        <i class="fa-regular fa-clock"></i>
                                        10:30 AM
                                    </span>

                                    <span>
                                        <i class="fa-solid fa-bus-simple"></i>
                                        BUS-005
                                    </span>

                                    <span>
                                        <i class="fa-solid fa-user"></i>
                                        Unassigned
                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                {{-- =================================================
                    FLEET STATUS
                ================================================== --}}
                <div class="dashboard-card fleet-card">

                    <div class="dashboard-card-header">

                        <div>
                            <span class="section-eyebrow">
                                FLEET STATUS
                            </span>

                            <h2>
                                Bus Availability
                            </h2>

                            <p>
                                Current operational fleet condition.
                            </p>
                        </div>

                        <a
                            href="{{ route('bus-master-list') }}"
                            class="view-all-link"
                        >
                            View Fleet

                            <i class="fa-solid fa-arrow-right"></i>
                        </a>

                    </div>


                    <div class="fleet-status-list">

                        <div class="fleet-status-row">

                            <div class="fleet-status-info">

                                <span class="fleet-status-icon active">
                                    <i class="fa-solid fa-circle-check"></i>
                                </span>

                                <div>
                                    <h3>Active</h3>
                                    <p>Ready for operations</p>
                                </div>

                            </div>

                            <strong>
                                11
                            </strong>

                        </div>


                        <div class="fleet-status-row">

                            <div class="fleet-status-info">

                                <span class="fleet-status-icon maintenance">
                                    <i class="fa-solid fa-screwdriver-wrench"></i>
                                </span>

                                <div>
                                    <h3>Under Maintenance</h3>
                                    <p>Temporarily unavailable</p>
                                </div>

                            </div>

                            <strong>
                                2
                            </strong>

                        </div>


                        <div class="fleet-status-row">

                            <div class="fleet-status-info">

                                <span class="fleet-status-icon inactive">
                                    <i class="fa-solid fa-circle-xmark"></i>
                                </span>

                                <div>
                                    <h3>Inactive</h3>
                                    <p>Not assigned to operations</p>
                                </div>

                            </div>

                            <strong>
                                2
                            </strong>

                        </div>

                    </div>

                </div>

            </section>


            {{-- =====================================================
                SECOND ROW
            ====================================================== --}}
            <section class="operation-bottom-grid">

                {{-- =================================================
                    DRIVER ATTENDANCE
                ================================================== --}}
                <div class="dashboard-card">

                    <div class="dashboard-card-header">

                        <div>
                            <span class="section-eyebrow">
                                ATTENDANCE
                            </span>

                            <h2>
                                Driver Availability
                            </h2>

                            <p>
                                Today's driver attendance overview.
                            </p>
                        </div>

                        <a
                            href="{{ route('driver-attendance') }}"
                            class="view-all-link"
                        >
                            View Attendance

                            <i class="fa-solid fa-arrow-right"></i>
                        </a>

                    </div>


                    <div class="mini-stat-grid">

                        <div class="mini-stat">

                            <div class="mini-stat-icon green">
                                <i class="fa-solid fa-user-check"></i>
                            </div>

                            <div>
                                <span>Present</span>
                                <strong>7</strong>
                            </div>

                        </div>


                        <div class="mini-stat">

                            <div class="mini-stat-icon yellow">
                                <i class="fa-solid fa-clock"></i>
                            </div>

                            <div>
                                <span>Late</span>
                                <strong>1</strong>
                            </div>

                        </div>


                        <div class="mini-stat">

                            <div class="mini-stat-icon red">
                                <i class="fa-solid fa-user-xmark"></i>
                            </div>

                            <div>
                                <span>Absent</span>
                                <strong>1</strong>
                            </div>

                        </div>


                        <div class="mini-stat">

                            <div class="mini-stat-icon purple">
                                <i class="fa-solid fa-calendar-minus"></i>
                            </div>

                            <div>
                                <span>On Leave</span>
                                <strong>1</strong>
                            </div>

                        </div>

                    </div>

                </div>


                {{-- =================================================
                    MECHANIC AVAILABILITY
                ================================================== --}}
                <div class="dashboard-card">

                    <div class="dashboard-card-header">

                        <div>
                            <span class="section-eyebrow">
                                MAINTENANCE SUPPORT
                            </span>

                            <h2>
                                Mechanic Availability
                            </h2>

                            <p>
                                Current mechanic attendance and assignment.
                            </p>
                        </div>

                        <a
                            href="{{ route('mechanic-attendance') }}"
                            class="view-all-link"
                        >
                            View Mechanics

                            <i class="fa-solid fa-arrow-right"></i>
                        </a>

                    </div>


                    <div class="mechanic-summary">

                        <div class="mechanic-summary-main">

                            <div class="mechanic-big-icon">
                                <i class="fa-solid fa-users-gear"></i>
                            </div>

                            <div>
                                <span>
                                    Available Mechanics
                                </span>

                                <strong>
                                    4
                                </strong>

                                <p>
                                    Ready for job assignment
                                </p>
                            </div>

                        </div>


                        <div class="mechanic-status-row">

                            <span>
                                On Duty
                            </span>

                            <strong>
                                5
                            </strong>

                        </div>


                        <div class="mechanic-status-row">

                            <span>
                                On Leave
                            </span>

                            <strong>
                                1
                            </strong>

                        </div>

                    </div>

                </div>


                {{-- =================================================
                    QUICK ACTIONS
                ================================================== --}}
                <div class="dashboard-card quick-actions-card">

                    <div class="dashboard-card-header">

                        <div>
                            <span class="section-eyebrow">
                                QUICK ACCESS
                            </span>

                            <h2>
                                Operation Actions
                            </h2>

                            <p>
                                Common daily operation tasks.
                            </p>
                        </div>

                    </div>


                    <div class="quick-actions">

                        <a
                            href="{{ route('trip-schedule') }}"
                            class="quick-action-item"
                        >

                            <span class="quick-action-icon blue">
                                <i class="fa-solid fa-calendar-days"></i>
                            </span>

                            <div>
                                <h3>Trip Schedule</h3>
                                <p>Review scheduled trips</p>
                            </div>

                            <i class="fa-solid fa-chevron-right"></i>

                        </a>


                        <a
                            href="{{ route('driver-bus-assignment') }}"
                            class="quick-action-item"
                        >

                            <span class="quick-action-icon yellow">
                                <i class="fa-solid fa-user-tie"></i>
                            </span>

                            <div>
                                <h3>Driver Assignment</h3>
                                <p>Assign buses and drivers</p>
                            </div>

                            <i class="fa-solid fa-chevron-right"></i>

                        </a>


                        <a
                            href="{{ route('bus-master-list') }}"
                            class="quick-action-item"
                        >

                            <span class="quick-action-icon green">
                                <i class="fa-solid fa-bus"></i>
                            </span>

                            <div>
                                <h3>Bus Master List</h3>
                                <p>Manage fleet records</p>
                            </div>

                            <i class="fa-solid fa-chevron-right"></i>

                        </a>

                    </div>

                </div>

            </section>

        </main>

    </div>

</x-layout.app>