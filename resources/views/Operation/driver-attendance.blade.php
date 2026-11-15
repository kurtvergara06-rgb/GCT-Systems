<x-layout.app
  title="FROMS - Driver Attendance"
  :assets="[
    'resources/css/Operation/attendance.css'
  ]"
>

  <div class="app">

   <x-layout.sidebar
      department="Operation"
      subtitle="Department Module"
      icon="fa-clipboard-check"
        :items="[
                [
                    'label' => 'Dashboard',
                    'route' => 'dashboard-operation',
                    'icon' => 'fa-table-cells-large'
                ],
                [
                    'label' => 'Bus Master List',
                    'route' => 'bus-master-list',
                    'icon' => 'fa-bus'
                ],
                [
                    'label' => 'Attendance',
                    'icon' => 'fa-calendar-check',
                    'children' => [
                        [
                            'label' => 'Driver Attendance',
                            'route' => 'driver-attendance',
                            'icon' => 'fa-id-card'
                        ],
                        [
                            'label' => 'Mechanic Attendance',
                            'route' => 'mechanic-attendance',
                            'icon' => 'fa-users-gear'
                        ],
                    ]
                ],
            ]"
        />


    <main class="main">

      <!-- TOP BAR -->
      <header class="topbar">
        <div>
          <h1>Attendance Record</h1>
          <p>Manage and track driver and mechanic availability</p>
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
<!-- SUMMARY CARDS -->
    <section class="stats-grid">

      <x-ui.summary-card
        label="Present"
        value="6"
        small="Mechanics today"
        icon="fa-user-check"
        color="green"
      />

      <x-ui.summary-card
        label="Absent"
        value="2"
        small="Mechanics absent"
        icon="fa-user-xmark"
        color="red"
      />

      <x-ui.summary-card
        label="Late"
        value="1"
        small="Mechanics who were late"
        icon="fa-clock"
        color="yellow"
      />

      <x-ui.summary-card
        label="On Duty"
        value="5"
        small="Assigned mechanics"
        icon="fa-screwdriver-wrench"
        color="blue"
      />

    </section>

      <!-- ATTENDANCE TABLE -->
      <section class="table-card attendance-card">

        <div class="section-header">
          <div>
            <h2>Attendance List</h2>
            <p>Track attendance records for drivers and mechanics</p>
          </div>
        </div>

        <div class="toolbar attendance-toolbar">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" placeholder="Search driver, mechanic, bus, or ID...">
          </div>

          <div class="filter-group">
            <label>Status</label>
            <select>
              <option>All Status</option>
              <option>Present</option>
              <option>On Duty</option>
              <option>Absent</option>
              <option>On Leave</option>
            </select>
          </div>

          <button class="primary-btn">
            <i class="fa-solid fa-plus"></i>
            New Record
          </button>
        </div>

        <div class="table-wrap">
          <table class="attendance-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Role</th>
                <th>Shift</th>
                <th>Bus / Assignment</th>
                <th>Time-in</th>
                <th>Time-out</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              <tr>
                <td>D-001</td>
                <td>Rowell Amano</td>
                <td>Driver</td>
                <td>Morning</td>
                <td>BUS-001</td>
                <td>06:00 AM</td>
                <td>--:--</td>
                <td><span class="badge present">Present</span></td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>D-002</td>
                <td>Cardo Mendoza</td>
                <td>Driver</td>
                <td>Morning</td>
                <td>BUS-003</td>
                <td>06:10 AM</td>
                <td>--:--</td>
                <td><span class="badge late">Late</span></td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>D-003</td>
                <td>Joshua Garcia</td>
                <td>Driver</td>
                <td>Morning</td>
                <td>BUS-007</td>
                <td>--:--</td>
                <td>--:--</td>
                <td><span class="badge absent">Absent</span></td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>D-004</td>
                <td>Emman Guzman</td>
                <td>Driver</td>
                <td>Morning</td>
                <td>BUS-002</td>
                <td>06:00 AM</td>
                <td>--:--</td>
                <td><span class="badge present">Present</span></td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>M-001</td>
                <td>Leo Fernandez</td>
                <td>Mechanic</td>
                <td>Morning</td>
                <td>Engine Oil Filter</td>
                <td>07:00 AM</td>
                <td>--:--</td>
                <td><span class="badge present">Present</span></td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>M-002</td>
                <td>Ronald Mendoza</td>
                <td>Mechanic</td>
                <td>Morning</td>
                <td>Brake Repair</td>
                <td>07:15 AM</td>
                <td>--:--</td>
                <td><span class="badge late">Late</span></td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>M-003</td>
                <td>Roy Montalban</td>
                <td>Mechanic</td>
                <td>Morning</td>
                <td>Transmission</td>
                <td>--:--</td>
                <td>--:--</td>
                <td><span class="badge absent">Absent</span></td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>M-004</td>
                <td>Rowell Aspanua</td>
                <td>Mechanic</td>
                <td>Morning</td>
                <td>PMS</td>
                <td>07:00 AM</td>
                <td>--:--</td>
                <td><span class="badge present">Present</span></td>
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

    </main>

  </div>

</x-layout.app>
