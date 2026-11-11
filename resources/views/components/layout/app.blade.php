@props([
  'title' => 'GCT System',
  'assets' => [],
  'partialNavigation' => true,
])

@php
  // Theme rollout is Operation-only for now. Other modules retain their CSS.
  $operationThemePage = collect($assets)->contains(
    fn ($asset) => is_string($asset) && str_starts_with($asset, 'resources/css/Operation/')
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

  @if($operationThemePage && auth()->check())
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
</body>
</html>
