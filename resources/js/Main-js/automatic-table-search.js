window.GCTPartialNavigation.registerInitializer('shared-table-search', 'main', () => {
  const toolbarSelector = [
    '.toolbar',
    '.requested-toolbar',
    '.restock-toolbar',
    '.po-toolbar',
    '.schedule-toolbar',
  ].join(', ');

  const searchInputSelector = '.search-box input[type="text"], .search-box input[type="search"]';

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

  const usesOwnClientFilter = (toolbar) => {
    return toolbar?.dataset?.clientFilter !== undefined;
  };

  const usesServerFilter = (toolbar) => {
    return toolbar?.dataset?.serverFilter === 'true';
  };

  const ownsServerFilter = (toolbar) => {
    return toolbar?.dataset?.serverFilterOwned === 'true';
  };

  const serverFilterTimers = new WeakMap();

  const closestToolbar = (element) => element?.closest?.(toolbarSelector) || null;

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

  const requestRemainingLazyRows = (toolbar) => {
    const context = findTableContext(toolbar);
    const footer = context?.footer;

    if (
      footer?.dataset?.lazyPagination === 'true'
      && footer.dataset.hasMore === 'true'
    ) {
      footer.dispatchEvent(new CustomEvent('gct:load-all-records'));
    }
  };

  const hasActiveClientCriteria = (toolbar) => {
    const input = toolbar?.querySelector(searchInputSelector);
    const query = String(input?.value || '').trim();

    return query !== '' || activeFilterValues(toolbar).length > 0;
  };

  const buildServerFilterUrl = (toolbar) => {
    if (!(toolbar instanceof HTMLFormElement)) {
      return null;
    }

    const method = String(toolbar.getAttribute('method') || 'GET').toUpperCase();
    if (method !== 'GET') {
      return null;
    }

    const target = new URL(
      toolbar.getAttribute('action') || window.location.href,
      window.location.href
    );
    const params = new URLSearchParams();

    new FormData(toolbar).forEach((value, key) => {
      if (typeof value === 'string') {
        params.append(key, value);
      }
    });

    params.delete('page');
    params.delete('history_page');
    target.search = params.toString();

    return target;
  };

  const runServerFilter = (toolbar) => {
    const target = buildServerFilterUrl(toolbar);
    if (!target) {
      return;
    }

    if (window.GCTPartialNavigation?.navigate) {
      window.GCTPartialNavigation.navigate(target.href);
      return;
    }

    window.location.assign(target.href);
  };

  const scheduleServerFilter = (toolbar, delay = 300) => {
    const previousTimer = serverFilterTimers.get(toolbar);
    if (previousTimer) {
      window.clearTimeout(previousTimer);
    }

    const timer = window.setTimeout(() => {
      serverFilterTimers.delete(toolbar);
      runServerFilter(toolbar);
    }, delay);

    serverFilterTimers.set(toolbar, timer);
  };

  const applyToolbarFilters = (toolbar) => {
    if (!toolbar || usesOwnClientFilter(toolbar) || usesServerFilter(toolbar)) {
      return;
    }

    const input = toolbar.querySelector(searchInputSelector);
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
    if (!toolbar || usesOwnClientFilter(toolbar)) {
      return;
    }

    const input = toolbar.querySelector(searchInputSelector);

    if (usesServerFilter(toolbar)) {
      toolbar.dataset.instantSearch = 'server';

      if (!ownsServerFilter(toolbar)) {
        toolbar.querySelectorAll('select[onchange]').forEach((select) => {
          select.removeAttribute('onchange');
        });
      }

      if (input) {
        input.dataset.autoSearchBound = 'true';
        input.setAttribute('autocomplete', 'off');
      }

      return;
    }

    toolbar.dataset.instantSearch = 'true';

    toolbar.querySelectorAll('select[onchange]').forEach((select) => {
      select.removeAttribute('onchange');
    });

    if (input) {
      input.dataset.autoSearchBound = 'true';
      input.setAttribute('autocomplete', 'off');
    }

    applyToolbarFilters(toolbar);
  };

  const prepareAllToolbars = () => document.querySelectorAll(toolbarSelector).forEach(prepareToolbar);

  prepareAllToolbars();

  document.addEventListener('ajax:content-updated', prepareAllToolbars);

  document.addEventListener('input', (event) => {
    const input = event.target.closest?.(searchInputSelector);
    const toolbar = closestToolbar(input);

    if (!input || !toolbar) {
      return;
    }

    if (usesServerFilter(toolbar)) {
      if (!ownsServerFilter(toolbar)) {
        scheduleServerFilter(toolbar);
      }
      return;
    }

    if (hasActiveClientCriteria(toolbar)) {
      requestRemainingLazyRows(toolbar);
    }

    applyToolbarFilters(toolbar);
  }, true);

  document.addEventListener('change', (event) => {
    const select = event.target.closest?.('select');
    const toolbar = closestToolbar(select);

    if (!select || !toolbar) {
      return;
    }

    if (usesServerFilter(toolbar)) {
      if (!ownsServerFilter(toolbar)) {
        runServerFilter(toolbar);
      }
      return;
    }

    if (hasActiveClientCriteria(toolbar)) {
      requestRemainingLazyRows(toolbar);
    }

    applyToolbarFilters(toolbar);
  }, true);

  document.addEventListener('keydown', (event) => {
    const input = event.target.closest?.(searchInputSelector);
    const toolbar = closestToolbar(input);

    if (!input || !toolbar) {
      return;
    }

    if (usesServerFilter(toolbar)) {
      if (ownsServerFilter(toolbar)) {
        return;
      }

      if (event.key === 'Enter') {
        event.preventDefault();
        runServerFilter(toolbar);
      }

      if (event.key === 'Escape') {
        event.preventDefault();
        input.value = '';
        runServerFilter(toolbar);
      }

      return;
    }

    if (event.key === 'Enter') {
      event.preventDefault();

      if (hasActiveClientCriteria(toolbar)) {
        requestRemainingLazyRows(toolbar);
      }

      applyToolbarFilters(toolbar);
    }

    if (event.key === 'Escape') {
      event.preventDefault();
      input.value = '';

      if (hasActiveClientCriteria(toolbar)) {
        requestRemainingLazyRows(toolbar);
      }

      applyToolbarFilters(toolbar);
    }
  }, true);

  document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.matches(toolbarSelector)) {
      return;
    }

    if (usesOwnClientFilter(form)) {
      return;
    }

    if (usesServerFilter(form)) {
      if (ownsServerFilter(form)) {
        return;
      }

      event.preventDefault();
      runServerFilter(form);
      return;
    }

    event.preventDefault();

    if (hasActiveClientCriteria(form)) {
      requestRemainingLazyRows(form);
    }

    applyToolbarFilters(form);
  }, true);

  document.addEventListener('system:table-rows-loaded', (event) => {
    document.querySelectorAll(toolbarSelector).forEach((toolbar) => {
      if (usesOwnClientFilter(toolbar)) {
        return;
      }

      const context = findTableContext(toolbar);

      if (context?.table === event.detail?.table) {
        window.setTimeout(() => applyToolbarFilters(toolbar), 0);
      }
    });
  });
});
