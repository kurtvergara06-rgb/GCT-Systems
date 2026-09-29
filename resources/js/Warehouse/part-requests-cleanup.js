document.addEventListener('DOMContentLoaded', function () {
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
