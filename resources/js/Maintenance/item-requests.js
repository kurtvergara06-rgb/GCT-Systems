document.addEventListener('click', (event) => {
    const modal = document.getElementById('itemRequestModal');
    if (!modal) return;
    const open = event.target.closest('#openItemRequestModal');
    const close = event.target.closest('#closeItemRequestModal, #cancelItemRequestModal');
    if (open) {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        modal.querySelector('select')?.focus();
    }
    if (close) {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.getElementById('openItemRequestModal')?.focus();
    }
});
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const modal = document.getElementById('itemRequestModal');
    if (modal?.classList.contains('is-open')) {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.getElementById('openItemRequestModal')?.focus();
    }
});


function closeItemRequestDetails() {
    const modal = document.getElementById('viewItemRequestModal');
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
}
document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-ir-view]');
    if (trigger) {
        const modal = document.getElementById('viewItemRequestModal');
        if (!modal) return;
        const map = {
            'ir-detail-number': trigger.dataset.irNumber,
            'ir-detail-job': trigger.dataset.irJob,
            'ir-detail-bus': trigger.dataset.irBus,
            'ir-detail-status': trigger.dataset.irStatus,
            'ir-detail-parts': (trigger.dataset.irParts || '').split(' | ').join('\n'),
            'ir-detail-remarks': trigger.dataset.irRemarks || 'No remarks provided.'
        };
        Object.entries(map).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) element.textContent = value || '—';
        });
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        modal.querySelector('[data-ir-close]')?.focus();
    }
    if (event.target.closest('[data-ir-close]')) closeItemRequestDetails();
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeItemRequestDetails();
});
