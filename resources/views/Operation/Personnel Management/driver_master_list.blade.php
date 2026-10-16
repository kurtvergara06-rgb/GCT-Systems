<x-layout.app
    title="FROMS - Driver Master List"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Main-styles/form-components.css',
        'resources/css/Operation/Attendance/personnel-master.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Operation/Attendance/personnel-master-modal.js'
    ]"
>
@php
    $canEditOperation = auth()->user()?->hasSystemPermission('operation', 'edit') ?? false;
@endphp
<div class="app">
    <x-layout.sidebar department="Operation" />

    <main class="main personnel-master-page">
        <x-layout.topbar title="Driver Master List" subtitle="Manage permanent driver profiles and employment information" notification-count="0" />

        <section data-ajax-region="summary" class="stats-grid personnel-stats-grid">
            <x-ui.summary-card label="Total Drivers" value="{{ $stats['total'] }}" small="All driver profiles" icon="fa-users" color="blue" />
            <x-ui.summary-card label="Active" value="{{ $stats['active'] }}" small="Available for attendance" icon="fa-user-check" color="green" />
            <x-ui.summary-card label="Inactive" value="{{ $stats['inactive'] }}" small="Deactivated profiles" icon="fa-user-slash" color="red" />
            <x-ui.summary-card label="Authorized Drivers" value="{{ $stats['authorized'] }}" small="Active drivers with assigned buses" icon="fa-bus" color="yellow" />
        </section>

        <section data-ajax-region="records" class="table-card attendance-card personnel-master-panel">
            <div class="section-header personnel-section-header">
                <div>
                    <h2>Driver Records</h2>
                    <p>Permanent driver information only. Daily transactions remain in Driver Attendance.</p>
                </div>
