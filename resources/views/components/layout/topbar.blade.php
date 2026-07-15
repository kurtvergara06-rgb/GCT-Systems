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

  <div class="top-actions" id="topbarActions">

    {{-- NOTIFICATIONS --}}
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

        <span
          id="notificationBadge"
          class="topbar-badge"
          @if(!$notificationCount) hidden @endif
        >
          {{ $notificationCount ?? 0 }}
        </span>
      </button>

      <div
        id="notificationsDropdown"
        class="topbar-dropdown"
        hidden
      >
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

        <div
          class="topbar-dropdown-body"
          id="notificationsList"
        >
          <div class="topbar-empty-state">
            <div class="topbar-empty-icon">
              <i class="fa-regular fa-bell"></i>
            </div>

            <strong>No notifications yet</strong>

            <p>
              Important updates from your department will appear here.
            </p>
          </div>
        </div>
      </div>
    </div>

    {{-- PENDING ACTIONS --}}
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

      <div
        id="pendingActionsDropdown"
        class="topbar-dropdown"
        hidden
      >
        <div class="topbar-dropdown-header">
          <div>
            <h3>Pending Actions</h3>
            <p>Items that require your attention</p>
          </div>
        </div>

        <div
          class="topbar-dropdown-body"
          id="pendingActionsList"
        >
          <div class="topbar-empty-state">
            <div class="topbar-empty-icon">
              <i class="fa-solid fa-list-check"></i>
            </div>

            <strong>No pending actions</strong>

            <p>
              Tasks that require action will appear here.
            </p>
          </div>
        </div>
      </div>
    </div>

    {{-- RECENT ACTIVITY --}}
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

      <div
        id="recentActivityDropdown"
        class="topbar-dropdown"
        hidden
      >
        <div class="topbar-dropdown-header">
          <div>
            <h3>Recent Activity</h3>
            <p>Latest important system changes</p>
          </div>
        </div>

        <div
          class="topbar-dropdown-body"
          id="recentActivityList"
        >
          <div class="topbar-empty-state">
            <div class="topbar-empty-icon">
              <i class="fa-solid fa-clock-rotate-left"></i>
            </div>

            <strong>No recent activity yet</strong>

            <p>
              Recent changes made in the system will appear here.
            </p>
          </div>
        </div>
      </div>
    </div>

  </div>
</header>

<style>
  .topbar-heading {
    min-width: 0;
  }

  .top-actions {
    position: relative;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .topbar-action-item {
    position: relative;
  }

  .topbar-action-item .icon-btn {
    position: relative;
  }

  .topbar-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border: 2px solid #ffffff;
    border-radius: 999px;
    background: #dc2626;
    color: #ffffff;
    font-size: 10px;
    font-weight: 800;
    line-height: 14px;
    text-align: center;
  }

  .topbar-badge[hidden] {
    display: none;
  }

  .topbar-dropdown {
    position: absolute;
    top: calc(100% + 14px);
    right: 0;
    z-index: 9000;
    width: 350px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    background: #ffffff;
    box-shadow:
      0 18px 45px rgba(15, 23, 42, 0.16),
      0 4px 12px rgba(15, 23, 42, 0.08);
  }

  .topbar-dropdown[hidden] {
    display: none;
  }

  .topbar-dropdown.is-open {
    display: block;
    animation: topbarDropdownOpen 160ms ease-out;
  }

  @keyframes topbarDropdownOpen {
    from {
      opacity: 0;
      transform: translateY(-6px);
    }

    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .topbar-dropdown-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    padding: 18px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
  }

  .topbar-dropdown-header h3 {
    margin: 0;
    color: #0f172a;
    font-size: 16px;
    font-weight: 800;
  }

  .topbar-dropdown-header p {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 12px;
  }

  .topbar-text-button {
    padding: 0;
    border: 0;
    background: transparent;
    color: #0b40b5;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
  }

  .topbar-text-button:hover:not(:disabled) {
    text-decoration: underline;
  }

  .topbar-text-button:disabled {
    color: #94a3b8;
    cursor: not-allowed;
  }

  .topbar-dropdown-body {
    max-height: 380px;
    overflow-y: auto;
  }

  .topbar-empty-state {
    display: flex;
    min-height: 230px;
    padding: 28px 24px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
  }

  .topbar-empty-icon {
    display: flex;
    width: 54px;
    height: 54px;
    margin-bottom: 14px;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #eaf1fb;
    color: #0b40b5;
    font-size: 21px;
  }

  .topbar-empty-state strong {
    color: #0f172a;
    font-size: 14px;
  }

  .topbar-empty-state p {
    max-width: 245px;
    margin: 7px 0 0;
    color: #64748b;
    font-size: 12px;
    line-height: 1.55;
  }

  @media (max-width: 700px) {
    .topbar-dropdown {
      position: fixed;
      top: 78px;
      right: 14px;
      left: 14px;
      width: auto;
    }

    .top-actions {
      gap: 6px;
    }
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const topbarActions =
      document.getElementById('topbarActions');

    if (!topbarActions) {
      return;
    }

    const toggles =
      topbarActions.querySelectorAll(
        '.topbar-dropdown-toggle'
      );

    const dropdowns =
      topbarActions.querySelectorAll(
        '.topbar-dropdown'
      );

    function closeAllTopbarDropdowns(
      exceptDropdownId = null
    ) {
      dropdowns.forEach(function (dropdown) {
        if (
          exceptDropdownId &&
          dropdown.id === exceptDropdownId
        ) {
          return;
        }

        dropdown.hidden = true;
        dropdown.classList.remove('is-open');
      });

      toggles.forEach(function (toggle) {
        if (
          exceptDropdownId &&
          toggle.dataset.dropdownTarget ===
            exceptDropdownId
        ) {
          return;
        }

        toggle.setAttribute(
          'aria-expanded',
          'false'
        );
      });
    }

    toggles.forEach(function (toggle) {
      toggle.addEventListener(
        'click',
        function (event) {
          event.stopPropagation();

          const dropdownId =
            toggle.dataset.dropdownTarget;

          const dropdown =
            document.getElementById(dropdownId);

          if (!dropdown) {
            return;
          }

          const isCurrentlyOpen =
            !dropdown.hidden;

          closeAllTopbarDropdowns(dropdownId);

          if (isCurrentlyOpen) {
            dropdown.hidden = true;
            dropdown.classList.remove(
              'is-open'
            );

            toggle.setAttribute(
              'aria-expanded',
              'false'
            );

            return;
          }

          dropdown.hidden = false;
          dropdown.classList.add('is-open');

          toggle.setAttribute(
            'aria-expanded',
            'true'
          );
        }
      );
    });

    dropdowns.forEach(function (dropdown) {
      dropdown.addEventListener(
        'click',
        function (event) {
          event.stopPropagation();
        }
      );
    });

    document.addEventListener(
      'click',
      function () {
        closeAllTopbarDropdowns();
      }
    );

    document.addEventListener(
      'keydown',
      function (event) {
        if (event.key === 'Escape') {
          closeAllTopbarDropdowns();
        }
      }
    );
  });
</script>