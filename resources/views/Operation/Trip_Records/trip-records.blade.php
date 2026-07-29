<x-layout.app
    title="FROMS - Trip Records"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Operation/Trip_Records/trip-records.css',
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
            'label' => 'Fleet Management',
            'icon' => 'fa-bus',
            'children' => [
                [
                    'label' => 'Bus Master List',
                    'route' => 'bus-master-list',
                    'icon' => 'fa-bus'
                ],
            ]
        ],

        [
            'label' => 'Fuel Data Entry',
            'route' => 'operation.fuel-data',
            'icon' => 'fa-gas-pump'
        ],
    ]"
/>
        <main class="main trip-records-page">
            <x-layout.topbar
                title="Trip Records"
                subtitle="Review completed shuttle trips and operational history"
                notification-count="4"
            />

            <section class="trip-records-content">
                <h2>Trip Records</h2>
                <p>Trip records content will be rendered here.</p>
            </section>
        </main>
    </div>
</x-layout.app>
