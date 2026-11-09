// Scroll master-list records inside their table after ten visible rows.
// This is opt-in, leaving modal and transaction tables untouched.
const MASTER_SELECTOR = '.gct-master-scroll-10';
const VISIBLE_ROWS = 10;

function sizeMasterTable(wrapper) {
    const table = wrapper.querySelector(':scope > table');
    if (!table) return;
    const header = table.tHead;
    const rows = Array.from(table.tBodies).flatMap(body =>
        Array.from(body.rows).filter(row => !row.hidden && getComputedStyle(row).display !== 'none')
    );
    if (rows.length <= VISIBLE_ROWS) {
        wrapper.classList.remove('is-master-scrollable');
        wrapper.style.removeProperty('--gct-master-scroll-height');
        return;
    }

    // Read heights from actual rendered rows so font size, density and
    // responsive layout preferences cannot hide part of the tenth row.
    const headerHeight = header?.getBoundingClientRect().height ?? 0;
    const rowsHeight = rows.slice(0, VISIBLE_ROWS).reduce(
        (sum, row) => sum + row.getBoundingClientRect().height, 0
    );
    if (rowsHeight <= 0) return;
    wrapper.style.setProperty('--gct-master-scroll-height', `${Math.ceil(headerHeight + rowsHeight + 2)}px`);
    wrapper.classList.add('is-master-scrollable');
}

const masterTableObservers = new Map();

function observeMasterRows() {
    document.querySelectorAll(MASTER_SELECTOR).forEach(wrapper => {
        if (masterTableObservers.has(wrapper)) return;
        const table = wrapper.querySelector(':scope > table');
        if (!table?.tBodies.length) return;

        // Record insertion/removal and live filtering can change row counts
        // without firing the page-ready event.
        const observer = new MutationObserver(refreshMasterScroll);
        Array.from(table.tBodies).forEach(body => observer.observe(body, {
            childList: true,
            subtree: false,
        }));
        masterTableObservers.set(wrapper, observer);
    });
    // Partial navigation replaces the old page nodes; drop their observers.
    for (const [wrapper, observer] of masterTableObservers) {
        if (wrapper.isConnected) continue;
        observer.disconnect();
        masterTableObservers.delete(wrapper);
    }
}

let masterScrollFrame = 0;
function refreshMasterScroll() {
    if (masterScrollFrame) cancelAnimationFrame(masterScrollFrame);
    masterScrollFrame = requestAnimationFrame(() => {
        masterScrollFrame = 0;
        observeMasterRows();
        document.querySelectorAll(MASTER_SELECTOR).forEach(sizeMasterTable);
    });
}

document.addEventListener('DOMContentLoaded', refreshMasterScroll);
document.addEventListener('gct:page-loaded', refreshMasterScroll);
window.addEventListener('gct:navigation-ready', refreshMasterScroll);
window.addEventListener('resize', refreshMasterScroll);
document.addEventListener('change', event => {
    if (event.target.closest?.('[data-gct-preference]')) refreshMasterScroll();
});
if (document.readyState !== 'loading') refreshMasterScroll();
