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
    $isMaintenanceUser = request()->routeIs('maintenance.personnel.mechanics*')
        || strtolower(trim((string) auth()->user()?->department)) === 'maintenance';
    $mechanicPermissionModule = $isMaintenanceUser ? 'maintenance' : 'operation';
    $mechanicRouteNames = $isMaintenanceUser ? [
        'index' => 'maintenance.personnel.mechanics',
        'store' => 'maintenance.personnel.mechanics.store',
        'update' => 'maintenance.personnel.mechanics.update',
        'deactivate' => 'maintenance.personnel.mechanics.deactivate',
        'activate' => 'maintenance.personnel.mechanics.activate',
    ] : [
        'index' => 'operation.personnel.mechanics',
        'store' => 'operation.personnel.mechanics.store',
        'update' => 'operation.personnel.mechanics.update',
        'deactivate' => 'operation.personnel.mechanics.deactivate',
        'activate' => 'operation.personnel.mechanics.activate',
    ];
    $canEditMechanic = auth()->user()?->hasSystemPermission($mechanicPermissionModule, 'edit') ?? false;
@endphp
<div class="app">
<x-layout.sidebar :department="$isMaintenanceUser ? 'Maintenance' : 'Operation'" />

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
@if($canEditMechanic)
                <button type="button" class="primary-btn personnel-add-btn" data-personnel-action="add">
                    <i class="fa-solid fa-plus"></i>
                    <span>Add Mechanic</span>
                </button>
                @endif

            </div>


            <div class="gct-table-toolbar-row"><nav class="personnel-record-tabs" aria-label="Mechanic record groups">
                <a class="personnel-record-tab {{ request('tab', 'active') !== 'history' ? 'is-active' : '' }}" href="{{ route($mechanicRouteNames['index'], [], false) }}?{{ http_build_query(array_merge(request()->except(['tab', 'status', 'page']), ['tab' => 'active'])) }}"><i class="fa-solid fa-user-gear" aria-hidden="true"></i> Active Mechanics</a>
                <a class="personnel-record-tab {{ request('tab') === 'history' ? 'is-active' : '' }}" href="{{ route($mechanicRouteNames['index'], [], false) }}?{{ http_build_query(array_merge(request()->except(['tab', 'status', 'page']), ['tab' => 'history'])) }}"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> History</a>
            </nav>
            <x-ui.table-toolbar action="{{ route($mechanicRouteNames['index'], [], false) }}" search-label="Search Mechanics" search-placeholder="Search ID, name, or specialization..." :filter-count="(int) request()->filled('specialization')" filter-title="Filter Mechanics" :sort-options="['mechanic_name' => 'Mechanic Name', 'mechanic_id' => 'Mechanic ID', 'employment_status' => 'Status']" default-sort="mechanic_name">
                <x-slot:hidden><input type="hidden" name="tab" value="{{ request('tab') === 'history' ? 'history' : 'active' }}"></x-slot:hidden>
                <x-slot:filters>
                    <x-ui.table-toolbar-field label="Specialization" name="specialization"><option value="">All Specializations</option>@foreach($specializationOptions as $specialization)<option value="{{ $specialization }}" @selected(request('specialization') === $specialization)>{{ $specialization }}</option>@endforeach</x-ui.table-toolbar-field>
                </x-slot:filters>
            </x-ui.table-toolbar></div>

            <div class="personnel-filter-loading" data-server-filter-loading hidden>
                <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>
                Updating mechanic records...
            </div>

            <div class="table-wrap personnel-master-table-wrap">
                <table class="attendance-table personnel-master-table">
                    <colgroup>
                        <col class="mechanic-col-id">
                        <col class="mechanic-col-name">
                        <col class="mechanic-col-specializations">
                        <col class="mechanic-col-status">
                        <col class="mechanic-col-actions">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="mechanic-table-strong-heading">Mechanic ID</th>
                            <th class="mechanic-table-strong-heading">Mechanic</th>
                            <th class="mechanic-table-strong-heading">Specializations</th>
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
                                <x-ui.status-badge :status="$mechanic->employment_status" class="personnel-status gct-pill gct-pill--{{ strtolower($mechanic->employment_status) }}" />
                            </td>
                            <td>
                                <div class="actions">
                                    <button type="button" class="action-btn view" title="View" aria-label="View {{ $mechanic->mechanic_name }}" data-personnel-action="view" data-record="{!! $mechanicRecord !!}"><i class="fa-solid fa-eye"></i></button>
                                    @if($canEditMechanic)
                                    <button type="button" class="action-btn edit" title="Edit" aria-label="Edit {{ $mechanic->mechanic_name }}" data-personnel-action="edit" data-record-id="{{ $mechanic->id }}" data-update-url="{{ route($mechanicRouteNames['update'], $mechanic, false) }}" data-record="{!! $mechanicRecord !!}"><i class="fa-solid fa-pen-to-square"></i></button>
                                    @if($mechanic->employment_status === 'Active')
                                    <form method="POST" action="{{ route($mechanicRouteNames['deactivate'], $mechanic, false) }}" data-confirm-form data-confirm-title="Deactivate Mechanic?" data-confirm-message="This removes the mechanic from active attendance rosters but preserves historical records." data-confirm-button="Deactivate" data-confirm-type="warning">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="action-btn delete" @if($mechanic->deactivation_locked) disabled aria-disabled="true" title="Locked: ongoing Maintenance Job Order" @else title="Deactivate" @endif aria-label="Deactivate {{ $mechanic->mechanic_name }}"><i class="fa-solid fa-user-slash"></i></button>
                                    </form>
                                    @else
                                    <form method="POST" action="{{ route($mechanicRouteNames['activate'], $mechanic, false) }}" data-confirm-form data-confirm-title="Reactivate Mechanic?" data-confirm-message="This restores the mechanic profile to Active so it can be used in future attendance and assignments." data-confirm-button="Reactivate" data-confirm-type="info">
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
                        <x-ui.empty-row
                            colspan="6"
                            icon="fa-wrench"
                            message="No Mechanic Records Found"
                            description="No mechanic profiles match your search or filter criteria. Try adjusting your filters."
                        />
                    @endforelse
                    </tbody>
                </table>
            </div>
            <x-ui.table-footer :items="$mechanics" />
        </section>
    </main>
