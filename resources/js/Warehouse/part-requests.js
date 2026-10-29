window.GCTPartialNavigation.registerInitializer('warehouse-part-requests', '.warehouse-part-main', function () {

  // Floating JO parts menu avoids clipping inside the horizontally scrollable table.
  let joPartsMenu = null;
  let joPartsButton = null;
  const closeJoParts = () => {
    joPartsMenu?.remove();
    joPartsButton?.setAttribute('aria-expanded', 'false');
    joPartsMenu = null;
    joPartsButton = null;
  };
  const positionJoParts = () => {
    if (!joPartsMenu || !joPartsButton?.isConnected) return closeJoParts();
    const r = joPartsButton.getBoundingClientRect();
    const margin = 8;
    const width = Math.min(330, window.innerWidth - 2 * margin);
    joPartsMenu.style.width = width + 'px';
    joPartsMenu.style.left = Math.max(margin, Math.min(r.left, window.innerWidth - width - margin)) + 'px';
    const below = window.innerHeight - r.bottom;
    const above = r.top;
    const height = joPartsMenu.scrollHeight;
    const flip = below < Math.min(height + margin, 220) && above > below;
    const available = Math.max(90, (flip ? above : below) - 2 * margin);
    joPartsMenu.style.maxHeight = Math.min(230, available) + 'px';
    joPartsMenu.style.top = (flip ? Math.max(margin, r.top - Math.min(height, 230, available) - margin) : r.bottom + margin) + 'px';
  };
  const joPartsPage = document.querySelector('.warehouse-part-main');
  joPartsPage?.addEventListener('click', event => {
    const button = event.target.closest('.warehouse-jo-parts-more');
    if (!button || !joPartsPage.contains(button)) return;
    event.preventDefault();
    if (joPartsButton === button) return closeJoParts();
    closeJoParts();
    const template = button.parentElement.querySelector('.warehouse-jo-parts-template');
    if (!template) return;
    joPartsButton = button;
    joPartsMenu = template.cloneNode(true);
    joPartsMenu.removeAttribute('hidden');
    joPartsMenu.classList.remove('warehouse-jo-parts-template');
    joPartsMenu.classList.add('warehouse-jo-parts-popup');
    joPartsMenu.setAttribute('role', 'dialog');
    joPartsMenu.setAttribute('aria-label', 'Other requested parts');
    document.body.appendChild(joPartsMenu);
    button.setAttribute('aria-expanded', 'true');
    positionJoParts();
  });
  document.addEventListener('click', event => {
    if (joPartsButton?.contains(event.target) || joPartsMenu?.contains(event.target)) return;
    closeJoParts();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') closeJoParts();
  });
  window.addEventListener('resize', closeJoParts);
  document.addEventListener('scroll', event => {
    if (joPartsMenu && !joPartsMenu.contains(event.target)) closeJoParts();
  }, true);

  function openModal(modal) {
    if (!modal) return;

    modal.classList.add('show');
    modal.classList.add('active');
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
    delete modal.dataset.gsapClosing;

    const scrollable = modal.querySelector('.warehouse-modal-scrollable');
    if (scrollable) {
      scrollable.scrollTop = 0;
    }

    window.GCTModalBackdrop?.sync?.();
    window.GCTSystemAnimations?.animateModalOpen?.(modal);
  }

  function closeModal(modal) {
    if (!modal) return;

    const finishClose = function () {
      modal.classList.remove('show');
      modal.classList.remove('active');
      modal.style.display = 'none';
      modal.setAttribute('aria-hidden', 'true');
      window.GCTModalBackdrop?.sync?.();
    };

    if (!window.GCTSystemAnimations?.animateModalClose?.(modal, finishClose)) {
      finishClose();
    }
  }

  function closeAllModals() {
    document
      .querySelectorAll(
        '.modal-overlay, .delete-modal-overlay, .success-modal-overlay, .error-modal-overlay, .feedback-modal-overlay, .action-modal-overlay'
      )
      .forEach(function (modal) {
        closeModal(modal);
      });
  }

  function setField(id, value, fallback = '—') {
    const element = document.getElementById(id);

    if (!element) return;

    const finalValue =
      value === undefined || value === null || value === '' || value === 'null'
        ? fallback
        : value;

    if (
      element.tagName === 'INPUT' ||
      element.tagName === 'TEXTAREA' ||
      element.tagName === 'SELECT'
    ) {
      element.value = finalValue;
    } else {
      element.textContent = finalValue;
    }
  }

  function escapeHtml(value) {
    return String(value || '')
      .replaceAll('&', '&amp;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;');
  }

  function slugStatus(value) {
    return String(value || '')
      .toLowerCase()
      .replace(/\//g, '-')
      .replace(/\s+/g, '-');
  }

  function partIdentity(part) {
    const name = String(part?.name || '')
      .replace(/\s*\[item:\d+\]/gi, '')
      .trim()
      .toLowerCase();
    const unit = String(part?.unit || '').trim().toLowerCase();
    return `${name}|${unit}`;
  }

  function renderPartsBreakdown(parts, joParts, processingStatus) {
    const container = document.getElementById('view_parts_breakdown');
    const stockContainer = document.getElementById('view_stock_reference');
    if (!container) return;
    if (!Array.isArray(parts) || !parts.length) {
      container.innerHTML = '<div class="parts-breakdown-empty">No parts found.</div>';
      if (stockContainer) {
        stockContainer.innerHTML = '<div class="parts-breakdown-empty">No stock reference found.</div>';
      }
      return;
    }

    const cleanName = value => String(value || '—').replace(/\s*\[item:\d+\]/gi, '').trim();
    const joRequirements = new Map(
      (Array.isArray(joParts) ? joParts : []).map(part => [partIdentity(part), part])
    );

    const cells = parts.map(part => {
      const unit = String(part.unit || '').trim();
      const quantityValue = Number(part.needed ?? part.quantity ?? 0);
      const quantity = part.needed_display ?? `${quantityValue}${unit ? ` ${unit}` : ''}`;
      const onHandValue = Number(part.physical_on_hand ?? 0);
      const onHand = part.physical_on_hand_display ?? `${onHandValue}${unit ? ` ${unit}` : ''}`;
      const joPart = joRequirements.get(partIdentity(part));
      const joQuantityValue = Number(joPart?.quantity ?? part.jo_required ?? quantityValue);
      const joQuantity = `${joQuantityValue}${unit ? ` ${unit}` : ''}`;
      const shortage = part.shortage_display ?? quantity;
      const needsPurchase = /needs purchase|waiting for purchase|waiting for stock/i.test(processingStatus || '')
        || onHandValue < joQuantityValue;
      const status = needsPurchase ? 'Needs Purchase' : (processingStatus || 'Ready to Issue');
      const statusClass = needsPurchase ? 'is-needs-purchase' : 'is-ready';
      const stockClass = onHandValue > 0 ? 'is-positive' : 'is-empty';

      return `<tr>
        <td>${escapeHtml(cleanName(part.name))}</td>
        <td>${escapeHtml(joQuantity)}</td>
        <td>${escapeHtml(quantity)}</td>
        <td class="warehouse-pr-physical-stock ${stockClass}">${escapeHtml(onHand)}</td>
        <td class="warehouse-pr-shortage">${escapeHtml(shortage)}</td>
        <td><span class="warehouse-pr-line-status ${statusClass}">${escapeHtml(status)}</span></td>
      </tr>`;
    }).join('');

    container.innerHTML = '<div class="warehouse-pr-view-table-wrap"><table class="warehouse-pr-view-table warehouse-pr-parts-table"><thead><tr><th>PART NAME</th><th>JO REQUIRED</th><th>PR QUANTITY</th><th>ON HAND</th><th>SHORTAGE</th><th>STATUS</th></tr></thead><tbody>' + cells + '</tbody></table></div>';

    if (stockContainer) {
      const stockRows = parts.map(part => {
        const unit = String(part.unit || '').trim();
        const onHandValue = Math.max(0, Number(part.physical_on_hand ?? 0));
        const onHand = part.physical_on_hand_display ?? `${onHandValue}${unit ? ` ${unit}` : ''}`;
        const joPart = joRequirements.get(partIdentity(part));
        const required = Math.max(0, Number(joPart?.quantity ?? part.jo_required ?? part.needed ?? part.quantity ?? 0));
        const issuableValue = Math.min(onHandValue, required);
        const issuable = `${issuableValue}${unit ? ` ${unit}` : ''}`;
        const stockClass = onHandValue > 0 ? 'is-positive' : 'is-empty';
        const remarks = onHandValue <= 0 ? 'Out of stock' : (onHandValue < required ? 'Partial stock only' : 'Available in full');

        return `<tr>
          <td>${escapeHtml(cleanName(part.name))}</td>
          <td class="warehouse-pr-stock-value ${stockClass}">${escapeHtml(onHand)}</td>
          <td class="warehouse-pr-stock-value ${stockClass}">${escapeHtml(issuable)}</td>
          <td>${escapeHtml(remarks)}</td>
        </tr>`;
      }).join('');

      stockContainer.innerHTML = '<div class="warehouse-pr-view-table-wrap"><table class="warehouse-pr-view-table warehouse-pr-stock-table"><thead><tr><th>PART NAME</th><th>PHYSICAL ON HAND</th><th>ISSUABLE NOW</th><th>REMARKS</th></tr></thead><tbody>' + stockRows + '</tbody></table></div>';
    }
  }

  const viewPrModal = document.getElementById('viewPrModal');
  const closeViewPrModal = document.getElementById('closeViewPrModal');
  const closeViewPrModalBottom = document.getElementById('closeViewPrModalBottom');

  document.addEventListener('click', function (event) {
    const button = event.target.closest('.open-view-pr-modal');

    if (!button) return;

    event.preventDefault();

    let parts = [];
    let joParts = [];

    try {
      parts = JSON.parse(button.dataset.parts || '[]');
    } catch (error) {
      parts = [];
    }

    try {
      joParts = JSON.parse(button.dataset.joParts || '[]');
    } catch (error) {
      joParts = [];
    }

    setField('view_pr_no', button.dataset.prNo);
    setField('view_job_order_no', button.dataset.jobOrderNo);
    setField('view_bus_no', button.dataset.busNo);
    setField('view_requested_by', button.dataset.requestedBy);
    setField('view_created', button.dataset.created);
    setField('view_processing', button.dataset.warehouseStatus);
    setField('view_warehouse_status_badge', button.dataset.warehouseStatus);
    setField('view_pr_parts_count', String(parts.length));

    const statusBadge = document.getElementById('view_warehouse_status_badge');
    if (statusBadge) {
      statusBadge.dataset.status = slugStatus(button.dataset.warehouseStatus);
    }

    setField('view_remarks', button.dataset.remarks, 'No remarks');

    renderPartsBreakdown(parts, joParts, button.dataset.warehouseStatus);

    openModal(viewPrModal);
  });

  if (closeViewPrModal) {
    closeViewPrModal.addEventListener('click', function () {
      closeModal(viewPrModal);
    });
  }

  if (closeViewPrModalBottom) {
    closeViewPrModalBottom.addEventListener('click', function () {
      closeModal(viewPrModal);
    });
  }

  // Intercept the eligible Send Missing Parts form; no POST occurs until confirmed.
  const purchaseFeedbackModal = document.getElementById('warehousePurchaseFeedbackModal');
  const showPurchaseFeedback = (kind, message) => {
    if (!purchaseFeedbackModal) return;
    const icon = document.getElementById('warehousePurchaseFeedbackIcon');
    const title = document.getElementById('warehousePurchaseFeedbackTitle');
    const subtitle = document.getElementById('warehousePurchaseFeedbackSubtitle');
    purchaseFeedbackModal.dataset.kind = kind;
    if (title) title.textContent = kind === 'success' ? 'Parts Request Sent' :
      kind === 'empty' ? 'No Missing Parts to Submit' : 'Unable to Send Request';
    if (subtitle) subtitle.textContent = kind === 'success' ? 'Purchase Department processing' :
      kind === 'empty' ? 'There are no missing parts in this request.' : 'The request was not submitted.';
    if (icon) {
      const i = icon.querySelector('i');
      if (i) i.className = kind === 'success' ? 'fa-solid fa-circle-check' :
        kind === 'empty' ? 'fa-solid fa-circle-info' : 'fa-solid fa-circle-xmark';
    }
    setField('warehousePurchaseFeedbackMessage', message);
    openModal(purchaseFeedbackModal);
  };
  purchaseFeedbackModal?.querySelectorAll('[data-purchase-feedback-close]').forEach(button => {
    button.addEventListener('click', () => closeModal(purchaseFeedbackModal));
  });
  if (purchaseFeedbackModal?.dataset.serverResult) {
    showPurchaseFeedback(purchaseFeedbackModal.dataset.serverResult, purchaseFeedbackModal.dataset.serverMessage);
  }

  const sendMissingModal = document.getElementById('warehouseSendMissingModal');
  const sendMissingConfirm = document.getElementById('sendMissingConfirm');
  let pendingMissingForm = null;
  document.addEventListener('submit', event => {
    const form = event.target.closest?.('form[data-pr-send-form]');
    if (!form || !sendMissingModal || !sendMissingConfirm || form.dataset.confirmedSend === '1') return;
    event.preventDefault();
    pendingMissingForm = form;
    setField('sendMissingPr', form.dataset.prNo);
    setField('sendMissingJo', form.dataset.joNo);
    setField('sendMissingBus', form.dataset.busNo);
    let parts = [];
    try { parts = JSON.parse(form.dataset.parts || '[]'); } catch (_) { parts = []; }
    const rows = document.getElementById('sendMissingPartsRows');
    if (rows) {
      rows.replaceChildren();
      parts.forEach(part => {
        const tr = document.createElement('tr');
        const name = document.createElement('td');
        name.textContent = String(part.name || '—').replace(/\s*\[item:\d+\]/gi, '').trim();
        const qty = document.createElement('td');
        qty.textContent = String(part.needed_display ?? (part.needed ?? 0) + ' ' + (part.unit || ''));
        const stock = document.createElement('td');
        stock.textContent = String(part.physical_on_hand_display ?? '0 ' + (part.unit || ''));
        tr.append(name, qty, stock);
        rows.appendChild(tr);
      });
      if (!parts.length) {
        const tr = document.createElement('tr'); const td = document.createElement('td');
        td.colSpan = 3; td.textContent = 'No requested parts found.'; tr.appendChild(td); rows.appendChild(tr);
      }
    }
    if (!parts.length) {
      pendingMissingForm = null;
      showPurchaseFeedback('empty', 'This purchase request has no recorded missing parts to send. Review stock and request quantities before trying again.');
      return;
    }
    sendMissingConfirm.disabled = false;
    openModal(sendMissingModal);
  }, true);
  sendMissingConfirm?.addEventListener('click', () => {
    if (!pendingMissingForm || sendMissingConfirm.disabled) return;
    const form = pendingMissingForm;
    pendingMissingForm = null;
    sendMissingConfirm.disabled = true;
    form.dataset.confirmedSend = '1';
    HTMLFormElement.prototype.submit.call(form);
  });
  sendMissingModal?.querySelectorAll('[data-send-missing-close]').forEach(button => {
    button.addEventListener('click', () => {
      pendingMissingForm = null;
      closeModal(sendMissingModal);
    });
  });

  const issuePartsModal = document.getElementById('issuePartsModal');
  const issuePartsForm = document.getElementById('issuePartsForm');
  const issuePartsFields = document.getElementById('issue_parts_fields');
  const closeIssuePartsModal = document.getElementById('closeIssuePartsModal');
  const cancelIssueParts = document.getElementById('cancelIssueParts');

  function renderIssueFields(parts) {
    if (!issuePartsFields) return;

    if (!Array.isArray(parts) || parts.length === 0) {
      issuePartsFields.innerHTML = '<div class="issue-empty-state">No requested parts found.</div>';
      return;
    }

    const cardsHtml = parts.map(function (part) {
      const requested = Number.parseInt(part.needed || part.quantity || 1, 10);
      const unit = escapeHtml(part.unit || 'pcs');
      const requestedText = escapeHtml(part.needed_display || `${requested} ${unit}`);
      const onHandText = escapeHtml(part.available_display || (part.available !== undefined ? `${part.available} ${unit}` : '0 pcs'));

      return `
        <div class="issue-part-item-card">
          <div class="issue-part-item-name">
            <strong>${escapeHtml(part.name || 'Part')}</strong>
          </div>
          <div class="issue-part-item-details">
            <div class="issue-detail-line">
              <span class="detail-label">Requested:</span>
              <strong class="detail-value text-blue">${requestedText}</strong>
            </div>
            <div class="issue-detail-line">
              <span class="detail-label">On Hand:</span>
              <strong class="detail-value text-green">${onHandText}</strong>
            </div>
          </div>
        </div>
      `;
    }).join('');

    const confirmMessage = parts.length === 1
      ? `Confirm release of ${escapeHtml(parts[0].needed_display || (parts[0].needed ? `${parts[0].needed} ${parts[0].unit || 'pcs'}` : '1 pcs'))}?`
      : 'Confirm release of all requested parts?';

    issuePartsFields.innerHTML = `
      <div class="issue-parts-list">
        ${cardsHtml}
      </div>
      <div class="issue-confirm-prompt">
        <p>${confirmMessage}</p>
      </div>
    `;
  }

  document.addEventListener('click', function (event) {
    const button = event.target.closest('.open-issue-modal');

    if (!button || !issuePartsForm) return;

    event.preventDefault();

    let parts = [];
    try {
      parts = JSON.parse(button.dataset.parts || '[]');
    } catch (error) {
      parts = [];
    }

    issuePartsForm.action = button.dataset.action || '';
    setField('issue_pr_no', button.dataset.prNo, 'Purchase Request');
    renderIssueFields(parts);
    openModal(issuePartsModal);
  });

  [closeIssuePartsModal, cancelIssueParts].forEach(function (button) {
    if (button) {
      button.addEventListener('click', function () {
        closeModal(issuePartsModal);
      });
    }
  });

  const warehouseStatusFilter = document.getElementById('warehouseStatusFilter');

  function updateWarehouseStatusFilterColor() {
    if (!warehouseStatusFilter) return;

    warehouseStatusFilter.classList.remove(
      'approved',
      'for-purchase',
      'ordered',
      'for-pick-up',
      'for-delivery',
      'delivered',
      'picked-up',
      'pending-warehouse-approval',
      'approved-for-issue',
      'preparing',
      'on-hold',
      'issued'
    );

    if (
      warehouseStatusFilter.value &&
      warehouseStatusFilter.value !== 'All Statuses'
    ) {
      warehouseStatusFilter.classList.add(slugStatus(warehouseStatusFilter.value));
    }
  }

  updateWarehouseStatusFilterColor();

  if (warehouseStatusFilter) {
    warehouseStatusFilter.addEventListener('change', updateWarehouseStatusFilterColor);
  }

  document.addEventListener('click', function (event) {
    const button = event.target.closest(
      'button, [data-close-modal], [data-close-feedback]'
    );

    if (!button) return;

    const buttonText = button.textContent.trim().toLowerCase();

    const isCloseButton =
      buttonText === 'okay' ||
      buttonText === 'ok' ||
      buttonText === 'close' ||
      button.classList.contains('feedback-ok-btn') ||
      button.classList.contains('success-ok-btn') ||
      button.classList.contains('error-ok-btn') ||
      button.classList.contains('btn-ok') ||
      button.hasAttribute('data-close-modal') ||
      button.hasAttribute('data-close-feedback');

    if (!isCloseButton) return;

    const modal =
      button.closest('.modal-overlay') ||
      button.closest('.delete-modal-overlay') ||
      button.closest('.success-modal-overlay') ||
      button.closest('.error-modal-overlay') ||
      button.closest('.feedback-modal-overlay') ||
      button.closest('.action-modal-overlay') ||
      button.closest('[class*="modal-overlay"]');

    closeModal(modal);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeAllModals();
    }
  });
});

