window.GCTPartialNavigation.registerInitializer('warehouse-part-requests', '.warehouse-part-main', function () {
  function openModal(modal) {
    if (!modal) return;

    modal.classList.add('show');
    modal.classList.add('active');
    modal.style.display = 'flex';

    if (window.GCTModalBackdrop?.sync) {
      window.GCTModalBackdrop.sync();
    }

    window.GCTSystemAnimations?.animateModalOpen?.(modal);
  }

  function closeModal(modal) {
    if (!modal) return;

    const finishClose = function () {
      modal.classList.remove('show');
      modal.classList.remove('active');
      modal.style.display = 'none';

      if (window.GCTModalBackdrop?.sync) {
        window.GCTModalBackdrop.sync();
      }
    };

    if (!window.GCTSystemAnimations?.animateModalClose?.(modal, finishClose)) {
      finishClose();
    }
  }

  const restockPrModal = document.getElementById('warehouseRestockPrModal');
  document.querySelector('[data-open-restock-pr]')?.addEventListener('click', function (event) {
    event.preventDefault();
    openModal(restockPrModal);
  });

  const restockPrItems = document.getElementById('restockPrItems');
  const restockPrTemplate = restockPrModal?.querySelector('.warehouse-restock-pr-template .warehouse-restock-pr-item');
  const addRestockItem = document.getElementById('restockPrAddItem');
  const restockPrForm = document.getElementById('warehouseRestockPrForm');

  function addRestockItemRow() {
    if (!restockPrItems || !restockPrTemplate) return;
    const row = restockPrTemplate.cloneNode(true);
    restockPrItems.appendChild(row);
    updateRestockRemovalButtons();
  }
  function updateRestockRemovalButtons() {
    restockPrItems?.querySelectorAll('.warehouse-restock-remove-item').forEach(function (button) {
      button.disabled = restockPrItems.children.length <= 1;
    });
  }
  addRestockItem?.addEventListener('click', addRestockItemRow);
  restockPrItems?.addEventListener('click', function (event) {
    if (!event.target.closest('.warehouse-restock-remove-item')) return;
    event.target.closest('.warehouse-restock-pr-item')?.remove();
    updateRestockRemovalButtons();
  });
  restockPrItems?.addEventListener('change', function (event) {
    const select = event.target.closest('.restock-pr-item-select');
    if (!select) return;
    const row = select.closest('.warehouse-restock-pr-item');
    const option = select.selectedOptions[0];
    const info = row?.querySelector('.warehouse-restock-stock-info');
    if (!info) return;
    info.textContent = option?.value
      ? 'On hand: ' + (option.dataset.stock || '0') + ' ' + (option.dataset.unit || '') +
        ' · Reorder point: ' + (option.dataset.rop || '0') +
        ' · Safety stock: ' + (option.dataset.safety || 'Not set') +
        (option.dataset.supplier ? ' · Supplier: ' + option.dataset.supplier : '')
      : 'Select an item to view its stock, reorder point and safety stock.';
  });
  if (restockPrItems && !restockPrItems.children.length) addRestockItemRow();
  restockPrForm?.addEventListener('submit', function (event) {
    // Disabled until restocking requests have a durable approval/PO workflow.
    event.preventDefault();
  });

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

  function renderPartsBreakdown(parts, containerId = 'view_parts_breakdown') {
    const container = document.getElementById(containerId);

    if (!container) return;

    if (!Array.isArray(parts) || parts.length === 0) {
      container.innerHTML = '<div class="shared-pr-empty">No parts found.</div>';
      return;
    }

    let html = `
      <div class="shared-pr-parts-table">
        <div class="shared-pr-parts-head">
          <span>Part Name</span>
          <span>Quantity</span>
          <span>On Hand</span>
        </div>
    `;

    parts.forEach(function (part) {
      html += `
        <div class="shared-pr-parts-row">
          <span class="part-name">${escapeHtml(String(part.name || '—').replace(/\s*\[item:\d+\]/gi, '').trim())}</span>
          <span>${escapeHtml(part.needed_display || part.needed || '0')}</span>
          <span>${escapeHtml(part.physical_on_hand_display || part.available_display || part.available || '0')}</span>
        </div>
      `;
    });

    html += '</div>';

    container.innerHTML = html;
  }

  const viewPrModal = document.getElementById('viewPrModal');
  const closeViewPrModal = document.getElementById('closeViewPrModal');
  const closeViewPrModalBottom = document.getElementById('closeViewPrModalBottom');

  document.addEventListener('click', function (event) {
    const button = event.target.closest('.open-view-pr-modal');

    if (!button) return;

    event.preventDefault();

    let parts = [];

    try {
      parts = JSON.parse(button.dataset.parts || '[]');
    } catch (error) {
      parts = [];
    }

    setField('view_pr_no', button.dataset.prNo);
    setField('view_job_order_no', button.dataset.jobOrderNo);
    setField('view_bus_no', button.dataset.busNo);
    setField('view_created', button.dataset.created);
    setField('view_requested_by', button.dataset.requestedBy);
    setField('view_processing', button.dataset.warehouseStatus || button.dataset.status);
    setField('view_remarks', button.dataset.remarks, 'No remarks');
    setField('view_pr_parts_count', Array.isArray(parts) ? `${parts.length} ${parts.length === 1 ? 'part' : 'parts'}` : '0 parts');
    setField('view_warehouse_status_badge', button.dataset.warehouseStatus || button.dataset.status);

    renderPartsBreakdown(parts);
    renderPartsBreakdown(parts, 'view_stock_reference');

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
