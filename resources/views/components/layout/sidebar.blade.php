@props([
  'department' => 'Department',
  'subtitle' => 'Department Module',
  'icon' => 'fa-table-cells-large',
  'userName' => 'Admin',
  'userRole' => 'System Admin',
  'items' => []
])

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

  {{-- USER BOX --}}
  <div class="user-box">
    <div class="avatar">
      <i class="fa-solid fa-user"></i>
    </div>

    <div>
      <h4>{{ $userName }}</h4>
      <p>{{ $userRole }}</p>
    </div>

    <i class="fa-solid fa-chevron-down"></i>
  </div>

</aside>