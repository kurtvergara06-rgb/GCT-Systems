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

            const panel = document.getElementById('rolePermissionPanel');
            const form = document.getElementById('rolePermissionForm');
            const editButton = document.getElementById('editPermissionsButton');
            const cancelButton = document.getElementById('cancelPermissionEdit');
            const saveButton = document.getElementById('savePermissionsButton');
            const editStatus = document.querySelector('#permissionEditStatus span');
            const editBanner = document.getElementById('permissionEditBanner');
            const inputs = Array.from(document.querySelectorAll('[data-permission-input]'));
            const initialStates = inputs.map(input => input.checked);

            function syncOption(input) {
                const option = input.closest('[data-permission-option]');
                const stateIcon = option?.querySelector('.option-state');
                const statusText = option?.querySelector('.permission-status-text');

                option?.classList.toggle('allowed', input.checked);
                option?.classList.toggle('restricted', !input.checked);

                if (stateIcon) {
                    stateIcon.classList.toggle('fa-circle-check', input.checked);
                    stateIcon.classList.toggle('fa-circle-xmark', !input.checked);
                }

                if (statusText) {
                    statusText.textContent = input.checked ? 'Allowed' : 'Restricted';
                }
            }

            function setButtonVisibility(button, visible, displayMode = 'inline-flex') {
                if (!button) return;

                button.hidden = !visible;
                button.style.display = visible ? displayMode : 'none';
            }

            function setEditMode(enabled) {
                inputs.forEach(input => input.disabled = !enabled);

                panel?.classList.toggle('is-editing', enabled);
                form?.classList.toggle('is-editing', enabled);

                if (editBanner) {
                    editBanner.hidden = !enabled;
                }

                setButtonVisibility(editButton, !enabled);
                setButtonVisibility(cancelButton, enabled);
                setButtonVisibility(saveButton, enabled);

                if (editStatus) {
                    editStatus.innerHTML = enabled
                        ? '<i class="fa-solid fa-pen-to-square"></i> Edit Mode'
                        : '<i class="fa-solid fa-eye"></i> Review Mode';
                }
            }

            setEditMode(false);

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
