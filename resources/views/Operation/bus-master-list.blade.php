<x-layout.app
    title="FROMS - Bus Master List"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Operation/bus-master-list.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Operation/bus-master-list.js'
    ]"
>
    <x-ui.action-buttom-modal
        mode="feedback"
        feedback-type="success"
        :message="session('success')"
    />

    <x-ui.action-buttom-modal
        mode="feedback"
        feedback-type="error"
        :message="session('error')"
    />

    @if($errors->any())
        <x-ui.action-buttom-modal
            mode="feedback"
            feedback-type="error"
            :message="$errors->first()"
        />
    @endif

    <div class="app">

        <x-layout.sidebar
            department="Operation"
            subtitle="Department Module"
            icon="fa-clipboard-check"
            :items="[
                [
                    'label' => 'Dashboard',
                    'route' => 'dashboard-operation',
                    'icon' => 'fa-table-cells-large'
                ],
                [
                    'label' => 'Bus Master List',
                    'route' => 'bus-master-list',
                    'icon' => 'fa-bus'
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
                        [
                            'label' => 'Available Mechanics',
                            'route' => 'available-mechanics',
                            'icon' => 'fa-user-check'
                        ],
                    ]
                ],
            ]"
        />

        <main class="main">

            <x-layout.topbar
                title="Bus Master List"
                subtitle="Manage official bus records used by GPS, PMS Scheduling, and Job Orders"
                notification-count="6"
            />

            <section class="stats-grid">
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
                    color="yellow"
                />

                <x-ui.summary-card
                    label="GPS Matched"
                    value="{{ $withGpsData }}"
                    small="With processed GPS data"
                    icon="fa-location-dot"
                    color="red"
                />
            </section>

            <section class="table-card">
                <div class="section-header">
                    <div>
                        <h2>Registered Buses</h2>
                        <p>GPS mileage appears after Admin processes matching GPS batch records.</p>
                    </div>
                </div>

                <form
                    method="GET"
                    action="{{ route('bus-master-list') }}"
                    class="toolbar bus-toolbar"
                >
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Search bus number, plate, model, route, or status..."
                        >
                    </div>

                    <div class="filter-group">
                        <label for="busStatusFilter">Status</label>

                        <select
                            name="status"
                            id="busStatusFilter"
                            onchange="this.form.submit()"
                        >
                            <option value="All Status">All Status</option>
                            <option value="Active" @selected(request('status') === 'Active')>
                                Active
                            </option>
                            <option value="Inactive" @selected(request('status') === 'Inactive')>
                                Inactive
                            </option>
                            <option value="Under Maintenance" @selected(request('status') === 'Under Maintenance')>
                                Under Maintenance
                            </option>
                        </select>
                    </div>

                    <button
                        type="button"
                        id="openImportBusModal"
                        class="import-btn"
                    >
                        <i class="fa-solid fa-file-import"></i>
                        Import CSV
                    </button>

                    <button
                        type="button"
                        id="openBusModal"
                        class="primary-btn"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Add Bus
                    </button>
                </form>

                <div class="table-wrap">
                    <table class="bus-table">
                        <thead>
                            <tr>
                                <th>Bus No.</th>
                                <th>Plate No.</th>
                                <th>Model</th>
                                <th>Route / Grouping</th>
                                <th>Latest GPS KM</th>
                                <th>Last PMS KM</th>
                                <th>Next PMS KM</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($buses as $bus)
                                <tr>
                                    <td><strong>{{ $bus->bus_no }}</strong></td>
                                    <td>{{ $bus->plate_no ?: '—' }}</td>
                                    <td>{{ $bus->bus_model ?: '—' }}</td>
                                    <td>{{ $bus->route_grouping ?: '—' }}</td>

                                    <td>
                                        @if($bus->display_latest_gps_km !== null)
                                            {{ number_format($bus->display_latest_gps_km, 2) }} km
                                        @else
                                            <span class="empty">No GPS data</span>
                                        @endif
                                    </td>

                                    <td>{{ number_format($bus->last_pms_km, 2) }} km</td>
                                    <td>{{ number_format($bus->next_pms_km, 2) }} km</td>

                                    <td>
                                        <x-ui.status-badge :status="$bus->status" />
                                    </td>

                                    <td>
                                        <div class="actions">
                                            <x-ui.action-buttom-modal
                                                class="edit open-edit-bus"
                                                type="button"
                                                title="Edit Bus"
                                                icon="fa-pen-to-square"
                                                data-id="{{ $bus->id }}"
                                                data-bus-no="{{ $bus->bus_no }}"
                                                data-plate-no="{{ $bus->plate_no }}"
                                                data-bus-model="{{ $bus->bus_model }}"
                                                data-year-model="{{ $bus->year_model }}"
                                                data-capacity="{{ $bus->capacity }}"
                                                data-route-grouping="{{ $bus->route_grouping }}"
                                                data-status="{{ $bus->status }}"
                                                data-last-pms-km="{{ $bus->last_pms_km }}"
                                                data-pms-interval-km="{{ $bus->pms_interval_km }}"
                                                data-update-url="{{ route('bus-master-list.update', $bus->id) }}"
                                            />

                                            <form
                                                id="deleteBusForm-{{ $bus->id }}"
                                                action="{{ route('bus-master-list.destroy', $bus->id) }}"
                                                method="POST"
                                            >
                                                @csrf
                                                @method('DELETE')

                                                <x-ui.action-buttom-modal
                                                    class="delete open-delete-bus"
                                                    type="button"
                                                    title="Delete Bus"
                                                    icon="fa-trash"
                                                    data-id="{{ $bus->id }}"
                                                    data-bus-no="{{ $bus->bus_no }}"
                                                />
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <x-ui.empty-row
                                    colspan="9"
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

    <x-ui.form-modal
        id="busModal"
        title="Add New Bus"
        subtitle="Bus Information"
        description="Add an official bus record for Operations, GPS matching, PMS, and Job Orders."
        action="{{ route('bus-master-list.store') }}"
        method="POST"
        submit-text="Save Bus"
        close-id="closeBusModal"
        cancel-id="cancelBusModal"
    >
        <div class="form-group">
            <label>Bus No.</label>
            <input type="text" name="bus_no" placeholder="Example: BUS-205" required>
        </div>

        <div class="form-group">
            <label>Plate No.</label>
            <input type="text" name="plate_no" placeholder="Example: ABC-1234">
        </div>

        <div class="form-group">
            <label>Bus Model</label>
            <input type="text" name="bus_model" placeholder="Example: Isuzu N-Series">
        </div>

        <div class="form-group">
            <label>Year Model</label>
            <input type="text" name="year_model" placeholder="Example: 2024">
        </div>

        <div class="form-group">
            <label>Capacity</label>
            <input type="number" name="capacity" min="1" placeholder="Example: 40">
        </div>

        <div class="form-group">
            <label>Status</label>

            <select name="status" required>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="Under Maintenance">Under Maintenance</option>
            </select>
        </div>

        <div class="form-group full-width">
            <label>Route / Grouping</label>
            <input
                type="text"
                name="route_grouping"
                placeholder="Example: Batangas City - Malvar"
            >
        </div>

        <div class="form-section-title full-width">
            <h3>PMS Starting Information</h3>
            <p>GPS mileage appears later after Admin processes matching GPS records.</p>
        </div>

        <div class="form-group">
            <label>Last PMS KM</label>
            <input
                type="number"
                name="last_pms_km"
                min="0"
                step="0.01"
                value="0"
            >
        </div>

        <div class="form-group">
            <label>PMS Interval KM</label>
            <input
                type="number"
                name="pms_interval_km"
                min="1"
                step="0.01"
                value="5000"
            >
        </div>
    </x-ui.form-modal>

    {{-- CSV Import Modal --}}
    <div id="importBusModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <div>
                    <h2>Import Bus CSV</h2>
                    <p>Bulk add or update buses using a CSV file.</p>
                </div>

                <button
                    type="button"
                    id="closeImportBusModal"
                    class="close-btn"
                >
                    &times;
                </button>
            </div>

            <form
                action="{{ route('bus-master-list.import') }}"
                method="POST"
                enctype="multipart/form-data"
                class="job-form"
            >
                @csrf

                <div class="form-group full-width">
                    <label>CSV File</label>

                    <input
                        type="file"
                        name="csv_file"
                        accept=".csv,text/csv"
                        required
                    >

                    <small>
                        Required column: <strong>bus_no</strong>
                    </small>
                </div>

                <div class="form-section-title full-width">
                    <h3>Supported CSV Columns</h3>
                    <p>
                        bus_no, plate_no, bus_model, year_model, capacity,
                        route_grouping, status, last_pms_km, pms_interval_km
                    </p>
                </div>

                <div class="modal-actions full-width">
                    <button
                        type="button"
                        id="cancelImportBusModal"
                        class="cancel-btn"
                    >
                        Cancel
                    </button>

                    <button type="submit" class="save-btn">
                        Import CSV
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="editBusModal" class="modal-overlay">
        <div class="modal-box wide-modal">
            <div class="modal-header">
                <div>
                    <h2>Edit Bus Information</h2>
                    <p>Update the selected official bus record.</p>
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
            >
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label>Bus No.</label>
                    <input type="text" name="bus_no" id="edit_bus_no" required>
                </div>

                <div class="form-group">
                    <label>Plate No.</label>
                    <input type="text" name="plate_no" id="edit_plate_no">
                </div>

                <div class="form-group">
                    <label>Bus Model</label>
                    <input type="text" name="bus_model" id="edit_bus_model">
                </div>

                <div class="form-group">
                    <label>Year Model</label>
                    <input type="text" name="year_model" id="edit_year_model">
                </div>

                <div class="form-group">
                    <label>Capacity</label>
                    <input type="number" name="capacity" id="edit_capacity" min="1">
                </div>

                <div class="form-group">
                    <label>Status</label>

                    <select name="status" id="edit_status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Under Maintenance">Under Maintenance</option>
                    </select>
                </div>

                <div class="form-group full-width">
                    <label>Route / Grouping</label>
                    <input type="text" name="route_grouping" id="edit_route_grouping">
                </div>

                <div class="form-section-title full-width">
                    <h3>PMS Information</h3>
                    <p>Next PMS KM is automatically recalculated after saving.</p>
                </div>

                <div class="form-group">
                    <label>Last PMS KM</label>
                    <input
                        type="number"
                        name="last_pms_km"
                        id="edit_last_pms_km"
                        min="0"
                        step="0.01"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>PMS Interval KM</label>
                    <input
                        type="number"
                        name="pms_interval_km"
                        id="edit_pms_interval_km"
                        min="1"
                        step="0.01"
                        required
                    >
                </div>

                <div class="modal-actions full-width">
                    <button
                        type="button"
                        id="cancelEditBusModal"
                        class="cancel-btn"
                    >
                        Cancel
                    </button>

                    <button type="submit" class="save-btn">
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
</x-layout.app>