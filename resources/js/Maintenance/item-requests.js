document.addEventListener('click', (event) => {
    const modal = document.getElementById('itemRequestModal');
    if (!modal) return;
    const open = event.target.closest('#openItemRequestModal, [data-ir-review-job]');
    const close = event.target.closest('#closeItemRequestModal, #cancelItemRequestModal');
    if (open) {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        const select = document.getElementById('item_request_job_order');
        if (open.dataset.irReviewJob && select) select.value = open.dataset.irReviewJob;
        renderItemRequestPreview();
        select?.focus();
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


function renderItemRequestPreview() {
    const select = document.getElementById('item_request_job_order');
    const source = document.getElementById('ir-job-previews');
    const tbody = document.getElementById('ir-preview-parts');
    if (!select || !source || !tbody) return;
    let previews = {};
    try { previews = JSON.parse(source.textContent || '{}'); } catch (_) { return; }
    const jo = previews[select.value];
    document.getElementById('ir-preview-bus').textContent = jo?.bus || 'Select a JO';
    document.getElementById('ir-preview-mechanic').textContent = jo?.mechanic || 'Select a JO';
    tbody.replaceChildren();
    if (!jo || !Array.isArray(jo.parts) || !jo.parts.length) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 3; td.className = 'ir-preview-placeholder';
        td.textContent = 'Select an approved JO to review its required parts.';
        tr.appendChild(td); tbody.appendChild(tr); return;
    }
    jo.parts.forEach(part => {
        const tr = document.createElement('tr');
        const partTd = document.createElement('td');
        partTd.textContent = part.name || '—';
        const qtyTd = document.createElement('td');
        qtyTd.textContent = String(part.quantity || 0) + ' ' + (part.unit || '');
        const stockTd = document.createElement('td');
        const badge = document.createElement('span');
        const available = Number(part.available || 0);
        const required = Number(part.quantity || 0);
        const status = 'Available';
        badge.className = 'ir-stock-badge ir-stock-' + status.toLowerCase();
        badge.textContent = status;
        stockTd.appendChild(badge);
        tr.append(partTd, qtyTd, stockTd);
        tbody.appendChild(tr);
    });
}
document.addEventListener('change', event => {
    if (event.target?.id === 'item_request_job_order') renderItemRequestPreview();
});
document.addEventListener('click', event => {
    if (event.target.closest('#openItemRequestModal')) renderItemRequestPreview();
});
