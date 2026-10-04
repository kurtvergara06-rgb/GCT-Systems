@props([
    'selectedRolePermission',
    'permissionModules',
])

@php
    $selectedPermissions = $selectedRolePermission->permissions ?? [];
    $isProtectedRole = $selectedRolePermission->role_key === 'admin_head';
    $roleModuleKey = $isProtectedRole
        ? null
        : strtolower(trim((string) $selectedRolePermission->department));

    $visibleModules = $isProtectedRole
        ? collect($permissionModules)
        : collect($permissionModules)->only([$roleModuleKey]);

    $enabledCapabilities = $visibleModules
        ->flatMap(function ($module, $moduleKey) use ($selectedPermissions) {
            return collect($module['capabilities'])->mapWithKeys(
                fn ($capability, $capabilityKey) => [
                    $moduleKey.'.'.$capabilityKey => (bool) data_get(
                        $selectedPermissions,
                        $moduleKey.'.'.$capabilityKey,
                        false
                    ),
                ]
            );
        })
        ->filter()
        ->count();

    $totalCapabilities = $visibleModules
        ->sum(fn ($module) => count($module['capabilities']));

    $roleTypeLabel = $isProtectedRole
        ? 'Protected'
        : ucfirst((string) $selectedRolePermission->role_type);

    $departmentLabel = $isProtectedRole
        ? 'Administration'
        : $selectedRolePermission->department;

    $restrictedModuleLabels = collect($permissionModules)
        ->except([$roleModuleKey])
        ->pluck('label')
        ->values();

    $roleModule = $roleModuleKey
        ? ($permissionModules[$roleModuleKey] ?? null)
        : null;
@endphp

<x-ui.ajax-region
    name="role-permission-panel"
    id="rolePermissionPanel"
    class="role-access-panel"
