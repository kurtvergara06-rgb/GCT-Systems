<x-layout.app
    title="FROMS - Notifications"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/System_Monitoring/notifications.css',
        'resources/js/Main-js/sidebar.js'
    ]"
>

    <div class="app">

        <x-layout.sidebar department="Admin" />

        <main class="main notifications-page">

            <x-layout.topbar
                title="Notifications"
                subtitle="Monitor important alerts and system updates across FROMS"
                notification-count="6"
            />

            {{-- =====================================================
                SUMMARY CARDS
            ====================================================== --}}
            <section data-ajax-region="summary" class="stats-grid notification-stats-grid">

                <x-ui.summary-card
                    label="Unread"
                    value="6"
                    small="Notifications requiring review"
                    icon="fa-envelope"
                    color="blue"
                />

                <x-ui.summary-card
                    label="Critical Alerts"
                    value="2"
                    small="Requires immediate attention"
                    icon="fa-triangle-exclamation"
                    color="red"
                />

                <x-ui.summary-card
                    label="System Updates"
                    value="8"
                    small="Recent system events"
                    icon="fa-gears"
                    color="yellow"
                />

                <x-ui.summary-card
                    label="Total Notifications"
                    value="24"
                    small="Recorded notifications"
                    icon="fa-bell"
                    color="green"
                />

            </section>

            @php
                $notifications = [
                    [
                        'title' => 'Inventory stock has reached critical level',
                        'message' => 'Brake Pad Set is currently below its reorder threshold and requires restocking.',
                        'module' => 'Warehouse',
                        'type' => 'Critical',
                        'reference' => 'PART-0042',
                        'date' => 'Jul 25, 2026',
                        'time' => '9:18 PM',
                        'icon' => 'fa-box-open',
                        'unread' => true,
                    ],
                    [
                        'title' => 'Preventive maintenance is approaching',
                        'message' => 'Bus BUS-012 is approaching its configured PMS mileage threshold.',
                        'module' => 'Maintenance',
                        'type' => 'Warning',
                        'reference' => 'BUS-012',
                        'date' => 'Jul 25, 2026',
                        'time' => '8:40 PM',
                        'icon' => 'fa-screwdriver-wrench',
                        'unread' => true,
                    ],
                    [
                        'title' => 'Purchase order status updated',
                        'message' => 'PO-2026-0015 has been marked For Delivery by the Purchase Department.',
                        'module' => 'Purchase',
                        'type' => 'Update',
                        'reference' => 'PO-2026-0015',
                        'date' => 'Jul 25, 2026',
                        'time' => '6:26 PM',
                        'icon' => 'fa-cart-shopping',
                        'unread' => true,
                    ],
                    [
                        'title' => 'New account request received',
                        'message' => 'A new Warehouse Staff account is waiting for administrator approval.',
                        'module' => 'Admin',
                        'type' => 'Request',
                        'reference' => 'USR-REQ-0021',
                        'date' => 'Jul 25, 2026',
                        'time' => '4:55 PM',
                        'icon' => 'fa-user-clock',
                        'unread' => true,
                    ],
                    [
                        'title' => 'GPS batch file processed successfully',
                        'message' => 'The uploaded GPS trip file has been processed and trip records are available for review.',
                        'module' => 'Admin',
                        'type' => 'Success',
                        'reference' => 'BAT-2026-0018',
                        'date' => 'Jul 25, 2026',
                        'time' => '2:12 PM',
                        'icon' => 'fa-file-circle-check',
                        'unread' => false,
                    ],
                    [
                        'title' => 'Shuttle bus record updated',
                        'message' => 'BUS-007 was updated from Under Maintenance to Operational.',
                        'module' => 'Operation',
                        'type' => 'Update',
                        'reference' => 'BUS-007',
                        'date' => 'Jul 25, 2026',
                        'time' => '11:34 AM',
                        'icon' => 'fa-bus',
                        'unread' => false,
                    ],
                ];
            @endphp

            {{-- =====================================================
                MAIN NOTIFICATIONS CARD
            ====================================================== --}}
            <section data-ajax-region="records" class="table-card notification-card">

                <div class="section-header">

                    <div>
                        <h2>Notification Center</h2>

                        <p>
                            Review system alerts, departmental updates, requests, and important events.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="mark-all-btn"
                    >
                        <i class="fa-solid fa-check-double"></i>
                        Mark All as Read
                    </button>

                </div>

                {{-- =================================================
                    FILTERS
                ================================================== --}}
                <div class="notification-toolbar">

                    <div class="search-box">

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            placeholder="Search notifications..."
                        >

                    </div>

                    <div class="filter-group">

                        <select>
                            <option>All Modules</option>
                            <option>Admin</option>
                            <option>Maintenance</option>
                            <option>Warehouse</option>
                            <option>Purchase</option>
                            <option>Operation</option>
                        </select>

                    </div>

                    <div class="filter-group">

                        <select>
                            <option>All Types</option>
                            <option>Critical</option>
                            <option>Warning</option>
                            <option>Update</option>
                            <option>Request</option>
                            <option>Success</option>
                        </select>

                    </div>

                    <div class="filter-group">

                        <select>
                            <option>All Notifications</option>
                            <option>Unread</option>
                            <option>Read</option>
                        </select>

                    </div>

                </div>

                {{-- =================================================
                    NOTIFICATION LIST
                ================================================== --}}
                <div class="notification-list">

                    @foreach($notifications as $notification)

                        @php
                            $moduleClass = strtolower($notification['module']);

                            $typeClass = match($notification['type']) {
                                'Critical' => 'critical',
                                'Warning' => 'warning',
                                'Update' => 'update',
                                'Request' => 'request',
                                'Success' => 'success',
                                default => 'update',
                            };
                        @endphp

                        <article class="notification-item {{ $notification['unread'] ? 'unread' : '' }}">

                            <div class="notification-icon {{ $typeClass }}">
                                <i class="fa-solid {{ $notification['icon'] }}"></i>
                            </div>

                            <div class="notification-content">

                                <div class="notification-heading">

                                    <div>
                                        <div class="notification-title-row">

                                            <h3>
                                                {{ $notification['title'] }}
                                            </h3>

                                            @if($notification['unread'])
                                                <span class="unread-dot"></span>
                                            @endif

                                        </div>

                                        <p>
                                            {{ $notification['message'] }}
                                        </p>
                                    </div>

                                    <div class="notification-time">

                                        <strong>
                                            {{ $notification['date'] }}
                                        </strong>

                                        <span>
                                            {{ $notification['time'] }}
                                        </span>

                                    </div>

                                </div>

                                <div class="notification-meta">

                                    <span class="module-badge {{ $moduleClass }}">
                                        {{ $notification['module'] }}
                                    </span>

                                    <span class="type-badge {{ $typeClass }}">
                                        {{ $notification['type'] }}
                                    </span>

                                    <span class="reference-code">
                                        {{ $notification['reference'] }}
                                    </span>

                                </div>

                            </div>

                            <div class="notification-actions">

                                <button
                                    type="button"
                                    class="notification-action view"
                                    title="View Details"
                                >
                                    <i class="fa-solid fa-eye"></i>
                                </button>

                                @if($notification['unread'])
                                    <button
                                        type="button"
                                        class="notification-action read"
                                        title="Mark as Read"
                                    >
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                @endif

                            </div>

                        </article>

                    @endforeach

                </div>

                {{-- =================================================
                    FOOTER
                ================================================== --}}
                <div class="table-footer">

                    <p>
                        Showing 1 to 6 of 24 notifications
                    </p>

                    <div class="pagination">

                        <button
                            type="button"
                            class="page-btn disabled"
                            disabled
                        >
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>

                        <span class="page-number">
                            1
                        </span>

                        <button
                            type="button"
                            class="page-btn"
                        >
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>

                    </div>

                </div>

            </section>

        </main>

    </div>

</x-layout.app>