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

    /* Suppress root transition to prevent full-screen/background flash */
    ::view-transition-old(root),
    ::view-transition-new(root) {
      animation: none;
    }
    ::view-transition-group(root) {
      animation-duration: 0s;
    }

    /* Keep sidebar pinned and visually stable */
    #appSidebar {
      view-transition-name: gct-sidebar;
      position: fixed;
      inset: 0 auto 0 0;
      width: 290px;
      min-height: 100vh;
      background: linear-gradient(180deg, #073763, #041f3d);
      z-index: 1000;
      box-sizing: border-box;
    }

    ::view-transition-group(gct-sidebar) {
      animation-duration: 0s;
    }

    ::view-transition-old(gct-sidebar),
    ::view-transition-new(gct-sidebar) {
      animation: none;
      mix-blend-mode: normal;
    }

    /* Main content view transition: soft 2px fade */
    main,
    .main {
      view-transition-name: gct-main-content;
      margin-left: 290px;
      flex: 1;
      min-width: 0;
    }

    ::view-transition-group(gct-main-content) {
      animation-duration: 280ms;
    }

    ::view-transition-old(gct-main-content) {
      animation: 160ms cubic-bezier(0.4, 0, 0.2, 1) both gctCriticalMainOut;
      mix-blend-mode: normal;
    }

    ::view-transition-new(gct-main-content) {
      animation: 280ms cubic-bezier(0.2, 0, 0, 1) both gctCriticalMainIn;
      mix-blend-mode: normal;
    }

    @keyframes gctCriticalMainOut {
      from {
        opacity: 1;
        transform: translateY(0);
      }
      to {
        opacity: 0;
        transform: translateY(2px);
      }
    }

    @keyframes gctCriticalMainIn {
      from {
        opacity: 0;
        transform: translateY(2px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Pre-navigation exit state */
    main.gct-main-leaving,
    .main.gct-main-leaving {
      opacity: 0 !important;
      transform: translateY(2px) !important;
      transition: opacity 200ms cubic-bezier(0.4, 0, 0.2, 1), transform 200ms cubic-bezier(0.4, 0, 0.2, 1) !important;
      pointer-events: none !important;
    }

    /* Fallback entrance */
    body.gct-initial-reveal main,
    body.gct-initial-reveal .main {
      animation: gctCriticalMainIn 280ms cubic-bezier(0.2, 0, 0, 1) both;
    }

    body.gct-initial-reveal main *,
    body.gct-initial-reveal .main * {
      animation-delay: 0s !important;
    }

    #gctNavigationProgress {
      position: fixed;
      top: 0;
      left: var(--gct-sidebar-offset, 290px);
      right: 0;
      height: 2.5px;
      z-index: 5000;
      pointer-events: none;
      opacity: 0;
      overflow: hidden;
      transition: opacity 160ms ease;
    }

    #gctNavigationProgress.is-visible {
      opacity: 1;
    }

    #gctNavigationProgress > span {
      display: block;
      width: 100%;
      height: 100%;
      background: #f9b817;
      box-shadow: 0 0 6px rgba(249, 184, 23, .35);
      transform: scaleX(0);
      transform-origin: left center;
      will-change: transform;
      transition: transform 240ms cubic-bezier(.22, 1, .36, 1);
    }

    @media (max-width: 900px) {
      #appSidebar {
        width: 100%;
      }
      main,
      .main {
        margin-left: 0;
      }
      #gctNavigationProgress {
        left: 0;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      ::view-transition-group(*),
      ::view-transition-old(*),
      ::view-transition-new(*) {
        animation: none !important;
      }
      main,
      .main,
      main.gct-main-leaving,
      .main.gct-main-leaving,
      body.gct-initial-reveal main,
      body.gct-initial-reveal .main {
        animation: none !important;
        transition: none !important;
        transform: none !important;
        opacity: 1 !important;
      }
      #gctNavigationProgress {
        display: none !important;
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
