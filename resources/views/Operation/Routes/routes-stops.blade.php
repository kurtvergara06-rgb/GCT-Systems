<x-layout.app
    title="FROMS - Routes"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
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

                <h2>Form Error</h2>

                <p>
                    Please check the route information.
                </p>

                <ul class="form-error-list">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
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

            {{-- =====================================================
                TOPBAR
            ====================================================== --}}
            <x-layout.topbar
                title="Routes"
                subtitle="Manage shuttle routes, destinations, stops, distance, and estimated travel time"
                notification-count="4"
            />


            {{-- =====================================================
                SUMMARY CARDS
            ====================================================== --}}
            <section class="stats-grid routes-summary-grid">

                <x-ui.summary-card
                    label="Total Routes"
                    value="{{ $totalRoutes ?? 0 }}"
                    small="Registered shuttle routes"
                    icon="fa-route"
                    color="blue"
                />

                <x-ui.summary-card
                    label="Active Routes"
                    value="{{ $activeRoutes ?? 0 }}"
                    small="Currently available"
                    icon="fa-circle-check"
                    color="green"
                />

                <x-ui.summary-card
                    label="Total Stops"
                    value="{{ $totalStops ?? 0 }}"
                    small="Across all routes"
                    icon="fa-location-dot"
                    color="purple"
                />

                <x-ui.summary-card
                    label="Route Coverage"
                    value="{{ number_format((float) ($routeCoverage ?? 0), 1) }} KM"
                    small="Combined route distance"
                    icon="fa-road"
                    color="yellow"
                />

            </section>


            {{-- =====================================================
                ROUTES TABLE
            ====================================================== --}}
            <section class="table-card routes-card">

                <div class="section-header">

                    <div>
                        <h2>Route Records</h2>

                        <p>
                            Routes defined here will be available for trip,
                            bus, and driver scheduling.
                        </p>
                    </div>

                </div>


                {{-- =================================================
                    SEARCH / FILTER / NEW ROUTE
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

                        <label for="routeStatusFilter">

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
                                $stopsJson = $route->stops
                                    ->pluck('stop_name')
                                    ->values()
                                    ->toJson();
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

                                        @php
                                            $statusClass = strtolower($route->status);
                                        @endphp

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
                @if($routes instanceof \Illuminate\Pagination\LengthAwarePaginator)

                    <x-ui.table-footer
                        :items="$routes"
                    />

                @endif

            </section>

        </main>

    </div>


    {{-- =========================================================
        ADD / EDIT ROUTE MODAL
    ========================================================== --}}
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

                    <p id="routeModalDescription">
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
                id="routeForm"
                action="{{ route('operation.routes.store') }}"
                method="POST"
                class="route-form"
            >

                @csrf

                {{-- Changes between POST and PUT through JS --}}
                <input
                    type="hidden"
                    name="_method"
                    id="routeFormMethod"
                    value="POST"
                    disabled
                >


                <div class="route-form-grid">

                    {{-- ROUTE CODE --}}
                    <div
                        class="route-form-group full"
                        id="routeCodeGroup"
                    >

                        <label for="routeCode">
                            Route ID
                        </label>

                        <input
                            type="text"
                            id="routeCode"
                            value="{{ $nextRouteCode ?? 'R-01' }}"
                            readonly
                        >

                    </div>


                    {{-- ROUTE NAME --}}
                    <div class="route-form-group full">

                        <label for="routeName">
                            Route Name
                        </label>

                        <input
                            type="text"
                            name="route_name"
                            id="routeName"
                            value="{{ old('route_name') }}"
                            placeholder="Example: Route E - Lipa"
                            required
                        >

                    </div>


                    {{-- ORIGIN --}}
                    <div class="route-form-group">

                        <label for="routeOrigin">
                            Origin
                        </label>

                        <input
                            type="text"
                            name="origin"
                            id="routeOrigin"
                            value="{{ old('origin') }}"
                            placeholder="Enter origin"
                            required
                        >

                    </div>


                    {{-- DESTINATION --}}
                    <div class="route-form-group">

                        <label for="routeDestination">
                            Destination
                        </label>

                        <input
                            type="text"
                            name="destination"
                            id="routeDestination"
                            value="{{ old('destination') }}"
                            placeholder="Enter destination"
                            required
                        >

                    </div>


                    {{-- DISTANCE --}}
                    <div class="route-form-group">

                        <label for="routeDistance">
                            Distance
                        </label>

                        <div class="route-input-unit">

                            <input
                                type="number"
                                name="distance_km"
                                id="routeDistance"
                                value="{{ old('distance_km') }}"
                                step="0.1"
                                min="0"
                                placeholder="0"
                            >

                            <span>KM</span>

                        </div>

                    </div>


                    {{-- ESTIMATED TIME --}}
                    <div class="route-form-group">

                        <label for="routeTime">
                            Estimated Travel Time
                        </label>

                        <div class="route-input-unit">

                            <input
                                type="number"
                                name="estimated_time_minutes"
                                id="routeTime"
                                value="{{ old('estimated_time_minutes') }}"
                                min="1"
                                placeholder="0"
                            >

                            <span>min</span>

                        </div>

                    </div>


                    {{-- STATUS --}}
                    <div class="route-form-group full">

                        <label for="routeStatus">
                            Status
                        </label>

                        <select
                            name="status"
                            id="routeStatus"
                            required
                        >

                            <option
                                value="Active"
                                {{ old('status', 'Active') === 'Active' ? 'selected' : '' }}
                            >
                                Active
                            </option>

                            <option
                                value="Inactive"
                                {{ old('status') === 'Inactive' ? 'selected' : '' }}
                            >
                                Inactive
                            </option>

                        </select>

                    </div>

                </div>


                {{-- =================================================
                    STOPS
                ================================================== --}}
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

                        @if(old('stops'))

                            @foreach(old('stops') as $index => $stop)

                                <div class="route-stop-item">

                                    <div class="route-stop-number">
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
                                        class="remove-route-stop"
                                        title="Remove Stop"
                                    >
                                        <i class="fa-solid fa-trash"></i>
                                    </button>

                                </div>

                            @endforeach

                        @else

                            <div class="route-stop-item">

                                <div class="route-stop-number">
                                    1
                                </div>

                                <input
                                    type="text"
                                    name="stops[]"
                                    placeholder="Enter stop name"
                                >

                                <button
                                    type="button"
                                    class="remove-route-stop"
                                    title="Remove Stop"
                                >
                                    <i class="fa-solid fa-trash"></i>
                                </button>

                            </div>

                        @endif

                    </div>

                </div>


                {{-- =================================================
                    MODAL ACTIONS
                ================================================== --}}
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
                        id="saveRouteBtn"
                    >
                        <i class="fa-solid fa-floppy-disk"></i>

                        <span id="saveRouteText">
                            Save Route
                        </span>
                    </button>

                </div>

            </form>

        </div>

    </div>


    {{-- =========================================================
        VIEW ROUTE MODAL
    ========================================================== --}}
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

                        <strong id="viewRouteCode">
                            —
                        </strong>
                    </div>


                    <div>
                        <span>Status</span>

                        <div id="viewRouteStatus">
                            —
                        </div>
                    </div>


                    <div class="full">

                        <span>Route Name</span>

                        <strong id="viewRouteName">
                            —
                        </strong>

                    </div>


                    <div>

                        <span>Origin</span>

                        <strong id="viewRouteOrigin">
                            —
                        </strong>

                    </div>


                    <div>

                        <span>Destination</span>

                        <strong id="viewRouteDestination">
                            —
                        </strong>

                    </div>


                    <div>

                        <span>Distance</span>

                        <strong id="viewRouteDistance">
                            —
                        </strong>

                    </div>


                    <div>

                        <span>Estimated Time</span>

                        <strong id="viewRouteTime">
                            —
                        </strong>

                    </div>

                </section>


                {{-- ROUTE PATH --}}
                <section class="route-path">

                    <h3>
                        Route Path
                    </h3>

                    <div
                        class="route-path-list"
                        id="viewRoutePath"
                    >
                    </div>

                </section>

            </div>

        </div>

    </div>


    {{-- =========================================================
        DELETE CONFIRMATION
        USING YOUR REUSABLE COMPONENT
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

</x-layout.app>