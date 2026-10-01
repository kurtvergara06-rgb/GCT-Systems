const TAB_PARAM = 'record_view';

const getRecordView = () => {
    const value = new URL(window.location.href).searchParams.get(TAB_PARAM);
    return value === 'history' ? 'history' : 'active';
};

const buildUrl = (view) => {
    const url = new URL(window.location.href);
    url.searchParams.delete('page');

    if (view === 'history') {
        url.searchParams.set(TAB_PARAM, 'history');
    } else {
        url.searchParams.delete(TAB_PARAM);
    }

    return `${url.pathname}${url.search}`;
};

const navigateRecordTab = (event, link) => {
    if (event.defaultPrevented || event.button !== 0) return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    event.preventDefault();

    const target = link.href;
    if (window.GCTPartialNavigation?.navigate) {
        window.GCTPartialNavigation.navigate(target);
        return;
    }

    window.location.assign(target);
};

const ensureStyles = () => {
    if (document.getElementById('maintenanceRecordTabsStyles')) return;

    const style = document.createElement('style');
    style.id = 'maintenanceRecordTabsStyles';
    style.textContent = `
        .maintenance-record-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .maintenance-record-tabs {
            display: inline-flex;
            flex: 0 0 auto;
            gap: 6px;
            padding: 5px;
            margin: 0 0 0 auto;
            border: 1px solid #dbe4f0;
            border-radius: 12px;
            background: #f6f8fb;
        }

        .maintenance-record-tab {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            min-height: 36px;
            padding: 8px 14px;
            border-radius: 9px;
            color: #5a6678;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
            transition: background-color .18s ease, color .18s ease, box-shadow .18s ease;
        }

        .maintenance-record-tab:hover {
            background: #ffffff;
            color: #102a56;
        }

        .maintenance-record-tab.is-active {
            background: #ffffff;
            color: #102a56;
            box-shadow: 0 2px 8px rgba(16, 42, 86, .09);
        }

        .maintenance-history-note {
            margin: 4px 0 12px;
            color: #7b8798;
            font-size: 11px;
        }

        @media (max-width: 760px) {
            .maintenance-record-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .maintenance-record-tabs {
                margin-left: 0;
            }
        }
    `;
    document.head.appendChild(style);
};

const createTab = (viewName, pageType) => {
    const link = document.createElement('a');
    link.className = 'maintenance-record-tab';
    link.dataset.maintenanceRecordTabLink = viewName;
    link.dataset.partialNavigation = 'true';
    link.setAttribute('role', 'tab');
    link.innerHTML = viewName === 'history'
        ? '<i class="fa-solid fa-clock-rotate-left"></i><span>History</span>'
        : '<i class="fa-solid fa-list-check"></i><span>Active</span>';
    link.href = buildUrl(viewName, pageType);
    return link;
};

const updateTab = (link, viewName, pageType, currentView) => {
    if (!link) return;

    link.href = buildUrl(viewName, pageType);
    link.dataset.maintenanceRecordTabLink = viewName;
    link.dataset.partialNavigation = 'true';
    link.classList.toggle('is-active', currentView === viewName);
    link.setAttribute('aria-selected', currentView === viewName ? 'true' : 'false');
};

const insertTabs = (card, pageType, view) => {
    if (!card) return;

    const toolbar = card.querySelector('.toolbar');
    const header = card.querySelector('.section-header');
    if (!toolbar) return;

    let tabs = card.querySelector('[data-maintenance-record-tabs]');

    if (!tabs) {
        tabs = document.createElement('div');
        tabs.className = 'maintenance-record-tabs';
        tabs.dataset.maintenanceRecordTabs = 'true';
        tabs.setAttribute('role', 'tablist');
        tabs.setAttribute('aria-label', 'Maintenance record view');
        tabs.append(
            createTab('active', pageType),
            createTab('history', pageType),
        );

        if (header) {
            header.classList.add('maintenance-record-header');
            header.appendChild(tabs);
        } else {
            toolbar.before(tabs);
        }
    }

    updateTab(tabs.querySelector('[data-maintenance-record-tab-link="active"]'), 'active', pageType, view);
    updateTab(tabs.querySelector('[data-maintenance-record-tab-link="history"]'), 'history', pageType, view);

    let note = card.querySelector('.maintenance-history-note');
    if (view === 'history') {
        if (!note) {
            note = document.createElement('p');
            note.className = 'maintenance-history-note';
            toolbar.before(note);
        }
        note.textContent = 'Completed Job Orders are kept here for reference and audit history.';
    } else {
        note?.remove();
    }
};

const applyJobOrderView = () => {
    const page = document.querySelector('.jo-page');
    const card = page?.querySelector('.jo-table-card');
    if (!page || !card) return;

    const view = getRecordView();
    insertTabs(card, 'job-order', view);

    // Row filtering is server-side. JS only controls presentation/state.
    const newButton = card.querySelector('#openJobModal');
    if (newButton) newButton.hidden = view === 'history';
};

const applyMaintenanceRecordViews = () => {
    ensureStyles();
    applyJobOrderView();
};

const initializeMaintenanceRecordViews = () => {
    applyMaintenanceRecordViews();
    window.setTimeout(applyMaintenanceRecordViews, 100);
};

document.addEventListener('click', (event) => {
    const link = event.target.closest('.jo-page [data-maintenance-record-tab-link]');
    if (!link) return;
    navigateRecordTab(event, link);
});

document.addEventListener('DOMContentLoaded', initializeMaintenanceRecordViews);
window.addEventListener('load', initializeMaintenanceRecordViews);
window.addEventListener('ajax:content-updated', initializeMaintenanceRecordViews);
window.addEventListener('system-regions-refreshed', initializeMaintenanceRecordViews);
window.addEventListener('gct:navigation-ready', initializeMaintenanceRecordViews);
