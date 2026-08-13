function initDataHistoryPage() {
    const modal = document.getElementById('historyDetailsModal');
    if (!modal) return;

    const closeTop = document.getElementById('closeHistoryModal');
    const closeFooter = document.getElementById('closeHistoryModalFooter');

    function setText(id, value) {
        const element = document.getElementById(id);
        if (element) element.textContent = value || '—';
    }

    function openModal(button) {
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
    }

    function closeModal() {
        modal.classList.remove('show');
        document.body.classList.remove('history-modal-open');
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
