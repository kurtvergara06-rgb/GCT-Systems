// Inline receipt quantities are intentionally replaced by an isolated, accessible review dialog.
window.GCTPartialNavigation.registerInitializer('warehouse-incoming-receipts', '.incoming-delivery-page', function () {
  const modal = document.getElementById('incomingReceiptModal');
  const form = document.getElementById('incomingReceiptForm');
  const linesContainer = document.getElementById('incomingReceiptLines');
  const error = document.getElementById('incomingReceiptError');
  if (!modal || !form || !linesContainer) return;
  const allButton = document.getElementById('incomingReceiveAll');
  const partialButton = document.getElementById('incomingReceivePartial');
  const submitButton = document.getElementById('incomingReceiptSubmit');
  let receiptMode = null;
  const selectMode = (mode) => {
    receiptMode = mode;
    allButton?.setAttribute('aria-pressed', String(mode === 'all'));
    partialButton?.setAttribute('aria-pressed', String(mode === 'partial'));
    if (submitButton) submitButton.disabled = !mode;
    linesContainer.querySelectorAll('input[type="number"]').forEach((input) => {
      input.readOnly = mode !== 'partial';
      input.value = mode === 'all' ? input.max : '0';
    });
    if (error) { error.textContent = ''; error.hidden = true; }
    if (mode === 'partial') linesContainer.querySelector('input:not([max="0"])')?.focus();
  };
  allButton?.addEventListener('click', () => selectMode('all'));
  partialButton?.addEventListener('click', () => selectMode('partial'));

  const close = () => {
    modal.hidden = true;
    modal.classList.remove('show', 'active');
    form.reset();
    receiptMode = null;
    allButton?.setAttribute('aria-pressed', 'false');
    partialButton?.setAttribute('aria-pressed', 'false');
    if (submitButton) submitButton.disabled = true;
    linesContainer.replaceChildren();
    window.GCTModalBackdrop?.sync?.();
  };

  const validate = () => {
    const inputs = [...linesContainer.querySelectorAll('input[type="number"]')];
    const invalid = inputs.some((input) =>
      input.value.trim() === '' || !Number.isInteger(Number(input.value)) ||
      Number(input.value) < 0 || Number(input.value) > Number(input.max)
    );
    const hasReceived = inputs.some((input) => Number(input.value) > 0);
    const message = !receiptMode ? 'Choose Receive All or Partial Receipt before confirming.'
      : invalid
      ? 'Enter a whole quantity between 0 and the remaining amount for every item.'
      : !hasReceived ? 'Enter a received quantity greater than zero for at least one item.' : '';
    error.textContent = message;
    error.hidden = !message;
    return !message;
  };

  // Delegate click handling so buttons replaced by partial navigation still work.
  document.addEventListener('click', (event) => {
    const button = event.target.closest('.open-incoming-receipt');
    if (!button || !document.querySelector('.incoming-delivery-page')) return;
    event.preventDefault();
    {
      let lines;
      try { lines = JSON.parse(button.dataset.lines || '[]'); } catch (_) { lines = []; }
      if (!Array.isArray(lines) || !lines.length) return;
      linesContainer.replaceChildren();
      lines.forEach((line) => {
        const row = document.createElement('tr');
        const cell = (value) => {
          const td = document.createElement('td');
          td.textContent = String(value ?? '—');
          row.appendChild(td);
          return td;
        };
        cell(line.name);
        cell(line.ordered);
        cell(line.received);
        cell(line.remaining);
        const inputCell = document.createElement('td');
        const input = document.createElement('input');
        input.type = 'number';
        input.name = 'received_quantities[' + Number(line.index) + ']';
        input.min = '0';
        input.max = String(Math.max(0, Number(line.remaining)));
        input.step = '1';
        input.required = true;
        input.value = '0';
        input.readOnly = true;
        input.setAttribute('aria-label', 'Receive quantity for ' + String(line.name || 'item'));
        inputCell.appendChild(input);
        row.appendChild(inputCell);
        linesContainer.appendChild(row);
      });
      form.action = button.dataset.receiptUrl || '';
      document.getElementById('incomingReceiptPo').textContent = button.dataset.po || '—';
      document.getElementById('incomingReceiptSupplier').textContent = button.dataset.supplier || '—';
      selectMode(null);
      modal.hidden = false;
      modal.classList.add('show', 'active');
      window.GCTModalBackdrop?.sync?.();
      allButton?.focus();
    }
  });

  form.addEventListener('submit', (event) => {
    if (!validate()) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);
  document.getElementById('incomingReceiptClose')?.addEventListener('click', close);
  document.getElementById('incomingReceiptCancel')?.addEventListener('click', close);
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal.hidden) close();
  });
});
