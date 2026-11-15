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
      <a
        href="{{ route($item['route']) }}"
        class="menu-item {{ request()->routeIs($item['route']) ? 'active' : '' }}"
      >
        <i class="fa-solid {{ $item['icon'] }}"></i>
        <span>{{ $item['label'] }}</span>
      </a>
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