@if($canEditOperation)
                <button type="button" class="primary-btn personnel-add-btn" data-personnel-action="add">
                    <i class="fa-solid fa-plus"></i>
                    <span>Add Driver</span>
                </button>
                @endif

            </div>


            <div class="personnel-record-controls">
                <nav class="personnel-record-tabs" aria-label="Driver record groups">
                    <a class="personnel-record-tab {{ request('tab', 'active') !== 'history' ? 'is-active' : '' }}" href="{{ route('operation.personnel.drivers', [], false) }}?{{ http_build_query(array_merge(request()->except(['tab', 'status', 'page']), ['tab' => 'active'])) }}"><i class="fa-solid fa-user-check" aria-hidden="true"></i> Active Drivers</a>
                    <a class="personnel-record-tab {{ request('tab') === 'history' ? 'is-active' : '' }}" href="{{ route('operation.personnel.drivers', [], false) }}?{{ http_build_query(array_merge(request()->except(['tab', 'status', 'page']), ['tab' => 'history'])) }}"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> History</a>
                </nav>
                <x-ui.table-toolbar action="{{ route('operation.personnel.drivers', [], false) }}" search-label="Search Drivers" search-placeholder="Search ID, name, or contact..."  :filter-count="(int) request()->filled('bus_authorization')" filter-title="Filter Drivers" :sort-options="['driver_name' => 'Driver Name', 'driver_id' => 'Driver ID', 'employment_status' => 'Status']" default-sort="driver_name">
                    <x-slot:hidden><input type="hidden" name="tab" value="{{ request('tab') === 'history' ? 'history' : 'active' }}"></x-slot:hidden>
                    <x-slot:filters>
                        <x-ui.table-toolbar-field label="Authorized Buses" name="bus_authorization">
                            <option value="">All Drivers</option>
                            <option value="assigned" @selected(request('bus_authorization') === 'assigned')>With Authorized Buses</option>
                            <option value="unassigned" @selected(request('bus_authorization') === 'unassigned')>Without Authorized Buses</option>
                        </x-ui.table-toolbar-field>
                    </x-slot:filters>

                </x-ui.table-toolbar>
            </div>
            <div class="personnel-filter-loading" data-server-filter-loading hidden>
                <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>
                Updating driver records...
            </div>

            <div class="table-wrap personnel-master-table-wrap">
                <table class="attendance-table personnel-master-table">
                    <thead><tr><th>Driver ID</th><th>Driver</th><th>Contact</th><th>Assigned Buses</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    @forelse($drivers as $driver)
                        @php
                            $driverRecord = e(json_encode([
                                'driver_id' => $driver->driver_id,
                                'identity_locked' => $driver->attendances_exists || \App\Models\Operation\TripAssignment::query()->where('driver_id', $driver->driver_id)->exists(),
                                'driver_name' => $driver->driver_name,
                                'shift' => $driver->shift,
                                'contact_number' => $driver->contact_number,
                                'employment_status' => $driver->employment_status,
                                'bus_ids' => $driver->authorizedBuses->pluck('id')->all(),
                            ], JSON_THROW_ON_ERROR));
                        @endphp
                        <tr>
                            <td><span class="personnel-id">{{ $driver->driver_id }}</span></td>
                            <td><div class="personnel-name-cell"><span class="personnel-avatar"><i class="fa-solid fa-user"></i></span><div><strong>{{ $driver->driver_name }}</strong><small>Driver profile</small></div></div></td>
                            <td>{{ $driver->contact_number ?: '—' }}</td>
                            <td class="driver-buses-cell">
                                @php $assignedBuses = $driver->authorizedBuses->sortBy('bus_no')->values(); @endphp
                                @if($assignedBuses->isEmpty())
                                    <span class="driver-bus-none">Not assigned</span>
                                @else
                                    <div class="driver-bus-summary">
                                        <span class="driver-bus-primary"><i class="fa-solid fa-bus" aria-hidden="true"></i> {{ $assignedBuses->first()->bus_no }}</span>
                                        @if($assignedBuses->count() > 1)
                                            <details class="driver-bus-more">
                                                <summary aria-label="Show {{ $assignedBuses->count() - 1 }} other authorized buses for {{ $driver->driver_name }}">+{{ $assignedBuses->count() - 1 }} more <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                                                <div class="driver-bus-more-panel">
                                                    <strong>Other authorized buses</strong>
                                                    @foreach($assignedBuses->skip(1) as $assignedBus)
                                                        <div class="driver-bus-more-row"><i class="fa-solid fa-bus" aria-hidden="true"></i><span><b>{{ $assignedBus->bus_no }}</b><small>{{ $assignedBus->plate_no ?: 'No plate number' }}</small></span></div>
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td><x-ui.status-badge :status="$driver->employment_status" class="personnel-status gct-pill gct-pill--{{ strtolower($driver->employment_status) }}" /></td>
                            <td><div class="actions">
                                <button type="button" class="action-btn view" title="View" aria-label="View {{ $driver->driver_name }}" data-personnel-action="view" data-record="{!! $driverRecord !!}"><i class="fa-solid fa-eye"></i></button>
                                @if($canEditOperation)
                                <button type="button" class="action-btn edit" title="Edit driver profile" aria-label="Edit {{ $driver->driver_name }}" data-personnel-action="edit" data-record-id="{{ $driver->id }}" data-update-url="{{ route('operation.personnel.drivers.update', $driver, false) }}" data-record="{!! $driverRecord !!}"><i class="fa-solid fa-pen-to-square"></i></button>
                                @if($driver->employment_status === 'Active')
                                <form method="POST" action="{{ route('operation.personnel.drivers.deactivate', $driver, false) }}" data-confirm-form data-confirm-title="Deactivate Driver?" data-confirm-message="This removes the driver from active attendance rosters but preserves historical records." data-confirm-button="Deactivate" data-confirm-type="warning">@csrf @method('PATCH')<button type="submit" class="action-btn delete" @if($driver->deactivation_locked) disabled aria-disabled="true" title="Locked: unresolved trip assignment" @else title="Deactivate and release assigned buses" @endif aria-label="Deactivate {{ $driver->driver_name }}"><i class="fa-solid fa-user-slash"></i></button></form>
                                @endif
                                @endif
                            </div></td>
                        </tr>
                    @empty
                        <x-ui.empty-row
                            colspan="6"
                            icon="fa-id-card"
                            message="No Driver Records Found"
                            description="No driver profiles match your search or filter criteria. Try adjusting your filters."
                        />
                    @endforelse
                    </tbody>
                </table>
            </div>
            <x-ui.table-footer :items="$drivers" />
        </section>
    </main>
</div>

