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

    @php
        $routeUser = auth()->user();

        $isOperationHead =
            $routeUser
            && strtolower(trim((string) $routeUser->department)) === 'operation'
            && strtolower(trim((string) $routeUser->role)) === 'head';

        $canManageRoutes =
            $isOperationHead
            || (
                $routeUser?->hasSystemPermission(
                    'operation',
                    'edit'
                ) ?? false
            );
    @endphp

    {{-- Validation errors are shown by the shared system toast and inline field feedback. --}}

    <div class="app">

        {{-- =====================================================
            SIDEBAR
        ====================================================== --}}
    <x-layout.sidebar department="Operation" />

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
        small="Intermediate stops in existing routes"
        icon="fa-location-dot"
        color="purple"
    />
</section>


            {{-- =====================================================
                ROUTES TABLE
            ====================================================== --}}
            <section data-ajax-region="records" class="table-card routes-card">

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
                    action="{{ route('operation.routes', [], false) }}"
                    class="toolbar routes-toolbar"
                    data-server-filter="true"
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


                    @if($canManageRoutes)
                                            <button
                        type="button"
                        class="new-route-btn"
                        id="openRouteModal"
                    >
                        <i class="fa-solid fa-plus"></i>

                        New Route
                    </button>
                    @endif

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
                                        ->map(fn ($stop) => [
                                            'name' => $stop->stop_name,
                                            'address' => $stop->address,
                                            'latitude' => $stop->latitude,
                                            'longitude' => $stop->longitude,
                                            'source' => $stop->location_source,
                                        ])
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
                                                data-origin-address="{{ $route->origin_address }}"
                                                data-origin-latitude="{{ $route->origin_latitude }}"
                                                data-origin-longitude="{{ $route->origin_longitude }}"
                                                data-origin-source="{{ $route->origin_source }}"
                                                data-destination-address="{{ $route->destination_address }}"
                                                data-destination-latitude="{{ $route->destination_latitude }}"
                                                data-destination-longitude="{{ $route->destination_longitude }}"
                                                data-destination-source="{{ $route->destination_source }}"
                                                data-route-geometry='@json($route->route_geometry)'
                                            data-distance="{{ $route->distance_km }}"
                                            data-time="{{ $route->estimated_time_minutes }}"
                                            data-calculated-distance="{{ $route->calculated_distance_km }}"
                                            data-calculated-time="{{ $route->calculated_time_minutes }}"
                                            data-distance-source="{{ $route->distance_source }}"
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
                                            @php
                                                $durationMinutes = (int) $route->estimated_time_minutes;
                                                $durationHours = intdiv($durationMinutes, 60);
                                                $remainingMinutes = $durationMinutes % 60;

                                                if ($durationHours > 0) {
                                                    $durationLabel = $durationHours
                                                        . ' '
                                                        . ($durationHours === 1 ? 'hr' : 'hrs');

                                                    if ($remainingMinutes > 0) {
                                                        $durationLabel .= ' '
                                                            . str_pad(
                                                                (string) $remainingMinutes,
                                                                2,
                                                                '0',
                                                                STR_PAD_LEFT
                                                            )
                                                            . ' min';
                                                    }
                                                } else {
                                                    $durationLabel = $remainingMinutes . ' min';
                                                }
                                            @endphp

                                            {{ $durationLabel }}
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
                                                data-origin-address="{{ $route->origin_address }}"
                                                data-origin-latitude="{{ $route->origin_latitude }}"
                                                data-origin-longitude="{{ $route->origin_longitude }}"
                                                data-origin-source="{{ $route->origin_source }}"
                                                data-destination-address="{{ $route->destination_address }}"
                                                data-destination-latitude="{{ $route->destination_latitude }}"
                                                data-destination-longitude="{{ $route->destination_longitude }}"
                                                data-destination-source="{{ $route->destination_source }}"
                                                data-route-geometry='@json($route->route_geometry)'
                                                data-distance="{{ $route->distance_km }}"
                                                data-time="{{ $route->estimated_time_minutes }}"
                                                data-status="{{ $route->status }}"
                                                data-stops="{{ $stopsJson }}"
                                            >
                                                <i class="fa-solid fa-eye"></i>
                                            </button>


                                            @if($canManageRoutes)
                                            {{-- Used routes must keep their historical definition. --}}
                                            @if($route->trip_schedules_count > 0)
                                                <button type="button" class="route-action edit gct-action-unavailable"
                                                    data-has-schedules="1"
                                                    disabled aria-disabled="true" title="Unavailable: route already used by a trip"
                                                    aria-label="Edit unavailable: route already used by a trip">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <button type="button" class="route-action delete gct-action-unavailable"
                                                    disabled aria-disabled="true" title="Unavailable: route already used by a trip"
                                                    aria-label="Delete unavailable: route already used by a trip">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            @else
                                                <button
                                                    type="button"
                                                    class="route-action edit edit-route-btn"
                                                    title="Edit Route"
                                                    data-id="{{ $route->id }}"
                                                    data-route-code="{{ $route->route_code }}"
                                                    data-route-name="{{ $route->route_name }}"
                                                    data-origin="{{ $route->origin }}"
                                                    data-destination="{{ $route->destination }}"
                                                    data-origin-address="{{ $route->origin_address }}"
                                                    data-origin-latitude="{{ $route->origin_latitude }}"
                                                    data-origin-longitude="{{ $route->origin_longitude }}"
                                                    data-origin-source="{{ $route->origin_source }}"
                                                    data-destination-address="{{ $route->destination_address }}"
                                                    data-destination-latitude="{{ $route->destination_latitude }}"
                                                    data-destination-longitude="{{ $route->destination_longitude }}"
                                                    data-destination-source="{{ $route->destination_source }}"
                                                    data-route-geometry='@json($route->route_geometry)'
                                                    data-distance="{{ $route->distance_km }}"
                                                    data-time="{{ $route->estimated_time_minutes }}"
                                                    data-status="{{ $route->status }}"
                                                    data-has-schedules="0"
                                                    data-update-url="{{ route('operation.routes.update', $route->id, false) }}"
                                                    data-stops="{{ $stopsJson }}"
                                                >
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <form id="deleteRouteForm-{{ $route->id }}"
                                                    action="{{ route('operation.routes.destroy', $route->id, false) }}"
                                                    method="POST" class="route-delete-form">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="route-action delete open-delete-route-modal"
                                                        title="Delete Route"
                                                        data-form-id="deleteRouteForm-{{ $route->id }}"
                                                        data-route-code="{{ $route->route_code }}"
                                                        data-route-name="{{ $route->route_name }}">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            @endif

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

        description="Update route information, shuttle stops, and map preview."

        icon="fa-map-location-dot"
        size="wide"
        class="route-editor-overlay"

        form-id="routeForm"

        :action="route('operation.routes.store', [], false)"

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
        <x-ui.form-section
            title="Route Information"
            subtitle="Enter the basic details for this route."
            icon="fa-map-location-dot"
            class="route-editor-section route-information-section"
        >
        <div
            class="route-history-lock-note"
            id="routeHistoricalLockNotice"
            hidden
        >
            <i class="fa-solid fa-lock"></i>
            <div>
                <strong>Historical route details are protected.</strong>
                <span>This route is already used by trip schedules. Create a new route variant to change its name, locations, stops, distance, or map path. Only Status can be changed here.</span>
            </div>
        </div>

        <div class="ui-form-grid">

            {{-- ROUTE ID --}}
            <x-ui.form-field
                label="Route ID"
                name="display_route_code"
                id="routeCode"

                :value="$nextRouteCode ?? 'R-01'"

                icon="fa-hashtag"

                readonly
            />


            {{-- ROUTE NAME --}}
            <x-ui.form-field
                label="Route Name"
                name="route_name"
                id="routeName"

                placeholder="Example: Route E - Lipa"

                icon="fa-route"

                required
            />


            <p class="route-name-guidance">
                Route Name is auto-filled from Origin and Destination, but you can edit it for a distinct route variant.
            </p>

            {{-- ORIGIN --}}
            <x-ui.form-field
                label="Origin"
                name="origin"
                id="routeOrigin"

                placeholder="Enter origin"

                icon="fa-location-dot"
                spellcheck="false"

                required
            />


            {{-- DESTINATION --}}
            <x-ui.form-field
                label="Destination"
                name="destination"
                id="routeDestination"

                placeholder="Enter destination"

                icon="fa-location-crosshairs"
                spellcheck="false"

                required
            />

            <input type="hidden" name="editing_route_id" id="routeEditingId">

            <input type="hidden" name="origin_address" id="routeOriginAddress">
            <input type="hidden" name="origin_latitude" id="routeOriginLatitude">
            <input type="hidden" name="origin_longitude" id="routeOriginLongitude">
            <input type="hidden" name="origin_source" id="routeOriginSource">

            <input type="hidden" name="destination_address" id="routeDestinationAddress">
            <input type="hidden" name="destination_latitude" id="routeDestinationLatitude">
            <input type="hidden" name="destination_longitude" id="routeDestinationLongitude">
            <input type="hidden" name="destination_source" id="routeDestinationSource">

            <input type="hidden" name="calculated_distance_km" id="routeCalculatedDistance">
            <input type="hidden" name="calculated_time_minutes" id="routeCalculatedTime">
            <input type="hidden" name="distance_source" id="routeDistanceSource">
            <input type="hidden" name="distance_is_manual" id="routeDistanceManual" value="0">
            <input type="hidden" name="time_is_manual" id="routeTimeManual" value="0">
            <input type="hidden" name="route_geometry" id="routeGeometry">


            {{-- DISTANCE --}}
            <x-ui.form-field
                label="Distance (Auto)"
                name="distance_km"
                id="routeDistance"

                type="number"

                placeholder="Auto-calculated"

                icon="fa-ruler-combined"
                unit="KM"

                min="0"
                step="0.01"
                readonly
            />


            {{-- ESTIMATED TIME --}}
            <x-ui.form-field
                label="Estimated Travel Time (Auto)"
                name="estimated_time_display"
                id="routeTime"

                type="text"

                placeholder="Auto-calculated"

                icon="fa-clock"

                readonly
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
        </x-ui.form-section>


        {{-- =====================================================
            SHUTTLE STOPS
        ====================================================== --}}
        <x-ui.form-section
            title="Shuttle Stops"
            subtitle="Add, edit, or reorder the intermediate stops for this route."
            icon="fa-bus-simple"
            class="route-editor-section route-stops-section"
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


                            <div class="route-stop-location-field">
                                <input
                                    type="text"
                                    name="stops[]"
                                    value="{{ $stop }}"
                                    placeholder="Search or enter a stop"
                                    autocomplete="off"
                                    spellcheck="false"
                                >
                                <input type="hidden" name="stop_addresses[]" value="{{ old('stop_addresses.' . $index) }}">
                                <input type="hidden" name="stop_latitudes[]" value="{{ old('stop_latitudes.' . $index) }}">
                                <input type="hidden" name="stop_longitudes[]" value="{{ old('stop_longitudes.' . $index) }}">
                                <input type="hidden" name="stop_sources[]" value="{{ old('stop_sources.' . $index) }}">
                            </div>


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


                        <div class="route-stop-location-field">
                            <input
                                type="text"
                                name="stops[]"
                                placeholder="Search or enter a stop"
                                autocomplete="off"
                            >
                            <input type="hidden" name="stop_addresses[]">
                            <input type="hidden" name="stop_latitudes[]">
                            <input type="hidden" name="stop_longitudes[]">
                            <input type="hidden" name="stop_sources[]">
                        </div>


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
            subtitle="Visualize the route and its confirmed stops on the map."
            icon="fa-map-location-dot"
            class="route-editor-section route-map-section"
        >
            <div class="route-form-map-toolbar">
                <div class="route-map-active-field" id="routeMapActiveField">
                    <i class="fa-solid fa-location-crosshairs"></i>
                    Click an Origin, Stop, or Destination field first.
                </div>

                <button type="button" class="route-form-map-tool-btn" id="pinActiveLocationBtn" disabled>
                    <i class="fa-solid fa-thumbtack"></i>
                    Pin Active Field
                </button>

                <button type="button" class="route-form-map-tool-btn" id="recalculateRouteBtn" disabled>
                    <i class="fa-solid fa-rotate"></i>
                    Recalculate
                </button>

                <button type="button" class="route-form-fit-map-btn" id="fitRouteFormGpsMap" disabled>
                    <i class="fa-solid fa-expand"></i>
                    Fit Route
                </button>
            </div>

            <div class="route-form-map-message" id="routeFormGpsMapMessage" role="status" aria-live="polite">
                Select confirmed locations to build the road route.
            </div>

            <div class="route-calculation-summary" id="routeCalculationSummary" hidden>
                <span id="routeCalculatedDistanceText">—</span>
                <span id="routeCalculatedTimeText">—</span>
                <button type="button" id="useCalculatedValuesBtn">Use calculated values</button>
            </div>

            <div class="route-form-gps-map" id="routeFormGpsMap" aria-label="Interactive route map preview"></div>

            <p class="route-form-map-note">
                Search results use OpenStreetMap data. The road path uses OSRM; travel time is a conservative shuttle ETA and does not include live traffic.
            </p>
        </x-ui.form-section>

        <div class="route-editor-summary" aria-label="Route summary">
            <div class="route-editor-summary-item">
                <span class="route-editor-summary-icon"><i class="fa-solid fa-route"></i></span>
                <div>
                    <strong id="routeEditorSummaryDistance">—</strong>
                    <small>Total Distance</small>
                </div>
            </div>

            <div class="route-editor-summary-item">
                <span class="route-editor-summary-icon"><i class="fa-regular fa-clock"></i></span>
                <div>
                    <strong id="routeEditorSummaryTime">—</strong>
                    <small>Estimated Time</small>
                </div>
            </div>

            <div class="route-editor-summary-item">
                <span class="route-editor-summary-icon"><i class="fa-solid fa-location-dot"></i></span>
                <div>
                    <strong id="routeEditorSummaryStops">0</strong>
                    <small>Total Stops</small>
                </div>
            </div>

            <div class="route-editor-summary-item">
                <span class="route-editor-summary-icon"><i class="fa-solid fa-hashtag"></i></span>
                <div>
                    <strong id="routeEditorSummaryCode">—</strong>
                    <small>Route ID</small>
                </div>
            </div>
        </div>

    </x-ui.form-modal>


    {{-- =========================================================
        VIEW ROUTE MODAL
    ========================================================== --}}
    {{-- =========================================================
    VIEW ROUTE MODAL
========================================================= --}}
<div
    class="route-modal-overlay route-view-overlay"
    id="routeDetailsModal"
    aria-hidden="true"
