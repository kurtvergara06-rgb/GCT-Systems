<x-layout.sidebar
    department="Admin"
    subtitle="Administration Module"
    icon="fa-user-shield"
    :items="[
        [
            'label' => 'Dashboard',
            'route' => 'admin.dashboard',
            'icon' => 'fa-table-cells-large'
        ],

        [
            'label' => 'User Management',
            'icon' => 'fa-users',
            'children' => [
                [
                    'label' => 'Users',
                    'route' => 'admin.users',
                    'icon' => 'fa-user'
                ],
                [
                    'label' => 'Roles & Permissions',
                    'route' => 'admin.roles-permissions',
                    'icon' => 'fa-user-lock'
                ],
                [
                    'label' => 'Account Requests',
                    'route' => 'admin.account-requests',
                    'icon' => 'fa-user-clock'
                ],
            ]
        ],

        [
            'label' => 'System Monitoring',
            'icon' => 'fa-desktop',
            'children' => [
                [
                    'label' => 'Activity Logs',
                    'route' => 'admin.activity-logs',
                    'icon' => 'fa-clock-rotate-left'
                ],
                [
                    'label' => 'Notifications',
                    'route' => 'admin.notifications',
                    'icon' => 'fa-bell'
                ],
            ]
        ],

        [
            'label' => 'Data Management',
            'icon' => 'fa-database',
            'children' => [
                [
                    'label' => 'Batch File Processing',
                    'route' => 'admin.batch-file-processing',
                    'icon' => 'fa-file-import'
                ],
                [
                    'label' => 'Import / Export',
                    'route' => 'admin.import-export',
                    'icon' => 'fa-right-left'
                ],
                [
                    'label' => 'Data History',
                    'route' => 'admin.data-history',
                    'icon' => 'fa-clock-rotate-left'
                ],
            ]
        ],

        [
            'label' => 'Analytics',
            'route' => 'analytics',
            'icon' => 'fa-chart-line'
        ],

        [
            'label' => 'Settings',
            'icon' => 'fa-gear',
            'children' => [
                [
                    'label' => 'General Settings',
                    'route' => 'admin.settings.general',
                    'icon' => 'fa-sliders'
                ],
                [
                    'label' => 'Notification Settings',
                    'route' => 'admin.settings.notifications',
                    'icon' => 'fa-bell'
                ],
                [
                    'label' => 'Security Settings',
                    'route' => 'admin.settings.security',
                    'icon' => 'fa-shield-halved'
                ],
            ]
        ],
    ]"
/>