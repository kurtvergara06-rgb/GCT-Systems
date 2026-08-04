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
            ['label' => 'Dashboard', 'route' => 'dashboard-operation', 'icon' => 'fa-table-cells-large'],
            ['label' => 'Routes', 'route' => 'operation.routes', 'icon' => 'fa-route'],
            ['label' => 'Scheduling', 'icon' => 'fa-calendar-days', 'children' => [
                ['label' => 'Trip Schedule', 'route' => 'trip-schedule', 'icon' => 'fa-calendar-days'],
                ['label' => 'Driver & Bus Assignment', 'route' => 'driver-bus-assignment', 'icon' => 'fa-user-tie'],
                ['label' => 'Auto Scheduling', 'route' => 'auto-scheduling', 'icon' => 'fa-wand-magic-sparkles'],
            ]],
            ['label' => 'Personnel Management', 'icon' => 'fa-address-book', 'children' => [
                ['label' => 'Driver Master List', 'route' => 'operation.personnel.drivers', 'icon' => 'fa-id-card'],
                ['label' => 'Mechanic Master List', 'route' => 'operation.personnel.mechanics', 'icon' => 'fa-users-gear'],
            ]],
            ['label' => 'Attendance', 'icon' => 'fa-calendar-check', 'children' => [
                ['label' => 'Driver Attendance', 'route' => 'driver-attendance', 'icon' => 'fa-user-check'],
                ['label' => 'Mechanic Attendance', 'route' => 'mechanic-attendance', 'icon' => 'fa-clipboard-user'],
            ]],
            ['label' => 'Bus Master List', 'route' => 'bus-master-list', 'icon' => 'fa-bus'],
        ]"
    />

    <main class="main">
        <x-layout.topbar
            title="Driver Master List"
            subtitle="Manage permanent driver profiles separately from daily attendance"
            notification-count="0"
        />

        <div class="personnel-master-page">
            <section class="personnel-master-grid">
                <div class="personnel-master-card"><span>Total Drivers</span><strong>{{ $drivers->total() }}</strong></div>
                <div class="personnel-master-card"><span>Active on this page</span><strong>{{ $drivers->where('employment_status', 'Active')->count() }}</strong></div>
                <div class="personnel-master-card"><span>Morning Shift</span><strong>{{ $drivers->where('shift', 'Morning')->count() }}</strong></div>
                <div class="personnel-master-card"><span>Other Shifts</span><strong>{{ $drivers->where('shift', '!=', 'Morning')->count() }}</strong></div>
            </section>

            <section class="personnel-master-panel">
                <div class="personnel-master-header">
                    <div>
                        <h2>Driver Records</h2>
                        <p>Permanent personnel records only. Daily attendance is recorded in Driver Attendance.</p>
                    </div>
                    <div class="personnel-master-header-actions">
                        <button type="button" class="primary-btn personnel-add-btn" data-open-personnel-modal>
                            <i class="fa-solid fa-plus"></i> Add Driver
                        </button>
                    </div>
                </div>

                <form method="GET" class="personnel-master-toolbar">
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search ID, name, shift, or license...">
                    <button class="secondary-btn" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                </form>

                <div class="personnel-master-table-wrap">
                    <table class="personnel-master-table">
                        <thead><tr><th>Driver ID</th><th>Name</th><th>Shift</th><th>Contact</th><th>License</th><th>Expiration</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($drivers as $driver)
                                <tr>
                                    <td>{{ $driver->driver_id }}</td>
                                    <td><strong>{{ $driver->driver_name }}</strong></td>
                                    <td>{{ $driver->shift }}</td>
                                    <td>{{ $driver->contact_number ?: '—' }}</td>
                                    <td>{{ $driver->license_number ?: '—' }}</td>
                                    <td>{{ $driver->license_expiration?->format('M d, Y') ?? '—' }}</td>
                                    <td><span class="personnel-master-status {{ strtolower($driver->employment_status) }}">{{ $driver->employment_status }}</span></td>
                                </tr>
                            @empty
                                <x-ui.empty-row colspan="7" message="No driver master records found." />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-ui.table-footer :items="$drivers" />
            </section>
        </div>
    </main>
</div>

<div
    class="personnel-modal-overlay"
    data-personnel-modal
    data-open-on-error="{{ $errors->any() ? 'true' : 'false' }}"
    aria-hidden="true"
>
    <div class="personnel-modal" role="dialog" aria-modal="true" aria-labelledby="addDriverModalTitle">
        <div class="personnel-modal-header">
            <div class="personnel-modal-title">
                <div class="personnel-modal-icon"><i class="fa-solid fa-user-plus"></i></div>
                <div>
                    <h2 id="addDriverModalTitle">Add New Driver</h2>
                    <p>Enter the permanent profile information for the driver.</p>
                </div>
            </div>
            <button type="button" class="personnel-modal-close" data-close-personnel-modal aria-label="Close">&times;</button>
        </div>

        <form method="POST" action="{{ route('operation.personnel.drivers.store', [], false) }}" class="personnel-modal-form">
            @csrf

            @if($errors->any())
                <div class="personnel-modal-errors">
                    <strong>Please review the following:</strong>
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <label>Driver ID <span class="personnel-required">*</span>
                <input name="driver_id" value="{{ old('driver_id') }}" required placeholder="e.g., CF-DRV-07">
            </label>
            <label>Driver Name <span class="personnel-required">*</span>
                <input name="driver_name" value="{{ old('driver_name') }}" required placeholder="Full name">
            </label>
            <label>Default Shift <span class="personnel-required">*</span>
                <select name="shift" required>
                    <option value="">Select shift</option>
                    @foreach(['Morning', 'Afternoon', 'Night'] as $shift)
                        <option value="{{ $shift }}" @selected(old('shift') === $shift)>{{ $shift }}</option>
                    @endforeach
                </select>
            </label>
            <label>Contact Number
                <input name="contact_number" value="{{ old('contact_number') }}" placeholder="e.g., 0917 555 0000">
            </label>
            <label>License Number
                <input name="license_number" value="{{ old('license_number') }}" placeholder="e.g., N07-17-765432">
            </label>
            <label>License Expiration
                <input type="date" name="license_expiration" value="{{ old('license_expiration') }}">
            </label>
            <label class="full-width">Employment Status <span class="personnel-required">*</span>
                <select name="employment_status" required>
                    <option value="Active" @selected(old('employment_status', 'Active') === 'Active')>Active</option>
                    <option value="Inactive" @selected(old('employment_status') === 'Inactive')>Inactive</option>
                </select>
            </label>

            <div class="personnel-modal-actions">
                <button type="button" class="secondary-btn" data-close-personnel-modal>Cancel</button>
                <button type="submit" class="primary-btn"><i class="fa-solid fa-floppy-disk"></i> Save Driver</button>
            </div>
        </form>
    </div>
</div>
</x-layout.app>
