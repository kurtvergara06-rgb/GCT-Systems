<x-layout.app
    title="FROMS - Driver & Bus Assignment"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Operation/Scheduling_And_Dispatch/driver-bus-assignment.css',
        'resources/js/Main-js/sidebar.js'
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
            'icon' => 'fa-table-cells-large',
        ],

        [
            'label' => 'Routes',
            'route' => 'operation.routes',
            'icon' => 'fa-route',
        ],

        [
            'label' => 'Scheduling',
            'icon' => 'fa-calendar-days',
            'children' => [
                [
                    'label' => 'Trip Schedule',
                    'route' => 'trip-schedule',
                    'icon' => 'fa-calendar-days',
                ],
                [
                    'label' => 'Driver & Bus Assignment',
                    'route' => 'driver-bus-assignment',
                    'icon' => 'fa-user-tie',
                ],
                [
                    'label' => 'Auto Scheduling',
                    'route' => 'auto-scheduling',
                    'icon' => 'fa-wand-magic-sparkles',
                ],
            ],
        ],

        [
            'label' => 'Attendance',
            'icon' => 'fa-calendar-check',
            'children' => [
                [
                    'label' => 'Driver Attendance',
                    'route' => 'driver-attendance',
                    'icon' => 'fa-id-card',
                ],
                [
                    'label' => 'Mechanic Attendance',
                    'route' => 'mechanic-attendance',
                    'icon' => 'fa-users-gear',
                ],
            ],
        ],

        [
            'label' => 'Bus Master List',
            'route' => 'bus-master-list',
            'icon' => 'fa-bus',
        ],
    ]"
