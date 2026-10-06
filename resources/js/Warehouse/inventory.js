window.GCTPartialNavigation.registerInitializer('warehouse-inventory', '.warehouse-inventory-page', function () {
  function openModal(modal) {
    if (!modal) {
      return;
    }

    modal.classList.add('show');
    modal.classList.add('active');
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');

    if (window.GCTModalBackdrop?.sync) {
      window.GCTModalBackdrop.sync();
    }
  }

  function closeModal(modal) {
    if (!modal) {
      return;
    }

    modal.classList.remove('show');
    modal.classList.remove('active');
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');

    if (window.GCTModalBackdrop?.sync) {
      window.GCTModalBackdrop.sync();
    }
  }

  function closeAllModals() {
    document.querySelectorAll('.modal-overlay').forEach(function (modal) {
      closeModal(modal);
    });
  }

  function setInputValue(id, value) {
    const input = document.getElementById(id);

    if (!input) {
      return;
    }

    input.value =
      value === undefined ||
      value === null ||
      value === 'null'
        ? ''
        : value;
  }

  /*
  |--------------------------------------------------------------------------
  | CLIENT-SIDE INVENTORY TOOLBAR
  | Search and category filters work against the rows already loaded by the
  | shared scroll-table component, without refreshing the page.
  |--------------------------------------------------------------------------
  */

  const inventoryToolbar = document.querySelector('.inventory-toolbar');
  const inventoryTable = document.querySelector('.inventory-table');
  const inventoryFooter = document.querySelector('.inventory-card [data-scroll-pagination]');
  const searchInput = inventoryToolbar?.querySelector('input[name="search"]');
  const categorySelect = inventoryToolbar?.querySelector('select[name="category"]');

  function inventoryRows() {
    if (!inventoryTable?.tBodies?.[0]) {
      return [];
    }

    return Array.from(inventoryTable.tBodies[0].rows).filter(function (row) {
      return !row.classList.contains('empty-row')
        && !row.classList.contains('inventory-client-empty');
    });
  }

  function updateInventoryEntryCount(visibleCount) {
    const label = inventoryFooter?.querySelector('[data-entry-count]');

    if (!label) {
      return;
    }

    label.textContent = visibleCount
      ? `Showing 1 to ${visibleCount} of ${visibleCount} entries`
      : 'Showing 0 to 0 of 0 entries';
  }

  function removeClientEmptyRow() {
    inventoryTable?.querySelector('.inventory-client-empty')?.remove();
  }

  function showClientEmptyRow() {
    const body = inventoryTable?.tBodies?.[0];

    if (!body || body.querySelector('.inventory-client-empty')) {
      return;
    }

    const row = document.createElement('tr');
    row.className = 'empty-row inventory-client-empty';
    row.innerHTML = '<td colspan="8">No inventory items match the current filters.</td>';
    body.appendChild(row);
  }

  function requestAllInventoryRows() {
    if (
      inventoryFooter?.dataset.lazyPagination === 'true'
      && inventoryFooter.dataset.hasMore === 'true'
    ) {
      inventoryFooter.dispatchEvent(new CustomEvent('gct:load-all-records'));
    }
  }

  function applyInventoryFilters() {
    if (!inventoryTable) {
      return;
    }

    removeClientEmptyRow();

    const search = String(searchInput?.value || '').trim().toLowerCase();
    const category = String(categorySelect?.value || 'All Categories').trim().toLowerCase();
    let visibleCount = 0;

    inventoryRows().forEach(function (row) {
      const cells = row.cells;
      const categoryText = String(cells[1]?.textContent || '').trim().toLowerCase();
      const searchableText = [
        cells[0]?.textContent,
        cells[1]?.textContent,
        cells[5]?.textContent,
        cells[6]?.textContent,
      ].join(' ').toLowerCase();

      const matchesSearch = !search || searchableText.includes(search);
      const matchesCategory = category === 'all categories' || categoryText === category;
      const visible = matchesSearch && matchesCategory;

      // Use an explicit inline display value instead of the `hidden` attribute.
      // Some table styles can override the browser's [hidden] display rule,
      // making filtered rows look unchanged even though `row.hidden` is true.
      row.style.display = visible ? '' : 'none';
      row.setAttribute('aria-hidden', visible ? 'false' : 'true');

      if (visible) {
        visibleCount += 1;
      }
    });

    if (visibleCount === 0) {
      showClientEmptyRow();
    }

    updateInventoryEntryCount(visibleCount);
  }

  if (inventoryToolbar && inventoryToolbar.dataset.serverFilter !== 'true') {
    inventoryToolbar.dataset.clientFilter = 'true';

    if (searchInput) {
      searchInput.dataset.autoSearchBound = 'true';
      searchInput.addEventListener('input', function () {
        requestAllInventoryRows();
        applyInventoryFilters();
      });
      searchInput.addEventListener('search', function () {
        requestAllInventoryRows();
        applyInventoryFilters();
      });
      searchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          searchInput.value = '';
          requestAllInventoryRows();
          applyInventoryFilters();
        }
      });
    }

    if (categorySelect) {
      categorySelect.removeAttribute('onchange');
      categorySelect.addEventListener('change', function () {
        requestAllInventoryRows();
        applyInventoryFilters();
      });
      categorySelect.addEventListener('input', function () {
        requestAllInventoryRows();
        applyInventoryFilters();
      });
    }

    applyInventoryFilters();
  }

  document.addEventListener('system:table-rows-loaded', function (event) {
    if (event.detail?.table === inventoryTable) {
      window.setTimeout(applyInventoryFilters, 0);
    }
  });

  /*
  |--------------------------------------------------------------------------
  | ITEM MOVEMENT HISTORY MODAL
  |--------------------------------------------------------------------------
  | Movement history stays inside Inventory. The route is used only as an
  | HTML data endpoint for the modal; filter and pagination requests are
  | fetched into the same modal without navigating away from the page.
  |--------------------------------------------------------------------------
  */

  const movementHistoryModal = document.getElementById('movementHistoryModal');
  const movementHistoryContent = document.getElementById('movementHistoryContent');
  const movementHistoryTitle = document.getElementById('movementHistoryModalTitle');
  let movementHistoryRequest = null;
  let movementHistoryUrl = null;

  function movementModalUrl(url) {
    const parsed = new URL(url, window.location.origin);
    parsed.searchParams.set('modal', '1');

    return parsed.toString();
  }

  function showMovementHistoryLoading() {
    if (!movementHistoryContent) {
      return;
    }

    movementHistoryContent.innerHTML = `
      <div class="movement-history-loading" role="status">
        <i class="fa-solid fa-spinner fa-spin"></i>
        <span>Loading movement history...</span>
      </div>
    `;
  }

  function showMovementHistoryError(url) {
    if (!movementHistoryContent) {
      return;
    }

    movementHistoryContent.innerHTML = `
      <div class="movement-history-error">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div>
          <strong>Unable to load movement history.</strong>
          <button type="button" class="movement-history-retry" data-movement-retry-url="${String(url || '')}">
            Try Again
          </button>
        </div>
      </div>
    `;
  }

  async function loadMovementHistory(url, options = {}) {
    if (!movementHistoryModal || !movementHistoryContent || !url) {
      return;
    }

    movementHistoryRequest?.abort();
    movementHistoryRequest = new AbortController();
    movementHistoryUrl = movementModalUrl(url);

    if (options.open !== false) {
      openModal(movementHistoryModal);
    }

    if (options.loading !== false) {
      showMovementHistoryLoading();
    }

    try {
      const response = await fetch(movementHistoryUrl, {
        method: 'GET',
        headers: {
          Accept: 'text/html',
          'X-Requested-With': 'XMLHttpRequest',
        },
        signal: movementHistoryRequest.signal,
        credentials: 'same-origin',
      });

      if (!response.ok) {
        throw new Error(`Movement history request failed with status ${response.status}.`);
      }

      movementHistoryContent.innerHTML = await response.text();

      document.dispatchEvent(new CustomEvent('ajax:content-updated', {
        detail: {
          container: movementHistoryContent,
          source: 'inventory-movement-history',
        },
      }));

      const itemName = movementHistoryContent
        .querySelector('.item-history-header h2')
        ?.textContent
        ?.trim();

      if (movementHistoryTitle) {
        movementHistoryTitle.textContent = itemName
          ? `Movement History — ${itemName}`
          : 'Movement History';
      }

      movementHistoryContent.scrollTop = 0;
    } catch (error) {
      if (error?.name === 'AbortError') {
        return;
      }

      console.error(error);
      showMovementHistoryError(movementHistoryUrl);
    }
  }

  document.addEventListener('click', function (event) {
    const historyButton = event.target.closest('.openMovementHistory');

    if (historyButton) {
      event.preventDefault();
      event.stopPropagation();

      loadMovementHistory(historyButton.dataset.url);
      return;
    }

    if (!movementHistoryModal?.classList.contains('active')) {
      return;
    }

    const retryButton = event.target.closest('[data-movement-retry-url]');
    if (retryButton) {
      event.preventDefault();
      loadMovementHistory(retryButton.dataset.movementRetryUrl, {
        open: false,
      });
      return;
    }

    const pageLink = event.target.closest(
      '#movementHistoryContent .pagination a, #movementHistoryContent a.page-link'
    );

    if (pageLink) {
      event.preventDefault();
      loadMovementHistory(pageLink.href, {
        open: false,
      });
    }
  });

  document.addEventListener('submit', function (event) {
    const form = event.target.closest('[data-movement-history-filter]');

    if (!form || !movementHistoryModal?.classList.contains('active')) {
      return;
    }

    event.preventDefault();

    const url = new URL(form.action, window.location.origin);
    const data = new FormData(form);

    data.forEach(function (value, key) {
      if (String(value).trim() !== '') {
        url.searchParams.set(key, value);
      }
    });

    loadMovementHistory(url.toString(), {
      open: false,
    });
  });

  document.addEventListener('change', function (event) {
    const filter = event.target.closest(
      '#movementHistoryContent [data-movement-history-filter] select'
    );

    if (!filter) {
      return;
    }

    filter.form?.requestSubmit();
  });

  const initialMovementItem = new URLSearchParams(window.location.search)
    .get('movement_item');

  if (initialMovementItem) {
    const matchingButton = document.querySelector(
      `.openMovementHistory[data-item-id="${CSS.escape(initialMovementItem)}"]`
    );

    matchingButton?.click();
  }


  /*
  |--------------------------------------------------------------------------
  | ADD ITEM MODAL
  |--------------------------------------------------------------------------
  */

  const addModal = document.getElementById('addModal');
  const openAddModalButton = document.getElementById('openAddModal');

  if (openAddModalButton && addModal) {
    openAddModalButton.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();

      openModal(addModal);
    });
  }

  /*
  |--------------------------------------------------------------------------
  | IMPORT MODAL
  |--------------------------------------------------------------------------
  */

  const importModal = document.getElementById('importModal');
  const openImportModalButton = document.getElementById('openImportModal');

  if (openImportModalButton && importModal) {
    openImportModalButton.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();

      openModal(importModal);
    });
  }

  /*
  |--------------------------------------------------------------------------
  | EDIT ITEM MODAL
  |--------------------------------------------------------------------------
  */

  const editModal = document.getElementById('editModal');
  const editForm = document.getElementById('editForm');

  document.addEventListener('click', function (event) {
    const editButton = event.target.closest('.openEditModal');

    if (!editButton) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    if (!editModal || !editForm) {
      console.error('Edit modal or edit form was not found.');
      return;
    }

    const updateUrl = editButton.dataset.action;

    if (!updateUrl) {
      console.error('Inventory update URL was not found.');
      return;
    }

    editForm.setAttribute('action', updateUrl);

    setInputValue('edit_item_code', editButton.dataset.code);
    setInputValue('edit_item_name', editButton.dataset.name);
    setInputValue('edit_category', editButton.dataset.category);
    setInputValue('edit_quantity', editButton.dataset.quantity);
    setInputValue('edit_unit', editButton.dataset.unit);
    setInputValue('edit_reorder', editButton.dataset.reorder);
    setInputValue('edit_supplier', editButton.dataset.supplier);
    setInputValue('edit_location', editButton.dataset.location);
    setInputValue('edit_adjustment_reason', '');

    editForm.dataset.originalQuantity = String(editButton.dataset.quantity ?? '');
    updateAdjustmentReasonRequirement();

    openModal(editModal);
  });

  function updateAdjustmentReasonRequirement() {
    if (!editForm) {
      return;
    }

    const quantityInput = document.getElementById('edit_quantity');
    const reasonInput = document.getElementById('edit_adjustment_reason');
    const requiredMark = document.getElementById('editAdjustmentRequiredMark');
    const helpText = document.getElementById('editAdjustmentHelp');

    if (!quantityInput || !reasonInput) {
      return;
    }

    const originalQuantity = String(editForm.dataset.originalQuantity ?? '');
    const currentQuantity = String(quantityInput.value ?? '');
    const quantityChanged = originalQuantity !== '' && currentQuantity !== originalQuantity;

    reasonInput.required = quantityChanged;

    if (requiredMark) {
      requiredMark.hidden = !quantityChanged;
    }

    if (helpText) {
      helpText.textContent = quantityChanged
        ? 'Required because Quantity Available has been changed.'
        : 'No reason is required when stock quantity remains unchanged.';
    }
  }

  document.getElementById('edit_quantity')?.addEventListener('input', updateAdjustmentReasonRequirement);

  /*
  |--------------------------------------------------------------------------
  | PREVENT EDIT FORM FROM USING GET
  |--------------------------------------------------------------------------
  */

  if (editForm) {
    editForm.addEventListener('submit', function (event) {
      const action = editForm.getAttribute('action');

      if (!action || action === '#') {
        event.preventDefault();
        console.error('The edit form action is missing.');
      }
    });
  }

  /*
  |--------------------------------------------------------------------------
  | ISSUE STOCK MODAL
  |--------------------------------------------------------------------------
  */

  const issueModal = document.getElementById('issueModal');
  const issueForm = document.getElementById('issueForm');
  const issueSelect = document.getElementById('issue_item');
  const issueSubmitBtn = document.getElementById('issueSubmitBtn');
  const openIssueModalButton = document.getElementById('openIssueModal');

  function updateIssuePreview() {
    if (!issueSelect) {
      return;
    }

    const selected = issueSelect.selectedOptions?.[0];
    const nameLabel = document.getElementById('issue_preview_name');
    const quantityLabel = document.getElementById('issue_preview_quantity');
    const unitLabel = document.getElementById('issue_preview_unit');

    if (!selected || !selected.value) {
      if (nameLabel) {
        nameLabel.textContent = 'Select an item...';
      }
      if (quantityLabel) {
        quantityLabel.textContent = '—';
      }
      if (unitLabel) {
        unitLabel.textContent = '—';
      }

      return;
    }

    if (nameLabel) {
      nameLabel.textContent = selected.textContent;
    }
    if (quantityLabel) {
      quantityLabel.textContent = selected.dataset.quantity ?? '—';
    }
    if (unitLabel) {
      unitLabel.textContent = selected.dataset.unit ?? '—';
    }
  }

  function openIssueModalFor(itemId) {
    if (!issueModal || !issueSelect) {
      return;
    }

    if (itemId) {
      const match = Array.from(issueSelect.options).find(function (option) {
        return option.value === String(itemId);
      });

      if (match) {
        issueSelect.value = match.value;
      }
    }

    updateIssuePreview();
    openModal(issueModal);
  }

  if (openIssueModalButton && issueModal) {
    openIssueModalButton.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();

      openIssueModalFor(null);
    });
  }

  if (issueSelect) {
    issueSelect.addEventListener('change', updateIssuePreview);
  }

  document.addEventListener('click', function (event) {
    const issueButton = event.target.closest('.openIssueModal');

    if (!issueButton) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    openIssueModalFor(issueButton.dataset.itemId);
  });

  if (issueForm && issueSubmitBtn) {
    issueForm.addEventListener('submit', function () {
      // Prevent duplicate submission on double-click / repeated enter.
      issueSubmitBtn.disabled = true;
      issueSubmitBtn.dataset.processing = 'true';
    });
  }

  /*
  |--------------------------------------------------------------------------
  | CLOSE BUTTONS
  |--------------------------------------------------------------------------
  */

  document.querySelectorAll('.closeModal').forEach(function (button) {
    button.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();

      const modal = button.closest('.modal-overlay');

      if (modal === movementHistoryModal) {
        movementHistoryRequest?.abort();
      }

      closeModal(modal);
    });
  });

  /*
  |--------------------------------------------------------------------------
  | CLOSE USING ESCAPE KEY
  |--------------------------------------------------------------------------
  */

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      movementHistoryRequest?.abort();
      closeAllModals();
    }
  });

  window.addEventListener('gct:navigation-before', function () {
    movementHistoryRequest?.abort();
  });
});
