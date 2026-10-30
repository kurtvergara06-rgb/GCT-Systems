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


  function fillHeadAuthorization(modal, source) {
    if (!modal || !source) return;
    const name = modal.querySelector('[data-pr-head-name]');
    const status = modal.querySelector('[data-pr-head-status]');
    const date = modal.querySelector('[data-pr-head-date]');
    const img = modal.querySelector('[data-pr-head-signature]');
    const unavailable = modal.querySelector('[data-pr-head-no-signature]');
    if (name) name.textContent = source.joApprover || 'Not available';
    if (status) status.textContent = 'Linked JO: ' + (source.joApprovalStatus || 'Not verified');
    if (date) date.textContent = source.joApprovedAt || '';
    const signatureUrl = source.joApprovalSignature || '';
    if (img) {
      img.hidden = !signatureUrl;
      if (signatureUrl) img.src = signatureUrl;
      else img.removeAttribute('src');
    }
    if (unavailable) unavailable.hidden = Boolean(signatureUrl);
  }

  document.addEventListener('click', function (event) {
    const button = event.target.closest('.open-view-pr-modal');
    if (!button) return;
    fillHeadAuthorization(document.getElementById('viewPrModal'), button.dataset);

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


  // Preview missing PR lines before allowing the Warehouse send action.
  const sendModal = document.getElementById('warehouseSendMissingModal');
  const sendConfirm = document.getElementById('sendMissingConfirm');
  let pendingSendForm = null;
  function closeSendModal() {
    if (!sendModal) return;
    sendModal.classList.remove('show', 'active');
    sendModal.setAttribute('aria-hidden', 'true');
    sendModal.style.display = 'none';
    pendingSendForm = null;
  }
  document.addEventListener('submit', function (event) {
    const form = event.target.closest('.warehouse-part-main form[data-pr-send-form]');
    if (!form || form.dataset.sendConfirmed === '1') return;
    event.preventDefault();
    if (!sendModal) return;
    pendingSendForm = form;
    fillHeadAuthorization(sendModal, form.dataset);
    setField('sendMissingPr', form.dataset.prNo);
    setField('sendMissingJo', form.dataset.joNo);
    setField('sendMissingBus', form.dataset.busNo);
    let parts = [];
    try { parts = JSON.parse(form.dataset.parts || '[]'); } catch (_) {}
    const tbody = document.getElementById('sendMissingPartsRows');
    if (tbody) {
      const rows = parts.filter(part => {
        const qty = Number(part.needed ?? part.quantity ?? 0);
        return qty > 0;
      });
      tbody.innerHTML = rows.length ? rows.map(part => {
        const name = escapeHtml(String(part.name || part.item || 'Part').replace(/\s*\[item:\d+\]/gi, '').trim());
        const qty = escapeHtml(part.needed_display || ((part.needed ?? part.quantity ?? 0) + ' ' + (part.unit || '')));
        const stock = escapeHtml(part.physical_on_hand_display || ((part.physical_on_hand ?? 0) + ' ' + (part.unit || '')));
        return '<tr><td>' + name + '</td><td>' + qty + '</td><td>' + stock + '</td></tr>';
      }).join('') : '<tr><td colspan="3">No requested parts available for preview.</td></tr>';
    }
    sendModal.style.display = 'flex';
    sendModal.classList.add('show', 'active');
    sendModal.setAttribute('aria-hidden', 'false');
    sendModal.querySelector('.warehouse-edit-style-modal')?.focus();
  });
  sendConfirm?.addEventListener('click', function () {
    if (!pendingSendForm) return;
    pendingSendForm.dataset.sendConfirmed = '1';
    sendConfirm.disabled = true;
    pendingSendForm.requestSubmit();
  });
  sendModal?.querySelectorAll('[data-send-missing-close]').forEach(button => button.addEventListener('click', closeSendModal));
  sendModal?.addEventListener('click', event => { if (event.target === sendModal) closeSendModal(); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && sendModal?.classList.contains('show')) closeSendModal(); });

});
