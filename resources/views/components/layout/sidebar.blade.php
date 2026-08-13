@props([
    'department' => 'Department',
    'subtitle' => 'Department Module',
    'icon' => 'fa-table-cells-large',
    'userName' => null,
    'userRole' => null,
    'items' => [],
])

@php
    $authUser = auth()->user();

    $displayName = trim($authUser?->name ?? $userName ?? 'Guest User');
    $departmentRaw = trim($authUser?->department ?? $department ?? 'Department');
    $roleRaw = strtolower(trim($authUser?->role ?? ''));

    $normalizedDepartment = strtolower(str_replace(['_', '-'], ' ', $departmentRaw));
    $normalizedRole = strtolower(str_replace(['_', '-'], ' ', $roleRaw));
    $componentDepartment = strtolower(trim(str_replace(['_', '-'], ' ', $department)));

    $departmentProfiles = [
        'maintenance' => ['subtitle' => 'Department Module', 'icon' => 'fa-truck'],
        'purchase' => ['subtitle' => 'Department Module', 'icon' => 'fa-cart-shopping'],
        'warehouse' => ['subtitle' => 'Warehouse Module', 'icon' => 'fa-warehouse'],
        'operation' => ['subtitle' => 'Operation Module', 'icon' => 'fa-bus'],
        'operations' => ['subtitle' => 'Operation Module', 'icon' => 'fa-bus'],
        'admin' => ['subtitle' => 'Administration Module', 'icon' => 'fa-user-shield'],
    ];

    $routeMatches = function ($patterns): bool {
        foreach ((array) $patterns as $pattern) {
            if ($pattern && request()->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    };

    $menuItemIsActive = function (array $item) use ($routeMatches): bool {
        if (array_key_exists('active', $item)) {
            return (bool) $item['active'];
        }

        if (! empty($item['active_routes'])) {
            return $routeMatches($item['active_routes']);
        }

        return ! empty($item['route']) && $routeMatches($item['route']);
    };

    if (isset($departmentProfiles[$componentDepartment])) {
        if ($subtitle === 'Department Module') {
            $subtitle = $departmentProfiles[$componentDepartment]['subtitle'];
        }

        if ($icon === 'fa-table-cells-large') {
            $icon = $departmentProfiles[$componentDepartment]['icon'];
        }
    }

    if ($componentDepartment === 'maintenance') {
        $items = [
            ['label' => 'Dashboard', 'route' => 'maintenance-dashboard', 'icon' => 'fa-table-cells-large'],
            [
                'label' => 'Work Management',
                'icon' => 'fa-screwdriver-wrench',
                'children' => [
                    ['label' => 'Job Orders', 'route' => 'job-orders', 'icon' => 'fa-clipboard-list'],
                    ['label' => 'PMS Scheduling', 'route' => 'PMS-Scheduling', 'icon' => 'fa-calendar-check'],
                ],
            ],
            [
                'label' => 'Resources',
                'icon' => 'fa-toolbox',
                'children' => [
                    ['label' => 'Mechanic Availability', 'route' => 'mechanic-list', 'icon' => 'fa-user-gear'],
                    ['label' => 'Fuel Reports', 'route' => 'fuel-reports', 'icon' => 'fa-gas-pump'],
                ],
            ],
            ['label' => 'Purchase Requests', 'route' => 'purchase-requests', 'icon' => 'fa-file-invoice'],
        ];
    }

    if ($componentDepartment === 'purchase') {
        $isPurchaseHistory = request()->routeIs('maintenance-requests')
            && request()->query('view') === 'history';

        $items = [
            ['label' => 'Dashboard', 'route' => 'dashboard-purchase', 'icon' => 'fa-table-cells-large'],
            ['label' => 'Purchase Orders', 'route' => 'purchase-orders', 'icon' => 'fa-file-invoice'],
            [
                'label' => 'Requested Purchase',
                'icon' => 'fa-clipboard-list',
                'children' => [
                    [
                        'label' => 'Maintenance Requests',
                        'route' => 'maintenance-requests',
                        'icon' => 'fa-screwdriver-wrench',
                        'active' => request()->routeIs('maintenance-requests') && ! $isPurchaseHistory,
                    ],
                    ['label' => 'Inventory Restock', 'route' => 'inventory-restock', 'icon' => 'fa-boxes-stacked'],
                ],
            ],
            [
                'label' => 'Purchase History',
                'url' => route('maintenance-requests', [], false) . '?view=history',
                'icon' => 'fa-clock-rotate-left',
                'active' => $isPurchaseHistory,
            ],
            ['label' => 'Scheduled Purchase', 'route' => 'scheduled-purchase', 'icon' => 'fa-calendar-check'],
        ];
    }

    if ($componentDepartment === 'warehouse') {
        $items = [
            ['label' => 'Dashboard', 'route' => 'warehouse.dashboard', 'icon' => 'fa-table-cells-large'],
            ['label' => 'Inventory', 'route' => 'inventory', 'icon' => 'fa-boxes-stacked'],
            ['label' => 'Part Requests', 'route' => 'part-requests', 'icon' => 'fa-clipboard-list'],
            ['label' => 'Incoming Deliveries', 'route' => 'incoming-deliveries', 'icon' => 'fa-truck-ramp-box'],
            ['label' => 'Stock Movements', 'route' => 'stock-movements', 'icon' => 'fa-right-left'],
        ];
    }

    if (in_array($componentDepartment, ['operation', 'operations'], true)) {
        $items = [
            ['label' => 'Dashboard', 'route' => 'dashboard-operation', 'icon' => 'fa-table-cells-large'],
            ['label' => 'Routes', 'route' => 'operation.routes', 'icon' => 'fa-route'],
            [
                'label' => 'Scheduling',
                'icon' => 'fa-calendar-days',
                'children' => [
                    ['label' => 'Trip Schedule', 'route' => 'trip-schedule', 'icon' => 'fa-calendar-days'],
                    ['label' => 'Driver & Bus Assignment', 'route' => 'driver-bus-assignment', 'icon' => 'fa-user-tie'],
                    ['label' => 'Auto Scheduling', 'route' => 'auto-scheduling', 'icon' => 'fa-wand-magic-sparkles'],
                ],
            ],
            [
                'label' => 'Personnel Management',
                'icon' => 'fa-address-book',
                'children' => [
                    ['label' => 'Driver Master List', 'route' => 'operation.personnel.drivers', 'icon' => 'fa-id-card'],
                    ['label' => 'Mechanic Master List', 'route' => 'operation.personnel.mechanics', 'icon' => 'fa-users-gear'],
                ],
            ],
            [
                'label' => 'Attendance',
                'icon' => 'fa-calendar-check',
                'children' => [
                    ['label' => 'Driver Attendance', 'route' => 'driver-attendance', 'icon' => 'fa-user-check'],
                    ['label' => 'Mechanic Attendance', 'route' => 'mechanic-attendance', 'icon' => 'fa-clipboard-user'],
                ],
            ],
            ['label' => 'Bus Master List', 'route' => 'bus-master-list', 'icon' => 'fa-bus'],
            ['label' => 'Trip Records', 'route' => 'trip-records', 'icon' => 'fa-clock-rotate-left'],
        ];
    }

    if ($componentDepartment === 'admin') {
        $items = [
            [
                'label' => 'Dashboard',
                'route' => 'admin.dashboard',
                'icon' => 'fa-table-cells-large',
                'active_routes' => ['admin.dashboard'],
            ],
            [
                'label' => 'Account Management',
                'icon' => 'fa-users-gear',
                'children' => [
                    [
                        'label' => 'Accounts',
                        'route' => 'admin.users',
                        'icon' => 'fa-address-card',
                        'active_routes' => ['admin.users', 'admin.users.*'],
                    ],
                    [
                        'label' => 'Roles & Permissions',
                        'route' => 'admin.roles-permissions',
                        'icon' => 'fa-user-lock',
                        'active_routes' => ['admin.roles-permissions', 'admin.roles-permissions.*'],
                    ],
                ],
            ],
            [
                'label' => 'System Monitoring',
                'icon' => 'fa-desktop',
                'children' => [
                    [
                        'label' => 'Activity Logs',
                        'route' => 'admin.activity-logs',
                        'icon' => 'fa-clock-rotate-left',
                        'active_routes' => ['admin.activity-logs', 'admin.activity-logs.*'],
                    ],
                    [
                        'label' => 'Notifications',
                        'route' => 'admin.notifications',
                        'icon' => 'fa-bell',
                        'active_routes' => ['admin.notifications', 'admin.notifications.*'],
                    ],
                ],
            ],
            [
                'label' => 'Data Management',
                'icon' => 'fa-database',
                'children' => [
                    [
                        'label' => 'Batch File Processing',
                        'route' => 'admin.batch-file-processing',
                        'icon' => 'fa-file-import',
                        'active_routes' => [
                            'admin.batch-file-processing',
                            'batch-file-processing',
                            'batch-file-processing.*',
                        ],
                    ],
                    [
                        'label' => 'Import / Export',
                        'route' => 'admin.import-export',
                        'icon' => 'fa-right-left',
                        'active_routes' => ['admin.import-export', 'admin.import-export.*'],
                    ],
                    [
                        'label' => 'Data History',
                        'route' => 'admin.data-history',
                        'icon' => 'fa-clock-rotate-left',
                        'active_routes' => ['admin.data-history', 'admin.data-history.*'],
                    ],
                ],
            ],
            [
                'label' => 'Analytics',
                'icon' => 'fa-chart-line',
                'children' => [
                    [
                        'label' => 'Overview',
                        'route' => 'analytics.overview',
                        'icon' => 'fa-chart-pie',
                        'active_routes' => ['analytics.overview', 'analytics.overview.*'],
                    ],
                    [
                        'label' => 'Fleet & Trip',
                        'route' => 'analytics.fleet-trip',
                        'icon' => 'fa-route',
                        'active_routes' => ['analytics.fleet-trip', 'analytics.fleet-trip.*'],
                    ],
                    [
                        'label' => 'Fuel',
                        'route' => 'analytics.fuel',
                        'icon' => 'fa-gas-pump',
                        'active_routes' => ['analytics.fuel', 'analytics.fuel.*'],
                    ],
                    [
                        'label' => 'Bus Health',
                        'route' => 'analytics.bus-health',
                        'icon' => 'fa-heart-pulse',
                        'active_routes' => ['analytics.bus-health', 'analytics.bus-health.*'],
                    ],
                    [
                        'label' => 'Inventory',
                        'route' => 'analytics.inventory',
                        'icon' => 'fa-boxes-stacked',
                        'active_routes' => ['analytics.inventory', 'analytics.inventory.*'],
                    ],
                    [
                        'label' => 'Recommendations',
                        'route' => 'analytics.recommendations',
                        'icon' => 'fa-lightbulb',
                        'active_routes' => ['analytics.recommendations', 'analytics.recommendations.*'],
                    ],
                ],
            ],
            [
                'label' => 'Settings',
                'icon' => 'fa-gear',
                'children' => [
                    [
                        'label' => 'General Settings',
                        'route' => 'admin.settings.general',
                        'icon' => 'fa-sliders',
                        'active_routes' => ['admin.settings.general', 'admin.settings.general.*'],
                    ],
                    [
                        'label' => 'Notification Settings',
                        'route' => 'admin.settings.notifications',
                        'icon' => 'fa-bell',
                        'active_routes' => ['admin.settings.notifications', 'admin.settings.notifications.*'],
                    ],
                    [
                        'label' => 'Security Settings',
                        'route' => 'admin.settings.security',
                        'icon' => 'fa-shield-halved',
                        'active_routes' => ['admin.settings.security', 'admin.settings.security.*'],
                    ],
                ],
            ],
        ];
    }

    if ($authUser) {
        if ($normalizedDepartment === 'admin' && $normalizedRole === 'head') {
            $displayRole = 'System Admin';
        } elseif ($normalizedRole === 'head') {
            $displayRole = ucfirst($normalizedDepartment) . ' Head';
        } elseif ($normalizedRole === 'staff') {
            $displayRole = ucfirst($normalizedDepartment) . ' Staff';
        } else {
            $displayRole = ucfirst($normalizedDepartment) . ' User';
        }
    } else {
        $displayRole = $userRole ?? ucfirst($normalizedDepartment) . ' User';
    }

    $nameParts = collect(preg_split('/\s+/', $displayName))->filter()->values();
    $initials = strtoupper(
        substr($nameParts->get(0, ''), 0, 1)
        . substr($nameParts->get(1, ''), 0, 1)
    ) ?: 'U';

    $canOpenMaintenanceSettings = $normalizedDepartment === 'maintenance'
        || ($normalizedDepartment === 'admin' && $normalizedRole === 'head');
@endphp

<aside class="sidebar" id="appSidebar">
    <button
        type="button"
        class="sidebar-collapse-btn"
        id="sidebarCollapseBtn"
        aria-label="Toggle sidebar"
        aria-expanded="true"
        title="Collapse sidebar"
    >
        <i class="fa-solid fa-chevron-left"></i>
    </button>

    <div class="brand">
        <div class="brand-icon">
            <i class="fa-solid {{ $icon }}"></i>
        </div>
        <div class="brand-text">
            <h2>{{ $department }}</h2>
            <p>{{ $subtitle }}</p>
        </div>
    </div>

    <nav class="menu">
        @foreach($items as $item)
            @php
                $hasChildren = isset($item['children'])
                    && is_array($item['children'])
                    && count($item['children']) > 0;
                $itemRoute = $item['route'] ?? null;
                $itemUrl = $item['url'] ?? null;
                $isParentActive = $menuItemIsActive($item);

                if ($hasChildren) {
                    foreach ($item['children'] as $child) {
                        if ($menuItemIsActive($child)) {
                            $isParentActive = true;
                            break;
                        }
                    }
                }
            @endphp

            @if($hasChildren)
                <div class="menu-dropdown {{ $isParentActive ? 'open active' : '' }}">
                    <button
                        type="button"
                        class="menu-item dropdown-toggle {{ $isParentActive ? 'active' : '' }}"
                        aria-expanded="{{ $isParentActive ? 'true' : 'false' }}"
                        title="{{ $item['label'] ?? 'Menu' }}"
                    >
                        <i class="fa-solid {{ $item['icon'] ?? 'fa-circle' }}"></i>
                        <span>{{ $item['label'] ?? 'Menu' }}</span>
                        <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                    </button>

                    <div class="submenu">
                        @foreach($item['children'] as $child)
                            @php
                                $childActive = $menuItemIsActive($child);
                            @endphp

                            @if(isset($child['url']))
                                <a
                                    href="{{ $child['url'] }}"
                                    class="submenu-item {{ $childActive ? 'active' : '' }}"
                                    title="{{ $child['label'] ?? 'Submenu' }}"
                                >
                                    <i class="fa-solid {{ $child['icon'] ?? 'fa-circle' }}"></i>
                                    <span>{{ $child['label'] ?? 'Submenu' }}</span>
                                </a>
                            @elseif(isset($child['route']) && \Illuminate\Support\Facades\Route::has($child['route']))
                                <a
                                    href="{{ route($child['route'], [], false) }}"
                                    class="submenu-item {{ $childActive ? 'active' : '' }}"
                                    title="{{ $child['label'] ?? 'Submenu' }}"
                                >
                                    <i class="fa-solid {{ $child['icon'] ?? 'fa-circle' }}"></i>
                                    <span>{{ $child['label'] ?? 'Submenu' }}</span>
                                </a>
                            @elseif(! isset($child['route']))
                                <div class="submenu-item submenu-item-disabled" title="{{ $child['label'] ?? 'Submenu' }}">
                                    <i class="fa-solid {{ $child['icon'] ?? 'fa-circle' }}"></i>
                                    <span>{{ $child['label'] ?? 'Submenu' }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @else
                @if($itemUrl)
                    <a
                        href="{{ $itemUrl }}"
                        class="menu-item {{ $isParentActive ? 'active' : '' }}"
                        title="{{ $item['label'] ?? 'Menu' }}"
                    >
                        <i class="fa-solid {{ $item['icon'] ?? 'fa-circle' }}"></i>
                        <span>{{ $item['label'] ?? 'Menu' }}</span>
                    </a>
                @elseif($itemRoute && \Illuminate\Support\Facades\Route::has($itemRoute))
                    <a
                        href="{{ route($itemRoute, [], false) }}"
                        class="menu-item {{ $isParentActive ? 'active' : '' }}"
                        title="{{ $item['label'] ?? 'Menu' }}"
                    >
                        <i class="fa-solid {{ $item['icon'] ?? 'fa-circle' }}"></i>
                        <span>{{ $item['label'] ?? 'Menu' }}</span>
                    </a>
                @endif
            @endif
        @endforeach
    </nav>

    <div class="sidebar-profile-wrap">
        <button
            type="button"
            class="user-box sidebar-profile-toggle"
            id="sidebarProfileToggle"
            aria-expanded="false"
            title="{{ $displayName }}"
        >
            <div class="avatar"><span>{{ $initials }}</span></div>
            <div class="user-box-text">
                <h4>{{ $displayName }}</h4>
                <p>{{ $displayRole }}</p>
            </div>
            <i class="fa-solid fa-chevron-down profile-chevron"></i>
        </button>

        <div class="sidebar-profile-menu" id="sidebarProfileMenu">
            <div class="profile-menu-header">
                <div class="profile-menu-avatar">{{ $initials }}</div>
                <div>
                    <h4>{{ $displayName }}</h4>
                    <p>{{ $displayRole }}</p>
                </div>
            </div>

            <div class="profile-menu-divider"></div>

            <button type="button" class="profile-menu-item" disabled>
                <i class="fa-solid fa-user"></i>
                <span>Profile</span>
            </button>

            @if($canOpenMaintenanceSettings && \Illuminate\Support\Facades\Route::has('settings'))
                <a href="{{ route('settings', [], false) }}" class="profile-menu-item">
                    <i class="fa-solid fa-gear"></i>
                    <span>Settings</span>
                </a>
            @else
                <button type="button" class="profile-menu-item" disabled>
                    <i class="fa-solid fa-gear"></i>
                    <span>Settings</span>
                </button>
            @endif

            <div class="profile-menu-divider"></div>

            @if(\Illuminate\Support\Facades\Route::has('logout'))
                <form action="{{ route('logout', [], false) }}" method="POST" class="profile-logout-form">
                    @csrf
                    <button type="submit" class="profile-menu-item logout">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>Log out</span>
                    </button>
                </form>
            @else
                <button type="button" class="profile-menu-item logout" disabled>
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Log out</span>
                </button>
            @endif
        </div>
    </div>
</aside>
