<x-layout.app
  title="FROMS - Mechanic Attendance"
  :assets="[
    'resources/css/Operation/attendance.css'
  ]"
>

  <div class="app">

    <x-layout.sidebar
      department="Operation"
      subtitle="Department Module"
      icon="fa-clipboard-check"
      user-name="O. Admin"
      user-role="Operation Admin"
      :items="[
        ['label' => 'Dashboard', 'route' => 'dashboard-operation', 'icon' => 'fa-table-cells-large'],

        [
          'label' => 'Attendance',
          'icon' => 'fa-calendar-check',
          'children' => [
            ['label' => 'Driver Attendance', 'route' => 'driver-attendance', 'icon' => 'fa-id-card'],
            ['label' => 'Mechanic Attendance', 'route' => 'mechanic-attendance', 'icon' => 'fa-users-gear'],
          ]
        ],

        ['label' => 'Available Mechanics', 'route' => 'available-mechanics', 'icon' => 'fa-users-gear'],
      ]"
    />

    <main class="main">

      <!-- TOP BAR -->
      <header class="topbar">
        <div>
          <h1>Mechanic Attendance</h1>
          <p>Manage and track mechanic attendance and availability</p>
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
    label="On Leave"
    value="1"
    small="Approved leave"
    icon="fa-calendar-minus"
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

      <!-- MECHANIC ATTENDANCE TABLE -->
      <section class="table-card attendance-card">

        <div class="section-header">
          <div>
            <h2>Mechanic Attendance List</h2>
            <p>Track time-in, time-out, assigned job, and attendance status</p>
          </div>
        </div>

        <div class="toolbar attendance-toolbar">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" placeholder="Search mechanic name, ID, or assigned job...">
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
                <th>Mechanic</th>
                <th>Shift</th>
                <th>Assigned Job</th>
                <th>Time-in</th>
                <th>Time-out</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              <tr>
                <td>M-001</td>
                <td>Leo Fernandez</td>
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

              <tr>
                <td>M-005</td>
                <td>Priya Nair</td>
                <td>Morning</td>
                <td>Suspension Repair</td>
                <td>07:05 AM</td>
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
                <td>M-006</td>
                <td>Joshua Garcia</td>
                <td>Morning</td>
                <td>Flat Tire Repair</td>
                <td>07:20 AM</td>
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
                <td>M-007</td>
                <td>Biboy Enriquez</td>
                <td>Morning</td>
                <td>Available</td>
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
                <td>M-008</td>
                <td>Jose Dimaano</td>
                <td>Morning</td>
                <td>Available</td>
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