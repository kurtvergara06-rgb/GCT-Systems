window.GCTPartialNavigation.registerInitializer('shared-table-search', 'main', () => {
  const toolbarSelector = [
    '.toolbar',
    '.requested-toolbar',
    '.restock-toolbar',
    '.po-toolbar',
    '.schedule-toolbar',
    '.assignment-toolbar',
  ].join(', ');

  const searchInputSelector = '.search-box input[type="text"], .search-box input[type="search"]';

  const findTableContext = (toolbar, root = document) => {
    let node = toolbar?.parentElement;

    while (node && node !== root.body) {
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
  const pendingServerFilterTimers = new Set();

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

      if (!value || value === 'all' || value.startsWith('all ')) {
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

  const serverFilterControllers = new WeakMap();
  const activeServerFilterControllers = new Set();

  const findMatchingServerToolbar = (parsed, toolbar) => {
    if (!(toolbar instanceof HTMLFormElement)) {
      return null;
    }

    const currentToolbars = Array.from(
      document.querySelectorAll(toolbarSelector)
    ).filter((item) => usesServerFilter(item));

    const parsedToolbars = Array.from(
      parsed.querySelectorAll(toolbarSelector)
    ).filter((item) => usesServerFilter(item));

    const currentIndex = currentToolbars.indexOf(toolbar);

    if (currentIndex >= 0 && parsedToolbars[currentIndex]) {
      return parsedToolbars[currentIndex];
    }

    const currentAction = new URL(
      toolbar.getAttribute('action') || window.location.href,
      window.location.href
    );

    return parsedToolbars.find((item) => {
      const parsedAction = new URL(
        item.getAttribute('action') || currentAction.href,
        currentAction.href
      );

      return parsedAction.pathname === currentAction.pathname;
    }) || null;
  };

  const replaceServerFilteredTable = (toolbar, parsedToolbar, parsed) => {
    const currentContext = findTableContext(toolbar);
    const parsedContext = findTableContext(parsedToolbar, parsed);

    if (!currentContext?.table || !parsedContext?.table) {
      return false;
    }

    const currentBody = currentContext.table.tBodies?.[0];
    const parsedBody = parsedContext.table.tBodies?.[0];

    if (!currentBody || !parsedBody) {
      return false;
    }

    currentBody.replaceWith(
      document.importNode(parsedBody, true)
    );

    if (currentContext.footer && parsedContext.footer) {
      currentContext.footer.replaceWith(
        document.importNode(parsedContext.footer, true)
      );
    }

    const currentContainer = toolbar.closest(
      'section, .table-card, .card'
    );
    const parsedContainer = parsedToolbar.closest(
      'section, .table-card, .card'
    );

    [
      '.section-count',
      '.activity-count',
      '[data-server-filter-count]',
    ].forEach((selector) => {
      const currentCount = currentContainer?.querySelector(selector);
      const parsedCount = parsedContainer?.querySelector(selector);

      if (currentCount && parsedCount) {
        currentCount.innerHTML = parsedCount.innerHTML;
      }
    });

    document.dispatchEvent(
      new CustomEvent(
        'ajax:content-updated',
        {
          detail: {
            source: 'server-filter',
            toolbar,
          },
        }
      )
    );

    document.dispatchEvent(
      new CustomEvent(
        'system:table-filtered',
        {
          detail: {
            table: currentContext.table,
            toolbar,
          },
        }
      )
    );

    return true;
  };

  const runServerFilter = async (toolbar) => {
    if (!toolbar?.isConnected) {
      return;
    }

    const target = buildServerFilterUrl(toolbar);
    if (!target) {
      return;
    }

    if (
      toolbar.dataset.serverFilterNavigation === 'true'
      && window.GCTPartialNavigation?.navigate
    ) {
      await window.GCTPartialNavigation.navigate(target.href);
      return;
    }

    const previousController =
      serverFilterControllers.get(toolbar);

    if (previousController) {
      previousController.abort();
    }

    const controller =
      new AbortController();

    serverFilterControllers.set(
      toolbar,
      controller
    );
    activeServerFilterControllers.add(controller);

    const context =
      findTableContext(toolbar);

    const loading =
      toolbar.closest(
        'section, .table-card, .card'
      )?.querySelector(
        '[data-server-filter-loading], .activity-table-loading'
      );

    toolbar.setAttribute(
      'aria-busy',
      'true'
    );

    context?.table
      ?.closest('.table-wrap')
      ?.classList.add(
        'is-server-filtering'
      );

    if (loading) {
      loading.hidden = false;
    }

    document.dispatchEvent(
      new CustomEvent(
        'system:server-filter-started',
        {
          detail: {
            toolbar,
            url: target.href,
          },
        }
      )
    );

    try {
      const response =
        await fetch(
          target.href,
          {
            method: 'GET',
            headers: {
              Accept: 'text/html',
              'X-Requested-With':
                'XMLHttpRequest',
            },
            credentials:
              'same-origin',
            cache: 'no-store',
            signal:
              controller.signal,
          }
        );

      if (!response.ok) {
        throw new Error(
          `Server filter request failed: ${response.status}`
        );
      }

      const finalUrl =
        response.url ||
        target.href;

      const parsed =
        new DOMParser()
          .parseFromString(
            await response.text(),
            'text/html'
          );

      if (
        controller.signal.aborted ||
        serverFilterControllers.get(toolbar) !== controller ||
        !toolbar.isConnected
      ) {
        return;
      }

      const parsedToolbar =
        findMatchingServerToolbar(
          parsed,
          toolbar
        );

      if (
        !parsedToolbar ||
        !replaceServerFilteredTable(
          toolbar,
          parsedToolbar,
          parsed
        )
      ) {
        throw new Error(
          'Server-filter table region could not be resolved.'
        );
      }

      window.history.replaceState(
        {
          ...window.history.state,
          gctPartial: true,
        },
        '',
        finalUrl
      );
    } catch (error) {
      if (
        error?.name === 'AbortError' ||
        controller.signal.aborted ||
        serverFilterControllers.get(toolbar) !== controller ||
        !toolbar.isConnected
      ) {
        return;
      }

      console.error(
        'Unable to update table filters without a page reload.',
        error
      );

      if (
        window
          .GCTPartialNavigation
          ?.navigate
      ) {
        await window
          .GCTPartialNavigation
          .navigate(
            target.href
          );
        return;
      }

      window.location.assign(
        target.href
      );
    } finally {
      activeServerFilterControllers.delete(controller);

      const isLatestRequest =
        serverFilterControllers.get(toolbar) === controller;

      if (isLatestRequest) {
        serverFilterControllers.delete(toolbar);

        toolbar.removeAttribute(
          'aria-busy'
        );

        context?.table
          ?.closest('.table-wrap')
          ?.classList.remove(
            'is-server-filtering'
          );

        if (loading) {
          loading.hidden = true;
        }

        document.dispatchEvent(
          new CustomEvent(
            'system:server-filter-finished',
            {
              detail: {
                toolbar,
                url: target.href,
              },
            }
          )
        );
      }
    }
  };

  const scheduleServerFilter = (toolbar, delay = 300) => {
    const previousTimer = serverFilterTimers.get(toolbar);
    if (previousTimer) {
      window.clearTimeout(previousTimer);
      pendingServerFilterTimers.delete(previousTimer);
    }

    const timer = window.setTimeout(() => {
      pendingServerFilterTimers.delete(timer);
      serverFilterTimers.delete(toolbar);
      void runServerFilter(toolbar);
    }, delay);

    serverFilterTimers.set(toolbar, timer);
    pendingServerFilterTimers.add(timer);
  };

  window.addEventListener('gct:navigation-before', () => {
    pendingServerFilterTimers.forEach((timer) => window.clearTimeout(timer));
    pendingServerFilterTimers.clear();
    activeServerFilterControllers.forEach((controller) => controller.abort());
    activeServerFilterControllers.clear();
  });

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
    const control = event.target.closest?.('select, input[type="date"]');
    const toolbar = closestToolbar(control);

    if (!control || !toolbar) {
      return;
    }

    // Date inputs are server-side criteria; do not treat them as text filters.
    if (control.matches('input[type="date"]') && !usesServerFilter(toolbar)) {
      return;
    }

    if (usesServerFilter(toolbar)) {
      if (!ownsServerFilter(toolbar)) {
        void runServerFilter(toolbar);
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
        void runServerFilter(toolbar);
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
      event.preventDefault();
      void runServerFilter(form);
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
