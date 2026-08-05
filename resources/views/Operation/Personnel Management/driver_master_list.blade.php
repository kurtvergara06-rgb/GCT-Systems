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
<div class="app">
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
            'label' => 'Personnel Management',
            'icon' => 'fa-address-book',
            'children' => [
                [
                    'label' => 'Driver Master List',
                    'route' => 'operation.personnel.drivers',
                    'icon' => 'fa-id-card',
                ],
                [
                    'label' => 'Mechanic Master List',
                    'route' => 'operation.personnel.mechanics',
                    'icon' => 'fa-users-gear',
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
                    'icon' => 'fa-user-check',
                ],
                [
                    'label' => 'Mechanic Attendance',
                    'route' => 'mechanic-attendance',
                    'icon' => 'fa-clipboard-user',
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

    <main class="main">
        <x-layout.topbar title="Driver Master List" subtitle="Manage permanent driver profiles and employment information" notification-count="0" />

        <section class="stats-grid personnel-stats-grid">
            <x-ui.summary-card label="Total Drivers" value="{{ $stats['total'] }}" small="All driver profiles" icon="fa-users" color="blue" />
            <x-ui.summary-card label="Active" value="{{ $stats['active'] }}" small="Available for attendance" icon="fa-user-check" color="green" />
            <x-ui.summary-card label="Inactive" value="{{ $stats['inactive'] }}" small="Deactivated profiles" icon="fa-user-slash" color="red" />
            <x-ui.summary-card label="License Expiring" value="{{ $stats['expiring'] }}" small="Within the next 60 days" icon="fa-id-card" color="yellow" />
        </section>

        <section class="table-card attendance-card personnel-master-panel">
            <div class="section-header personnel-section-header">
                <div>
                    <span class="personnel-module-label"><i class="fa-solid fa-address-book"></i> Personnel Management</span>
                    <h2>Driver Records</h2>
                    <p>Permanent driver information only. Daily transactions remain in Driver Attendance.</p>
                </div>
            </div>

            <form method="GET" action="{{ route('operation.personnel.drivers', [], false) }}" class="toolbar attendance-toolbar personnel-master-toolbar">
                <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="search" value="{{ request('search') }}" placeholder="Search ID, name, contact, shift, or license..."></div>
                <div class="filter-group"><label>Status</label><select name="status" onchange="this.form.submit()"><option value="">All Status</option><option value="Active" @selected(request('status') === 'Active')>Active</option><option value="Inactive" @selected(request('status') === 'Inactive')>Inactive</option></select></div>
                <button class="secondary-btn personnel-search-btn" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                <button type="button" class="primary-btn personnel-add-btn" data-personnel-action="add"><i class="fa-solid fa-plus"></i> Add Driver</button>
            </form>

            <div class="table-wrap personnel-master-table-wrap">
                <table class="attendance-table personnel-master-table">
                    <thead><tr><th>Driver ID</th><th>Driver</th><th>Default Shift</th><th>Contact</th><th>License Number</th><th>License Expiration</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    @forelse($drivers as $driver)
                        @php
                            $driverRecord = e(json_encode([
                                'driver_id' => $driver->driver_id,
                                'driver_name' => $driver->driver_name,
                                'shift' => $driver->shift,
                                'contact_number' => $driver->contact_number,
                                'license_number' => $driver->license_number,
                                'license_expiration' => $driver->license_expiration?->format('Y-m-d'),
                                'employment_status' => $driver->employment_status,
                            ], JSON_THROW_ON_ERROR));
                        @endphp
                        <tr>
                            <td><span class="personnel-id">{{ $driver->driver_id }}</span></td>
                            <td><div class="personnel-name-cell"><span class="personnel-avatar"><i class="fa-solid fa-user"></i></span><div><strong>{{ $driver->driver_name }}</strong><small>Driver profile</small></div></div></td>
                            <td><span class="personnel-shift"><i class="fa-regular fa-clock"></i> {{ $driver->shift }}</span></td>
                            <td>{{ $driver->contact_number ?: '—' }}</td>
                            <td>{{ $driver->license_number ?: '—' }}</td>
                            <td>{{ $driver->license_expiration?->format('M d, Y') ?? '—' }}</td>
                            <td><span class="badge personnel-status {{ strtolower($driver->employment_status) }}">{{ $driver->employment_status }}</span></td>
                            <td><div class="actions">
                                <button type="button" class="action-btn view" title="View" data-personnel-action="view" data-record="{{ $driverRecord }}"><i class="fa-solid fa-eye"></i></button>
                                <button type="button" class="action-btn edit" title="Edit" data-personnel-action="edit" data-update-url="{{ route('operation.personnel.drivers.update', $driver, false) }}" data-record="{{ $driverRecord }}"><i class="fa-solid fa-pen-to-square"></i></button>
                                @if($driver->employment_status === 'Active')
                                <form method="POST" action="{{ route('operation.personnel.drivers.deactivate', $driver, false) }}" data-confirm-form data-confirm-title="Deactivate Driver?" data-confirm-message="This removes the driver from active attendance rosters but preserves historical records." data-confirm-button="Deactivate" data-confirm-type="warning">@csrf @method('PATCH')<button type="submit" class="action-btn delete" title="Deactivate"><i class="fa-solid fa-user-slash"></i></button></form>
                                @endif
                            </div></td>
                        </tr>
                    @empty
                        <x-ui.empty-row colspan="8" message="No driver master records found." />
                    @endforelse
                    </tbody>
                </table>
            </div>
            <x-ui.table-footer :items="$drivers" />
        </section>
    </main>
</div>

<div class="personnel-modal-overlay" data-personnel-modal data-open-on-error="{{ $errors->any() ? 'true' : 'false' }}" aria-hidden="true">
    <div class="personnel-modal" role="dialog" aria-modal="true" aria-labelledby="personnelModalTitle">
        <div class="personnel-modal-header"><div class="personnel-modal-title"><div class="personnel-modal-icon"><i class="fa-solid fa-user-plus"></i></div><div><span class="personnel-modal-eyebrow">Personnel Management</span><h2 id="personnelModalTitle" data-modal-title>Add New Driver</h2><p data-modal-subtitle>Create a permanent driver profile. Attendance is recorded separately.</p></div></div><button type="button" class="personnel-modal-close" data-close-personnel-modal aria-label="Close">&times;</button></div>
        <form method="POST" action="{{ route('operation.personnel.drivers.store', [], false) }}" data-personnel-form data-store-url="{{ route('operation.personnel.drivers.store', [], false) }}" class="personnel-modal-form">
            @csrf <input type="hidden" name="_method" value="POST" data-method-field>
            @if($errors->any())<div class="personnel-modal-errors"><strong>Please review the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <label>Driver ID <span class="personnel-required">*</span><input name="driver_id" value="{{ old('driver_id') }}" required></label>
            <label>Driver Name <span class="personnel-required">*</span><input name="driver_name" value="{{ old('driver_name') }}" required></label>
            <label>Default Shift <span class="personnel-required">*</span><select name="shift" required><option value="">Select shift</option>@foreach(['Morning','Afternoon','Night'] as $shift)<option value="{{ $shift }}" @selected(old('shift') === $shift)>{{ $shift }}</option>@endforeach</select></label>
            <label>Contact Number<input name="contact_number" value="{{ old('contact_number') }}"></label>
            <label>License Number<input name="license_number" value="{{ old('license_number') }}"></label>
            <label>License Expiration<input type="date" name="license_expiration" value="{{ old('license_expiration') }}"></label>
            <label class="full-width">Employment Status <span class="personnel-required">*</span><select name="employment_status" required><option value="Active">Active</option><option value="Inactive">Inactive</option></select></label>
            <div class="personnel-modal-actions"><button type="button" class="secondary-btn" data-close-personnel-modal>Cancel</button><button type="submit" class="primary-btn" data-submit-button><i class="fa-solid fa-floppy-disk"></i> Save Driver</button></div>
        </form>
    </div>
</div>
</x-layout.app>
