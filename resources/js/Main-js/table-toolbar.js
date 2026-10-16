// Keep shared Search / Filter / Sort menus visible even when their table card
// clips overflow, especially close to the bottom of the viewport.
const positionTableToolPopover = (trigger, popover) => {
    if (!trigger || !popover) return;
    const rect = trigger.getBoundingClientRect();
    const gutter = 12;
    const width = Math.min(Math.max(popover.offsetWidth || 260, 240), window.innerWidth - gutter * 2);
    const height = Math.min(popover.scrollHeight || 300, window.innerHeight - gutter * 2);
    const below = window.innerHeight - rect.bottom - gutter;
    const above = rect.top - gutter;
    const openAbove = below < height && above > below;
    Object.assign(popover.style, {
        position: 'fixed',
        zIndex: '99990',
        width: width + 'px',
        maxHeight: Math.max(110, window.innerHeight - gutter * 2) + 'px',
        overflowY: 'auto',
        left: Math.max(gutter, Math.min(rect.right - width, window.innerWidth - width - gutter)) + 'px',
        top: (openAbove ? Math.max(gutter, rect.top - height - 8) : Math.min(rect.bottom + 8, window.innerHeight - height - gutter)) + 'px',
    });
};

const closeAllTableToolbars = (except = null) => {
    document.querySelectorAll('[data-table-tool]').forEach((tool) => {
        if (tool === except) return;
        tool.querySelector('[data-table-tool-trigger]')?.setAttribute('aria-expanded', 'false');
        tool.querySelector('.gct-table-tool-popover')?.setAttribute('hidden', '');
    });
};

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-table-tool-trigger]');
    if (trigger) {
        const tool = trigger.closest('[data-table-tool]');
        const popover = tool?.querySelector('.gct-table-tool-popover');
        const shouldOpen = Boolean(popover?.hasAttribute('hidden'));
        closeAllTableToolbars(tool);
        popover?.toggleAttribute('hidden', !shouldOpen);
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

    if (!event.target.closest('[data-table-tool]') && !event.target.closest('.gct-picker-popover') && !event.target.closest('[role="listbox"]') && !event.target.closest('.flatpickr-calendar')) closeAllTableToolbars();
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeAllTableToolbars();
});

document.addEventListener('submit', (event) => {
    if (event.target.matches('form[data-table-toolbar]')) closeAllTableToolbars();
});

window.addEventListener('resize', () => {
    document.querySelectorAll('[data-table-tool-popover]:not([hidden]), .gct-table-tool-popover:not([hidden])').forEach((popover) => {
        positionTableToolPopover(popover.closest('[data-table-tool]')?.querySelector('[data-table-tool-trigger]'), popover);
    });
});
