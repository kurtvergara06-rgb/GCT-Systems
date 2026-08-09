@props([
    'selectedRolePermission',
    'permissionModules',
])

@php
    $selectedPermissions = $selectedRolePermission->permissions ?? [];
    $isProtectedRole = $selectedRolePermission->role_key === 'admin_head';
    $enabledCapabilities = collect($selectedPermissions)
        ->flatMap(fn ($capabilities) => collect($capabilities)->filter())
        ->count();
    $totalCapabilities = collect($permissionModules)
        ->sum(fn ($module) => count($module['capabilities']));
@endphp

<x-ui.ajax-region
    name="role-permission-panel"
    id="rolePermissionPanel"
    class="role-access-panel"
>
    <div class="selected-role-header">
        <div class="selected-role-main">
            <div class="selected-role-icon">
                <i class="fa-solid {{ $isProtectedRole ? 'fa-user-shield' : ($selectedRolePermission->role_type === 'head' ? 'fa-user-tie' : 'fa-user') }}"></i>
            </div>

            <div>
                <span class="selected-label">Selected Role</span>
                <h2>{{ $selectedRolePermission->label }}</h2>
                <p>{{ $selectedRolePermission->department }} Department · {{ ucfirst($selectedRolePermission->role_type) }} role</p>
            </div>
        </div>

        <div class="selected-role-state">
            <span>
                <i class="fa-solid {{ $isProtectedRole ? 'fa-lock' : 'fa-circle-check' }}"></i>
                {{ $isProtectedRole ? 'Protected' : $enabledCapabilities . ' of ' . $totalCapabilities . ' Allowed' }}
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
                <div class="capability-icon view"><i class="fa-solid fa-eye"></i></div>
                <div><span>Role</span><strong>{{ $selectedRolePermission->label }}</strong></div>
            </div>

            <div class="capability-item">
                <div class="capability-icon edit"><i class="fa-solid fa-layer-group"></i></div>
                <div><span>Allowed Access</span><strong>{{ $enabledCapabilities }} / {{ $totalCapabilities }}</strong></div>
            </div>

            <div class="capability-item">
                <div class="capability-icon approve"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <div><span>Last Updated</span><strong>{{ $selectedRolePermission->updated_at?->diffForHumans() ?? 'Not yet' }}</strong></div>
            </div>
        </div>

        <section class="module-access-section">
            <div class="module-access-heading">
                <div>
                    <span class="section-kicker">Module Permissions</span>
                    <h2>System Access</h2>
                    <p id="permissionModeText">Review the permissions currently stored for this role.</p>
                </div>

                <div class="access-legend">
                    <span><i class="fa-solid fa-circle-check"></i> Allowed</span>
                    <span><i class="fa-solid fa-circle-xmark"></i> Restricted</span>
                </div>
            </div>

            <div class="module-access-list">
                @foreach($permissionModules as $moduleKey => $module)
                    <article class="module-access-row">
                        <div class="module-access-info">
                            <div class="module-access-icon {{ $moduleKey }}">
                                <i class="fa-solid {{ $module['icon'] }}"></i>
                            </div>

                            <div>
                                <strong>{{ $module['label'] }}</strong>
                                <span>{{ $module['description'] }}</span>
                            </div>
                        </div>

                        <div class="module-permission-options">
                            @foreach($module['capabilities'] as $capabilityKey => $capability)
                                @php
                                    $isAllowed = (bool) data_get(
                                        $selectedPermissions,
                                        $moduleKey . '.' . $capabilityKey,
                                        false
                                    );
                                    $fieldName = "permissions[{$moduleKey}][{$capabilityKey}]";
                                @endphp

                                <input type="hidden" name="{{ $fieldName }}" value="0">

                                <label
                                    class="permission-option {{ $isAllowed ? 'allowed' : 'restricted' }}"
                                    data-permission-option
                                >
                                    <input
                                        type="checkbox"
                                        name="{{ $fieldName }}"
                                        value="1"
                                        {{ $isAllowed ? 'checked' : '' }}
                                        disabled
                                        hidden
                                        data-permission-input
                                    >

                                    <div><i class="fa-solid {{ $capability['icon'] }}"></i></div>
                                    <span>{{ $capability['label'] }}</span>
                                    <i class="fa-solid {{ $isAllowed ? 'fa-circle-check' : 'fa-circle-xmark' }} option-state"></i>
                                </label>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        @unless($isProtectedRole)
            <div class="module-access-heading permission-edit-actions">
                <div class="selected-role-state" id="permissionEditStatus">
                    <span><i class="fa-solid fa-eye"></i> Review Mode</span>
                </div>

                <div class="permission-action-buttons">
                    <button type="button" class="secondary-btn" id="cancelPermissionEdit" hidden>
                        Cancel
                    </button>

                    <button type="button" class="primary-btn" id="editPermissionsButton">
                        <i class="fa-solid fa-pen"></i>
                        Edit Permissions
                    </button>

                    <button type="submit" class="primary-btn" id="savePermissionsButton" hidden>
                        <i class="fa-solid fa-floppy-disk"></i>
                        Save Permissions
                    </button>
                </div>
            </div>
        @endunless
    </form>
</x-ui.ajax-region>
