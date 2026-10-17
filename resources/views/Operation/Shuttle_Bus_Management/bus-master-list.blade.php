<x-layout.app
    title="FROMS - Bus Master List"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Main-styles/form-components.css',
        'resources/css/Operation/Shuttle_Bus_Management/bus-master-list.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Operation/Shuttle_Bus_Management/bus-master-list.js',
    ]"
>

    @php
        $canEditOperation = auth()->user()?->hasSystemPermission('operation', 'edit') ?? false;
    @endphp

    <div class="app">

  <x-layout.sidebar department="Operation" />

        <main class="main bus-master-list-page">

            <x-layout.topbar
                title="Bus Master List"
                subtitle="Manage official bus records used by GPS, PMS Scheduling, and Job Orders"
                notification-count="6"
            />

            <section data-ajax-region="summary" class="stats-grid">
                <x-ui.summary-card
                    label="Total Buses"
                    value="{{ $totalBuses }}"
                    small="Registered buses"
                    icon="fa-bus"
                    color="blue"
                />

                <x-ui.summary-card
                    label="Active"
                    value="{{ $activeBuses }}"
                    small="Operational buses"
                    icon="fa-circle-check"
                    color="green"
                />

                <x-ui.summary-card
                    label="Under Maintenance"
                    value="{{ $underMaintenance }}"
                    small="Not available"
                    icon="fa-screwdriver-wrench"
                    color="red"
                />

                <x-ui.summary-card
                    label="Available Buses"
                    value="{{ $availableBuses }}"
                    small="Active without pending trip assignments"
                    icon="fa-bus-simple"
                    color="yellow"
                />
            </section>

            <section data-ajax-region="records" class="table-card">
                <div class="section-header">
                    <div>
                        <h2>Registered Buses</h2>
                        <p>
                            Bus status reflects current operational and maintenance records.
                        </p>
                    </div>
                    @if($canEditOperation)
                    <button
                        type="button"
                        id="openBusModal"
                        class="primary-btn"
                    >
                        <i class="fa-solid fa-plus"></i>
                        <span>Add Bus</span>
                    </button>
                    @endif
                </div>
                <div class="bus-record-controls">
                    <nav class="bus-fleet-tabs" aria-label="Bus record groups">
                        <a class="bus-fleet-tab {{ request('fleet_purpose', 'Regular') === 'Regular' ? 'is-active' : '' }}" href="{{ route('bus-master-list', ['fleet_purpose' => 'Regular']) }}"><i class="fa-solid fa-bus"></i> Active Buses</a>
                        <a class="bus-fleet-tab {{ request('fleet_purpose') === 'Reserve' ? 'is-active' : '' }}" href="{{ route('bus-master-list', ['fleet_purpose' => 'Reserve']) }}"><i class="fa-solid fa-shield-halved"></i> Reserve Buses</a>
                        <a class="bus-fleet-tab {{ request('fleet_purpose') === 'Inactive' ? 'is-active' : '' }}" href="{{ route('bus-master-list', ['fleet_purpose' => 'Inactive']) }}"><i class="fa-solid fa-clock-rotate-left"></i> Inactive Buses</a>
                    </nav>

                    <x-ui.table-toolbar
                        action="/bus-master-list"
                        form-id="busListFilters"
                        data-server-filter-navigation="true"
                        class="bus-compact-toolbar"
                        search-label="Search Buses"
                        search-placeholder="Search by Bus ID, plate, or model..."
                        search-help="Enter a Bus ID, plate number, or model."
                        filter-title="Filter Buses"
                        :filter-count="(int) (request()->filled('status') && request('status') !== 'All Status')"
                        sort-title="Sort Buses"
                        :sort-options="['bus_no' => 'Bus ID', 'plate_no' => 'Plate Number', 'bus_model' => 'Model', 'status' => 'Status']"
                        default-sort="bus_no"
                    >
                        <x-slot:hidden><input type="hidden" name="fleet_purpose" value="{{ in_array(request('fleet_purpose'), ['Regular', 'Reserve', 'Inactive'], true) ? request('fleet_purpose') : 'Regular' }}"></x-slot:hidden>
                        <x-slot:filters>
                            <x-ui.table-toolbar-field label="Status" name="status" default="All Status">
                                <option value="All Status" @selected(! request()->filled('status') || request('status') === 'All Status')>All Statuses</option>
                                @if(request('fleet_purpose') === 'Inactive')
                                    <option value="Inactive" @selected(request('status') === 'Inactive')>Inactive</option>
                                @else
                                    <option value="Active" @selected(request('status') === 'Active')>Active</option>
                                    <option value="Under Maintenance" @selected(request('status') === 'Under Maintenance')>Under Maintenance</option>
                                @endif
                            </x-ui.table-toolbar-field>
                        </x-slot:filters>
                    </x-ui.table-toolbar>
                </div>

                <div class="bus-filter-loading" data-server-filter-loading hidden>
                    <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>
                    Updating bus records...
                </div>

                <div class="table-wrap">
                    <table class="bus-table {{ request('fleet_purpose') === 'Reserve' ? 'bus-table--reserve' : '' }}">
                        <thead>
                            <tr>
                                <th>
                                    <div class="th-content">
                                        <span>Bus ID (Body Number)</span>
                                        <i class="fa-solid fa-up-down sort-icon" aria-hidden="true"></i>
                                    </div>
                                </th>
                                <th>
                                    <div class="th-content">
                                        <span>Plate Number</span>
                                        <i class="fa-solid fa-up-down sort-icon" aria-hidden="true"></i>
                                    </div>
                                </th>
                                <th>
                                    <div class="th-content">
                                        <span>Model</span>
                                        <i class="fa-solid fa-up-down sort-icon" aria-hidden="true"></i>
                                    </div>
                                </th>
                                <th>
                                    <div class="th-content">
                                        <span>Status</span>
                                        <i class="fa-solid fa-up-down sort-icon" aria-hidden="true"></i>
                                    </div>
                                </th>
                                @unless(request('fleet_purpose') === 'Reserve')
                                <th>Client Allocation</th>
                                <th>Authorized Drivers</th>
                                @endunless
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($buses as $bus)
                                @php
                                    $displayStatus = $bus->display_status ?? $bus->status;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="bus-id-cell">
                                            <div class="bus-icon-badge {{ $displayStatus === 'Active' ? 'is-active' : 'is-muted' }}" aria-hidden="true">
                                                <i class="fa-solid fa-bus"></i>
                                            </div>
                                            <span class="bus-id-text" title="{{ $bus->bus_no }}">{{ $bus->bus_no }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="bus-plate-text">{{ $bus->plate_no ?: '—' }}</span>
                                    </td>

                                    <td>
                                        <span class="bus-model-text" title="{{ $bus->bus_model ?: 'Not specified' }}">{{ $bus->bus_model ?: '—' }}</span>
                                    </td>

                                    <td>
                                        <x-ui.status-badge :status="$displayStatus" class="bus-status-pill" />
                                    </td>
                                    @unless(request('fleet_purpose') === 'Reserve')
                                    <td class="bus-allocation-cell">
                                        @if($bus->operationClients->isEmpty())
                                            <span class="bus-identity-empty">Not allocated</span>
                                        @else
                                            <div class="bus-table-disclosure bus-identity-summary">
                                                <span class="bus-identity-avatar bus-company-avatar" aria-hidden="true"><i class="fa-solid fa-building"></i></span>
                                                <span class="bus-identity-details">
                                                    <strong class="bus-disclosure-primary" title="{{ $bus->operationClients->first()->name }}">{{ $bus->operationClients->first()->name }}</strong>
                                                    <small>Client / Company</small>
                                                </span>
                                                @if($bus->operationClients->count() > 1)
                                                    <button type="button" class="bus-more-trigger" data-bus-more aria-label="Show {{ $bus->operationClients->count() - 1 }} more companies for bus {{ $bus->bus_no }}" aria-expanded="false">+{{ $bus->operationClients->count() - 1 }} more <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>
                                                    <div class="bus-more-source" hidden>
                                                        @foreach($bus->operationClients->skip(1) as $company)
                                                            <div class="bus-more-entry bus-identity-entry"><span class="bus-identity-avatar bus-company-avatar" aria-hidden="true"><i class="fa-solid fa-building"></i></span><span class="bus-identity-details"><strong title="{{ $company->name }}">{{ $company->name }}</strong><small>Client / Company</small></span></div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="bus-drivers-cell">
                                        @if($bus->authorizedDrivers->isEmpty())
                                            <span class="bus-identity-empty">Not assigned</span>
                                        @else
                                            <div class="bus-table-disclosure bus-identity-summary bus-driver-summary">
                                                <span class="bus-identity-avatar bus-driver-avatar" aria-hidden="true"><i class="fa-solid fa-user"></i></span>
                                                <span class="bus-identity-details bus-driver-details">
                                                    <strong class="bus-disclosure-primary" title="{{ $bus->authorizedDrivers->first()->driver_name }}">{{ $bus->authorizedDrivers->first()->driver_name }}</strong>
                                                    <small>Driver profile</small>
                                                </span>
                                                @if($bus->authorizedDrivers->count() > 1)
                                                    <button type="button" class="bus-more-trigger" data-bus-more aria-label="Show {{ $bus->authorizedDrivers->count() - 1 }} more drivers for bus {{ $bus->bus_no }}" aria-expanded="false">+{{ $bus->authorizedDrivers->count() - 1 }} more <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>
                                                    <div class="bus-more-source" hidden>
                                                        @foreach($bus->authorizedDrivers->skip(1) as $driver)
                                                            <div class="bus-more-entry bus-identity-entry"><span class="bus-identity-avatar bus-driver-avatar" aria-hidden="true"><i class="fa-solid fa-user"></i></span><span class="bus-identity-details"><strong title="{{ $driver->driver_name }}">{{ $driver->driver_name }}</strong><small>Driver profile</small></span></div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    @endunless
                                    <td class="bus-actions-cell">
                                        <div class="actions">
                                            <button type="button" class="action-btn view open-view-bus" title="View Bus" aria-label="View bus {{ $bus->bus_no }}"
                                                data-bus-no="{{ $bus->bus_no }}"
                                                data-bus-no-locked="{{ $bus->bus_no_locked ? '1' : '0' }}" data-plate-no="{{ $bus->plate_no }}"
                                                data-bus-model="{{ $bus->bus_model }}" data-year-model="{{ $bus->year_model }}"
                                                data-capacity="{{ $bus->capacity }}" data-status="{{ $displayStatus }}" data-fleet-purpose="{{ $bus->fleet_purpose }}"
                                                data-route-grouping="{{ $bus->route_grouping }}"
                                                data-display-route="{{ $bus->display_route_name }}"
                                                data-client-allocation-type="{{ $bus->client_allocation_type }}"
                                                data-client-names="{{ $bus->operationClients->pluck('name')->join(', ') }}" data-authorized-drivers="{{ $bus->authorizedDrivers->pluck('driver_name')->join(', ') }}">
                                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                            </button>
                                            @if($canEditOperation)
                                            <button
                                                class="action-btn edit open-edit-bus"
                                                type="button"
                                                @if($bus->maintenance_locked || $displayStatus === 'Under Maintenance') disabled aria-disabled="true" title="Locked: bus Under Maintenance" @else title="Edit Bus" @endif
                                                data-id="{{ $bus->id }}"
                                                data-bus-no="{{ $bus->bus_no }}"
                                                data-plate-no="{{ $bus->plate_no }}"
                                                data-bus-model="{{ $bus->bus_model }}"
                                                data-year-model="{{ $bus->year_model }}"
                                                data-capacity="{{ $bus->capacity }}"
                                                data-route-grouping="{{ $bus->route_grouping }}"
                                                data-status="{{ $bus->status }}"
                                                data-fleet-purpose="{{ $bus->fleet_purpose }}"
                                                data-fleet-purpose-locked="{{ $bus->fleet_purpose_locked ? '1' : '0' }}"
                                                data-client-allocation-type="{{ $bus->client_allocation_type }}"
                                                data-client-names="{{ $bus->operationClients->pluck('name')->join(', ') }}"
                                                data-update-url="/bus-master-list/{{ $bus->id }}"
                                            ><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></button>

                                            @if($displayStatus === 'Active')
                                            <form action="{{ route('bus-master-list.deactivate', $bus, false) }}" method="POST"
                                                data-confirm-form data-confirm-title="Deactivate Bus?"
                                                data-confirm-message="Deactivate bus {{ $bus->bus_no }}? Its history will be retained."
                                                data-confirm-button="Yes, Deactivate" data-confirm-type="warning">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="action-btn deactivate"
                                                    @if($bus->deactivation_locked) disabled aria-disabled="true" title="Locked: active or upcoming trip assignment" @else title="Deactivate Bus" @endif
                                                    aria-label="Deactivate bus {{ $bus->bus_no }}">
                                                    <i class="fa-solid fa-power-off" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                            @elseif($displayStatus === 'Inactive')
                                            <form action="{{ route('bus-master-list.activate', $bus, false) }}" method="POST"
                                                data-confirm-form data-confirm-title="Activate Bus?"
                                                data-confirm-message="Activate bus {{ $bus->bus_no }}?"
                                                data-confirm-button="Yes, Activate" data-confirm-type="info">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="action-btn activate" title="Activate Bus"
                                                    aria-label="Activate bus {{ $bus->bus_no }}">
                                                    <i class="fa-solid fa-power-off" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                            @else
                                            <button type="button" class="action-btn deactivate" disabled aria-disabled="true"
                                                title="Locked: bus Under Maintenance" aria-label="Bus status controlled by Maintenance">
                                                <i class="fa-solid fa-power-off" aria-hidden="true"></i>
                                            </button>
                                            @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <x-ui.empty-row
                                    colspan="{{ request('fleet_purpose') === 'Reserve' ? 5 : 7 }}"
                                    icon="fa-bus"
                                    message="No Bus Records Found"
                                    description="There are no buses matching the selected fleet and filters."
                                />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-ui.table-footer :items="$buses" />
            </section>

        {{-- Modals are part of the navigable page so their handlers reinitialize on return. --}}


    {{-- Read-only Bus Details: no form, editable controls, or update action. --}}
    <div id="viewBusModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-box wide-modal bus-details-modal" role="dialog" aria-modal="true" aria-labelledby="viewBusTitle">
            <div class="bus-details-header">
                <span class="bus-details-icon" aria-hidden="true"><i class="fa-solid fa-bus"></i></span>
                <div class="bus-details-heading">
                    <h2 id="viewBusTitle">Bus Information</h2>
                    <p>Official bus record and operational details. View only.</p>
                </div>
                <button type="button" class="close-btn" id="closeViewBusModal" aria-label="Close bus details">&times;</button>
            </div>
            <div class="bus-details-body">
                <div class="bus-details-section">
                    <i class="fa-regular fa-clipboard" aria-hidden="true"></i>
                    <div><strong>Bus Details</strong><p>Registered vehicle information</p></div>
                </div>
                <dl class="bus-details-grid">
                    <div><dt>Bus ID (Body Number)</dt><dd data-bus-detail="busNo">—</dd></div>
                    <div><dt>Plate Number</dt><dd data-bus-detail="plateNo">—</dd></div>
                    <div><dt>Bus Model</dt><dd data-bus-detail="busModel">—</dd></div>
                    <div><dt>Year Model</dt><dd data-bus-detail="yearModel">—</dd></div>
                    <div><dt>Capacity</dt><dd data-bus-detail="capacity">—</dd></div>
                    <div><dt>Status</dt><dd><span class="bus-details-status" data-bus-detail="status">—</span></dd></div>
                    <div><dt>Bus Purpose</dt><dd data-bus-detail="fleetPurpose">—</dd></div>
                    <div data-bus-regular-detail><dt>Client Allocation</dt><dd data-bus-detail="clientAllocation">—</dd></div><div data-bus-regular-detail><dt>Authorized Drivers</dt><dd data-bus-detail="authorizedDrivers">—</dd></div>
                </dl>
                <div class="bus-details-section bus-details-route-heading">
                    <i class="fa-solid fa-route" aria-hidden="true"></i>
                    <div><strong>Route / Grouping</strong><p>Assigned route or grouping information</p></div>
                </div>
                <div class="bus-details-route" data-bus-detail="routeGrouping">—</div>
            </div>
            <div class="bus-details-footer">
                <button type="button" class="primary-btn" id="dismissViewBusModal">Close</button>
            </div>
        </div>
    </div>

    @if($canEditOperation)
    <x-ui.form-modal
        id="busModal"
        icon="fa-bus"
        title="Add New Bus"
        description="Register a new bus to the fleet. Enter the vehicle details and select its purpose."
        action="/bus-master-list"
        method="POST"
        submit-text="Save Bus"
        close-id="closeBusModal"
        cancel-id="cancelBusModal"
        :confirm="true"
        confirm-title="Add Bus?"
        confirm-message="Are you sure you want to add this bus record?"
        confirm-button="Yes, Add Bus"
        confirm-type="create"
    >
        <div class="bus-modal-basic">
            <div class="bus-form-section"><span class="bus-section-icon"><i class="fa-regular fa-file-lines"></i></span><div><strong>Basic Information</strong><p>Provide the basic details of the bus unit.</p></div></div>
            <div class="bus-modal-field-grid"><div class="form-group">
            <label>Bus ID (Body Number)</label>

            <div class="bus-icon-input"><i class="fa-solid fa-bus" aria-hidden="true"></i><input
                type="text"
                name="bus_no"
                placeholder="Example: 277"
                required
            ></div>
        </div>
<div class="form-group">
            <label>Plate Number</label>

            <div class="bus-icon-input"><i class="fa-solid fa-id-card" aria-hidden="true"></i><input
                type="text"
                name="plate_no"
                placeholder="Example: ABC-1234"
            ></div>
        </div>
<div class="form-group">
            <label>Bus Model</label>

            <div class="bus-icon-input"><i class="fa-solid fa-bus-simple" aria-hidden="true"></i><input
                type="text"
                name="bus_model"
                placeholder="Example: Isuzu N-Series"
            ></div>
        </div>
<div class="form-group">
            <label>Year Model</label>

            <div class="bus-icon-input"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i><input
                type="text"
                name="year_model"
                placeholder="Example: 2024"
            ></div>
        </div>
<div class="form-group">
            <label>Capacity</label>

            <div class="bus-icon-input"><i class="fa-solid fa-users" aria-hidden="true"></i><input
                type="number"
                name="capacity"
                min="1"
                placeholder="Example: 40"
            ></div>
        </div>
<div class="form-group">
            <label>Status</label>

            <div class="bus-icon-input"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><select name="status" required>
                <option value="Active">
                    Active
                </option>

                <option value="Inactive">
                    Inactive
                </option>
            </select></div>
        </div></div>
        </div>
        <div class="bus-modal-lower">
<section class="bus-modal-panel">
                <div class="bus-form-section"><span class="bus-section-icon"><i class="fa-regular fa-bookmark"></i></span><div><strong>Bus Purpose</strong><p>Choose between regular and standby fleet.</p></div></div>
                <div class="form-group">
                    <label>Bus Purpose</label>
                    <div class="bus-icon-input"><i class="fa-solid fa-bus" aria-hidden="true"></i><select name="fleet_purpose" required>
                        <option value="Regular" @selected(old('fleet_purpose', request('fleet_purpose', 'Regular')) !== 'Reserve')>Regular</option>
                        <option value="Reserve" @selected(old('fleet_purpose', request('fleet_purpose')) === 'Reserve')>Reserve</option>
                    </select></div>
                    <p class="bus-purpose-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Regular buses join standard scheduling. Reserve buses are kept for breakdown replacements.</p>
                </div>
            </section>
            <section class="bus-modal-panel bus-modal-client-panel" data-bus-client-panel>
<div class="bus-client-editor bus-client-editor--simple">
    <div class="bus-client-section-heading"><span class="bus-client-section-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></span><div><strong>Client / Company</strong><small>Client assigned to this bus</small></div></div>
    <label for="create_client_name">Client / Company Name</label>
    <div class="bus-multi-client" data-bus-multi-client>
        <input type="hidden" name="client_name" id="create_client_name" value="{{ old('client_name') }}">
        <input type="hidden" name="client_allocation_submitted" value="1">
        <div class="bus-multi-client-tags" data-client-tags aria-live="polite"></div>
        <div class="bus-multi-client-entry">
            <input type="text" id="create_client_name_entry" maxlength="100" placeholder="Enter a client, e.g. SM" aria-label="Client name">
            <button type="button" data-add-client><i class="fa-solid fa-plus" aria-hidden="true"></i> Add</button>
        </div>
    </div>
    <small>Type one client and press Enter or Add. You can add multiple clients.</small>
    
</div>
</section>
        </div>
    </x-ui.form-modal>

    {{-- Edit Bus Modal --}}
    <div id="editBusModal" class="modal-overlay">
        <div class="modal-box wide-modal bus-edit-design">
            <div class="modal-header">
                <span class="bus-header-icon"><i class="fa-solid fa-bus"></i></span>
                <div class="bus-edit-heading">
                    <h2>Edit Bus Information</h2>
                    <p>
                        Update the selected official bus record.
                    </p>
                </div>

                <button
                    type="button"
                    id="closeEditBusModal"
                    class="close-btn"
                >
                    &times;
                </button>
            </div>

            <form
                id="editBusForm"
                action="#"
                method="POST"
                class="job-form wide-form"
                data-confirm-form
                data-confirm-title="Update Bus?"
                data-confirm-message="Are you sure you want to update this bus record?"
                data-confirm-button="Yes, Update Bus"
                data-confirm-type="update"
            >
                @csrf
                @method('PUT')

                <div class="bus-edit-scroll">
                    <div class="bus-modal-basic">
                        <div class="bus-form-section"><span class="bus-section-icon"><i class="fa-regular fa-file-lines"></i></span><div><strong>Basic Information</strong><p>Update the official details of this bus unit.</p></div></div>
                        <div class="bus-modal-field-grid">
                            <div class="form-group">
                    <label>Bus ID (Body Number)</label>

                    <div class="bus-icon-input"><i class="fa-solid fa-bus" aria-hidden="true"></i><input
                        type="text"
                        name="bus_no"
                        id="edit_bus_no"
                        required
                        aria-describedby="editBusNoLockHint"
                    ></div>
                </div>
                            <div class="form-group">
                    <label>Plate Number</label>

                    <div class="bus-icon-input"><i class="fa-solid fa-id-card" aria-hidden="true"></i><input
                        type="text"
                        name="plate_no"
                        id="edit_plate_no"
                    ></div>
                </div>
                            <div class="form-group">
                    <label>Bus Model</label>

                    <div class="bus-icon-input"><i class="fa-solid fa-bus-simple" aria-hidden="true"></i><input
                        type="text"
                        name="bus_model"
                        id="edit_bus_model"
                    ></div>
                </div>
                            <div class="form-group">
                    <label>Year Model</label>

                    <div class="bus-icon-input"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i><input
                        type="text"
                        name="year_model"
                        id="edit_year_model"
                    ></div>
                </div>
                            <div class="form-group">
                    <label>Capacity</label>

                    <div class="bus-icon-input"><i class="fa-solid fa-users" aria-hidden="true"></i><input
                        type="number"
                        name="capacity"
                        id="edit_capacity"
                        min="1"
                    ></div>
                </div>
                            <div class="form-group">
                    <label>Status</label>

                    <div class="bus-icon-input"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><select
                        name="status"
                        id="edit_status"
                        required
                    >
                        <option value="Active">
                            Active
                        </option>

                        <option value="Inactive">
                            Inactive
                        </option>
                        <option value="Under Maintenance" disabled>Under Maintenance — controlled by Maintenance</option>
                    </select></div>
                </div>
                        </div>
                        <p id="editBusNoLockHint" class="bus-no-lock-hint full-width" hidden><i class="fa-solid fa-lock" aria-hidden="true"></i> Bus ID (Body Number) is locked because this bus has related operational history.</p>
                    </div>
                    <div class="bus-modal-lower">
<section class="bus-modal-panel">
                <div class="bus-form-section"><span class="bus-section-icon"><i class="fa-regular fa-bookmark"></i></span><div><strong>Bus Purpose</strong><p>Choose between regular and standby fleet.</p></div></div>
                <div class="form-group">
                    <label>Bus Purpose</label>
                    <div class="bus-icon-input"><i class="fa-solid fa-bus" aria-hidden="true"></i><select name="fleet_purpose" id="edit_fleet_purpose" required>
                        <option value="Regular">Regular</option>
                        <option value="Reserve">Reserve</option>
                    </select></div>
                    <p class="bus-purpose-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Reserve buses are excluded from ordinary scheduling.</p>
                    <p id="editFleetPurposeLockHint" class="bus-no-lock-hint" hidden><i class="fa-solid fa-lock" aria-hidden="true"></i> Bus Purpose is locked while this bus has an ongoing or future trip assignment.</p>
                </div>
            </section>
                        <section class="bus-modal-panel bus-modal-client-panel" data-bus-client-panel>
<div class="bus-client-editor bus-client-editor--simple">
    <div class="bus-client-section-heading"><span class="bus-client-section-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></span><div><strong>Client / Company</strong><small>Client assigned to this bus</small></div></div>
    <label for="edit_client_name">Client / Company Name</label>
    <div class="bus-multi-client" data-bus-multi-client>
        <input type="hidden" name="client_name" id="edit_client_name" value="">
        <input type="hidden" name="client_allocation_submitted" value="1">
        <div class="bus-multi-client-tags" data-client-tags aria-live="polite"></div>
        <div class="bus-multi-client-entry">
            <input type="text" id="edit_client_name_entry" maxlength="100" placeholder="Enter a client, e.g. SM" aria-label="Client name">
            <button type="button" data-add-client><i class="fa-solid fa-plus" aria-hidden="true"></i> Add</button>
        </div>
    </div>
    <small>Add or remove client chips individually. Changes are blocked during an unfinished dispatched trip.</small>
    <p id="existingSharedClientNotice" class="bus-client-existing-note" hidden>Existing client allocations are shown above. Edit the list to change them.</p>
</div>
</section>
                    </div>
                    <p id="editBusMaintenanceNotice" class="bus-maintenance-lock-notice full-width" role="status" hidden><i class="fa-solid fa-lock" aria-hidden="true"></i> This bus is Under Maintenance. Master-list editing is disabled until Maintenance releases it.</p>
                </div>
                <div class="modal-actions full-width">
                    <button
                        type="button"
                        id="cancelEditBusModal"
                        class="secondary-btn cancel-btn"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="primary-btn save-btn"
                    >
                        Update Bus
                    </button>
                </div>
            </form>
        </div>
    </div>

    <x-ui.action-buttom-modal
        mode="delete"
        id="deleteBusModal"
        delete-title="Delete Bus?"
        delete-message="Are you sure you want to delete"
        name-id="deleteBusNo"
        cancel-id="cancelDeleteBus"
        confirm-id="confirmDeleteBus"
    />
    @endif
        </main>
    </div>
</x-layout.app>