/>


    <main class="main assignment-page">

        <x-layout.topbar
            title="Driver & Bus Assignment"
            subtitle="Assign available drivers and shuttle buses to scheduled trips"
            notification-count="4"
        />


        {{-- =====================================================
             SUMMARY CARDS
        ====================================================== --}}
        <section class="assignment-summary-grid">

            <article class="assignment-summary-card">

                <div class="summary-icon blue">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>

                <div>
                    <p>Scheduled Trips</p>
                    <h2>8</h2>
                    <small>Trips for today</small>
                </div>

            </article>


            <article class="assignment-summary-card">

                <div class="summary-icon green">
                    <i class="fa-solid fa-user-check"></i>
                </div>

                <div>
                    <p>Available Drivers</p>
                    <h2>10</h2>
                    <small>Ready for assignment</small>
                </div>

            </article>


            <article class="assignment-summary-card">

                <div class="summary-icon purple">
                    <i class="fa-solid fa-bus"></i>
                </div>

                <div>
                    <p>Available Buses</p>
                    <h2>7</h2>
                    <small>Operational vehicles</small>
                </div>

            </article>


            <article class="assignment-summary-card">

                <div class="summary-icon yellow">
                    <i class="fa-solid fa-circle-exclamation"></i>
                </div>

                <div>
                    <p>Unassigned Trips</p>
                    <h2>2</h2>
                    <small>Need assignment</small>
                </div>

            </article>

        </section>


        {{-- =====================================================
             ASSIGNMENT TABLE
        ====================================================== --}}
        <section class="assignment-card">

            <div class="assignment-card-header">

                <div>
                    <h2>Trip Assignments</h2>

                    <p>
                        Manage driver and bus assignments for scheduled shuttle trips.
                    </p>
                </div>

                <button
                    type="button"
                    class="new-assignment-btn"
                    id="openAssignmentModal"
                >
                    <i class="fa-solid fa-plus"></i>
                    New Assignment
                </button>

            </div>


            {{-- FILTERS --}}
            <div class="assignment-toolbar">

                <div class="assignment-search">

                    <i class="fa-solid fa-magnifying-glass"></i>

                    <input
                        type="text"
                        placeholder="Search trip, route, driver, bus..."
                    >

                </div>


                <div class="assignment-filter">

                    <label>Date</label>

                    <input
                        type="date"
                        value="2026-07-23"
                    >

                </div>


                <div class="assignment-filter">

                    <label>Status</label>

                    <select>
                        <option>All Statuses</option>
                        <option>Ready</option>
                        <option>Assigned</option>
                        <option>Unassigned</option>
                        <option>Dispatched</option>
                    </select>

                </div>

            </div>


            <div class="assignment-table-wrap">

                <table class="assignment-table">

                    <thead>
                        <tr>
                            <th>Trip ID</th>
                            <th>Schedule</th>
                            <th>Route</th>
                            <th>Driver</th>
                            <th>Bus</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>


                    <tbody>

                        <tr>

                            <td>T-001</td>

                            <td>
                                <div class="schedule-cell">
                                    <strong>6:00 AM</strong>
                                    <span>Jul 23, 2026</span>
                                </div>
                            </td>

                            <td>
                                <div class="route-cell">
                                    <strong>R-01</strong>
                                    <span>Downtown Express</span>
                                </div>
                            </td>

                            <td>
                                <div class="driver-cell">

                                    <div class="driver-avatar">
                                        RA
                                    </div>

                                    <div>
                                        <strong>Rowell Amano</strong>
                                        <span>Available</span>
                                    </div>

                                </div>
                            </td>

                            <td>
                                <span class="bus-badge">
                                    BUS-001
                                </span>
                            </td>

                            <td>
                                <span class="assignment-status ready">
                                    Ready
                                </span>
                            </td>

                            <td>
                                <div class="assignment-actions">

                                    <button
                                        type="button"
                                        class="assignment-action view"
                                        title="View"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                    </button>

                                    <button
                                        type="button"
                                        class="assignment-action edit"
                                        title="Edit Assignment"
                                    >
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                </div>
                            </td>

                        </tr>


                        <tr>

                            <td>T-002</td>

                            <td>
                                <div class="schedule-cell">
                                    <strong>6:30 AM</strong>
                                    <span>Jul 23, 2026</span>
                                </div>
                            </td>

                            <td>
                                <div class="route-cell">
                                    <strong>R-02</strong>
                                    <span>Sto. Tomas</span>
                                </div>
                            </td>

                            <td>
                                <div class="driver-cell">

                                    <div class="driver-avatar">
                                        CM
                                    </div>

                                    <div>
                                        <strong>Cardo Mendoza</strong>
                                        <span>Available</span>
                                    </div>

                                </div>
                            </td>

                            <td>
                                <span class="bus-badge">
                                    BUS-003
                                </span>
                            </td>

                            <td>
                                <span class="assignment-status assigned">
                                    Assigned
                                </span>
                            </td>

                            <td>
                                <div class="assignment-actions">

                                    <button
                                        type="button"
                                        class="assignment-action view"
                                        title="View"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                    </button>

                                    <button
                                        type="button"
                                        class="assignment-action edit"
                                        title="Edit Assignment"
                                    >
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                </div>
                            </td>

                        </tr>


                        <tr>

                            <td>T-003</td>

                            <td>
                                <div class="schedule-cell">
                                    <strong>7:00 AM</strong>
                                    <span>Jul 23, 2026</span>
                                </div>
                            </td>

                            <td>
                                <div class="route-cell">
                                    <strong>R-03</strong>
                                    <span>Campus Loop</span>
                                </div>
                            </td>

                            <td>
                                <div class="driver-cell">

                                    <div class="driver-avatar">
                                        JP
                                    </div>

                                    <div>
                                        <strong>Juan Perez</strong>
                                        <span>Available</span>
                                    </div>

                                </div>
                            </td>

                            <td>
                                <span class="bus-badge">
                                    BUS-004
                                </span>
                            </td>

                            <td>
                                <span class="assignment-status dispatched">
                                    Dispatched
                                </span>
                            </td>

                            <td>
                                <div class="assignment-actions">

                                    <button
                                        type="button"
                                        class="assignment-action view"
                                        title="View"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                    </button>

                                </div>
                            </td>

                        </tr>


                        <tr class="unassigned-row">

                            <td>T-004</td>

                            <td>
                                <div class="schedule-cell">
                                    <strong>7:30 AM</strong>
                                    <span>Jul 23, 2026</span>
                                </div>
                            </td>

                            <td>
                                <div class="route-cell">
                                    <strong>R-04</strong>
                                    <span>Industrial Zone</span>
                                </div>
                            </td>

                            <td>
                                <span class="not-assigned">
                                    Not Assigned
                                </span>
                            </td>

                            <td>
                                <span class="not-assigned">
                                    Not Assigned
                                </span>
                            </td>

                            <td>
                                <span class="assignment-status unassigned">
                                    Unassigned
                                </span>
                            </td>

                            <td>
                                <button
                                    type="button"
                                    class="assign-now-btn"
                                    data-open-assignment
                                >
                                    Assign
                                </button>
                            </td>

                        </tr>


                        <tr>

                            <td>T-005</td>

                            <td>
                                <div class="schedule-cell">
                                    <strong>8:00 AM</strong>
                                    <span>Jul 23, 2026</span>
                                </div>
                            </td>

                            <td>
                                <div class="route-cell">
                                    <strong>R-01</strong>
                                    <span>Downtown Express</span>
                                </div>
                            </td>

                            <td>
                                <div class="driver-cell">

                                    <div class="driver-avatar">
                                        AR
                                    </div>

                                    <div>
                                        <strong>Allan Reyes</strong>
                                        <span>Available</span>
                                    </div>

                                </div>
                            </td>

                            <td>
                                <span class="bus-badge">
                                    BUS-006
                                </span>
                            </td>

                            <td>
                                <span class="assignment-status ready">
                                    Ready
                                </span>
                            </td>

                            <td>
                                <div class="assignment-actions">

                                    <button
                                        type="button"
                                        class="assignment-action view"
                                        title="View"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                    </button>

                                    <button
                                        type="button"
                                        class="assignment-action edit"
                                        title="Edit Assignment"
                                    >
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                </div>
                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>


            <div class="assignment-footer">

                <span>
                    Showing 5 trip assignments
                </span>

                <div>
                    <button disabled>Previous</button>
                    <span>Page 1 of 1</span>
                    <button disabled>Next</button>
                </div>

            </div>

        </section>


        {{-- =====================================================
             AVAILABLE RESOURCES
        ====================================================== --}}
        <section class="resource-grid">

            {{-- DRIVERS --}}
            <article class="resource-card">

                <div class="resource-card-header">

                    <div>
                        <span>Workforce</span>
                        <h2>Available Drivers</h2>
                    </div>

                    <strong class="resource-total">
                        10
                    </strong>

                </div>


                <div class="resource-record">

                    <div class="driver-avatar">
                        RA
                    </div>

                    <div class="resource-record-info">
                        <strong>Rowell Amano</strong>
                        <span>Morning Shift</span>
                    </div>

                    <span class="availability available">
                        Available
                    </span>

                </div>


                <div class="resource-record">

                    <div class="driver-avatar">
                        CM
                    </div>

                    <div class="resource-record-info">
                        <strong>Cardo Mendoza</strong>
                        <span>Morning Shift</span>
                    </div>

                    <span class="availability available">
                        Available
                    </span>

                </div>


                <div class="resource-record">

                    <div class="driver-avatar">
                        AR
                    </div>

                    <div class="resource-record-info">
                        <strong>Allan Reyes</strong>
                        <span>Morning Shift</span>
                    </div>

                    <span class="availability assigned">
                        Assigned
                    </span>

                </div>

            </article>


            {{-- BUSES --}}
            <article class="resource-card">

                <div class="resource-card-header">

                    <div>
                        <span>Fleet</span>
                        <h2>Available Buses</h2>
                    </div>

                    <strong class="resource-total">
                        7
                    </strong>

                </div>


                <div class="resource-record">

                    <div class="bus-resource-icon">
                        <i class="fa-solid fa-bus"></i>
                    </div>

                    <div class="resource-record-info">
                        <strong>BUS-001</strong>
                        <span>Operational</span>
                    </div>

                    <span class="availability available">
                        Available
                    </span>

                </div>


                <div class="resource-record">

                    <div class="bus-resource-icon">
                        <i class="fa-solid fa-bus"></i>
                    </div>

                    <div class="resource-record-info">
                        <strong>BUS-003</strong>
                        <span>Operational</span>
                    </div>

                    <span class="availability available">
                        Available
                    </span>

                </div>


                <div class="resource-record">

                    <div class="bus-resource-icon">
                        <i class="fa-solid fa-bus"></i>
                    </div>

                    <div class="resource-record-info">
                        <strong>BUS-005</strong>
                        <span>Under Maintenance</span>
                    </div>

                    <span class="availability unavailable">
                        Unavailable
                    </span>

                </div>

            </article>

        </section>

    </main>

