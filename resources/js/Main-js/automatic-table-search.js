document.addEventListener('DOMContentLoaded', () => {
  const forms = document.querySelectorAll('form.toolbar');

  const findTableContext = (form) => {
    let node = form.parentElement;

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
        && !row.classList.contains('gct-search-empty-row');
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

  forms.forEach((form) => {
    if (form.classList.contains('inventory-toolbar') || form.dataset.clientFilter !== undefined) {
      return;
    }

    const input = form.querySelector('.search-box input[type="text"]');
    const context = findTableContext(form);

    if (!input || !context?.table || input.dataset.autoSearchBound === 'true') {
      return;
    }

    input.dataset.autoSearchBound = 'true';
    form.dataset.instantSearch = 'true';

    const applySearch = () => {
      removeEmptyRow(context.table);

      const query = input.value.trim().toLowerCase();
      const rows = searchableRows(context.table);
      let visibleCount = 0;

      rows.forEach((row) => {
        const matches = !query || row.textContent.toLowerCase().includes(query);
        row.style.display = matches ? '' : 'none';

        if (matches) {
          visibleCount += 1;
        }
      });

      if (visibleCount === 0 && rows.length > 0) {
        showEmptyRow(context.table);
      }

      updateCount(context.footer, visibleCount, rows.length);
    };

    input.addEventListener('input', applySearch);

    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        applySearch();
      }

      if (event.key === 'Escape') {
        event.preventDefault();
        input.value = '';
        applySearch();
      }
    });

    form.addEventListener('submit', (event) => {
      const submitter = event.submitter;
      const searchIsActive = document.activeElement === input || !submitter;

      if (searchIsActive) {
        event.preventDefault();
        applySearch();
      }
    });

    document.addEventListener('system:table-rows-loaded', (event) => {
      if (event.detail?.table === context.table) {
        window.setTimeout(applySearch, 0);
      }
    });

    applySearch();
  });
});
