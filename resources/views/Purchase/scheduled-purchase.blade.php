<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>FROMS - Scheduled Purchases</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

  @vite([
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Purchase/scheduled-purchase.css'
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
      <a href="{{ route('purchase-orders') }}" class="menu-item">
        <i class="fa-solid fa-table-cells-large"></i>
        <span>Purchase Orders</span>
      </a>

      <a href="{{ route('requested-purchase') }}" class="menu-item">
        <i class="fa-solid fa-clipboard-list"></i>
        <span>Requested Purchases</span>
      </a>

      <a href="{{ route('scheduled-purchase') }}" class="menu-item active">
        <i class="fa-solid fa-calendar-check"></i>
        <span>Scheduled Purchases</span>
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

  <!-- MAIN -->
  <main class="main">

    <!-- TOP BAR -->
    <header class="topbar">
      <div>
        <h1>Scheduled Purchase</h1>
        <p>Manage recurring procurement schedules for shuttle operations</p>
      </div>

      <div class="top-actions">
        <button class="icon-btn notification">
          <i class="fa-regular fa-bell"></i>
          <span>6</span>
        </button>

        <button class="icon-btn">
          <i class="fa-regular fa-circle-question"></i>
        </button>

        <button class="icon-btn">
          <i class="fa-solid fa-user"></i>
        </button>
      </div>
    </header>

    <!-- SUMMARY CARDS -->
   <section class="stats-grid">

  <div class="stat-card">
    <div class="stat-icon green">
      <i class="fa-solid fa-calendar-days"></i>
    </div>

    <div>
      <p>Total Schedules</p>
      <h2>6</h2>
      <small>Recurring schedules</small>
    </div>

    <i class="fa-solid fa-chevron-right arrow"></i>
  </div>

  <div class="stat-card">
    <div class="stat-icon yellow">
      <i class="fa-solid fa-play"></i>
    </div>

    <div>
      <p>Active</p>
      <h2>5</h2>
      <small>Running schedules</small>
    </div>

    <i class="fa-solid fa-chevron-right arrow"></i>
  </div>

  <div class="stat-card">
    <div class="stat-icon blue">
      <i class="fa-solid fa-pause"></i>
    </div>

    <div>
      <p>Paused</p>
      <h2>1</h2>
      <small>Temporarily stopped</small>
    </div>

    <i class="fa-solid fa-chevron-right arrow"></i>
  </div>

  <div class="stat-card">
    <div class="stat-icon red">
      <i class="fa-solid fa-clock"></i>
    </div>

    <div>
      <p>Due This Month</p>
      <h2>2</h2>
      <small>Upcoming purchases</small>
    </div>

    <i class="fa-solid fa-chevron-right arrow"></i>
  </div>

</section>
    <!-- SCHEDULED PURCHASE CONTAINER -->
    <section class="schedule-card">

      <div class="toolbar">

        <div class="search-box">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" placeholder="Search by item, order number, or supplier...">
        </div>

        <select>
          <option>All Categories</option>
          <option>Fuel</option>
          <option>Lubricants</option>
          <option>Spare Parts</option>
        </select>

        <select>
          <option>All Status</option>
          <option>Active</option>
          <option>Paused</option>
        </select>

        <button class="primary-btn">
          <i class="fa-solid fa-plus"></i>
          Add Supplier
        </button>

      </div>

      <div class="supplier-grid">

        <div class="supplier-card">

          <div class="supplier-header">
            <div class="supplier-title">
              <div class="supplier-icon">
                <i class="fa-solid fa-building"></i>
              </div>

              <div>
                <h3>Diesel Masters</h3>
                <p>Roy Cruz</p>
              </div>
            </div>

            <span class="status active">Active</span>
          </div>

          <div class="supplier-body">
            <p><i class="fa-solid fa-phone"></i> 09281234567</p>
            <p><i class="fa-regular fa-envelope"></i> roy@dieselmasters.com</p>

            <div class="tags">
              <span>Fuel</span>
              <span>Lubricants</span>
            </div>
          </div>

          <div class="supplier-footer">
            <span>No expiry set</span>

            <button>
              <i class="fa-solid fa-pen-to-square"></i>
            </button>
          </div>

        </div>

        <div class="supplier-card">

          <div class="supplier-header">
            <div class="supplier-title">
              <div class="supplier-icon">
                <i class="fa-solid fa-building"></i>
              </div>

              <div>
                <h3>Diesel Masters</h3>
                <p>Roy Cruz</p>
              </div>
            </div>

            <span class="status active">Active</span>
          </div>

          <div class="supplier-body">
            <p><i class="fa-solid fa-phone"></i> 09281234567</p>
            <p><i class="fa-regular fa-envelope"></i> roy@dieselmasters.com</p>

            <div class="tags">
              <span>Fuel</span>
              <span>Lubricants</span>
            </div>
          </div>

          <div class="supplier-footer">
            <span>No expiry set</span>

            <button>
              <i class="fa-solid fa-pen-to-square"></i>
            </button>
          </div>

        </div>

        <div class="supplier-card">

          <div class="supplier-header">
            <div class="supplier-title">
              <div class="supplier-icon">
                <i class="fa-solid fa-building"></i>
              </div>

              <div>
                <h3>Diesel Masters</h3>
                <p>Roy Cruz</p>
              </div>
            </div>

            <span class="status active">Active</span>
          </div>

          <div class="supplier-body">
            <p><i class="fa-solid fa-phone"></i> 09281234567</p>
            <p><i class="fa-regular fa-envelope"></i> roy@dieselmasters.com</p>

            <div class="tags">
              <span>Fuel</span>
              <span>Lubricants</span>
            </div>
          </div>

          <div class="supplier-footer">
            <span>No expiry set</span>

            <button>
              <i class="fa-solid fa-pen-to-square"></i>
            </button>
          </div>

        </div>

      </div>

    </section>

  </main>

</div>

</body>
</html>