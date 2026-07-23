<x-layout.app
    title="FROMS - Routes"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Operation/routes.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Operation/routes.js'
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
            'label' => 'Bus Master List',
            'route' => 'bus-master-list',
            'icon' => 'fa-bus'
        ],
    ]"
/>


    <main class="main routes-page">

        <x-layout.topbar
            title="Routes"
            subtitle="Manage shuttle routes, destinations, stops, distance, and estimated travel time"
            notification-count="4"
        />


        {{-- =====================================================
             SUMMARY
        ====================================================== --}}
        <section class="routes-summary-grid">

            <article class="route-summary-card">
                <div class="route-summary-icon blue">
                    <i class="fa-solid fa-route"></i>
                </div>

                <div>
                    <p>Total Routes</p>
                    <h2>4</h2>
                    <small>Registered shuttle routes</small>
                </div>
            </article>


            <article class="route-summary-card">
                <div class="route-summary-icon green">
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <div>
                    <p>Active Routes</p>
                    <h2>4</h2>
                    <small>Currently available</small>
                </div>
            </article>


            <article class="route-summary-card">
                <div class="route-summary-icon purple">
                    <i class="fa-solid fa-location-dot"></i>
                </div>

                <div>
                    <p>Total Stops</p>
                    <h2>10</h2>
                    <small>Across all routes</small>
                </div>
            </article>


            <article class="route-summary-card">
                <div class="route-summary-icon yellow">
                    <i class="fa-solid fa-road"></i>
                </div>

                <div>
                    <p>Route Coverage</p>
                    <h2>67.5 KM</h2>
                    <small>Combined route distance</small>
                </div>
            </article>

        </section>


        {{-- =====================================================
             ROUTES CARD
        ====================================================== --}}
        <section class="routes-card">

            <div class="routes-card-header">
                <div>
                    <h2>Route Records</h2>

                    <p>
                        Routes defined here will be available for trip,
                        bus, and driver scheduling.
                    </p>
                </div>
            </div>


            {{-- =================================================
                 TOOLBAR
            ================================================== --}}
            <div class="routes-toolbar">

                <div class="route-search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>

                    <input
                        type="text"
                        id="routeSearch"
                        placeholder="Search route, origin, destination..."
                    >
                </div>


                <div class="route-filter">
                    <label for="routeStatusFilter">
                        Status
                    </label>

                    <select id="routeStatusFilter">
                        <option value="all">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>


                <button
                    type="button"
                    class="new-route-btn"
                    id="openRouteModal"
                >
                    <i class="fa-solid fa-plus"></i>
                    New Route
                </button>

            </div>


            {{-- =================================================
                 TABLE
            ================================================== --}}
            <div class="routes-table-wrap">

                <table class="routes-table">

                    <thead>
                        <tr>
                            <th>Route ID</th>
                            <th>Route Name</th>
                            <th>Origin</th>
                            <th>Destination</th>
                            <th>Stops</th>
                            <th>Distance</th>
                            <th>Est. Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>


                    <tbody id="routeTableBody">

                        <tr
                            data-route-row
                            data-search="r-01 route a downtown express gate 1 jti"
                            data-status="active"
                        >
                            <td>R-01</td>

                            <td>
                                Route A - Downtown Express
                            </td>

                            <td>Gate 1</td>

                            <td>JTI</td>

                            <td>
                                <button
                                    type="button"
                                    class="stops-btn open-route-details"
                                    data-route="R-01"
                                >
                                    2 Stops
                                </button>
                            </td>

                            <td>12.5 KM</td>

                            <td>45 min</td>

                            <td>
                                <span class="route-status active">
                                    Active
                                </span>
                            </td>

                            <td>
                                <div class="route-actions">

                                    <button
                                        type="button"
                                        class="route-action view open-route-details"
                                        title="View"
                                        data-route="R-01"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                    </button>


                                    <button
                                        type="button"
                                        class="route-action edit edit-route-btn"
                                        title="Edit"
                                        data-route="R-01"
                                    >
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>


                                    <button
                                        type="button"
                                        class="route-action delete delete-route-btn"
                                        title="Delete"
                                        data-route="R-01"
                                    >
                                        <i class="fa-solid fa-trash"></i>
                                    </button>

                                </div>
                            </td>
                        </tr>


                        <tr
                            data-route-row
                            data-search="r-02 route b sto tomas gate 1 iconics"
                            data-status="active"
                        >
                            <td>R-02</td>

                            <td>
                                Route B - Sto. Tomas
                            </td>

                            <td>Gate 1</td>

                            <td>Iconics</td>

                            <td>
                                <button
                                    type="button"
                                    class="stops-btn open-route-details"
                                    data-route="R-02"
                                >
                                    1 Stop
                                </button>
                            </td>

                            <td>28 KM</td>

                            <td>60 min</td>

                            <td>
                                <span class="route-status active">
                                    Active
                                </span>
                            </td>

                            <td>
                                <div class="route-actions">

                                    <button
                                        type="button"
                                        class="route-action view open-route-details"
                                        title="View"
                                        data-route="R-02"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                    </button>

                                    <button
                                        type="button"
                                        class="route-action edit edit-route-btn"
                                        title="Edit"
                                        data-route="R-02"
                                    >
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <button
                                        type="button"
                                        class="route-action delete delete-route-btn"
                                        title="Delete"
                                        data-route="R-02"
                                    >
                                        <i class="fa-solid fa-trash"></i>
                                    </button>

                                </div>
                            </td>
                        </tr>


                        <tr
                            data-route-row
                            data-search="r-03 route c campus loop gate 1 epson"
                            data-status="active"
                        >
                            <td>R-03</td>

                            <td>
                                Route C - Campus Loop
                            </td>

                            <td>Gate 1</td>

                            <td>Epson</td>

                            <td>
                                <button
                                    type="button"
                                    class="stops-btn open-route-details"
                                    data-route="R-03"
                                >
                                    3 Stops
                                </button>
                            </td>

                            <td>8.3 KM</td>

                            <td>35 min</td>

                            <td>
                                <span class="route-status active">
                                    Active
                                </span>
                            </td>

                            <td>
                                <div class="route-actions">

                                    <button
                                        type="button"
                                        class="route-action view open-route-details"
                                        title="View"
                                        data-route="R-03"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                    </button>

                                    <button
                                        type="button"
                                        class="route-action edit edit-route-btn"
                                        title="Edit"
                                        data-route="R-03"
                                    >
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <button
                                        type="button"
                                        class="route-action delete delete-route-btn"
                                        title="Delete"
                                        data-route="R-03"
                                    >
                                        <i class="fa-solid fa-trash"></i>
                                    </button>

                                </div>
                            </td>
                        </tr>


                        <tr
                            data-route-row
                            data-search="r-04 route d industrial zone gate 1 murata"
                            data-status="active"
                        >
                            <td>R-04</td>

                            <td>
                                Route D - Industrial Zone
                            </td>

                            <td>Gate 1</td>

                            <td>Murata</td>

                            <td>
                                <button
                                    type="button"
                                    class="stops-btn open-route-details"
                                    data-route="R-04"
                                >
                                    4 Stops
                                </button>
                            </td>

                            <td>18.7 KM</td>

                            <td>50 min</td>

                            <td>
                                <span class="route-status active">
                                    Active
                                </span>
                            </td>

                            <td>
                                <div class="route-actions">

                                    <button
                                        type="button"
                                        class="route-action view open-route-details"
                                        title="View"
                                        data-route="R-04"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                    </button>

                                    <button
                                        type="button"
                                        class="route-action edit edit-route-btn"
                                        title="Edit"
                                        data-route="R-04"
                                    >
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <button
                                        type="button"
                                        class="route-action delete delete-route-btn"
                                        title="Delete"
                                        data-route="R-04"
                                    >
                                        <i class="fa-solid fa-trash"></i>
                                    </button>

                                </div>
                            </td>
                        </tr>

                    </tbody>

                </table>

            </div>


            <div class="routes-footer">
                <span id="routeResultCount">
                    Showing 4 route records
                </span>

                <div class="routes-pagination">
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
     ADD / EDIT ROUTE MODAL
