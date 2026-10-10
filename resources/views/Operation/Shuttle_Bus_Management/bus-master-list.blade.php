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
                            Bus availability and assigned routes reflect current operational records.
                        </p>
                    </div>
                </div>

                <form
                    method="GET"
                    action="/bus-master-list"
                    class="toolbar bus-toolbar"
                    data-server-filter="true"
                >
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Search plate number, unit number, model or status..."
                            aria-label="Search bus records"
                        >
                    </div>

                    <div class="filter-group">
                        <label for="busStatusFilter">Status</label>

                        <select
                            name="status"
                            id="busStatusFilter"
                            aria-label="Filter bus status"
                        >
                            <option value="All Status" @selected(! request()->filled('status') || request('status') === 'All Status')>
                                All Status
                            </option>

                            <option
                                value="Active"
                                @selected(request('status') === 'Active')
                            >
                                Active
                            </option>

                            <option
                                value="Inactive"
                                @selected(request('status') === 'Inactive')
                            >
                                Inactive
                            </option>

                            <option
                                value="Under Maintenance"
                                @selected(request('status') === 'Under Maintenance')
                            >
                                Under Maintenance
                            </option>
                        </select>
                    </div>

                    @if($canEditOperation)
                    <button
                        type="button"
                        id="openBusModal"
                        class="primary-btn"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Add Bus
                    </button>
                    @endif
                </form>

                <div class="bus-filter-loading" data-server-filter-loading hidden>
                    <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>
                    Updating bus records...
                </div>

                <div class="table-wrap">
                    <table class="bus-table">
                        <thead>
                            <tr>
                                <th>Bus ID (Plate Number)</th>
                                <th>Model</th>
                                <th>Assigned Route</th>

                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($buses as $bus)
                                <tr>
                                    <td>
                                        <x-ui.id-badge :value="$bus->plate_no ?: 'Plate not recorded'" />
                                    </td>

                                    <td>
                                        <strong>{{ $bus->bus_model ?: '—' }}</strong>
                                        <div class="bus-plate-detail">Unit No. {{ $bus->bus_no }}</div>
                                    </td>

                                    <td>
                                        <span class="gct-pill {{ $bus->display_route_name ? 'gct-pill--scheduled' : 'gct-pill--unassigned' }}">{{ $bus->display_route_name ?: 'Unassigned' }}</span>
                                    </td>

                                    <td>
                                        @php
                                            $statusClass = match ($bus->status) {
                                                'Active' => 'status-active',
                                                'Under Maintenance' => 'status-maintenance',
                                                'Inactive' => 'status-inactive',
                                                default => 'status-default',
                                            };
                                        @endphp

                                        <span class="bus-status gct-pill {{ $statusClass }}">
                                            {{ $bus->status }}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="actions">
                                            <button type="button" class="action-btn view open-view-bus" title="View Bus" aria-label="View bus {{ $bus->bus_no }}"
                                                data-bus-no="{{ $bus->bus_no }}"
                                                data-bus-no-locked="{{ $bus->bus_no_locked ? '1' : '0' }}" data-plate-no="{{ $bus->plate_no }}"
                                                data-bus-model="{{ $bus->bus_model }}" data-year-model="{{ $bus->year_model }}"
                                                data-capacity="{{ $bus->capacity }}" data-status="{{ $bus->status }}"
                                                data-route-grouping="{{ $bus->route_grouping }}"
                                                data-display-route="{{ $bus->display_route_name }}">
                                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                            </button>
                                            @if($canEditOperation)
                                            <button
                                                class="action-btn edit open-edit-bus"
                                                type="button"
                                                @if($bus->status === 'Under Maintenance') disabled aria-disabled="true" title="Locked: bus Under Maintenance" @else title="Edit Bus" @endif
                                                data-id="{{ $bus->id }}"
                                                data-bus-no="{{ $bus->bus_no }}"
                                                data-plate-no="{{ $bus->plate_no }}"
                                                data-bus-model="{{ $bus->bus_model }}"
                                                data-year-model="{{ $bus->year_model }}"
                                                data-capacity="{{ $bus->capacity }}"
                                                data-route-grouping="{{ $bus->route_grouping }}"
                                                data-status="{{ $bus->status }}"
                                                data-update-url="/bus-master-list/{{ $bus->id }}"
                                            ><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></button>

                                            <form
                                                id="deleteBusForm-{{ $bus->id }}"
                                                action="/bus-master-list/{{ $bus->id }}"
                                                method="POST"
                                            >
                                                @csrf
                                                @method('DELETE')

                                                <button
                                                    class="action-btn delete open-delete-bus"
                                                    type="button"
                                                    @if($bus->status === 'Under Maintenance') disabled aria-disabled="true" title="Locked: bus Under Maintenance" @else title="Delete Bus" @endif
                                                    data-id="{{ $bus->id }}"
                                                    data-bus-no="{{ $bus->bus_no }}"
                                                ><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                            </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <x-ui.empty-row
                                    colspan="6"
                                    message="No bus records found. Add your first bus."
                                />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-ui.table-footer :items="$buses" />
            </section>
        </main>
    </div>

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
                    <div><dt>Bus No.</dt><dd data-bus-detail="busNo">—</dd></div>
                    <div><dt>Plate No.</dt><dd data-bus-detail="plateNo">—</dd></div>
                    <div><dt>Bus Model</dt><dd data-bus-detail="busModel">—</dd></div>
                    <div><dt>Year Model</dt><dd data-bus-detail="yearModel">—</dd></div>
                    <div><dt>Capacity</dt><dd data-bus-detail="capacity">—</dd></div>
                    <div><dt>Status</dt><dd><span class="bus-details-status" data-bus-detail="status">—</span></dd></div>
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
        subtitle="Bus Information"
        description="Add an official bus record for Operations, GPS matching, PMS, and Job Orders."
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
        <div class="bus-form-section full-width"><span class="bus-section-icon"><i class="fa-regular fa-file-lines"></i></span><div><strong>Bus Information</strong><p>Provide the basic details of the bus unit.</p></div></div>
        <div class="form-group">
            <label>Bus No.</label>

            <input
                type="text"
                name="bus_no"
                placeholder="Example: BUS-205"
                required
            >
        </div>

        <div class="form-group">
            <label>Plate No.</label>

            <input
                type="text"
                name="plate_no"
                placeholder="Example: ABC-1234"
            >
        </div>

        <div class="form-group">
            <label>Bus Model</label>

            <input
                type="text"
                name="bus_model"
                placeholder="Example: Isuzu N-Series"
            >
        </div>

        <div class="form-group">
            <label>Year Model</label>

            <input
                type="text"
                name="year_model"
                placeholder="Example: 2024"
            >
        </div>

        <div class="form-group">
            <label>Capacity</label>

            <input
                type="number"
                name="capacity"
                min="1"
                placeholder="Example: 40"
            >
        </div>

        <div class="form-group">
            <label>Status</label>

            <select name="status" required>
                <option value="Active">
                    Active
                </option>

                <option value="Inactive">
                    Inactive
                </option>

                <option value="Under Maintenance">
                    Under Maintenance
                </option>
            </select>
        </div>

        <div class="bus-form-section full-width bus-route-section"><span class="bus-section-icon"><i class="fa-solid fa-location-dot"></i></span><div><strong>Route / Grouping</strong><p>Assign the bus to a route or group for easier management.</p></div></div>
        <div class="form-group full-width">
            <label>Route / Grouping</label>

            <input
                type="text"
                name="route_grouping"
                placeholder="Example: Batangas City - Malvar"
            >
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

                <div class="bus-form-section full-width"><span class="bus-section-icon"><i class="fa-regular fa-file-lines"></i></span><div><strong>Bus Information</strong><p>Update the official details of this bus unit.</p></div></div>
                <div class="form-group">
                    <label>Bus No.</label>

                    <input
                        type="text"
                        name="bus_no"
                        id="edit_bus_no"
                        required
                        aria-describedby="editBusNoLockHint"
                    >
                </div>

                <p id="editBusNoLockHint" class="bus-no-lock-hint full-width" hidden><i class="fa-solid fa-lock" aria-hidden="true"></i> Bus No. is locked because this bus has related operational history.</p>
                <div class="form-group">
                    <label>Plate No.</label>

                    <input
                        type="text"
                        name="plate_no"
                        id="edit_plate_no"
                    >
                </div>

                <div class="form-group">
                    <label>Bus Model</label>

                    <input
                        type="text"
                        name="bus_model"
                        id="edit_bus_model"
                    >
                </div>

                <div class="form-group">
                    <label>Year Model</label>

                    <input
                        type="text"
                        name="year_model"
                        id="edit_year_model"
                    >
                </div>

                <div class="form-group">
                    <label>Capacity</label>

                    <input
                        type="number"
                        name="capacity"
                        id="edit_capacity"
                        min="1"
                    >
                </div>

                <div class="form-group">
                    <label>Status</label>

                    <select
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

                        <option value="Under Maintenance">
                            Under Maintenance
                        </option>
                    </select>
                </div>

                <div class="bus-form-section full-width bus-route-section"><span class="bus-section-icon"><i class="fa-solid fa-location-dot"></i></span><div><strong>Route / Grouping</strong><p>Route assignments and group information.</p></div></div>
                <div class="form-group full-width">
                    <label>Route / Grouping</label>

                    <input
                        type="text"
                        name="route_grouping"
                        id="edit_route_grouping"
                    >
                </div>

                <p id="editBusMaintenanceNotice" class="bus-maintenance-lock-notice full-width" role="status" hidden><i class="fa-solid fa-lock" aria-hidden="true"></i> This bus is Under Maintenance. Master-list editing is disabled until Maintenance releases it.</p>

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
</x-layout.app>
