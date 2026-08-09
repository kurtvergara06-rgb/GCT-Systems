@props([
    'department' => 'Department',
    'subtitle' => 'Department Module',
    'icon' => 'fa-table-cells-large',
    'userName' => null,
    'userRole' => null,
    'items' => []
])

@php
    $authUser = auth()->user();

    $displayName = trim(
        $authUser?->name
        ?? $userName
        ?? 'Guest User'
    );

    $departmentRaw = trim(
        $authUser?->department
        ?? $department
        ?? 'Department'
    );

    $roleRaw = strtolower(trim(
        $authUser?->role
        ?? ''
    ));

    $normalizedDepartment = strtolower(
        str_replace(['_', '-'], ' ', $departmentRaw)
    );

    $normalizedRole = strtolower(
        str_replace(['_', '-'], ' ', $roleRaw)
    );

    $componentDepartment = strtolower(
        trim(str_replace(['_', '-'], ' ', $department))
    );

    /*
    |--------------------------------------------------------------------------
    | MAINTENANCE NAVIGATION
    |--------------------------------------------------------------------------
    */
    if ($componentDepartment === 'maintenance') {
        $items = [
            [
                'label' => 'Dashboard',
                'route' => 'maintenance-dashboard',
                'icon' => 'fa-table-cells-large',
            ],
            [
                'label' => 'Work Management',
                'icon' => 'fa-screwdriver-wrench',
                'children' => [
                    [
                        'label' => 'Job Orders',
                        'route' => 'job-orders',
                        'icon' => 'fa-clipboard-list',
                    ],
                    [
                        'label' => 'PMS Scheduling',
                        'route' => 'PMS-Scheduling',
                        'icon' => 'fa-calendar-check',
                    ],
                ],
            ],
            [
                'label' => 'Resources',
                'icon' => 'fa-toolbox',
                'children' => [
                    [
                        'label' => 'Mechanic Availability',
                        'route' => 'mechanic-list',
                        'icon' => 'fa-user-gear',
                    ],
                    [
                        'label' => 'Fuel Reports',
                        'route' => 'fuel-reports',
                        'icon' => 'fa-gas-pump',
                    ],
                ],
            ],
            [
                'label' => 'Purchase Requests',
                'route' => 'purchase-requests',
                'icon' => 'fa-file-invoice',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | PURCHASE NAVIGATION
    |--------------------------------------------------------------------------
    | Keep Purchase navigation consistent across every Purchase page. History
    | uses the existing maintenance-requests route with a dedicated view mode,
    | so no duplicate data or duplicate route/controller is required.
    */
    if ($componentDepartment === 'purchase') {
        $isPurchaseHistory =
            request()->routeIs('maintenance-requests')
            && request()->query('view') === 'history';

        $items = [
            [
                'label' => 'Dashboard',
                'route' => 'dashboard-purchase',
                'icon' => 'fa-table-cells-large',
            ],
            [
                'label' => 'Purchase Orders',
                'route' => 'purchase-orders',
                'icon' => 'fa-file-invoice',
            ],
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
                    [
                        'label' => 'Inventory Restock',
                        'route' => 'inventory-restock',
                        'icon' => 'fa-boxes-stacked',
                    ],
                ],
            ],
            [
                'label' => 'Purchase History',
                'url' => route('maintenance-requests', [], false) . '?view=history',
                'icon' => 'fa-clock-rotate-left',
                'active' => $isPurchaseHistory,
            ],
            [
                'label' => 'Scheduled Purchase',
                'route' => 'scheduled-purchase',
                'icon' => 'fa-calendar-check',
            ],
        ];
    }

    if ($authUser) {
        if (
            $normalizedDepartment === 'admin'
            && $normalizedRole === 'head'
        ) {
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

    $nameParts = collect(
        preg_split('/\s+/', $displayName)
    )
        ->filter()
        ->values();

    $initials = strtoupper(
        substr($nameParts->get(0, ''), 0, 1)
        . substr($nameParts->get(1, ''), 0, 1)
    );

    $initials = $initials ?: 'U';

    $canOpenMaintenanceSettings =
        $normalizedDepartment === 'maintenance'
        || (
            $normalizedDepartment === 'admin'
            && $normalizedRole === 'head'
        );
@endphp

<aside
    class="sidebar"
    id="appSidebar"
>
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
                $hasChildren =
                    isset($item['children'])
                    && is_array($item['children'])
                    && count($item['children']) > 0;

                $itemRoute = $item['route'] ?? null;
                $itemUrl = $item['url'] ?? null;
                $isParentActive = (bool) ($item['active'] ?? false);

                if ($hasChildren) {
                    foreach ($item['children'] as $child) {
                        $childActive = array_key_exists('active', $child)
                            ? (bool) $child['active']
                            : (
                                isset($child['route'])
                                && request()->routeIs($child['route'])
                            );

                        if ($childActive) {
                            $isParentActive = true;
                            break;
                        }
                    }
                } elseif (! array_key_exists('active', $item)) {
                    $isParentActive =
                        $itemRoute
                        ? request()->routeIs($itemRoute)
                        : false;
                }
            @endphp

            @if($hasChildren)
                <div
                    class="menu-dropdown {{ $isParentActive ? 'open active' : '' }}"
                >
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
                                $childActive = array_key_exists('active', $child)
                                    ? (bool) $child['active']
                                    : (
                                        isset($child['route'])
                                        && request()->routeIs($child['route'])
                                    );
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
                            @elseif(isset($child['route']) && Route::has($child['route']))
                                <a
                                    href="{{ route($child['route'], [], false) }}"
                                    class="submenu-item {{ $childActive ? 'active' : '' }}"
                                    title="{{ $child['label'] ?? 'Submenu' }}"
                                >
                                    <i class="fa-solid {{ $child['icon'] ?? 'fa-circle' }}"></i>
                                    <span>{{ $child['label'] ?? 'Submenu' }}</span>
                                </a>
                            @elseif(!isset($child['route']))
                                <div
                                    class="submenu-item submenu-item-disabled"
                                    title="{{ $child['label'] ?? 'Submenu' }}"
                                >
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
                @elseif(
                    $itemRoute
                    && \Illuminate\Support\Facades\Route::has($itemRoute)
                )
                    <a
                        href="{{ route($item['route'], [], false) }}"
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
            <div class="avatar">
                <span>{{ $initials }}</span>
            </div>

            <div class="user-box-text">
                <h4>{{ $displayName }}</h4>
                <p>{{ $displayRole }}</p>
            </div>

            <i class="fa-solid fa-chevron-down profile-chevron"></i>
        </button>

        <div
            class="sidebar-profile-menu"
            id="sidebarProfileMenu"
        >
            <div class="profile-menu-header">
                <div class="profile-menu-avatar">
                    {{ $initials }}
                </div>

                <div>
                    <h4>{{ $displayName }}</h4>
                    <p>{{ $displayRole }}</p>
                </div>
            </div>

            <div class="profile-menu-divider"></div>

            <button
                type="button"
                class="profile-menu-item"
                disabled
            >
                <i class="fa-solid fa-user"></i>
                <span>Profile</span>
            </button>

            @if(
                $canOpenMaintenanceSettings
                && \Illuminate\Support\Facades\Route::has('settings')
            )
                <a
                    href="{{ route('settings', [], false) }}"
                    class="profile-menu-item"
                >
                    <i class="fa-solid fa-gear"></i>
                    <span>Settings</span>
                </a>
            @else
                <button
                    type="button"
                    class="profile-menu-item"
                    disabled
                >
                    <i class="fa-solid fa-gear"></i>
                    <span>Settings</span>
                </button>
            @endif

            <div class="profile-menu-divider"></div>

            @if(\Illuminate\Support\Facades\Route::has('logout'))
                <form
                    action="{{ route('logout', [], false) }}"
                    method="POST"
                    class="profile-logout-form"
                >
                    @csrf

                    <button
                        type="submit"
                        class="profile-menu-item logout"
                    >
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>Log out</span>
                    </button>
                </form>
            @else
                <button
                    type="button"
                    class="profile-menu-item logout"
                    disabled
                >
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Log out</span>
                </button>
            @endif
        </div>
    </div>
</aside>
