<x-layout.app
    title="FROMS - Auto Scheduling"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Operation/Scheduling_And_Dispatch/auto-dispatch.css',
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


    <main class="main auto-scheduling-page">

        <x-layout.topbar
            title="Auto Scheduling"
            subtitle="Automatically assign available drivers and shuttle buses to scheduled trips"
            notification-count="4"
        />


        {{-- =====================================================
             SUMMARY CARDS
        ====================================================== --}}
        <section class="auto-summary-grid">

            <article class="auto-summary-card">

                <div class="summary-icon blue">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>

                <div>
                    <p>Trips to Schedule</p>
                    <h2>8</h2>
                    <small>For selected date</small>
                </div>

            </article>


            <article class="auto-summary-card">

                <div class="summary-icon green">
                    <i class="fa-solid fa-user-check"></i>
                </div>

                <div>
                    <p>Available Drivers</p>
                    <h2>10</h2>
                    <small>Present and available</small>
                </div>

            </article>


            <article class="auto-summary-card">

                <div class="summary-icon purple">
                    <i class="fa-solid fa-bus"></i>
                </div>

                <div>
                    <p>Available Buses</p>
                    <h2>7</h2>
                    <small>Ready for dispatch</small>
                </div>

            </article>


            <article class="auto-summary-card">

                <div class="summary-icon yellow">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>

                <div>
                    <p>Potential Conflicts</p>
                    <h2>1</h2>
                    <small>Needs review</small>
                </div>

            </article>

        </section>


        {{-- =====================================================
             AUTO SCHEDULING CONFIGURATION
        ====================================================== --}}
        <section class="auto-main-grid">

            <article class="auto-card schedule-configuration-card">

                <div class="auto-card-header">

                    <div>
                        <span class="section-eyebrow">
                            Scheduling Setup
                        </span>

                        <h2>
                            Generate Dispatch Schedule
                        </h2>

                        <p>
                            Select the date and shift, then let the system
                            prepare recommended driver and bus assignments.
                        </p>
                    </div>

                    <div class="auto-header-icon">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                    </div>

                </div>


                <div class="schedule-form-grid">

                    <div class="schedule-field">

                        <label>
                            Schedule Date
                        </label>

                        <div class="field-control">

                            <i class="fa-solid fa-calendar"></i>

                            <input
                                type="date"
                                value="2026-07-23"
                            >

                        </div>

                    </div>


                    <div class="schedule-field">

                        <label>
                            Shift
                        </label>

                        <div class="field-control">

                            <i class="fa-solid fa-clock"></i>

                            <select>
                                <option>
                                    All Shifts
                                </option>

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

                    </div>


                    <div class="schedule-field">

                        <label>
                            Route
                        </label>

                        <div class="field-control">

                            <i class="fa-solid fa-route"></i>

                            <select>
                                <option>
                                    All Routes
                                </option>

                                <option>
                                    R-01 - Downtown Express
                                </option>

                                <option>
                                    R-02 - Sto. Tomas
                                </option>

                                <option>
                                    R-03 - Campus Loop
                                </option>

                                <option>
                                    R-04 - Industrial Zone
                                </option>
                            </select>

                        </div>

                    </div>

                </div>


                <div class="generation-summary">

                    <div class="generation-summary-item">

                        <span>
                            Trips
                        </span>

                        <strong>
                            8
                        </strong>

                    </div>


                    <div class="generation-arrow">
                        <i class="fa-solid fa-arrow-right"></i>
                    </div>


                    <div class="generation-summary-item">

                        <span>
                            Drivers
                        </span>

                        <strong>
                            10
                        </strong>

                    </div>


                    <div class="generation-arrow">
                        <i class="fa-solid fa-plus"></i>
                    </div>


                    <div class="generation-summary-item">

                        <span>
                            Buses
                        </span>

                        <strong>
                            7
                        </strong>

                    </div>

                </div>


                <button
                    type="button"
                    class="generate-schedule-btn"
                >
                    <i class="fa-solid fa-wand-magic-sparkles"></i>

                    Generate Schedule
                </button>

            </article>


            {{-- =================================================
                 RESOURCE AVAILABILITY
            ================================================== --}}
            <article class="auto-card resource-card">

                <div class="auto-card-header compact">

                    <div>
                        <span class="section-eyebrow">
                            Resource Status
                        </span>

                        <h2>
                            Availability
                        </h2>

                        <p>
                            Resources eligible for scheduling.
                        </p>
                    </div>

                </div>


                <div class="resource-list">

                    <div class="resource-item">

                        <div class="resource-info">

                            <div class="resource-icon green">
                                <i class="fa-solid fa-user-check"></i>
                            </div>

                            <div>
                                <strong>
                                    Available Drivers
                                </strong>

                                <span>
                                    Present and not assigned
                                </span>
                            </div>

                        </div>

                        <strong class="resource-count">
                            10
                        </strong>

                    </div>


                    <div class="resource-item">

                        <div class="resource-info">

                            <div class="resource-icon blue">
                                <i class="fa-solid fa-bus"></i>
                            </div>

                            <div>
                                <strong>
                                    Available Buses
                                </strong>

                                <span>
                                    Operational and unassigned
                                </span>
                            </div>

                        </div>

                        <strong class="resource-count">
                            7
                        </strong>

                    </div>


                    <div class="resource-item">

                        <div class="resource-info">

                            <div class="resource-icon red">
                                <i class="fa-solid fa-user-xmark"></i>
                            </div>

                            <div>
                                <strong>
                                    Unavailable Drivers
                                </strong>

                                <span>
                                    Absent or already assigned
                                </span>
                            </div>

                        </div>

                        <strong class="resource-count">
                            3
                        </strong>

                    </div>


                    <div class="resource-item">

                        <div class="resource-info">

                            <div class="resource-icon orange">
                                <i class="fa-solid fa-screwdriver-wrench"></i>
                            </div>

                            <div>
                                <strong>
                                    Unavailable Buses
                                </strong>

                                <span>
                                    Maintenance or assigned
                                </span>
                            </div>

                        </div>

                        <strong class="resource-count">
                            2
                        </strong>

                    </div>

                </div>

            </article>

        </section>


        {{-- =====================================================
             SCHEDULING RULES
        ====================================================== --}}
        <section class="auto-card scheduling-rules-card">

            <div class="auto-card-header">

                <div>
                    <span class="section-eyebrow">
                        Dispatch Logic
                    </span>

                    <h2>
                        Scheduling Rules
                    </h2>

                    <p>
                        Rules considered before a driver or shuttle bus
                        is assigned to a trip.
                    </p>
                </div>

            </div>


            <div class="rules-grid">

                <div class="rule-item active">

                    <div class="rule-check">
                        <i class="fa-solid fa-check"></i>
                    </div>

                    <div>
                        <strong>
                            Driver must be present
                        </strong>

                        <span>
                            Only drivers marked Present or On Duty
                            can be assigned.
                        </span>
                    </div>

                </div>


                <div class="rule-item active">

                    <div class="rule-check">
                        <i class="fa-solid fa-check"></i>
                    </div>

                    <div>
                        <strong>
                            Driver must be available
                        </strong>

                        <span>
                            Prevent overlapping driver assignments.
                        </span>
                    </div>

                </div>


                <div class="rule-item active">

                    <div class="rule-check">
                        <i class="fa-solid fa-check"></i>
                    </div>

                    <div>
                        <strong>
                            Bus must be operational
                        </strong>

                        <span>
                            Inactive or unavailable buses are excluded.
                        </span>
                    </div>

                </div>


                <div class="rule-item active">

                    <div class="rule-check">
                        <i class="fa-solid fa-check"></i>
                    </div>

                    <div>
                        <strong>
                            Exclude buses under maintenance
                        </strong>

                        <span>
                            Buses currently under repair or maintenance
                            cannot be dispatched.
                        </span>
                    </div>

                </div>


                <div class="rule-item active">

                    <div class="rule-check">
                        <i class="fa-solid fa-check"></i>
                    </div>

                    <div>
                        <strong>
                            Prevent bus schedule conflicts
                        </strong>

                        <span>
                            The same bus cannot be assigned to
                            overlapping trips.
                        </span>
                    </div>

                </div>


                <div class="rule-item active">

                    <div class="rule-check">
                        <i class="fa-solid fa-check"></i>
                    </div>

                    <div>
                        <strong>
                            Balance driver workload
                        </strong>

                        <span>
                            Prefer available drivers with fewer
                            assignments.
                        </span>
                    </div>

                </div>

            </div>

        </section>


        {{-- =====================================================
             GENERATED SCHEDULE
        ====================================================== --}}
        <section class="auto-card generated-schedule-card">

            <div class="auto-card-header schedule-result-header">

                <div>
                    <span class="section-eyebrow">
                        Auto-Generated Result
                    </span>

                    <h2>
                        Schedule Preview
                    </h2>

                    <p>
                        Review the recommended assignments before
                        confirming the dispatch schedule.
                    </p>
                </div>


                <div class="schedule-result-badge">
                    <i class="fa-solid fa-circle-check"></i>

                    7 Ready
                </div>

            </div>


            <div class="auto-table-wrap">

                <table class="auto-schedule-table">

                    <thead>

                        <tr>
                            <th>Trip ID</th>
                            <th>Time</th>
                            <th>Route</th>
                            <th>Driver</th>
                            <th>Bus</th>
                            <th>Result</th>
                            <th>Action</th>
                        </tr>

                    </thead>


                    <tbody>

                        <tr>

                            <td>
                                T-001
                            </td>

                            <td>
                                6:00 AM
                            </td>

                            <td>

                                <div class="route-cell">

                                    <strong>
                                        R-01
                                    </strong>

                                    <span>
                                        Downtown Express
                                    </span>

                                </div>

                            </td>

                            <td>

                                <div class="driver-cell">

                                    <div class="driver-avatar">
                                        RA
                                    </div>

                                    <div>
                                        <strong>
                                            Rowell Amano
                                        </strong>

                                        <span>
                                            Available
                                        </span>
                                    </div>

                                </div>

                            </td>

                            <td>
                                <span class="bus-chip">
                                    BUS-001
                                </span>
                            </td>

                            <td>
                                <span class="schedule-status ready">
                                    Ready
                                </span>
                            </td>

                            <td>

                                <button
                                    type="button"
                                    class="schedule-action-btn"
                                    title="Edit assignment"
                                >
                                    <i class="fa-solid fa-pen"></i>
                                </button>

                            </td>

                        </tr>


                        <tr>

                            <td>
                                T-002
                            </td>

                            <td>
                                6:30 AM
                            </td>

                            <td>

                                <div class="route-cell">

                                    <strong>
                                        R-02
                                    </strong>

                                    <span>
                                        Sto. Tomas
                                    </span>

                                </div>

                            </td>

                            <td>

                                <div class="driver-cell">

                                    <div class="driver-avatar">
                                        CM
                                    </div>

                                    <div>
                                        <strong>
                                            Cardo Mendoza
                                        </strong>

                                        <span>
                                            Available
                                        </span>
                                    </div>

                                </div>

                            </td>

                            <td>
                                <span class="bus-chip">
                                    BUS-003
                                </span>
                            </td>

                            <td>
                                <span class="schedule-status ready">
                                    Ready
                                </span>
                            </td>

                            <td>

                                <button
                                    type="button"
                                    class="schedule-action-btn"
                                    title="Edit assignment"
                                >
                                    <i class="fa-solid fa-pen"></i>
                                </button>

                            </td>

                        </tr>


                        <tr>

                            <td>
                                T-003
                            </td>

                            <td>
                                7:00 AM
                            </td>

                            <td>

                                <div class="route-cell">

                                    <strong>
                                        R-03
                                    </strong>

                                    <span>
                                        Campus Loop
                                    </span>

                                </div>

                            </td>

                            <td>

                                <div class="driver-cell">

                                    <div class="driver-avatar">
                                        JP
                                    </div>

                                    <div>
                                        <strong>
                                            Juan Perez
                                        </strong>

                                        <span>
                                            Available
                                        </span>
                                    </div>

                                </div>

                            </td>

                            <td>
                                <span class="bus-chip">
                                    BUS-004
                                </span>
                            </td>

                            <td>
                                <span class="schedule-status ready">
                                    Ready
                                </span>
                            </td>

                            <td>

                                <button
                                    type="button"
                                    class="schedule-action-btn"
                                    title="Edit assignment"
                                >
                                    <i class="fa-solid fa-pen"></i>
                                </button>

                            </td>

                        </tr>


                        <tr class="conflict-row">

                            <td>
                                T-004
                            </td>

                            <td>
                                7:30 AM
                            </td>

                            <td>

                                <div class="route-cell">

                                    <strong>
                                        R-04
                                    </strong>

                                    <span>
                                        Industrial Zone
                                    </span>

                                </div>

                            </td>

                            <td>
                                <span class="unassigned-text">
                                    Not Assigned
                                </span>
                            </td>

                            <td>
                                <span class="unassigned-text">
                                    Not Assigned
                                </span>
                            </td>

                            <td>
                                <span class="schedule-status conflict">
                                    Conflict
                                </span>
                            </td>

                            <td>

                                <button
                                    type="button"
                                    class="schedule-action-btn warning"
                                    title="Resolve conflict"
                                >
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                </button>

                            </td>

                        </tr>


                        <tr>

                            <td>
                                T-005
                            </td>

                            <td>
                                8:00 AM
                            </td>

                            <td>

                                <div class="route-cell">

                                    <strong>
                                        R-01
                                    </strong>

                                    <span>
                                        Downtown Express
                                    </span>

                                </div>

                            </td>

                            <td>

                                <div class="driver-cell">

                                    <div class="driver-avatar">
                                        AR
                                    </div>

                                    <div>
                                        <strong>
                                            Allan Reyes
                                        </strong>

                                        <span>
                                            Available
                                        </span>
                                    </div>

                                </div>

                            </td>

                            <td>
                                <span class="bus-chip">
                                    BUS-006
                                </span>
                            </td>

                            <td>
                                <span class="schedule-status ready">
                                    Ready
                                </span>
                            </td>

                            <td>

                                <button
                                    type="button"
                                    class="schedule-action-btn"
                                    title="Edit assignment"
                                >
                                    <i class="fa-solid fa-pen"></i>
                                </button>

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>


            <div class="schedule-footer">

                <div class="schedule-footer-info">

                    <i class="fa-solid fa-circle-info"></i>

                    <span>
                        1 trip requires manual review before
                        confirming this schedule.
                    </span>

                </div>


                <div class="schedule-footer-actions">

                    <button
                        type="button"
                        class="schedule-secondary-btn"
                    >
                        <i class="fa-solid fa-rotate"></i>

                        Regenerate
                    </button>


                    <button
                        type="button"
                        class="schedule-primary-btn"
                    >
                        <i class="fa-solid fa-circle-check"></i>

                        Confirm Schedule
                    </button>

                </div>

            </div>

        </section>


        {{-- =====================================================
             CONFLICT DETAILS
        ====================================================== --}}
        <section class="auto-card conflict-card">

            <div class="conflict-header">

                <div class="conflict-icon">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>


                <div>

                    <span class="section-eyebrow warning">
                        Requires Attention
                    </span>

                    <h2>
                        Scheduling Conflict
                    </h2>

                    <p>
                        Some trips could not be assigned automatically.
                    </p>

                </div>

            </div>


            <div class="conflict-content">

                <div class="conflict-trip">

                    <span>
                        Trip
                    </span>

                    <strong>
                        T-004
                    </strong>

                </div>


                <div class="conflict-trip">

                    <span>
                        Route
                    </span>

                    <strong>
                        R-04 - Industrial Zone
                    </strong>

                </div>


                <div class="conflict-trip">

                    <span>
                        Departure
                    </span>

                    <strong>
                        7:30 AM
                    </strong>

                </div>


                <div class="conflict-reason">

                    <i class="fa-solid fa-circle-info"></i>

                    <div>

                        <strong>
                            No eligible bus is available for this time slot.
                        </strong>

                        <span>
                            Review existing assignments or manually select
                            another available vehicle.
                        </span>

                    </div>

                </div>

            </div>


            <button
                type="button"
                class="resolve-conflict-btn"
            >
                <i class="fa-solid fa-screwdriver-wrench"></i>

                Resolve Manually
            </button>

        </section>

    </main>

</div>

</x-layout.app>