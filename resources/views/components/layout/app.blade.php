@props([
  'title' => 'GCT System',
  'assets' => []
])

@php
  $viteAssets = array_values(array_unique(array_merge([
    'resources/css/Main-styles/theme.css',
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Main-styles/admin-records.css',
    'resources/js/Main-js/sidebar.js',
    'resources/js/Main-js/confirmation-modal.js',
    'resources/js/app.js',
  ], $assets)));
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">

  <meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
  >

  <meta
    name="csrf-token"
    content="{{ csrf_token() }}"
  >

  @auth
    <meta
      name="gct-force-password-change"
      content="{{ auth()->user()->must_change_password ? '1' : '0' }}"
    >
  @endauth

  <title>{{ $title }}</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
  >

  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
  >

  <style>
    @view-transition {
      navigation: auto;
    }

    #appSidebar {
      view-transition-name: gct-sidebar;
    }

    main,
    .main {
      view-transition-name: gct-main-content;
    }

    ::view-transition-old(gct-sidebar),
    ::view-transition-new(gct-sidebar) {
      animation: none;
    }

    ::view-transition-old(gct-main-content) {
      animation: 120ms ease both gctCriticalMainOut;
    }

    ::view-transition-new(gct-main-content) {
      animation: 260ms cubic-bezier(.22, 1, .36, 1) both gctCriticalMainIn;
    }

    @keyframes gctCriticalMainOut {
      to {
        opacity: 0;
        transform: translateY(2px);
      }
    }

    @keyframes gctCriticalMainIn {
      from {
        opacity: 0;
        transform: translateY(8px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    #gctNavigationProgress {
      position: fixed;
      top: 0;
      left: var(--gct-sidebar-offset, 290px);
      right: 0;
      height: 3px;
      z-index: 5000;
      pointer-events: none;
      opacity: 0;
      overflow: hidden;
    }

    #gctNavigationProgress > span {
      display: block;
      width: 0;
      height: 100%;
      background: #f9b817;
      box-shadow: 0 0 8px rgba(249, 184, 23, .35);
      transition: width 180ms ease, opacity 160ms ease;
    }

    @media (max-width: 900px) {
      #gctNavigationProgress {
        left: 0;
      }
    }
  </style>

  @vite($viteAssets)
  @stack('styles')
</head>

<body>
  <div id="gctNavigationProgress" aria-hidden="true"><span></span></div>

  {{ $slot }}

  <x-ui.action-buttom-modal
    mode="global-confirmation"
  />

  <x-ui.system-toast />

  @if(request()->routeIs('operation.routes') && $errors->any())
    <script type="application/json" id="routeValidationOldInput">@json(session()->getOldInput())</script>
  @endif

  @stack('scripts')
</body>
</html>
