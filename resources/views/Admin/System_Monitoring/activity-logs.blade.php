<x-layout.app
    title="FROMS - Activity Logs"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/System_Monitoring/activity-logs.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Admin/System_Monitoring/activity-logs.js',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main activity-logs-page">
            <x-layout.topbar
                title="Activity Logs"
                subtitle="Monitor real user and system activities across FROMS"
                notification-count="6"
            />

            <x-ui.ajax-region name="summary" class="stats-grid activity-stats-grid">
                <x-ui.summary-card label="Activities Today" :value="$activitiesToday ?? 0" small="Recorded system events" icon="fa-clock-rotate-left" color="blue" />
                <x-ui.summary-card label="User Actions" :value="$userActions ?? 0" small="Account-based activities" icon="fa-user-check" color="green" />
                <x-ui.summary-card label="System Events" :value="$systemEvents ?? 0" small="Operational system activity" icon="fa-gears" color="yellow" />
                <x-ui.summary-card label="Security Events" :value="$securityEvents ?? 0" small="Login, logout, and account events" icon="fa-shield-halved" color="red" />
            </x-ui.ajax-region>

            <x-ui.ajax-region name="records" class="table-card activity-table-card">
                <x-ui.section-header
                    title="System Activity History"
                    subtitle="Review actions recorded automatically from actual FROMS accounts and modules."
                >
                    <x-slot:actions>
                        <span class="activity-count">
                            {{ $logs->total() }} {{ $logs->total() === 1 ? 'Activity' : 'Activities' }}
                        </span>
                    </x-slot:actions>
                </x-ui.section-header>

                <x-ui.table-toolbar
                    :action="route('admin.activity-logs')"
                    class="activity-toolbar"
                    search-placeholder="Search user, activity, reference..."
                    :show-button="false"
                    id="activityFilterForm"
                    data-activity-filter-form
                    data-no-loading
                    autocomplete="off"
                >
                    <div class="filter-group">
                        <select id="activityModuleFilter" name="module" aria-label="Filter by module" autocomplete="off">
                            <option value="all">All Modules</option>
                            @foreach(($modules ?? collect()) as $module)
                                <option value="{{ $module }}" @selected(request('module') === $module)>
                                    {{ $module }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <select id="activityEventFilter" name="event" aria-label="Filter by event type" autocomplete="off">
                            <option value="all">All Events</option>
                            @foreach(($events ?? collect()) as $event)
                                <option value="{{ $event }}" @selected(request('event') === $event)>
                                    {{ $event }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <select id="activityDateFilter" name="date" aria-label="Filter by date range" autocomplete="off">
                            <option value="all" @selected(request('date', 'all') === 'all')>All Time</option>
                            <option value="today" @selected(request('date') === 'today')>Today</option>
                            <option value="week" @selected(request('date') === 'week')>This Week</option>
                            <option value="month" @selected(request('date') === 'month')>This Month</option>
                        </select>
                    </div>
                </x-ui.table-toolbar>

                <div id="activityTableLoading" class="activity-table-loading" hidden>
                    <x-ui.spinner size="sm" label="Loading activity logs" />
                    <span>Loading activity records...</span>
                </div>

                <div class="table-wrap activity-table-wrap">
                    <table id="activityLogsTable" class="activity-logs-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Activity</th>
                                <th>Module</th>
                                <th>Reference</th>
                                <th>Event Type</th>
                                <th>Date & Time</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                                @php
                                    $nameParts = collect(preg_split('/\s+/', trim($log->user_name ?? '')))
                                        ->filter()
                                        ->values();
                                    $initials = strtoupper(
                                        substr($nameParts->get(0, ''), 0, 1)
                                        . substr($nameParts->get(1, ''), 0, 1)
                                    ) ?: 'U';
                                    $moduleClass = strtolower(str_replace(' ', '-', $log->module ?? 'system'));
                                    $eventClass = strtolower(str_replace([' ', '/'], '-', $log->event_type ?? 'updated'));
                                    $createdAt = $log->created_at;
                                @endphp

                                <tr data-activity-row>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar">{{ $initials }}</div>
                                            <div>
                                                <strong>{{ $log->user_name }}</strong>
                                                <span>{{ $log->user_role ?: ($log->department ?: 'System User') }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="activity-cell">
                                            <strong>{{ $log->activity }}</strong>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="module-badge {{ $moduleClass }}">{{ $log->module }}</span>
                                    </td>
                                    <td>
                                        @if($log->reference)
                                            <span class="reference-code">{{ $log->reference }}</span>
                                        @else
                                            <span class="system-id-empty">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="event-badge {{ $eventClass }}">{{ $log->event_type }}</span>
                                    </td>
                                    <td>
                                        <div class="date-time-cell">
                                            <span class="date-value">{{ $createdAt?->format('M d, Y') ?? '—' }}</span>
                                            <span class="time-value">{{ $createdAt?->format('g:i A') ?? '' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button
                                                type="button"
                                                class="action-btn view open-log-modal"
                                                title="View Activity Details"
                                                data-icon-only
                                                data-user="{{ $log->user_name }}"
                                                data-role="{{ $log->user_role }}"
                                                data-activity="{{ $log->activity }}"
                                                data-module="{{ $log->module }}"
                                                data-reference="{{ $log->reference ?: '—' }}"
                                                data-type="{{ $log->event_type }}"
                                                data-date="{{ $createdAt?->format('M d, Y') ?? '—' }}"
                                                data-time="{{ $createdAt?->format('g:i A') ?? '' }}"
                                                data-details="{{ $log->details ?: 'No additional details recorded.' }}"
                                                data-ip="{{ $log->ip_address ?: '—' }}"
                                                data-device="{{ $log->user_agent ?: '—' }}"
                                            >
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <x-ui.empty-row
                                    :colspan="7"
                                    message="No activity records found for the selected filters."
                                />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-ui.table-footer :items="$logs" />
            </x-ui.ajax-region>
        </main>
    </div>

    <div id="activityDetailsModal" class="activity-modal-overlay">
        <div class="activity-modal">
            <div class="activity-modal-header">
                <div class="modal-heading">
                    <div class="modal-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <div>
                        <h2>Activity Details</h2>
                        <p>Complete information about the selected system activity.</p>
                    </div>
                </div>
                <button type="button" id="closeActivityModal" class="modal-close-btn" data-icon-only aria-label="Close activity details">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="activity-modal-body">
                <div class="modal-info-grid">
                    <div class="modal-info-item"><span>User</span><strong id="modalUser">—</strong></div>
                    <div class="modal-info-item"><span>Role</span><strong id="modalRole">—</strong></div>
                    <div class="modal-info-item"><span>Module</span><strong id="modalModule">—</strong></div>
                    <div class="modal-info-item"><span>Event Type</span><strong id="modalType">—</strong></div>
                    <div class="modal-info-item"><span>Reference</span><strong id="modalReference">—</strong></div>
                    <div class="modal-info-item"><span>Date & Time</span><strong id="modalDateTime">—</strong></div>
                </div>

                <div class="modal-detail-block"><span>Activity</span><strong id="modalActivity">—</strong></div>
                <div class="modal-detail-block"><span>Details</span><p id="modalDetails">—</p></div>

                <div class="security-info-grid">
                    <div class="security-info-item">
                        <div class="security-icon"><i class="fa-solid fa-network-wired"></i></div>
                        <div><span>IP Address</span><strong id="modalIp">—</strong></div>
                    </div>
                    <div class="security-info-item">
                        <div class="security-icon"><i class="fa-solid fa-laptop"></i></div>
                        <div><span>Device / Browser</span><strong id="modalDevice">—</strong></div>
                    </div>
                </div>
            </div>

            <div class="activity-modal-footer">
                <button type="button" id="closeActivityModalFooter" class="modal-done-btn">Close</button>
            </div>
        </div>
    </div>
</x-layout.app>
