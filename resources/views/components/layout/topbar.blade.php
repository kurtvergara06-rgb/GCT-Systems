@props([
  'title' => 'Page Title',
  'subtitle' => '',
  'notificationCount' => null,
])

<header class="topbar">
  <div>
    <h1>{{ $title }}</h1>

    @if($subtitle)
      <p>{{ $subtitle }}</p>
    @endif
  </div>

  <div class="top-actions">
    <button type="button" class="icon-btn notification">
      <i class="fa-regular fa-bell"></i>

      @if($notificationCount)
        <span>{{ $notificationCount }}</span>
      @endif
    </button>

    <button type="button" class="icon-btn">
      <i class="fa-regular fa-circle-question"></i>
    </button>

    <button type="button" class="icon-btn">
      <i class="fa-solid fa-user"></i>
    </button>
  </div>
</header>
