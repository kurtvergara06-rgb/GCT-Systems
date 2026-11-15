@props([
  'title' => 'GCT System',
  'assets' => [],
  'partialNavigation' => true,
])

@php
  // Dark page-specific components are enabled in Operation and Account.
  $operationThemePage = collect($assets)->contains(
    fn ($asset) => is_string($asset) && str_starts_with($asset, 'resources/css/Operation/')
  );
  // Account pages share the saved appearance preference and already have
  // dark CSS. Resolve their theme before the first-paint loading cover too.
  $accountThemePage = collect($assets)->contains(
    fn ($asset) => is_string($asset) && str_starts_with($asset, 'resources/css/Account/')
  );
  $performanceProfile = app()->environment('local') && request()->boolean('_gct_profile');
  $viteAssets = array_values(array_unique(array_merge([
    'resources/css/Main-styles/theme.css',
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Main-styles/sidebar-state.css',
    'resources/css/Main-styles/identifier-text.css',
    'resources/css/Main-styles/admin-records.css',
    'resources/css/Main-styles/table-toolbar.css',
    /*
     * Load the partial-navigation runtime as its own early Vite entry.
     * Maintenance page scripts register their modal/button initializers against
     * window.GCTPartialNavigation, so it must exist before independent page
     * entry modules such as job-order.js, pms-scheduling.js, fuel-reports.js,
     * and purchase-requests.js execute.
     */
    'resources/js/Main-js/partial-navigation.js',
    'resources/js/Main-js/sidebar.js',
    'resources/js/Main-js/confirmation-modal.js',
    'resources/js/app.js',
  ], $assets, [
    'resources/css/Operation/operation-action-buttons.css',
    'resources/css/Warehouse/warehouse-status.css',
    'resources/css/Main-styles/record-action-buttons.css',
    'resources/css/Main-styles/gct-scrollbar.css',
  ], request()->is('admin/*', 'analytics/*', 'batch-file-processing*')
    ? ['resources/css/Admin/administration-standards.css']
    : [],
    $operationThemePage ? ['resources/css/Operation/operation-dark-mode.css'] : []
  )));
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
    <meta name="gct-account-id" content="{{ auth()->id() }}">
    <meta name="gct-session-lifetime-seconds" content="{{ max(1, (int) config('session.lifetime', 120)) * 60 }}">
    <meta
      name="gct-force-password-change"
      content="{{ auth()->user()->must_change_password ? '1' : '0' }}"
    >
  @endauth

  <title>{{ $title }}</title>

  <meta name="gct-partial-navigation" content="{{ $partialNavigation ? 'enabled' : 'disabled' }}">

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

  @if(($operationThemePage || $accountThemePage) && auth()->check())
    {{-- Resolve the saved browser theme before styles paint to avoid a light flash. --}}
    <script>
      (() => {
        try {
          const key = @json('gct:ui-preferences:'.auth()->id());
          const saved = JSON.parse(localStorage.getItem(key) || '{}');
          const choice = ['light', 'dark', 'system'].includes(saved.theme) ? saved.theme : 'light';
          const theme = choice === 'system'
            ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
            : choice;
          document.documentElement.dataset.gctTheme = theme;
          document.documentElement.dataset.gctThemeChoice = choice;
          document.documentElement.style.colorScheme = theme;
        } catch (_) {
          document.documentElement.dataset.gctTheme = 'light';
        }
      })();
    </script>
  @endif

  {{-- Critical first-paint cover for full document navigation.
       Sidebar links intentionally reload the document to reinitialize page scripts.
       Keep all page content covered until CSS, scripts and layout have loaded. --}}
  <style>
    #gctBootLoader { display: none; }
    html.gct-page-booting #gctBootLoader {
      display: flex; position: fixed; inset: 0; z-index: 2147483000;
      align-items: center; justify-content: center;
      background: #f4f7fb; color: #334155; pointer-events: auto;
      font: 700 13px 'Poppins', Arial, sans-serif;
    }
    #gctBootLoader .gct-navigation-loader__content {
      display: flex; align-items: center; gap: 12px;
      padding: 12px 18px; min-height: 56px;
      border-radius: 12px; border: 1px solid #dce5f2;
      background: #fff; box-shadow: 0 10px 28px #0f172a14;
    }
    #gctBootLoader .gct-navigation-loader__spinner {
      display: block; width: 30px; height: 30px; flex: 0 0 30px;
      box-sizing: border-box; border: 3px solid #dbe4f0;
      border-top-color: #f9b817; border-right-color: #061f3d;
      border-radius: 50%; animation: gctNavigationSpin 620ms linear infinite;
    }
    html[data-gct-theme="dark"].gct-page-booting #gctBootLoader {
      background: #0b1220; color: #edf3fc;
    }
    html[data-gct-theme="dark"] #gctBootLoader .gct-navigation-loader__content {
      background: #162235; border-color: #35465e; color: #edf3fc;
    }
    html[data-gct-theme="dark"] #gctBootLoader .gct-navigation-loader__spinner {
      border-color: #35465e; border-top-color: #f9b817; border-right-color: #9bc7ff;
    }
    @keyframes gctNavigationSpin { to { transform: rotate(360deg); } }
  </style>
  <script>document.documentElement.classList.add('gct-page-booting');</script>

  @if($performanceProfile)
    <script>window.GCTNavigationDiagnostics = true;</script>
  @endif

  @if($performanceProfile)
    {{-- Use the production dependency graph for comparable local measurements,
         without touching another developer's running Vite server or hot file. --}}
    {{ (clone app(\Illuminate\Foundation\Vite::class))->useHotFile(storage_path('framework/profile-built-only.hot'))($viteAssets) }}
  @else
    @vite($viteAssets)
  @endif
  @stack('styles')
</head>

<body @if($operationThemePage) data-gct-operation-page="1" @endif @if($performanceProfile) data-gct-performance-profile="1" @endif>
  <div id="gctBootLoader" role="status" aria-live="polite" aria-label="Loading page">
    <div class="gct-navigation-loader__content">
      <span class="gct-navigation-loader__spinner" aria-hidden="true"></span>
      <span class="gct-navigation-loader__label">Loading...</span>
    </div>
  </div>
  {{ $slot }}

  <x-ui.action-buttom-modal
    mode="global-confirmation"
  />

  @unless(request()->boolean('embedded') && request()->routeIs('incidents.show'))
        <x-ui.system-toast />
        @endunless

  @if(request()->routeIs('operation.routes') && $errors->any())
    <script type="application/json" id="routeValidationOldInput">@json(session()->getOldInput())</script>
  @endif

  @stack('scripts')
  <script data-gct-full-load-only>
    (() => {
      const root = document.documentElement;
      let released = false;
      const release = () => {
        if (released) return;
        released = true;
        root.classList.remove('gct-page-booting');
        document.getElementById('gctBootLoader')?.remove();
      };
      const afterPaint = () => requestAnimationFrame(() => requestAnimationFrame(release));
      if (document.readyState === 'complete') afterPaint();
      else window.addEventListener('load', afterPaint, { once: true });
      // Never trap the user behind a loader if a third-party asset stalls.
      window.setTimeout(release, 10000);
      window.addEventListener('pageshow', (event) => {
        if (event.persisted) release();
      });
    })();
  </script>
</body>
</html>
