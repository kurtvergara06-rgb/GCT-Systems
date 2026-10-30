window.GCTPartialNavigation.registerInitializer('warehouse-part-requests-cleanup', '.warehouse-part-main', function () {
  function setField(id, value, fallback = '—') {
    const element = document.getElementById(id);
    if (!element) return;

    const finalValue =
      value === undefined || value === null || value === '' || value === 'null'
        ? fallback
        : value;

    if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
      element.value = finalValue;
    } else {
      element.textContent = finalValue;
    }
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;');
  }

  function renderIssuedQuantities(items) {
    const container = document.getElementById('view_issue_quantities');
    if (!container) return;

    if (!Array.isArray(items) || items.length === 0) {
      container.innerHTML = '<div class="parts-breakdown-empty">No parts have been issued yet.</div>';
      return;
    }

    container.innerHTML = `
      <div class="warehouse-issued-list">
        ${items.map(function (item) {
          const requested = item.requested ?? 0;
          const issued = item.issued ?? 0;
          const unit = item.unit || '';

          return `
            <div class="warehouse-issued-row">
              <strong>${escapeHtml(item.name || 'Part')}</strong>
              <span>${escapeHtml(issued)}${unit ? ` ${escapeHtml(unit)}` : ''} issued / ${escapeHtml(requested)} requested</span>
            </div>
          `;
        }).join('')}
      </div>
    `;
  }

  document.addEventListener('click', function (event) {
    const button = event.target.closest('.open-view-pr-modal');
    if (!button) return;

    setField('view_purchase_status', button.dataset.status);
    setField('view_warehouse_status', button.dataset.warehouseStatus);

    let issuedQuantities = [];
    try {
      issuedQuantities = JSON.parse(button.dataset.issuedQuantities || '[]');
    } catch (error) {
      issuedQuantities = [];
    }

    renderIssuedQuantities(issuedQuantities);
  });

  // Floating On Hand/Item breakdown: native <details> inside overflow-x:auto
  // is clipped even when overflow-y is set to visible. Portal the popover.
  let breakdown = null;
  const closeBreakdown = () => {
    if (!breakdown) return;
    breakdown.details.open = false;
    breakdown.popup.remove();
    breakdown = null;
  };
  const placeBreakdown = () => {
    if (!breakdown) return;
    const rect = breakdown.summary.getBoundingClientRect();
    const width = Math.min(350, Math.max(210, window.innerWidth - 24));
    const left = Math.max(12, Math.min(rect.left, window.innerWidth - width - 12));
    const preferredHeight = Math.min(breakdown.popup.scrollHeight, 290);
    const below = window.innerHeight - rect.bottom - 12;
    const above = rect.top - 12;
    const openAbove = below < Math.min(preferredHeight, 140) && above > below;
    Object.assign(breakdown.popup.style, {
      position: 'fixed', zIndex: '12000', left: left + 'px',
      width: width + 'px', right: 'auto',
      top: openAbove ? 'auto' : rect.bottom + 6 + 'px',
      bottom: openAbove ? window.innerHeight - rect.top + 6 + 'px' : 'auto',
      maxHeight: Math.max(80, Math.min(290, openAbove ? above : below)) + 'px',
      overflowY: 'auto', display: 'block', visibility: 'visible'
    });
  };
  document.addEventListener('click', function (event) {
    const summary = event.target.closest('.warehouse-part-main .warehouse-pr-parts-more > summary');
    if (summary) {
      event.preventDefault();
      const details = summary.parentElement;
      if (breakdown?.details === details) { closeBreakdown(); return; }
      closeBreakdown();
      const template = details.querySelector('.warehouse-pr-parts-popup');
      if (!template) return;
      const popup = template.cloneNode(true);
      popup.classList.add('warehouse-pr-floating-popup');
      document.body.appendChild(popup);
      details.open = true;
      breakdown = { details, summary, popup };
      placeBreakdown();
      return;
    }
    if (breakdown && !breakdown.popup.contains(event.target)) closeBreakdown();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && breakdown) { closeBreakdown(); }
  });
  window.addEventListener('resize', placeBreakdown);
  window.addEventListener('scroll', () => { if (breakdown) closeBreakdown(); }, true);

});
