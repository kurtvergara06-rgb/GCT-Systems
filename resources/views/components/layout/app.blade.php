@props([
  'title' => 'GCT System',
  'assets' => []
])

@php
  $viteAssets = array_values(array_unique(array_merge([
    'resources/css/Main-styles/theme.css',
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
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

  @vite($viteAssets)
</head>

<body>
  {{ $slot }}

  <x-ui.action-buttom-modal
    mode="global-confirmation"
  />

  <x-ui.system-toast />
</body>
</html>
