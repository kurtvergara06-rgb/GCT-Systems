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
@php
    $canEditOperation = auth()->user()?->hasSystemPermission('operation', 'edit') ?? false;
@endphp
<div class="app">
<x-layout.sidebar department="Operation" />

    <main class="main personnel-master-page">
        <x-layout.topbar title="Mechanic Master List" subtitle="Manage mechanic profiles, specializations, and employment information" notification-count="0" />

        <section data-ajax-region="summary" class="stats-grid personnel-stats-grid">
            <x-ui.summary-card label="Total Mechanics" value="{{ $stats['total'] }}" small="All mechanic profiles" icon="fa-users" color="blue" />
            <x-ui.summary-card label="Active Mechanics" value="{{ $stats['active'] }}" small="Available for attendance" icon="fa-user-check" color="green" />
            <x-ui.summary-card label="Inactive Mechanics" value="{{ $stats['inactive'] }}" small="Deactivated profiles" icon="fa-user-slash" color="red" />
            <x-ui.summary-card label="Specialization Categories" value="{{ $stats['specializations'] }}" small="Recorded skill groups" icon="fa-screwdriver-wrench" color="yellow" />
        </section>

        <section data-ajax-region="records" class="table-card attendance-card personnel-master-panel">
            <div class="section-header personnel-section-header">
                <div>
                    <h2>Mechanic Records</h2>
                    <p>Permanent mechanic information only. Daily attendance remains separate from mechanic skills and profile data.</p>
                </div>
            </div>

            <form
                method="GET"
                action="{{ route('operation.personnel.mechanics', [], false) }}"
                class="toolbar attendance-toolbar personnel-master-toolbar mechanic-master-toolbar"
                data-server-filter="true"
            >
                <div class="search-box personnel-search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search mechanic ID, name, shift, or specialization..."
                        aria-label="Search mechanic records"
                    >
                </div>

                <div class="filter-group personnel-filter">
                    <label class="sr-only" for="mechanicStatusFilter">Status</label>
                    <select id="mechanicStatusFilter" name="status" aria-label="Filter by status">
                        <option value="">All Statuses</option>
                        <option value="Active" @selected(request('status') === 'Active')>Active</option>
                        <option value="Inactive" @selected(request('status') === 'Inactive')>Inactive</option>
                    </select>
                </div>

                <div class="filter-group personnel-filter personnel-filter-specialization">
                    <label class="sr-only" for="mechanicSpecializationFilter">Specialization</label>
                    <select id="mechanicSpecializationFilter" name="specialization" aria-label="Filter by specialization">
                        <option value="">All Specializations</option>
                        @foreach($specializationOptions as $specialization)
                            <option value="{{ $specialization }}" @selected(request('specialization') === $specialization)>{{ $specialization }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-group personnel-filter">
                    <label class="sr-only" for="mechanicShiftFilter">Shift</label>
                    <select id="mechanicShiftFilter" name="shift" aria-label="Filter by default shift">
                        <option value="">All Shifts</option>
                        @foreach(['Morning', 'Afternoon'] as $shift)
                            <option value="{{ $shift }}" @selected(request('shift') === $shift)>{{ $shift }}</option>
                        @endforeach
                    </select>
                </div>

                @if($canEditOperation)
                <button type="button" class="primary-btn personnel-add-btn" data-personnel-action="add">
                    <i class="fa-solid fa-plus"></i>
                    <span>Add Mechanic</span>
                </button>
                @endif
            </form>

            <div class="personnel-filter-loading" data-server-filter-loading hidden>
                <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>
                Updating mechanic records...
            </div>

            <div class="table-wrap personnel-master-table-wrap">
                <table class="attendance-table personnel-master-table">
                    <colgroup>
                        <col class="mechanic-col-id">
                        <col class="mechanic-col-name">
                        <col class="mechanic-col-shift">
                        <col class="mechanic-col-specializations">
                        <col class="mechanic-col-status">
                        <col class="mechanic-col-actions">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Mechanic ID</th>
                            <th>Mechanic</th>
                            <th>Default Shift</th>
                            <th>Specializations</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($mechanics as $mechanic)
                        @php
                            $mechanicSpecializations = $mechanic->specialization_labels ?? collect();

                            $mechanicRecord = e(json_encode([
                                'mechanic_id' => $mechanic->mechanic_id,
                                'mechanic_name' => $mechanic->mechanic_name,
                                'shift' => $mechanic->shift,
                                'contact_number' => $mechanic->contact_number,
                                'specialization' => $mechanic->specialization_canonical ?? $mechanic->specialization,
                                'employment_status' => $mechanic->employment_status,
                            ], JSON_THROW_ON_ERROR));
                        @endphp
                        <tr>
                            <td><span class="personnel-id">{{ $mechanic->mechanic_id }}</span></td>
                            <td>
                                <div class="personnel-name-cell">
                                    <span class="personnel-avatar mechanic"><i class="fa-solid fa-user"></i></span>
                                    <div>
                                        <strong>{{ $mechanic->mechanic_name }}</strong>
                                        <small>Mechanic profile</small>
                                    </div>
                                </div>
                            </td>
                            <td><span class="gct-pill gct-pill--shift-{{ strtolower($mechanic->shift) }}">{{ $mechanic->shift }}</span></td>
                            <td>
                                @if($mechanicSpecializations->isNotEmpty())
                                    @php
                                        $visibleSpecializations = $mechanicSpecializations->take(3);
                                        $hiddenSpecializations = $mechanicSpecializations->slice(3);
                                    @endphp
                                    <div class="personnel-specialization-list">
                                        @foreach($visibleSpecializations as $specialization)
                                            @php
                                                $specializationTone = match ($specialization) {
                                                    'Electrical', 'Aircon' => 'blue',
                                                    'Diagnostics', 'Bodyworks', 'Welding & Fabrication' => 'purple',
                                                    'Suspension', 'Tires & Wheels' => 'green',
                                                    'Engine', 'Brakes' => 'red',
                                                    'Transmission', 'Fuel System' => 'orange',
                                                    'Lubrication', 'Preventive Maintenance' => 'yellow',
                                                    default => 'blue',
                                                };
                                            @endphp
                                            <span class="personnel-specialization-chip tone-{{ $specializationTone }}">{{ $specialization }}</span>
                                        @endforeach
                                        @if($hiddenSpecializations->isNotEmpty())
                                            <span
                                                class="personnel-specialization-more"
                                                title="{{ $hiddenSpecializations->implode(', ') }}"
                                                aria-label="{{ $hiddenSpecializations->count() }} more specializations: {{ $hiddenSpecializations->implode(', ') }}"
                                            >
                                                +{{ $hiddenSpecializations->count() }} more
                                            </span>
                                        @endif
                                    </div>
                                @else
                                    <span class="personnel-empty-value">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge personnel-status gct-pill gct-pill--{{ strtolower($mechanic->employment_status) }}">
                                    {{ $mechanic->employment_status }}
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <button type="button" class="action-btn view" title="View" aria-label="View {{ $mechanic->mechanic_name }}" data-personnel-action="view" data-record="{!! $mechanicRecord !!}"><i class="fa-solid fa-eye"></i></button>
                                    @if($canEditOperation)
                                    <button type="button" class="action-btn edit" title="Edit" aria-label="Edit {{ $mechanic->mechanic_name }}" data-personnel-action="edit" data-record-id="{{ $mechanic->id }}" data-update-url="{{ route('operation.personnel.mechanics.update', $mechanic, false) }}" data-record="{!! $mechanicRecord !!}"><i class="fa-solid fa-pen-to-square"></i></button>
                                    @if($mechanic->employment_status === 'Active')
                                    <form method="POST" action="{{ route('operation.personnel.mechanics.deactivate', $mechanic, false) }}" data-confirm-form data-confirm-title="Deactivate Mechanic?" data-confirm-message="This removes the mechanic from active attendance rosters but preserves historical records." data-confirm-button="Deactivate" data-confirm-type="warning">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="action-btn delete" @if($mechanic->deactivation_locked) disabled aria-disabled="true" title="Locked: ongoing Maintenance Job Order" @else title="Deactivate" @endif aria-label="Deactivate {{ $mechanic->mechanic_name }}"><i class="fa-solid fa-user-slash"></i></button>
                                    </form>
                                    @else
                                    <form method="POST" action="{{ route('operation.personnel.mechanics.activate', $mechanic, false) }}" data-confirm-form data-confirm-title="Reactivate Mechanic?" data-confirm-message="This restores the mechanic profile to Active so it can be used in future attendance and assignments." data-confirm-button="Reactivate" data-confirm-type="info">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="action-btn activate" title="Reactivate" aria-label="Reactivate {{ $mechanic->mechanic_name }}"><i class="fa-solid fa-user-check"></i></button>
                                    </form>
                                    @endif
                                    @endif
                                </div>
                            </td>
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

@if($canEditOperation)
<div class="personnel-modal-overlay" data-personnel-modal data-open-on-error="{{ $errors->any() ? 'true' : 'false' }}" aria-hidden="true">
    <div class="personnel-modal personnel-modal-mechanic" role="dialog" aria-modal="true" aria-labelledby="personnelModalTitle">
        <div class="personnel-modal-header personnel-modal-header-mechanic">
            <div class="personnel-modal-title">
                <div class="personnel-modal-icon"><i class="fa-solid fa-user-plus"></i></div>
                <div>
                    <h2 id="personnelModalTitle" data-modal-title>Add New Mechanic</h2>
                    <p data-modal-subtitle>Fill in the details below to create a mechanic profile. Attendance is recorded separately.</p>
                </div>
            </div>
            <button type="button" class="personnel-modal-close" data-close-personnel-modal aria-label="Cancel and close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('operation.personnel.mechanics.store', [], false) }}" data-personnel-form data-store-url="{{ route('operation.personnel.mechanics.store', [], false) }}" class="personnel-modal-form personnel-mechanic-form">
            @csrf
            <input type="hidden" name="_method" value="POST" data-method-field>
            <input type="hidden" name="editing_personnel_id" value="{{ old('editing_personnel_id') }}" data-editing-personnel-id>

            @if($errors->any())
                <div class="personnel-modal-errors">
                    <strong>Please review the following:</strong>
                    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <label class="personnel-input-field">
                <span>Mechanic ID <span class="personnel-required">*</span></span>
                <div class="personnel-input-shell">
                    <i class="fa-solid fa-hashtag" aria-hidden="true"></i>
                    <input name="mechanic_id" value="{{ old('mechanic_id') }}" placeholder="Enter mechanic ID" required>
                </div>
            </label>

            <label class="personnel-input-field">
                <span>Mechanic Name <span class="personnel-required">*</span></span>
                <div class="personnel-input-shell">
                    <i class="fa-solid fa-user" aria-hidden="true"></i>
                    <input name="mechanic_name" value="{{ old('mechanic_name') }}" placeholder="Enter full name" required>
                </div>
            </label>

            <label class="personnel-input-field">
                <span>Default Shift <span class="personnel-required">*</span></span>
                <div class="personnel-input-shell">
                    <i class="fa-regular fa-clock" aria-hidden="true"></i>
                    <select name="shift" required>
                        <option value="">Select shift</option>
                        @foreach(['Morning','Afternoon'] as $shift)
                            <option value="{{ $shift }}" @selected(old('shift') === $shift)>{{ $shift }}</option>
                        @endforeach
                    </select>
                </div>
            </label>

            <label class="personnel-input-field">
                <span>Contact Number</span>
                <div class="personnel-input-shell">
                    <i class="fa-solid fa-phone" aria-hidden="true"></i>
                    <input name="contact_number" value="{{ old('contact_number') }}" placeholder="Enter contact number">
                </div>
            </label>

            <div class="personnel-specialization-field full-width">
                <div class="personnel-specialization-topline">
                    <div class="personnel-field-heading">
                        <div>
                            <span>Specializations</span>
                            <small>Select every vehicle system this mechanic is qualified to service.</small>
                        </div>
                    </div>

                    <div class="personnel-specialization-search">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" placeholder="Search specializations..." data-specialization-search autocomplete="off">
                    </div>
                </div>

                <input type="hidden" name="specialization" value="{{ old('specialization') }}" data-specialization-value>

                <div class="personnel-specialization-picker" data-specialization-picker>
                    @foreach($specializationOptions as $specialization)
                        @php
                            $specializationTone = match ($specialization) {
                                'Electrical', 'Aircon' => 'blue',
                                'Diagnostics', 'Bodyworks', 'Welding & Fabrication' => 'purple',
                                'Suspension', 'Tires & Wheels' => 'green',
                                'Engine', 'Brakes' => 'red',
                                'Transmission', 'Fuel System' => 'orange',
                                'Lubrication', 'Preventive Maintenance' => 'yellow',
                                default => 'blue',
                            };

                            $specializationIcon = match ($specialization) {
                                'Aircon' => 'fa-snowflake',
                                'Bodyworks' => 'fa-car-side',
                                'Brakes' => 'fa-compact-disc',
                                'Diagnostics' => 'fa-wave-square',
                                'Electrical' => 'fa-bolt',
                                'Engine' => 'fa-gears',
                                'Fuel System' => 'fa-gas-pump',
                                'Lubrication' => 'fa-droplet',
                                'Preventive Maintenance' => 'fa-screwdriver-wrench',
                                'Suspension' => 'fa-grip-lines-vertical',
                                'Tires & Wheels' => 'fa-circle-dot',
                                'Transmission' => 'fa-gear',
                                'Welding & Fabrication' => 'fa-wand-magic-sparkles',
                                default => 'fa-wrench',
                            };
                        @endphp
                        <button
                            type="button"
                            class="personnel-specialization-option tone-{{ $specializationTone }}"
                            data-specialization-option="{{ $specialization }}"
                            aria-pressed="false"
                        >
                            <i class="fa-solid {{ $specializationIcon }} personnel-specialization-leading-icon" aria-hidden="true"></i>
                            <span>{{ $specialization }}</span>
                            <i class="fa-solid fa-plus personnel-specialization-state-icon" aria-hidden="true"></i>
                        </button>
                    @endforeach
                </div>

                <div class="personnel-selected-panel">
                    <div class="personnel-selected-panel-title">
                        <strong>Selected Specializations (<span data-specialization-count-number>0</span>)</strong>
                        <span class="personnel-selected-count" data-specialization-count>0 selected</span>
                    </div>

                    <div class="personnel-specialization-selected" data-specialization-selected aria-live="polite"></div>
                </div>
            </div>

            <label class="personnel-input-field personnel-employment-field">
                <span>Employment Status <span class="personnel-required">*</span></span>
                <div class="personnel-input-shell personnel-status-input-shell">
                    <select name="employment_status" required>
                        <option value="Active" @selected(old('employment_status', 'Active') === 'Active')>Active</option>
                        <option value="Inactive" @selected(old('employment_status') === 'Inactive')>Inactive</option>
                    </select>
                </div>
            </label>

            <div class="personnel-modal-actions">
                <button type="button" class="secondary-btn" data-close-personnel-modal>Cancel</button>
                <button type="submit" class="primary-btn" data-submit-button><i class="fa-solid fa-floppy-disk"></i> Save Mechanic</button>
            </div>
        </form>
    </div>
</div>
@endif

</x-layout.app>
