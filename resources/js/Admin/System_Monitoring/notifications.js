document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('notificationFilterForm');
    const list = document.getElementById('notificationList');
    const loading = document.getElementById('notificationListLoading');
    const empty = document.getElementById('notificationClientEmpty');
    const markAll = document.getElementById('markAllNotificationsRead');
    const unreadCardValue = document.querySelector('#notificationUnreadCount h2');

    if (!form || !list) {
        return;
    }

    const search = form.querySelector('input[name="search"]');
    const moduleFilter = document.getElementById('notificationModuleFilter');
    const typeFilter = document.getElementById('notificationTypeFilter');
    const stateFilter = document.getElementById('notificationStateFilter');
    const rows = () => Array.from(list.querySelectorAll('[data-notification-item]'));
    let timer = null;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const updateEmptyState = () => {
        const visible = rows().filter((item) => !item.hidden).length;
        if (empty) {
            empty.hidden = visible !== 0;
        }
    };

    const applyFilters = () => {
        const query = String(search?.value || '').trim().toLowerCase();
        const moduleValue = String(moduleFilter?.value || 'all').toLowerCase();
        const typeValue = String(typeFilter?.value || 'all').toLowerCase();
        const stateValue = String(stateFilter?.value || 'all').toLowerCase();

        rows().forEach((item) => {
            const haystack = item.textContent.toLowerCase();
            const itemModule = String(item.dataset.module || '').toLowerCase();
            const itemType = String(item.dataset.type || '').toLowerCase();
            const itemState = String(item.dataset.state || 'read').toLowerCase();

            item.hidden = !(
                (!query || haystack.includes(query))
                && (moduleValue === 'all' || itemModule === moduleValue)
                && (typeValue === 'all' || itemType === typeValue)
                && (stateValue === 'all' || itemState === stateValue)
            );
        });

        if (loading) loading.hidden = true;
        updateEmptyState();
    };

    const scheduleFilter = () => {
        if (loading) loading.hidden = false;
        window.clearTimeout(timer);
        timer = window.setTimeout(applyFilters, 100);
    };

    search?.addEventListener('input', scheduleFilter);
    moduleFilter?.addEventListener('change', scheduleFilter);
    typeFilter?.addEventListener('change', scheduleFilter);
    stateFilter?.addEventListener('change', scheduleFilter);

    const markItemRead = (item) => {
        item.classList.remove('unread');
        item.dataset.state = 'read';
        item.querySelector('.unread-dot')?.remove();
        item.querySelector('.notification-mark-read')?.remove();
    };

    const post = async (url) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Notification update failed.');
        }

        return response.json();
    };

    const refreshUnreadCount = async () => {
        const summaryUrl = markAll?.dataset.summaryUrl;
        if (!summaryUrl) return;

        const response = await fetch(summaryUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) return;

        const data = await response.json();
        const unreadCount = Number(data.unread_count || 0);

        if (unreadCardValue) {
            unreadCardValue.textContent = String(unreadCount);
        }

        if (markAll) {
            markAll.disabled = unreadCount === 0;
        }
    };

    markAll?.addEventListener('click', async () => {
        if (markAll.disabled) return;

        markAll.disabled = true;
        markAll.classList.add('is-loading');

        try {
            await post(markAll.dataset.url);
            rows().forEach(markItemRead);
            await refreshUnreadCount();
            window.dispatchEvent(new CustomEvent('topbar-summary-refresh'));
            applyFilters();
        } catch (error) {
            markAll.disabled = false;
        } finally {
            markAll.classList.remove('is-loading');
        }
    });

    document.addEventListener('click', async (event) => {
        const readButton = event.target.closest('.notification-mark-read');

        if (readButton) {
            const item = readButton.closest('[data-notification-item]');
            readButton.disabled = true;

            try {
                await post(readButton.dataset.readUrl);
                if (item) markItemRead(item);
                await refreshUnreadCount();
                window.dispatchEvent(new CustomEvent('topbar-summary-refresh'));
                applyFilters();
            } catch (error) {
                readButton.disabled = false;
            }
            return;
        }

        const viewButton = event.target.closest('.open-notification-modal');
        if (!viewButton) return;

        document.getElementById('notificationModalTitle').textContent = viewButton.dataset.title || 'Notification Details';
        document.getElementById('notificationModalMessage').textContent = viewButton.dataset.message || '—';
        document.getElementById('notificationModalModule').textContent = viewButton.dataset.module || '—';
        document.getElementById('notificationModalType').textContent = viewButton.dataset.type || '—';
        document.getElementById('notificationModalReference').textContent = viewButton.dataset.reference || '—';
        document.getElementById('notificationModalDateTime').textContent = `${viewButton.dataset.date || '—'} ${viewButton.dataset.time || ''}`;
        document.getElementById('notificationDetailsModal')?.classList.add('show');
    });

    const closeModal = () => document.getElementById('notificationDetailsModal')?.classList.remove('show');
    document.getElementById('closeNotificationModal')?.addEventListener('click', closeModal);
    document.getElementById('closeNotificationModalFooter')?.addEventListener('click', closeModal);
    document.getElementById('notificationDetailsModal')?.addEventListener('click', (event) => {
        if (event.target.id === 'notificationDetailsModal') closeModal();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeModal();
    });

    applyFilters();
});
