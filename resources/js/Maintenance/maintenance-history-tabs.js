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
        if (pageType === 'purchase-request' && url.searchParams.get('status') === 'Issued') {
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
        .maintenance-record-tabs {
            display: inline-flex;
            gap: 6px;
            padding: 5px;
            margin: 0 0 12px;
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
            margin: -2px 0 12px;
            color: #7b8798;
            font-size: 11px;
        }
    `;
    document.head.appendChild(style);
};

const insertTabs = (card, pageType, view) => {
    if (!card || card.querySelector('[data-maintenance-record-tabs]')) return;

    const toolbar = card.querySelector('.toolbar');
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
    toolbar.before(tabs);

    if (view === 'history') {
        const note = document.createElement('p');
        note.className = 'maintenance-history-note';
        note.textContent = pageType === 'job-order'
            ? 'Completed Job Orders are kept here for reference and audit history.'
            : 'Issued Purchase Requests are kept here for reference and audit history.';
        tabs.after(note);
    }
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
        const joStatus = statusCells[0]?.textContent?.trim().toLowerCase() || '';
        const completed = joStatus === 'completed';
        row.hidden = view === 'history' ? !completed : completed;
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
        const status = row.querySelector('.status-col')?.textContent?.trim().toLowerCase() || '';
        const issued = status === 'issued';
        row.hidden = view === 'history' ? !issued : issued;
    });

    const newButton = card.querySelector('#openPrModal');
    if (newButton) newButton.hidden = view === 'history';
};

const applyMaintenanceRecordViews = () => {
    ensureStyles();
    applyJobOrderView();
    applyPurchaseRequestView();
};

document.addEventListener('DOMContentLoaded', applyMaintenanceRecordViews);
window.addEventListener('ajax:content-updated', applyMaintenanceRecordViews);
window.addEventListener('system-regions-refreshed', applyMaintenanceRecordViews);
