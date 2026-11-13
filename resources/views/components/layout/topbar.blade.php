@props([
  'title' => 'Page Title',
  'subtitle' => '',
  'notificationCount' => 0,
])

<header class="topbar">
  <div class="topbar-heading">
    <h1>{{ $title }}</h1>

    @if($subtitle)
      <p>{{ $subtitle }}</p>
    @endif
  </div>

  <div
    class="top-actions"
    id="topbarActions"
    data-summary-url="{{ route('topbar.summary', [], false) }}"
    data-read-all-url="{{ route('topbar.notifications.read-all', [], false) }}"
    data-history-url="{{ route('topbar.notifications.history', [], false) }}"
  >
    <div class="topbar-action-item">
      <button
        type="button"
        class="icon-btn notification topbar-dropdown-toggle"
        data-dropdown-target="notificationsDropdown"
        aria-label="Notifications"
        aria-expanded="false"
        title="Notifications"
      >
        <i class="fa-regular fa-bell"></i>
        <span id="notificationBadge" class="topbar-badge" hidden>0</span>
      </button>

      <div id="notificationsDropdown" class="topbar-dropdown" hidden>
        <div class="topbar-dropdown-header">
          <div>
            <h3>Notifications</h3>
            <p>Important system updates</p>
          </div>

          <button
            type="button"
            class="topbar-text-button"
            id="markAllNotificationsRead"
            disabled
          >
            Mark all as read
          </button>
        </div>

        <div class="topbar-dropdown-body" id="notificationsList">
          <div class="topbar-empty-state">
            <div class="topbar-empty-icon">
              <i class="fa-regular fa-bell"></i>
            </div>
            <strong>No notifications yet</strong>
            <p>Important updates from your department will appear here.</p>
          </div>
        </div>
        <div class="topbar-dropdown-footer"><button type="button" class="topbar-view-all" data-topbar-history="notifications">View all notifications</button></div>
      </div>
    </div>

    <div class="topbar-action-item">
      <button
        type="button"
        class="icon-btn topbar-dropdown-toggle"
        data-dropdown-target="pendingActionsDropdown"
        aria-label="Pending Actions"
        aria-expanded="false"
        title="Pending Actions"
      >
        <i class="fa-solid fa-list-check"></i>
      </button>

      <div id="pendingActionsDropdown" class="topbar-dropdown" hidden>
        <div class="topbar-dropdown-header">
          <div>
            <h3>Pending Actions</h3>
            <p>Items that require your attention</p>
          </div>
        </div>

        <div class="topbar-dropdown-body" id="pendingActionsList">
          <div class="topbar-empty-state">
            <div class="topbar-empty-icon">
              <i class="fa-solid fa-list-check"></i>
            </div>
            <strong>No pending actions</strong>
            <p>Tasks that require action will appear here.</p>
          </div>
        </div>
        <div class="topbar-dropdown-footer"><button type="button" class="topbar-view-all" id="showAllPendingActions" hidden>View all pending actions</button></div>
      </div>
    </div>

    <div class="topbar-action-item">
      <button
        type="button"
        class="icon-btn topbar-dropdown-toggle"
        data-dropdown-target="recentActivityDropdown"
        aria-label="Recent Activity"
        aria-expanded="false"
        title="Recent Activity"
      >
        <i class="fa-solid fa-clock-rotate-left"></i>
      </button>

      <div id="recentActivityDropdown" class="topbar-dropdown" hidden>
        <div class="topbar-dropdown-header">
          <div>
            <h3>Recent Activity</h3>
            <p>Latest important system changes</p>
          </div>
        </div>

        <div class="topbar-dropdown-body" id="recentActivityList">
          <div class="topbar-empty-state">
            <div class="topbar-empty-icon">
              <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <strong>No recent activity yet</strong>
            <p>Recent changes made in the system will appear here.</p>
          </div>
        </div>
        <div class="topbar-dropdown-footer"><button type="button" class="topbar-view-all" data-topbar-history="activity">View all activity</button></div>
      </div>
    </div>
  </div>
  <div class="topbar-history-backdrop" id="topbarHistoryDialog" role="dialog" aria-modal="true" aria-labelledby="topbarHistoryTitle" hidden>
    <section class="topbar-history-panel" tabindex="-1">
      <div class="topbar-history-header">
        <div>
          <h2 id="topbarHistoryTitle">Notification History</h2>
          <p>Browse your department's recorded updates.</p>
        </div>
        <button type="button" class="topbar-history-close" id="topbarHistoryClose" aria-label="Close notification history"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="topbar-history-controls" role="group" aria-label="Notification history filters">
        <button type="button" class="is-active" data-history-state="all" aria-pressed="true">All</button>
        <button type="button" data-history-state="unread" aria-pressed="false">Unread</button>
      </div>
      <div class="topbar-history-list" id="topbarHistoryList" aria-live="polite"></div>
      <div class="topbar-history-footer">
        <span id="topbarHistoryCount">Showing notifications</span>
        <div>
          <button type="button" class="topbar-history-nav" id="topbarHistoryPrevious">Previous</button>
          <button type="button" class="topbar-history-nav" id="topbarHistoryNext">Next</button>
        </div>
      </div>
    </section>
  </div>
</header>
