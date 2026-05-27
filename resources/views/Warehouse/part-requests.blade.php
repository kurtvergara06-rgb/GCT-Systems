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
    'resources/css/Warehouse/part-requests.css'
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
          <h2>Warehouse</h2>
          <p>Department Module</p>
        </div>
      </div>

      <nav class="menu">
        <a href="{{ route('Inventory') }}" class="menu-item">
          <i class="fa-solid fa-table-cells-large"></i>
          <span>Inventory</span>
        </a>

          <a href="{{ route('part-requests') }}" class="menu-item active">
          <i class="fa-solid fa-table-cells-large"></i>
          <span>Part Requests</span>
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
          <h1>Requested Purchase</h1>
          <p>Manage procurement records for vehicle parts, equipment, and operational materials</p>
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
            <i class="fa-solid fa-clock"></i>
          </div>

          <div>
            <p>Pending Requests</p>
            <h2>4</h2>
            <small>Waiting for approval</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon green">
            <i class="fa-solid fa-check"></i>
          </div>

          <div>
            <p>Approved Requests</p>
            <h2>3</h2>
            <small>Ready for purchase</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon red">
            <i class="fa-solid fa-xmark"></i>
          </div>

          <div>
            <p>Rejected Requests</p>
            <h2>3</h2>
            <small>Not approved</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon blue">
            <i class="fa-solid fa-file-invoice"></i>
          </div>

          <div>
            <p>Total Requests</p>
            <h2>10</h2>
            <small>Procurement records</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

      </section>

  <!-- REQUESTED PURCHASE TABLE -->
<section class="table-card purchase-card">

  <div class="section-header">
    <div>
      <h2>Requested Purchase Records</h2>
      <p>Track requested items, urgency level, approval status, and related job orders</p>
    </div>
  </div>

  <div class="toolbar request-toolbar">
    <div class="search-box">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="text" placeholder="Search by item, order number, or supplier...">
    </div>

    <div class="filter-group">
      <label>Category</label>
      <select>
        <option>All Categories</option>
        <option>Engine Parts</option>
        <option>Brake System</option>
        <option>Cooling System</option>
        <option>Operational Materials</option>
      </select>
    </div>

    <div class="filter-group">
      <label>Status</label>
      <select>
        <option>All States</option>
        <option>Pending</option>
        <option>Approved</option>
        <option>Rejected</option>
      </select>
    </div>
  </div>

  <div class="table-wrap">
    <table class="request-table">
      <thead>
        <tr>
          <th>Request No.</th>
          <th>JO Reference</th>
          <th>Requested By</th>
          <th>Urgency</th>
          <th>Status</th>
          <th>Date</th>
          <th>Items</th>
          <th>Action</th>
        </tr>
      </thead>

      <tbody>
        <tr>
          <td><strong>PR-001</strong></td>
          <td><strong>JO-2026-001</strong></td>
          <td><strong>Maria Santos</strong></td>
          <td><span class="badge high">High</span></td>
          <td><span class="badge pending">Pending</span></td>
          <td>2026-05-10</td>
          <td>Brake Pads (2), Oil Filter (1)</td>
          <td><a href="#" class="view-link">View →</a></td>
        </tr>

        <tr>
          <td><strong>PR-002</strong></td>
          <td><strong>JO-2026-002</strong></td>
          <td><strong>Maria Santos</strong></td>
          <td><span class="badge low">Low</span></td>
          <td><span class="badge approved">Approved</span></td>
          <td>2026-05-10</td>
          <td>Air Filter (1), Coolant (2)</td>
          <td><a href="#" class="view-link">View →</a></td>
        </tr>

        <tr>
          <td><strong>PR-003</strong></td>
          <td><strong>JO-2026-003</strong></td>
          <td><strong>Maria Santos</strong></td>
          <td><span class="badge high">High</span></td>
          <td><span class="badge rejected">Rejected</span></td>
          <td>2026-05-10</td>
          <td>Brake Pads (2), Oil Filter (1)</td>
          <td><a href="#" class="view-link">View →</a></td>
        </tr>

        <tr>
          <td><strong>PR-004</strong></td>
          <td><strong>JO-2026-004</strong></td>
          <td><strong>Maria Santos</strong></td>
          <td><span class="badge low">Low</span></td>
          <td><span class="badge pending">Pending</span></td>
          <td>2026-05-10</td>
          <td>Fuel Filter (1)</td>
          <td><a href="#" class="view-link">View →</a></td>
        </tr>

        <tr>
          <td><strong>PR-005</strong></td>
          <td><strong>JO-2026-005</strong></td>
          <td><strong>Maria Santos</strong></td>
          <td><span class="badge high">High</span></td>
          <td><span class="badge approved">Approved</span></td>
          <td>2026-05-10</td>
          <td>Engine Oil (4), Oil Filter (1)</td>
          <td><a href="#" class="view-link">View →</a></td>
        </tr>

        <tr>
          <td><strong>PR-006</strong></td>
          <td><strong>JO-2026-006</strong></td>
          <td><strong>Maria Santos</strong></td>
          <td><span class="badge low">Low</span></td>
          <td><span class="badge rejected">Rejected</span></td>
          <td>2026-05-10</td>
          <td>Tire Valve (4)</td>
          <td><a href="#" class="view-link">View →</a></td>
        </tr>

        <tr>
          <td><strong>PR-007</strong></td>
          <td><strong>JO-2026-007</strong></td>
          <td><strong>Maria Santos</strong></td>
          <td><span class="badge high">High</span></td>
          <td><span class="badge pending">Pending</span></td>
          <td>2026-05-10</td>
          <td>Brake Pads (2), Oil Filter (1)</td>
          <td><a href="#" class="view-link">View →</a></td>
        </tr>

        <tr>
          <td><strong>PR-008</strong></td>
          <td><strong>JO-2026-008</strong></td>
          <td><strong>Maria Santos</strong></td>
          <td><span class="badge low">Low</span></td>
          <td><span class="badge approved">Approved</span></td>
          <td>2026-05-10</td>
          <td>Coolant Antifreeze 4L (2)</td>
          <td><a href="#" class="view-link">View →</a></td>
        </tr>
      </tbody>
    </table>
  </div>

</section>

    </main>

  </div>

</body>
</html>