(() => {
    const footers = () => document.querySelectorAll('[data-scroll-pagination]');

    const findContext = (footer) => {
        let node = footer.parentElement;
        while (node && node !== document.body) {
            const wrap = node.querySelector('.table-wrap');
            const table = wrap?.querySelector('table');
            if (table && node.contains(footer)) return { wrap, table };
            node = node.parentElement;
        }
        return null;
    };

    const findParsedContext = (footer) => {
        let node = footer.parentElement;
        while (node && node.tagName !== 'BODY') {
            const wrap = node.querySelector('.table-wrap');
            const table = wrap?.querySelector('table');
            if (table && node.contains(footer)) return { wrap, table };
            node = node.parentElement;
        }
        return null;
    };

    const updateCount = (footer, table) => {
        const total = Number(footer.dataset.total || 0);
        const rows = table.tBodies[0]
            ? Array.from(table.tBodies[0].rows).filter((row) => !row.classList.contains('empty-row')).length
            : 0;
        const label = footer.querySelector('[data-entry-count]');
        if (label) label.textContent = `Showing ${rows ? 1 : 0} to ${rows} of ${total} entries`;
    };

    const appendModals = (parsed) => {
        parsed.querySelectorAll('.modal-overlay[id], .pms-modal-overlay[id], .ui-modal-overlay[id], .popup-overlay[id]').forEach((modal) => {
            if (!document.getElementById(modal.id)) {
                document.body.appendChild(document.importNode(modal, true));
            }
        });
    };

    const initializeFooter = async (footer, footerIndex) => {
        if (footer.dataset.scrollPaginationInitialized === 'true') return;
        footer.dataset.scrollPaginationInitialized = 'true';

        const context = findContext(footer);
        if (!context) return;

        let nextUrl = footer.dataset.nextUrl || '';
        const visited = new Set();
        const loading = footer.querySelector('[data-table-loading]');

        if (loading && nextUrl) loading.hidden = false;
        context.wrap.classList.toggle('is-loading-all-rows', Boolean(nextUrl));

        try {
            while (nextUrl && !visited.has(nextUrl)) {
                visited.add(nextUrl);
                const response = await fetch(nextUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                    credentials: 'same-origin',
                });
                if (!response.ok) throw new Error(`Table page request failed: ${response.status}`);

                const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
                const parsedFooters = parsed.querySelectorAll('[data-scroll-pagination]');
                const parsedFooter = parsedFooters[footerIndex] || parsedFooters[0];
                const parsedContext = parsedFooter ? findParsedContext(parsedFooter) : null;
                if (!parsedFooter || !parsedContext?.table?.tBodies[0]) break;

                const targetBody = context.table.tBodies[0];
                Array.from(parsedContext.table.tBodies[0].rows).forEach((row) => {
                    if (!row.classList.contains('empty-row')) {
                        targetBody.appendChild(document.importNode(row, true));
                    }
                });

                appendModals(parsed);
                nextUrl = parsedFooter.dataset.nextUrl || '';
                updateCount(footer, context.table);
            }

            document.dispatchEvent(new CustomEvent('system:table-rows-loaded', {
                detail: { table: context.table },
            }));
        } catch (error) {
            console.error('Unable to load all table records.', error);
            footer.dataset.loadError = 'true';
        } finally {
            if (loading) loading.hidden = true;
            context.wrap.classList.remove('is-loading-all-rows');
            updateCount(footer, context.table);
        }
    };

    const initialize = () => footers().forEach((footer, index) => initializeFooter(footer, index));

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();