/* Portal PR stock and item popovers out of the scrollable table so they overlap rows. */
window.GCTPartialNavigation.registerInitializer('warehouse-pr-stock-popover-portal', '.warehouse-part-main', () => {
  const root = document.querySelector('.warehouse-part-main');
  if (!root || root.dataset.prPopoverPortalReady === '1') return;
  root.dataset.prPopoverPortalReady = '1';
  let active = null;
  const close = () => {
    if (!active) return;
    const { panel, details, summary } = active;
    panel.removeAttribute('style');
    details.appendChild(panel);
    details.open = false;
    summary.setAttribute('aria-expanded', 'false');
    active = null;
  };
  const position = () => {
    if (!active) return;
    const { panel, summary } = active;
    if (!summary.isConnected) return close();
    const r = summary.getBoundingClientRect();
    const w = Math.min(310, window.innerWidth - 24);
    const h = Math.min(panel.scrollHeight || 200, window.innerHeight - 24);
    const above = window.innerHeight - r.bottom < h + 12 && r.top > h + 12;
    Object.assign(panel.style, {
      position: 'fixed', zIndex: '110500', width: w + 'px',
      minWidth: '0', maxWidth: 'calc(100vw - 24px)',
      left: Math.max(12, Math.min(r.left, window.innerWidth - w - 12)) + 'px',
      top: above ? 'auto' : (r.bottom + 6) + 'px',
      bottom: above ? (window.innerHeight - r.top + 6) + 'px' : 'auto',
      maxHeight: Math.max(90, Math.min(280, above ? r.top - 18 : window.innerHeight - r.bottom - 18)) + 'px'
    });
  };
  root.addEventListener('click', event => {
    const summary = event.target.closest('.warehouse-pr-parts-more > summary');
    if (!summary) return;
    const details = summary.parentElement;
    const panel = details.querySelector(':scope > .warehouse-pr-parts-popup');
    if (!panel) return;
    event.preventDefault();
    if (active?.details === details) return close();
    close();
    details.open = true;
    document.body.appendChild(panel);
    active = { panel, details, summary };
    summary.setAttribute('aria-expanded', 'true');
    position();
  });
  document.addEventListener('click', event => {
    if (active && !active.summary.contains(event.target) && !active.panel.contains(event.target)) close();
  });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
  window.addEventListener('resize', position);
  window.addEventListener('scroll', position, true);
});
