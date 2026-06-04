@props([
  'department' => 'Department',
  'subtitle' => 'Department Module',
  'icon' => 'fa-table-cells-large',
  'userName' => 'Admin',
  'userRole' => 'System Admin',
  'items' => []
])

<aside class="sidebar">

  <div class="brand">
    <div class="brand-icon">
      <i class="fa-solid {{ $icon }}"></i>
    </div>

    <div>
      <h2>{{ $department }}</h2>
      <p>{{ $subtitle }}</p>
    </div>
  </div>

  <nav class="menu">
    @foreach($items as $item)

      @php
        $hasChildren = isset($item['children']) && count($item['children']) > 0;

        $isParentActive = false;

        if ($hasChildren) {
          foreach ($item['children'] as $child) {
            if (request()->routeIs($child['route'])) {
              $isParentActive = true;
              break;
            }
          }
        }
      @endphp

      @if($hasChildren)

        <div class="menu-dropdown {{ $isParentActive ? 'open' : '' }}">
          <button type="button" class="menu-item dropdown-toggle {{ $isParentActive ? 'active' : '' }}">
            <i class="fa-solid {{ $item['icon'] }}"></i>
            <span>{{ $item['label'] }}</span>
            <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
          </button>

          <div class="submenu">
            @foreach($item['children'] as $child)
              <a
                href="{{ route($child['route']) }}"
                class="submenu-item {{ request()->routeIs($child['route']) ? 'active' : '' }}"
              >
                <i class="fa-solid {{ $child['icon'] }}"></i>
                <span>{{ $child['label'] }}</span>
              </a>
            @endforeach
          </div>
        </div>

      @else

        <a
          href="{{ route($item['route']) }}"
          class="menu-item {{ request()->routeIs($item['route']) ? 'active' : '' }}"
        >
          <i class="fa-solid {{ $item['icon'] }}"></i>
          <span>{{ $item['label'] }}</span>
        </a>

      @endif

    @endforeach
  </nav>

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