<x-layout.app
    title="FROMS - Roles & Permissions"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/User_Management/permissions.css'
    ]"
>
    <div class="app">
        <x-layout.sidebar
            department="Admin"
            subtitle="Administration Module"
            icon="fa-user-shield"
            :items="[
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'fa-table-cells-large'],
                [
                    'label' => 'User Management',
                    'icon' => 'fa-users',
                    'children' => [
                        ['label' => 'Users', 'route' => 'admin.users', 'icon' => 'fa-user'],
                        ['label' => 'Roles & Permissions', 'route' => 'admin.roles-permissions', 'icon' => 'fa-user-lock'],
                    ],
                ],
                [
                    'label' => 'System Monitoring',
                    'icon' => 'fa-desktop',
                    'children' => [
                        ['label' => 'Activity Logs', 'route' => 'admin.activity-logs', 'icon' => 'fa-clock-rotate-left'],
                        ['label' => 'Notifications', 'route' => 'admin.notifications', 'icon' => 'fa-bell'],
                    ],
                ],
                [
                    'label' => 'Data Management',
                    'icon' => 'fa-database',
                    'children' => [
                        ['label' => 'Batch File Processing', 'route' => 'admin.batch-file-processing', 'icon' => 'fa-file-import'],
                        ['label' => 'Import / Export', 'route' => 'admin.import-export', 'icon' => 'fa-right-left'],
                        ['label' => 'Data History', 'route' => 'admin.data-history', 'icon' => 'fa-clock-rotate-left'],
                    ],
                ],
                [
                    'label' => 'Analytics',
                    'icon' => 'fa-chart-line',
                    'children' => [
                        ['label' => 'Overview', 'route' => 'analytics.overview', 'icon' => 'fa-chart-pie'],
                        ['label' => 'Fleet & Trip', 'route' => 'analytics.fleet-trip', 'icon' => 'fa-route'],
                        ['label' => 'Fuel', 'route' => 'analytics.fuel', 'icon' => 'fa-gas-pump'],
                        ['label' => 'Bus Health', 'route' => 'analytics.bus-health', 'icon' => 'fa-heart-pulse'],
                        ['label' => 'Inventory', 'route' => 'analytics.inventory', 'icon' => 'fa-boxes-stacked'],
                        ['label' => 'Recommendations', 'route' => 'analytics.recommendations', 'icon' => 'fa-lightbulb'],
                    ],
                ],
                [
                    'label' => 'Settings',
                    'icon' => 'fa-gear',
                    'children' => [
                        ['label' => 'General Settings', 'route' => 'admin.settings.general', 'icon' => 'fa-sliders'],
                        ['label' => 'Notification Settings', 'route' => 'admin.settings.notifications', 'icon' => 'fa-bell'],
                        ['label' => 'Security Settings', 'route' => 'admin.settings.security', 'icon' => 'fa-shield-halved'],
                    ],
                ],
            ]"
        />

        <main class="main permissions-page">
            <x-layout.topbar title="Roles & Permissions" subtitle="Manage department roles and system access levels" />

            @if(! $permissionsReady)
                <section class="permission-note">
                    <div class="permission-note-icon"><i class="fa-solid fa-database"></i></div>
                    <div><strong>Role permissions database is not ready yet.</strong><p>Run the latest Laravel migration, then refresh this page.</p></div>
                </section>
            @else
                <section class="permission-overview">
                    <div class="permission-overview-copy">
                        <span class="section-kicker"><i class="fa-solid fa-user-lock"></i> Access Control</span>
                        <h2>Review and manage access assigned to each FROMS role.</h2>
                        <p>Choose a role, review its current database permissions, then edit and save when changes are needed.</p>
                    </div>
                    <div class="permission-overview-stats">
                        <div class="overview-stat"><span>Roles</span><strong>{{ $rolePermissions->count() }}</strong></div>
                        <div class="overview-divider"></div>
                        <div class="overview-stat"><span>Departments</span><strong>{{ $rolePermissions->pluck('department')->unique()->count() }}</strong></div>
                        <div class="overview-divider"></div>
                        <div class="overview-stat"><span>Protected Roles</span><strong>1</strong></div>
                    </div>
                </section>

                <section class="access-workspace">
                    <aside class="role-directory">
                        <div class="role-directory-heading">
                            <div><span class="section-kicker">Roles</span><h2>Role Directory</h2><p>Select a role to review access.</p></div>
                            <span class="role-count">{{ $rolePermissions->count() }}</span>
                        </div>
                        <div class="role-search"><i class="fa-solid fa-magnifying-glass"></i><input type="text" id="rolePermissionSearch" placeholder="Search role..."></div>
                        <div class="role-directory-list" id="roleDirectoryList">
                            @foreach($rolePermissions as $rolePermission)
                                <form method="GET" action="{{ route('admin.roles-permissions') }}" class="role-select-form">
                                    <input type="hidden" name="role" value="{{ $rolePermission->role_key }}">
                                    <button type="submit" class="directory-role {{ $selectedRolePermission?->role_key === $rolePermission->role_key ? 'active' : '' }}" data-role-search="{{ strtolower($rolePermission->label . ' ' . $rolePermission->department) }}">
                                        <div class="directory-role-icon {{ $rolePermission->role_type === 'admin' ? 'admin' : $rolePermission->role_type }}"><i class="fa-solid {{ $rolePermission->role_type === 'admin' ? 'fa-user-shield' : ($rolePermission->role_type === 'head' ? 'fa-user-tie' : 'fa-user') }}"></i></div>
                                        <div class="directory-role-info"><strong>{{ $rolePermission->label }}</strong><span>{{ $rolePermission->department }} Department</span></div>
                                        <span class="directory-role-type {{ $rolePermission->role_type === 'admin' ? 'admin' : $rolePermission->role_type }}">{{ $rolePermission->role_type === 'admin' ? 'Protected' : ucfirst($rolePermission->role_type) }}</span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </aside>

                    @if($selectedRolePermission)
                        @php
                            $selectedPermissions = $selectedRolePermission->permissions ?? [];
                            $isProtectedRole = $selectedRolePermission->role_key === 'admin_head';
                            $enabledCapabilities = collect($selectedPermissions)->flatMap(fn ($capabilities) => collect($capabilities)->filter())->count();
                            $totalCapabilities = collect($permissionModules)->sum(fn ($module) => count($module['capabilities']));
                        @endphp

                        <div class="role-access-panel">
                            <div class="selected-role-header">
                                <div class="selected-role-main">
                                    <div class="selected-role-icon"><i class="fa-solid {{ $isProtectedRole ? 'fa-user-shield' : ($selectedRolePermission->role_type === 'head' ? 'fa-user-tie' : 'fa-user') }}"></i></div>
                                    <div><span class="selected-label">Selected Role</span><h2>{{ $selectedRolePermission->label }}</h2><p>{{ $selectedRolePermission->department }} Department · {{ ucfirst($selectedRolePermission->role_type) }} role</p></div>
                                </div>
                                <div class="selected-role-state"><span><i class="fa-solid {{ $isProtectedRole ? 'fa-lock' : 'fa-circle-check' }}"></i> {{ $isProtectedRole ? 'Protected' : $enabledCapabilities . ' of ' . $totalCapabilities . ' Allowed' }}</span></div>
                            </div>

                            <form method="POST" action="{{ route('admin.users.store') }}" id="rolePermissionForm" data-confirm-form data-confirm-title="Save Role Permissions?" data-confirm-message="Apply these access changes to {{ $selectedRolePermission->label }}?" data-confirm-button="Yes, Save Permissions" data-confirm-type="status">
                                @csrf
                                <input type="hidden" name="_permission_update" value="1">
                                <input type="hidden" name="role_key" value="{{ $selectedRolePermission->role_key }}">

                                <div class="role-capability-strip">
                                    <div class="capability-item"><div class="capability-icon view"><i class="fa-solid fa-eye"></i></div><div><span>Role</span><strong>{{ $selectedRolePermission->label }}</strong></div></div>
                                    <div class="capability-item"><div class="capability-icon edit"><i class="fa-solid fa-layer-group"></i></div><div><span>Allowed Access</span><strong>{{ $enabledCapabilities }} / {{ $totalCapabilities }}</strong></div></div>
                                    <div class="capability-item"><div class="capability-icon approve"><i class="fa-solid fa-clock-rotate-left"></i></div><div><span>Last Updated</span><strong>{{ $selectedRolePermission->updated_at?->diffForHumans() ?? 'Not yet' }}</strong></div></div>
                                </div>

                                <section class="module-access-section">
                                    <div class="module-access-heading">
                                        <div><span class="section-kicker">Module Permissions</span><h2>System Access</h2><p id="permissionModeText">Review the permissions currently stored for this role.</p></div>
                                        <div class="access-legend"><span><i class="fa-solid fa-circle-check"></i> Allowed</span><span><i class="fa-solid fa-circle-xmark"></i> Restricted</span></div>
                                    </div>

                                    <div class="module-access-list">
                                        @foreach($permissionModules as $moduleKey => $module)
                                            <article class="module-access-row">
                                                <div class="module-access-info">
                                                    <div class="module-access-icon {{ $moduleKey }}"><i class="fa-solid {{ $module['icon'] }}"></i></div>
                                                    <div><strong>{{ $module['label'] }}</strong><span>{{ $module['description'] }}</span></div>
                                                </div>
                                                <div class="module-permission-options">
                                                    @foreach($module['capabilities'] as $capabilityKey => $capability)
                                                        @php
                                                            $isAllowed = (bool) data_get($selectedPermissions, $moduleKey . '.' . $capabilityKey, false);
                                                            $fieldName = "permissions[{$moduleKey}][{$capabilityKey}]";
                                                        @endphp
                                                        <input type="hidden" name="{{ $fieldName }}" value="0">
                                                        <label class="permission-option {{ $isAllowed ? 'allowed' : 'restricted' }}" data-permission-option>
                                                            <input type="checkbox" name="{{ $fieldName }}" value="1" {{ $isAllowed ? 'checked' : '' }} disabled hidden data-permission-input>
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

                                <div class="module-access-heading permission-edit-actions">
                                    @if($isProtectedRole)
                                        <div class="selected-role-state"><span><i class="fa-solid fa-lock"></i> Protected Role</span></div>
                                        <span class="permission-protected-note"><i class="fa-solid fa-lock"></i> System Admin permissions are protected from accidental changes.</span>
                                    @else
                                        <div class="selected-role-state" id="permissionEditStatus">
                                            <span><i class="fa-solid fa-eye"></i> Review Mode</span>
                                        </div>
                                        <div class="permission-action-buttons">
                                            <button type="button" class="secondary-btn" id="cancelPermissionEdit" hidden>Cancel</button>
                                            <button type="button" class="primary-btn" id="editPermissionsButton"><i class="fa-solid fa-pen"></i> Edit Permissions</button>
                                            <button type="submit" class="primary-btn" id="savePermissionsButton" hidden><i class="fa-solid fa-floppy-disk"></i> Save Permissions</button>
                                        </div>
                                    @endif
                                </div>
                            </form>
                        </div>
                    @endif
                </section>

                <section class="access-policy">
                    <div class="access-policy-heading"><div><span class="section-kicker">Access Policy</span><h2>How Role Access Works</h2></div></div>
                    <div class="policy-flow">
                        <div class="policy-step"><div class="policy-step-number">01</div><div><strong>System Admin</strong><span>Protected administrative role for system management.</span></div></div>
                        <i class="fa-solid fa-arrow-right policy-arrow"></i>
                        <div class="policy-step"><div class="policy-step-number">02</div><div><strong>Department Head</strong><span>View, manage, and approve within assigned access.</span></div></div>
                        <i class="fa-solid fa-arrow-right policy-arrow"></i>
                        <div class="policy-step"><div class="policy-step-number">03</div><div><strong>Department Staff</strong><span>Operational access without default approval permission.</span></div></div>
                    </div>
                </section>
            @endif
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const search = document.getElementById('rolePermissionSearch');
            const roleForms = document.querySelectorAll('.role-select-form');
            search?.addEventListener('input', function () {
                const value = search.value.trim().toLowerCase();
                roleForms.forEach(function (form) {
                    const button = form.querySelector('[data-role-search]');
                    form.hidden = value !== '' && !(button?.dataset.roleSearch || '').includes(value);
                });
            });

            const editButton = document.getElementById('editPermissionsButton');
            const cancelButton = document.getElementById('cancelPermissionEdit');
            const saveButton = document.getElementById('savePermissionsButton');
            const modeText = document.getElementById('permissionModeText');
            const editStatus = document.querySelector('#permissionEditStatus span');
            const inputs = Array.from(document.querySelectorAll('[data-permission-input]'));
            const initialStates = inputs.map(input => input.checked);

            function syncOption(input) {
                const option = input.closest('[data-permission-option]');
                const stateIcon = option?.querySelector('.option-state');
                option?.classList.toggle('allowed', input.checked);
                option?.classList.toggle('restricted', !input.checked);
                if (stateIcon) {
                    stateIcon.classList.toggle('fa-circle-check', input.checked);
                    stateIcon.classList.toggle('fa-circle-xmark', !input.checked);
                }
            }

            function setEditMode(enabled) {
                inputs.forEach(input => input.disabled = !enabled);
                if (editButton) editButton.hidden = enabled;
                if (cancelButton) cancelButton.hidden = !enabled;
                if (saveButton) saveButton.hidden = !enabled;
                if (modeText) {
                    modeText.textContent = enabled
                        ? 'Edit mode is active. Click any permission to allow or restrict it, then save your changes.'
                        : 'Review the permissions currently stored for this role.';
                }
                if (editStatus) {
                    editStatus.innerHTML = enabled
                        ? '<i class="fa-solid fa-pen-to-square"></i> Edit Mode Active'
                        : '<i class="fa-solid fa-eye"></i> Review Mode';
                }
            }

            editButton?.addEventListener('click', () => setEditMode(true));
            cancelButton?.addEventListener('click', function () {
                inputs.forEach(function (input, index) {
                    input.checked = initialStates[index];
                    syncOption(input);
                });
                setEditMode(false);
            });
            inputs.forEach(input => input.addEventListener('change', () => syncOption(input)));
        });
    </script>
</x-layout.app>
