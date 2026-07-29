<x-layout.app
    title="FROMS - Routes"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Main-styles/form-components.css',
        'resources/css/Operation/Routes/routes-stops.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Operation/Routes/routes-stops.js'
    ]"
>

    {{-- =====================================================
        VALIDATION ERROR
    ====================================================== --}}
    @if($errors->any())

        <div
            id="routeValidationModal"
            class="modal-overlay delete-modal-overlay show active"
        >

            <div class="modal-card delete-modal-box">

                <div class="delete-icon">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>

                <h2>
                    Form Error
                </h2>

                <p>
                    Please check the route information.
                </p>

                <ul class="form-error-list">

                    @foreach($errors->all() as $error)

                        <li>
                            {{ $error }}
                        </li>

                    @endforeach

                </ul>

                <div class="delete-modal-actions">

                    <button
                        type="button"
                        id="closeRouteValidationModal"
                        class="secondary-btn"
                    >
                        Okay
                    </button>

                </div>

            </div>

        </div>

    @endif


    <div class="app">

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

        <main class="main routes-page">

            {{-- =====================================================
                TOPBAR
            ====================================================== --}}
            <x-layout.topbar
                title="Routes"
                subtitle="Manage shuttle routes, stops, distances, and estimated travel times for scheduling and dispatch"
                notification-count="6"
            />


            {{-- =====================================================
                ROUTE SUMMARY
            ====================================================== --}}
          @php
    $routeStats = $routeStats ?? [
        'total' => $routes->total(),
        'active' => $routes->getCollection()
            ->where('status', 'Active')
            ->count(),
        'inactive' => $routes->getCollection()
            ->where('status', 'Inactive')
            ->count(),
        'stops' => $routes->getCollection()
            ->sum(fn ($route) => $route->stops->count()),
    ];
@endphp

<section class="route-summary-grid" aria-label="Route summary">
    <x-ui.summary-card
        label="Total Routes"
        :value="$routeStats['total']"
        small="All registered routes"
        icon="fa-route"
        color="blue"
    />

    <x-ui.summary-card
        label="Active Routes"
        :value="$routeStats['active']"
        small="Available for scheduling"
        icon="fa-circle-check"
        color="green"
    />

    <x-ui.summary-card
        label="Inactive Routes"
        :value="$routeStats['inactive']"
        small="Currently unavailable"
        icon="fa-circle-pause"
        color="orange"
    />

    <x-ui.summary-card
        label="Total Stops"
        :value="$routeStats['stops']"
        small="Stops in loaded routes"
        icon="fa-location-dot"
        color="purple"
    />
