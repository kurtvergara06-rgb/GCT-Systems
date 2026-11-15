<x-layout.app
  title="FROMS - Scheduled Purchase"
  :assets="[
    'resources/css/Purchase/scheduled-purchase.css'
  ]"
>

  <div class="app">

    <x-layout.sidebar
      department="Purchase"
      subtitle="Department Module"
      icon="fa-truck"
      user-name="R. Lim"
      user-role="Purchase Admin"
      :items="[
        ['label' => 'Purchase Orders', 'route' => 'purchase-orders', 'icon' => 'fa-file-invoice'],
        ['label' => 'Requested Purchase', 'route' => 'requested-purchase', 'icon' => 'fa-clipboard-list'],
        ['label' => 'Scheduled Purchase', 'route' => 'scheduled-purchase', 'icon' => 'fa-calendar-check'],
      ]"
    />

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

            <x-ui.summary-card
              label="Total Schedules"
              value="6"
              small="Recurring schedules"
              icon="fa-calendar-days"
              color="green"
            />

            <x-ui.summary-card
              label="Active"
              value="5"
              small="Running schedules"
              icon="fa-play"
              color="yellow"
            />

            <x-ui.summary-card
              label="Paused"
              value="1"
              small="Temporarily stopped"
              icon="fa-pause"
              color="blue"
            />

            <x-ui.summary-card
              label="Due This Month"
              value="2"
              small="Upcoming purchases"
              icon="fa-clock"
              color="red"
            />

      </section>

      <!-- SCHEDULED PURCHASE CONTAINER -->
      <section class="schedule-card">

        <div class="toolbar schedule-toolbar">

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

              <button type="button">
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
                  <h3>Metro Supplies</h3>
                  <p>Maria Santos</p>
                </div>
              </div>

              <span class="status active">Active</span>
            </div>

            <div class="supplier-body">
              <p><i class="fa-solid fa-phone"></i> 09171234567</p>
              <p><i class="fa-regular fa-envelope"></i> maria@metrosupplies.com</p>

              <div class="tags">
                <span>Spare Parts</span>
                <span>Tools</span>
              </div>
            </div>

            <div class="supplier-footer">
              <span>No expiry set</span>

              <button type="button">
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
                  <h3>AutoParts Philippines</h3>
                  <p>Juan Reyes</p>
                </div>
              </div>

              <span class="status paused">Paused</span>
            </div>

            <div class="supplier-body">
              <p><i class="fa-solid fa-phone"></i> 09081234567</p>
              <p><i class="fa-regular fa-envelope"></i> juan@autopartsph.com</p>

              <div class="tags">
                <span>Vehicle Parts</span>
                <span>Filters</span>
              </div>
            </div>

            <div class="supplier-footer">
              <span>No expiry set</span>

              <button type="button">
                <i class="fa-solid fa-pen-to-square"></i>
              </button>
            </div>

          </div>

        </div>

      </section>

    </main>

  </div>

</x-layout.app>