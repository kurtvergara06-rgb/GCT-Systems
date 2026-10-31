// Inline receipt quantities are intentionally replaced by an isolated, accessible review dialog.
window.GCTPartialNavigation.registerInitializer('warehouse-incoming-receipts', '.incoming-delivery-page', function () {
  const modal = document.getElementById('incomingReceiptModal');
  const form = document.getElementById('incomingReceiptForm');
  const linesContainer = document.getElementById('incomingReceiptLines');
  const error = document.getElementById('incomingReceiptError');
  if (!modal || !form || !linesContainer) return;

  const close = () => {
    modal.hidden = true;
    modal.classList.remove('show', 'active');
    form.reset();
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
    const message = invalid
      ? 'Enter a whole quantity between 0 and the remaining amount for every item.'
      : !hasReceived ? 'Enter a received quantity greater than zero for at least one item.' : '';
    error.textContent = message;
    error.hidden = !message;
    return !message;
  };

  document.querySelectorAll('.open-incoming-receipt').forEach((button) => {
    button.addEventListener('click', () => {
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
        input.setAttribute('aria-label', 'Receive quantity for ' + String(line.name || 'item'));
        inputCell.appendChild(input);
        row.appendChild(inputCell);
        linesContainer.appendChild(row);
      });
      form.action = button.dataset.receiptUrl || '';
      document.getElementById('incomingReceiptPo').textContent = button.dataset.po || '—';
      document.getElementById('incomingReceiptSupplier').textContent = button.dataset.supplier || '—';
      error.hidden = true;
      modal.hidden = false;
      modal.classList.add('show', 'active');
      window.GCTModalBackdrop?.sync?.();
      linesContainer.querySelector('input:not([max="0"])')?.focus();
    });
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
