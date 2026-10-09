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
            <x-ui.summary-card label="Active Morning Shift" value="{{ $stats['morning'] }}" small="Active drivers on Morning shift" icon="fa-sun" color="yellow" />
        </section>

        <section data-ajax-region="records" class="table-card attendance-card personnel-master-panel">
            <div class="section-header personnel-section-header">
                <div>
                    <h2>Driver Records</h2>
                    <p>Permanent driver information only. Daily transactions remain in Driver Attendance.</p>
                </div>
            </div>

            <form method="GET" action="{{ route('operation.personnel.drivers', [], false) }}" class="toolbar attendance-toolbar personnel-master-toolbar driver-master-toolbar" data-server-filter="true">
                <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="search" value="{{ request('search') }}" placeholder="Search ID, name, contact, or shift..." aria-label="Search driver records"></div>
                <div class="filter-group personnel-filter">
                    <label class="sr-only" for="driverStatusFilter">Status</label>
                    <select id="driverStatusFilter" name="status" aria-label="Filter by status">
                        <option value="">All Statuses</option>
                        <option value="Active" @selected(request('status') === 'Active')>Active</option>
                        <option value="Inactive" @selected(request('status') === 'Inactive')>Inactive</option>
                    </select>
                </div>
                <div class="filter-group personnel-filter">
                    <label class="sr-only" for="driverShiftFilter">Default Shift</label>
                    <select id="driverShiftFilter" name="shift" aria-label="Filter by default shift">
                        <option value="">All Shifts</option>
                        @foreach(['Morning', 'Afternoon', 'Night'] as $shift)
                            <option value="{{ $shift }}" @selected(request('shift') === $shift)>{{ $shift }}</option>
                        @endforeach
                    </select>
                </div>
                @if($canEditOperation)
                <button type="button" class="primary-btn personnel-add-btn" data-personnel-action="add">
                    <i class="fa-solid fa-plus"></i>
                    <span>Add Driver</span>
                </button>
                @endif
            </form>
            <div class="personnel-filter-loading" data-server-filter-loading hidden>
                <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>
                Updating driver records...
            </div>

            <div class="table-wrap personnel-master-table-wrap">
                <table class="attendance-table personnel-master-table">
                    <thead><tr><th>Driver ID</th><th>Driver</th><th>Default Shift</th><th>Contact</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    @forelse($drivers as $driver)
                        @php
                            $driverRecord = e(json_encode([
                                'driver_id' => $driver->driver_id,
                                'driver_name' => $driver->driver_name,
                                'shift' => $driver->shift,
                                'contact_number' => $driver->contact_number,
                                'employment_status' => $driver->employment_status,
                            ], JSON_THROW_ON_ERROR));
                        @endphp
                        <tr>
                            <td><span class="personnel-id">{{ $driver->driver_id }}</span></td>
                            <td><div class="personnel-name-cell"><span class="personnel-avatar"><i class="fa-solid fa-user"></i></span><div><strong>{{ $driver->driver_name }}</strong><small>Driver profile</small></div></div></td>
                            <td><span class="gct-pill gct-pill--shift-{{ strtolower($driver->shift) }}">{{ $driver->shift }}</span></td>
                            <td>{{ $driver->contact_number ?: '—' }}</td>
                            <td><span class="badge personnel-status gct-pill gct-pill--{{ strtolower($driver->employment_status) }}">{{ $driver->employment_status }}</span></td>
                            <td><div class="actions">
                                <button type="button" class="action-btn view" title="View" aria-label="View {{ $driver->driver_name }}" data-personnel-action="view" data-record="{!! $driverRecord !!}"><i class="fa-solid fa-eye"></i></button>
                                @if($canEditOperation)
                                <button type="button" class="action-btn edit" title="Edit" aria-label="Edit {{ $driver->driver_name }}" data-personnel-action="edit" data-record-id="{{ $driver->id }}" data-update-url="{{ route('operation.personnel.drivers.update', $driver, false) }}" data-record="{!! $driverRecord !!}"><i class="fa-solid fa-pen-to-square"></i></button>
                                @if($driver->employment_status === 'Active')
                                <form method="POST" action="{{ route('operation.personnel.drivers.deactivate', $driver, false) }}" data-confirm-form data-confirm-title="Deactivate Driver?" data-confirm-message="This removes the driver from active attendance rosters but preserves historical records." data-confirm-button="Deactivate" data-confirm-type="warning">@csrf @method('PATCH')<button type="submit" class="action-btn delete" title="Deactivate" aria-label="Deactivate {{ $driver->driver_name }}"><i class="fa-solid fa-user-slash"></i></button></form>
                                @endif
                                @endif
                            </div></td>
                        </tr>
                    @empty
                        <x-ui.empty-row colspan="6" message="No driver master records found." />
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
            <label class="driver-input-field">
                <span>Default Shift <span class="personnel-required">*</span></span>
                <span class="driver-input-shell">
                    <i class="fa-regular fa-clock" aria-hidden="true"></i>
                    <select name="shift" required>
                        <option value="">Select shift</option>
                        @foreach(['Morning','Afternoon','Night'] as $shift)
                            <option value="{{ $shift }}" @selected(old('shift') === $shift)>{{ $shift }}</option>
                        @endforeach
                    </select>
                </span>
            </label>
            <label class="driver-input-field">
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