========================================================= --}}
<div
    class="route-modal-overlay"
    id="routeModal"
>

    <div class="route-modal">

        <div class="route-modal-header">

            <div>
                <h2 id="routeModalTitle">
                    New Route
                </h2>

                <p>
                    Enter the route information and shuttle stops.
                </p>
            </div>

            <button
                type="button"
                class="route-modal-close"
                data-close-route-modal
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </div>


        <form
            class="route-form"
            id="routeForm"
        >

            <div class="route-form-grid">

                <div class="route-form-group full">
                    <label>
                        Route Name
                    </label>

                    <input
                        type="text"
                        id="routeName"
                        placeholder="Example: Route E - Lipa"
                        required
                    >
                </div>


                <div class="route-form-group">
                    <label>
                        Origin
                    </label>

                    <input
                        type="text"
                        id="routeOrigin"
                        placeholder="Enter origin"
                        required
                    >
                </div>


                <div class="route-form-group">
                    <label>
                        Destination
                    </label>

                    <input
                        type="text"
                        id="routeDestination"
                        placeholder="Enter destination"
                        required
                    >
                </div>


                <div class="route-form-group">
                    <label>
                        Distance
                    </label>

                    <div class="route-input-unit">
                        <input
                            type="number"
                            id="routeDistance"
                            step="0.1"
                            min="0"
                            placeholder="0"
                        >

                        <span>KM</span>
                    </div>
                </div>


                <div class="route-form-group">
                    <label>
                        Estimated Travel Time
                    </label>

                    <div class="route-input-unit">
                        <input
                            type="number"
                            id="routeTime"
                            min="1"
                            placeholder="0"
                        >

                        <span>min</span>
                    </div>
                </div>


                <div class="route-form-group full">
                    <label>
                        Status
                    </label>

                    <select id="routeStatus">
                        <option value="active">
                            Active
                        </option>

                        <option value="inactive">
                            Inactive
                        </option>
                    </select>
                </div>

            </div>


            {{-- STOPS --}}
            <div class="route-stops-section">

                <div class="route-stops-header">

                    <div>
                        <h3>
                            Shuttle Stops
                        </h3>

                        <p>
                            Add intermediate stops between the origin
                            and destination.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="add-stop-btn"
                        id="addRouteStop"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Add Stop
                    </button>

                </div>


                <div
                    class="route-stop-list"
                    id="routeStopList"
                >

                    <div class="route-stop-item">

                        <div class="route-stop-number">
                            1
                        </div>

                        <input
                            type="text"
                            placeholder="Enter stop name"
                        >

                        <button
                            type="button"
                            class="remove-route-stop"
                        >
                            <i class="fa-solid fa-trash"></i>
                        </button>

                    </div>

                </div>

            </div>


            <div class="route-modal-actions">

                <button
                    type="button"
                    class="route-secondary-btn"
                    data-close-route-modal
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="route-primary-btn"
                >
                    <i class="fa-solid fa-floppy-disk"></i>
                    Save Route
                </button>

            </div>

        </form>

    </div>

