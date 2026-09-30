document.addEventListener('DOMContentLoaded', function () {
  function openModal(modal) {
    if (!modal) {
      return;
    }

    modal.classList.add('show');
    modal.classList.add('active');
    modal.style.display = 'flex';
  }

  function closeModal(modal) {
    if (!modal) {
      return;
    }

    modal.classList.remove('show');
    modal.classList.remove('active');
    modal.style.display = 'none';
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
    row.innerHTML = '<td colspan="11">No inventory items match the current filters.</td>';
    body.appendChild(row);
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
      const categoryText = String(cells[2]?.textContent || '').trim().toLowerCase();
      const searchableText = [
        cells[0]?.textContent,
        cells[1]?.textContent,
        cells[7]?.textContent,
        cells[8]?.textContent,
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

  if (inventoryToolbar) {
    inventoryToolbar.dataset.clientFilter = 'true';

    if (searchInput) {
      searchInput.dataset.autoSearchBound = 'true';
      searchInput.addEventListener('input', applyInventoryFilters);
      searchInput.addEventListener('search', applyInventoryFilters);
      searchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          searchInput.value = '';
          applyInventoryFilters();
        }
      });
    }

    if (categorySelect) {
      categorySelect.removeAttribute('onchange');
      categorySelect.addEventListener('change', applyInventoryFilters);
      categorySelect.addEventListener('input', applyInventoryFilters);
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

    openModal(editModal);
  });

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
      closeModal(modal);
    });
  });

  /*
  |--------------------------------------------------------------------------
  | CLOSE WHEN CLICKING OUTSIDE MODAL
  |--------------------------------------------------------------------------
  */

  document.querySelectorAll('.modal-overlay').forEach(function (modal) {
    modal.addEventListener('click', function (event) {
      if (event.target === modal) {
        closeModal(modal);
      }
    });
  });

  /*
  |--------------------------------------------------------------------------
  | CLOSE USING ESCAPE KEY
  |--------------------------------------------------------------------------
  */

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeAllModals();
    }
  });
});
