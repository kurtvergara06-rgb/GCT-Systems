<x-layout.app
    title="FROMS - Roles & Permissions"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/User_Management/permissions.css',
        'resources/js/Main-js/sidebar.js'
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

        <main class="main permissions-page">

            <x-layout.topbar
                title="Roles & Permissions"
                subtitle="Manage department roles and review system access levels"
                notification-count="6"
            />

            {{-- =====================================================
                ACCESS SUMMARY
            ====================================================== --}}
            <section class="permission-overview">

                <div class="permission-overview-copy">

                    <span class="section-kicker">
                        <i class="fa-solid fa-user-lock"></i>
                        Access Control
                    </span>

                    <h2>
                        Define what each FROMS role can view, manage, and approve.
                    </h2>

                    <p>
                        Select a role to review its access across Operation, Maintenance,
                        Purchase, Warehouse, Administration, and Analytics.
                    </p>

                </div>


                <div class="permission-overview-stats">

                    <div class="overview-stat">
                        <span>Roles</span>
                        <strong>9</strong>
                    </div>

                    <div class="overview-divider"></div>

                    <div class="overview-stat">
                        <span>Departments</span>
                        <strong>5</strong>
                    </div>

                    <div class="overview-divider"></div>

                    <div class="overview-stat">
                        <span>Admin Roles</span>
                        <strong>1</strong>
                    </div>

                </div>

            </section>


            {{-- =====================================================
                ACCESS WORKSPACE
            ====================================================== --}}
            <section class="access-workspace">

                {{-- =================================================
                    ROLE DIRECTORY
                ================================================== --}}
                <aside class="role-directory">

                    <div class="role-directory-heading">

                        <div>
                            <span class="section-kicker">
                                Roles
                            </span>

                            <h2>
                                Role Directory
                            </h2>

                            <p>
                                Select a role to review access.
                            </p>
                        </div>

                        <span class="role-count">
                            9
                        </span>

                    </div>


                    <div class="role-search">

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            placeholder="Search role..."
                        >

                    </div>


                    <div class="role-directory-list">

                        {{-- SYSTEM ADMIN --}}
                        <button
                            type="button"
                            class="directory-role active"
                        >

                            <div class="directory-role-icon admin">
                                <i class="fa-solid fa-user-shield"></i>
                            </div>

                            <div class="directory-role-info">
                                <strong>System Admin</strong>
                                <span>Admin Department</span>
                            </div>

                            <span class="directory-role-type admin">
                                Full
                            </span>

                        </button>


                        {{-- OPERATION HEAD --}}
                        <button
                            type="button"
                            class="directory-role"
                        >

                            <div class="directory-role-icon head">
                                <i class="fa-solid fa-user-tie"></i>
                            </div>

                            <div class="directory-role-info">
                                <strong>Operation Head</strong>
                                <span>Operation Department</span>
                            </div>

                            <span class="directory-role-type head">
                                Head
                            </span>

                        </button>


                        {{-- OPERATION STAFF --}}
                        <button
                            type="button"
                            class="directory-role"
                        >

                            <div class="directory-role-icon staff">
                                <i class="fa-solid fa-user"></i>
                            </div>

                            <div class="directory-role-info">
                                <strong>Operation Staff</strong>
                                <span>Operation Department</span>
                            </div>

                            <span class="directory-role-type staff">
                                Staff
                            </span>

                        </button>


                        {{-- MAINTENANCE HEAD --}}
                        <button
                            type="button"
                            class="directory-role"
                        >

                            <div class="directory-role-icon head">
                                <i class="fa-solid fa-user-tie"></i>
                            </div>

                            <div class="directory-role-info">
                                <strong>Maintenance Head</strong>
                                <span>Maintenance Department</span>
                            </div>

                            <span class="directory-role-type head">
                                Head
                            </span>

                        </button>


                        {{-- MAINTENANCE STAFF --}}
                        <button
                            type="button"
                            class="directory-role"
                        >

                            <div class="directory-role-icon staff">
                                <i class="fa-solid fa-user"></i>
                            </div>

                            <div class="directory-role-info">
                                <strong>Maintenance Staff</strong>
                                <span>Maintenance Department</span>
                            </div>

                            <span class="directory-role-type staff">
                                Staff
                            </span>

                        </button>


                        {{-- PURCHASE HEAD --}}
                        <button
                            type="button"
                            class="directory-role"
                        >

                            <div class="directory-role-icon head">
                                <i class="fa-solid fa-user-tie"></i>
                            </div>

                            <div class="directory-role-info">
                                <strong>Purchase Head</strong>
                                <span>Purchase Department</span>
                            </div>

                            <span class="directory-role-type head">
                                Head
                            </span>

                        </button>


                        {{-- PURCHASE STAFF --}}
                        <button
                            type="button"
                            class="directory-role"
                        >

                            <div class="directory-role-icon staff">
                                <i class="fa-solid fa-user"></i>
                            </div>

                            <div class="directory-role-info">
                                <strong>Purchase Staff</strong>
                                <span>Purchase Department</span>
                            </div>

                            <span class="directory-role-type staff">
                                Staff
                            </span>

                        </button>


                        {{-- WAREHOUSE HEAD --}}
                        <button
                            type="button"
                            class="directory-role"
                        >

                            <div class="directory-role-icon head">
                                <i class="fa-solid fa-user-tie"></i>
                            </div>

                            <div class="directory-role-info">
                                <strong>Warehouse Head</strong>
                                <span>Warehouse Department</span>
                            </div>

                            <span class="directory-role-type head">
                                Head
                            </span>

                        </button>


                        {{-- WAREHOUSE STAFF --}}
                        <button
                            type="button"
                            class="directory-role"
                        >

                            <div class="directory-role-icon staff">
                                <i class="fa-solid fa-user"></i>
                            </div>

                            <div class="directory-role-info">
                                <strong>Warehouse Staff</strong>
                                <span>Warehouse Department</span>
                            </div>

                            <span class="directory-role-type staff">
                                Staff
                            </span>

                        </button>

                    </div>

                </aside>


                {{-- =================================================
                    ROLE PERMISSIONS
                ================================================== --}}
                <div class="role-access-panel">

                    {{-- ROLE HEADER --}}
                    <div class="selected-role-header">

                        <div class="selected-role-main">

                            <div class="selected-role-icon">
                                <i class="fa-solid fa-user-shield"></i>
                            </div>

                            <div>

                                <span class="selected-label">
                                    Selected Role
                                </span>

                                <h2>
                                    System Admin
                                </h2>

                                <p>
                                    Admin Department · Full administrative access
                                </p>

                            </div>

                        </div>


                        <div class="selected-role-state">

                            <span>
                                <i class="fa-solid fa-circle-check"></i>
                                Full Access
                            </span>

                        </div>

                    </div>


                    {{-- =================================================
                        ROLE CAPABILITIES
                    ================================================== --}}
                    <div class="role-capability-strip">

                        <div class="capability-item">

                            <div class="capability-icon view">
                                <i class="fa-solid fa-eye"></i>
                            </div>

                            <div>
                                <span>View Access</span>
                                <strong>All Modules</strong>
                            </div>

                        </div>


                        <div class="capability-item">

                            <div class="capability-icon edit">
                                <i class="fa-solid fa-pen"></i>
                            </div>

                            <div>
                                <span>Create / Edit</span>
                                <strong>Allowed</strong>
                            </div>

                        </div>


                        <div class="capability-item">

                            <div class="capability-icon approve">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>

                            <div>
                                <span>Approval Access</span>
                                <strong>Allowed</strong>
                            </div>

                        </div>

                    </div>


                    {{-- =================================================
                        MODULE PERMISSIONS
                    ================================================== --}}
                    <section class="module-access-section">

                        <div class="module-access-heading">

                            <div>
                                <span class="section-kicker">
                                    Module Permissions
                                </span>

                                <h2>
                                    System Access
                                </h2>

                                <p>
                                    Permission levels assigned to the selected role.
                                </p>
                            </div>


                            <div class="access-legend">

                                <span>
                                    <i class="fa-solid fa-circle-check"></i>
                                    Allowed
                                </span>

                                <span>
                                    <i class="fa-solid fa-circle-xmark"></i>
                                    Restricted
                                </span>

                            </div>

                        </div>


                        <div class="module-access-list">

                            {{-- OPERATION --}}
                            <article class="module-access-row">

                                <div class="module-access-info">

                                    <div class="module-access-icon operation">
                                        <i class="fa-solid fa-bus"></i>
                                    </div>

                                    <div>
                                        <strong>Operation</strong>
                                        <span>
                                            Shuttle buses, routes, schedules, attendance, and trip records
                                        </span>
                                    </div>

                                </div>


                                <div class="module-permission-options">

                                    <div class="permission-option allowed">

                                        <div>
                                            <i class="fa-solid fa-eye"></i>
                                        </div>

                                        <span>
                                            View
                                        </span>

                                        <i class="fa-solid fa-circle-check option-state"></i>

                                    </div>


                                    <div class="permission-option allowed">

                                        <div>
                                            <i class="fa-solid fa-pen"></i>
                                        </div>

                                        <span>
                                            Create / Edit
                                        </span>

                                        <i class="fa-solid fa-circle-check option-state"></i>

                                    </div>


                                    <div class="permission-option allowed">

                                        <div>
                                            <i class="fa-solid fa-check-double"></i>
                                        </div>

                                        <span>
                                            Approve
                                        </span>

                                        <i class="fa-solid fa-circle-check option-state"></i>

                                    </div>

                                </div>

                            </article>


                            {{-- MAINTENANCE --}}
                            <article class="module-access-row">

                                <div class="module-access-info">

                                    <div class="module-access-icon maintenance">
                                        <i class="fa-solid fa-screwdriver-wrench"></i>
                                    </div>

                                    <div>
                                        <strong>Maintenance</strong>
                                        <span>
                                            Job orders, PMS scheduling, mechanics, and fuel reports
                                        </span>
                                    </div>

                                </div>


                                <div class="module-permission-options">

                                    <div class="permission-option allowed">

                                        <div>
                                            <i class="fa-solid fa-eye"></i>
                                        </div>

                                        <span>
                                            View
                                        </span>

                                        <i class="fa-solid fa-circle-check option-state"></i>

                                    </div>


                                    <div class="permission-option allowed">

                                        <div>
                                            <i class="fa-solid fa-pen"></i>
                                        </div>

                                        <span>
                                            Create / Edit
                                        </span>

                                        <i class="fa-solid fa-circle-check option-state"></i>

                                    </div>


                                    <div class="permission-option allowed">

                                        <div>
                                            <i class="fa-solid fa-check-double"></i>
                                        </div>

                                        <span>
                                            Approve
                                        </span>

                                        <i class="fa-solid fa-circle-check option-state"></i>

                                    </div>

                                </div>

                            </article>


                            {{-- PURCHASE --}}
                            <article class="module-access-row">

                                <div class="module-access-info">

                                    <div class="module-access-icon purchase">
                                        <i class="fa-solid fa-cart-shopping"></i>
                                    </div>

                                    <div>
                                        <strong>Purchase</strong>
                                        <span>
                                            Purchase requests, purchase orders, and scheduled purchases
                                        </span>
                                    </div>

                                </div>


                                <div class="module-permission-options">

                                    <div class="permission-option allowed">

                                        <div>
                                            <i class="fa-solid fa-eye"></i>
                                        </div>

                                        <span>
                                            View
                                        </span>

                                        <i class="fa-solid fa-circle-check option-state"></i>

                                    </div>


                                    <div class="permission-option allowed">

                                        <div>
                                            <i class="fa-solid fa-pen"></i>
                                        </div>

                                        <span>
                                            Create / Edit
                                        </span>

                                        <i class="fa-solid fa-circle-check option-state"></i>

                                    </div>


                                    <div class="permission-option allowed">

                                        <div>
                                            <i class="fa-solid fa-check-double"></i>
                                        </div>

                                        <span>
                                            Approve
                                        </span>

                                        <i class="fa-solid fa-circle-check option-state"></i>

                                    </div>

                                </div>

                            </article>


                            {{-- WAREHOUSE --}}
                            <article class="module-access-row">

                                <div class="module-access-info">

                                    <div class="module-access-icon warehouse">
                                        <i class="fa-solid fa-boxes-stacked"></i>
                                    </div>

                                    <div>
                                        <strong>Warehouse</strong>
                                        <span>
                                            Inventory, part requests, deliveries, and stock movements
                                        </span>
                                    </div>

                                </div>


                                <div class="module-permission-options">

                                    <div class="permission-option allowed">

                                        <div>
                                            <i class="fa-solid fa-eye"></i>
                                        </div>

                                        <span>
                                            View
                                        </span>

                                        <i class="fa-solid fa-circle-check option-state"></i>

                                    </div>


                                    <div class="permission-option allowed">

                                        <div>
                                            <i class="fa-solid fa-pen"></i>
                                        </div>

                                        <span>
                                            Create / Edit
                                        </span>

                                        <i class="fa-solid fa-circle-check option-state"></i>

                                    </div>


                                    <div class="permission-option allowed">

                                        <div>
                                            <i class="fa-solid fa-check-double"></i>
                                        </div>

                                        <span>
                                            Approve
                                        </span>

                                        <i class="fa-solid fa-circle-check option-state"></i>

                                    </div>

                                </div>

                            </article>


                            {{-- ANALYTICS --}}
                            <article class="module-access-row">

                                <div class="module-access-info">

                                    <div class="module-access-icon analytics">
                                        <i class="fa-solid fa-chart-line"></i>
                                    </div>

                                    <div>
                                        <strong>Analytics</strong>
                                        <span>
                                            Operational analytics and decision-support recommendations
                                        </span>
                                    </div>

                                </div>


                                <div class="module-permission-options">

                                    <div class="permission-option allowed">

                                        <div>
                                            <i class="fa-solid fa-eye"></i>
                                        </div>

                                        <span>
                                            View
                                        </span>

                                        <i class="fa-solid fa-circle-check option-state"></i>

                                    </div>


                                    <div class="permission-option allowed">

                                        <div>
                                            <i class="fa-solid fa-chart-column"></i>
                                        </div>

                                        <span>
                                            Analyze
                                        </span>

                                        <i class="fa-solid fa-circle-check option-state"></i>

                                    </div>


                                    <div class="permission-option allowed">

                                        <div>
                                            <i class="fa-solid fa-lightbulb"></i>
                                        </div>

                                        <span>
                                            Recommendations
                                        </span>

                                        <i class="fa-solid fa-circle-check option-state"></i>

                                    </div>

                                </div>

                            </article>


                            {{-- ADMINISTRATION --}}
                            <article class="module-access-row">

                                <div class="module-access-info">

                                    <div class="module-access-icon admin">
                                        <i class="fa-solid fa-user-shield"></i>
                                    </div>

                                    <div>
                                        <strong>Administration</strong>
                                        <span>
                                            Users, permissions, monitoring, data management, and settings
                                        </span>
                                    </div>

                                </div>


                                <div class="module-permission-options">

                                    <div class="permission-option allowed">

                                        <div>
                                            <i class="fa-solid fa-eye"></i>
                                        </div>

                                        <span>
                                            View
                                        </span>

                                        <i class="fa-solid fa-circle-check option-state"></i>

                                    </div>


                                    <div class="permission-option allowed">

                                        <div>
                                            <i class="fa-solid fa-user-gear"></i>
                                        </div>

                                        <span>
                                            Manage
                                        </span>

                                        <i class="fa-solid fa-circle-check option-state"></i>

                                    </div>


                                    <div class="permission-option allowed">

                                        <div>
                                            <i class="fa-solid fa-shield-halved"></i>
                                        </div>

                                        <span>
                                            Full Control
                                        </span>

                                        <i class="fa-solid fa-circle-check option-state"></i>

                                    </div>

                                </div>

                            </article>

                        </div>

                    </section>

                </div>

            </section>


            {{-- =====================================================
                POLICY GUIDE
            ====================================================== --}}
            <section class="access-policy">

                <div class="access-policy-heading">

                    <div>
                        <span class="section-kicker">
                            Access Policy
                        </span>

                        <h2>
                            How Role Access Works
                        </h2>
                    </div>

                </div>


                <div class="policy-flow">

                    <div class="policy-step">

                        <div class="policy-step-number">
                            01
                        </div>

                        <div>
                            <strong>System Admin</strong>

                            <span>
                                Full access across the FROMS system.
                            </span>
                        </div>

                    </div>


                    <i class="fa-solid fa-arrow-right policy-arrow"></i>


                    <div class="policy-step">

                        <div class="policy-step-number">
                            02
                        </div>

                        <div>
                            <strong>Department Head</strong>

                            <span>
                                Manages department records and selected approvals.
                            </span>
                        </div>

                    </div>


                    <i class="fa-solid fa-arrow-right policy-arrow"></i>


                    <div class="policy-step">

                        <div class="policy-step-number">
                            03
                        </div>

                        <div>
                            <strong>Department Staff</strong>

                            <span>
                                Handles permitted operational tasks without approval access.
                            </span>
                        </div>

                    </div>

                </div>

            </section>


            {{-- =====================================================
                STATIC NOTE
            ====================================================== --}}
            <section class="permission-note">

                <div class="permission-note-icon">
                    <i class="fa-solid fa-circle-info"></i>
                </div>

                <div>

                    <strong>
                        Role permissions are currently displayed as a static frontend preview.
                    </strong>

                    <p>
                        Later, these permission states can be loaded from your database
                        and enforced using Laravel middleware, policies, or authorization rules.
                    </p>

                </div>

            </section>

        </main>

    </div>

</x-layout.app>