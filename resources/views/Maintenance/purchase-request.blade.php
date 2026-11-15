<x-layout.app
  title="FROMS - Purchase Requests"
  :assets="[
    'resources/css/Maintenance/purchase-request.css'
  ]"
>

  <div class="app">

    <x-layout.sidebar
      department="Maintenance"
      subtitle="Department Module"
      icon="fa-truck"
      user-name="R. Lim"
      user-role="Maintenance Admin"
      :items="[
        ['label' => 'Dashboard', 'route' => 'dashboard-maintenance', 'icon' => 'fa-table-cells-large'],
        ['label' => 'Job Orders', 'route' => 'job-orders', 'icon' => 'fa-clipboard-list'],
        ['label' => 'Mechanic List', 'route' => 'mechanic-list', 'icon' => 'fa-bus'],
        ['label' => 'PMS Scheduling', 'route' => 'PMS-Scheduling', 'icon' => 'fa-calendar-check'],
        ['label' => 'Purchase Requests', 'route' => 'purchase-requests', 'icon' => 'fa-file-invoice'],
        ['label' => 'Fuel Reports', 'route' => 'fuel-reports', 'icon' => 'fa-gas-pump'],
        ['label' => 'Settings', 'route' => 'settings', 'icon' => 'fa-gear'],
      ]"
    />

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
            <p>Draft</p>
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
            <p>Submitted</p>
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
            <p>Total Requests</p>
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

</x-layout.app>