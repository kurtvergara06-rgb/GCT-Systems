<x-layout.app
    title="FROMS - Driver Master List"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Main-styles/form-components.css',
        'resources/css/Operation/Attendance/personnel-master.css',
        'resources/js/Main-js/sidebar.js'
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

                <form method="POST" action="{{ route('operation.personnel.drivers.store', [], false) }}" class="personnel-master-form">
                    @csrf
                    <label>Driver ID<input name="driver_id" required placeholder="DRV-0001"></label>
                    <label>Driver Name<input name="driver_name" required></label>
                    <label>Default Shift<select name="shift"><option>Morning</option><option>Afternoon</option><option>Night</option></select></label>
                    <label>Contact Number<input name="contact_number"></label>
                    <label>License Number<input name="license_number"></label>
                    <label>License Expiration<input type="date" name="license_expiration"></label>
                    <label>Employment Status<select name="employment_status"><option>Active</option><option>Inactive</option></select></label>
                    <div class="form-actions"><button class="primary-btn" type="submit"><i class="fa-solid fa-plus"></i> Add Driver</button></div>
                </form>
            </section>
        </div>
    </main>
</div>
</x-layout.app>
