<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>FROMS - Fuel Reports</title>

  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
  />

  @vite([
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Maintenance/fuel-reports.css'
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

        <a href="{{ route('fuel-reports') }}" class="menu-item active">
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
          <h1>Fuel Reports</h1>
          <p>Track vehicle fuel efficiency, fuel usage, and inefficient trips</p>
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
            <i class="fa-solid fa-gas-pump"></i>
          </div>

          <div>
            <p>Total Fuel Used</p>
            <h2>2,153 L</h2>
            <small>Recorded fuel consumption</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon green">
            <i class="fa-solid fa-road"></i>
          </div>

          <div>
            <p>Total Distance</p>
            <h2>7,480 km</h2>
            <small>Distance travelled</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon yellow">
            <i class="fa-solid fa-chart-line"></i>
          </div>

          <div>
            <p>Fleet Average</p>
            <h2>3.47</h2>
            <small>Average km/L</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon red">
            <i class="fa-solid fa-triangle-exclamation"></i>
          </div>

          <div>
            <p>Inefficient Vehicles</p>
            <h2>2</h2>
            <small>Needs checking</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

      </section>

      <!-- EFFICIENCY BY VEHICLE -->
      <section class="table-card fuel-card">

        <div class="section-header">
          <div>
            <h2>Efficiency by Vehicle</h2>
            <p>Summary of fuel efficiency per vehicle based on recorded fuel data</p>
          </div>
        </div>

        <div class="toolbar fuel-toolbar">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" placeholder="Search vehicle">
          </div>

          <div class="filter-group">
            <label>Date</label>
            <select>
              <option>This Month</option>
              <option>This Week</option>
              <option>Today</option>
            </select>
          </div>

          <button class="primary-btn">
            <i class="fa-solid fa-plus"></i>
            Add Fuel Record
          </button>
        </div>

        <div class="table-wrap">
          <table class="fuel-table">
            <thead>
              <tr>
                <th>Vehicle</th>
                <th>Total KM</th>
                <th>Total L</th>
                <th>Total KM/L</th>
                <th>VS Fleet Avg</th>
                <th>Entries</th>
                <th>Status</th>
              </tr>
            </thead>

            <tbody>
              <tr>
                <td>BUS 001</td>
                <td>1,296</td>
                <td>370.3</td>
                <td>3.50</td>
                <td>-3.0%</td>
                <td>6</td>
                <td><span class="badge normal">Normal</span></td>
              </tr>

              <tr>
                <td>BUS 002</td>
                <td>1,266</td>
                <td>312.0</td>
                <td>4.06</td>
                <td>+12.4%</td>
                <td>7</td>
                <td><span class="badge efficient">Efficient</span></td>
              </tr>

              <tr class="danger-row">
                <td>BUS 003</td>
                <td>1,270</td>
                <td>453.6</td>
                <td>2.80</td>
                <td>-22.4%</td>
                <td>6</td>
                <td>
                  <span class="badge inefficient">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Inefficient
                  </span>
                </td>
              </tr>

              <tr>
                <td>BUS 004</td>
                <td>1,229</td>
                <td>279.4</td>
                <td>4.40</td>
                <td>+21.9%</td>
                <td>7</td>
                <td><span class="badge efficient">Efficient</span></td>
              </tr>

              <tr class="danger-row">
                <td>BUS 005</td>
                <td>1,197</td>
                <td>299.3</td>
                <td>4.00</td>
                <td>-10.8%</td>
                <td>6</td>
                <td>
                  <span class="badge inefficient">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Inefficient
                  </span>
                </td>
              </tr>

              <tr>
                <td>BUS 006</td>
                <td>1,217</td>
                <td>438.2</td>
                <td>2.90</td>
                <td>-19.6%</td>
                <td>7</td>
                <td><span class="badge normal">Normal</span></td>
              </tr>
            </tbody>
          </table>
        </div>

      </section>

      <!-- RECENT FUEL RECORDS -->
      <section class="table-card fuel-card">

        <div class="section-header">
          <div>
            <h2>Recent Fuel Records</h2>
            <p>Latest fuel entries recorded for each vehicle</p>
          </div>
        </div>

        <div class="table-wrap">
          <table class="fuel-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Vehicle</th>
                <th>Distance</th>
                <th>Fuel</th>
                <th>KM/L</th>
                <th>Driver</th>
              </tr>
            </thead>

            <tbody>
              <tr>
                <td>Apr 29, 2026</td>
                <td>BUS 001</td>
                <td>240 km</td>
                <td>58.3 L</td>
                <td>3.0</td>
                <td>Jose Cruz</td>
              </tr>

              <tr>
                <td>Apr 29, 2026</td>
                <td>BUS 002</td>
                <td>3 km</td>
                <td>4.0 L</td>
                <td>0.75</td>
                <td>Rodolfo Castillo</td>
              </tr>

              <tr>
                <td>Apr 26, 2026</td>
                <td>BUS 003</td>
                <td>239 km</td>
                <td>58.3 L</td>
                <td>4.10</td>
                <td>Marg Patel</td>
              </tr>

              <tr>
                <td>Apr 24, 2026</td>
                <td>BUS 004</td>
                <td>195 km</td>
                <td>70.0 L</td>
                <td>2.80</td>
                <td>Ricky Okafor</td>
              </tr>

              <tr>
                <td>Apr 24, 2026</td>
                <td>BUS 005</td>
                <td>216 km</td>
                <td>43.0 L</td>
                <td>4.40</td>
                <td>Sung Lee</td>
              </tr>

              <tr>
                <td>Apr 23, 2026</td>
                <td>BUS 006</td>
                <td>438.2 km</td>
                <td>48.0 L</td>
                <td>4.00</td>
                <td>Nestor Ocampo</td>
              </tr>
            </tbody>
          </table>
        </div>

      </section>

    </main>

  </div>

</body>
</html>