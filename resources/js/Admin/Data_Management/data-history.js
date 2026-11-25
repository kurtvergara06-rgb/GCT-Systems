function initDataHistoryPage() {
    const modal = document.getElementById('historyDetailsModal');
    if (!modal) return;

    const closeTop = document.getElementById('closeHistoryModal');
    const closeFooter = document.getElementById('closeHistoryModalFooter');
    const validationContainer = document.getElementById('historyModalValidationContainer');
    const errorsList = document.getElementById('historyModalErrorsList');
    const errorCount = document.getElementById('historyModalErrorCount');
    const actionLink = document.getElementById('historyModalActionLink');
    const actionText = document.getElementById('historyModalActionText');
    let activeRequest;

    function setText(id, value) {
        const element = modal.querySelector('#' + id);
        if (element) element.textContent = value == null || value === '' ? '—' : value;
    }

    function setStatus(value, button) {
        const target = modal.querySelector('#historyModalStatus');
        if (!target) return;
        const rowBadge = button.closest('tr')?.querySelector('[data-ui-component="status-badge"]');
        if (!value || !rowBadge) {
            target.textContent = value || '—';
            return;
        }
        const badge = rowBadge.cloneNode(true);
        const label = badge.querySelector('.status-badge__label');
        if (label) label.textContent = value;
        target.replaceChildren(badge);
    }

    function resetExtras() {
        if (validationContainer) validationContainer.hidden = true;
        errorsList?.replaceChildren();
        if (actionLink) actionLink.style.display = 'none';
    }

    async function openModal(button) {
        activeRequest?.abort();
        const controller = new AbortController();
        activeRequest = controller;
        resetExtras();

        // 1. Instant preliminary fill from data attributes
        setText('historyModalFile', button.dataset.file);
        setText('historyModalType', button.dataset.type);
        setText('historyModalModule', button.dataset.module);
        setText('historyModalDataType', button.dataset.dataType);
        setText('historyModalSource', button.dataset.source);
        setText('historyModalRecords', button.dataset.records);
        setText('historyModalSuccessful', button.dataset.successful);
        setText('historyModalFailed', button.dataset.failed);
        setText('historyModalSkipped', button.dataset.skipped);
        setStatus(button.dataset.status, button);
        setText('historyModalUser', button.dataset.user);
        setText('historyModalDateTime', `${button.dataset.date || '—'} ${button.dataset.time || ''}`);
        setText('historyModalError', button.dataset.error);

        modal.classList.add('show');
        document.body.classList.add('history-modal-open');

        // 2. Fetch full activity details from backend endpoint
        const detailUrl = button.dataset.url;
        if (!detailUrl) return;

        try {
            const response = await fetch(detailUrl, {
                signal: controller.signal,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) return;

            const data = await response.json();
            if (controller.signal.aborted) return;

            setText('historyModalFile', data.file_name);
            setText('historyModalType', data.activity_type);
            setText('historyModalModule', data.module);
            setText('historyModalDataType', data.data_type);
            setText('historyModalSource', data.source);
            setText('historyModalRecords', data.total_records);
            setText('historyModalSuccessful', data.successful_records);
            setText('historyModalFailed', data.failed_records);
            setText('historyModalSkipped', data.skipped_records);
            setStatus(data.status, button);
            setText('historyModalUser', data.processed_by);
            setText('historyModalDateTime', data.created_at);
            setText('historyModalError', data.error_message || 'None');

            // Render validation errors if present
            if (Array.isArray(data.validation_errors) && data.validation_errors.length > 0) {
                if (errorCount) errorCount.textContent = data.validation_errors.length;
                if (errorsList) {
                    errorsList.replaceChildren(...data.validation_errors.map(error => {
                        const row = document.createElement('div');
                        if (error?.row) {
                            const label = document.createElement('strong');
                            label.textContent = `Row ${error.row}: `;
                            row.append(label);
                        }
                        row.append(typeof error === 'string' ? error : (error?.message || JSON.stringify(error)));
                        return row;
                    }));
                }
                if (validationContainer) validationContainer.hidden = false;
            }

            // Render direct remediation / navigation link
            if (data.remediation_url && actionLink) {
                actionLink.href = data.remediation_url;
                if (actionText) {
                    if (data.activity_type === 'Batch Processing') {
                        actionText.textContent = 'Go to Batch File Processing';
                    } else if (data.activity_type === 'Import') {
                        actionText.textContent = 'Go to Data History';
                    } else {
                        actionText.textContent = 'View Activity';
                    }
                }
                actionLink.style.display = 'inline-flex';
            }
        } catch (error) {
            if (error.name !== 'AbortError') console.error('Failed to load data activity details:', error);
        }
    }

    function closeModal() {
        activeRequest?.abort();
        modal.classList.remove('show');
        document.body.classList.remove('history-modal-open');
        resetExtras();
    }

    document.addEventListener('click', (event) => {
        const button = event.target.closest('.open-history-modal');
        if (button) openModal(button);
    });

    closeTop?.addEventListener('click', closeModal);
    closeFooter?.addEventListener('click', closeModal);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('show')) closeModal();
    });
}

window.GCTPartialNavigation.registerInitializer(
    'admin-data-history',
    '.data-history-page',
    initDataHistoryPage,
);
