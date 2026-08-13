document.addEventListener('DOMContentLoaded', function () {
  const topbarActions = document.getElementById('topbarActions');
  if (!topbarActions) return;

  const toggles = topbarActions.querySelectorAll('.topbar-dropdown-toggle');
  const dropdowns = topbarActions.querySelectorAll('.topbar-dropdown');
  const notificationsList = document.getElementById('notificationsList');
  const pendingActionsList = document.getElementById('pendingActionsList');
  const recentActivityList = document.getElementById('recentActivityList');
  const notificationBadge = document.getElementById('notificationBadge');
  const markAllButton = document.getElementById('markAllNotificationsRead');
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  let summaryLoaded = false;
  let summaryLoading = false;

  function escapeHtml(value) {
    return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
  }

  function emptyState(icon, title, message) {
    return `<div class="topbar-empty-state"><div class="topbar-empty-icon"><i class="${escapeHtml(icon)}"></i></div><strong>${escapeHtml(title)}</strong><p>${escapeHtml(message)}</p></div>`;
  }

  function renderNotifications(items, container, isActivity = false) {
    if (!container) return;
    if (!Array.isArray(items) || items.length === 0) {
      container.innerHTML = emptyState(isActivity ? 'fa-solid fa-clock-rotate-left' : 'fa-regular fa-bell', isActivity ? 'No recent activity yet' : 'No notifications yet', isActivity ? 'Recent changes made in the system will appear here.' : 'Important system updates will appear here.');
      return;
    }

    container.innerHTML = items.map(function (item) {
      const tag = item.url ? 'a' : 'div';
      const href = item.url ? ` href="${escapeHtml(item.url)}"` : '';
      const unreadClass = item.unread ? ' is-unread' : '';
      return `<${tag}${href} class="topbar-list-item${unreadClass}"><span class="topbar-list-icon"><i class="${isActivity ? 'fa-solid fa-clock-rotate-left' : 'fa-regular fa-bell'}"></i></span><span class="topbar-list-content"><span class="topbar-list-title">${escapeHtml(item.message)}</span><span class="topbar-list-meta">${escapeHtml(item.module)} · ${escapeHtml(item.time)}</span></span></${tag}>`;
    }).join('');
  }

  function renderPendingActions(items) {
    if (!pendingActionsList) return;
    if (!Array.isArray(items) || items.length === 0) {
      pendingActionsList.innerHTML = emptyState('fa-solid fa-list-check', 'No pending actions', 'There are no pending records for your department.');
      return;
    }

    pendingActionsList.innerHTML = items.map(function (item) {
      return `<a href="${escapeHtml(item.url)}" class="topbar-list-item"><span class="topbar-list-icon"><i class="fa-solid ${escapeHtml(item.icon)}"></i></span><span class="topbar-list-content"><span class="topbar-list-title">${escapeHtml(item.label)}</span><span class="topbar-list-meta">Open the related page</span></span><span class="topbar-list-count">${escapeHtml(item.count)}</span></a>`;
    }).join('');
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
    } catch (error) {
      console.warn(error);
    } finally {
      summaryLoading = false;
    }
  }

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
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeAllTopbarDropdowns(); });
});
