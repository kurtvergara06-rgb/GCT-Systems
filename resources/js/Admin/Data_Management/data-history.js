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

    function setText(id, value) {
        const element = document.getElementById(id);
        if (element) element.textContent = value || '—';
    }

    function resetExtras() {
        if (validationContainer) validationContainer.style.display = 'none';
        if (errorsList) errorsList.innerHTML = '';
        if (actionLink) actionLink.style.display = 'none';
    }

    async function openModal(button) {
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
        setText('historyModalStatus', button.dataset.status);
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
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) return;

            const data = await response.json();

            setText('historyModalFile', data.file_name);
            setText('historyModalType', data.activity_type);
            setText('historyModalModule', data.module);
            setText('historyModalDataType', data.data_type);
            setText('historyModalSource', data.source);
            setText('historyModalRecords', data.total_records);
            setText('historyModalSuccessful', data.successful_records);
            setText('historyModalFailed', data.failed_records);
            setText('historyModalSkipped', data.skipped_records);
            setText('historyModalStatus', data.status);
            setText('historyModalUser', data.processed_by);
            setText('historyModalDateTime', data.created_at);
            setText('historyModalError', data.error_message || 'None');

            // Render validation errors if present
            if (Array.isArray(data.validation_errors) && data.validation_errors.length > 0) {
                if (errorCount) errorCount.textContent = data.validation_errors.length;
                if (errorsList) {
                    errorsList.innerHTML = data.validation_errors.map(err => {
                        const rowNum = err.row ? `<strong>Row ${err.row}:</strong> ` : '';
                        const msg = typeof err === 'string' ? err : (err.message || JSON.stringify(err));
                        return `<div>${rowNum}${msg}</div>`;
                    }).join('');
                }
                if (validationContainer) validationContainer.style.display = 'block';
            }

            // Render direct remediation / navigation link
            if (data.remediation_url && actionLink) {
                actionLink.href = data.remediation_url;
                if (actionText) {
                    if (data.activity_type === 'Batch Processing') {
                        actionText.textContent = 'Go to Batch File Processing';
                    } else if (data.activity_type === 'Import') {
                        actionText.textContent = 'Go to Import / Export';
                    } else {
                        actionText.textContent = 'View Activity';
                    }
                }
                actionLink.style.display = 'inline-flex';
            }
        } catch (error) {
            console.error('Failed to load data activity details:', error);
        }
    }

    function closeModal() {
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
    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('show')) closeModal();
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDataHistoryPage, { once: true });
} else {
    initDataHistoryPage();
}
