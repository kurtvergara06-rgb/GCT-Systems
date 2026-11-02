window.GCTPartialNavigation.registerInitializer(
    'admin-role-permissions',
    '.permissions-page',
    () => {
        const tabs = Array.from(document.querySelectorAll('[data-permission-tab]'));
        const roleSection = document.getElementById('permissionDepartmentRoles');
        const additionalSection = document.getElementById('permissionAdditionalAccess');
        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                const showRoles = tab.dataset.permissionTab === 'roles';
                if (roleSection) roleSection.hidden = !showRoles;
                if (additionalSection) additionalSection.hidden = showRoles;
                tabs.forEach((item) => {
                    const selected = item === tab;
                    item.classList.toggle('active', selected);
                    item.setAttribute('aria-selected', String(selected));
                });
            });
        });


        // Read-only access planner. Never submit grants until the backend authorizes each feature.
        const employeeChoices = Array.from(document.querySelectorAll('[data-access-employee]'));
        const employeeSearch = document.getElementById('additionalEmployeeSearch');
        const departmentChoices = Array.from(document.querySelectorAll('[data-access-department-choice]'));
        const featureList = document.getElementById('additionalFeatureList');
        const featureDescriptions = {
            'View Purchase Request Status': 'Track requisition progress without editing requests.',
            'View Purchase Order Status': 'Review purchase orders and their current status.',
            'View Delivery Status': 'Monitor supplier deliveries and receiving progress.',
            'View Stock Availability': 'Check inventory quantities, reservations, and available stock.',
            'View Incoming Delivery Status': 'Review incoming deliveries and received quantities.',
            'View Trip Schedules': 'Review planned trip departure schedules.',
            'View Bus Availability': 'Check availability without changing assignments.',
            'View Job Order Status': 'Track maintenance progress and job status.',
            'View PMS Schedule Status': 'Review preventive maintenance schedule status.'
        };
        const review = document.getElementById('additionalAccessReview');
        const empty = document.getElementById('additionalEmployeeEmpty');
        const selectedName = document.getElementById('additionalSelectedName');
        const selectedRole = document.getElementById('additionalSelectedRole');
        const featureCatalog = {
            purchase: ['View Purchase Request Status', 'View Purchase Order Status', 'View Delivery Status'],
            warehouse: ['View Stock Availability', 'View Incoming Delivery Status'],
            operation: ['View Trip Schedules', 'View Bus Availability'],
            maintenance: ['View Job Order Status', 'View PMS Schedule Status']
        };
        let accessEmployee = null;
        let accessDepartment = null;
        const selectedFeatures = new Set();
        const updateReview = () => {
            if (!review) return;
            review.replaceChildren();
            const paragraph = document.createElement('p');
            paragraph.textContent = !accessEmployee ? 'Select an employee to begin.' : selectedFeatures.size === 0
                ? 'No features selected. This preview will not change access.'
                : accessEmployee.dataset.accessName + ': ' + [...selectedFeatures].join(', ') + '. Preview only — not saved.';
            review.appendChild(paragraph);
        };
        const updateFeatures = () => {
            if (!featureList) return;
            featureList.replaceChildren();
            selectedFeatures.clear();
            if (!accessDepartment) {
                const p = document.createElement('p');
                p.textContent = 'Choose a department to preview available features.';
                featureList.appendChild(p);
            } else {
                for (const feature of featureCatalog[accessDepartment] || []) {
                    const label = document.createElement('label');
                    label.className = 'permission-feature-choice';
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.addEventListener('change', () => {
                        if (checkbox.checked) selectedFeatures.add(feature);
                        else selectedFeatures.delete(feature);
                        label.classList.toggle('selected', checkbox.checked);
                        updateReview();
                    });
                    const span = document.createElement('span');
                    const title = document.createElement('strong');
                    title.textContent = feature;
                    const description = document.createElement('small');
                    description.textContent = featureDescriptions[feature] || '';
                    span.append(title, description);
                    const viewTag = document.createElement('em');
                    viewTag.textContent = 'View only';
                    label.append(checkbox, span, viewTag);
                    featureList.appendChild(label);
                }
            }
            updateReview();
        };
        employeeChoices.forEach((choice) => choice.addEventListener('click', () => {
            accessEmployee = choice;
            accessDepartment = null;
            employeeChoices.forEach((c) => c.classList.toggle('active', c === choice));
            if (selectedName) selectedName.textContent = choice.dataset.accessName;
            if (selectedRole) selectedRole.textContent = choice.dataset.accessDepartment + ' · ' + choice.dataset.accessRole;
            departmentChoices.forEach((c) => {
                c.disabled = c.dataset.accessDepartmentChoice === choice.dataset.accessDepartment;
                c.classList.remove('active');
            });
            updateFeatures();
        }));
        employeeSearch?.addEventListener('input', () => {
            const searchText = employeeSearch.value.toLowerCase().trim();
            let shown = 0;
            employeeChoices.forEach((c) => {
                c.hidden = !((c.dataset.accessName + ' ' + c.dataset.accessDepartment).toLowerCase().includes(searchText));
                if (!c.hidden) shown++;
            });
            if (empty) empty.hidden = shown > 0;
        });
        departmentChoices.forEach((choice) => choice.addEventListener('click', () => {
            if (!accessEmployee || choice.disabled) return;
            accessDepartment = choice.dataset.accessDepartmentChoice;
            departmentChoices.forEach((c) => c.classList.toggle('active', c === choice));
            updateFeatures();
        }));
        document.getElementById('additionalAccessClear')?.addEventListener('click', () => {
            accessDepartment = null;
            departmentChoices.forEach((c) => c.classList.remove('active'));
            updateFeatures();
        });

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
            stateIcon?.classList.remove('fa-circle-xmark');
            stateIcon?.classList.toggle('fa-lock', !input.checked);

            if (statusText) {
                statusText.textContent = input.checked ? 'Allowed' : 'Not granted';
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
