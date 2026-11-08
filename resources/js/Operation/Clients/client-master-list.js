// Client Master List modal behavior, including read-only View mode.
window.GCTPartialNavigation?.registerInitializer('operation-client-master-list', '.client-master-page', (root) => {
    const createButton = root.querySelector('[data-client-create-open]');
    const createModal = document.getElementById('clientMasterCreateModal');
    const detailModal = document.getElementById('clientMasterDetailModal');
    const detailForm = document.getElementById('clientMasterDetailForm');
    const detailTitle = document.getElementById('clientMasterDetailTitle');
    const submitButton = document.getElementById('clientMasterDetailSubmit');
    const detailButtons = Array.from(root.querySelectorAll('[data-client-detail-open]'));
    const controllers = new AbortController();
    const { signal } = controllers;

    const show = (modal) => {
        if (!modal) return;
        modal.classList.add('show', 'active');
        document.body.classList.add('modal-open');
    };
    const hide = (modal) => {
        if (!modal) return;
        modal.classList.remove('show', 'active');
        if (!document.querySelector('.ui-form-overlay.show, .ui-form-overlay.active')) {
            document.body.classList.remove('modal-open');
        }
    };

    createButton?.addEventListener('click', () => {
        show(createModal);
        createModal?.querySelector('input[name="name"]')?.focus();
    }, { signal });
    if (createButton?.dataset.openOnError === 'true') {
        show(createModal);
    }

    const fields = {
        name: 'clientName',
        company_address: 'clientAddress',
        contact_person: 'clientContactPerson',
        contact_position: 'clientContactPosition',
        contact_number: 'clientContactNumber',
        contact_email: 'clientContactEmail',
    };

    const openDetail = (button) => {
        if (!detailModal || !detailForm) return;
        const editing = button.dataset.clientMode === 'edit' && Boolean(button.dataset.clientUpdateUrl);
        detailForm.action = editing ? button.dataset.clientUpdateUrl : '#';
        detailTitle.textContent = editing ? 'Edit Client Information' : 'View Client Information';

        for (const [name, dataKey] of Object.entries(fields)) {
            const field = detailForm.elements.namedItem(name);
            if (!field) continue;
            field.value = button.dataset[dataKey] || '';
            field.readOnly = !editing;
            field.setAttribute('aria-readonly', String(!editing));
        }
        const output = {
            clientDetailId: button.dataset.clientId,
            clientDetailStatus: button.dataset.clientStatus,
            clientDetailBuses: button.dataset.clientBuses,
            clientDetailTripTimes: button.dataset.clientTripTimes ? button.dataset.clientTripTimes + '/4' : '0/4',
        };
        for (const [id, value] of Object.entries(output)) {
            const element = document.getElementById(id);
            if (element) element.textContent = value || '—';
        }

        if (submitButton) submitButton.hidden = !editing;
        detailModal.dataset.mode = editing ? 'edit' : 'view';
        show(detailModal);
        (editing ? detailForm.elements.namedItem('name') : detailModal.querySelector('[data-ui-modal-close]'))?.focus();
    };

    detailButtons.forEach(button => button.addEventListener('click', () => openDetail(button), { signal }));
    detailForm?.addEventListener('submit', event => {
        if (detailModal?.dataset.mode !== 'edit') event.preventDefault();
    }, { signal });
    for (const modal of [createModal, detailModal]) {
        modal?.addEventListener('click', event => {
            if (event.target.closest('[data-ui-modal-close]')) hide(modal);
        }, { signal });
    }
    return () => {
        controllers.abort();
        hide(createModal);
        hide(detailModal);
    };
});
