<x-layout.app
  title="FROMS - Attendance"
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
        ['label' => 'Attendance', 'route' => 'attendance', 'icon' => 'fa-calendar-check'],
        ['label' => 'Available Mechanics', 'route' => 'available-mechanics', 'icon' => 'fa-users-gear'],
      ]"
    />

    <main class="main">

      <!-- your attendance page content here -->

    </main>

  </div>

</x-layout.app>
  <!-- SIDEBAR -->
  <aside class="sidebar">

    <div class="brand">
      <div class="brand-icon">
        <i class="fa-solid fa-user-gear"></i>
      </div>

      <div>
        <h2>Operations</h2>
        <p>Department Module</p>
      </div>
    </div>

    <nav class="menu">
      <a href="{{ route('dashboard-operation') }}" class="menu-item">
        <i class="fa-solid fa-table-cells-large"></i>
        <span>Dashboard</span>
      </a>

      <a href="{{ route('available-mechanics') }}" class="menu-item active">
        <i class="fa-solid fa-screwdriver-wrench"></i>
        <span>Available Mechanics</span>
      </a>
    </nav>

    <div class="user-box">
      <div class="avatar">
        <i class="fa-solid fa-user"></i>
      </div>

      <div>
        <h4>R. Lim</h4>
        <p>Operations Admin</p>
      </div>

      <i class="fa-solid fa-chevron-down"></i>
    </div>

  </aside>

  <!-- MAIN -->
  <main class="main">

    <!-- PAGE HEADER -->
    <header class="topbar">
      <div>
        <h1>Available Mechanics</h1>
        <p>Manage workforce availability and assignments</p>
      </div>

      <div class="top-actions">
        <button class="icon-btn notification">
          <i class="fa-regular fa-bell"></i>
          <span>4</span>
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
        <div class="stat-icon green">
          <i class="fa-solid fa-user-check"></i>
        </div>

        <div>
          <p>Available Mechanics</p>
          <h2>12</h2>
          <small>Ready for Assignment</small>
        </div>

        <i class="fa-solid fa-chevron-right arrow"></i>
      </div>

      <div class="stat-card">
        <div class="stat-icon blue">
          <i class="fa-solid fa-screwdriver-wrench"></i>
        </div>

        <div>
          <p>Assigned Today</p>
          <h2>8</h2>
          <small>Current Tasks</small>
        </div>

        <i class="fa-solid fa-chevron-right arrow"></i>
      </div>

      <div class="stat-card">
        <div class="stat-icon yellow">
          <i class="fa-solid fa-calendar-xmark"></i>
        </div>

        <div>
          <p>On Leave</p>
          <h2>2</h2>
          <small>Unavailable</small>
        </div>

        <i class="fa-solid fa-chevron-right arrow"></i>
      </div>

      <div class="stat-card">
        <div class="stat-icon red">
          <i class="fa-solid fa-clipboard-list"></i>
        </div>

        <div>
          <p>Pending Assignments</p>
          <h2>5</h2>
          <small>Job Orders</small>
        </div>

        <i class="fa-solid fa-chevron-right arrow"></i>
      </div>

    </section>

    <!-- TABLE -->
    <section class="table-card">

      <div class="section-title">
        <div>
          <h2>Available Mechanics</h2>
          <p>Manage workforce availability and assignments</p>
        </div>
      </div>

      <div class="toolbar mechanics-toolbar">
        <div class="search-box">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" placeholder="Search mechanic...">
        </div>

        <div class="filter-group">
          <label>Status</label>
          <select>
            <option>All Status</option>
            <option>Available</option>
            <option>Assigned</option>
            <option>On Leave</option>
          </select>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Mechanic</th>
              <th>Specialization</th>
              <th>Status</th>
              <th>Assigned Bus</th>
              <th>Job Order</th>
              <th>Contact</th>
            </tr>
          </thead>

          <tbody>
            <tr>
              <td>MEC-001</td>
              <td>Juan Dela Cruz</td>
              <td>Engine Repair</td>
              <td><span class="badge completed">Available</span></td>
              <td>-</td>
              <td>-</td>
              <td>09123456789</td>
            </tr>

            <tr>
              <td>MEC-002</td>
              <td>Pedro Santos</td>
              <td>Electrical Systems</td>
              <td><span class="badge ongoing">Assigned</span></td>
              <td>BUS-014</td>
              <td>JO-26-0014</td>
              <td>09181234567</td>
            </tr>

            <tr>
              <td>MEC-003</td>
              <td>Mark Reyes</td>
              <td>Brake Systems</td>
              <td><span class="badge submitted">On Leave</span></td>
              <td>-</td>
              <td>-</td>
              <td>09172345678</td>
            </tr>
          </tbody>
        </table>
      </div>

    </section>

  </main>

</div>

</body>
</html>