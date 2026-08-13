<x-layout.app
    title="FROMS - Roles & Permissions"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/User_Management/permissions.css'
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main permissions-page">
            <x-layout.topbar
                title="Roles & Permissions"
                subtitle="Manage department roles and system access levels"
            />

            @if(! $permissionsReady)
                <section class="permission-note">
                    <div class="permission-note-icon">
                        <i class="fa-solid fa-database"></i>
                    </div>
                    <div>
                        <strong>Role permissions database is not ready yet.</strong>
                        <p>Run the latest Laravel migration, then refresh this page.</p>
                    </div>
                </section>
            @else
                <section class="permission-overview">
                    <div class="permission-overview-copy">
                        <span class="section-kicker">
                            <i class="fa-solid fa-user-lock"></i>
                            Access Control
                        </span>
                        <h2>Review and manage access assigned to each FROMS role.</h2>
                        <p>Choose a role, review its current database permissions, then edit and save when changes are needed.</p>
                    </div>

                    <div class="permission-overview-stats">
                        <div class="overview-stat">
                            <span>Roles</span>
                            <strong>{{ $rolePermissions->count() }}</strong>
                        </div>
                        <div class="overview-divider"></div>
                        <div class="overview-stat">
                            <span>Departments</span>
                            <strong>{{ $rolePermissions->pluck('department')->unique()->count() }}</strong>
                        </div>
                        <div class="overview-divider"></div>
                        <div class="overview-stat">
                            <span>Protected Roles</span>
                            <strong>1</strong>
                        </div>
                    </div>
                </section>

                <section class="access-workspace">
                    <x-admin.role-directory
                        :role-permissions="$rolePermissions"
                        :selected-role-permission="$selectedRolePermission"
                    />

                    @if($selectedRolePermission)
                        <x-admin.role-permission-panel
                            :selected-role-permission="$selectedRolePermission"
                            :permission-modules="$permissionModules"
                        />
                    @endif
                </section>

                <section class="access-policy">
                    <div class="access-policy-heading">
                        <div>
                            <span class="section-kicker">Access Policy</span>
                            <h2>How Role Access Works</h2>
                        </div>
                    </div>

                    <div class="policy-flow">
                        <div class="policy-step">
                            <div class="policy-step-number">01</div>
                            <div>
                                <strong>System Admin</strong>
                                <span>Protected administrative role for system management.</span>
                            </div>
                        </div>

                        <i class="fa-solid fa-arrow-right policy-arrow"></i>

                        <div class="policy-step">
                            <div class="policy-step-number">02</div>
                            <div>
                                <strong>Department Head</strong>
                                <span>View, manage, and approve within assigned access.</span>
                            </div>
                        </div>

                        <i class="fa-solid fa-arrow-right policy-arrow"></i>

                        <div class="policy-step">
                            <div class="policy-step-number">03</div>
                            <div>
                                <strong>Department Staff</strong>
                                <span>Operational access without default approval permission.</span>
                            </div>
                        </div>
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
