<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>FROMS - Mechanic List</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  @vite([
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Maintenance/mechanic-list.css'
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

        <a href="{{ route('mechanic-list') }}" class="menu-item active">
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

    <!-- MAIN -->
    <main class="main">

      <!-- TOP BAR -->
      <header class="topbar">
        <div>
          <h1>Mechanic List</h1>
          <p>Monitor mechanic availability, assigned jobs, and work history</p>
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
            <i class="fa-solid fa-users-gear"></i>
          </div>

          <div>
            <p>Total Mechanics</p>
            <h2>30</h2>
            <small>All mechanics</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon green">
            <i class="fa-solid fa-user-check"></i>
          </div>

          <div>
            <p>Available Mechanics</p>
            <h2>8</h2>
            <small>Ready for assignment</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon red">
            <i class="fa-solid fa-user-clock"></i>
          </div>

          <div>
            <p>Not Available</p>
            <h2>15</h2>
            <small>Currently assigned</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon yellow">
            <i class="fa-solid fa-clipboard-check"></i>
          </div>

          <div>
            <p>Jobs Done Today</p>
            <h2>3</h2>
            <small>Completed jobs</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

      </section>

      <!-- MECHANIC LIST -->
      <section class="table-card">

        <div class="section-header">
          <div>
            <h2>Mechanic List</h2>
            <p>Monitor mechanic availability, assigned job orders, and working status</p>
          </div>
        </div>

        <div class="toolbar mechanic-toolbar">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" placeholder="Search mechanic name or availability...">
          </div>

          <div class="filter-group">
            <label>Date</label>
            <select>
              <option>Month Dates</option>
              <option>Today</option>
              <option>This Week</option>
            </select>
          </div>

          <div class="filter-group">
            <label>Status</label>
            <select>
              <option>All Types</option>
              <option>Available</option>
              <option>Not Available</option>
            </select>
          </div>

          <button class="primary-btn">
            <i class="fa-solid fa-plus"></i>
            New Mechanic
          </button>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Availability</th>
                <th>Job Order</th>
                <th>Time Started</th>
                <th>Time Ended</th>
                <th>Duration</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              <tr>
                <td>Priya Nair</td>
                <td><span class="badge not-available">Not Available</span></td>
                <td>Engine Oil Filter</td>
                <td>9:00 AM</td>
                <td>--:--</td>
                <td>--:--</td>
                <td>Apr 5, 2026</td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>Leo Fernandez</td>
                <td><span class="badge not-available">Not Available</span></td>
                <td>Brake System Repair</td>
                <td>10:00 AM</td>
                <td>--:--</td>
                <td>--:--</td>
                <td>Apr 4, 2026</td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>Joshua Garcia</td>
                <td><span class="badge not-available">Not Available</span></td>
                <td>Suspension Repair</td>
                <td>11:00 AM</td>
                <td>--:--</td>
                <td>--:--</td>
                <td>Apr 3, 2026</td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>Biboy Enriquez</td>
                <td><span class="badge available">Available</span></td>
                <td>--:--</td>
                <td>--:--</td>
                <td>--:--</td>
                <td>--:--</td>
                <td>Apr 3, 2026</td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>Jose Dimaano</td>
                <td><span class="badge available">Available</span></td>
                <td>--:--</td>
                <td>--:--</td>
                <td>--:--</td>
                <td>--:--</td>
                <td>Apr 3, 2026</td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>Leo De Ocampo</td>
                <td><span class="badge not-available">Not Available</span></td>
                <td>Suspension Repair</td>
                <td>12:00 PM</td>
                <td>--:--</td>
                <td>--:--</td>
                <td>Apr 3, 2026</td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>Rowell Latido</td>
                <td><span class="badge available">Available</span></td>
                <td>--:--</td>
                <td>--:--</td>
                <td>--:--</td>
                <td>--:--</td>
                <td>Apr 3, 2026</td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>Cardo Balba</td>
                <td><span class="badge available">Available</span></td>
                <td>--:--</td>
                <td>--:--</td>
                <td>--:--</td>
                <td>--:--</td>
                <td>Apr 3, 2026</td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>Richard Mendoza</td>
                <td><span class="badge not-available">Not Available</span></td>
                <td>Flat Tire Repair</td>
                <td>12:00 PM</td>
                <td>--:--</td>
                <td>--:--</td>
                <td>Apr 3, 2026</td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>Juan Del Mundo</td>
                <td><span class="badge not-available">Not Available</span></td>
                <td>Engine Oil Filter</td>
                <td>12:00 PM</td>
                <td>--:--</td>
                <td>--:--</td>
                <td>Apr 3, 2026</td>
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

      <!-- JOB HISTORY -->
      <section class="table-card history-card">

        <div class="section-header">
          <div>
            <h2>Job History</h2>
            <p>Completed work records and mechanic service history</p>
          </div>
        </div>

        <div class="toolbar mechanic-toolbar">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" placeholder="Search mechanic name or job order...">
          </div>

          <div class="filter-group">
            <label>Date</label>
            <select>
              <option>Month Dates</option>
              <option>Today</option>
              <option>This Week</option>
            </select>
          </div>

          <div class="filter-group">
            <label>Type</label>
            <select>
              <option>All Types</option>
              <option>PMS</option>
              <option>Repair</option>
            </select>
          </div>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Job Order</th>
                <th>Time Started</th>
                <th>Time Ended</th>
                <th>Duration</th>
                <th>Date</th>
              </tr>
            </thead>

            <tbody>
              <tr>
                <td>Priya Nair</td>
                <td>Engine Oil Filter</td>
                <td>9:00 AM</td>
                <td>11:00 AM</td>
                <td>2 hrs</td>
                <td>Apr 5, 2026</td>
              </tr>

              <tr>
                <td>Leo Fernandez</td>
                <td>Brake System Repair</td>
                <td>10:00 AM</td>
                <td>4:00 PM</td>
                <td>6 hrs</td>
                <td>Apr 4, 2026</td>
              </tr>

              <tr>
                <td>Joshua Garcia</td>
                <td>Suspension Repair</td>
                <td>11:00 AM</td>
                <td>5:00 PM</td>
                <td>6 hrs</td>
                <td>Apr 3, 2026</td>
              </tr>
            </tbody>
          </table>
        </div>

      </section>

    </main>

  </div>

</body>
</html>