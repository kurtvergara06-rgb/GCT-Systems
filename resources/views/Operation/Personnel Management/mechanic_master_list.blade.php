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
<x-layout.sidebar department="Operation" />

    <main class="main">
        <x-layout.topbar title="Mechanic Master List" subtitle="Manage permanent mechanic profiles and employment information" notification-count="0" />

        <section data-ajax-region="summary" class="stats-grid personnel-stats-grid">
            <x-ui.summary-card label="Total Mechanics" value="{{ $stats['total'] }}" small="All mechanic profiles" icon="fa-users-gear" color="blue" />
            <x-ui.summary-card label="Active" value="{{ $stats['active'] }}" small="Available for attendance" icon="fa-user-check" color="green" />
            <x-ui.summary-card label="Inactive" value="{{ $stats['inactive'] }}" small="Deactivated profiles" icon="fa-user-slash" color="red" />
            <x-ui.summary-card label="Specializations" value="{{ $stats['specializations'] }}" small="Recorded skill groups" icon="fa-screwdriver-wrench" color="yellow" />
        </section>

        <section data-ajax-region="records" class="table-card attendance-card personnel-master-panel">
            <div class="section-header personnel-section-header"><div><span class="personnel-module-label"><i class="fa-solid fa-address-book"></i> Personnel Management</span><h2>Mechanic Records</h2><p>Permanent mechanic information only. Daily transactions remain in Mechanic Attendance.</p></div></div>

            <form method="GET" action="{{ route('operation.personnel.mechanics', [], false) }}" class="toolbar attendance-toolbar personnel-master-toolbar">
                <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="search" value="{{ request('search') }}" placeholder="Search ID, name, contact, shift, or specialization..."></div>
                <div class="filter-group"><label>Status</label><select name="status" onchange="this.form.submit()"><option value="">All Status</option><option value="Active" @selected(request('status') === 'Active')>Active</option><option value="Inactive" @selected(request('status') === 'Inactive')>Inactive</option></select></div>
                <button class="secondary-btn personnel-search-btn" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                <button type="button" class="primary-btn personnel-add-btn" data-personnel-action="add"><i class="fa-solid fa-plus"></i> Add Mechanic</button>
            </form>

            <div class="table-wrap personnel-master-table-wrap">
                <table class="attendance-table personnel-master-table">
                    <thead><tr><th>Mechanic ID</th><th>Mechanic</th><th>Default Shift</th><th>Contact</th><th>Specialization</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    @forelse($mechanics as $mechanic)
                        @php
                            $mechanicRecord = e(json_encode([
                                'mechanic_id' => $mechanic->mechanic_id,
                                'mechanic_name' => $mechanic->mechanic_name,
                                'shift' => $mechanic->shift,
                                'contact_number' => $mechanic->contact_number,
                                'specialization' => $mechanic->specialization,
                                'employment_status' => $mechanic->employment_status,
                            ], JSON_THROW_ON_ERROR));
                        @endphp
                        <tr>
                            <td><span class="personnel-id">{{ $mechanic->mechanic_id }}</span></td>
                            <td><div class="personnel-name-cell"><span class="personnel-avatar mechanic"><i class="fa-solid fa-screwdriver-wrench"></i></span><div><strong>{{ $mechanic->mechanic_name }}</strong><small>Mechanic profile</small></div></div></td>
                            <td><span class="personnel-shift"><i class="fa-regular fa-clock"></i> {{ $mechanic->shift }}</span></td>
                            <td>{{ $mechanic->contact_number ?: '—' }}</td>
                            <td>{{ $mechanic->specialization ?: '—' }}</td>
                            <td><span class="badge personnel-status {{ strtolower($mechanic->employment_status) }}">{{ $mechanic->employment_status }}</span></td>
                            <td><div class="actions">
                                <button type="button" class="action-btn view" title="View" data-personnel-action="view" data-record="{{ $mechanicRecord }}"><i class="fa-solid fa-eye"></i></button>
                                <button type="button" class="action-btn edit" title="Edit" data-personnel-action="edit" data-update-url="{{ route('operation.personnel.mechanics.update', $mechanic, false) }}" data-record="{{ $mechanicRecord }}"><i class="fa-solid fa-pen-to-square"></i></button>
                                @if($mechanic->employment_status === 'Active')
                                <form method="POST" action="{{ route('operation.personnel.mechanics.deactivate', $mechanic, false) }}" data-confirm-form data-confirm-title="Deactivate Mechanic?" data-confirm-message="This removes the mechanic from active attendance rosters but preserves historical records." data-confirm-button="Deactivate" data-confirm-type="warning">@csrf @method('PATCH')<button type="submit" class="action-btn delete" title="Deactivate"><i class="fa-solid fa-user-slash"></i></button></form>
                                @endif
                            </div></td>
                        </tr>
                    @empty
                        <x-ui.empty-row colspan="7" message="No mechanic master records found." />
                    @endforelse
                    </tbody>
                </table>
            </div>
            <x-ui.table-footer :items="$mechanics" />
        </section>
    </main>
</div>

<div class="personnel-modal-overlay" data-personnel-modal data-open-on-error="{{ $errors->any() ? 'true' : 'false' }}" aria-hidden="true">
    <div class="personnel-modal" role="dialog" aria-modal="true" aria-labelledby="personnelModalTitle">
        <div class="personnel-modal-header"><div class="personnel-modal-title"><div class="personnel-modal-icon"><i class="fa-solid fa-user-gear"></i></div><div><span class="personnel-modal-eyebrow">Personnel Management</span><h2 id="personnelModalTitle" data-modal-title>Add New Mechanic</h2><p data-modal-subtitle>Create a permanent mechanic profile. Attendance is recorded separately.</p></div></div><button type="button" class="personnel-modal-close" data-close-personnel-modal aria-label="Close">&times;</button></div>
        <form method="POST" action="{{ route('operation.personnel.mechanics.store', [], false) }}" data-personnel-form data-store-url="{{ route('operation.personnel.mechanics.store', [], false) }}" class="personnel-modal-form">
            @csrf <input type="hidden" name="_method" value="POST" data-method-field>
            @if($errors->any())<div class="personnel-modal-errors"><strong>Please review the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <label>Mechanic ID <span class="personnel-required">*</span><input name="mechanic_id" value="{{ old('mechanic_id') }}" required></label>
            <label>Mechanic Name <span class="personnel-required">*</span><input name="mechanic_name" value="{{ old('mechanic_name') }}" required></label>
            <label>Default Shift <span class="personnel-required">*</span><select name="shift" required><option value="">Select shift</option>@foreach(['Morning','Afternoon','Night'] as $shift)<option value="{{ $shift }}" @selected(old('shift') === $shift)>{{ $shift }}</option>@endforeach</select></label>
            <label>Contact Number<input name="contact_number" value="{{ old('contact_number') }}"></label>
            <label>Specialization<input name="specialization" value="{{ old('specialization') }}"></label>
            <label>Employment Status <span class="personnel-required">*</span><select name="employment_status" required><option value="Active">Active</option><option value="Inactive">Inactive</option></select></label>
            <div class="personnel-modal-actions"><button type="button" class="secondary-btn" data-close-personnel-modal>Cancel</button><button type="submit" class="primary-btn" data-submit-button><i class="fa-solid fa-floppy-disk"></i> Save Mechanic</button></div>
        </form>
    </div>
</div>
</x-layout.app>
