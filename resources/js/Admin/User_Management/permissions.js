window.GCTPartialNavigation.registerInitializer(
    'admin-role-permissions',
    '.permissions-page',
    () => {
        const search = document.getElementById('rolePermissionSearch');
        const roleForms = document.querySelectorAll('.role-select-form');

        search?.addEventListener('input', () => {
            const value = search.value.trim().toLowerCase();
            roleForms.forEach((form) => {
                const button = form.querySelector('[data-role-search]');
                form.hidden = value !== ''
                    && !(button?.dataset.roleSearch || '').includes(value);
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
        const initialStates = inputs.map((input) => input.checked);

        const syncOption = (input) => {
            const option = input.closest('[data-permission-option]');
            const stateIcon = option?.querySelector('.option-state');
            const statusText = option?.querySelector('.permission-status-text');

            option?.classList.toggle('allowed', input.checked);
            option?.classList.toggle('restricted', !input.checked);
            stateIcon?.classList.toggle('fa-circle-check', input.checked);
            stateIcon?.classList.toggle('fa-circle-xmark', !input.checked);

            if (statusText) {
                statusText.textContent = input.checked ? 'Allowed' : 'Restricted';
            }
        };

        const setButtonVisibility = (button, visible) => {
            if (!button) return;
            button.hidden = !visible;
            button.style.display = visible ? 'inline-flex' : 'none';
        };

        const setEditMode = (enabled) => {
            inputs.forEach((input) => { input.disabled = !enabled; });
            panel?.classList.toggle('is-editing', enabled);
            form?.classList.toggle('is-editing', enabled);
            if (editBanner) editBanner.hidden = !enabled;

            setButtonVisibility(editButton, !enabled);
            setButtonVisibility(cancelButton, enabled);
            setButtonVisibility(saveButton, enabled);

            if (editStatus) {
                editStatus.innerHTML = enabled
                    ? '<i class="fa-solid fa-pen-to-square"></i> Edit Mode'
                    : '<i class="fa-solid fa-eye"></i> Review Mode';
            }
        };

        setEditMode(false);
        editButton?.addEventListener('click', () => setEditMode(true));
        cancelButton?.addEventListener('click', () => {
            inputs.forEach((input, index) => {
                input.checked = initialStates[index];
                syncOption(input);
            });
            setEditMode(false);
        });
        inputs.forEach((input) => {
            input.addEventListener('change', () => syncOption(input));
        });
    }
);
