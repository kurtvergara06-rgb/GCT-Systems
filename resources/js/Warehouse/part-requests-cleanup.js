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

  function normalizeText(value) {
    return String(value || '').trim().toLowerCase();
  }

  function applyWarehouseDisplayStates() {
    document.querySelectorAll('.warehouse-part-table-clean tbody tr').forEach(function (row) {
      const cells = row.querySelectorAll('td');
      if (cells.length < 8) return;

      const purchaseStatus = normalizeText(cells[5]?.textContent);
      const warehousePill = cells[6]?.querySelector('.warehouse-status-pill');
      if (!warehousePill) return;

      const warehouseStatus = normalizeText(warehousePill.textContent);
      if (warehouseStatus !== 'pending approval') return;

      let label = null;
      let className = null;

      if (purchaseStatus === 'for purchase') {
        label = 'Waiting for Purchase';
        className = 'waiting-purchase';
      } else if (purchaseStatus === 'ordered' || purchaseStatus === 'for pick-up' || purchaseStatus === 'for delivery') {
        label = 'Waiting for Delivery';
        className = 'waiting-delivery';
      }

      if (!label) return;

      warehousePill.textContent = label;
      warehousePill.title = label;
      warehousePill.classList.remove('pending');
      warehousePill.classList.add(className);

      const viewButton = row.querySelector('.open-view-pr-modal');
      if (viewButton) {
        viewButton.dataset.warehouseStatus = label;
      }
    });
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

  applyWarehouseDisplayStates();

  document.addEventListener('click', function (event) {
    const button = event.target.closest('.open-view-pr-modal');
    if (!button) return;

    setField('view_purchase_status', button.dataset.status);
    setField('view_warehouse_status', button.dataset.warehouseStatus);
    setField('view_approved_by', button.dataset.approvedBy);
    setField('view_approved_at', button.dataset.approvedAt);
    setField('view_prepared_by', button.dataset.preparedBy);
    setField('view_prepared_at', button.dataset.preparedAt);

    let issuedQuantities = [];
    try {
      issuedQuantities = JSON.parse(button.dataset.issuedQuantities || '[]');
    } catch (error) {
      issuedQuantities = [];
    }

    renderIssuedQuantities(issuedQuantities);
  });
});
