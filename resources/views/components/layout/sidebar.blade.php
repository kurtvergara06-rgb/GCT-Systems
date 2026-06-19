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

  $displayName = trim($userName ?? $authUser?->name ?? 'Guest User');

  $roleRaw = strtolower(trim($authUser?->role ?? ''));
  $departmentRaw = trim($authUser?->department ?? $department);

  $roleRaw = str_replace(['_', '-'], ' ', $roleRaw);

  if (strtolower($departmentRaw) === 'admin') {
    $displayRole = 'System Admin';
  } elseif ($userRole) {
    $displayRole = $userRole;
  } elseif (str_contains($roleRaw, 'head')) {
    $displayRole = $departmentRaw . ' Head';
  } elseif (str_contains($roleRaw, 'staff')) {
    $displayRole = $departmentRaw . ' Staff';
  } else {
    $displayRole = $departmentRaw . ' User';
  }

  $nameParts = collect(explode(' ', $displayName))->filter()->values();

  $initials = strtoupper(
    substr($nameParts->get(0, ''), 0, 1) .
    substr($nameParts->get(1, ''), 0, 1)
  );

  $initials = $initials ?: 'U';
@endphp

<aside class="sidebar">

  {{-- BRAND --}}
  <div class="brand">
    <div class="brand-icon">
      <i class="fa-solid {{ $icon }}"></i>
    </div>

    <div>
      <h2>{{ $department }}</h2>
      <p>{{ $subtitle }}</p>
    </div>
  </div>

  {{-- MENU --}}
  <nav class="menu">
    @foreach($items as $item)

      @php
        $hasChildren = isset($item['children']) && is_array($item['children']) && count($item['children']) > 0;
        $itemRoute = $item['route'] ?? null;
        $isParentActive = false;

        if ($hasChildren) {
          foreach ($item['children'] as $child) {
            if (isset($child['route']) && request()->routeIs($child['route'])) {
              $isParentActive = true;
              break;
            }
          }
        } else {
          $isParentActive = $itemRoute ? request()->routeIs($itemRoute) : false;
        }
      @endphp

      @if($hasChildren)

        <div class="menu-dropdown {{ $isParentActive ? 'open active' : '' }}">
          <button
            type="button"
            class="menu-item dropdown-toggle {{ $isParentActive ? 'active' : '' }}"
            aria-expanded="{{ $isParentActive ? 'true' : 'false' }}"
          >
            <i class="fa-solid {{ $item['icon'] ?? 'fa-circle' }}"></i>
            <span>{{ $item['label'] ?? 'Menu' }}</span>
            <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
          </button>

          <div class="submenu">
            @foreach($item['children'] as $child)
              @if(isset($child['route']))
                <a
                  href="{{ route($child['route']) }}"
                  class="submenu-item {{ request()->routeIs($child['route']) ? 'active' : '' }}"
                >
                  <i class="fa-solid {{ $child['icon'] ?? 'fa-circle' }}"></i>
                  <span>{{ $child['label'] ?? 'Submenu' }}</span>
                </a>
              @endif
            @endforeach
          </div>
        </div>

      @else

        @if($itemRoute)
          <a
            href="{{ route($itemRoute) }}"
            class="menu-item {{ request()->routeIs($itemRoute) ? 'active' : '' }}"
          >
            <i class="fa-solid {{ $item['icon'] ?? 'fa-circle' }}"></i>
            <span>{{ $item['label'] ?? 'Menu' }}</span>
          </a>
        @endif

      @endif

    @endforeach
  </nav>

  {{-- USER PROFILE --}}
  <div class="sidebar-profile-wrap">

    <button
      type="button"
      class="user-box sidebar-profile-toggle"
      id="sidebarProfileToggle"
      aria-expanded="false"
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

    <div class="sidebar-profile-menu" id="sidebarProfileMenu">

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

      <button type="button" class="profile-menu-item" disabled>
        <i class="fa-solid fa-user"></i>
        <span>Profile</span>
      </button>

      @if(\Illuminate\Support\Facades\Route::has('settings'))
        <a href="{{ route('settings') }}" class="profile-menu-item">
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
        <form action="{{ route('logout') }}" method="POST" class="profile-logout-form">
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
