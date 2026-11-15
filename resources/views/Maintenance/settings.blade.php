<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>FROMS - Maintenance Module</title>
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
  />

@vite([
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/js/Maintenance/settings.js'
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
          <h2>Maintenance</h2>
          <p>Department Module</p>
        </div>
      </div>

      <nav class="menu">
        <a href="{{ route('dashboard') }}" class="menu-item">
          <i class="fa-solid fa-table-cells-large"></i>
          <span>Dashboard</span>
        </a>

        <a href="{{ route('job-orders') }}" class="menu-item">
          <i class="fa-solid fa-clipboard-list"></i>
          <span>Job Orders</span>
        </a>

        <a href="{{ route('mechanic-list') }}" class="menu-item">
          <i class="fa-solid fa-bus"></i>
          <span>Mechanic List</span>
        </a>

         <a href="{{ route('PMS-Scheduling') }}" class="menu-item">
          <i class="fa-solid fa-calendar-check"></i>
          <span>PMS Scheduling</span>
        </a>

        <a href="{{ route('purchase-requests') }}" class="menu-item">
          <i class="fa-solid fa-file-invoice"></i>
          <span>Purchase Requests</span>
        </a>

        <a href="{{ route('fuel-reports') }}" class="menu-item">
          <i class="fa-solid fa-file-lines"></i>
          <span>Fuel Reports</span>
        </a>

        <a href="{{ route('settings') }}" class="menu-item active">
          <i class="fa-solid fa-gear"></i>
          <span>Settings</span>
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