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

    </x-ui.form-modal>


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

                        <span>
                            Route ID
                        </span>

                        <strong id="viewRouteCode">
                            —
                        </strong>

                    </div>


                    <div>

                        <span>
                            Status
                        </span>

                        <div id="viewRouteStatus">
                            —
                        </div>

                    </div>


                    <div class="full">

                        <span>
                            Route Name
                        </span>

                        <strong id="viewRouteName">
                            —
                        </strong>

                    </div>


                    <div>

                        <span>
                            Origin
                        </span>

                        <strong id="viewRouteOrigin">
                            —
                        </strong>

                    </div>


                    <div>

                        <span>
                            Destination
                        </span>

                        <strong id="viewRouteDestination">
                            —
                        </strong>

                    </div>


                    <div>

                        <span>
                            Distance
                        </span>

                        <strong id="viewRouteDistance">
                            —
                        </strong>

                    </div>


                    <div>

                        <span>
                            Estimated Time
                        </span>

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

</x-layout.app>