>
    <section
        class="route-modal route-details-modal route-view-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="routeDetailsTitle"
    >
        <header class="route-details-header route-view-header">
            <div class="route-details-title-group">
                <div class="route-details-header-icon route-view-header-icon">
                    <i class="fa-solid fa-map-location-dot"></i>
                </div>

                <div>
                    <span class="route-view-eyebrow">Route Management</span>
                    <h2 id="routeDetailsTitle">Route Details</h2>
                    <p>Review route information, map preview, and shuttle stop sequence.</p>
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

        <div class="route-details-body route-view-body">
            <section class="route-view-card route-view-information">
                <div class="route-view-card-header">
                    <div class="route-view-card-title">
                        <span class="route-view-section-icon">
                            <i class="fa-solid fa-route"></i>
                        </span>
                        <div>
                            <h3>Route Information</h3>
                            <p>Basic route details and current operating status.</p>
                        </div>
                    </div>
                </div>

                <div class="route-details-grid route-view-grid">
                    <div class="route-detail-field route-view-field">
                        <span><i class="fa-solid fa-hashtag"></i> Route ID</span>
                        <strong id="viewRouteCode">—</strong>
                    </div>

                    <div class="route-detail-field route-view-field">
                        <span><i class="fa-solid fa-circle-check"></i> Status</span>
                        <div
                            class="route-detail-value route-status-value"
                            id="viewRouteStatus"
                        >
                            —
                        </div>
                    </div>

                    <div class="route-detail-field route-view-field full">
                        <span><i class="fa-solid fa-signature"></i> Route Name</span>
                        <strong id="viewRouteName">—</strong>
                    </div>

                    <div class="route-detail-field route-view-field">
                        <span><i class="fa-solid fa-location-dot"></i> Origin</span>
                        <strong id="viewRouteOrigin">—</strong>
                    </div>

                    <div class="route-detail-field route-view-field">
                        <span><i class="fa-solid fa-location-crosshairs"></i> Destination</span>
                        <strong id="viewRouteDestination">—</strong>
                    </div>

                    <div class="route-detail-field route-view-field">
                        <span><i class="fa-solid fa-ruler-combined"></i> Distance</span>
                        <strong id="viewRouteDistance">—</strong>
                    </div>

                    <div class="route-detail-field route-view-field">
                        <span><i class="fa-regular fa-clock"></i> Estimated Time</span>
                        <strong id="viewRouteTime">—</strong>
                    </div>
                </div>
            </section>

            <section class="route-view-card route-view-map-card gps-map-section">
                <div class="route-view-card-header">
                    <div class="route-view-card-title">
                        <span class="route-view-section-icon">
                            <i class="fa-solid fa-map-location-dot"></i>
                        </span>
                        <div>
                            <h3>Route Map Preview</h3>
                            <p>View the saved road route and confirmed route points.</p>
                        </div>
                    </div>

                </div>

                <div class="route-view-map-toolbar">
                    @if($gpsTripRecords->count() > 0)
                        <div class="gps-map-field">
                            <label for="gpsTripSelect">
                                Optional GPS Trip Record
                            </label>

                            <select id="gpsTripSelect">
                                <option value="">
                                    Show saved route
                                </option>
                            </select>
                        </div>
                    @else
                        <select id="gpsTripSelect" hidden>
                            <option value=""></option>
                        </select>

                        <div class="route-view-map-source">
                            <i class="fa-solid fa-road"></i>
                            Saved OSRM road route
                        </div>
                    @endif

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
                    Loading the saved route map…
                </div>

                <div
                    class="gps-trip-map route-view-map"
                    id="gpsTripMap"
                    aria-label="Saved route map preview"
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

                <p class="gps-map-note route-view-map-note">
                    Saved route geometry uses OSRM/OpenStreetMap. The displayed travel time is a conservative shuttle ETA and does not include live traffic.
                </p>
            </section>

            <section class="route-view-card route-path-section route-view-path-card">
                <div class="route-view-card-header">
                    <div class="route-view-card-title">
                        <span class="route-view-section-icon">
                            <i class="fa-solid fa-route"></i>
                        </span>
                        <div>
                            <h3>Route Path</h3>
                            <p>Origin, intermediate shuttle stops, and destination in sequence.</p>
                        </div>
                    </div>

                    <span class="route-path-count" id="viewRouteStopCount">
                        0 Stops
                    </span>
                </div>

                <div class="horizontal-route-card route-view-path-scroll">
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


{{-- =========================================================
    ROUTE MAP DATA CONSUMED BY routes-stops.js
========================================================== --}}

@php
    $routeMapConfig = [
        'searchUrl' => route('operation.routes.location-search', [], false),

        'reverseUrl' => route('operation.routes.reverse-location', [], false),

        'routingUrl' => route('operation.routes.calculate', [], false),

        'csrfToken' => csrf_token(),
    ];
@endphp

<script
    type="application/json"
    id="gpsTripRecordsData"
>@json($gpsTripRecords ?? [])</script>

<script
    type="application/json"
    id="gpsLocationSuggestionsData"
>@json($gpsLocationSuggestions ?? [])</script>

<script
    type="application/json"
    id="routeMapConfigData"
>@json($routeMapConfig)</script>

</x-layout.app>