@if($canEditOperation)
<div class="personnel-modal-overlay" data-personnel-modal data-open-on-error="{{ $errors->any() ? 'true' : 'false' }}" aria-hidden="true">
    <div class="personnel-modal personnel-modal-driver" role="dialog" aria-modal="true" aria-labelledby="personnelModalTitle" aria-describedby="personnelModalSubtitle">
        <div class="personnel-modal-header driver-modal-header">
            <div class="personnel-modal-title">
                <div class="personnel-modal-icon driver-modal-icon">
                    <i class="fa-solid fa-id-card" data-personnel-modal-icon aria-hidden="true"></i>
                </div>
                <div>
                    <h2 id="personnelModalTitle" data-modal-title>Add New Driver</h2>
                    <p id="personnelModalSubtitle" data-modal-subtitle>Create a permanent driver profile. Attendance is recorded separately.</p>
                </div>
            </div>
            <button type="button" class="personnel-modal-close" data-close-personnel-modal aria-label="Close driver details">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('operation.personnel.drivers.store', [], false) }}" data-personnel-form data-store-url="{{ route('operation.personnel.drivers.store', [], false) }}" class="personnel-modal-form personnel-driver-form">
            @csrf
            <input type="hidden" name="_method" value="POST" data-method-field>
            <input type="hidden" name="editing_personnel_id" value="{{ old('editing_personnel_id') }}" data-editing-personnel-id>
            @if($errors->any())
                <div class="personnel-modal-errors" role="alert">
                    <strong>Please review the following:</strong>
                    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="driver-modal-group-heading">
                <div>
                    <strong>Driver information</strong>
                    <small>Identity and default assignment details</small>
                </div>
                <i class="fa-solid fa-address-card" aria-hidden="true"></i>
            </div>

            <label class="driver-input-field">
                <span>Driver ID <span class="personnel-required">*</span></span>
                <span class="driver-input-shell">
                    <i class="fa-solid fa-hashtag" aria-hidden="true"></i>
                    <input name="driver_id" value="{{ old('driver_id') }}" placeholder="e.g. DRV-001" autocomplete="off" required>
                </span>
            </label>
            <label class="driver-input-field">
                <span>Driver Name <span class="personnel-required">*</span></span>
                <span class="driver-input-shell">
                    <i class="fa-solid fa-user" aria-hidden="true"></i>
                    <input name="driver_name" value="{{ old('driver_name') }}" placeholder="Full driver name" required>
                </span>
            </label>
            <div class="driver-input-field driver-bus-field">
                <span><i class="fa-solid fa-bus-simple" aria-hidden="true"></i> Assigned Buses</span>
                <input type="hidden" name="bus_ids_present" value="1">
                <div class="driver-bus-picker" data-driver-bus-picker>
                    <button type="button" class="driver-bus-picker-trigger" data-driver-bus-trigger aria-expanded="false" aria-controls="driverBusChoices">
                        <span data-driver-bus-label>Choose buses</span>
                        <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="driver-bus-picker-panel" id="driverBusChoices" data-driver-bus-panel hidden>
                        <input type="search" class="driver-bus-picker-search" data-driver-bus-search placeholder="Search bus number or plate..." aria-label="Search available buses">
                        <div class="driver-bus-picker-options">
                            @foreach($availableBuses as $busOption)
                                @php $activeOwner = $activeBusOwners->get($busOption->id); @endphp
                                <label class="driver-bus-picker-option" data-driver-bus-option data-owner-id="{{ $activeOwner['owner_id'] ?? '' }}">
                                    <input type="checkbox" name="bus_ids[]" value="{{ $busOption->id }}" data-driver-bus-checkbox data-bus-number="{{ $busOption->bus_no }}" @checked(in_array($busOption->id, old('bus_ids', [])))>
                                    <span class="driver-bus-option-text"><strong>{{ $busOption->bus_no }}</strong><small>{{ $activeOwner ? 'Assigned to '.$activeOwner['owner_name'] : ($busOption->plate_no ?: 'No plate number') }}</small></span>
                                </label>
                            @endforeach
                        </div>
                        <span class="driver-bus-picker-empty" data-driver-bus-empty hidden>No matching buses</span>
                    </div>
                </div>
                <div class="driver-bus-chips" data-driver-bus-chips aria-live="polite"></div>
                <small class="driver-bus-help">Multiple selections allowed</small>
            </div>
            <label class="driver-input-field driver-contact-field">
                <span>Contact Number</span>
                <span class="driver-input-shell">
                    <i class="fa-solid fa-phone" aria-hidden="true"></i>
                    <input name="contact_number" value="{{ old('contact_number') }}" placeholder="Contact number" inputmode="tel">
                </span>
            </label>

            <div class="driver-modal-group-heading">
                <div>
                    <strong>Employment information</strong>
                    <small>Driver availability for operations</small>
                </div>
                <i class="fa-solid fa-user-check" aria-hidden="true"></i>
            </div>
            <label class="driver-input-field full-width">
                <span>Employment Status <span class="personnel-required">*</span></span>
                <span class="driver-input-shell">
                    <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                    <select name="employment_status" required>
                        <option value="Active" @selected(old('employment_status', 'Active') === 'Active')>Active</option>
                        <option value="Inactive" @selected(old('employment_status') === 'Inactive')>Inactive</option>
                    </select>
                </span>
            </label>

            <div class="driver-modal-view-notice" data-personnel-view-notice hidden>
                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                <span>View-only details. Select Edit from the table to make changes.</span>
            </div>
            <div class="personnel-modal-actions driver-modal-actions">
                <span class="driver-modal-helper">Attendance records are managed separately.</span>
                <button type="button" class="secondary-btn" data-close-personnel-modal>Cancel</button>
                <button type="submit" class="primary-btn" data-submit-button>
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save Driver
                </button>
            </div>
        </form>
    </div>
</div>
@endif
</x-layout.app>
