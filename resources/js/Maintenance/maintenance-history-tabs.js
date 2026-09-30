const TAB_PARAM = 'record_view';

const getRecordView = () => {
    const value = new URL(window.location.href).searchParams.get(TAB_PARAM);
    return value === 'history' ? 'history' : 'active';
};

const buildUrl = (view, pageType) => {
    const url = new URL(window.location.href);
    url.searchParams.delete('page');

    if (view === 'history') {
        url.searchParams.set(TAB_PARAM, 'history');
        if (pageType === 'purchase-request') {
            url.searchParams.set('status', 'Issued');
        }
    } else {
        url.searchParams.delete(TAB_PARAM);
        if (pageType === 'purchase-request') {
            url.searchParams.set('status', 'All Statuses');
        }
    }

    return `${url.pathname}${url.search}`;
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

        tr[data-maintenance-record-hidden="true"] {
            display: none !important;
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

const insertTabs = (card, pageType, view) => {
    if (!card) return;

    const existing = card.querySelector('[data-maintenance-record-tabs]');
    if (existing) existing.remove();
    card.querySelector('.maintenance-history-note')?.remove();

    const toolbar = card.querySelector('.toolbar');
    const header = card.querySelector('.section-header');
    if (!toolbar) return;

    const tabs = document.createElement('div');
    tabs.className = 'maintenance-record-tabs';
    tabs.dataset.maintenanceRecordTabs = 'true';
    tabs.setAttribute('role', 'tablist');
    tabs.setAttribute('aria-label', 'Maintenance record view');

    const active = document.createElement('a');
    active.className = `maintenance-record-tab${view === 'active' ? ' is-active' : ''}`;
    active.href = buildUrl('active', pageType);
    active.innerHTML = '<i class="fa-solid fa-list-check"></i><span>Active</span>';
    active.setAttribute('role', 'tab');
    active.setAttribute('aria-selected', view === 'active' ? 'true' : 'false');

    const history = document.createElement('a');
    history.className = `maintenance-record-tab${view === 'history' ? ' is-active' : ''}`;
    history.href = buildUrl('history', pageType);
    history.innerHTML = '<i class="fa-solid fa-clock-rotate-left"></i><span>History</span>';
    history.setAttribute('role', 'tab');
    history.setAttribute('aria-selected', view === 'history' ? 'true' : 'false');

    tabs.append(active, history);

    if (header) {
        header.classList.add('maintenance-record-header');
        header.appendChild(tabs);
    } else {
        toolbar.before(tabs);
    }

    if (view === 'history') {
        const note = document.createElement('p');
        note.className = 'maintenance-history-note';
        note.textContent = pageType === 'job-order'
            ? 'Completed Job Orders are kept here for reference and audit history.'
            : 'Issued Purchase Requests are kept here for reference and audit history.';
        toolbar.before(note);
    }
};

const setRowVisible = (row, visible) => {
    if (visible) {
        row.hidden = false;
        row.removeAttribute('data-maintenance-record-hidden');
        row.style.removeProperty('display');
        return;
    }

    row.hidden = true;
    row.dataset.maintenanceRecordHidden = 'true';
    row.style.setProperty('display', 'none', 'important');
};

const normalizeStatus = (value) => String(value || '').trim().toLowerCase();

const getPurchaseRequestStatus = (row) => {
    const viewAction = row.querySelector('.open-view-pr-modal');
    const rawActionStatus = normalizeStatus(viewAction?.getAttribute('data-status'));
    if (rawActionStatus) return rawActionStatus;

    const editAction = row.querySelector('.open-edit-pr-modal');
    const rawEditStatus = normalizeStatus(editAction?.getAttribute('data-status'));
    if (rawEditStatus) return rawEditStatus;

    return normalizeStatus(row.querySelector('.status-col')?.textContent);
};

const applyJobOrderView = () => {
    const page = document.querySelector('.jo-page');
    const card = page?.querySelector('.jo-table-card');
    if (!page || !card) return;

    const view = getRecordView();
    insertTabs(card, 'job-order', view);

    const rows = card.querySelectorAll('.job-orders-table tbody tr');
    rows.forEach((row) => {
        const statusCells = row.querySelectorAll('td.status-col');
        const joStatus = normalizeStatus(statusCells[0]?.textContent);
        const completed = joStatus === 'completed';
        setRowVisible(row, view === 'history' ? completed : !completed);
    });

    const newButton = card.querySelector('#openJobModal');
    if (newButton) newButton.hidden = view === 'history';
};

const applyPurchaseRequestView = () => {
    const page = document.querySelector('.purchase-page');
    const card = page?.querySelector('.purchase-request-card');
    if (!page || !card) return;

    const view = getRecordView();
    insertTabs(card, 'purchase-request', view);

    const rows = card.querySelectorAll('.purchase-request-table tbody tr');
    rows.forEach((row) => {
        const status = getPurchaseRequestStatus(row);
        row.dataset.maintenanceRecordStatus = status;
        const issued = status === 'issued';
        setRowVisible(row, view === 'history' ? issued : !issued);
    });

    const newButton = card.querySelector('#openPrModal');
    if (newButton) newButton.hidden = view === 'history';
};

const applyMaintenanceRecordViews = () => {
    ensureStyles();
    applyJobOrderView();
    applyPurchaseRequestView();
};

let maintenanceRecordObserver = null;
let maintenanceRecordObserverTimer = null;

const startMaintenanceRecordObserver = () => {
    if (maintenanceRecordObserver) maintenanceRecordObserver.disconnect();

    const target = document.querySelector('.jo-table-card, .purchase-request-card');
    if (!target) return;

    maintenanceRecordObserver = new MutationObserver(() => {
        if (maintenanceRecordObserverTimer) clearTimeout(maintenanceRecordObserverTimer);
        maintenanceRecordObserverTimer = window.setTimeout(() => {
            maintenanceRecordObserverTimer = null;
            applyMaintenanceRecordViews();
        }, 25);
    });

    maintenanceRecordObserver.observe(target, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['style', 'class', 'hidden'],
    });
};

const initializeMaintenanceRecordViews = () => {
    applyMaintenanceRecordViews();
    startMaintenanceRecordObserver();
    window.setTimeout(applyMaintenanceRecordViews, 100);
    window.setTimeout(applyMaintenanceRecordViews, 500);
};

document.addEventListener('DOMContentLoaded', initializeMaintenanceRecordViews);
window.addEventListener('load', initializeMaintenanceRecordViews);
window.addEventListener('ajax:content-updated', initializeMaintenanceRecordViews);
window.addEventListener('system-regions-refreshed', initializeMaintenanceRecordViews);
window.addEventListener('gct:navigation-ready', initializeMaintenanceRecordViews);
