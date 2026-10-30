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
    'warehouse-ir-requester': 'irRequester',
    'warehouse-ir-date': 'irDate',
    'warehouse-ir-parts-count': 'irPartsCount',
    'warehouse-ir-status': 'irStatus',
    'warehouse-ir-release-policy': 'irReleasePolicy',
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
    appendCell(loading, 'Checking current Warehouse stock...', 'warehouse-ir-stock-empty').colSpan = 7;
    tbody.appendChild(loading);
    message.textContent = '';
    timestamp.textContent = '';
    const coverageLoading = document.getElementById('warehouse-ir-jo-coverage-rows');
    if (coverageLoading) { coverageLoading.replaceChildren(); const row = document.createElement('tr'); appendCell(row, 'Checking linked Job Order...', 'warehouse-ir-stock-empty').colSpan = 4; coverageLoading.appendChild(row); }
    button.disabled = true;

    try {
      const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
      if (!response.ok) throw new Error('Could not verify inventory (HTTP ' + response.status + ').');
      const data = await response.json();
      if (sequence !== requestSequence) return;
      tbody.replaceChildren();
      const irParts = Array.isArray(data.parts) ? data.parts : [];
      let joParts = [];
      try { joParts = JSON.parse(activeTrigger?.dataset.irJoParts || '[]'); } catch (_) { joParts = []; }
      const key = (part) => String(part.name || '').toLowerCase().replace(/\s*\[item:\d+\]/g, '').trim() + '|' + String(part.unit || '').toLowerCase().trim();
      const joByKey = new Map(joParts.map(part => [key(part), part]));
      irParts.forEach((part) => {
        const tr = document.createElement('tr');
        const available = Number(part.available || 0);
        const remaining = Number(part.quantity || 0);
        const required = Number(part.requested_total ?? remaining);
        const joPart = joByKey.get(key(part));
        appendCell(tr, part.name);
        appendCell(tr, joPart ? Number(joPart.quantity || 0) + ' ' + (part.unit || '') : '—');
        appendCell(tr, required + ' ' + (part.unit || ''));
        appendCell(tr, Number(part.on_hand ?? 0) + ' ' + (part.unit || ''), 'warehouse-ir-stock-physical');
        appendCell(tr, available + ' ' + (part.unit || ''));
        appendCell(tr, Number(part.shortage || 0) + ' ' + (part.unit || ''));
        const status = part.fulfillable ? 'Available' : available > 0 ? 'Partial' : 'Unavailable';
        const cell = appendCell(tr, '');
        const badge = document.createElement('span');
        badge.className = 'warehouse-ir-stock-status is-' + status.toLowerCase();
        badge.textContent = status;
        cell.appendChild(badge);
        tbody.appendChild(tr);
      });
      const coverage = document.getElementById('warehouse-ir-jo-coverage-rows');
      if (coverage) {
        coverage.replaceChildren();
        const irQuantities = new Map(irParts.map(part => [key(part), Number(part.requested_total ?? part.quantity ?? 0)]));
        if (joParts.length) {
          joParts.forEach(part => {
            const total = Number(part.quantity || 0);
            const included = Math.min(total, irQuantities.get(key(part)) || 0);
            const outside = Math.max(0, total - included);
            const unit = part.unit || '';
            const row = document.createElement('tr');
            appendCell(row, part.name || '—');
            appendCell(row, total + ' ' + unit);
            appendCell(row, included + ' ' + unit);
            appendCell(row, outside + ' ' + unit, outside ? 'warehouse-ir-jo-outside' : '');
            
            coverage.appendChild(row);
          });
        } else {
          const row = document.createElement('tr');
          appendCell(row, 'JO requirements are not available for comparison.', 'warehouse-ir-stock-empty').colSpan = 4;
          coverage.appendChild(row);
        }
      }
      if (!data.parts?.length) {
        const tr = document.createElement('tr');
        appendCell(tr, 'No requested parts are recorded.', 'warehouse-ir-stock-empty').colSpan = 7;
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
      appendCell(tr, 'Stock verification unavailable. Try Recheck Stock.', 'warehouse-ir-stock-empty').colSpan = 7;
      tbody.appendChild(tr);
      message.textContent = error.message;
      const coverage = document.getElementById('warehouse-ir-jo-coverage-rows');
      if (coverage) { coverage.replaceChildren(); const row = document.createElement('tr'); appendCell(row, 'JO coverage could not be verified.', 'warehouse-ir-stock-empty').colSpan = 4; coverage.appendChild(row); }
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
      const signedSlipLink = document.getElementById('warehouseIrSignedSlipLink');
      if (signedSlipLink) {
        const slipUrl = trigger.dataset.irSignedSlipUrl;
        signedSlipLink.hidden = !slipUrl;
        if (slipUrl) signedSlipLink.href = slipUrl;
        else signedSlipLink.removeAttribute('href');
      }
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
    const quantities = Array.from(signedForm.querySelectorAll('input[name^="issued_quantities["]'));
    const hasPositiveQuantity = quantities.some((input) => Number(input.value) > 0);
    button.disabled = !(slipStockEligible && hasPositiveQuantity && receivingName?.value.trim() && acceptedFile && handover?.checked && signedForm.checkValidity());
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
    set('ir-slip-release-policy', 'Checking Head authorization...');
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
      set('ir-slip-release-policy', data.release_policy === 'issue_available_only' ? 'Issue Available Parts' : 'Wait Until Complete');
      if (sequence !== slipSequence) return;
      tbody.replaceChildren();
      for (const [index, part] of (data.parts || []).entries()) {
        const tr = document.createElement('tr');
        appendCell(tr, index + 1);
        appendCell(tr, part.name);
        appendCell(tr, part.quantity + ' ' + part.unit);
        appendCell(tr, trigger.dataset.irBus);
        const issuedCell = appendCell(tr, '0 ' + part.unit);
        issuedCell.dataset.issuanceIndex = String(index);
        const signatureCell = appendCell(tr, '');
        const signatureLine = document.createElement('span');
        signatureLine.className = 'warehouse-ir-handwritten-line';
        signatureCell.appendChild(signatureLine);
        appendCell(tr, Number(part.shortage) > 0 ? part.shortage + ' ' + part.unit + ' short' : '—');
        tbody.appendChild(tr);
      }
      set('ir-slip-total-parts', String(data.parts?.length ?? 0));
      if (!data.parts?.length) throw Error('This request has no recorded parts.');
      const policyAllowsPartial = data.release_policy === 'issue_available_only';
      const canPrepare = data.status === 'sufficient' || (policyAllowsPartial && data.status === 'partial');
      if (canPrepare && form && lines) {
        (data.parts || []).forEach((part, index) => {
          const cap = Math.min(Number(part.quantity) || 0, Number(part.available) || 0);
          // The authorized issue amount is fixed; keep it in the form for
          // server validation but do not expose an editable quantity field.
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'issued_quantities[' + index + ']';
          input.value = String(policyAllowsPartial ? cap : Number(part.quantity));
          lines.appendChild(input);
          const cell = tbody.querySelector('[data-issuance-index="' + index + '"]');
          if (cell) cell.textContent = input.value + ' ' + part.unit;
        });
        slipStockEligible = true;
        updateIssueButton();
      } else {
        const notice = document.createElement('p');
        notice.textContent = data.status === 'none'
          ? 'No allocatable stock remains. Issuance is blocked.'
          : 'The Head selected Wait Until Complete. No partial handover is permitted.';
        if (lines) lines.appendChild(notice);
      }
      msg.querySelector('span').textContent = canPrepare
        ? 'Review issued quantities and sign the physical handover slip. Inventory is deducted only after confirmation.'
        : 'Issuance is blocked by current stock availability or the Maintenance Head release decision.';
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
  document.addEventListener('click', (event) => {
    const prepare = event.target.closest('[data-ir-prepare]');
    if (prepare && document.querySelector('.warehouse-ir-page')?.contains(prepare)) void openSlip(prepare);
    if (event.target.closest('[data-ir-slip-close]') && slipModal?.contains(event.target)) closeSlip();
    if (event.target.closest('#ir-slip-print') && slipModal?.contains(event.target)) window.print();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal.classList.contains('is-open')) close();
    if (event.key === 'Escape' && slipModal?.classList.contains('is-open')) closeSlip();
  });
});
