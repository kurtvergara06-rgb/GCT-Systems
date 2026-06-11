<x-layout.app
  title="FROMS - Dashboard"
  :assets="[
    'resources/css/Operation/dashboard.css'
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

      ]"
    />

    <main class="main">

      <!-- your dashboard page content here -->

    </main>

  </div>

</x-layout.app>