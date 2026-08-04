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
            subtitle="Manage permanent mechanic profiles and employment information"
            notification-count="0"
        />

        <section class="stats-grid personnel-stats-grid">
            <x-ui.summary-card label="Total Mechanics" value="{{ $mechanics->total() }}" small="All personnel records" icon="fa-users-gear" color="blue" />
            <x-ui.summary-card label="Active" value="{{ $mechanics->where('employment_status', 'Active')->count() }}" small="Active on this page" icon="fa-user-check" color="green" />
            <x-ui.summary-card label="Morning Shift" value="{{ $mechanics->where('shift', 'Morning')->count() }}" small="Mechanics on this page" icon="fa-sun" color="yellow" />
            <x-ui.summary-card label="Other Shifts" value="{{ $mechanics->where('shift', '!=', 'Morning')->count() }}" small="Afternoon and night" icon="fa-moon" color="red" />
        </section>

        <section class="table-card attendance-card personnel-master-panel">
            <div class="section-header personnel-section-header">
                <div>
                    <span class="personnel-module-label"><i class="fa-solid fa-address-book"></i> Personnel Management</span>
                    <h2>Mechanic Records</h2>
                    <p>Maintain permanent mechanic details here. Daily time records remain in Mechanic Attendance.</p>
                </div>
            </div>

            <form method="GET" action="{{ route('operation.personnel.mechanics', [], false) }}" class="toolbar attendance-toolbar personnel-master-toolbar">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search mechanic ID, name, shift, or specialization...">
                </div>
                <button class="secondary-btn personnel-search-btn" type="submit">
                    <i class="fa-solid fa-magnifying-glass"></i> Search
                </button>
                <button type="button" class="primary-btn personnel-add-btn" data-open-personnel-modal>
                    <i class="fa-solid fa-plus"></i> Add Mechanic
                </button>
            </form>

            <div class="table-wrap personnel-master-table-wrap">
                <table class="attendance-table personnel-master-table">
                    <thead>
                        <tr>
                            <th>Mechanic ID</th>
                            <th>Mechanic</th>
                            <th>Shift</th>
                            <th>Specialization</th>
                            <th>Contact</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($mechanics as $mechanic)
                            <tr>
                                <td><span class="personnel-id">{{ $mechanic->mechanic_id }}</span></td>
                                <td>
                                    <div class="personnel-name-cell">
                                        <span class="personnel-avatar mechanic"><i class="fa-solid fa-screwdriver-wrench"></i></span>
                                        <div><strong>{{ $mechanic->mechanic_name }}</strong><small>Mechanic profile</small></div>
                                    </div>
                                </td>
                                <td><span class="personnel-shift"><i class="fa-regular fa-clock"></i> {{ $mechanic->shift }}</span></td>
                                <td>{{ $mechanic->specialization ?: '—' }}</td>
                                <td>{{ $mechanic->contact_number ?: '—' }}</td>
                                <td><span class="badge personnel-status {{ strtolower($mechanic->employment_status) }}">{{ $mechanic->employment_status }}</span></td>
                            </tr>
                        @empty
                            <x-ui.empty-row colspan="6" message="No mechanic master records found." />
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-ui.table-footer :items="$mechanics" />
        </section>
    </main>
</div>

<div class="personnel-modal-overlay" data-personnel-modal data-open-on-error="{{ $errors->any() ? 'true' : 'false' }}" aria-hidden="true">
    <div class="personnel-modal" role="dialog" aria-modal="true" aria-labelledby="addMechanicModalTitle">
        <div class="personnel-modal-header">
            <div class="personnel-modal-title">
                <div class="personnel-modal-icon"><i class="fa-solid fa-user-gear"></i></div>
                <div>
                    <span class="personnel-modal-eyebrow">Personnel Management</span>
                    <h2 id="addMechanicModalTitle">Add New Mechanic</h2>
                    <p>Create a permanent mechanic profile. Attendance is recorded separately.</p>
                </div>
            </div>
            <button type="button" class="personnel-modal-close" data-close-personnel-modal aria-label="Close">&times;</button>
        </div>

        <form method="POST" action="{{ route('operation.personnel.mechanics.store', [], false) }}" class="personnel-modal-form">
            @csrf

            @if($errors->any())
                <div class="personnel-modal-errors">
                    <strong>Please review the following:</strong>
                    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <label>Mechanic ID <span class="personnel-required">*</span><input name="mechanic_id" value="{{ old('mechanic_id') }}" required placeholder="e.g., CF-MEC-07"></label>
            <label>Mechanic Name <span class="personnel-required">*</span><input name="mechanic_name" value="{{ old('mechanic_name') }}" required placeholder="Full name"></label>
            <label>Default Shift <span class="personnel-required">*</span>
                <select name="shift" required>
                    <option value="">Select shift</option>
                    @foreach(['Morning', 'Afternoon', 'Night'] as $shift)<option value="{{ $shift }}" @selected(old('shift') === $shift)>{{ $shift }}</option>@endforeach
                </select>
            </label>
            <label>Specialization<input name="specialization" value="{{ old('specialization') }}" placeholder="Engine, electrical, body repair..."></label>
            <label>Contact Number<input name="contact_number" value="{{ old('contact_number') }}" placeholder="e.g., 0917 555 0000"></label>
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