</div>


{{-- =========================================================
     NEW ASSIGNMENT MODAL
========================================================= --}}
<div
    class="assignment-modal-overlay"
    id="assignmentModal"
>

    <div class="assignment-modal">

        <div class="assignment-modal-header">

            <div>
                <h2>Driver & Bus Assignment</h2>

                <p>
                    Select an available driver and shuttle bus for the trip.
                </p>
            </div>

            <button
                type="button"
                class="modal-close"
                onclick="document.getElementById('assignmentModal').classList.remove('show')"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </div>


        <div class="assignment-modal-body">

            <div class="trip-summary">

                <div>
                    <span>Trip</span>
                    <strong>T-004</strong>
                </div>

                <div>
                    <span>Route</span>
                    <strong>R-04 - Industrial Zone</strong>
                </div>

                <div>
                    <span>Departure</span>
                    <strong>7:30 AM</strong>
                </div>

            </div>


            <div class="assignment-form-grid">

                <div class="assignment-field">

                    <label>
                        Driver
                    </label>

                    <select>
                        <option value="">
                            Select available driver
                        </option>

                        <option>
                            Rowell Amano
                        </option>

                        <option>
                            Cardo Mendoza
                        </option>

                        <option>
                            Allan Reyes
                        </option>

                        <option>
                            Juan Perez
                        </option>
                    </select>

                    <small>
                        Only present and available drivers are shown.
                    </small>

                </div>


                <div class="assignment-field">

                    <label>
                        Shuttle Bus
                    </label>

                    <select>
                        <option value="">
                            Select available bus
                        </option>

                        <option>
                            BUS-001
                        </option>

                        <option>
                            BUS-003
                        </option>

                        <option>
                            BUS-004
                        </option>

                        <option>
                            BUS-006
                        </option>
                    </select>

                    <small>
                        Buses under maintenance are excluded.
                    </small>

                </div>

            </div>


            <div class="assignment-validation">

                <i class="fa-solid fa-circle-check"></i>

                <div>
                    <strong>
                        Assignment validation
                    </strong>

                    <span>
                        The system will check availability and schedule
                        conflicts before confirming an assignment.
                    </span>
                </div>

            </div>


            <div class="assignment-modal-footer">

                <button
                    type="button"
                    class="secondary-modal-btn"
                    onclick="document.getElementById('assignmentModal').classList.remove('show')"
                >
                    Cancel
                </button>

                <button
                    type="button"
                    class="primary-modal-btn"
                >
                    <i class="fa-solid fa-check"></i>
                    Confirm Assignment
                </button>

            </div>

        </div>

    </div>

</div>


<script>
    document.addEventListener('DOMContentLoaded', function () {

        const modal = document.getElementById('assignmentModal');
        const openButton = document.getElementById('openAssignmentModal');

        openButton?.addEventListener('click', function () {
            modal?.classList.add('show');
        });

        document.querySelectorAll('[data-open-assignment]').forEach(function (button) {
            button.addEventListener('click', function () {
                modal?.classList.add('show');
            });
        });

        modal?.addEventListener('click', function (event) {
            if (event.target === modal) {
                modal.classList.remove('show');
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                modal?.classList.remove('show');
            }
        });

    });
</script>

</x-layout.app>