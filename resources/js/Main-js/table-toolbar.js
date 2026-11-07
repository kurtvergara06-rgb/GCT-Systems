// Use the browser top layer for table tool popovers. A transformed page
// wrapper creates a new containing block for position:fixed; that can push
// Filter / Sort beyond the production viewport even with clamped coordinates.
// Native popovers remain inside their form in the DOM, preserving inputs,
// validation, and submit behavior while escaping overflow and transforms.
const getViewport = () => {
    const view = window.visualViewport;
    return {
        left: view?.offsetLeft || 0,
        top: view?.offsetTop || 0,
        width: view?.width || window.innerWidth,
        height: view?.height || window.innerHeight,
    };
};

const positionTableToolPopover = (trigger, popover) => {
    if (!trigger || !popover || popover.hidden) return;
    const rect = trigger.getBoundingClientRect();
    const viewport = getViewport();
    const gutter = 12;
    const availableWidth = Math.max(1, viewport.width - gutter * 2);
    const availableHeight = Math.max(1, viewport.height - gutter * 2);
    const width = Math.min(
        Math.max(popover.scrollWidth || popover.offsetWidth || 260, 240),
        availableWidth
    );
    const height = Math.min(popover.scrollHeight || 300, availableHeight);
    const leftBound = viewport.left + gutter;
    const topBound = viewport.top + gutter;
    const rightBound = viewport.left + viewport.width - gutter;
    const bottomBound = viewport.top + viewport.height - gutter;
    const below = bottomBound - rect.bottom;
    const above = rect.top - topBound;
    const openAbove = below < height + 8 && above > below;
    const preferredTop = openAbove ? rect.top - height - 8 : rect.bottom + 8;
    Object.assign(popover.style, {
        position: 'fixed',
        zIndex: '99990',
        inset: 'auto',
        right: 'auto',
        bottom: 'auto',
        margin: '0',
        boxSizing: 'border-box',
        width: width + 'px',
        maxWidth: availableWidth + 'px',
        maxHeight: availableHeight + 'px',
        overflowY: 'auto',
        left: Math.max(leftBound, Math.min(rect.right - width, rightBound - width)) + 'px',
        top: Math.max(topBound, Math.min(preferredTop, bottomBound - height)) + 'px',
    });
};

const hideTableToolPopover = (popover) => {
    if (!popover) return;
    // Close any date/time picker owned by this Filter before hiding its
    // parent. Native manual popovers have independent top-layer lifetimes.
    const picker = popover.querySelector('.gct-picker-popover:not([hidden])');
    picker?.querySelector('.gct-picker-close')?.click();
    if (popover.matches?.(':popover-open')) popover.hidePopover();
    popover.hidden = true;
};

const showTableToolPopover = (popover) => {
    if (!popover) return;
    popover.hidden = false;
    if (typeof popover.showPopover === 'function') {
        popover.setAttribute('popover', 'manual');
        try {
            popover.showPopover();
        } catch {
            // Older browsers retain the fixed-position fallback.
        }
    }
};

const closeAllTableToolbars = (except = null) => {
    document.querySelectorAll('[data-table-tool]').forEach((tool) => {
        if (tool === except) return;
        tool.querySelector('[data-table-tool-trigger]')?.setAttribute('aria-expanded', 'false');
        hideTableToolPopover(tool.querySelector('.gct-table-tool-popover'));
    });
};

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-table-tool-trigger]');
    if (trigger) {
        const tool = trigger.closest('[data-table-tool]');
        const popover = tool?.querySelector('.gct-table-tool-popover');
        const shouldOpen = Boolean(popover?.hasAttribute('hidden'));
        closeAllTableToolbars(tool);
        if (shouldOpen) showTableToolPopover(popover);
        else hideTableToolPopover(popover);
        trigger.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        if (shouldOpen) positionTableToolPopover(trigger, popover);
        if (shouldOpen && tool?.dataset.tableTool === 'search') {
            requestAnimationFrame(() => popover?.querySelector('input[name="search"]')?.focus());
        }
        return;
    }

    const close = event.target.closest('[data-table-popover-close]');
    if (close) {
        const tool = close.closest('[data-table-tool]');
        closeAllTableToolbars();
        tool?.querySelector('[data-table-tool-trigger]')?.focus();
        return;
    }

    const clear = event.target.closest('[data-table-clear-filters]');
    if (clear) {
        const form = clear.closest('form[data-table-toolbar]');
        form?.querySelectorAll('.gct-table-filter-fields input,.gct-table-filter-fields select').forEach((field) => {
            field.value = field.dataset.default ?? '';
            field.dispatchEvent(new Event('input', { bubbles: true }));
        });
        // Clear drafts only; user chooses when to apply.
        return;
    }

    const reset = event.target.closest('[data-table-reset-sort]');
    if (reset) {
        const form = reset.closest('form[data-table-toolbar]');
        form?.querySelectorAll('[name="sort"],[name="sort_direction"]').forEach((field) => {
            field.value = field.dataset.default ?? '';
        });
        // Reset choices without submitting until Apply Sort is clicked.
        return;
    }

    // A date picker removes its calendar DOM when a date is chosen. By the
    // time this document handler runs, event.target may be detached, making
    // closest('.gct-picker-popover') fail. The composed event path preserves
    // the original calendar ancestors and prevents dismissing Filter early.
    const clickedInsidePicker = event.composedPath().some((node) =>
        node instanceof Element && node.matches('.gct-picker-popover, .flatpickr-calendar, [role="listbox"]')
    );
    if (!event.target.closest('[data-table-tool]') && !clickedInsidePicker) {
        closeAllTableToolbars();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeAllTableToolbars();
});

document.addEventListener('submit', (event) => {
    if (event.target.matches('form[data-table-toolbar]')) closeAllTableToolbars();
});

const repositionOpenTableToolbars = () => {
    document.querySelectorAll('[data-table-tool-popover]:not([hidden]), .gct-table-tool-popover:not([hidden])').forEach((popover) => {
        positionTableToolPopover(popover.closest('[data-table-tool]')?.querySelector('[data-table-tool-trigger]'), popover);
    });
};
window.addEventListener('resize', repositionOpenTableToolbars);
window.visualViewport?.addEventListener('resize', repositionOpenTableToolbars);
window.visualViewport?.addEventListener('scroll', repositionOpenTableToolbars);
document.addEventListener('scroll', repositionOpenTableToolbars, true);
