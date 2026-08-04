<x-layout.app
    title="FROMS - Mechanic Master List"
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
            title="Mechanic Master List"
            subtitle="Manage permanent mechanic profiles separately from daily attendance"
            notification-count="0"
        />

        <div class="personnel-master-page">
            <section class="personnel-master-grid">
                <div class="personnel-master-card"><span>Total Mechanics</span><strong>{{ $mechanics->total() }}</strong></div>
                <div class="personnel-master-card"><span>Active on this page</span><strong>{{ $mechanics->where('employment_status', 'Active')->count() }}</strong></div>
                <div class="personnel-master-card"><span>Morning Shift</span><strong>{{ $mechanics->where('shift', 'Morning')->count() }}</strong></div>
                <div class="personnel-master-card"><span>Other Shifts</span><strong>{{ $mechanics->where('shift', '!=', 'Morning')->count() }}</strong></div>
            </section>

            <section class="personnel-master-panel">
                <div class="personnel-master-header">
                    <div>
                        <h2>Mechanic Records</h2>
                        <p>Permanent personnel records only. Daily attendance is recorded in Mechanic Attendance.</p>
                    </div>
                </div>

                <form method="GET" class="personnel-master-toolbar">
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search ID, name, shift, or specialization...">
                    <button class="secondary-btn" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                </form>

                <div class="personnel-master-table-wrap">
                    <table class="personnel-master-table">
                        <thead><tr><th>Mechanic ID</th><th>Name</th><th>Shift</th><th>Specialization</th><th>Contact</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($mechanics as $mechanic)
                                <tr>
                                    <td>{{ $mechanic->mechanic_id }}</td>
                                    <td><strong>{{ $mechanic->mechanic_name }}</strong></td>
                                    <td>{{ $mechanic->shift }}</td>
                                    <td>{{ $mechanic->specialization ?: '—' }}</td>
                                    <td>{{ $mechanic->contact_number ?: '—' }}</td>
                                    <td><span class="personnel-master-status {{ strtolower($mechanic->employment_status) }}">{{ $mechanic->employment_status }}</span></td>
                                </tr>
                            @empty
                                <x-ui.empty-row colspan="6" message="No mechanic master records found." />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-ui.table-footer :items="$mechanics" />

                <form method="POST" action="{{ route('operation.personnel.mechanics.store', [], false) }}" class="personnel-master-form">
                    @csrf
                    <label>Mechanic ID<input name="mechanic_id" required placeholder="M-0001"></label>
                    <label>Mechanic Name<input name="mechanic_name" required></label>
                    <label>Default Shift<select name="shift"><option>Morning</option><option>Afternoon</option><option>Night</option></select></label>
                    <label>Specialization<input name="specialization" placeholder="Engine, electrical, body repair..."></label>
                    <label>Contact Number<input name="contact_number"></label>
                    <label>Employment Status<select name="employment_status"><option>Active</option><option>Inactive</option></select></label>
                    <div class="form-actions"><button class="primary-btn" type="submit"><i class="fa-solid fa-plus"></i> Add Mechanic</button></div>
                </form>
            </section>
        </div>
    </main>
</div>
</x-layout.app>
