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


function renderItemRequestDetailParts(raw, prRaw) {
    const availableBody = document.getElementById('ir-detail-available-parts');
    const missingBody = document.getElementById('ir-detail-missing-parts');
    const count = document.getElementById('ir-detail-parts-count');
    if (!availableBody || !missingBody) return;

    const available = String(raw).split(' | ').map(value => value.trim()).filter(Boolean).map(part => {
        const separator = part.lastIndexOf(' — ');
        return {
            name: separator >= 0 ? part.slice(0, separator) : part,
            quantity: separator >= 0 ? part.slice(separator + 3) : '—'
        };
    });
    let missing = [];
    try {
        const decoded = JSON.parse(prRaw || '[]');
        if (Array.isArray(decoded)) missing = decoded.filter(part => part && typeof part === 'object');
    } catch (_) {
        missing = [];
    }

    if (count) count.textContent = 'IR: ' + available.length + ' • PR: ' + missing.length;
    const showRows = (body, parts, label, statusLabel, isPr) => {
        body.replaceChildren();
        if (!parts.length) {
            const row = document.createElement('tr');
            const empty = document.createElement('td');
            empty.colSpan = 3;
            empty.className = 'ir-view-request-empty';
            empty.textContent = isPr ? 'No Purchase Request recorded for this Job Order.' : 'No submitted IR parts.';
            row.append(empty);
            body.append(row);
            return;
        }
        parts.forEach(part => {
            const row = document.createElement('tr');
            const name = document.createElement('td');
            name.textContent = part.name || '—';
            const qty = document.createElement('td');
            qty.textContent = isPr ? String(part.quantity ?? 0) + ' ' + (part.unit || '') : part.quantity;
            const status = document.createElement('td');
            const badge = document.createElement('span');
            const actualStatus = isPr ? String(part.status || 'Status unavailable').trim() : statusLabel;
            const normalizedStatus = actualStatus.toLowerCase();
            const statusTone = !isPr ? 'is-ir'
                : ['approved', 'completed', 'received', 'purchased', 'delivered'].includes(normalizedStatus) ? 'is-success'
                : ['rejected', 'cancelled', 'canceled'].includes(normalizedStatus) ? 'is-danger'
                : ['pending', 'submitted', 'for review', 'for purchase', 'processing', 'on order'].includes(normalizedStatus) ? 'is-pr'
                : 'is-neutral';
            badge.className = 'ir-view-request-badge ' + statusTone;
            badge.textContent = actualStatus;
            status.append(badge);
            row.append(name, qty, status);
            body.append(row);
        });
    };
    showRows(availableBody, available, 'IR', 'For Warehouse', false);
    showRows(missingBody, missing, 'PR', 'For Purchase', true);
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
            'ir-detail-overview-job': trigger.dataset.irJob,
            'ir-detail-requester': trigger.dataset.irRequester,
            'ir-detail-request-date': trigger.dataset.irDate,
            'ir-detail-remarks': trigger.dataset.irRemarks || 'No remarks provided.'
        };
        Object.entries(map).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) element.textContent = value || '—';
        });
        const approver = document.getElementById('ir-detail-approver');
        if (approver) approver.textContent = trigger.dataset.irApprover || 'Not recorded';
        const signature = document.getElementById('ir-detail-signature');
        const signaturePlaceholder = document.getElementById('ir-detail-signature-placeholder');
        if (signature) {
            signature.hidden = true;
            signature.removeAttribute('src');
            if (signaturePlaceholder) {
                signaturePlaceholder.hidden = false;
                signaturePlaceholder.textContent = 'Signature unavailable';
            }
            const url = trigger.dataset.irSignatureUrl;
            if (url) {
                signature.onload = () => {
                    signature.hidden = false;
                    if (signaturePlaceholder) signaturePlaceholder.hidden = true;
                };
                signature.onerror = () => {
                    signature.hidden = true;
                    if (signaturePlaceholder) signaturePlaceholder.hidden = false;
                };
                signature.src = url;
            }
        }
        renderItemRequestDetailParts(trigger.dataset.irParts || '', trigger.dataset.irPrParts || '[]');
        const status = (trigger.dataset.irStatus || '').trim();
        const statusBadge = document.getElementById('ir-detail-status');
        if (statusBadge) {
            const normalized = status.toLowerCase();
            const tone = ['issued', 'completed'].includes(normalized) ? 'is-complete'
                : ['cancelled', 'rejected'].includes(normalized) ? 'is-closed'
                : ['partially issued', 'partial'].includes(normalized) ? 'is-partial'
                : 'is-active';
            statusBadge.classList.remove('is-complete', 'is-closed', 'is-partial', 'is-active');
            statusBadge.classList.add(tone);
        }
        const description = document.getElementById('ir-detail-status-description');
        if (description) {
            const descriptions = {
                submitted: 'Submitted to Warehouse; awaiting review.',
                approved: 'Warehouse has approved the request for processing.',
                preparing: 'Warehouse is preparing the requested parts.',
                partial: 'Some parts have been issued; quantities remain outstanding.',
                'partially issued': 'Part of this request has been issued; the remainder is still outstanding.',
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
    const missingBody = document.getElementById('ir-preview-missing');
    if (missingBody) {
        missingBody.replaceChildren();
        const missing = Array.isArray(jo?.missing_parts) ? jo.missing_parts : [];
        if (!missing.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 3;
            cell.textContent = 'No missing parts — Purchase Request not required.';
            row.append(cell);
            missingBody.append(row);
        } else {
            missing.forEach(part => {
                const row = document.createElement('tr');
                const name = document.createElement('td');
                name.textContent = part.name || '—';
                const quantity = document.createElement('td');
                quantity.textContent = String(part.quantity || 0) + ' ' + (part.unit || '');
                const status = document.createElement('td');
                const badge = document.createElement('span');
                badge.className = 'ir-stock-badge ir-stock-unavailable';
                badge.textContent = 'For Purchase';
                status.append(badge);
                row.append(name, quantity, status);
                missingBody.append(row);
            });
        }
    }
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

/* Close other IR/PR breakdowns and place the open popover above table clipping. */
if (!window.__gctIrSplitBreakdownBound) {
    window.__gctIrSplitBreakdownBound = true;
    document.addEventListener('toggle', (event) => {
        const details = event.target;
        if (!(details instanceof HTMLDetailsElement) || !details.matches('.ir-split-breakdown')) return;
        if (!details.open) return;
        document.querySelectorAll('.ir-split-breakdown[open]').forEach(other => {
            if (other !== details) other.open = false;
        });
        const popover = details.querySelector('.ir-split-popover');
        const trigger = details.querySelector('summary');
        if (!popover || !trigger) return;
        const bounds = trigger.getBoundingClientRect();
        const width = Math.min(335, window.innerWidth - 32);
        popover.style.position = 'fixed';
        popover.style.width = width + 'px';
        popover.style.left = Math.max(16, Math.min(bounds.left, window.innerWidth - width - 16)) + 'px';
        popover.style.top = Math.min(bounds.bottom + 7, window.innerHeight - 32) + 'px';
    }, true);
    document.addEventListener('click', event => {
        document.querySelectorAll('.ir-split-breakdown[open]').forEach(details => {
            if (!details.contains(event.target)) details.open = false;
        });
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') document.querySelectorAll('.ir-split-breakdown[open]').forEach(details => details.open = false);
    });
    window.addEventListener('scroll', () => {
        document.querySelectorAll('.ir-split-breakdown[open]').forEach(details => details.open = false);
    }, true);
}
