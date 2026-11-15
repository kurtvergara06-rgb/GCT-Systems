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