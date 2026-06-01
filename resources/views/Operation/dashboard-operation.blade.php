<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>FROMS - Purchase Orders</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

  @vite([
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Purchase/purchase-orders.css'
  ])
</head>

<body>

<div class="app">

  <!-- SIDEBAR -->
  <aside class="sidebar">

    <div class="brand">
      <div class="brand-icon">
        <i class="fa-solid fa-truck"></i>
      </div>

      <div>
        <h2>Purchase</h2>
        <p>Department Module</p>
      </div>
    </div>

    <nav class="menu">
      <a href="{{ route('dashboard-operation') }}" class="menu-item active">
        <i class="fa-solid fa-table-cells-large"></i>
        <span>Dashboard</span>  
      </a>

      <a href="{{ route('available-mechanics') }}" class="menu-item">
        <i class="fa-solid fa-clipboard-list"></i>
        <span>Available Mechanics</span>
      </a>
    </nav>

    <div class="user-box">
      <div class="avatar">
        <i class="fa-solid fa-user"></i>
      </div>

      <div>
        <h4>R. Lim</h4>
        <p>Maintenance Admin</p>
      </div>

      <i class="fa-solid fa-chevron-down"></i>
    </div>

  </aside>
