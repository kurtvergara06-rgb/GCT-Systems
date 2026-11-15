<x-layout.app
    title="FROMS - Bus Assignment"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Operation/Shuttle_Bus_Management/bus-assignment.css',
        'resources/js/Main-js/sidebar.js'
    ]"
>
    <div class="app">

        <x-layout.sidebar
            department="Operation"
            subtitle="Department Module"
            icon="fa-clipboard-check"
            :items="[
                [
                    'label' => 'Dashboard',
                    'route' => 'dashboard-operation',
                    'icon' => 'fa-table-cells-large'
                ],

                [
                    'label' => 'Shuttle Bus Management',
                    'icon' => 'fa-bus',
                    'children' => [
                        [
                            'label' => 'Bus Master List',
                            'route' => 'bus-master-list',
                            'icon' => 'fa-list'
                        ],
                        [
                            'label' => 'Bus Assignment',
                            'route' => 'bus-assignment',
                            'icon' => 'fa-bus-simple'
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
                            'icon' => 'fa-screwdriver-wrench'
                        ],
                    ]
                ],

                [
                    'label' => 'Routes & Stops',
                    'route' => 'operation.routes',
                    'icon' => 'fa-route'
                ],

                [
                    'label' => 'Scheduling & Dispatch',
                    'icon' => 'fa-calendar-days',
                    'children' => [
                        [
                            'label' => 'Trip Schedule',
                            'route' => 'trip-schedule',
                            'icon' => 'fa-calendar-day'
                        ],
                        [
                            'label' => 'Driver-Bus Assignment',
                            'route' => 'driver-bus-assignment',
                            'icon' => 'fa-user-plus'
                        ],
                        [
                            'label' => 'Auto Dispatch',
                            'route' => 'auto-scheduling',
                            'icon' => 'fa-wand-magic-sparkles'
                        ],
                    ]
                ],
            ]"
        />

        <main class="main bus-assignment-page">

            <x-layout.topbar
                title="Bus Assignment"
                subtitle="Assign available shuttle buses to operational routes and scheduled trips"
                notification-count="4"
            />


            {{-- =====================================================
                ASSIGNMENT OVERVIEW
            ====================================================== --}}
            <section class="assignment-overview">

                <div class="overview-main">

                    <span class="overview-kicker">
                        <i class="fa-solid fa-bus"></i>
                        Shuttle Assignment Control
                    </span>

                    <h2>
                        Manage which buses are assigned to active routes and trip schedules.
                    </h2>

                    <p>
                        Review available buses, maintenance status, current assignments,
                        and route requirements before assigning a shuttle unit.
                    </p>

                </div>


                <div class="overview-status">

                    <div class="status-number">
                        <strong>18</strong>
                        <span>Available Buses</span>
                    </div>

                    <div class="status-divider"></div>

                    <div class="status-number">
                        <strong>12</strong>
                        <span>Assigned Today</span>
                    </div>

                </div>

            </section>


            {{-- =====================================================
                KPI STRIP
            ====================================================== --}}
            <section class="stats-grid assignment-summary-grid">

                <x-ui.summary-card
                    label="Total Buses"
                    value="22"
                    small="Registered shuttle units"
                    icon="fa-bus"
                    color="blue"
                />

                <x-ui.summary-card
                    label="Available"
                    value="18"
                    small="Ready for assignment"
                    icon="fa-circle-check"
                    color="green"
                />

                <x-ui.summary-card
                    label="Assigned Today"
                    value="12"
                    small="Currently scheduled"
                    icon="fa-route"
                    color="yellow"
                />

                <x-ui.summary-card
                    label="Unavailable"
                    value="4"
                    small="Maintenance or inactive"
                    icon="fa-triangle-exclamation"
                    color="red"
                />

            </section>


            {{-- =====================================================
                MAIN ASSIGNMENT WORKSPACE
            ====================================================== --}}
            <section class="assignment-workspace">

                {{-- =================================================
                    AVAILABLE BUS PANEL
                ================================================== --}}
                <article class="available-bus-panel">

                    <div class="panel-heading">

                        <div>
                            <span class="section-kicker">
                                Fleet Availability
                            </span>

                            <h2>
                                Available Buses
                            </h2>

                            <p>
                                Select an operational bus that is ready for assignment.
                            </p>
                        </div>

                        <span class="available-count">
                            18 Available
                        </span>

                    </div>


                    <div class="bus-search">

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            placeholder="Search bus number..."
                        >

                    </div>


                    <div class="available-bus-list">

                        {{-- BUS 003 --}}
                        <button
                            type="button"
                            class="available-bus-item active"
                        >

                            <div class="bus-item-icon">
                                <i class="fa-solid fa-bus"></i>
                            </div>

                            <div class="bus-item-info">

                                <strong>BUS-003</strong>

                                <span>
                                    41,280 km
                                </span>

                            </div>

                            <div class="bus-item-status">
                                <span>Available</span>
                                <small>Healthy</small>
                            </div>

                        </button>


                        {{-- BUS 018 --}}
                        <button
                            type="button"
                            class="available-bus-item"
                        >

                            <div class="bus-item-icon">
                                <i class="fa-solid fa-bus"></i>
                            </div>

                            <div class="bus-item-info">

                                <strong>BUS-018</strong>

                                <span>
                                    44,510 km
                                </span>

                            </div>

                            <div class="bus-item-status">
                                <span>Available</span>
                                <small>Healthy</small>
                            </div>

                        </button>


                        {{-- BUS 009 --}}
                        <button
                            type="button"
                            class="available-bus-item"
                        >

                            <div class="bus-item-icon">
                                <i class="fa-solid fa-bus"></i>
                            </div>

                            <div class="bus-item-info">

                                <strong>BUS-009</strong>

                                <span>
                                    38,920 km
                                </span>

                            </div>

                            <div class="bus-item-status">
                                <span>Available</span>
                                <small>Healthy</small>
                            </div>

                        </button>


                        {{-- BUS 006 --}}
                        <button
                            type="button"
                            class="available-bus-item warning"
                        >

                            <div class="bus-item-icon">
                                <i class="fa-solid fa-bus"></i>
                            </div>

                            <div class="bus-item-info">

                                <strong>BUS-006</strong>

                                <span>
                                    46,730 km
                                </span>

                            </div>

                            <div class="bus-item-status">
                                <span>Available</span>
                                <small>PMS Monitoring</small>
                            </div>

                        </button>

                    </div>


                    <div class="bus-list-footer">

                        <span>
                            Showing 4 of 18 available buses
                        </span>

                        <button type="button">
                            View All
                        </button>

                    </div>

                </article>


                {{-- =================================================
                    ASSIGNMENT FORM
                ================================================== --}}
                <article class="assignment-form-panel">

                    <div class="panel-heading">

                        <div>
                            <span class="section-kicker">
                                New Assignment
                            </span>

                            <h2>
                                Assign Shuttle Bus
                            </h2>

                            <p>
                                Complete the assignment details for the selected bus.
                            </p>
                        </div>

                        <div class="selected-bus-badge">
                            <i class="fa-solid fa-bus"></i>
                            BUS-003
                        </div>

                    </div>


                    <div class="selected-bus-preview">

                        <div class="preview-bus-icon">
                            <i class="fa-solid fa-bus-simple"></i>
                        </div>

                        <div class="preview-bus-main">

                            <span>Selected Shuttle</span>

                            <strong>BUS-003</strong>

                            <small>
                                Operational · 41,280 km accumulated mileage
                            </small>

                        </div>

                        <div class="preview-health">
                            <i class="fa-solid fa-circle-check"></i>

                            <div>
                                <span>Health Status</span>
                                <strong>Healthy</strong>
                            </div>
                        </div>

                    </div>


                    <form class="assignment-form">

                        <div class="assignment-form-grid">

                            <div class="form-group">

                                <label for="assignment_date">
                                    Assignment Date
                                </label>

                                <div class="input-with-icon">

                                    <i class="fa-solid fa-calendar"></i>

                                    <input
                                        type="date"
                                        id="assignment_date"
                                        value="2026-07-25"
                                    >

                                </div>

                            </div>


                            <div class="form-group">

                                <label for="shift">
                                    Shift
                                </label>

                                <select id="shift">
                                    <option selected>Morning Shift</option>
                                    <option>Afternoon Shift</option>
                                    <option>Evening Shift</option>
                                </select>

                            </div>


                            <div class="form-group form-group-wide">

                                <label for="route">
                                    Route
                                </label>

                                <div class="input-with-icon">

                                    <i class="fa-solid fa-route"></i>

                                    <select id="route">
                                        <option>Select route</option>
                                        <option selected>Malvar - Lipa</option>
                                        <option>Malvar - Tanauan</option>
                                        <option>Malvar - Sto. Tomas</option>
                                    </select>

                                </div>

                            </div>


                            <div class="form-group">

                                <label for="departure_time">
                                    Departure Time
                                </label>

                                <div class="input-with-icon">

                                    <i class="fa-solid fa-clock"></i>

                                    <input
                                        type="time"
                                        id="departure_time"
                                        value="07:00"
                                    >

                                </div>

                            </div>


                            <div class="form-group">

                                <label for="return_time">
                                    Expected Return
                                </label>

                                <div class="input-with-icon">

                                    <i class="fa-solid fa-clock-rotate-left"></i>

                                    <input
                                        type="time"
                                        id="return_time"
                                        value="09:30"
                                    >

                                </div>

                            </div>


                            <div class="form-group form-group-wide">

                                <label for="remarks">
                                    Assignment Remarks
                                </label>

                                <textarea
                                    id="remarks"
                                    rows="3"
                                    placeholder="Optional assignment note..."
                                ></textarea>

                            </div>

                        </div>


                        <div class="assignment-checks">

                            <div class="assignment-check good">

                                <i class="fa-solid fa-circle-check"></i>

                                <div>
                                    <strong>Bus is operational</strong>
                                    <span>No active maintenance restriction</span>
                                </div>

                            </div>


                            <div class="assignment-check good">

                                <i class="fa-solid fa-circle-check"></i>

                                <div>
                                    <strong>No schedule conflict</strong>
                                    <span>No overlapping assignment detected</span>
                                </div>

                            </div>


                            <div class="assignment-check info">

                                <i class="fa-solid fa-circle-info"></i>

                                <div>
                                    <strong>Driver assigned separately</strong>
                                    <span>Driver-bus assignment is managed under Scheduling & Dispatch</span>
                                </div>

                            </div>

                        </div>


                        <div class="assignment-form-actions">

                            <button
                                type="button"
                                class="clear-button"
                            >
                                <i class="fa-solid fa-rotate-left"></i>
                                Clear
                            </button>

                            <button
                                type="button"
                                class="assign-button"
                            >
                                <i class="fa-solid fa-bus"></i>
                                Assign Bus
                            </button>

                        </div>

                    </form>

                </article>

            </section>


            {{-- =====================================================
                CURRENT ASSIGNMENTS
            ====================================================== --}}
            <section class="current-assignment-section">

                <div class="current-assignment-heading">

                    <div>

                        <span class="section-kicker">
                            Today's Operations
                        </span>

                        <h2>
                            Current Bus Assignments
                        </h2>

                        <p>
                            Shuttle units assigned to routes and scheduled trips today.
                        </p>

                    </div>


                    <div class="assignment-filters">

                        <select>
                            <option>All Shifts</option>
                            <option>Morning Shift</option>
                            <option>Afternoon Shift</option>
                        </select>

                        <select>
                            <option>All Routes</option>
                            <option>Malvar - Lipa</option>
                            <option>Malvar - Tanauan</option>
                            <option>Malvar - Sto. Tomas</option>
                        </select>

                    </div>

                </div>


                <div class="assignment-table-card">

                    <div class="assignment-table-wrap">

                        <table class="assignment-table">

                            <thead>

                                <tr>
                                    <th>Bus</th>
                                    <th>Route</th>
                                    <th>Shift</th>
                                    <th>Departure</th>
                                    <th>Expected Return</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>

                            </thead>

                            <tbody>

                                <tr>

                                    <td>

                                        <div class="table-bus">

                                            <div>
                                                <i class="fa-solid fa-bus"></i>
                                            </div>

                                            <span>
                                                <strong>BUS-012</strong>
                                                <small>48,420 km</small>
                                            </span>

                                        </div>

                                    </td>

                                    <td>
                                        <strong class="route-name">
                                            Malvar - Lipa
                                        </strong>
                                    </td>

                                    <td>
                                        Morning Shift
                                    </td>

                                    <td>
                                        6:30 AM
                                    </td>

                                    <td>
                                        9:00 AM
                                    </td>

                                    <td>
                                        <span class="assignment-status ongoing">
                                            On Trip
                                        </span>
                                    </td>

                                    <td>

                                        <button
                                            type="button"
                                            class="table-action"
                                            title="View"
                                        >
                                            <i class="fa-solid fa-eye"></i>
                                        </button>

                                    </td>

                                </tr>


                                <tr>

                                    <td>

                                        <div class="table-bus">

                                            <div>
                                                <i class="fa-solid fa-bus"></i>
                                            </div>

                                            <span>
                                                <strong>BUS-007</strong>
                                                <small>47,980 km</small>
                                            </span>

                                        </div>

                                    </td>

                                    <td>
                                        <strong class="route-name">
                                            Malvar - Tanauan
                                        </strong>
                                    </td>

                                    <td>
                                        Morning Shift
                                    </td>

                                    <td>
                                        7:00 AM
                                    </td>

                                    <td>
                                        9:45 AM
                                    </td>

                                    <td>
                                        <span class="assignment-status assigned">
                                            Assigned
                                        </span>
                                    </td>

                                    <td>

                                        <button
                                            type="button"
                                            class="table-action"
                                            title="View"
                                        >
                                            <i class="fa-solid fa-eye"></i>
                                        </button>

                                    </td>

                                </tr>


                                <tr>

                                    <td>

                                        <div class="table-bus">

                                            <div>
                                                <i class="fa-solid fa-bus"></i>
                                            </div>

                                            <span>
                                                <strong>BUS-018</strong>
                                                <small>44,510 km</small>
                                            </span>

                                        </div>

                                    </td>

                                    <td>
                                        <strong class="route-name">
                                            Malvar - Sto. Tomas
                                        </strong>
                                    </td>

                                    <td>
                                        Morning Shift
                                    </td>

                                    <td>
                                        8:00 AM
                                    </td>

                                    <td>
                                        10:30 AM
                                    </td>

                                    <td>
                                        <span class="assignment-status assigned">
                                            Assigned
                                        </span>
                                    </td>

                                    <td>

                                        <button
                                            type="button"
                                            class="table-action"
                                            title="View"
                                        >
                                            <i class="fa-solid fa-eye"></i>
                                        </button>

                                    </td>

                                </tr>


                                <tr>

                                    <td>

                                        <div class="table-bus">

                                            <div>
                                                <i class="fa-solid fa-bus"></i>
                                            </div>

                                            <span>
                                                <strong>BUS-003</strong>
                                                <small>41,280 km</small>
                                            </span>

                                        </div>

                                    </td>

                                    <td>
                                        <strong class="route-name">
                                            Malvar - Lipa
                                        </strong>
                                    </td>

                                    <td>
                                        Afternoon Shift
                                    </td>

                                    <td>
                                        1:00 PM
                                    </td>

                                    <td>
                                        3:30 PM
                                    </td>

                                    <td>
                                        <span class="assignment-status scheduled">
                                            Scheduled
                                        </span>
                                    </td>

                                    <td>

                                        <button
                                            type="button"
                                            class="table-action"
                                            title="View"
                                        >
                                            <i class="fa-solid fa-eye"></i>
                                        </button>

                                    </td>

                                </tr>

                            </tbody>

                        </table>

                    </div>

                </div>

            </section>


            {{-- =====================================================
                OPERATION NOTE
            ====================================================== --}}
            <section class="assignment-note">

                <div class="assignment-note-icon">
                    <i class="fa-solid fa-circle-info"></i>
                </div>

                <div>

                    <strong>
                        Bus Assignment manages shuttle availability and route allocation.
                    </strong>

                    <p>
                        Driver assignment is handled separately under Driver-Bus Assignment.
                        Buses marked under maintenance or inactive should not be available for operational assignment.
                    </p>

                </div>

            </section>

        </main>

    </div>

</x-layout.app>