</div>

@if($canEditMechanic)
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

        <form method="POST" action="{{ route($mechanicRouteNames['store'], [], false) }}" data-personnel-form data-store-url="{{ route($mechanicRouteNames['store'], [], false) }}" class="personnel-modal-form personnel-mechanic-form">
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

                    @if(request()->routeIs('maintenance.personnel.mechanics') && auth()->user()?->hasSystemPermission('maintenance', 'edit'))
                        <div class="personnel-specialization-inline-create" style="display:flex;align-items:center;gap:8px;max-width:100%;min-width:0;margin-left:auto">
                            <input type="text" data-specialization-create-name maxlength="100" hidden placeholder="New specialization name" aria-label="New specialization name" style="width:200px;max-width:min(200px,45vw);height:32px;padding:0 10px;border:1px solid #d8e2ef;border-radius:8px;font-size:11px;box-sizing:border-box">
                            <button type="button" class="personnel-specialization-create-btn" data-specialization-create-toggle data-specialization-create-save data-endpoint="{{ route('maintenance.personnel.mechanics.specializations.store') }}" aria-expanded="false" style="display:inline-flex;align-items:center;justify-content:center;gap:5px;min-height:32px;padding:0 10px;background:#ffc400;border:0;border-radius:8px;font-size:11px;font-weight:700;line-height:1;white-space:nowrap;cursor:pointer">
                                <i class="fa-solid fa-plus" aria-hidden="true"></i><span data-specialization-create-label>New</span>
                            </button>
                            <button type="button" data-specialization-edit-toggle aria-pressed="false" style="min-height:32px;padding:0 10px;border:1px solid #d8e2ef;background:#fff;border-radius:8px;font-size:11px;font-weight:700;cursor:pointer">
                                <i class="fa-solid fa-pen" aria-hidden="true"></i> <span data-specialization-edit-label>Edit</span>
                            </button>
                        </div>
                    @endif
                </div>

                @if(request()->routeIs('maintenance.personnel.mechanics') && auth()->user()?->hasSystemPermission('maintenance', 'edit'))
                    <small data-specialization-create-feedback role="status" aria-live="polite"></small>
                @endif
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
                        @if(request()->routeIs('maintenance.personnel.mechanics') && auth()->user()?->hasSystemPermission('maintenance', 'edit') && $customSpecializationIds->has($specialization))
                            <button type="button"
                                class="personnel-specialization-delete-btn"
                                data-specialization-delete
                                hidden
                                data-specialization-name="{{ $specialization }}"
                                data-endpoint="{{ route('maintenance.personnel.mechanics.specializations.destroy', $customSpecializationIds->get($specialization)) }}"
                                aria-label="Delete custom specialization {{ $specialization }}"
                                title="Delete custom specialization {{ $specialization }}"
                                style="min-height:18px;min-width:18px;padding:0 3px;font-size:10px;border:1px solid #fecaca;border-radius:6px;background:#fff1f2;color:#b91c1c;cursor:pointer;align-items:center;justify-content:center">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            </button>
                        @endif
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
