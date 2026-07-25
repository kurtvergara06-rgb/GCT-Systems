<x-layout.app
  title="FROMS - Dashboard"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Operation/dashboard-operation.css',
    'resources/js/Main-js/sidebar.js',
  ]"
>
  <div class="app">

    <x-layout.sidebar
    department="Operation"
    subtitle="Operation Module"
    icon="fa-bus"
    :items="[
        [
            'label' => 'Dashboard',
            'route' => 'dashboard-operation',
            'icon' => 'fa-table-cells-large'
        ],

        [
            'label' => 'Routes',
            'route' => 'operation.routes',
            'icon' => 'fa-route'
        ],

        [
            'label' => 'Scheduling',
            'icon' => 'fa-calendar-days',
            'children' => [
                [
                    'label' => 'Trip Schedule',
                    'route' => 'trip-schedule',
                    'icon' => 'fa-calendar-days'
                ],
                [
                    'label' => 'Driver & Bus Assignment',
                    'route' => 'driver-bus-assignment',
                    'icon' => 'fa-user-tie'
                ],
                [
                    'label' => 'Auto Scheduling',
                    'route' => 'auto-scheduling',
                    'icon' => 'fa-wand-magic-sparkles'
                ],
            ]
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

        [
            'label' => 'Bus Master List',
            'route' => 'bus-master-list',
            'icon' => 'fa-bus'
        ],
    ]"
/>


    <main class="main">
      <!-- your dashboard page content here -->
    </main>

  </div>
</x-layout.app>