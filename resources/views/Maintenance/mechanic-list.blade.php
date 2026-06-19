<x-layout.app
  title="FROMS - Mechanic List"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Maintenance/mechanic-list.css',
    'resources/js/Main-js/sidebar.js'
  ]"
>

  <div class="app">

    <x-layout.sidebar
      department="Maintenance"
      subtitle="Department Module"
      icon="fa-truck"
      :items="[
        ['label' => 'Dashboard', 'route' => 'maintenance-dashboard', 'icon' => 'fa-table-cells-large'],
        ['label' => 'Job Orders', 'route' => 'job-orders', 'icon' => 'fa-clipboard-list'],
        ['label' => 'Mechanic List', 'route' => 'mechanic-list', 'icon' => 'fa-bus'],
        ['label' => 'PMS Scheduling', 'route' => 'PMS-Scheduling', 'icon' => 'fa-calendar-check'],
        ['label' => 'Purchase Requests', 'route' => 'purchase-requests', 'icon' => 'fa-file-invoice'],
        ['label' => 'Fuel Reports', 'route' => 'fuel-reports', 'icon' => 'fa-gas-pump'],
        ['label' => 'Settings', 'route' => 'settings', 'icon' => 'fa-gear'],
      ]"
    />

    <main class="main">

      <x-layout.topbar
        title="Mechanic List"
        subtitle="Monitor mechanic availability, assigned jobs, and work history"
        notification-count="6"
      />

      {{-- SUMMARY CARDS --}}
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

      {{-- MECHANIC LIST --}}
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

          <button class="primary-btn" type="button">
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
                    <button type="button" class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button type="button" class="delete"><i class="fa-solid fa-trash"></i></button>
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
                    <button type="button" class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button type="button" class="delete"><i class="fa-solid fa-trash"></i></button>
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
                    <button type="button" class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button type="button" class="delete"><i class="fa-solid fa-trash"></i></button>
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
                    <button type="button" class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button type="button" class="delete"><i class="fa-solid fa-trash"></i></button>
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
                    <button type="button" class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button type="button" class="delete"><i class="fa-solid fa-trash"></i></button>
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
                    <button type="button" class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button type="button" class="delete"><i class="fa-solid fa-trash"></i></button>
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
                    <button type="button" class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button type="button" class="delete"><i class="fa-solid fa-trash"></i></button>
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
                    <button type="button" class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button type="button" class="delete"><i class="fa-solid fa-trash"></i></button>
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
                    <button type="button" class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button type="button" class="delete"><i class="fa-solid fa-trash"></i></button>
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
                    <button type="button" class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button type="button" class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

      </section>

      {{-- JOB HISTORY --}}
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

</x-layout.app>
