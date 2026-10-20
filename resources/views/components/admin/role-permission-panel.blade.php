@props([
    'selectedRolePermission',
    'assignedAccounts' => collect(),
    'permissionModules',
])

@php
    $selectedPermissions = $selectedRolePermission->permissions ?? [];
    $isProtectedRole = $selectedRolePermission->role_key === 'admin_head';
    $roleModuleKey = $isProtectedRole
        ? null
        : strtolower(trim((string) $selectedRolePermission->department));

    $visibleModules = collect($permissionModules);

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
                    <span>Only {{ $selectedRolePermission->department }} permissions can be saved currently. Other modules are shown for review until cross-module authorization is enabled.</span>
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
        @else
            <section class="module-access-section system-wide-access department-module-matrix">
                <div class="module-access-heading">
                    <div>
                        <span class="section-kicker">Module Permissions</span>
                        <h2>System-Wide Module Access</h2>
                        <p>Review all GCT modules. Only the home department is editable in this first UI phase; cross-module access is not enabled yet.</p>
                    </div>
                    <span class="access-count-badge">{{ $enabledCapabilities }} of {{ $totalCapabilities }} Allowed</span>
                </div>
                <div class="system-admin-module-list">
                    @foreach($visibleModules->except(['administration']) as $moduleKey => $module)
                        @php
                            $isHomeModule = $moduleKey === $roleModuleKey;
                            $capabilities = $module['capabilities'];
                            // Warehouse approval is shown for design consistency, but it has no
                            // backend capability yet and must not be submitted as a permission.
                            if ($moduleKey === 'warehouse') {
                                $capabilities = array_merge($capabilities, [
                                    'approve' => ['label' => 'Approve', 'icon' => 'fa-check-double'],
                                ]);
                            }
                        @endphp
                        <article class="system-admin-module-row">
                            <div class="module-access-info">
                                <div class="module-access-icon {{ $moduleKey }}"><i class="fa-solid {{ $module['icon'] }}"></i></div>
                                <div>
                                    <strong>{{ $module['label'] }}</strong>
                                    <span>{{ $module['description'] }}</span>
                                </div>
                            </div>
                            <div class="system-admin-capabilities">
                                @foreach($capabilities as $capabilityKey => $capability)
                                    @php
                                        $supported = array_key_exists($capabilityKey, $module['capabilities']);
                                        $editable = $isHomeModule && $supported;
                                        $isAllowed = $editable && (bool) data_get($selectedPermissions, $moduleKey.'.'.$capabilityKey, false);
                                        $fieldName = "permissions[{$moduleKey}][{$capabilityKey}]";
                                    @endphp
                                    @if($editable)
                                        <label class="protected-capability permission-option matrix-native-toggle {{ $isAllowed ? 'allowed' : 'restricted' }}" data-permission-option>
                                            <input type="hidden" name="{{ $fieldName }}" value="0">
                                            <input type="checkbox" name="{{ $fieldName }}" value="1" @checked($isAllowed) disabled data-permission-input>
                                            <i class="fa-solid {{ $isAllowed ? 'fa-circle-check' : 'fa-circle-xmark' }} option-state"></i>
                                            <span>{{ $capability['label'] }}</span>
                                            <span class="permission-status-text" hidden>{{ $isAllowed ? 'Allowed' : 'Restricted' }}</span>
                                        </label>
                                    @else
                                        <span class="protected-capability matrix-native-locked" title="{{ $supported ? 'Cross-module permissions will be enabled after backend authorization is implemented.' : 'Warehouse approval permission has not been implemented yet.' }}">
                                            <i class="fa-solid fa-lock"></i> {{ $capability['label'] }}
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="module-restriction-note">
                    <div class="restriction-note-icon"><i class="fa-solid fa-circle-info"></i></div>
                    <div>
                        <strong>UI preview — no cross-module access granted</strong>
                        <p>System Admin can review every module here. Only {{ $selectedRolePermission->department }} permissions are currently saved; cross-module access and Warehouse approval require separate backend implementation.</p>
                    </div>
                </div>
            </section>
        @endif

        <section class="module-access-section assigned-role-accounts">
            <div class="module-access-heading">
                <div>
                    <span class="section-kicker">Registered Accounts</span>
                    <h2>Assigned Accounts</h2>
                    <p>These accounts inherit the selected role's permissions.</p>
                </div>
                <span class="access-count-badge">{{ $assignedAccounts->count() }} Accounts</span>
            </div>
            @forelse($assignedAccounts as $account)
                <div class="assigned-role-account-row">
                    <div>
                        <strong>{{ $account->name }}</strong>
                        <span>{{ $account->email }}</span>
                    </div>
                    <span>{{ $account->status }}</span>
                </div>
            @empty
                <p class="assigned-role-account-empty">No accounts are currently assigned to this role.</p>
            @endforelse
            <a class="assigned-role-account-link" href="{{ route('admin.users') }}">Manage accounts</a>
        </section>

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
                        <i class="fa-solid fa-pen-to-square"></i>
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
