<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>FROMS - PMS Scheduling</title>

  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
  />

  @vite([
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Maintenance/pms-scheduling.css'
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
        <a href="{{ route('dashboard-maintenance') }}" class="menu-item">
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

        <a href="{{ route('PMS-Scheduling') }}" class="menu-item active">
          <i class="fa-solid fa-calendar-check"></i>
          <span>PMS Scheduling</span>
        </a>

        <a href="{{ route('purchase-requests') }}" class="menu-item">
          <i class="fa-solid fa-file-invoice"></i>
          <span>Purchase Requests</span>
        </a>

        <a href="{{ route('fuel-reports') }}" class="menu-item">
          <i class="fa-solid fa-gas-pump"></i>
          <span>Fuel Reports</span>
        </a>

        <a href="{{ route('settings') }}" class="menu-item">
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

    <!-- MAIN -->
    <main class="main">

      <!-- TOP BAR -->
      <header class="topbar">
        <div>
          <h1>PMS Scheduling</h1>
          <p>Monitor automated preventive maintenance schedules based on GPS vehicle mileage data</p>
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
          <div class="stat-icon blue">
            <i class="fa-solid fa-file-lines"></i>
          </div>

          <div>
            <p>GPS Records Today</p>
            <h2>18</h2>
            <small>Updated mileage reports</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon green">
            <i class="fa-solid fa-calendar-check"></i>
          </div>

          <div>
            <p>Upcoming PMS</p>
            <h2>7</h2>
            <small>Scheduled maintenance</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon yellow">
            <i class="fa-solid fa-clock"></i>
          </div>

          <div>
            <p>Due Soon</p>
            <h2>4</h2>
            <small>Near PMS interval</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon red">
            <i class="fa-solid fa-triangle-exclamation"></i>
          </div>

          <div>
            <p>Overdue</p>
            <h2>2</h2>
            <small>Needs immediate action</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

      </section>

    <!-- PMS RECORD TABLE -->
<section class="table-card pms-card">

  <div class="section-header pms-header">
    <div>
      <h2>Automated PMS Record</h2>
      <p>The system uses GPS mileage data to compare the vehicle’s current KM against the next PMS interval</p>
    </div>
  </div>

  <div class="toolbar pms-toolbar">
    <div class="search-box">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="text" placeholder="Search vehicle or status...">
    </div>

    <div class="filter-group">
      <label>Status</label>
      <select>
        <option>All Status</option>
        <option>Upcoming</option>
        <option>Due Soon</option>
        <option>Overdue</option>
      </select>
    </div>
  </div>

  <div class="status-legend">
    <div><span class="dot green"></span>Upcoming</div>
    <div><span class="dot yellow"></span>Due Soon</div>
    <div><span class="dot red"></span>Overdue</div>
  </div>

  <div class="table-wrap">
    <table class="pms-table">
      <thead>
        <tr>
          <th>Vehicle ID</th>
          <th>GPS Report Date</th>
          <th>Current KM</th>
          <th>KM Traveled</th>
          <th>Last PMS KM</th>
          <th>Next PMS KM</th>
          <th>Maintenance Type</th>
          <th>Recommended Date</th>
          <th class="status-col">Status</th>
          <th>Action</th>
        </tr>
      </thead>

      <tbody>
        <tr>
          <td>BUS 0001</td>
          <td>Apr 22, 2026</td>
          <td>9,800 km</td>
          <td>180 km</td>
          <td>5,000 km</td>
          <td>10,000 km</td>
          <td>Preventive Maintenance</td>
          <td>Apr 30, 2026</td>
          <td class="status-col"><span class="badge due-soon">Due Soon</span></td>
          <td>
            <div class="actions">
              <button class="edit"><i class="fa-solid fa-pen"></i></button>
              <button class="delete"><i class="fa-solid fa-trash"></i></button>
            </div>
          </td>
        </tr>

        <tr>
          <td>BUS 0002</td>
          <td>Apr 22, 2026</td>
          <td>10,250 km</td>
          <td>220 km</td>
          <td>5,000 km</td>
          <td>10,000 km</td>
          <td>Oil Change</td>
          <td>Immediate</td>
          <td class="status-col"><span class="badge overdue">Overdue</span></td>
          <td>
            <div class="actions">
              <button class="edit"><i class="fa-solid fa-pen"></i></button>
              <button class="delete"><i class="fa-solid fa-trash"></i></button>
            </div>
          </td>
        </tr>

        <tr>
          <td>BUS 0003</td>
          <td>Apr 22, 2026</td>
          <td>7,300 km</td>
          <td>150 km</td>
          <td>5,000 km</td>
          <td>10,000 km</td>
          <td>Regular Check-up</td>
          <td>May 15, 2026</td>
          <td class="status-col"><span class="badge upcoming">Upcoming</span></td>
          <td>
            <div class="actions">
              <button class="edit"><i class="fa-solid fa-pen"></i></button>
              <button class="delete"><i class="fa-solid fa-trash"></i></button>
            </div>
          </td>
        </tr>

        <tr>
          <td>BUS 0004</td>
          <td>Apr 22, 2026</td>
          <td>12,800 km</td>
          <td>190 km</td>
          <td>10,000 km</td>
          <td>15,000 km</td>
          <td>Brake Inspection</td>
          <td>May 20, 2026</td>
          <td class="status-col"><span class="badge upcoming">Upcoming</span></td>
          <td>
            <div class="actions">
              <button class="edit"><i class="fa-solid fa-pen"></i></button>
              <button class="delete"><i class="fa-solid fa-trash"></i></button>
            </div>
          </td>
        </tr>

        <tr>
          <td>BUS 0005</td>
          <td>Apr 22, 2026</td>
          <td>14,750 km</td>
          <td>210 km</td>
          <td>10,000 km</td>
          <td>15,000 km</td>
          <td>Preventive Maintenance</td>
          <td>Apr 28, 2026</td>
          <td class="status-col"><span class="badge due-soon">Due Soon</span></td>
          <td>
            <div class="actions">
              <button class="edit"><i class="fa-solid fa-pen"></i></button>
              <button class="delete"><i class="fa-solid fa-trash"></i></button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

</section>

    </main>

  </div>

</body>
</html>