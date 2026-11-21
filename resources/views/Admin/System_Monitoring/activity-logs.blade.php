<x-layout.app
    title="FROMS - System Activity"
    :assets="[
        'resources/css/Admin/System_Monitoring/activity-logs.css',
        'resources/js/Admin/System_Monitoring/activity-logs.js',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main activity-logs-page">
            <x-layout.topbar
                title="System Activity"
                subtitle="Track administrative activity, user actions, and notification-related system events."
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
                            {{ number_format($logs->total()) }} {{ $logs->total() === 1 ? 'Activity' : 'Activities' }}
                        </span>
                    </x-slot:actions>
                </x-ui.section-header>

                <x-ui.table-toolbar
                    :action="route('admin.activity-logs')"
                    class="activity-toolbar"
                    search-placeholder="Search user, activity, reference..."
                    search-label="Search Activities"
                    filter-title="Filter Activities"
                    :filter-count="(int) (request()->filled('module') && request('module') !== 'all') + (int) (request()->filled('event') && request('event') !== 'all') + (int) (request()->filled('date') && request('date') !== 'all')"
                    :sort-options="['created_at' => 'Date & Time', 'user_name' => 'User', 'activity' => 'Activity', 'module' => 'Module', 'event_type' => 'Event Type']"
                    default-sort="created_at"
                    default-direction="desc"
                    :show-button="false"
                    id="activityFilterForm"
                    data-activity-filter-form
                    data-server-filter="true"
                    data-server-filter-owned="true"
                    data-no-loading
                    autocomplete="off"
                >
                    <x-slot:filters>
                        <x-ui.table-toolbar-field label="Module" name="module" default="all" id="activityModuleFilter" autocomplete="off">
                            <option value="all">All Modules</option>
                            @foreach(($modules ?? collect()) as $module)
                                <option value="{{ $module }}" @selected(request('module') === $module)>{{ $module }}</option>
                            @endforeach
                        </x-ui.table-toolbar-field>

                        <x-ui.table-toolbar-field label="Event Type" name="event" default="all" id="activityEventFilter" autocomplete="off">
                            <option value="all">All Events</option>
                            @foreach(($events ?? collect()) as $event)
                                <option value="{{ $event }}" @selected(request('event') === $event)>{{ $event }}</option>
                            @endforeach
                        </x-ui.table-toolbar-field>

                        <x-ui.table-toolbar-field label="Date Range" name="date" default="all" id="activityDateFilter" autocomplete="off">
                            <option value="all" @selected(request('date', 'all') === 'all')>All Time</option>
                            <option value="today" @selected(request('date') === 'today')>Today</option>
                            <option value="week" @selected(request('date') === 'week')>This Week</option>
                            <option value="month" @selected(request('date') === 'month')>This Month</option>
                        </x-ui.table-toolbar-field>
                    </x-slot:filters>
                </x-ui.table-toolbar>

                <div id="activityTableLoading" class="activity-table-loading" hidden>
                    <x-ui.spinner size="sm" label="Loading activity logs" />
                    <span>Loading activity records...</span>
                </div>

                <div class="table-wrap activity-table-wrap gct-responsive-records gct-sticky-actions">
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
                                    $profilePhotoUrl = $log->user?->profilePhotoUrl();
                                    $moduleClass = strtolower(str_replace(' ', '-', $log->module ?? 'system'));
                                    $eventClass = strtolower(str_replace([' ', '/'], '-', $log->event_type ?? 'updated'));
                                    $createdAt = $log->created_at;
                                @endphp

                                <tr data-activity-row>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar">
                                                @if($profilePhotoUrl)
                                                    <img
                                                        src="{{ $profilePhotoUrl }}"
                                                        alt=""
                                                        class="activity-user-avatar-image"
                                                        loading="lazy"
                                                        decoding="async"
                                                    >
                                                @else
                                                    <span>{{ $initials }}</span>
                                                @endif
                                            </div>
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
                                                aria-label="View activity details for {{ $log->user_name }}"
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
                                    icon="fa-clock-rotate-left"
                                    message="No Activity Logs Found"
                                    description="No system audit logs match your search or date filter."
                                />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-ui.table-footer :items="$logs" data-lazy-pagination="true" />
            </x-ui.ajax-region>
        </main>
    </div>

    <div id="activityDetailsModal" class="activity-modal-overlay" role="presentation">
        <div class="activity-modal" role="dialog" aria-modal="true" aria-labelledby="activityDetailsTitle" aria-describedby="activityDetailsDescription">
            <div class="activity-modal-header">
                <div class="modal-heading">
                    <div class="modal-icon"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></div>
                    <div>
                        <h2 id="activityDetailsTitle">Activity Details</h2>
                        <p id="activityDetailsDescription">System activity and audit information</p>
                    </div>
                </div>
                <button type="button" id="closeActivityModal" class="modal-close-btn" data-icon-only aria-label="Close activity details">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="activity-modal-body">
                <div class="activity-actor-row">
                    <div class="activity-actor-avatar"><i class="fa-solid fa-user" aria-hidden="true"></i></div>
                    <div class="activity-actor-identity">
                        <strong id="modalUser">—</strong>
                        <span id="modalRole">—</span>
                    </div>
                    <div class="activity-actor-date" id="modalDateTime">—</div>
                </div>

                <section class="activity-action-card" aria-label="Recorded action">
                    <div class="activity-action-top">
                        <span class="activity-action-kicker">RECORDED ACTION</span>
                        <span class="activity-event-chip" id="modalType">—</span>
                    </div>
                    <h3 id="modalActivity">—</h3>
                    <div class="activity-action-meta">
                        <span class="activity-module-chip" id="modalModule">—</span>
                        <span>Reference: <strong id="modalReference">—</strong></span>
                    </div>
                </section>

                <section class="activity-audit-section" aria-labelledby="activityChangesHeading">
                    <div class="activity-modal-section-heading" id="activityChangesHeading"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i><span>Changes Recorded</span></div>
                    <div id="modalChanges" class="activity-change-list" hidden></div>
                    <div id="modalDetailsFallback" class="activity-detail-fallback"><p id="modalDetails">—</p></div>
                </section>

                <section class="activity-audit-section" aria-labelledby="activitySecurityHeading">
                    <div class="activity-modal-section-heading" id="activitySecurityHeading"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>Security Information</span></div>
                    <div class="security-info-grid">
                        <div class="security-info-item">
                            <div class="security-icon"><i class="fa-solid fa-network-wired" aria-hidden="true"></i></div>
                            <div><span>IP Address</span><strong id="modalIp">—</strong></div>
                        </div>
                        <div class="security-info-item">
                            <div class="security-icon"><i class="fa-solid fa-laptop" aria-hidden="true"></i></div>
                            <div>
                                <span>Device / Browser</span>
                                <details class="activity-device-details">
                                    <summary>View device details <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                                    <strong id="modalDevice">—</strong>
                                </details>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="activity-modal-footer">
                <button type="button" id="closeActivityModalFooter" class="modal-done-btn"><i class="fa-solid fa-check" aria-hidden="true"></i> Done</button>
            </div>
        </div>
    </div>
</x-layout.app>
