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

        // Client-side pagination keeps the assigned accounts list compact without altering permissions.
        const membership = document.getElementById('assignedAccountsDisclosure');
        const accountRows = Array.from(document.querySelectorAll('#assignedAccountsRows [data-assigned-account]'));
        const memberSearch = document.getElementById('assignedAccountSearch');
        const memberEmpty = document.getElementById('assignedAccountEmpty');
        const memberRange = document.getElementById('assignedAccountsRange');
        const memberPage = document.getElementById('assignedAccountsPage');
        const previousMemberPage = document.getElementById('assignedAccountsPrevious');
        const nextMemberPage = document.getElementById('assignedAccountsNext');
        let membershipPage = 0;
        const renderMembership = () => {
            const query = (memberSearch?.value || '').trim().toLowerCase();
            const matched = accountRows.filter((row) => (row.dataset.search || '').includes(query));
            const totalPages = Math.max(1, Math.ceil(matched.length / 5));
            membershipPage = Math.max(0, Math.min(membershipPage, totalPages - 1));
            const offset = membershipPage * 5;
            const displayed = new Set(matched.slice(offset, offset + 5));
            accountRows.forEach((row) => { row.hidden = !displayed.has(row); });
            if (memberEmpty) memberEmpty.hidden = matched.length !== 0;
            if (memberRange) memberRange.textContent = matched.length ? 'Showing ' + (offset+1) + ' to ' + Math.min(offset+5,matched.length) + ' of ' + matched.length + ' accounts' : 'Showing 0 of 0 accounts';
            if (memberPage) memberPage.textContent = 'Page ' + (membershipPage+1) + ' of ' + totalPages;
            if (previousMemberPage) previousMemberPage.disabled = membershipPage === 0;
            if (nextMemberPage) nextMemberPage.disabled = membershipPage >= totalPages-1;
        };
        memberSearch?.addEventListener('input', () => { membershipPage = 0; renderMembership(); });
        previousMemberPage?.addEventListener('click', () => { membershipPage--; renderMembership(); });
        nextMemberPage?.addEventListener('click', () => { membershipPage++; renderMembership(); });
        membership?.addEventListener('toggle', () => { if(membership.open) renderMembership(); });
        renderMembership();

        const panel = document.getElementById('rolePermissionPanel');
        const form = document.getElementById('rolePermissionForm');
        const editButton = document.getElementById('editPermissionsButton');
        const cancelButton = document.getElementById('cancelPermissionEdit');
        const saveButton = document.getElementById('savePermissionsButton');
        const editStatus = document.querySelector('#permissionEditStatus span');
        const editBanner = document.getElementById('permissionEditBanner');
        const inputs = Array.from(document.querySelectorAll('[data-permission-input]'));
        const initialStates = inputs.map((input) => input.checked);
        const reviewPanel = document.getElementById('permissionChangeReview');
        const reviewList = document.getElementById('permissionChangeList');
        const reviewImpact = document.getElementById('permissionChangeImpact');
        const originalImpact = reviewImpact?.textContent || '';

        const reviewCount = document.getElementById('permissionChangeCount');
        const changes = () => inputs.flatMap((input, index) => {
            if (input.checked === initialStates[index]) return [];
            const name = input.closest('[data-permission-option]')?.querySelector('span')?.textContent?.trim() || input.name;
            const module = input.name.match(/^permissions\[([^\]]+)\]/)?.[1] || '';
            return [{ module, name, action: input.checked ? 'Grant' : 'Revoke' }];
        });
        const refreshReview = () => {
            const pending = changes();
            if (reviewPanel) reviewPanel.hidden = !form?.classList.contains('is-editing');
            if (reviewCount) reviewCount.textContent = pending.length + (pending.length === 1 ? ' Change' : ' Changes');
            if (reviewList) {
                reviewList.replaceChildren();
                if (!pending.length) {
                    const item = document.createElement('li');
                    item.className = 'permission-change-empty';
                    item.textContent = 'No permission changes yet.';
                    reviewList.appendChild(item);
                }
                for (const change of pending) {
                    const item = document.createElement('li');
                    item.className = 'permission-change-item';
                    const icon = document.createElement('span');
                    icon.className = 'permission-change-icon';
                    icon.setAttribute('aria-hidden', 'true');
                    const glyph = document.createElement('i');
                    glyph.className = 'fa-solid ' + (change.action === 'Grant' ? 'fa-shield-halved' : 'fa-lock');
                    icon.appendChild(glyph);

                    const content = document.createElement('span');
                    content.className = 'permission-change-description';
                    const title = document.createElement('strong');
                    title.textContent = change.name + (change.module === 'analytics' ? ' Analytics' : '');
                    const description = document.createElement('small');
                    description.textContent = 'Permission within ' + change.module.charAt(0).toUpperCase() + change.module.slice(1);
                    content.append(title, description);

                    const action = document.createElement('span');
                    action.className = 'permission-change-action ' + change.action.toLowerCase();
                    action.textContent = change.action;
                    item.append(icon, content, action);
                    reviewList.appendChild(item);
                }
            }
            if (reviewImpact) reviewImpact.textContent = originalImpact;
            if (saveButton) saveButton.disabled = pending.length === 0;
            if (form) form.dataset.confirmMessage = pending.map((change) =>
                change.module + ' — ' + change.name + ': ' + change.action
            ).join('; ') + '. ' + originalImpact;
        };

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

        const syncAnalyticsDependencies = () => {
            const view = inputs.find(input => input.name === 'permissions[analytics][view]');
            if (!view) return;
            const editing = form?.classList.contains('is-editing');
            for (const capability of ['analyze', 'recommendations']) {
                const input = inputs.find(input => input.name === `permissions[analytics][${capability}]`);
                if (!input) continue;
                if (!view.checked) input.checked = false;
                input.disabled = !editing || !view.checked;
                input.closest('[data-permission-option]')?.setAttribute('title', !view.checked ? 'Grant Analytics View first.' : '');
                syncOption(input);
            }
        };

        const setEditMode = (enabled) => {
            inputs.forEach((input) => { input.disabled = !enabled; });
            panel?.classList.toggle('is-editing', enabled);
            form?.classList.toggle('is-editing', enabled);
            if (editBanner) editBanner.hidden = !enabled;
            syncAnalyticsDependencies();
            refreshReview();

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
            input.addEventListener('change', () => { syncOption(input); syncAnalyticsDependencies(); refreshReview(); });
        });
    }
);