>
    <div class="selected-role-header">
        <div class="selected-role-main">
            <div class="selected-role-icon {{ $isProtectedRole ? 'protected' : $selectedRolePermission->role_type }}">
                <i class="fa-solid {{ $isProtectedRole ? 'fa-user-shield' : ($selectedRolePermission->role_type === 'head' ? 'fa-user-tie' : 'fa-user') }}"></i>
            </div>

            <div>
                <h2>{{ $selectedRolePermission->label }}</h2>
                <p>{{ $selectedRolePermission->department }} Department · {{ $roleTypeLabel }} role</p>
            </div>
        </div>

        <div class="selected-role-badges">
            @if($isProtectedRole)
                <span class="protected-role-badge">
                    <i class="fa-solid fa-lock"></i>
                    Protected Role
                </span>
            @endif

            <span class="active-role-badge">
                <i class="fa-solid fa-circle-check"></i>
                Active Role
            </span>
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('admin.users.store') }}"
        id="rolePermissionForm"
        data-confirm-form
        data-confirm-title="Save Role Permissions?"
        data-confirm-message="Apply these access changes to {{ $selectedRolePermission->label }}?"
        data-confirm-button="Yes, Save Permissions"
        data-confirm-type="status"
    >
        @csrf
        <input type="hidden" name="_permission_update" value="1">
        <input type="hidden" name="role_key" value="{{ $selectedRolePermission->role_key }}">

        <div class="role-capability-strip">
            <div class="capability-item">
                <div class="capability-icon view"><i class="fa-solid fa-building"></i></div>
                <div>
                    <span>Department</span>
                    <strong>{{ $departmentLabel }}</strong>
                </div>
            </div>

            <div class="capability-item">
                <div class="capability-icon edit"><i class="fa-solid fa-layer-group"></i></div>
                <div>
                    <span>Role Type</span>
                    <strong>{{ $roleTypeLabel }}</strong>
                </div>
            </div>

            <div class="capability-item">
                <div class="capability-icon approve"><i class="fa-solid fa-shield-halved"></i></div>
                <div>
                    <span>Allowed Access</span>
                    <strong>{{ $enabledCapabilities }} / {{ $totalCapabilities }}</strong>
                </div>
            </div>
        </div>

        @unless($isProtectedRole)
            <div
                id="permissionEditBanner"
                class="permission-edit-banner"
                role="status"
                aria-live="polite"
                hidden
            >
                <div class="permission-edit-banner-icon">
                    <i class="fa-solid fa-pen-to-square"></i>
                </div>

                <div class="permission-edit-banner-copy">
                    <strong>EDIT MODE ACTIVE</strong>
                    <span>Changes apply only to the {{ $selectedRolePermission->department }} module.</span>
                </div>

                <span class="permission-edit-unsaved">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    Unsaved Changes
                </span>
            </div>
        @endunless

        @if($isProtectedRole)
            <section class="module-access-section system-wide-access">
                <div class="module-access-heading">
                    <div>
                        <span class="section-kicker">Module Permissions</span>
                        <h2>System-Wide Access</h2>
                        <p>System Admin has protected access to every GCT module and capability.</p>
                    </div>

                    <span class="access-count-badge">
                        <i class="fa-solid fa-circle-check"></i>
                        {{ $visibleModules->count() }} of {{ $visibleModules->count() }} Modules
                    </span>
                </div>

                <div class="system-admin-module-list">
                    @foreach($visibleModules as $moduleKey => $module)
                        <article class="system-admin-module-row">
                            <div class="module-access-info">
                                <div class="module-access-icon {{ $moduleKey }}">
                                    <i class="fa-solid {{ $module['icon'] }}"></i>
                                </div>

                                <div>
                                    <strong>{{ $module['label'] }}</strong>
                                    <span>{{ $module['description'] }}</span>
                                </div>
                            </div>

                            <div class="system-admin-capabilities">
                                @foreach($module['capabilities'] as $capabilityKey => $capability)
                                    <span class="protected-capability">
                                        <i class="fa-solid fa-circle-check"></i>
                                        {{ $capability['label'] }}
                                    </span>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="module-restriction-note full-access-note">
                    <div class="restriction-note-icon">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div>
                        <strong>Full System Access</strong>
                        <p>System Admin can access Administration, Analytics, Operation, Maintenance, Purchase, and Warehouse modules.</p>
                    </div>
                </div>
            </section>
        @elseif($roleModule)
            <section class="module-access-section isolated-module-access">
                <div class="module-access-heading">
                    <div>
                        <span class="section-kicker">Module Permissions</span>
                        <h2>{{ $roleModule['label'] }} Module Access</h2>
                        <p>Manage what this role can do inside the {{ $roleModule['label'] }} module.</p>
                    </div>

                    <span class="access-count-badge">
                        <i class="fa-solid fa-circle-check"></i>
                        {{ $enabledCapabilities }} of {{ $totalCapabilities }} Allowed
                    </span>
                </div>

                <div class="isolated-permission-list">
                    @foreach($roleModule['capabilities'] as $capabilityKey => $capability)
                        @php
                            $isAllowed = (bool) data_get(
                                $selectedPermissions,
                                $roleModuleKey.'.'.$capabilityKey,
                                false
                            );
                            $fieldName = "permissions[{$roleModuleKey}][{$capabilityKey}]";
                            $descriptions = [
                                'view' => 'View records, reports, and information available inside this department module.',
                                'edit' => 'Create and edit records and operational information inside this department module.',
                                'approve' => 'Approve requests, transactions, and workflow actions assigned to this department role.',
                            ];
                        @endphp

                        <label
                            class="isolated-permission-row permission-option {{ $isAllowed ? 'allowed' : 'restricted' }}"
                            data-permission-option
                        >
                            <input type="hidden" name="{{ $fieldName }}" value="0">

                            <input
                                type="checkbox"
                                name="{{ $fieldName }}"
                                value="1"
                                @checked($isAllowed)
                                disabled
                                hidden
                                data-permission-input
                            >

                            <div class="permission-row-icon">
                                <i class="fa-solid {{ $capability['icon'] }}"></i>
                            </div>

                            <div class="permission-row-copy">
                                <strong>{{ $capability['label'] }}</strong>
                                <span>{{ $descriptions[$capabilityKey] ?? ('Use ' . strtolower($capability['label']) . ' capabilities within this module.') }}</span>
                            </div>

                            <span class="permission-toggle" aria-hidden="true">
                                <span></span>
                            </span>

                            <span class="permission-status">
                                <i class="fa-solid {{ $isAllowed ? 'fa-circle-check' : 'fa-circle-xmark' }} option-state"></i>
                                <span class="permission-status-text">{{ $isAllowed ? 'Allowed' : 'Restricted' }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="module-restriction-note">
                    <div class="restriction-note-icon">
                        <i class="fa-solid fa-circle-info"></i>
                    </div>

                    <div>
                        <strong>Module Access Restriction</strong>
                        <p>
                            The {{ $selectedRolePermission->label }} role can only access the
                            <strong>{{ $roleModule['label'] }}</strong> module.
                            This role does not have access to
                            <strong>{{ $restrictedModuleLabels->join(', ', ', or ') }}</strong>.
                        </p>
                    </div>
                </div>
            </section>
        @endif

        @unless($isProtectedRole)
            <div class="permission-edit-actions">
                <div class="selected-role-state" id="permissionEditStatus">
                    <span><i class="fa-solid fa-eye"></i> Review Mode</span>
                </div>

                <div class="permission-action-buttons">
                    <button
                        type="button"
                        class="secondary-btn"
                        id="cancelPermissionEdit"
                        hidden
                        style="display: none;"
                    >
                        Cancel
                    </button>

                    <button type="button" class="primary-btn" id="editPermissionsButton">
                        <i class="fa-solid fa-pen"></i>
                        Edit Permissions
                    </button>

                    <button
                        type="submit"
                        class="primary-btn"
                        id="savePermissionsButton"
                        hidden
                        style="display: none;"
                    >
                        <i class="fa-solid fa-floppy-disk"></i>
                        Save Permissions
                    </button>
                </div>
            </div>
        @endunless
    </form>
</x-ui.ajax-region>
