window.GCTPartialNavigation?.registerInitializer?.('warehouse-ir-details', '.warehouse-ir-page', () => {
  // Move expandable item/on-hand lists outside the scroll-clipped records table.
  document.querySelectorAll('.warehouse-ir-page .ir-parts-details').forEach((details) => {
    if (details.dataset.popupReady) return;
    details.dataset.popupReady = '1';
    const trigger = details.querySelector('summary');
    const panel = details.querySelector('.ir-parts-popover');
    if (!trigger || !panel) return;
    let visible = false;
    const place = () => {
      if (!visible) return;
      const rect = trigger.getBoundingClientRect();
      const width = Math.min(300, window.innerWidth - 24);
      panel.style.position = 'fixed';
      panel.style.zIndex = '110500';
      panel.style.width = width + 'px';
      panel.style.left = Math.max(12, Math.min(rect.left, window.innerWidth - width - 12)) + 'px';
      const above = window.innerHeight - rect.bottom < 200 && rect.top > 200;
      panel.style.top = above ? 'auto' : (rect.bottom + 6) + 'px';
      panel.style.bottom = above ? (window.innerHeight - rect.top + 6) + 'px' : 'auto';
      panel.style.maxHeight = Math.min(230, Math.max(90, above ? rect.top - 18 : window.innerHeight - rect.bottom - 16)) + 'px';
    };
    const close = () => {
      if (!visible) return;
      visible = false;
      details.open = false;
      panel.hidden = true;
      panel.removeAttribute('style');
      details.appendChild(panel);
      window.removeEventListener('scroll', place, true);
      window.removeEventListener('resize', place);
    };
    trigger.addEventListener('click', (event) => {
      event.preventDefault();
      if (visible) return close();
      document.querySelectorAll('.warehouse-ir-page .ir-parts-details[open] summary').forEach((other) => {
        if (other !== trigger) other.click();
      });
      visible = true;
      details.open = true;
      panel.hidden = false;
      document.body.appendChild(panel);
      place();
      window.addEventListener('scroll', place, true);
      window.addEventListener('resize', place);
    });
    document.addEventListener('click', (event) => {
      if (visible && !details.contains(event.target) && !panel.contains(event.target)) close();
    });
    document.addEventListener('keydown', (event) => {
      if (visible && event.key === 'Escape') { close(); trigger.focus(); }
    });
  });
  const modal = document.getElementById('warehouseItemRequestDetails');
  if (!modal) return;
  let activeTrigger = null;
  let requestSequence = 0;
  const fields = {
    'warehouse-ir-number': 'irNumber',
    'warehouse-ir-job': 'irJob',
    'warehouse-ir-bus': 'irBus',
    'warehouse-ir-status': 'irStatus',
    'warehouse-ir-remarks': 'irRemarks',
  };

  const appendCell = (row, text, className = '') => {
    const td = document.createElement('td');
    td.textContent = String(text ?? '—');
    if (className) td.className = className;
    row.appendChild(td);
    return td;
  };

  const verify = async () => {
    const sequence = ++requestSequence;
    const tbody = document.getElementById('warehouse-ir-stock-rows');
    const message = document.getElementById('warehouse-ir-stock-message');
    const timestamp = document.getElementById('warehouse-ir-stock-time');
    const button = document.getElementById('warehouseIrRefreshStock');
    const url = activeTrigger?.dataset.irVerifyUrl;
    if (!tbody || !url) return;
    tbody.replaceChildren();
    const loading = document.createElement('tr');
    appendCell(loading, 'Checking current Warehouse stock...', 'warehouse-ir-stock-empty').colSpan = 5;
    tbody.appendChild(loading);
    message.textContent = '';
    timestamp.textContent = '';
    button.disabled = true;
    const issueButton = document.getElementById('warehouseIrIssueButton');
    if (issueButton) issueButton.disabled = true;
    try {
      const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
      if (!response.ok) throw new Error('Could not verify inventory (HTTP ' + response.status + ').');
      const data = await response.json();
      if (sequence !== requestSequence) return;
      tbody.replaceChildren();
      (data.parts || []).forEach((part) => {
        const tr = document.createElement('tr');
        const required = Number(part.quantity || 0);
        const available = Number(part.available || 0);
        const shortage = Number(part.shortage || 0);
        appendCell(tr, part.name);
        appendCell(tr, required + ' ' + (part.unit || ''));
        appendCell(tr, available + ' ' + (part.unit || ''));
        appendCell(tr, shortage + ' ' + (part.unit || ''));
        const status = part.fulfillable ? 'Available' : available > 0 ? 'Partial' : 'Unavailable';
        const cell = appendCell(tr, '');
        const badge = document.createElement('span');
        badge.className = 'warehouse-ir-stock-status is-' + status.toLowerCase();
        badge.textContent = status;
        cell.appendChild(badge);
        tbody.appendChild(tr);
      });
      if (!data.parts?.length) {
        const tr = document.createElement('tr');
        appendCell(tr, 'No requested parts are recorded.', 'warehouse-ir-stock-empty').colSpan = 5;
        tbody.appendChild(tr);
      }
      timestamp.textContent = 'Checked ' + new Date(data.checked_at).toLocaleString();
      if (issueButton && activeTrigger?.dataset.irStatus === 'Submitted') {
        issueButton.disabled = data.status !== 'sufficient';
      }
      message.textContent = data.status === 'sufficient'
        ? 'Requested parts appear available. Recheck inventory at physical issuance.'
        : data.status === 'partial'
          ? 'Partial availability: Warehouse must coordinate missing quantities and any partial-release decision.'
          : 'No requested quantities are currently available. Shortages require purchase processing.';
    } catch (error) {
      if (sequence !== requestSequence) return;
      tbody.replaceChildren();
      const tr = document.createElement('tr');
      appendCell(tr, 'Stock verification unavailable. Try Recheck Stock.', 'warehouse-ir-stock-empty').colSpan = 5;
      tbody.appendChild(tr);
      message.textContent = error.message;
    } finally {
      if (sequence === requestSequence) button.disabled = false;
    }
  };

  const close = () => {
    requestSequence++;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    activeTrigger?.focus();
  };

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-ir-view]');
    if (trigger && document.querySelector('.warehouse-ir-page')?.contains(trigger)) {
      activeTrigger = trigger;
      Object.entries(fields).forEach(([id, key]) => {
        const node = document.getElementById(id);
        if (node) node.textContent = trigger.dataset[key] || (key === 'irRemarks' ? 'No remarks provided.' : '—');
      });
      const form = document.getElementById('warehouseIrIssueForm');
      if (form) {
        form.action = trigger.dataset.irIssueUrl || '#';
        form.hidden = trigger.dataset.irStatus !== 'Submitted';
      }
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      void verify();
      modal.querySelector('[data-ir-close]')?.focus();
    }
    if (event.target.closest('#warehouseIrRefreshStock') && modal.contains(event.target)) void verify();
    if (event.target.closest('[data-ir-close]') && modal.contains(event.target)) close();
  });
  const issueForm = document.getElementById('warehouseIrIssueForm');
  issueForm?.addEventListener('submit', (event) => {
    const button = document.getElementById('warehouseIrIssueButton');
    if (button?.disabled || !window.confirm('Confirm that ALL requested parts have been physically handed over to Maintenance? This will permanently deduct their quantities from Warehouse inventory.')) {
      event.preventDefault();
      return;
    }
    button.disabled = true;
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal.classList.contains('is-open')) close();
  });
});
