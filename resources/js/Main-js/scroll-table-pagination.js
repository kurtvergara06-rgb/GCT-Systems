(() => {
    const footers = () => document.querySelectorAll('[data-scroll-pagination]');

    const findTableContext = (footer, root = document) => {
        let node = footer.parentElement;
        while (node && node !== root.body && node.tagName !== 'BODY') {
            const wrap = node.querySelector('.table-wrap');
            const table = wrap?.querySelector('table');
            if (table && node.contains(footer)) {
                return { type: 'table', wrap, table };
            }
            node = node.parentElement;
        }
        return null;
    };

    const findRecordContext = (footer, root = document) => {
        let node = footer.parentElement;
        while (node && node !== root.body && node.tagName !== 'BODY') {
            const list = node.querySelector('[data-scroll-record-list]');
            if (list && node.contains(footer)) {
                return {
                    type: 'records',
                    list,
                    selector: list.dataset.recordSelector || '[data-scroll-record]',
                };
            }
            node = node.parentElement;
        }
        return null;
    };

    const findContext = (footer, root = document) => {
        return findTableContext(footer, root) || findRecordContext(footer, root);
    };

    const tableRows = (table) => {
        return table?.tBodies?.[0]
            ? Array.from(table.tBodies[0].rows).filter((row) => !row.classList.contains('empty-row'))
            : [];
    };

    const recordRows = (context) => {
        return Array.from(context.list.querySelectorAll(context.selector));
    };

    const updateCount = (footer, context) => {
        const total = Number(footer.dataset.total || 0);
        const count = context.type === 'table'
            ? tableRows(context.table).length
            : recordRows(context).length;
        const label = footer.querySelector('[data-entry-count]');

        if (label) {
            label.textContent = `Showing ${count ? 1 : 0} to ${count} of ${total} entries`;
        }
    };

    const appendModals = (parsed) => {
        parsed.querySelectorAll('.modal-overlay[id], .pms-modal-overlay[id], .ui-modal-overlay[id], .popup-overlay[id]').forEach((modal) => {
            if (!document.getElementById(modal.id)) {
                document.body.appendChild(document.importNode(modal, true));
            }
        });
    };

    const appendTablePage = (targetContext, parsedContext) => {
        const targetBody = targetContext.table.tBodies[0];

        tableRows(parsedContext.table).forEach((row) => {
            targetBody.appendChild(document.importNode(row, true));
        });
    };

    const appendRecordPage = (targetContext, parsedContext) => {
        recordRows(parsedContext).forEach((record) => {
            targetContext.list.appendChild(document.importNode(record, true));
        });
    };

    const dispatchLoaded = (context) => {
        if (context.type === 'table') {
            document.dispatchEvent(new CustomEvent('system:table-rows-loaded', {
                detail: { table: context.table },
            }));
            return;
        }

        document.dispatchEvent(new CustomEvent('system:record-rows-loaded', {
            detail: { list: context.list },
        }));
    };

    const initializeFooter = async (footer, footerIndex) => {
        if (footer.dataset.scrollPaginationInitialized === 'true') return;
        footer.dataset.scrollPaginationInitialized = 'true';

        const context = findContext(footer);
        if (!context) return;

        let nextUrl = footer.dataset.nextUrl || '';
        const visited = new Set();
        const loading = footer.querySelector('[data-table-loading]');
        const loadingTarget = context.type === 'table' ? context.wrap : context.list;

        if (loading && nextUrl) loading.hidden = false;
        loadingTarget?.classList.toggle('is-loading-all-rows', Boolean(nextUrl));

        try {
            while (nextUrl && !visited.has(nextUrl)) {
                visited.add(nextUrl);
                const response = await fetch(nextUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error(`Record page request failed: ${response.status}`);
                }

                const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
                const parsedFooters = parsed.querySelectorAll('[data-scroll-pagination]');
                const parsedFooter = parsedFooters[footerIndex] || parsedFooters[0];
                const parsedContext = parsedFooter ? findContext(parsedFooter, parsed) : null;

                if (!parsedFooter || !parsedContext || parsedContext.type !== context.type) {
                    break;
                }

                if (context.type === 'table') {
                    appendTablePage(context, parsedContext);
                } else {
                    appendRecordPage(context, parsedContext);
                }

                appendModals(parsed);
                nextUrl = parsedFooter.dataset.nextUrl || '';
                updateCount(footer, context);
            }

            dispatchLoaded(context);
        } catch (error) {
            console.error('Unable to load all records.', error);
            footer.dataset.loadError = 'true';
        } finally {
            if (loading) loading.hidden = true;
            loadingTarget?.classList.remove('is-loading-all-rows');
            updateCount(footer, context);
        }
    };

    const initialize = () => footers().forEach((footer, index) => initializeFooter(footer, index));

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();
