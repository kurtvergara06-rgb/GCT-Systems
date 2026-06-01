<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>FROMS - Purchase</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

  @vite([
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Purchase/requested-purchase.css'
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

      <a href="{{ route('requested-purchase') }}" class="menu-item active">
        <i class="fa-solid fa-clipboard-list"></i>
        <span>Requested Purchases</span>
      </a>

      <a href="{{ route('scheduled-purchase') }}" class="menu-item">
        <i class="fa-solid fa-bus"></i>
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

  < <!-- MAIN -->
    <main class="main">

      <!-- TOP BAR -->
      <header class="topbar">
        <div>
          <h1>Purchase Requests</h1>
          <p>Manage requested parts and maintenance purchasing records</p>
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
          <div class="stat-icon gray">
            <i class="fa-solid fa-file"></i>
          </div>

          <div>
            <p>Total Requests</p>
            <h2>1</h2>
            <small>Purchase Request</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon yellow">
            <i class="fa-solid fa-paper-plane"></i>
          </div>

          <div>
            <p>Pending Approval</p>
            <h2>4</h2>
            <small>Purchase Requests</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon green">
            <i class="fa-solid fa-check"></i>
          </div>

          <div>
            <p>Approved</p>
            <h2>4</h2>
            <small>Purchase Requests</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon blue">
            <i class="fa-solid fa-boxes-stacked"></i>
          </div>

          <div>
            <p>Convert to Purchase Order</p>
            <h2>9</h2>
            <small>All records</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

      </section>

      <!-- PURCHASE REQUEST TABLE -->
      <section class="table-card purchase-card">

        <div class="section-header">
          <div>
            <h2>Purchase Request Records</h2>
            <p>Track requested parts, approval status, and related job orders</p>
          </div>
        </div>

        <div class="toolbar purchase-toolbar">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" placeholder="Search PR no., JO no., bus, or item...">
          </div>

          <div class="filter-group">
            <label>Status</label>
            <select>
              <option>All Statuses</option>
              <option>Draft</option>
              <option>Submitted</option>
              <option>Approved</option>
            </select>
          </div>

          <button class="primary-btn">
            <i class="fa-solid fa-plus"></i>
            New PR
          </button>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>PR #</th>
                <th>JO No.</th>
                <th>Bus #</th>
                <th>Item</th>
                <th>Quantity</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              <tr>
                <td>PR-2026-0002</td>
                <td>JO-26-0011</td>
                <td>BUS-001</td>
                <td>Engine Oil Filter</td>
                <td>6</td>
                <td><span class="badge draft">Draft</span></td>
                <td>Apr 29, 2026</td>
                <td>No Action</td>
              </tr>

              <tr>
                <td>PR-2026-0008</td>
                <td>JO-26-0013</td>
                <td>BUS-013</td>
                <td>Brake Pads</td>
                <td>1</td>
                <td><span class="badge approved">Approved</span></td>
                <td>Apr 29, 2026</td>
                <td>No Action</td>
              </tr>

              <tr>
                <td>PR-2026-0007</td>
                <td>JO-26-0002</td>
                <td>BUS-008</td>
                <td>Air Filter</td>
                <td>4</td>
                <td><span class="badge approved">Approved</span></td>
                <td>Apr 29, 2026</td>
                <td>No Action</td>
              </tr>

              <tr>
                <td>PR-2026-0006</td>
                <td>JO-26-0016</td>
                <td>BUS-005</td>
                <td>Engine Oil</td>
                <td>6</td>
                <td><span class="badge approved">Approved</span></td>
                <td>Apr 29, 2026</td>
                <td>No Action</td>
              </tr>

              <tr>
                <td>PR-2026-0005</td>
                <td>JO-26-0014</td>
                <td>BUS-011</td>
                <td>Fuel Filter</td>
                <td>3</td>
                <td><span class="badge approved">Approved</span></td>
                <td>Apr 29, 2026</td>
                <td>No Action</td>
              </tr>

              <tr>
                <td>PR-2026-0004</td>
                <td>JO-26-0021</td>
                <td>BUS-003</td>
                <td>AC Belt</td>
                <td>1</td>
                <td><span class="badge submitted">Submitted</span></td>
                <td>Apr 29, 2026</td>
                <td>No Action</td>
              </tr>

              <tr>
                <td>PR-2026-0003</td>
                <td>JO-26-0019</td>
                <td>BUS-014</td>
                <td>Tire Valve</td>
                <td>1</td>
                <td><span class="badge submitted">Submitted</span></td>
                <td>Apr 29, 2026</td>
                <td>No Action</td>
              </tr>

              <tr>
                <td>PR-2026-0001</td>
                <td>JO-26-0002</td>
                <td>BUS-019</td>
                <td>Coolant</td>
                <td>1</td>
                <td><span class="badge submitted">Submitted</span></td>
                <td>Apr 29, 2026</td>
                <td>No Action</td>
              </tr>
            </tbody>
          </table>
        </div>

      </section>

    </main>

  </div>

</body>
</html>