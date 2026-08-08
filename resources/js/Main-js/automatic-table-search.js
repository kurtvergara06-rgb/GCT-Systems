document.addEventListener('DOMContentLoaded', () => {
  const findTableContext = (toolbar) => {
    let node = toolbar?.parentElement;

    while (node && node !== document.body) {
      const table = node.querySelector('.table-wrap table, table');
      const footer = node.querySelector('[data-scroll-pagination], .table-footer');

      if (table) {
        return { table, footer };
      }

      node = node.parentElement;
    }

    return null;
  };

  const searchableRows = (table) => {
    if (!table?.tBodies?.[0]) {
      return [];
    }

    return Array.from(table.tBodies[0].rows).filter((row) => {
      return !row.classList.contains('empty-row')
        && !row.classList.contains('gct-search-empty-row')
        && !row.classList.contains('inventory-client-empty');
    });
  };

  const removeEmptyRow = (table) => {
    table?.querySelector('.gct-search-empty-row')?.remove();
  };

  const showEmptyRow = (table) => {
    const body = table?.tBodies?.[0];

    if (!body || body.querySelector('.gct-search-empty-row')) {
      return;
    }

    const columnCount = Math.max(1, table.tHead?.rows?.[0]?.cells?.length || 1);
    const row = document.createElement('tr');
    row.className = 'empty-row gct-search-empty-row';
    row.innerHTML = `<td colspan="${columnCount}">No matching records found.</td>`;
    body.appendChild(row);
  };

  const updateCount = (footer, visibleCount, totalCount) => {
    const label = footer?.querySelector?.('[data-entry-count]')
      || footer?.querySelector?.('p');

    if (!label) {
      return;
    }

    label.textContent = visibleCount
      ? `Showing 1 to ${visibleCount} of ${totalCount} entries`
      : `Showing 0 to 0 of ${totalCount} entries`;
  };

  const activeFilterValues = (toolbar) => {
    return Array.from(toolbar.querySelectorAll('select')).map((select) => {
      const value = String(select.value || '').trim().toLowerCase();

      if (!value || value.startsWith('all ')) {
        return '';
      }

      return value;
    }).filter(Boolean);
  };

  const applyToolbarFilters = (toolbar) => {
    if (!toolbar || toolbar.classList.contains('inventory-toolbar')) {
      return;
    }

    const input = toolbar.querySelector('.search-box input[type="text"], .search-box input[type="search"]');
    const context = findTableContext(toolbar);

    if (!context?.table) {
      return;
    }

    removeEmptyRow(context.table);

    const query = String(input?.value || '').trim().toLowerCase();
    const filters = activeFilterValues(toolbar);
    const rows = searchableRows(context.table);
    let visibleCount = 0;

    rows.forEach((row) => {
      const rowText = String(row.textContent || '').toLowerCase();
      const matchesSearch = !query || rowText.includes(query);
      const matchesFilters = filters.every((filterValue) => rowText.includes(filterValue));
      const visible = matchesSearch && matchesFilters;

      row.style.display = visible ? '' : 'none';

      if (visible) {
        visibleCount += 1;
      }
    });

    if (visibleCount === 0 && rows.length > 0) {
      showEmptyRow(context.table);
    }

    updateCount(context.footer, visibleCount, rows.length);
  };

  const prepareToolbar = (toolbar) => {
    if (!toolbar || toolbar.classList.contains('inventory-toolbar')) {
      return;
    }

    toolbar.dataset.instantSearch = 'true';

    toolbar.querySelectorAll('select[onchange]').forEach((select) => {
      select.removeAttribute('onchange');
    });

    const input = toolbar.querySelector('.search-box input[type="text"], .search-box input[type="search"]');
    if (input) {
      input.dataset.autoSearchBound = 'true';
      input.setAttribute('autocomplete', 'off');
    }

    applyToolbarFilters(toolbar);
  };

  document.querySelectorAll('.toolbar').forEach(prepareToolbar);

  document.addEventListener('input', (event) => {
    const input = event.target.closest?.('.toolbar .search-box input[type="text"], .toolbar .search-box input[type="search"]');

    if (!input) {
      return;
    }

    const toolbar = input.closest('.toolbar');
    applyToolbarFilters(toolbar);
  }, true);

  document.addEventListener('change', (event) => {
    const select = event.target.closest?.('.toolbar select');

    if (!select) {
      return;
    }

    const toolbar = select.closest('.toolbar');
    applyToolbarFilters(toolbar);
  }, true);

  document.addEventListener('keydown', (event) => {
    const input = event.target.closest?.('.toolbar .search-box input[type="text"], .toolbar .search-box input[type="search"]');

    if (!input) {
      return;
    }

    if (event.key === 'Enter') {
      event.preventDefault();
      applyToolbarFilters(input.closest('.toolbar'));
    }

    if (event.key === 'Escape') {
      event.preventDefault();
      input.value = '';
      applyToolbarFilters(input.closest('.toolbar'));
    }
  }, true);

  document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.classList.contains('toolbar')) {
      return;
    }

    if (form.classList.contains('inventory-toolbar')) {
      return;
    }

    event.preventDefault();
    applyToolbarFilters(form);
  }, true);

  document.addEventListener('system:table-rows-loaded', (event) => {
    document.querySelectorAll('.toolbar').forEach((toolbar) => {
      if (toolbar.classList.contains('inventory-toolbar')) {
        return;
      }

      const context = findTableContext(toolbar);

      if (context?.table === event.detail?.table) {
        window.setTimeout(() => applyToolbarFilters(toolbar), 0);
      }
    });
  });
});
