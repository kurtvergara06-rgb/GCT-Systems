<x-layout.app
    title="FROMS - Trip Schedule"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Operation/Scheduling_And_Dispatch/trip-schedule.css',
        'resources/js/Main-js/sidebar.js',
    ]"
>

<div class="app">

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
            'label' => 'Fleet Management',
            'icon' => 'fa-bus',
            'children' => [
                [
                    'label' => 'Bus Master List',
                    'route' => 'bus-master-list',
                    'icon' => 'fa-bus'
                ],
                [
                    'label' => 'Fuel Efficiency',
                    'route' => 'fuel-efficiency',
                    'icon' => 'fa-gas-pump'
                ],
            ]
        ],
    ]"
/>


    <main class="main trip-schedule-page">

        <x-layout.topbar
            title="Trip Schedule"
            subtitle="Create and manage shuttle trips, routes, departure times, and schedule status"
            notification-count="4"
        />


        {{-- =====================================================
             SUMMARY
        ====================================================== --}}
        <section class="trip-summary-grid">

            <article class="trip-summary-card">

                <div class="trip-summary-icon blue">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>

                <div>
                    <p>Total Trips Today</p>
                    <h2>8</h2>
                    <small>Scheduled trips</small>
                </div>

            </article>


            <article class="trip-summary-card">

                <div class="trip-summary-icon green">
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <div>
                    <p>Assigned Trips</p>
                    <h2>6</h2>
                    <small>Driver and bus assigned</small>
                </div>

            </article>


            <article class="trip-summary-card">

                <div class="trip-summary-icon yellow">
                    <i class="fa-solid fa-clock"></i>
                </div>

                <div>
                    <p>Pending Assignment</p>
                    <h2>2</h2>
                    <small>Awaiting resources</small>
                </div>

            </article>


            <article class="trip-summary-card">

                <div class="trip-summary-icon purple">
                    <i class="fa-solid fa-route"></i>
                </div>

                <div>
                    <p>Active Routes</p>
                    <h2>4</h2>
                    <small>Used in today's schedule</small>
                </div>

            </article>

        </section>


        {{-- =====================================================
             MAIN CARD
        ====================================================== --}}
        <section class="trip-card">

            <div class="trip-card-header">

                <div>
                    <h2>Trip Records</h2>

                    <p>
                        Create shuttle trips first, then assign an available
                        driver and bus through the assignment module.
                    </p>
                </div>


                <button
                    type="button"
                    class="new-trip-btn"
                    id="openTripModal"
                >
                    <i class="fa-solid fa-plus"></i>
                    New Trip
                </button>

            </div>


            {{-- =================================================
                 TOOLBAR
            ================================================== --}}
            <div class="trip-toolbar">

                <div class="trip-search">

                    <i class="fa-solid fa-magnifying-glass"></i>

                    <input
                        type="text"
                        id="tripSearch"
                        placeholder="Search trip ID, route, status..."
                    >

                </div>


                <div class="trip-filter">

                    <label>
                        Date
                    </label>

                    <input
                        type="date"
                        value="2026-07-23"
                    >

                </div>


                <div class="trip-filter">

                    <label>
                        Route
                    </label>

                    <select id="routeFilter">
                        <option value="all">All Routes</option>
                        <option value="r-01">R-01</option>
                        <option value="r-02">R-02</option>
                        <option value="r-03">R-03</option>
                        <option value="r-04">R-04</option>
                    </select>

                </div>


                <div class="trip-filter">

                    <label>
                        Status
                    </label>

                    <select id="tripStatusFilter">
                        <option value="all">All Statuses</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="ready">Ready</option>
                        <option value="dispatched">Dispatched</option>
                        <option value="completed">Completed</option>
                    </select>

                </div>

            </div>


            {{-- =================================================
                 TABLE
            ================================================== --}}
            <div class="trip-table-wrap">

                <table class="trip-table">

                    <thead>

                        <tr>
                            <th>Trip ID</th>
                            <th>Date</th>
                            <th>Route</th>
                            <th>Departure</th>
                            <th>ETA</th>
                            <th>Shift</th>
                            <th>Assignment</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>

                    </thead>


                    <tbody id="tripTableBody">

                        <tr
                            data-trip-row
                            data-search="t-001 r-01 downtown express scheduled assigned morning"
                            data-route="r-01"
                            data-status="scheduled"
                        >

                            <td>T-001</td>

                            <td>
                                Jul 23, 2026
                            </td>

                            <td>

                                <div class="route-cell">

                                    <strong>R-01</strong>

                                    <span>
                                        Downtown Express
                                    </span>

                                </div>

                            </td>

                            <td>6:00 AM</td>

                            <td>6:45 AM</td>

                            <td>
                                <span class="shift-badge morning">
                                    Morning
                                </span>
                            </td>

                            <td>
                                <span class="assignment-badge assigned">
                                    Assigned
                                </span>
                            </td>

                            <td>
                                <span class="trip-status scheduled">
                                    Scheduled
                                </span>
                            </td>

                            <td>

                                <div class="trip-actions">

                                    <button
                                        type="button"
                                        class="trip-action view"
                                        title="View"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                    </button>


                                    <button
                                        type="button"
                                        class="trip-action edit edit-trip"
                                        title="Edit"
                                        data-trip="T-001"
                                    >
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>


                                    <button
                                        type="button"
                                        class="trip-action delete delete-trip"
                                        title="Delete"
                                        data-trip="T-001"
                                    >
                                        <i class="fa-solid fa-trash"></i>
                                    </button>

                                </div>

                            </td>

                        </tr>


                        <tr
                            data-trip-row
                            data-search="t-002 r-02 sto tomas ready assigned morning"
                            data-route="r-02"
                            data-status="ready"
                        >

                            <td>T-002</td>

                            <td>
                                Jul 23, 2026
                            </td>

                            <td>

                                <div class="route-cell">

                                    <strong>R-02</strong>

                                    <span>
                                        Sto. Tomas
                                    </span>

                                </div>

                            </td>

                            <td>6:30 AM</td>

                            <td>7:30 AM</td>

                            <td>
                                <span class="shift-badge morning">
                                    Morning
                                </span>
                            </td>

                            <td>
                                <span class="assignment-badge assigned">
                                    Assigned
                                </span>
                            </td>

                            <td>
                                <span class="trip-status ready">
                                    Ready
                                </span>
                            </td>

                            <td>

                                <div class="trip-actions">

                                    <button
                                        type="button"
                                        class="trip-action view"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                    </button>

                                    <button
                                        type="button"
                                        class="trip-action edit edit-trip"
                                        data-trip="T-002"
                                    >
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <button
                                        type="button"
                                        class="trip-action delete delete-trip"
                                        data-trip="T-002"
                                    >
                                        <i class="fa-solid fa-trash"></i>
                                    </button>

                                </div>

                            </td>

                        </tr>


                        <tr
                            data-trip-row
                            data-search="t-003 r-03 campus loop dispatched assigned morning"
                            data-route="r-03"
                            data-status="dispatched"
                        >

                            <td>T-003</td>

                            <td>
                                Jul 23, 2026
                            </td>

                            <td>

                                <div class="route-cell">

                                    <strong>R-03</strong>

                                    <span>
                                        Campus Loop
                                    </span>

                                </div>

                            </td>

                            <td>7:00 AM</td>

                            <td>7:35 AM</td>

                            <td>
                                <span class="shift-badge morning">
                                    Morning
                                </span>
                            </td>

                            <td>
                                <span class="assignment-badge assigned">
                                    Assigned
                                </span>
                            </td>

                            <td>
                                <span class="trip-status dispatched">
                                    Dispatched
                                </span>
                            </td>

                            <td>

                                <div class="trip-actions">

                                    <button
                                        type="button"
                                        class="trip-action view"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                    </button>

                                </div>

                            </td>

                        </tr>


                        <tr
                            class="pending-row"
                            data-trip-row
                            data-search="t-004 r-04 industrial zone scheduled unassigned morning"
                            data-route="r-04"
                            data-status="scheduled"
                        >

                            <td>T-004</td>

                            <td>
                                Jul 23, 2026
                            </td>

                            <td>

                                <div class="route-cell">

                                    <strong>R-04</strong>

                                    <span>
                                        Industrial Zone
                                    </span>

                                </div>

                            </td>

                            <td>7:30 AM</td>

                            <td>8:20 AM</td>

                            <td>
                                <span class="shift-badge morning">
                                    Morning
                                </span>
                            </td>

                            <td>

                                <a
                                    href="{{ route('driver-bus-assignment') }}"
                                    class="assignment-badge unassigned"
                                >
                                    Unassigned
                                </a>

                            </td>

                            <td>
                                <span class="trip-status scheduled">
                                    Scheduled
                                </span>
                            </td>

                            <td>

                                <div class="trip-actions">

                                    <button
                                        type="button"
                                        class="trip-action view"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                    </button>

                                    <button
                                        type="button"
                                        class="trip-action edit edit-trip"
                                        data-trip="T-004"
                                    >
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <button
                                        type="button"
                                        class="trip-action delete delete-trip"
                                        data-trip="T-004"
                                    >
                                        <i class="fa-solid fa-trash"></i>
                                    </button>

                                </div>

                            </td>

                        </tr>


                        <tr
                            data-trip-row
                            data-search="t-005 r-01 downtown express ready assigned morning"
                            data-route="r-01"
                            data-status="ready"
                        >

                            <td>T-005</td>

                            <td>
                                Jul 23, 2026
                            </td>

                            <td>

                                <div class="route-cell">

                                    <strong>R-01</strong>

                                    <span>
                                        Downtown Express
                                    </span>

                                </div>

                            </td>

                            <td>8:00 AM</td>

                            <td>8:45 AM</td>

                            <td>
                                <span class="shift-badge morning">
                                    Morning
                                </span>
                            </td>

                            <td>
                                <span class="assignment-badge assigned">
                                    Assigned
                                </span>
                            </td>

                            <td>
                                <span class="trip-status ready">
                                    Ready
                                </span>
                            </td>

                            <td>

                                <div class="trip-actions">

                                    <button
                                        type="button"
                                        class="trip-action view"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                    </button>

                                    <button
                                        type="button"
                                        class="trip-action edit edit-trip"
                                        data-trip="T-005"
                                    >
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <button
                                        type="button"
                                        class="trip-action delete delete-trip"
                                        data-trip="T-005"
                                    >
                                        <i class="fa-solid fa-trash"></i>
                                    </button>

                                </div>

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>


            <div class="trip-table-footer">

                <span id="tripResultCount">
                    Showing 5 trip records
                </span>


                <div class="trip-pagination">

                    <button disabled>
                        Previous
                    </button>

                    <span>
                        Page 1 of 1
                    </span>

                    <button disabled>
                        Next
                    </button>

                </div>

            </div>

        </section>

    </main>

