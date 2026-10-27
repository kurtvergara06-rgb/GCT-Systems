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
  const actionsSlot = document.getElementById('warehouseIrReviewActions');
  let activeActions = null;
  let actionsHome = null;
  const restoreActions = () => {
    if (activeActions && actionsHome) {
      activeActions.hidden = true;
      actionsHome.appendChild(activeActions);
    }
    activeActions = null;
    actionsHome = null;
  };

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
    restoreActions();
    requestSequence++;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    activeTrigger?.focus();
  };

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-ir-view]');
    if (trigger && document.querySelector('.warehouse-ir-page')?.contains(trigger)) {
      restoreActions();
      activeTrigger = trigger;
      const actions = trigger.closest('tr')?.querySelector('.warehouse-ir-hidden-actions');
      if (actions && actionsSlot) {
        actionsHome = actions.parentElement;
        activeActions = actions;
        actions.hidden = false;
        actionsSlot.appendChild(actions);
      }
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

  const slipModal = document.getElementById('warehouseIrSlipModal');
  let slipSequence = 0;
  let slipStockEligible = false;
  const signedForm = document.getElementById('warehouseIrSignedIssueForm');
  const updateIssueButton = () => {
    const button = document.getElementById('ir-slip-confirm');
    if (!button || !signedForm) return;
    const receivingName = signedForm.querySelector('[name="received_by"]');
    const slipFile = signedForm.querySelector('[name="signed_slip"]');
    const handover = signedForm.querySelector('[name="handover_confirmed"]');
    const file = slipFile?.files?.[0];
    const acceptedFile = file && file.size <= 10 * 1024 * 1024 && /\.(jpe?g|png|pdf)$/i.test(file.name);
    button.disabled = !(slipStockEligible && receivingName?.value.trim() && acceptedFile && handover?.checked && signedForm.checkValidity());
  };
  signedForm?.addEventListener('input', updateIssueButton);
  signedForm?.addEventListener('change', updateIssueButton);
  const closeSlip = () => {
    slipSequence++;
    slipStockEligible = false;
    slipModal?.classList.remove('is-open');
    slipModal?.setAttribute('aria-hidden', 'true');
    document.getElementById('ir-slip-print').disabled = true;
  };
  const openSlip = async (trigger) => {
    if (!slipModal) return;
    const sequence = ++slipSequence;
    const set = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value || '—'; };
    set('ir-slip-number', trigger.dataset.irNumber);
    set('ir-slip-job', trigger.dataset.irJob);
    set('ir-slip-bus', trigger.dataset.irBus);
    set('ir-slip-mechanic', trigger.dataset.irMechanics);
    set('ir-slip-date', new Date().toLocaleDateString());
    const tbody = document.getElementById('ir-slip-lines');
    const msg = document.getElementById('ir-slip-message');
    const printButton = document.getElementById('ir-slip-print');
    printButton.disabled = true;
    const form = document.getElementById('warehouseIrSignedIssueForm');
    const confirm = document.getElementById('ir-slip-confirm');
    const lines = document.getElementById('ir-slip-quantities');
    slipStockEligible = false;
    form?.reset();
    if (form) form.action = trigger.dataset.irIssueUrl || '#';
    if (confirm) confirm.disabled = true;
    if (lines) lines.replaceChildren();
    tbody.replaceChildren();
    const pending = document.createElement('tr');
    appendCell(pending, 'Checking inventory...', 'warehouse-ir-stock-empty').colSpan = 7;
    tbody.appendChild(pending);
    slipModal.classList.add('is-open');
    slipModal.setAttribute('aria-hidden', 'false');
    try {
      const response = await fetch(trigger.dataset.irVerifyUrl, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!response.ok) throw Error('Stock verification failed (HTTP ' + response.status + ').');
      const data = await response.json();
      if (sequence !== slipSequence) return;
      tbody.replaceChildren();
      for (const [index, part] of (data.parts || []).entries()) {
        const tr = document.createElement('tr');
        appendCell(tr, index + 1);
        appendCell(tr, part.name);
        appendCell(tr, part.quantity + ' ' + part.unit);
        appendCell(tr, trigger.dataset.irBus);
        appendCell(tr, data.status === 'sufficient' ? part.quantity + ' ' + part.unit : '________');
        const signatureCell = appendCell(tr, '');
        const signatureLine = document.createElement('span');
        signatureLine.className = 'warehouse-ir-handwritten-line';
        signatureCell.appendChild(signatureLine);
        appendCell(tr, Number(part.shortage) > 0 ? part.shortage + ' ' + part.unit + ' short' : '—');
        tbody.appendChild(tr);
      }
      if (!data.parts?.length) throw Error('This request has no recorded parts.');
      if (data.status === 'sufficient' && form && lines) {
        (data.parts || []).forEach((part, index) => {
          // The slip table is the visible quantity record. Keep only the
          // validated full-issuance values in hidden fields for submission.
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'issued_quantities[' + index + ']';
          input.value = String(part.quantity);
          lines.appendChild(input);
        });
        slipStockEligible = true;
        updateIssueButton();
      } else {
        const notice = document.createElement('p');
        notice.textContent = 'Partial/unavailable IR: issuance confirmation is disabled pending a Head-approved partial release.';
        if (lines) lines.appendChild(notice);
      }
      msg.querySelector('span').textContent = data.status === 'sufficient'
        ? 'Stock is currently available. This preview does not reserve or issue items. Signatures and confirmed handover are required before inventory deduction.'
        : 'Some items are unavailable or short. This slip is a draft only: quantities to issue must be decided and approved before handover. Nothing has been deducted.';
      printButton.disabled = false;
    } catch (error) {
      if (sequence !== slipSequence) return;
      tbody.replaceChildren();
      const row = document.createElement('tr');
      appendCell(row, error.message, 'warehouse-ir-stock-empty').colSpan = 7;
      tbody.appendChild(row);
      msg.querySelector('span').textContent = 'Unable to prepare the slip until stock verification succeeds.';
    }
  };
  document.getElementById('warehouseIrSignedIssueForm')?.addEventListener('submit', (event) => {
    const button = document.getElementById('ir-slip-confirm');
    updateIssueButton();
    if (button.disabled || !window.confirm('Confirm signed physical handover? This will deduct Warehouse stock and move the IR to History.')) {
      event.preventDefault();
      return;
    }
    button.disabled = true;
  });
  document.addEventListener('click', (event) => {
    const prepare = event.target.closest('[data-ir-prepare]');
    if (prepare && (document.querySelector('.warehouse-ir-page')?.contains(prepare) || document.getElementById('warehouseItemRequestDetails')?.contains(prepare))) { close(); void openSlip(prepare); }
    if (event.target.closest('[data-ir-slip-close]') && slipModal?.contains(event.target)) closeSlip();
    if (event.target.closest('#ir-slip-print') && slipModal?.contains(event.target)) window.print();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal.classList.contains('is-open')) close();
    if (event.key === 'Escape' && slipModal?.classList.contains('is-open')) closeSlip();
  });
});
