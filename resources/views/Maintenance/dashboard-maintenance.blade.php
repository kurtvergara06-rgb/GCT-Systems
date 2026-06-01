<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>FROMS - Dashboard</title>

  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
  />

  @vite([
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Maintenance/dashboard.css'
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
        <a href="{{ route('dashboard-maintenance') }}" class="menu-item active">
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

    <!-- MAIN CONTENT -->
    <main class="main">

      <!-- TOP BAR -->
      <header class="topbar">
        <div>
          <h1>Dashboard</h1>
          <p>Monitor fleet maintenance operations and key service activities</p>
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

      <!-- ALERT -->
      <section class="alert-banner">
        <div class="alert-left">
          <i class="fa-solid fa-triangle-exclamation"></i>

          <div>
            <h3>Overdue Maintenance Alert</h3>
            <p>7 vehicles have overdue maintenance. Immediate attention is recommended to avoid breakdowns and ensure safety.</p>
          </div>
        </div>

        <button>View Overdue</button>
      </section>

      <!-- STATS -->
      <section class="stats-grid">

        <div class="stat-card">
          <div class="stat-icon blue">
            <i class="fa-solid fa-truck"></i>
          </div>

          <div>
            <p>Total Vehicles</p>
            <h2>124</h2>
            <small>All fleet vehicles</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon yellow">
            <i class="fa-solid fa-clipboard-list"></i>
          </div>

          <div>
            <p>Active Job Orders</p>
            <h2>18</h2>
            <small>In progress</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon red">
            <i class="fa-solid fa-triangle-exclamation"></i>
          </div>

          <div>
            <p>Critical Issues</p>
            <h2>5</h2>
            <small>Requires immediate action</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon green">
            <i class="fa-solid fa-check"></i>
          </div>

          <div>
            <p>Completed This Month</p>
            <h2>27</h2>
            <small>Job orders completed</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

      </section>

      <!-- CHARTS -->
      <section class="dashboard-grid">

        <div class="panel">
          <div class="panel-header">
            <h3>Job Order Status Distribution</h3>

            <select>
              <option>This Month</option>
            </select>
          </div>

          <div class="chart-content">
            <div class="donut-chart">
              <div class="donut-center">
                <span>Total</span>
                <h2>60</h2>
                <small>Job Orders</small>
              </div>
            </div>

            <div class="legend">
              <div>
                <span><i class="dot blue-dot"></i>In Progress</span>
                <b>20 (33%)</b>
              </div>

              <div>
                <span><i class="dot yellow-dot"></i>Open</span>
                <b>17 (28%)</b>
              </div>

              <div>
                <span><i class="dot green-dot"></i>Completed</span>
                <b>10 (17%)</b>
              </div>

              <div>
                <span><i class="dot gray-dot"></i>On Hold</span>
                <b>7 (11%)</b>
              </div>

              <div>
                <span><i class="dot red-dot"></i>Cancelled</span>
                <b>6 (11%)</b>
              </div>
            </div>
          </div>
        </div>

        <div class="panel">
          <div class="panel-header">
            <h3>Monthly Maintenance Costs Trend</h3>

            <select>
              <option>Last 6 Months</option>
            </select>
          </div>

          <div class="line-chart">
            <div class="chart-points">
              <span>$28.4K</span>
              <span>$31.7K</span>
              <span>$27.5K</span>
              <span>$36.2K</span>
              <span>$42.8K</span>
              <span>$38.9K</span>
            </div>

            <div class="chart-line"></div>

            <div class="months">
              <span>Dec</span>
              <span>Jan</span>
              <span>Feb</span>
              <span>Mar</span>
              <span>Apr</span>
              <span>May</span>
            </div>
          </div>
        </div>

      </section>

      <!-- BOTTOM CONTENT -->
      <section class="bottom-grid">

        <div class="panel">
          <div class="panel-header">
            <h3>Recent Activity</h3>
            <button class="small-btn">View All</button>
          </div>

          <div class="activity-list">

            <div class="activity-item">
              <div class="activity-icon blue">
                <i class="fa-solid fa-clipboard-list"></i>
              </div>

              <div>
                <h4>Job Order JO-2026-0843 was updated to In Progress</h4>
                <p>Vehicle GCT-1025 · Transmission issue</p>
              </div>

              <span>1h ago</span>
            </div>

            <div class="activity-item">
              <div class="activity-icon green">
                <i class="fa-solid fa-check"></i>
              </div>

              <div>
                <h4>Job Order JO-2026-0842 was completed</h4>
                <p>Vehicle GCT-1021 · Brake system check</p>
              </div>

              <span>2h ago</span>
            </div>

            <div class="activity-item">
              <div class="activity-icon yellow">
                <i class="fa-solid fa-cart-shopping"></i>
              </div>

              <div>
                <h4>Purchase Request PR-2026-0456 was approved</h4>
                <p>Brake pads and engine oil</p>
              </div>

              <span>4h ago</span>
            </div>

            <div class="activity-item">
              <div class="activity-icon red">
                <i class="fa-solid fa-triangle-exclamation"></i>
              </div>

              <div>
                <h4>New critical issue reported for GCT-1028</h4>
                <p>AC malfunction</p>
              </div>

              <span>6h ago</span>
            </div>

          </div>
        </div>

        <div class="panel">
          <div class="panel-header">
            <h3>Upcoming Maintenance</h3>
            <button class="small-btn">View All</button>
          </div>

          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Vehicle</th>
                <th>Maintenance Type</th>
                <th>Priority</th>
                <th>Assigned To</th>
              </tr>
            </thead>

            <tbody>
              <tr>
                <td>May 25, 2026</td>
                <td>GCT-1023</td>
                <td>Engine oil change</td>
                <td><span class="badge high">High</span></td>
                <td>R. Lim</td>
              </tr>

              <tr>
                <td>May 27, 2026</td>
                <td>GCT-1030</td>
                <td>Regular check-up</td>
                <td><span class="badge normal">Normal</span></td>
                <td>M. Cruz</td>
              </tr>

              <tr>
                <td>May 29, 2026</td>
                <td>GCT-1028</td>
                <td>AC inspection</td>
                <td><span class="badge high">High</span></td>
                <td>J. Santos</td>
              </tr>
            </tbody>
          </table>
        </div>

      </section>

    </main>

  </div>

</body>
</html>