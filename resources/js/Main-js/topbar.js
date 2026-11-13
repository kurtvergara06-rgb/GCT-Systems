window.GCTPartialNavigation.registerInitializer('shared-topbar', '.topbar', function () {
  const topbarActions = document.getElementById('topbarActions');
  if (!topbarActions) return;

  const passwordChangeRequired = document.querySelector('meta[name="gct-force-password-change"]')?.content === '1';
  if (passwordChangeRequired) return;

  const toggles = topbarActions.querySelectorAll('.topbar-dropdown-toggle');
  const dropdowns = topbarActions.querySelectorAll('.topbar-dropdown');
  const notificationsList = document.getElementById('notificationsList');
  const pendingActionsList = document.getElementById('pendingActionsList');
  const recentActivityList = document.getElementById('recentActivityList');
  const notificationBadge = document.getElementById('notificationBadge');
  const markAllButton = document.getElementById('markAllNotificationsRead');
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const historyDialog = document.getElementById('topbarHistoryDialog');
  const historyList = document.getElementById('topbarHistoryList');
  const historyTitle = document.getElementById('topbarHistoryTitle');
  const historyCount = document.getElementById('topbarHistoryCount');
  const historyPrevious = document.getElementById('topbarHistoryPrevious');
  const historyNext = document.getElementById('topbarHistoryNext');
  const pendingMore = document.getElementById('showAllPendingActions');
  let pendingItems = [];
  let pendingExpanded = false;
  let historyPage = 1;
  let historyLastPage = 1;
  let historyState = 'all';
  let historyMode = 'notifications';
  let historyRequestId = 0;
  let historyReturnFocus = null;

  let summaryLoaded = false;
  let summaryLoading = false;
  let summaryWarned = false;

  function escapeHtml(value) {
    return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
  }

  function emptyState(icon, title, message) {
    return `<div class="topbar-empty-state"><div class="topbar-empty-icon"><i class="${escapeHtml(icon)}"></i></div><strong>${escapeHtml(title)}</strong><p>${escapeHtml(message)}</p></div>`;
  }

  function notificationTone(item) {
    const action = String(item.action || '').toLowerCase();
    const message = String(item.message || '').toLowerCase();
    if (/cancel|reject|fail|breakdown|accident|critical/.test(action + ' ' + message)) return 'critical';
    if (/pending|review|hold|attention|await/.test(action + ' ' + message)) return 'warning';
    if (/complete|approv|resolv|success|created|added/.test(action + ' ' + message)) return 'success';
    return 'info';
  }

  function notificationTitle(item, isActivity = false) {
    if (isActivity) return 'System Activity';
    const action = String(item.action || '').toLowerCase();
    const message = String(item.message || '').toLowerCase();
    if (/cancel/.test(action + ' ' + message)) return 'Trip Cancelled';
    if (/status.*updat|status.*advanc/.test(action + ' ' + message)) return 'Trip Status Updated';
    if (/breakdown|incident/.test(action + ' ' + message)) return 'Incident Update';
    if (/approv/.test(action + ' ' + message)) return 'Request Approved';
    if (/reject/.test(action + ' ' + message)) return 'Request Rejected';
    if (/review|pending/.test(action + ' ' + message)) return 'Action Required';
    return 'System Update';
  }

  function renderNotificationItem(item, isActivity = false) {
    const tag = item.url ? 'a' : 'div';
    const href = item.url ? ` href="${escapeHtml(item.url)}"` : '';
    const tone = notificationTone(item);
    const icon = isActivity ? 'fa-clock-rotate-left'
      : ({ critical: 'fa-circle-exclamation', warning: 'fa-triangle-exclamation',
           success: 'fa-circle-check', info: 'fa-bell' })[tone];
    return `<${tag}${href} class="topbar-list-item${!isActivity && item.unread ? ' is-unread' : ''}" data-tone="${tone}">
      <span class="topbar-list-icon"><i class="fa-solid ${icon}" aria-hidden="true"></i></span>
      <span class="topbar-list-content">
        <span class="topbar-list-heading">${escapeHtml(notificationTitle(item, isActivity))}</span>
        <span class="topbar-list-title">${escapeHtml(item.message)}</span>
        <span class="topbar-list-meta">${escapeHtml(item.module)} · ${escapeHtml(item.time)}</span>
      </span>${!isActivity && item.unread ? '<span class="topbar-unread-dot" aria-label="Unread"></span>' : ''}
    </${tag}>`;
  }

  function renderNotifications(items, container, isActivity = false) {
    if (!container) return;
    if (!Array.isArray(items) || items.length === 0) {
      container.innerHTML = emptyState(isActivity ? 'fa-solid fa-clock-rotate-left' : 'fa-regular fa-bell', isActivity ? 'No recent activity yet' : 'No notifications yet', isActivity ? 'Recent changes made in the system will appear here.' : 'Important system updates will appear here.');
      return;
    }
    // Preview only: history is loaded separately and paginated server-side.
    container.innerHTML = items.slice(0, 5).map(item => renderNotificationItem(item, isActivity)).join('');
  }

  function pendingDescription(item) {
    const count = Math.max(0, Number(item.count) || 0);
    const single = count === 1;
    const label = String(item.label || '').toLowerCase();
    if (label.includes('trips awaiting assignment'))
      return single ? '1 scheduled trip requires driver and bus assignments.' : `${count} scheduled trips require driver and bus assignments.`;
    if (label.includes('buses under maintenance'))
      return single ? '1 bus is currently undergoing maintenance.' : `${count} buses are currently undergoing maintenance.`;
    if (label.includes('active incidents'))
      return single ? '1 incident requires operational attention.' : `${count} incidents require operational attention.`;
    if (label.includes('pending user accounts'))
      return single ? '1 user account requires review.' : `${count} user accounts require review.`;
    if (label.includes('low-stock inventory'))
      return single ? '1 inventory item has reached its reorder level.' : `${count} inventory items have reached their reorder levels.`;
    if (label.includes('review') || label.includes('approval'))
      return single ? `1 item requires review: ${item.label}.` : `${count} items require review: ${item.label}.`;
    return single ? `1 record requires attention: ${item.label}.` : `${count} records require attention: ${item.label}.`;
  }

  function renderPendingActions(items) {
    if (!pendingActionsList) return;
    pendingItems = Array.isArray(items) ? items : [];
    if (pendingItems.length === 0) {
      pendingActionsList.innerHTML = emptyState('fa-solid fa-list-check', 'No pending actions', 'There are no pending records for your department.');
      if (pendingMore) pendingMore.hidden = true;
      return;
    }

    pendingActionsList.innerHTML = pendingItems.slice(0, pendingExpanded ? undefined : 5).map(function (item) {
      const tone = /maintenance|review|low.stock|hold|pending|incident/i.test(item.label) ? 'warning' : 'info';
      const count = Number(item.count) || 0;
      const message = pendingDescription(item);
      return `<a href="${escapeHtml(item.url)}" class="topbar-list-item" data-tone="${tone}">
        <span class="topbar-list-icon"><i class="fa-solid ${escapeHtml(item.icon)}" aria-hidden="true"></i></span>
        <span class="topbar-list-content"><span class="topbar-list-heading">${escapeHtml(item.label)}</span>
          <span class="topbar-list-title">${escapeHtml(message)}</span>
          <span class="topbar-list-meta">Open the related page</span></span>
        <span class="topbar-list-count">${escapeHtml(count)}</span></a>`;
    }).join('');
    if (pendingMore) {
      pendingMore.hidden = pendingItems.length <= 5;
      pendingMore.textContent = pendingExpanded ? 'Show fewer actions' : 'View all pending actions';
    }
  }

  function updateBadge(count) {
    const unreadCount = Math.max(0, Number(count) || 0);
    if (!notificationBadge) return;
    notificationBadge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
    notificationBadge.hidden = unreadCount === 0;
    if (markAllButton) markAllButton.disabled = unreadCount === 0;
  }

  async function loadTopbarSummary(force = false) {
    if (summaryLoading || (summaryLoaded && !force)) return;
    summaryLoading = true;

    try {
      const response = await fetch(topbarActions.dataset.summaryUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
      if (!response.ok) throw new Error('Topbar summary could not be loaded.');
      const summary = await response.json();
      updateBadge(summary.unread_count);
      renderNotifications(summary.notifications, notificationsList);
      renderPendingActions(summary.pending_actions);
      renderNotifications(summary.recent_activity, recentActivityList, true);
      summaryLoaded = true;
      window.dispatchEvent(new CustomEvent('topbar-summary-loaded', { detail: summary }));
    } catch (error) {
      console.warn(error);
      if (!summaryWarned) {
        summaryWarned = true;
        if (typeof window.showSystemToast === 'function') {
          window.showSystemToast('Notifications could not be loaded right now.', 'warning', 'Unable to load', { timeout: 6000 });
        }
      }
      window.dispatchEvent(new CustomEvent('topbar-summary-loaded', { detail: null }));
    } finally {
      summaryLoading = false;
    }
  }

  async function loadHistory(page = 1) {
    if (!historyDialog || historyDialog.hidden || !historyList) return;
    const requestId = ++historyRequestId;
    historyList.innerHTML = '<div class="topbar-loading-state">Loading notification history...</div>';
    try {
      const params = new URLSearchParams({ page: String(page), state: historyMode === 'activity' ? 'all' : historyState });
      const response = await fetch(`${topbarActions.dataset.historyUrl}?${params}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!response.ok) throw new Error('History could not be loaded.');
      const history = await response.json();
      if (requestId !== historyRequestId || historyDialog.hidden) return;
      historyPage = history.page;
      historyLastPage = history.last_page;
      historyList.innerHTML = history.items.length
        ? history.items.map(item => renderNotificationItem(item, historyMode === 'activity')).join('')
        : emptyState('fa-solid fa-inbox', 'No matching records', 'There are no notifications in this category.');
      historyCount.textContent = `Page ${historyPage} of ${historyLastPage} · ${history.total} records`;
      historyPrevious.disabled = historyPage <= 1;
      historyNext.disabled = historyPage >= historyLastPage;
    } catch (error) {
      if (requestId !== historyRequestId) return;
      historyList.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Unable to load history', 'Please try again.');
      historyCount.textContent = 'History unavailable';
      historyPrevious.disabled = true;
      historyNext.disabled = true;
    }
  }

  function closeHistory() {
    if (!historyDialog || historyDialog.hidden) return;
    historyDialog.hidden = true;
    historyRequestId++;
    document.body.classList.remove('topbar-history-open');
    historyReturnFocus?.focus?.();
  }

  function openHistory(mode) {
    if (!historyDialog) return;
    historyReturnFocus = document.activeElement;
    historyMode = mode === 'activity' ? 'activity' : 'notifications';
    historyState = 'all';
    historyTitle.textContent = historyMode === 'activity' ? 'Recent Activity History' : 'Notification History';
    historyDialog.hidden = false;
    document.body.classList.add('topbar-history-open');
    const controls = historyDialog.querySelector('.topbar-history-controls');
    controls.hidden = historyMode === 'activity';
    controls.querySelectorAll('[data-history-state]').forEach(button => {
      const active = button.dataset.historyState === 'all';
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', String(active));
    });
    closeAllTopbarDropdowns();
    historyDialog.querySelector('.topbar-history-close')?.focus();
    loadHistory(1);
  }

  topbarActions.querySelectorAll('[data-topbar-history]').forEach(button => {
    button.addEventListener('click', () => openHistory(button.dataset.topbarHistory));
  });
  pendingMore?.addEventListener('click', () => {
    pendingExpanded = !pendingExpanded;
    renderPendingActions(pendingItems);
  });
  historyDialog?.querySelectorAll('[data-history-state]').forEach(button => {
    button.addEventListener('click', () => {
      historyState = button.dataset.historyState;
      historyDialog.querySelectorAll('[data-history-state]').forEach(choice => {
        const active = choice === button;
        choice.classList.toggle('is-active', active);
        choice.setAttribute('aria-pressed', String(active));
      });
      loadHistory(1);
    });
  });
  document.getElementById('topbarHistoryClose')?.addEventListener('click', closeHistory);
  historyPrevious?.addEventListener('click', () => loadHistory(historyPage - 1));
  historyNext?.addEventListener('click', () => loadHistory(historyPage + 1));

  function closeAllTopbarDropdowns(exceptDropdownId = null) {
    dropdowns.forEach(function (dropdown) {
      if (exceptDropdownId && dropdown.id === exceptDropdownId) return;
      dropdown.hidden = true;
      dropdown.classList.remove('is-open');
    });
    toggles.forEach(function (toggle) {
      if (exceptDropdownId && toggle.dataset.dropdownTarget === exceptDropdownId) return;
      toggle.setAttribute('aria-expanded', 'false');
    });
  }

  toggles.forEach(function (toggle) {
    toggle.addEventListener('click', function (event) {
      event.stopPropagation();
      const dropdownId = toggle.dataset.dropdownTarget;
      const dropdown = document.getElementById(dropdownId);
      if (!dropdown) return;
      loadTopbarSummary();
      const isCurrentlyOpen = !dropdown.hidden;
      closeAllTopbarDropdowns(dropdownId);
      if (isCurrentlyOpen) {
        dropdown.hidden = true;
        dropdown.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        return;
      }
      dropdown.hidden = false;
      dropdown.classList.add('is-open');
      toggle.setAttribute('aria-expanded', 'true');
    });
  });

  dropdowns.forEach(function (dropdown) {
    dropdown.addEventListener('click', function (event) { event.stopPropagation(); });
  });

  if (markAllButton) {
    markAllButton.addEventListener('click', async function () {
      if (markAllButton.disabled) return;
      markAllButton.disabled = true;
      try {
        const response = await fetch(topbarActions.dataset.readAllUrl, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({}) });
        if (!response.ok) throw new Error('Notifications could not be marked as read.');
        summaryLoaded = false;
        await loadTopbarSummary(true);
      } catch (error) {
        console.warn(error);
        if (typeof window.showSystemToast === 'function') {
          window.showSystemToast('Notifications could not be marked as read.', 'error', 'Failed', { timeout: 6000 });
        }
        markAllButton.disabled = false;
      }
    });
  }

  window.addEventListener('system-data-updated', function () {
    summaryLoaded = false;
    window.setTimeout(function () { loadTopbarSummary(true); }, 250);
  });

  loadTopbarSummary();
  document.addEventListener('click', function () { closeAllTopbarDropdowns(); });
  document.addEventListener('keydown', function (event) {
    if (historyDialog && !historyDialog.hidden) {
      if (event.key === 'Escape') { closeHistory(); return; }
      if (event.key === 'Tab') {
        const focusables = Array.from(historyDialog.querySelectorAll('button:not([disabled]), a[href]'))
          .filter(element => element.getClientRects().length);
        if (focusables.length) {
          const first = focusables[0];
          const last = focusables[focusables.length - 1];
          if (event.shiftKey && document.activeElement === first) {
            event.preventDefault(); last.focus();
          } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault(); first.focus();
          }
        }
      }
      return;
    }
    if (event.key === 'Escape') closeAllTopbarDropdowns();
  });
});
