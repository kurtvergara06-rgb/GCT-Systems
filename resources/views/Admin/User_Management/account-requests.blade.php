<x-layout.app
    title="FROMS - Account Requests"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/User_Management/account-requests.css',
        'resources/js/Main-js/sidebar.js',
    ]"
>

    <div class="app">

        <x-layout.sidebar
            department="Admin"
            subtitle="Administration Module"
            icon="fa-user-shield"
            :items="[
                [
                    'label' => 'Dashboard',
                    'route' => 'admin.dashboard',
                    'icon' => 'fa-table-cells-large'
                ],
                [
                    'label' => 'User Management',
                    'icon' => 'fa-users',
                    'children' => [
                        [
                            'label' => 'Users',
                            'route' => 'admin.users',
                            'icon' => 'fa-user'
                        ],
                        [
                            'label' => 'Roles & Permissions',
                            'route' => 'admin.roles-permissions',
                            'icon' => 'fa-user-lock'
                        ],
                        [
                            'label' => 'Account Requests',
                            'route' => 'admin.account-requests',
                            'icon' => 'fa-user-clock'
                        ],
                    ]
                ],
                [
                    'label' => 'System Monitoring',
                    'icon' => 'fa-desktop',
                    'children' => [
                        [
                            'label' => 'Activity Logs',
                            'route' => 'admin.activity-logs',
                            'icon' => 'fa-clock-rotate-left'
                        ],
                        [
                            'label' => 'Notifications',
                            'route' => 'admin.notifications',
                            'icon' => 'fa-bell'
                        ],
                    ]
                ],
                [
                    'label' => 'Data Management',
                    'icon' => 'fa-database',
                    'children' => [
                        [
                            'label' => 'Batch File Processing',
                            'route' => 'admin.batch-file-processing',
                            'icon' => 'fa-file-import'
                        ],
                        [
                            'label' => 'Import / Export',
                            'route' => 'admin.import-export',
                            'icon' => 'fa-right-left'
                        ],
                        [
                            'label' => 'Data History',
                            'route' => 'admin.data-history',
                            'icon' => 'fa-clock-rotate-left'
                        ],
                    ]
                ],
                [
                    'label' => 'Analytics',
                    'icon' => 'fa-chart-line',
                    'children' => [
                        [
                            'label' => 'Overview',
                            'route' => 'analytics.overview',
                            'icon' => 'fa-chart-pie'
                        ],
                        [
                            'label' => 'Fleet & Trip',
                            'route' => 'analytics.fleet-trip',
                            'icon' => 'fa-route'
                        ],
                        [
                            'label' => 'Fuel',
                            'route' => 'analytics.fuel',
                            'icon' => 'fa-gas-pump'
                        ],
                        [
                            'label' => 'Bus Health',
                            'route' => 'analytics.bus-health',
                            'icon' => 'fa-heart-pulse'
                        ],
                        [
                            'label' => 'Inventory',
                            'route' => 'analytics.inventory',
                            'icon' => 'fa-boxes-stacked'
                        ],
                        [
                            'label' => 'Recommendations',
                            'route' => 'analytics.recommendations',
                            'icon' => 'fa-lightbulb'
                        ],
                    ]
                ],
                [
                    'label' => 'Settings',
                    'icon' => 'fa-gear',
                    'children' => [
                        [
                            'label' => 'General Settings',
                            'route' => 'admin.settings.general',
                            'icon' => 'fa-sliders'
                        ],
                        [
                            'label' => 'Notification Settings',
                            'route' => 'admin.settings.notifications',
                            'icon' => 'fa-bell'
                        ],
                        [
                            'label' => 'Security Settings',
                            'route' => 'admin.settings.security',
                            'icon' => 'fa-shield-halved'
                        ],
                    ]
                ],
            ]"
        />

        <main class="main account-requests-page">

            <x-layout.topbar
                title="Account Requests"
                subtitle="Review and manage pending account registration requests from different FROMS departments"
                notification-count="6"
            />

            <section class="stats-grid account-stats-grid">

                <x-ui.summary-card
                    label="Pending Requests"
                    value="6"
                    small="Waiting for review"
                    icon="fa-user-clock"
                    color="blue"
                />

                <x-ui.summary-card
                    label="Approved"
                    value="18"
                    small="Approved accounts"
                    icon="fa-user-check"
                    color="green"
                />

                <x-ui.summary-card
                    label="Rejected"
                    value="3"
                    small="Declined requests"
                    icon="fa-user-xmark"
                    color="red"
                />

                <x-ui.summary-card
                    label="Total Requests"
                    value="27"
                    small="All account requests"
                    icon="fa-users"
                    color="yellow"
                />

            </section>

            <section class="table-card account-table-card">

                <div class="section-header">
                    <div>
                        <h2>Registration Requests</h2>
                        <p>
                            Review requested department and role before approving a new account.
                        </p>
                    </div>

                    <span class="request-count">
                        6 Pending
                    </span>
                </div>

                <div class="account-toolbar">

                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            placeholder="Search name, email, department..."
                        >
                    </div>

                    <div class="filter-group">
                        <select>
                            <option>All Departments</option>
                            <option>Maintenance</option>
                            <option>Warehouse</option>
                            <option>Purchase</option>
                            <option>Operation</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <select>
                            <option>All Roles</option>
                            <option>Head</option>
                            <option>Staff</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <select>
                            <option>All Status</option>
                            <option>Pending</option>
                            <option>Approved</option>
                            <option>Rejected</option>
                        </select>
                    </div>

                </div>

                <div class="table-wrap">

                    <table class="account-requests-table">

                        <thead>
                            <tr>
                                <th>Requester</th>
                                <th>Department</th>
                                <th>Requested Role</th>
                                <th>Date Requested</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                            @php
                                $requests = [
                                    [
                                        'initials' => 'JM',
                                        'name' => 'Juan Miguel Santos',
                                        'email' => 'juan.santos@example.com',
                                        'department' => 'Maintenance',
                                        'role' => 'Head',
                                        'date' => 'Jul 24, 2026',
                                        'time' => '10:24 AM',
                                    ],
                                    [
                                        'initials' => 'AC',
                                        'name' => 'Andrea Cruz',
                                        'email' => 'andrea.cruz@example.com',
                                        'department' => 'Warehouse',
                                        'role' => 'Staff',
                                        'date' => 'Jul 24, 2026',
                                        'time' => '9:45 AM',
                                    ],
                                    [
                                        'initials' => 'RP',
                                        'name' => 'Rafael Perez',
                                        'email' => 'rafael.perez@example.com',
                                        'department' => 'Purchase',
                                        'role' => 'Staff',
                                        'date' => 'Jul 23, 2026',
                                        'time' => '4:15 PM',
                                    ],
                                    [
                                        'initials' => 'MS',
                                        'name' => 'Marco Salazar',
                                        'email' => 'marco.salazar@example.com',
                                        'department' => 'Operation',
                                        'role' => 'Head',
                                        'date' => 'Jul 23, 2026',
                                        'time' => '1:30 PM',
                                    ],
                                    [
                                        'initials' => 'LT',
                                        'name' => 'Lara Torres',
                                        'email' => 'lara.torres@example.com',
                                        'department' => 'Maintenance',
                                        'role' => 'Staff',
                                        'date' => 'Jul 22, 2026',
                                        'time' => '3:05 PM',
                                    ],
                                    [
                                        'initials' => 'DN',
                                        'name' => 'Daniel Navarro',
                                        'email' => 'daniel.navarro@example.com',
                                        'department' => 'Warehouse',
                                        'role' => 'Staff',
                                        'date' => 'Jul 22, 2026',
                                        'time' => '11:10 AM',
                                    ],
                                ];
                            @endphp

                            @foreach($requests as $requestItem)

                                @php
                                    $departmentClass = strtolower($requestItem['department']);
                                    $roleClass = strtolower($requestItem['role']);
                                @endphp

                                <tr>

                                    <td>
                                        <div class="requester-cell">

                                            <div class="requester-avatar">
                                                {{ $requestItem['initials'] }}
                                            </div>

                                            <div>
                                                <strong>
                                                    {{ $requestItem['name'] }}
                                                </strong>

                                                <span>
                                                    {{ $requestItem['email'] }}
                                                </span>
                                            </div>

                                        </div>
                                    </td>

                                    <td>
                                        <span class="department-badge {{ $departmentClass }}">
                                            {{ $requestItem['department'] }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="role-badge {{ $roleClass }}">
                                            {{ $requestItem['role'] }}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="date-time-cell">
                                            <span class="date-value">
                                                {{ $requestItem['date'] }}
                                            </span>

                                            <span class="time-value">
                                                {{ $requestItem['time'] }}
                                            </span>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="status-badge pending">
                                            <i class="fa-solid fa-clock"></i>
                                            Pending
                                        </span>
                                    </td>

                                    <td>
                                        <div class="actions">

                                            <button
                                                type="button"
                                                class="action-btn view"
                                                title="View Request"
                                            >
                                                <i class="fa-solid fa-eye"></i>
                                            </button>

                                            <button
                                                type="button"
                                                class="action-btn approve"
                                                title="Approve Request"
                                            >
                                                <i class="fa-solid fa-check"></i>
                                            </button>

                                            <button
                                                type="button"
                                                class="action-btn reject"
                                                title="Reject Request"
                                            >
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>

                                        </div>
                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

                <div class="table-footer">

                    <p>
                        Showing 1 to 6 of 6 pending requests
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
                            class="page-btn disabled"
                            disabled
                        >
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>

                    </div>

                </div>

            </section>

        </main>

    </div>

</x-layout.app>