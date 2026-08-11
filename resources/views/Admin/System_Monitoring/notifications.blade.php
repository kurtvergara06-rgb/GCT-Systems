<x-layout.app
    title="FROMS - Notifications"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/System_Monitoring/notifications.css',
        'resources/css/Admin/System_Monitoring/notifications-components.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Admin/System_Monitoring/notifications.js',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main notifications-page records-page">
            <x-layout.topbar
                title="Notifications"
                subtitle="Monitor important alerts and system updates across FROMS"
                notification-count="{{ $unreadNotifications ?? 0 }}"
            />

            <x-ui.ajax-region name="summary" class="stats-grid notification-stats-grid">
                <x-ui.summary-card
                    id="notificationUnreadCount"
                    label="Unread"
                    value="{{ $unreadNotifications ?? 0 }}"
                    small="Notifications requiring review"
                    icon="fa-envelope"
                    color="blue"
                />

                <x-ui.summary-card
                    label="Critical Alerts"
                    value="{{ $criticalAlerts ?? 0 }}"
                    small="Requires immediate attention"
                    icon="fa-triangle-exclamation"
                    color="red"
                />

                <x-ui.summary-card
                    label="System Updates"
                    value="{{ $systemUpdates ?? 0 }}"
                    small="Recent system events"
                    icon="fa-gears"
                    color="yellow"
                />

                <x-ui.summary-card
                    label="Total Notifications"
                    value="{{ $totalNotifications ?? 0 }}"
                    small="Recorded notifications"
                    icon="fa-bell"
                    color="green"
                />
            </x-ui.ajax-region>

            <x-ui.ajax-region name="records" class="table-card notification-card records-card">
                <x-ui.section-header
                    title="Notification Center"
                    subtitle="Review system alerts, departmental updates, requests, and important events."
                >
                    <x-slot:actions>
                        <button
                            type="button"
                            class="mark-all-btn"
                            id="markAllNotificationsRead"
                            data-url="{{ route('topbar.notifications.read-all') }}"
                            data-summary-url="{{ route('topbar.summary') }}"
                            @if(($unreadNotifications ?? 0) === 0) disabled @endif
                        >
                            <i class="fa-solid fa-check-double"></i>
                            Mark All as Read
                        </button>
                    </x-slot:actions>
                </x-ui.section-header>

                <x-ui.table-toolbar
                    :action="route('admin.notifications')"
                    class="notification-toolbar records-toolbar"
                    search-placeholder="Search notifications..."
                    :show-button="false"
                    id="notificationFilterForm"
                    data-client-filter="true"
                    data-no-loading
                >
                    <div class="filter-group">
                        <select id="notificationModuleFilter" name="module" aria-label="Filter by module">
                            <option value="all">All Modules</option>
                            @foreach(($modules ?? []) as $module)
                                <option value="{{ $module }}">{{ $module }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <select id="notificationTypeFilter" name="type" aria-label="Filter by notification type">
                            <option value="all">All Types</option>
                            <option value="Critical">Critical</option>
                            <option value="Warning">Warning</option>
                            <option value="Update">Update</option>
                            <option value="Success">Success</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <select id="notificationStateFilter" name="state" aria-label="Filter by read status">
                            <option value="all">All Notifications</option>
                            <option value="unread">Unread</option>
                            <option value="read">Read</option>
                        </select>
                    </div>
                </x-ui.table-toolbar>

                <div id="notificationListLoading" class="notification-list-loading" hidden>
                    <x-ui.spinner size="sm" label="Filtering notifications" />
                    <span>Filtering notifications...</span>
                </div>

                <div class="notification-list" id="notificationList">
                    @forelse(($notifications ?? []) as $notification)
                        <x-admin.notification-item :notification="$notification" />
                    @empty
                        <x-ui.empty-state
                            icon="fa-bell-slash"
                            title="No notifications"
                            description="System notifications will appear here when new events are recorded."
                        />
                    @endforelse

                    <div id="notificationClientEmpty" class="notification-client-empty" hidden>
                        <x-ui.empty-state
                            icon="fa-filter-circle-xmark"
                            title="No matching notifications"
                            description="Try changing the search or filters."
                        />
                    </div>
                </div>

                @if(isset($notifications) && method_exists($notifications, 'links'))
                    <x-ui.table-footer :items="$notifications" />
                @endif
            </x-ui.ajax-region>
        </main>
    </div>

    <div class="activity-modal-overlay" id="notificationDetailsModal">
        <div class="activity-modal notification-details-modal">
            <div class="activity-modal-header">
                <div class="modal-heading">
                    <div class="modal-icon"><i class="fa-solid fa-bell"></i></div>
                    <div>
                        <h2 id="notificationModalTitle">Notification Details</h2>
                        <p>Complete information about the selected notification.</p>
                    </div>
                </div>
                <button type="button" id="closeNotificationModal" class="modal-close-btn">&times;</button>
            </div>

            <div class="activity-modal-body">
                <div class="modal-info-grid">
                    <div class="modal-info-item"><span>Module</span><strong id="notificationModalModule">—</strong></div>
                    <div class="modal-info-item"><span>Type</span><strong id="notificationModalType">—</strong></div>
                    <div class="modal-info-item"><span>Reference</span><strong id="notificationModalReference">—</strong></div>
                    <div class="modal-info-item"><span>Date & Time</span><strong id="notificationModalDateTime">—</strong></div>
                </div>

                <div class="modal-detail-block">
                    <span>Message</span>
                    <p id="notificationModalMessage">—</p>
                </div>
            </div>

            <div class="activity-modal-footer">
                <button type="button" id="closeNotificationModalFooter" class="modal-done-btn">Close</button>
            </div>
        </div>
    </div>
</x-layout.app>
