window.GCTPartialNavigation.registerInitializer('admin-notifications', '.notifications-page', () => {
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
    // Keep controls synchronized with server-scoped category tabs.
    const selectedParams = new URLSearchParams(window.location.search);
    if (moduleFilter) moduleFilter.value = selectedParams.get('module') || 'all';
    if (typeFilter) typeFilter.value = selectedParams.get('type') || 'all';
    if (stateFilter) stateFilter.value = selectedParams.get('state') || 'all';

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

    document.addEventListener('system:record-rows-loaded', (event) => {
        if (event.detail?.list === list) {
            applyFilters();
        }
    });

    const markItemRead = (item) => {
        item.classList.remove('unread');
        item.dataset.state = 'read';
        item.querySelector('.unread-dot')?.remove();
        item.querySelector('.notification-mark-read')?.remove();
        const stateBadge = item.querySelector('.notification-read-state');
        if (stateBadge) {
            stateBadge.dataset.statusKey = 'read';
            const label = stateBadge.querySelector('.status-badge__label');
            if (label) label.textContent = 'Read';
            const icon = stateBadge.querySelector('.status-badge__icon');
            if (icon) icon.className = 'fa-solid fa-check status-badge__icon';
        }
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

    const modal = document.getElementById('notificationDetailsModal');
    const modalMarkRead = document.getElementById('notificationModalMarkRead');
    const modalError = document.getElementById('notificationModalError');
    let currentItem = null;
    let currentReadUrl = null;
    let previousFocus = null;

    const closeModal = () => {
        modal?.classList.remove('show');
        modal?.classList.remove('notification-modal-open');
        previousFocus?.focus?.();
        currentItem = null;
        currentReadUrl = null;
    };

    const updateModalReadState = () => {
        const unread = currentItem?.dataset.state === 'unread';
        const statusText = document.getElementById('notificationModalStatusText');
        const statusBadge = document.getElementById('notificationModalStatusBadge');
        if (statusText) statusText.textContent = unread
            ? 'This notification has not yet been marked as read.'
            : 'This notification has already been marked as read.';
        if (statusBadge) statusBadge.textContent = unread ? 'Unread' : 'Read';
        statusBadge?.classList.toggle('is-read', !unread);
        if (modalMarkRead) modalMarkRead.hidden = !unread;
    };

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
        previousFocus = viewButton;
        currentItem = viewButton.closest('[data-notification-item]');
        currentReadUrl = viewButton.dataset.readUrl;
        if (modalError) modalError.hidden = true;
        document.getElementById('notificationModalTitle').textContent = viewButton.dataset.title || 'Notification Details';
        document.getElementById('notificationModalMessage').textContent = viewButton.dataset.message || '—';
        document.getElementById('notificationModalModule').textContent = viewButton.dataset.module || '—';
        document.getElementById('notificationModalType').textContent = viewButton.dataset.type || '—';
        document.getElementById('notificationModalTypeBadge').textContent = viewButton.dataset.type || 'Update';
        document.getElementById('notificationModalReference').textContent = viewButton.dataset.reference || '—';
        document.getElementById('notificationModalDateTime').textContent = `${viewButton.dataset.date || '—'} ${viewButton.dataset.time || ''}`;
        updateModalReadState();
        modal?.classList.add('show');
        document.getElementById('closeNotificationModal')?.focus();
    });

    modalMarkRead?.addEventListener('click', async () => {
        if (!currentItem || !currentReadUrl || currentItem.dataset.state !== 'unread') return;
        modalMarkRead.disabled = true;
        if (modalError) modalError.hidden = true;
        try {
            await post(currentReadUrl);
            markItemRead(currentItem);
            updateModalReadState();
            await refreshUnreadCount();
            window.dispatchEvent(new CustomEvent('topbar-summary-refresh'));
            applyFilters();
        } catch (error) {
            if (modalError) modalError.hidden = false;
        } finally {
            modalMarkRead.disabled = false;
        }
    });

    document.getElementById('closeNotificationModal')?.addEventListener('click', closeModal);
    document.getElementById('closeNotificationModalFooter')?.addEventListener('click', closeModal);
    document.addEventListener('keydown', (event) => {
        if (!modal?.classList.contains('show')) return;
        if (event.key === 'Escape') closeModal();
        if (event.key === 'Tab') {
            const controls = Array.from(modal.querySelectorAll('button:not([disabled]):not([hidden])'))
                .filter((button) => button.getClientRects().length);
            if (!controls.length) return;
            const first = controls[0], last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    applyFilters();
});
