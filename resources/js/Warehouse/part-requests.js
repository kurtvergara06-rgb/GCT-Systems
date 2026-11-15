document.addEventListener('DOMContentLoaded', function () {
  /*
  |--------------------------------------------------------------------------
  | Helpers
  |--------------------------------------------------------------------------
  */

  function openModal(modal) {
    if (!modal) return;

    modal.classList.add('show');
    modal.classList.add('active');
    modal.style.display = 'flex';
  }

  function closeModal(modal) {
    if (!modal) return;

    modal.classList.remove('show');
    modal.classList.remove('active');
    modal.style.display = 'none';
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
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;');
  }

  function slugStatus(value) {
    return String(value || '')
      .toLowerCase()
      .replace(/\//g, '-')
      .replace(/\s+/g, '-');
  }

  function renderPartsBreakdown(parts) {
    const container = document.getElementById('view_parts_breakdown');

    if (!container) return;

    if (!Array.isArray(parts) || parts.length === 0) {
      container.innerHTML = '<div class="parts-breakdown-empty">No parts found.</div>';
      return;
    }

    let html = `
      <div class="parts-breakdown-table">
        <div class="parts-breakdown-head">
          <span>Part Name</span>
          <span>Quantity</span>
          <span>On Hand</span>
          <span>Status</span>
        </div>
    `;

    parts.forEach(function (part) {
      const status = part.status || 'Not Available';
      const statusClass = status === 'Available' ? 'available' : 'not-available';

      html += `
        <div class="parts-breakdown-row">
          <span class="part-name">${escapeHtml(part.name || '—')}</span>
          <span>${escapeHtml(part.needed_display || part.needed || '0')}</span>
          <span>${escapeHtml(part.available_display || part.available || '0')}</span>
          <span>
            <b class="mini-inventory-badge ${statusClass}">
              ${escapeHtml(status)}
            </b>
          </span>
        </div>
      `;
    });

    html += '</div>';

    container.innerHTML = html;
  }

  /*
  |--------------------------------------------------------------------------
  | Sidebar Dropdown
  |--------------------------------------------------------------------------
  */

  document.querySelectorAll('.dropdown-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
      const dropdown = button.closest('.menu-dropdown');

      if (dropdown) {
        dropdown.classList.toggle('open');
      }
    });
  });

  /*
  |--------------------------------------------------------------------------
  | View Purchase Request Modal
  |--------------------------------------------------------------------------
  */

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
    setField('view_inventory_status', button.dataset.inventoryStatus);
    setField('view_status', button.dataset.status);
    setField('view_created', button.dataset.created);
    setField('view_remarks', button.dataset.remarks, 'No remarks');

    renderPartsBreakdown(parts);

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

  if (viewPrModal) {
    viewPrModal.addEventListener('click', function (event) {
      if (event.target === viewPrModal) {
        closeModal(viewPrModal);
      }
    });
  }

  /*
  |--------------------------------------------------------------------------
  | Warehouse Status Filter Color
  |--------------------------------------------------------------------------
  */

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

  /*
  |--------------------------------------------------------------------------
  | Success / Error / Feedback Modal Okay Button
  |--------------------------------------------------------------------------
  */

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

  /*
  |--------------------------------------------------------------------------
  | Escape Key Close
  |--------------------------------------------------------------------------
  */

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeAllModals();
    }
  });
});