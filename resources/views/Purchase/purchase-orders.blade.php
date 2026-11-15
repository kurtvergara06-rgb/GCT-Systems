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
      <a href="{{ route('purchase-orders') }}" class="menu-item active">
        <i class="fa-solid fa-table-cells-large"></i>
        <span>Purchase Orders</span>
      </a>

      <a href="{{ route('requested-purchase') }}" class="menu-item">
        <i class="fa-solid fa-clipboard-list"></i>
        <span>Requested Purchases</span>
      </a>

      <a href="{{ route('scheduled-purchase') }}" class="menu-item">
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
        <h1>Purchase Order</h1>
        <p>Manage procurement records for vehicle parts, equipment & operational materials</p>
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
          <i class="fa-solid fa-file-invoice"></i>
        </div>

        <div>
          <p>Total Orders</p>
          <h2>8</h2>
          <small>Purchase orders</small>
        </div>

        <i class="fa-solid fa-chevron-right arrow"></i>
      </div>

      <div class="stat-card">
        <div class="stat-icon blue">
          <i class="fa-solid fa-cart-shopping"></i>
        </div>

        <div>
          <p>Ordered</p>
          <h2>3</h2>
          <small>Waiting for delivery</small>
        </div>

        <i class="fa-solid fa-chevron-right arrow"></i>
      </div>

      <div class="stat-card">
        <div class="stat-icon yellow">
          <i class="fa-solid fa-truck-fast"></i>
        </div>

        <div>
          <p>Shipped</p>
          <h2>2</h2>
          <small>In transit</small>
        </div>

        <i class="fa-solid fa-chevron-right arrow"></i>
      </div>

      <div class="stat-card">
        <div class="stat-icon green">
          <i class="fa-solid fa-circle-check"></i>
        </div>

        <div>
          <p>Delivered</p>
          <h2>3</h2>
          <small>Completed orders</small>
        </div>

        <i class="fa-solid fa-chevron-right arrow"></i>
      </div>

    </section>

    <!-- TOOLBAR -->
    <div class="toolbar po-toolbar">
      <div class="search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" placeholder="Search by item, order number, or supplier...">
      </div>

      <select>
        <option>All Categories</option>
        <option>Vehicle Parts</option>
        <option>Equipment</option>
        <option>Operational Materials</option>
      </select>

      <select>
        <option>All States</option>
        <option>Pending</option>
        <option>Ordered</option>
        <option>Approved</option>
        <option>Shipped</option>
        <option>Delivered</option>
      </select>

      <button class="primary-btn">
        <i class="fa-solid fa-plus"></i>
        New PO
      </button>
    </div>

    <!-- PURCHASE ORDER TABLE -->
    <section class="table-card po-card">

      <div class="po-table-top">
        <p>Showing 8 of 8 records</p>

        <button class="filter-records">
          <i class="fa-solid fa-filter"></i>
          All records
        </button>
      </div>

      <div class="table-wrap">
        <table class="po-table">
          <thead>
            <tr>
              <th>Order #</th>
              <th>Item / Category</th>
              <th>Quantity</th>
              <th>Supplier</th>
              <th>Total Price</th>
              <th>Status</th>
              <th>Scheduled Date</th>
              <th>Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr>
              <td>PO-2026-0001</td>
              <td>
                <strong>Engine Oil Filter</strong>
                <span class="sub-text">Vehicle Parts</span>
              </td>
              <td>50 pcs</td>
              <td>AutoParts Philippines Corp...</td>
              <td><strong>₱17,500.00</strong></td>
              <td><span class="badge delivered">Delivered</span></td>
              <td>Apr 1, 2026</td>
              <td>
                <div class="po-actions">
                  <button><i class="fa-regular fa-eye"></i></button>
                  <button><i class="fa-solid fa-pen"></i></button>
                  <button><i class="fa-solid fa-trash-can"></i></button>
                </div>
              </td>
            </tr>

            <tr>
              <td>PO-2026-0002</td>
              <td>
                <strong>Brake Pads</strong>
                <span class="sub-text">Vehicle Parts</span>
              </td>
              <td>30 pcs</td>
              <td>Metro Supplies</td>
              <td><strong>₱21,300.00</strong></td>
              <td><span class="badge approved">Approved</span></td>
              <td>Apr 3, 2026</td>
              <td>
                <div class="po-actions">
                  <button><i class="fa-regular fa-eye"></i></button>
                  <button><i class="fa-solid fa-pen"></i></button>
                  <button><i class="fa-solid fa-trash-can"></i></button>
                </div>
              </td>
            </tr>

            <tr>
              <td>PO-2026-0003</td>
              <td>
                <strong>Coolant</strong>
                <span class="sub-text">Operational Materials</span>
              </td>
              <td>40 pcs</td>
              <td>Fleet Warehouse</td>
              <td><strong>₱12,000.00</strong></td>
              <td><span class="badge ordered">Ordered</span></td>
              <td>Apr 5, 2026</td>
              <td>
                <div class="po-actions">
                  <button><i class="fa-regular fa-eye"></i></button>
                  <button><i class="fa-solid fa-pen"></i></button>
                  <button><i class="fa-solid fa-trash-can"></i></button>
                </div>
              </td>
            </tr>

            <tr>
              <td>PO-2026-0004</td>
              <td>
                <strong>Fuel Filter</strong>
                <span class="sub-text">Vehicle Parts</span>
              </td>
              <td>25 pcs</td>
              <td>AutoParts Philippines Corp...</td>
              <td><strong>₱9,750.00</strong></td>
              <td><span class="badge shipped">Shipped</span></td>
              <td>Apr 8, 2026</td>
              <td>
                <div class="po-actions">
                  <button><i class="fa-regular fa-eye"></i></button>
                  <button><i class="fa-solid fa-pen"></i></button>
                  <button><i class="fa-solid fa-trash-can"></i></button>
                </div>
              </td>
            </tr>

            <tr>
              <td>PO-2026-0005</td>
              <td>
                <strong>Tire Valve</strong>
                <span class="sub-text">Vehicle Parts</span>
              </td>
              <td>100 pcs</td>
              <td>Metro Supplies</td>
              <td><strong>₱8,500.00</strong></td>
              <td><span class="badge pending">Pending</span></td>
              <td>Apr 10, 2026</td>
              <td>
                <div class="po-actions">
                  <button><i class="fa-regular fa-eye"></i></button>
                  <button><i class="fa-solid fa-pen"></i></button>
                  <button><i class="fa-solid fa-trash-can"></i></button>
                </div>
              </td>
            </tr>

            <tr>
              <td>PO-2026-0006</td>
              <td>
                <strong>Air Filter</strong>
                <span class="sub-text">Vehicle Parts</span>
              </td>
              <td>45 pcs</td>
              <td>Fleet Warehouse</td>
              <td><strong>₱13,950.00</strong></td>
              <td><span class="badge delivered">Delivered</span></td>
              <td>Apr 12, 2026</td>
              <td>
                <div class="po-actions">
                  <button><i class="fa-regular fa-eye"></i></button>
                  <button><i class="fa-solid fa-pen"></i></button>
                  <button><i class="fa-solid fa-trash-can"></i></button>
                </div>
              </td>
            </tr>

            <tr>
              <td>PO-2026-0007</td>
              <td>
                <strong>Engine Oil</strong>
                <span class="sub-text">Lubricants</span>
              </td>
              <td>60 pcs</td>
              <td>Diesel Masters</td>
              <td><strong>₱36,000.00</strong></td>
              <td><span class="badge approved">Approved</span></td>
              <td>Apr 15, 2026</td>
              <td>
                <div class="po-actions">
                  <button><i class="fa-regular fa-eye"></i></button>
                  <button><i class="fa-solid fa-pen"></i></button>
                  <button><i class="fa-solid fa-trash-can"></i></button>
                </div>
              </td>
            </tr>

            <tr>
              <td>PO-2026-0008</td>
              <td>
                <strong>AC Belt</strong>
                <span class="sub-text">Vehicle Parts</span>
              </td>
              <td>20 pcs</td>
              <td>AutoParts Philippines Corp...</td>
              <td><strong>₱18,400.00</strong></td>
              <td><span class="badge ordered">Ordered</span></td>
              <td>Apr 18, 2026</td>
              <td>
                <div class="po-actions">
                  <button><i class="fa-regular fa-eye"></i></button>
                  <button><i class="fa-solid fa-pen"></i></button>
                  <button><i class="fa-solid fa-trash-can"></i></button>
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