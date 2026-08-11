import '../../../css/Admin/System_Monitoring/activity-logs-controls.css';

document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('activityLogsTable');
    const searchInput = document.getElementById('activitySearch');
    const moduleFilter = document.getElementById('activityModuleFilter');
    const eventFilter = document.getElementById('activityEventFilter');
    const dateFilter = document.getElementById('activityDateFilter');
    const loading = document.getElementById('activityTableLoading');
    const emptyRow = document.getElementById('activityEmptyRow');
    const resultCount = document.getElementById('activityResultCount');
    const footerCount = document.getElementById('activityFooterCount');

    if (!table || !searchInput || !moduleFilter || !eventFilter || !dateFilter) {
        return;
    }

    const rows = Array.from(table.querySelectorAll('tbody tr[data-activity-row]'));
    let filterTimer = null;

    const startOfDay = (date) => new Date(date.getFullYear(), date.getMonth(), date.getDate());

    const matchesDateRange = (rowDate, range) => {
        if (range === 'all') {
            return true;
        }

        const parsed = new Date(`${rowDate} 12:00:00`);

        if (Number.isNaN(parsed.getTime())) {
            return true;
        }

        const today = startOfDay(new Date());
        const value = startOfDay(parsed);
        const diffDays = Math.floor((today - value) / 86400000);

        if (range === 'today') {
            return diffDays === 0;
        }

        if (range === 'week') {
            return diffDays >= 0 && diffDays < 7;
        }

        if (range === 'month') {
            return value.getFullYear() === today.getFullYear()
                && value.getMonth() === today.getMonth();
        }

        return true;
    };

    const updateCounts = (visibleCount) => {
        if (resultCount) {
            resultCount.textContent = `${visibleCount} ${visibleCount === 1 ? 'Activity' : 'Activities'}`;
        }

        if (footerCount) {
            footerCount.textContent = visibleCount === 0
                ? 'No matching activities'
                : `Showing ${visibleCount} of ${rows.length} activities`;
        }
    };

    const applyFilters = () => {
        const query = searchInput.value.trim().toLowerCase();
        const module = moduleFilter.value.toLowerCase();
        const eventType = eventFilter.value.toLowerCase();
        const dateRange = dateFilter.value;
        let visibleCount = 0;

        rows.forEach((row) => {
            const searchable = String(row.dataset.search || '').toLowerCase();
            const rowModule = String(row.dataset.module || '').toLowerCase();
            const rowEvent = String(row.dataset.event || '').toLowerCase();
            const rowDate = String(row.dataset.date || '');

            const visible = (!query || searchable.includes(query))
                && (module === 'all' || rowModule === module)
                && (eventType === 'all' || rowEvent === eventType)
                && matchesDateRange(rowDate, dateRange);

            row.hidden = !visible;

            if (visible) {
                visibleCount += 1;
            }
        });

        if (emptyRow) {
            emptyRow.hidden = visibleCount !== 0;
        }

        updateCounts(visibleCount);

        if (loading) {
            loading.hidden = true;
        }
    };

    const scheduleFilter = () => {
        if (loading) {
            loading.hidden = false;
        }

        window.clearTimeout(filterTimer);
        filterTimer = window.setTimeout(applyFilters, 120);
    };

    searchInput.addEventListener('input', scheduleFilter);
    moduleFilter.addEventListener('change', scheduleFilter);
    eventFilter.addEventListener('change', scheduleFilter);
    dateFilter.addEventListener('change', scheduleFilter);

    applyFilters();

    const modal = document.getElementById('activityDetailsModal');
    const closeButton = document.getElementById('closeActivityModal');
    const closeFooterButton = document.getElementById('closeActivityModalFooter');

    const closeModal = () => {
        if (!modal) {
            return;
        }

        modal.classList.remove('show');
        document.body.classList.remove('activity-modal-open');
    };

    document.addEventListener('click', (event) => {
        const button = event.target.closest('.open-log-modal');

        if (button && modal) {
            document.getElementById('modalUser').textContent = button.dataset.user || '—';
            document.getElementById('modalRole').textContent = button.dataset.role || '—';
            document.getElementById('modalModule').textContent = button.dataset.module || '—';
            document.getElementById('modalType').textContent = button.dataset.type || '—';
            document.getElementById('modalReference').textContent = button.dataset.reference || '—';
            document.getElementById('modalDateTime').textContent = `${button.dataset.date || '—'} ${button.dataset.time || ''}`;
            document.getElementById('modalActivity').textContent = button.dataset.activity || '—';
            document.getElementById('modalDetails').textContent = button.dataset.details || '—';
            document.getElementById('modalIp').textContent = button.dataset.ip || '—';
            document.getElementById('modalDevice').textContent = button.dataset.device || '—';
            modal.classList.add('show');
            document.body.classList.add('activity-modal-open');
            return;
        }

        if (event.target === modal) {
            closeModal();
        }
    });

    closeButton?.addEventListener('click', closeModal);
    closeFooterButton?.addEventListener('click', closeModal);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal?.classList.contains('show')) {
            closeModal();
        }
    });
});
