window.GCTPartialNavigation?.registerInitializer?.('warehouse-ir-details', '.warehouse-ir-page', () => {
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
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      void verify();
      modal.querySelector('[data-ir-close]')?.focus();
    }
    if (event.target.closest('#warehouseIrRefreshStock') && modal.contains(event.target)) void verify();
    if (event.target.closest('[data-ir-close]') && modal.contains(event.target)) close();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal.classList.contains('is-open')) close();
  });
});