</div>


{{-- =========================================================
     CREATE / EDIT TRIP MODAL
========================================================= --}}
<div
    class="trip-modal-overlay"
    id="tripModal"
>

    <div class="trip-modal">

        <div class="trip-modal-header">

            <div>
                <h2 id="tripModalTitle">
                    New Trip
                </h2>

                <p>
                    Create a shuttle trip using an existing route.
                </p>
            </div>


            <button
                type="button"
                class="trip-modal-close"
                data-close-trip-modal
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </div>


        <form
            class="trip-form"
            id="tripForm"
        >

            <div class="trip-form-grid">

                <div class="trip-form-group">

                    <label>
                        Trip Date
                    </label>

                    <input
                        type="date"
                        id="tripDate"
                        value="2026-07-23"
                        required
                    >

                </div>


                <div class="trip-form-group">

                    <label>
                        Route
                    </label>

                    <select
                        id="tripRoute"
                        required
                    >
                        <option value="">
                            Select route
                        </option>

                        <option value="R-01">
                            R-01 - Downtown Express
                        </option>

                        <option value="R-02">
                            R-02 - Sto. Tomas
                        </option>

                        <option value="R-03">
                            R-03 - Campus Loop
                        </option>

                        <option value="R-04">
                            R-04 - Industrial Zone
                        </option>
                    </select>

                </div>


                <div class="trip-form-group">

                    <label>
                        Departure Time
                    </label>

                    <input
                        type="time"
                        id="departureTime"
                        required
                    >

                </div>


                <div class="trip-form-group">

                    <label>
                        Estimated Arrival
                    </label>

                    <input
                        type="time"
                        id="arrivalTime"
                        required
                    >

                </div>


                <div class="trip-form-group">

                    <label>
                        Shift
                    </label>

                    <select id="tripShift">

                        <option>
                            Morning
                        </option>

                        <option>
                            Afternoon
                        </option>

                        <option>
                            Evening
                        </option>

                    </select>

                </div>


                <div class="trip-form-group">

                    <label>
                        Status
                    </label>

                    <select id="tripStatus">

                        <option>
                            Scheduled
                        </option>

                        <option>
                            Ready
                        </option>

                    </select>

                </div>


                <div class="trip-form-group full">

                    <label>
                        Notes
                    </label>

                    <textarea
                        id="tripNotes"
                        rows="4"
                        placeholder="Optional trip remarks..."
                    ></textarea>

                </div>

            </div>


            <div class="trip-form-note">

                <i class="fa-solid fa-circle-info"></i>

                <div>

                    <strong>
                        Driver and bus assignment is handled separately.
                    </strong>

                    <span>
                        After creating the trip, assign available resources
                        through Driver & Bus Assignment or Auto Scheduling.
                    </span>

                </div>

            </div>


            <div class="trip-modal-actions">

                <button
                    type="button"
                    class="trip-secondary-btn"
                    data-close-trip-modal
                >
                    Cancel
                </button>


                <button
                    type="submit"
                    class="trip-primary-btn"
                >
                    <i class="fa-solid fa-floppy-disk"></i>
                    Save Trip
                </button>

            </div>

        </form>

    </div>

</div>

</x-layout.app>