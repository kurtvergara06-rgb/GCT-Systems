<x-layout.app
    title="FROMS - Permissions"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/User_Management/permissions.css',
        'resources/js/Main-js/sidebar.js'
    ]"
>

    @php
        $authUser = auth()->user();

        $sidebarName = $authUser?->name ?? 'System Admin';

        $sidebarDepartment = trim($authUser?->department ?? 'Admin');
        $sidebarRoleValue = strtolower(trim($authUser?->role ?? 'head'));

        if (strtolower($sidebarDepartment) === 'admin') {
            $sidebarRole = $sidebarRoleValue === 'head'
                ? 'System Admin'
                : 'Admin Staff';
        } else {
            $sidebarRole = $sidebarDepartment . ' ' . ucfirst($sidebarRoleValue ?: 'staff');
        }
    @endphp

    <div class="app admin-permission-app">

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

        <main class="main permission-main">

            <x-layout.topbar
                title="Permissions"
                subtitle="Configure role-based access control for all departments"
                notification-count="6"
            />

            <section class="permission-card">

                <div class="permission-card-header">

                    <div>
                        <h2>Role Permission Matrix</h2>

                        <p>
                            Static permission overview for System Admin,
                            department heads, and staff roles.
                        </p>
                    </div>

                    <div class="permission-legend">

                        <span class="legend-item">
                            <i class="fa-solid fa-check"></i>
                            Allowed
                        </span>

                        <span class="legend-item denied">
                            <i class="fa-solid fa-xmark"></i>
                            Denied
                        </span>

                    </div>

                </div>

                <div class="permission-table-wrap">

                    <table class="permission-table">

                        <thead>

                            <tr class="module-row">

                                <th
                                    rowspan="2"
                                    class="role-header"
                                >
                                    Role
                                </th>

                                <th
                                    colspan="3"
                                    class="module-header shuttle"
                                >
                                    Shuttle Management
                                </th>

                                <th
                                    colspan="3"
                                    class="module-header maintenance"
                                >
                                    Maintenance Management
                                </th>

                                <th
                                    colspan="3"
                                    class="module-header driver"
                                >
                                    Driver Assignment
                                </th>

                                <th
                                    colspan="3"
                                    class="module-header purchasing"
                                >
                                    Purchase
                                </th>

                                <th
                                    colspan="3"
                                    class="module-header warehouse"
                                >
                                    Warehouse
                                </th>

                            </tr>

                            <tr class="permission-action-row">

                                @for($i = 0; $i < 5; $i++)
                                    <th>View</th>
                                    <th>Create/Edit</th>
                                    <th>Approve</th>
                                @endfor

                            </tr>

                        </thead>

                        <tbody>

                            {{-- =================================================
                                SYSTEM ADMIN
                            ================================================== --}}
                            <tr>

                                <td class="role-cell">
                                    <span class="role-badge system">
                                        System Admin
                                    </span>
                                </td>

                                @for($i = 0; $i < 15; $i++)
                                    <td>
                                        <span class="permission-check">
                                            <i class="fa-solid fa-check"></i>
                                        </span>
                                    </td>
                                @endfor

                            </tr>


                            {{-- =================================================
                                OPERATION HEAD
                            ================================================== --}}
                            <tr>

                                <td class="role-cell">
                                    <span class="role-badge operation">
                                        Operation Head
                                    </span>
                                </td>

                                {{-- Shuttle Management --}}
                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                <td>
                                    <span class="permission-deny">
                                        <i class="fa-solid fa-xmark"></i>
                                    </span>
                                </td>

                                {{-- Maintenance --}}
                                @for($i = 0; $i < 3; $i++)
                                    <td>
                                        <span class="permission-deny">
                                            <i class="fa-solid fa-xmark"></i>
                                        </span>
                                    </td>
                                @endfor

                                {{-- Driver Assignment --}}
                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                {{-- Purchase --}}
                                @for($i = 0; $i < 3; $i++)
                                    <td>
                                        <span class="permission-deny">
                                            <i class="fa-solid fa-xmark"></i>
                                        </span>
                                    </td>
                                @endfor

                                {{-- Warehouse --}}
                                @for($i = 0; $i < 3; $i++)
                                    <td>
                                        <span class="permission-deny">
                                            <i class="fa-solid fa-xmark"></i>
                                        </span>
                                    </td>
                                @endfor

                            </tr>


                            {{-- =================================================
                                OPERATION STAFF
                            ================================================== --}}
                            <tr>

                                <td class="role-cell">
                                    <span class="role-badge operation-staff">
                                        Operation Staff
                                    </span>
                                </td>

                                {{-- Shuttle Management --}}
                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                <td>
                                    <span class="permission-deny">
                                        <i class="fa-solid fa-xmark"></i>
                                    </span>
                                </td>

                                {{-- Maintenance --}}
                                @for($i = 0; $i < 3; $i++)
                                    <td>
                                        <span class="permission-deny">
                                            <i class="fa-solid fa-xmark"></i>
                                        </span>
                                    </td>
                                @endfor

                                {{-- Driver Assignment --}}
                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                <td>
                                    <span class="permission-deny">
                                        <i class="fa-solid fa-xmark"></i>
                                    </span>
                                </td>

                                {{-- Purchase --}}
                                @for($i = 0; $i < 3; $i++)
                                    <td>
                                        <span class="permission-deny">
                                            <i class="fa-solid fa-xmark"></i>
                                        </span>
                                    </td>
                                @endfor

                                {{-- Warehouse --}}
                                @for($i = 0; $i < 3; $i++)
                                    <td>
                                        <span class="permission-deny">
                                            <i class="fa-solid fa-xmark"></i>
                                        </span>
                                    </td>
                                @endfor

                            </tr>


                            {{-- =================================================
                                MAINTENANCE HEAD
                            ================================================== --}}
                            <tr>

                                <td class="role-cell">
                                    <span class="role-badge maintenance-head">
                                        Maintenance Head
                                    </span>
                                </td>

                                {{-- Shuttle --}}
                                @for($i = 0; $i < 3; $i++)
                                    <td>
                                        <span class="permission-deny">
                                            <i class="fa-solid fa-xmark"></i>
                                        </span>
                                    </td>
                                @endfor

                                {{-- Maintenance --}}
                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                {{-- Driver / Purchase / Warehouse --}}
                                @for($i = 0; $i < 9; $i++)
                                    <td>
                                        <span class="permission-deny">
                                            <i class="fa-solid fa-xmark"></i>
                                        </span>
                                    </td>
                                @endfor

                            </tr>


                            {{-- =================================================
                                MAINTENANCE STAFF
                            ================================================== --}}
                            <tr>

                                <td class="role-cell">
                                    <span class="role-badge maintenance-staff">
                                        Maintenance Staff
                                    </span>
                                </td>

                                {{-- Shuttle --}}
                                @for($i = 0; $i < 3; $i++)
                                    <td>
                                        <span class="permission-deny">
                                            <i class="fa-solid fa-xmark"></i>
                                        </span>
                                    </td>
                                @endfor

                                {{-- Maintenance --}}
                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                <td>
                                    <span class="permission-deny">
                                        <i class="fa-solid fa-xmark"></i>
                                    </span>
                                </td>

                                {{-- Driver / Purchase / Warehouse --}}
                                @for($i = 0; $i < 9; $i++)
                                    <td>
                                        <span class="permission-deny">
                                            <i class="fa-solid fa-xmark"></i>
                                        </span>
                                    </td>
                                @endfor

                            </tr>


                            {{-- =================================================
                                PURCHASE HEAD
                            ================================================== --}}
                            <tr>

                                <td class="role-cell">
                                    <span class="role-badge purchase-head">
                                        Purchase Head
                                    </span>
                                </td>

                                {{-- Shuttle / Maintenance / Driver --}}
                                @for($i = 0; $i < 9; $i++)
                                    <td>
                                        <span class="permission-deny">
                                            <i class="fa-solid fa-xmark"></i>
                                        </span>
                                    </td>
                                @endfor

                                {{-- Purchase --}}
                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                {{-- Warehouse --}}
                                @for($i = 0; $i < 3; $i++)
                                    <td>
                                        <span class="permission-deny">
                                            <i class="fa-solid fa-xmark"></i>
                                        </span>
                                    </td>
                                @endfor

                            </tr>


                            {{-- =================================================
                                PURCHASE STAFF
                            ================================================== --}}
                            <tr>

                                <td class="role-cell">
                                    <span class="role-badge purchase-staff">
                                        Purchase Staff
                                    </span>
                                </td>

                                {{-- Shuttle / Maintenance / Driver --}}
                                @for($i = 0; $i < 9; $i++)
                                    <td>
                                        <span class="permission-deny">
                                            <i class="fa-solid fa-xmark"></i>
                                        </span>
                                    </td>
                                @endfor

                                {{-- Purchase --}}
                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                <td>
                                    <span class="permission-deny">
                                        <i class="fa-solid fa-xmark"></i>
                                    </span>
                                </td>

                                {{-- Warehouse --}}
                                @for($i = 0; $i < 3; $i++)
                                    <td>
                                        <span class="permission-deny">
                                            <i class="fa-solid fa-xmark"></i>
                                        </span>
                                    </td>
                                @endfor

                            </tr>


                            {{-- =================================================
                                WAREHOUSE HEAD
                            ================================================== --}}
                            <tr>

                                <td class="role-cell">
                                    <span class="role-badge warehouse-head">
                                        Warehouse Head
                                    </span>
                                </td>

                                {{-- Shuttle / Maintenance / Driver / Purchase --}}
                                @for($i = 0; $i < 12; $i++)
                                    <td>
                                        <span class="permission-deny">
                                            <i class="fa-solid fa-xmark"></i>
                                        </span>
                                    </td>
                                @endfor

                                {{-- Warehouse --}}
                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                            </tr>


                            {{-- =================================================
                                WAREHOUSE STAFF
                            ================================================== --}}
                            <tr>

                                <td class="role-cell">
                                    <span class="role-badge warehouse-staff">
                                        Warehouse Staff
                                    </span>
                                </td>

                                {{-- Shuttle / Maintenance / Driver / Purchase --}}
                                @for($i = 0; $i < 12; $i++)
                                    <td>
                                        <span class="permission-deny">
                                            <i class="fa-solid fa-xmark"></i>
                                        </span>
                                    </td>
                                @endfor

                                {{-- Warehouse --}}
                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                <td>
                                    <span class="permission-check">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </td>

                                <td>
                                    <span class="permission-deny">
                                        <i class="fa-solid fa-xmark"></i>
                                    </span>
                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>


                {{-- =====================================================
                    ROLE ACCESS GUIDE
                ====================================================== --}}
                <div class="permission-note">

                    <p class="permission-note-title">
                        <i class="fa-solid fa-circle-info"></i>
                        Role Access Guide
                    </p>

                    <ul class="permission-note-list">

                        <li>
                            <strong>System Admin</strong>
                            has full access across all FROMS modules.
                        </li>

                        <li>
                            <strong>Department Heads</strong>
                            can view, create/edit, and approve permitted
                            department transactions.
                        </li>

                        <li>
                            <strong>Department Staff</strong>
                            can perform permitted daily operational tasks
                            but normally cannot approve transactions.
                        </li>

                    </ul>

                </div>

            </section>


            <footer class="admin-footer">
                © 2026 FROMS. All rights reserved.
            </footer>

        </main>

    </div>

</x-layout.app>