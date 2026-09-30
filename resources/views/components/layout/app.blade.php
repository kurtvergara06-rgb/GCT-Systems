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
    #gctPageLoader {
      position: fixed;
      inset: 0;
      z-index: 99999;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f8fafc;
    }

    .gct-page-loader-inner {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 12px;
      color: #183153;
      font-family: Poppins, sans-serif;
      font-size: 12px;
      font-weight: 600;
    }

    .gct-page-loader-spinner {
      width: 30px;
      height: 30px;
      border: 3px solid #d9e2ef;
      border-top-color: #183153;
      border-radius: 50%;
      animation: gctCriticalLoaderSpin .72s linear infinite;
    }

    @keyframes gctCriticalLoaderSpin {
      to { transform: rotate(360deg); }
    }
  </style>

  @vite($viteAssets)
  @stack('styles')
</head>

<body class="gct-page-entering">
  <div id="gctPageLoader" role="status" aria-live="polite" aria-label="Loading page">
    <div class="gct-page-loader-inner">
      <div class="gct-page-loader-spinner" aria-hidden="true"></div>
      <span>Loading...</span>
    </div>
  </div>

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
