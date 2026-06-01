<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>FROMS - Job Orders</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  @vite([
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Maintenance/job-order.css'
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

        <a href="{{ route('job-orders') }}" class="menu-item active">
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

    <!-- MAIN -->
    <main class="main">

      <!-- TOP BAR -->
      <header class="topbar">
        <div>
          <h1>Job Orders</h1>
          <p>Manage repair and preventive maintenance service requests</p>
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
          <div class="stat-icon yellow">
            <i class="fa-solid fa-pause"></i>
          </div>

          <div>
            <p>On Hold</p>
            <h2>3</h2>
            <small>Job Orders</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon blue">
            <i class="fa-solid fa-spinner"></i>
          </div>

          <div>
            <p>On Going</p>
            <h2>1</h2>
            <small>Job Order</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon green">
            <i class="fa-solid fa-check"></i>
          </div>

          <div>
            <p>Completed</p>
            <h2>5</h2>
            <small>Job Orders</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon red">
            <i class="fa-solid fa-triangle-exclamation"></i>
          </div>

          <div>
            <p>Urgent Repair</p>
            <h2>2</h2>
            <small>Needs attention</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

      </section>

      <!-- JOB ORDERS TABLE -->
      <section class="table-card">

        <div class="section-header">
          <div>
            <h2>Job Orders</h2>
            <p>Track service requests, assigned mechanics, and job order status</p>
          </div>
        </div>

        <div class="toolbar job-toolbar">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" placeholder="Search service, JO no., bus, or mechanic...">
          </div>

          <div class="filter-group">
            <label>Status</label>
            <select>
              <option>All Statuses</option>
              <option>On Hold</option>
              <option>On Going</option>
              <option>Completed</option>
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

          <button class="primary-btn">
            <i class="fa-solid fa-plus"></i>
            New JO
          </button>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>
                  <input type="checkbox">
                </th>
                <th>JO No.</th>
                <th>Bus #</th>
                <th>Service</th>
                <th>Type</th>
                <th>Assigned Mechanic</th>
                <th>Status</th>
                <th>Duration</th>
                <th>Date Reported</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              <tr>
                <td><input type="checkbox"></td>
                <td>JO-26-0001</td>
                <td>BUS-001</td>
                <td>Engine Oil Filter</td>
                <td>PMS</td>
                <td class="empty">—</td>
                <td><span class="badge hold">On Hold</span></td>
                <td class="empty">—</td>
                <td>Apr 5, 2026 09:30 AM</td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td><input type="checkbox"></td>
                <td>JO-26-0011</td>
                <td>BUS-003</td>
                <td>Transmission</td>
                <td>Repair</td>
                <td>Leo Fernandez</td>
                <td><span class="badge ongoing">On Going</span></td>
                <td>9:00 AM | --:--</td>
                <td>Apr 5, 2026 09:30 AM</td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td><input type="checkbox"></td>
                <td>JO-26-0012</td>
                <td>BUS-004</td>
                <td>Brake</td>
                <td>Repair</td>
                <td>Ronald Mendoza</td>
                <td><span class="badge completed">Completed</span></td>
                <td>9:00 AM | 3:00 PM</td>
                <td>Apr 5, 2026 09:30 AM</td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td><input type="checkbox"></td>
                <td>JO-26-0031</td>
                <td>BUS-006</td>
                <td>
                  Engine Oil Filter
                  <span class="sub-text">Transmission</span>
                </td>
                <td>PMS</td>
                <td>Roy Muntalban</td>
                <td><span class="badge completed">Completed</span></td>
                <td>9:00 AM | 3:00 PM</td>
                <td>Apr 5, 2026 09:30 AM</td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td><input type="checkbox"></td>
                <td>JO-26-0032</td>
                <td>BUS-010</td>
                <td>Engine Oil Filter</td>
                <td>Repair</td>
                <td>Rowell Aspanao</td>
                <td><span class="badge completed">Completed</span></td>
                <td>9:00 AM | 3:00 PM</td>
                <td>Apr 5, 2026 09:30 AM</td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td><input type="checkbox"></td>
                <td>JO-26-0032</td>
                <td>BUS-010</td>
                <td>Engine Oil Filter</td>
                <td>PMS</td>
                <td class="empty">—</td>
                <td><span class="badge hold">On Hold</span></td>
                <td class="empty">—</td>
                <td>Apr 5, 2026 09:30 AM</td>
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

        <div class="table-footer">
          <p>Showing 1 to 6 of 9 entries</p>

          <div class="pagination">
            <button><i class="fa-solid fa-chevron-left"></i></button>
            <button class="active-page">1</button>
            <button>2</button>
            <button><i class="fa-solid fa-chevron-right"></i></button>
          </div>
        </div>

      </section>

    </main>

  </div>

</body>
</html>