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
      </div>
    </div>
  </div>
</header>
