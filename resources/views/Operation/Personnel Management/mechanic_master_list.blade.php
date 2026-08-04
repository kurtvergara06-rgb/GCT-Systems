<x-layout.app
    title="FROMS - Mechanic Master List"
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
                    <div class="personnel-master-header-actions">
                        <button type="button" class="primary-btn personnel-add-btn" data-open-personnel-modal>
                            <i class="fa-solid fa-plus"></i> Add Mechanic
                        </button>
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
    <div class="personnel-modal" role="dialog" aria-modal="true" aria-labelledby="addMechanicModalTitle">
        <div class="personnel-modal-header">
            <div class="personnel-modal-title">
                <div class="personnel-modal-icon"><i class="fa-solid fa-user-gear"></i></div>
                <div>
                    <h2 id="addMechanicModalTitle">Add New Mechanic</h2>
                    <p>Enter the permanent profile information for the mechanic.</p>
                </div>
            </div>
            <button type="button" class="personnel-modal-close" data-close-personnel-modal aria-label="Close">&times;</button>
        </div>

        <form method="POST" action="{{ route('operation.personnel.mechanics.store', [], false) }}" class="personnel-modal-form">
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

            <label>Mechanic ID <span class="personnel-required">*</span>
                <input name="mechanic_id" value="{{ old('mechanic_id') }}" required placeholder="e.g., CF-MEC-07">
            </label>
            <label>Mechanic Name <span class="personnel-required">*</span>
                <input name="mechanic_name" value="{{ old('mechanic_name') }}" required placeholder="Full name">
            </label>
            <label>Default Shift <span class="personnel-required">*</span>
                <select name="shift" required>
                    <option value="">Select shift</option>
                    @foreach(['Morning', 'Afternoon', 'Night'] as $shift)
                        <option value="{{ $shift }}" @selected(old('shift') === $shift)>{{ $shift }}</option>
                    @endforeach
                </select>
            </label>
            <label>Specialization
                <input name="specialization" value="{{ old('specialization') }}" placeholder="Engine, electrical, body repair...">
            </label>
            <label>Contact Number
                <input name="contact_number" value="{{ old('contact_number') }}" placeholder="e.g., 0917 555 0000">
            </label>
            <label>Employment Status <span class="personnel-required">*</span>
                <select name="employment_status" required>
                    <option value="Active" @selected(old('employment_status', 'Active') === 'Active')>Active</option>
                    <option value="Inactive" @selected(old('employment_status') === 'Inactive')>Inactive</option>
                </select>
            </label>

            <div class="personnel-modal-actions">
                <button type="button" class="secondary-btn" data-close-personnel-modal>Cancel</button>
                <button type="submit" class="primary-btn"><i class="fa-solid fa-floppy-disk"></i> Save Mechanic</button>
            </div>
        </form>
    </div>
</div>
</x-layout.app>