</section>


            {{-- =====================================================
                ROUTES TABLE
            ====================================================== --}}
            <section class="table-card routes-card">

                <div class="section-header">

                    <div>

                        <h2>
                            Route Records
                        </h2>

                        <p>
                            Routes defined here will be available for trip,
                            bus, and driver scheduling.
                        </p>

                    </div>

                </div>


                {{-- =================================================
                    TOOLBAR
                ================================================== --}}
                <form
                    method="GET"
                    action="{{ route('operation.routes') }}"
                    class="toolbar routes-toolbar"
                >

                    <div class="search-box">

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            name="search"
                            id="routeSearch"
                            value="{{ request('search') }}"
                            placeholder="Search route, origin, destination..."
                        >

                    </div>


                    <div class="filter-group">

                        <label
                            for="routeStatusFilter"
                            class="visually-hidden"
                        >
                            Status
                        </label>

                        <select
                            name="status"
                            id="routeStatusFilter"
                            onchange="this.form.requestSubmit()"
                        >

                            <option
                                value="all"
                                {{ request('status', 'all') === 'all' ? 'selected' : '' }}
                            >
                                All Statuses
                            </option>

                            <option
                                value="active"
                                {{ request('status') === 'active' ? 'selected' : '' }}
                            >
                                Active
                            </option>

                            <option
                                value="inactive"
                                {{ request('status') === 'inactive' ? 'selected' : '' }}
                            >
                                Inactive
                            </option>

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

                </form>


                {{-- =================================================
                    TABLE
                ================================================== --}}
                <div class="table-wrap routes-table-wrap">

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


                        <tbody>

                            @forelse($routes as $route)

                                @php
                                    $stopsJson = $route
                                        ->stops
                                        ->pluck('stop_name')
                                        ->values()
                                        ->toJson();

                                    $statusClass =
                                        strtolower($route->status);
                                @endphp


                                <tr>

                                    {{-- ROUTE ID --}}
                                    <td>
                                        {{ $route->route_code }}
                                    </td>


                                    {{-- ROUTE NAME --}}
                                    <td>
                                        {{ $route->route_name }}
                                    </td>


                                    {{-- ORIGIN --}}
                                    <td>
                                        {{ $route->origin }}
                                    </td>


                                    {{-- DESTINATION --}}
                                    <td>
                                        {{ $route->destination }}
                                    </td>


                                    {{-- STOPS --}}
                                    <td>

                                        <button
                                            type="button"
                                            class="stops-btn open-route-details"

                                            data-id="{{ $route->id }}"
                                            data-route-code="{{ $route->route_code }}"
                                            data-route-name="{{ $route->route_name }}"
                                            data-origin="{{ $route->origin }}"
                                            data-destination="{{ $route->destination }}"
                                            data-distance="{{ $route->distance_km }}"
                                            data-time="{{ $route->estimated_time_minutes }}"
                                            data-status="{{ $route->status }}"
                                            data-stops="{{ $stopsJson }}"
                                        >
                                            {{ $route->stops->count() }}

                                            {{ $route->stops->count() === 1 ? 'Stop' : 'Stops' }}
                                        </button>

                                    </td>


                                    {{-- DISTANCE --}}
                                    <td>

                                        @if($route->distance_km !== null)

                                            {{ number_format((float) $route->distance_km, 1) }} KM

                                        @else

                                            —

                                        @endif

                                    </td>


                                    {{-- ESTIMATED TIME --}}
                                    <td>

                                        @if($route->estimated_time_minutes)

                                            {{ $route->estimated_time_minutes }} min

                                        @else

                                            —

                                        @endif

                                    </td>


                                    {{-- STATUS --}}
                                    <td>

                                        <span class="route-status {{ $statusClass }}">
                                            {{ $route->status }}
                                        </span>

                                    </td>


                                    {{-- ACTIONS --}}
                                    <td>

                                        <div class="route-actions">

                                            {{-- VIEW --}}
                                            <button
                                                type="button"
                                                class="route-action view open-route-details"
                                                title="View Route"

                                                data-id="{{ $route->id }}"
                                                data-route-code="{{ $route->route_code }}"
                                                data-route-name="{{ $route->route_name }}"
                                                data-origin="{{ $route->origin }}"
                                                data-destination="{{ $route->destination }}"
                                                data-distance="{{ $route->distance_km }}"
                                                data-time="{{ $route->estimated_time_minutes }}"
                                                data-status="{{ $route->status }}"
                                                data-stops="{{ $stopsJson }}"
                                            >
                                                <i class="fa-solid fa-eye"></i>
                                            </button>


                                            {{-- EDIT --}}
                                            <button
                                                type="button"
                                                class="route-action edit edit-route-btn"
                                                title="Edit Route"

                                                data-id="{{ $route->id }}"
                                                data-route-code="{{ $route->route_code }}"
                                                data-route-name="{{ $route->route_name }}"
                                                data-origin="{{ $route->origin }}"
                                                data-destination="{{ $route->destination }}"
                                                data-distance="{{ $route->distance_km }}"
                                                data-time="{{ $route->estimated_time_minutes }}"
                                                data-status="{{ $route->status }}"
                                                data-update-url="{{ route(
                                                    'operation.routes.update',
                                                    $route->id
                                                ) }}"
                                                data-stops="{{ $stopsJson }}"
                                            >
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>


                                            {{-- DELETE --}}
                                            <form
                                                id="deleteRouteForm-{{ $route->id }}"
                                                action="{{ route(
                                                    'operation.routes.destroy',
                                                    $route->id
                                                ) }}"
                                                method="POST"
                                                class="route-delete-form"
                                            >

                                                @csrf
                                                @method('DELETE')


                                                <button
                                                    type="button"
                                                    class="route-action delete open-delete-route-modal"
                                                    title="Delete Route"

                                                    data-form-id="deleteRouteForm-{{ $route->id }}"
                                                    data-route-code="{{ $route->route_code }}"
                                                    data-route-name="{{ $route->route_name }}"
                                                >
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>


                            @empty

                                <x-ui.empty-row
                                    colspan="9"
                                    message="No route records found."
                                />

                            @endforelse

                        </tbody>

                    </table>

                </div>


                {{-- =================================================
                    PAGINATION
                ================================================== --}}
                @if(
                    $routes instanceof
                    \Illuminate\Pagination\LengthAwarePaginator
                )

                    <x-ui.table-footer
                        :items="$routes"
                    />

                @endif

            </section>

        </main>

    </div>


    {{-- =========================================================
        ADD / EDIT ROUTE
        GLOBAL FORM COMPONENT
    ========================================================== --}}
    <x-ui.form-modal
        id="routeModal"

        title="New Route"
        title-id="routeModalTitle"

        description="Enter the route information and shuttle stops."

        icon="fa-map-location-dot"
        size="large"

        form-id="routeForm"

        :action="route('operation.routes.store')"

        submit-text="Save Route"
        submit-text-id="saveRouteText"
        submit-id="saveRouteBtn"
        submit-icon="fa-floppy-disk"

        cancel-text="Cancel"
        cancel-id="cancelRouteModal"
        close-id="closeRouteModal"

        close-data-attribute="data-close-route-modal"
    >

        {{-- =====================================================
            PUT METHOD USED WHEN EDITING
        ====================================================== --}}
        <input
            type="hidden"
            name="_method"
            id="routeFormMethod"
            value="POST"
            disabled
        >


        {{-- =====================================================
            ROUTE FIELDS
        ====================================================== --}}
        <div class="ui-form-grid">

            {{-- ROUTE ID --}}
            <x-ui.form-field
                label="Route ID"
                name="display_route_code"
                id="routeCode"

                :value="$nextRouteCode ?? 'R-01'"

                icon="fa-hashtag"

                readonly
                full
            />


            {{-- ROUTE NAME --}}
            <x-ui.form-field
                label="Route Name"
                name="route_name"
                id="routeName"

                placeholder="Example: Route E - Lipa"

                icon="fa-route"

                required
                full
            />


            {{-- ORIGIN --}}
            <x-ui.form-field
                label="Origin"
                name="origin"
                id="routeOrigin"

                placeholder="Enter origin"

                icon="fa-location-dot"

                required
            />


            {{-- DESTINATION --}}
            <x-ui.form-field
                label="Destination"
                name="destination"
                id="routeDestination"

                placeholder="Enter destination"

                icon="fa-location-crosshairs"

                required
            />


            {{-- DISTANCE --}}
            <x-ui.form-field
                label="Distance"
                name="distance_km"
                id="routeDistance"

                type="number"

                placeholder="0"

                icon="fa-ruler-combined"
                unit="KM"

                min="0"
                step="0.1"
            />


            {{-- ESTIMATED TIME --}}
            <x-ui.form-field
                label="Estimated Travel Time"
                name="estimated_time_minutes"
                id="routeTime"

                type="number"

                placeholder="0"

                icon="fa-clock"
                unit="min"

                min="1"
            />


            {{-- STATUS --}}
            <x-ui.form-select
                label="Status"
                name="status"
                id="routeStatus"

                icon="fa-circle-check"

                :options="[
                    'Active' => 'Active',
                    'Inactive' => 'Inactive',
                ]"

                selected="Active"

                required
                full
            />

        </div>


        {{-- =====================================================
            SHUTTLE STOPS
        ====================================================== --}}
        <x-ui.form-section
            title="Shuttle Stops"
            subtitle="Add intermediate stops between the origin and destination."
            icon="fa-bus-simple"
        >

            <x-slot:action>

                <button
                    type="button"
                    class="ui-btn-small"
                    id="addRouteStop"
                >
                    <i class="fa-solid fa-plus"></i>

                    Add Stop
                </button>

            </x-slot:action>


            <div
                class="ui-form-repeater"
                id="routeStopList"
            >

                @if(old('stops'))

                    @foreach(old('stops') as $index => $stop)

                        <div class="ui-form-repeater-row route-stop-item">

                            <div class="ui-form-repeater-number route-stop-number">
                                {{ $index + 1 }}
                            </div>


                            <input
                                type="text"
                                name="stops[]"
                                value="{{ $stop }}"
                                placeholder="Enter stop name"
                            >


                            <button
                                type="button"
                                class="
                                    ui-form-repeater-remove
                                    remove-route-stop
                                "
                                title="Remove Stop"
                            >
                                <i class="fa-solid fa-trash"></i>
                            </button>

                        </div>

                    @endforeach


                @else

                    <div class="ui-form-repeater-row route-stop-item">

                        <div class="ui-form-repeater-number route-stop-number">
                            1
                        </div>


                        <input
                            type="text"
                            name="stops[]"
                            placeholder="Enter stop name"
                        >


                        <button
                            type="button"
                            class="
                                ui-form-repeater-remove
                                remove-route-stop
                            "
                            title="Remove Stop"
                        >
                            <i class="fa-solid fa-trash"></i>
                        </button>

                    </div>

                @endif

            </div>

        </x-ui.form-section>

        <x-ui.form-section
            title="Route Map Preview"
            subtitle="Select a processed GPS trip to preview its recorded origin and destination."
            icon="fa-map-location-dot"
        >
            <div class="route-form-map-toolbar">
                <div class="route-form-map-field">
                    <label for="routeFormGpsTripSelect">GPS Trip Record</label>
                    <select id="routeFormGpsTripSelect">
                        <option value="">Select a GPS trip</option>
                    </select>
                </div>

                <button
                    type="button"
                    class="route-form-fit-map-btn"
                    id="fitRouteFormGpsMap"
                    disabled
                >
                    <i class="fa-solid fa-expand"></i>
                    Fit Route
                </button>
            </div>

            <div
                class="route-form-map-message"
                id="routeFormGpsMapMessage"
                role="status"
            >
                Select a processed GPS trip to display it on the map.
            </div>

            <div
                class="route-form-gps-map"
                id="routeFormGpsMap"
                aria-label="Route form GPS map preview"
            ></div>

            <p class="route-form-map-note">
                The line connects the GPS record's beginning and ending coordinates.
            </p>
        </x-ui.form-section>

    </x-ui.form-modal>


    {{-- =========================================================
        VIEW ROUTE MODAL
    ========================================================== --}}
    {{-- =========================================================
    VIEW ROUTE MODAL
========================================================= --}}
<div
    class="route-modal-overlay"
    id="routeDetailsModal"
    aria-hidden="true"
