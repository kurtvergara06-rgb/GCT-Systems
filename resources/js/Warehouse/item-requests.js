window.GCTPartialNavigation?.registerInitializer?.('warehouse-ir-details', '.warehouse-ir-page', () => {
  const modal = document.getElementById('warehouseItemRequestDetails');
  if (!modal) return;
  let activeTrigger = null;
  const fields = {
    'warehouse-ir-number': 'irNumber',
    'warehouse-ir-job': 'irJob',
    'warehouse-ir-bus': 'irBus',
    'warehouse-ir-status': 'irStatus',
    'warehouse-ir-parts': 'irParts',
    'warehouse-ir-remarks': 'irRemarks',
  };
  const close = () => {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    activeTrigger?.focus();
  };
  const onClick = (event) => {
    const trigger = event.target.closest('[data-ir-view]');
    if (trigger && document.querySelector('.warehouse-ir-page')?.contains(trigger)) {
      activeTrigger = trigger;
      Object.entries(fields).forEach(([id, key]) => {
        const node = document.getElementById(id);
        if (!node) return;
        let value = trigger.dataset[key] || '';
        if (key === 'irParts') value = value.split(' | ').join('\n');
        node.textContent = value || (key === 'irRemarks' ? 'No remarks provided.' : '—');
      });
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      modal.querySelector('[data-ir-close]')?.focus();
    }
    if (event.target.closest('[data-ir-close]') && modal.contains(event.target)) close();
  };
  const onKey = (event) => { if (event.key === 'Escape' && modal.classList.contains('is-open')) close(); };
  document.addEventListener('click', onClick);
  document.addEventListener('keydown', onKey);
});
