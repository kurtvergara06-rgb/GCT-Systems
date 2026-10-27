document.addEventListener('click', (event) => {
    const modal = document.getElementById('itemRequestModal');
    if (!modal) return;
    const open = event.target.closest('#openItemRequestModal, [data-ir-review-job]');
    const close = event.target.closest('#closeItemRequestModal, #cancelItemRequestModal');
    if (open) {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        const linkedJob = document.getElementById('item_request_job_order');
        if (linkedJob) linkedJob.value = open.dataset.irReviewJob || '';
        renderItemRequestPreview();
        const display = document.getElementById('item_request_job_order_display');
        if (display && open.dataset.irReviewNumber) {
            display.textContent = open.dataset.irReviewNumber + ' — Bus ' + (open.dataset.irReviewBus || '—');
        }
        modal.querySelector('#item_request_job_order_display')?.focus();
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


function renderItemRequestDetailParts(raw) {
    const list = document.getElementById('ir-detail-parts');
    const count = document.getElementById('ir-detail-parts-count');
    if (!list) return;
    const parts = String(raw).split(' | ').map(value => value.trim()).filter(Boolean);
    if (count) count.textContent = parts.length + (parts.length === 1 ? ' item' : ' items');
    list.replaceChildren();
    if (!parts.length) {
        const empty = document.createElement('p');
        empty.className = 'ir-view-parts-empty';
        empty.textContent = 'No requested parts listed.';
        list.append(empty);
        return;
    }
    parts.forEach((part, index) => {
        const separator = part.lastIndexOf(' — ');
        const name = separator >= 0 ? part.slice(0, separator) : part;
        const quantity = separator >= 0 ? part.slice(separator + 3) : '';
        const row = document.createElement('div');
        row.className = 'ir-view-part-row';
        const main = document.createElement('div');
        main.className = 'ir-view-part-main';
        const number = document.createElement('span');
        number.className = 'ir-view-part-index';
        number.textContent = String(index + 1).padStart(2, '0');
        const label = document.createElement('strong');
        label.textContent = name || '—';
        main.append(number, label);
        const amount = document.createElement('span');
        amount.className = 'ir-view-part-qty';
        amount.textContent = quantity || '—';
        row.append(main, amount);
        list.append(row);
    });
}

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
            'ir-detail-date': trigger.dataset.irDate,
            'ir-detail-remarks': trigger.dataset.irRemarks || 'No remarks provided.'
        };
        Object.entries(map).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) element.textContent = value || '—';
        });
        renderItemRequestDetailParts(trigger.dataset.irParts || '');
        const status = (trigger.dataset.irStatus || '').trim();
        const description = document.getElementById('ir-detail-status-description');
        if (description) {
            const descriptions = {
                submitted: 'Submitted to Warehouse; awaiting review.',
                approved: 'Warehouse has approved the request for processing.',
                preparing: 'Warehouse is preparing the requested parts.',
                partial: 'Some parts have been issued; quantities remain outstanding.',
                issued: 'The request has been issued by Warehouse.',
                completed: 'This request is completed.',
                cancelled: 'This request was cancelled.',
                rejected: 'This request was rejected.',
                pending: 'The request is awaiting processing.'
            };
            description.textContent = descriptions[status.toLowerCase()] || 'Current request processing status.';
        }
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
    const display = document.getElementById('item_request_job_order_display');
    if (display) display.textContent = jo ? (jo.job_order_no || jo.jobOrderNo || jo.job || 'Approved Job Order') + ' — Bus ' + (jo.bus || '—') : 'No Job Order linked';
    document.getElementById('ir-preview-bus').textContent = jo?.bus || '—';
    document.getElementById('ir-preview-mechanic').textContent = jo?.mechanic || '—';
    const approver = document.getElementById('ir-preview-approver');
    if (approver) approver.textContent = jo?.approver || 'Not recorded';
    const signature = document.getElementById('ir-preview-signature');
    const signaturePlaceholder = document.getElementById('ir-preview-signature-placeholder');
    if (signature) {
        signature.hidden = true;
        signature.removeAttribute('src');
        if (signaturePlaceholder) signaturePlaceholder.hidden = false;
        if (jo?.signature_url) {
            signature.onerror = () => {
                signature.hidden = true;
                if (signaturePlaceholder) signaturePlaceholder.hidden = false;
            };
            signature.onload = () => {
                signature.hidden = false;
                if (signaturePlaceholder) signaturePlaceholder.hidden = true;
            };
            signature.src = jo.signature_url;
        }
    }
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