>
    <section
        class="route-modal route-details-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="routeDetailsTitle"
    >
        {{-- Modal Header --}}
        <header class="route-details-header">
            <div class="route-details-title-group">
                <div class="route-details-header-icon">
                    <i class="fa-solid fa-route"></i>
                </div>

                <div>
                    <h2 id="routeDetailsTitle">
                        Route Details
                    </h2>

                    <p>
                        Route information and shuttle stop sequence.
                    </p>
                </div>
            </div>

            <button
                type="button"
                class="route-modal-close"
                data-close-route-details
                aria-label="Close route details"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        {{-- Modal Body --}}
        <div class="route-details-body">
            <section class="route-details-grid">
                {{-- Route ID --}}
                <div class="route-detail-field">
                    <span>Route ID</span>

                    <strong id="viewRouteCode">
                        —
                    </strong>
                </div>

                {{-- Status --}}
                <div class="route-detail-field">
                    <span>Status</span>

                    <div
                        class="route-detail-value route-status-value"
                        id="viewRouteStatus"
                    >
                        —
                    </div>
                </div>

                {{-- Route Name --}}
                <div class="route-detail-field full">
                    <span>Route Name</span>

                    <strong id="viewRouteName">
                        —
                    </strong>
                </div>

                {{-- Origin --}}
                <div class="route-detail-field">
                    <span>Origin</span>

                    <strong id="viewRouteOrigin">
                        —
                    </strong>
                </div>

                {{-- Destination --}}
                <div class="route-detail-field">
                    <span>Destination</span>

                    <strong id="viewRouteDestination">
                        —
                    </strong>
                </div>

                {{-- Distance --}}
                <div class="route-detail-field">
                    <span>Distance</span>

                    <strong id="viewRouteDistance">
                        —
                    </strong>
                </div>

                {{-- Estimated Time --}}
                <div class="route-detail-field">
                    <span>Estimated Time</span>

                    <strong id="viewRouteTime">
                        —
                    </strong>
                </div>
            </section>


            {{-- =================================================
                GPS TRIP MAP
            ================================================== --}}
            <section class="gps-map-section">
                <div class="gps-map-heading">
                    <div>
                        <h3>GPS Trip Map</h3>
                        <p>
                            View processed GPS trip records and their
                            recorded origin and destination coordinates.
                        </p>
                    </div>

                    <span
                        class="gps-record-count"
                        id="gpsRecordCount"
                    >
                        {{ $gpsTripRecords->count() }} GPS Records
                    </span>
                </div>

                <div class="gps-map-toolbar">
                    <div class="gps-map-field">
                        <label for="gpsTripSelect">
                            GPS Trip Record
                        </label>

                        <select id="gpsTripSelect">
                            <option value="">
                                Select a GPS trip
                            </option>
                        </select>
                    </div>

                    <button
                        type="button"
                        class="gps-fit-map-btn"
                        id="fitGpsMap"
                        disabled
                    >
                        <i class="fa-solid fa-expand"></i>
                        Fit Route
                    </button>
                </div>

                <div
                    class="gps-map-message"
                    id="gpsMapMessage"
                    role="status"
                >
                    Select a processed GPS trip to display it on the map.
                </div>

                <div
                    class="gps-trip-map"
                    id="gpsTripMap"
                    aria-label="GPS trip route map"
                ></div>

                <div
                    class="gps-trip-details"
                    id="gpsTripDetails"
                    hidden
                >
                    <article class="gps-detail-card">
                        <span>Bus Number</span>
                        <strong id="gpsDetailBus">—</strong>
                    </article>

                    <article class="gps-detail-card">
                        <span>Route Grouping</span>
                        <strong id="gpsDetailGrouping">—</strong>
                    </article>

                    <article class="gps-detail-card">
                        <span>Beginning</span>
                        <strong id="gpsDetailBeginning">—</strong>
                    </article>

                    <article class="gps-detail-card">
                        <span>Ending</span>
                        <strong id="gpsDetailEnding">—</strong>
                    </article>

                    <article class="gps-detail-card">
                        <span>Initial Location</span>
                        <strong id="gpsDetailOrigin">—</strong>
                    </article>

                    <article class="gps-detail-card">
                        <span>Final Location</span>
                        <strong id="gpsDetailDestination">—</strong>
                    </article>

                    <article class="gps-detail-card">
                        <span>Mileage</span>
                        <strong id="gpsDetailMileage">—</strong>
                    </article>

                    <article class="gps-detail-card">
                        <span>Total Time</span>
                        <strong id="gpsDetailDuration">—</strong>
                    </article>
                </div>

                <p class="gps-map-note">
                    The line connects the recorded beginning and ending
                    coordinates. It is a reference line, not the exact
                    road path traveled.
                </p>
            </section>

            {{-- Horizontal Route Path --}}
            <section class="route-path-section">
                <div class="route-path-heading">
                    <div>
                        <h3>Route Path</h3>

                        <p>
                            Origin, intermediate shuttle stops, and destination.
                        </p>
                    </div>

                    <span class="route-path-count" id="viewRouteStopCount">
                        0 Stops
                    </span>
                </div>

                <div class="horizontal-route-card">
                    <div
                        class="horizontal-route-path"
                        id="viewRoutePath"
                    >
                        {{-- Generated through routes-stops.js --}}
                    </div>
                </div>
            </section>
        </div>
    </section>
</div>




    {{-- =========================================================
        DELETE MODAL
    ========================================================== --}}
    <x-ui.action-buttom-modal
        mode="delete"
        id="deleteRouteModal"

        delete-title="Delete Route?"
        delete-message="Are you sure you want to delete"

        name-id="deleteRouteName"

        cancel-id="cancelDeleteRoute"
        confirm-id="confirmDeleteRoute"
    />


    {{-- =========================================================
        SUCCESS FEEDBACK
    ========================================================== --}}
    @if(session('success'))

        <x-ui.action-buttom-modal
            mode="feedback"

            feedback-type="success"

            :message="session('success')"

            button-text="Okay"
        />

    @endif


    {{-- GPS data consumed by routes-stops.js. --}}
    <script
        type="application/json"
        id="gpsTripRecordsData"
    >@json($gpsTripRecords)</script>

    <script
        type="application/json"
        id="gpsRouteSuggestionsData"
    >@json($gpsRouteSuggestions)</script>

    {{-- Browser autocomplete lists populated by routes-stops.js. --}}
    <datalist id="routeNameSuggestionList"></datalist>
    <datalist id="routeOriginSuggestionList"></datalist>
    <datalist id="routeDestinationSuggestionList"></datalist>

</x-layout.app>