</div>


{{-- =========================================================
     VIEW ROUTE
========================================================= --}}
<div
    class="route-modal-overlay"
    id="routeDetailsModal"
>

    <div class="route-modal route-details-modal">

        <div class="route-modal-header">

            <div>
                <h2>
                    Route Details
                </h2>

                <p>
                    Route information and shuttle stop sequence.
                </p>
            </div>

            <button
                type="button"
                class="route-modal-close"
                data-close-route-details
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </div>


        <div class="route-details-body">

            <section class="route-details-grid">

                <div>
                    <span>Route ID</span>
                    <strong>R-01</strong>
                </div>

                <div>
                    <span>Status</span>
                    <strong class="route-details-active">
                        Active
                    </strong>
                </div>

                <div class="full">
                    <span>Route Name</span>
                    <strong>
                        Route A - Downtown Express
                    </strong>
                </div>

                <div>
                    <span>Origin</span>
                    <strong>Gate 1</strong>
                </div>

                <div>
                    <span>Destination</span>
                    <strong>JTI</strong>
                </div>

                <div>
                    <span>Distance</span>
                    <strong>12.5 KM</strong>
                </div>

                <div>
                    <span>Estimated Time</span>
                    <strong>45 minutes</strong>
                </div>

            </section>


            <section class="route-path">

                <h3>
                    Route Path
                </h3>


                <div class="route-path-list">

                    <div class="route-path-item start">
                        <div class="path-marker">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>

                        <div>
                            <strong>Gate 1</strong>
                            <span>Origin</span>
                        </div>
                    </div>


                    <div class="route-path-item">
                        <div class="path-marker">
                            <i class="fa-solid fa-circle"></i>
                        </div>

                        <div>
                            <strong>
                                Sto. Tomas Junction
                            </strong>

                            <span>Stop 1</span>
                        </div>
                    </div>


                    <div class="route-path-item">
                        <div class="path-marker">
                            <i class="fa-solid fa-circle"></i>
                        </div>

                        <div>
                            <strong>
                                Industrial Crossing
                            </strong>

                            <span>Stop 2</span>
                        </div>
                    </div>


                    <div class="route-path-item destination">
                        <div class="path-marker">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>

                        <div>
                            <strong>JTI</strong>
                            <span>Destination</span>
                        </div>
                    </div>

                </div>

            </section>

        </div>

    </div>

</div>

</x-layout.app>