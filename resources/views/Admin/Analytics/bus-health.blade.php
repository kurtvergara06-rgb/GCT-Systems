<x-layout.app :assets="[
    'resources/css/Admin/Analytics/bus-health.css',
]">

    {{-- =====================================================
        ADMIN SIDEBAR
    ====================================================== --}}
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
                'icon' => 'fa-chart-line',
                'children' => [
                    [
                        'label' => 'Overview',
                        'route' => 'analytics.overview',
                        'icon' => 'fa-chart-pie'
                    ],
                    [
                        'label' => 'Fleet & Trip',
                        'route' => 'analytics.fleet-trip',
                        'icon' => 'fa-route'
                    ],
                    [
                        'label' => 'Fuel',
                        'route' => 'analytics.fuel',
                        'icon' => 'fa-gas-pump'
                    ],
                    [
                        'label' => 'Bus Health',
                        'route' => 'analytics.bus-health',
                        'icon' => 'fa-heart-pulse'
                    ],
                    [
                        'label' => 'Inventory',
                        'route' => 'analytics.inventory',
                        'icon' => 'fa-boxes-stacked'
                    ],
                    [
                        'label' => 'Recommendations',
                        'route' => 'analytics.recommendations',
                        'icon' => 'fa-lightbulb'
                    ],
                ]
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

    {{-- =====================================================
        PAGE CONTENT
    ====================================================== --}}
    <main class="bus-health-page">

        {{-- Header --}}
        <div class="page-header">
            <div>
                <div class="page-title-row">
                    <div class="page-title-icon">
                        <i class="fa-solid fa-heart-pulse"></i>
                    </div>

                    <div>
                        <h1>Bus Health Analytics</h1>
                        <p>
                            Monitor fleet condition, accumulated mileage,
                            preventive maintenance schedules, and maintenance alerts.
                        </p>
                    </div>
                </div>
            </div>

            <div class="header-actions">
                <select class="period-filter">
                    <option>Current Fleet Status</option>
                    <option>Last 7 Days</option>
                    <option>Last 30 Days</option>
                    <option>This Year</option>
                </select>

                <button type="button" class="export-btn">
                    <i class="fa-solid fa-download"></i>
                    Export Report
                </button>
            </div>
        </div>


        {{-- =================================================
            SUMMARY CARDS
        ================================================== --}}
        <section class="summary-grid">

            <div class="summary-card">
                <div class="summary-icon blue">
                    <i class="fa-solid fa-bus"></i>
                </div>

                <div class="summary-content">
                    <span>Total Shuttle Buses</span>
                    <h2>18</h2>
                    <small>Registered fleet units</small>
                </div>
            </div>


            <div class="summary-card">
                <div class="summary-icon green">
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <div class="summary-content">
                    <span>Operational</span>
                    <h2>14</h2>
                    <small>Currently available</small>
                </div>
            </div>


            <div class="summary-card">
                <div class="summary-icon orange">
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                </div>

                <div class="summary-content">
                    <span>Approaching PMS</span>
                    <h2>3</h2>
                    <small>Maintenance due soon</small>
                </div>
            </div>


            <div class="summary-card">
                <div class="summary-icon red">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>

                <div class="summary-content">
                    <span>Maintenance Alerts</span>
                    <h2>2</h2>
                    <small>Require attention</small>
                </div>
            </div>

        </section>


        {{-- =================================================
            FLEET CONDITION
        ================================================== --}}
        <section class="content-card">

            <div class="section-header">
                <div>
                    <h2>Fleet Health Overview</h2>
                    <p>
                        Current condition and preventive maintenance status
                        of each shuttle bus.
                    </p>
                </div>

                <div class="legend">
                    <span>
                        <i class="legend-dot operational"></i>
                        Operational
                    </span>

                    <span>
                        <i class="legend-dot warning"></i>
                        PMS Soon
                    </span>

                    <span>
                        <i class="legend-dot critical"></i>
                        Attention
                    </span>
                </div>
            </div>


            <div class="toolbar">

                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input
                        type="text"
                        placeholder="Search bus number or plate number..."
                    >
                </div>

                <select>
                    <option>All Statuses</option>
                    <option>Operational</option>
                    <option>Under Maintenance</option>
                    <option>For Inspection</option>
                    <option>Inactive</option>
                </select>

                <select>
                    <option>All PMS Status</option>
                    <option>Normal</option>
                    <option>Due Soon</option>
                    <option>Overdue</option>
                </select>

            </div>


            <div class="table-wrapper">
                <table class="health-table">

                    <thead>
                        <tr>
                            <th>BUS</th>
                            <th>OPERATIONAL STATUS</th>
                            <th>CURRENT MILEAGE</th>
                            <th>NEXT PMS</th>
                            <th>REMAINING</th>
                            <th>PMS FORECAST</th>
                            <th>HEALTH STATUS</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>

                    <tbody>

                        {{-- Bus 1 --}}
                        <tr>
                            <td>
                                <div class="bus-cell">
                                    <div class="bus-icon">
                                        <i class="fa-solid fa-bus-simple"></i>
                                    </div>

                                    <div>
                                        <strong>ABC-1234</strong>
                                        <span>Toyota Coaster</span>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span class="status-badge operational">
                                    Operational
                                </span>
                            </td>

                            <td>
                                <strong>24,350 km</strong>
                            </td>

                            <td>
                                <strong>25,000 km</strong>
                            </td>

                            <td>
                                <span class="remaining warning-text">
                                    650 km
                                </span>
                            </td>

                            <td>
                                <div class="forecast-cell">
                                    <strong>~7 days</strong>
                                    <span>Based on recent mileage</span>
                                </div>
                            </td>

                            <td>
                                <span class="health-badge warning">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    PMS Soon
                                </span>
                            </td>

                            <td>
                                <button type="button" class="table-action">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </td>
                        </tr>


                        {{-- Bus 2 --}}
                        <tr>
                            <td>
                                <div class="bus-cell">
                                    <div class="bus-icon">
                                        <i class="fa-solid fa-bus-simple"></i>
                                    </div>

                                    <div>
                                        <strong>DEF-5678</strong>
                                        <span>Mitsubishi Rosa</span>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span class="status-badge operational">
                                    Operational
                                </span>
                            </td>

                            <td>
                                <strong>17,220 km</strong>
                            </td>

                            <td>
                                <strong>20,000 km</strong>
                            </td>

                            <td>
                                <span class="remaining">
                                    2,780 km
                                </span>
                            </td>

                            <td>
                                <div class="forecast-cell">
                                    <strong>~31 days</strong>
                                    <span>Within normal range</span>
                                </div>
                            </td>

                            <td>
                                <span class="health-badge good">
                                    <i class="fa-solid fa-circle-check"></i>
                                    Healthy
                                </span>
                            </td>

                            <td>
                                <button type="button" class="table-action">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </td>
                        </tr>


                        {{-- Bus 3 --}}
                        <tr>
                            <td>
                                <div class="bus-cell">
                                    <div class="bus-icon">
                                        <i class="fa-solid fa-bus-simple"></i>
                                    </div>

                                    <div>
                                        <strong>GHI-9012</strong>
                                        <span>Isuzu Journey</span>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span class="status-badge maintenance">
                                    Under Maintenance
                                </span>
                            </td>

                            <td>
                                <strong>30,420 km</strong>
                            </td>

                            <td>
                                <strong>30,000 km</strong>
                            </td>

                            <td>
                                <span class="remaining danger-text">
                                    420 km overdue
                                </span>
                            </td>

                            <td>
                                <div class="forecast-cell">
                                    <strong>Overdue</strong>
                                    <span>PMS threshold exceeded</span>
                                </div>
                            </td>

                            <td>
                                <span class="health-badge critical">
                                    <i class="fa-solid fa-circle-exclamation"></i>
                                    Critical
                                </span>
                            </td>

                            <td>
                                <button type="button" class="table-action">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </td>
                        </tr>


                        {{-- Bus 4 --}}
                        <tr>
                            <td>
                                <div class="bus-cell">
                                    <div class="bus-icon">
                                        <i class="fa-solid fa-bus-simple"></i>
                                    </div>

                                    <div>
                                        <strong>MNO-7890</strong>
                                        <span>Toyota Coaster</span>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span class="status-badge operational">
                                    Operational
                                </span>
                            </td>

                            <td>
                                <strong>38,720 km</strong>
                            </td>

                            <td>
                                <strong>40,000 km</strong>
                            </td>

                            <td>
                                <span class="remaining warning-text">
                                    1,280 km
                                </span>
                            </td>

                            <td>
                                <div class="forecast-cell">
                                    <strong>~14 days</strong>
                                    <span>Approaching threshold</span>
                                </div>
                            </td>

                            <td>
                                <span class="health-badge warning">
                                    <i class="fa-solid fa-clock"></i>
                                    Monitor
                                </span>
                            </td>

                            <td>
                                <button type="button" class="table-action">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </td>
                        </tr>


                        {{-- Bus 5 --}}
                        <tr>
                            <td>
                                <div class="bus-cell">
                                    <div class="bus-icon">
                                        <i class="fa-solid fa-bus-simple"></i>
                                    </div>

                                    <div>
                                        <strong>JKL-3456</strong>
                                        <span>Mitsubishi Rosa</span>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span class="status-badge inspection">
                                    For Inspection
                                </span>
                            </td>

                            <td>
                                <strong>21,840 km</strong>
                            </td>

                            <td>
                                <strong>25,000 km</strong>
                            </td>

                            <td>
                                <span class="remaining">
                                    3,160 km
                                </span>
                            </td>

                            <td>
                                <div class="forecast-cell">
                                    <strong>~40 days</strong>
                                    <span>PMS not yet due</span>
                                </div>
                            </td>

                            <td>
                                <span class="health-badge inspection">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    Inspection
                                </span>
                            </td>

                            <td>
                                <button type="button" class="table-action">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </td>
                        </tr>

                    </tbody>

                </table>
            </div>

        </section>


        {{-- =================================================
            ANALYTICS GRID
        ================================================== --}}
        <section class="analytics-grid">

            {{-- Mileage / PMS --}}
            <div class="analytics-card">

                <div class="card-header">
                    <div>
                        <h2>PMS Mileage Monitoring</h2>
                        <p>
                            Distance remaining before the next preventive maintenance threshold.
                        </p>
                    </div>

                    <div class="card-icon blue">
                        <i class="fa-solid fa-gauge-high"></i>
                    </div>
                </div>


                <div class="mileage-list">

                    <div class="mileage-item">
                        <div class="mileage-top">
                            <div>
                                <strong>ABC-1234</strong>
                                <span>24,350 / 25,000 km</span>
                            </div>

                            <strong class="warning-text">97%</strong>
                        </div>

                        <div class="progress">
                            <div
                                class="progress-bar warning"
                                style="width: 97%;"
                            ></div>
                        </div>
                    </div>


                    <div class="mileage-item">
                        <div class="mileage-top">
                            <div>
                                <strong>MNO-7890</strong>
                                <span>38,720 / 40,000 km</span>
                            </div>

                            <strong class="warning-text">97%</strong>
                        </div>

                        <div class="progress">
                            <div
                                class="progress-bar warning"
                                style="width: 97%;"
                            ></div>
                        </div>
                    </div>


                    <div class="mileage-item">
                        <div class="mileage-top">
                            <div>
                                <strong>DEF-5678</strong>
                                <span>17,220 / 20,000 km</span>
                            </div>

                            <strong>86%</strong>
                        </div>

                        <div class="progress">
                            <div
                                class="progress-bar normal"
                                style="width: 86%;"
                            ></div>
                        </div>
                    </div>


                    <div class="mileage-item">
                        <div class="mileage-top">
                            <div>
                                <strong>GHI-9012</strong>
                                <span>30,420 / 30,000 km</span>
                            </div>

                            <strong class="danger-text">Overdue</strong>
                        </div>

                        <div class="progress">
                            <div
                                class="progress-bar critical"
                                style="width: 100%;"
                            ></div>
                        </div>
                    </div>

                </div>

            </div>


            {{-- Maintenance indicators --}}
            <div class="analytics-card">

                <div class="card-header">
                    <div>
                        <h2>Maintenance Indicators</h2>
                        <p>
                            Buses that require closer monitoring based on
                            maintenance records and current condition.
                        </p>
                    </div>

                    <div class="card-icon orange">
                        <i class="fa-solid fa-screwdriver-wrench"></i>
                    </div>
                </div>


                <div class="risk-list">

                    <div class="risk-item critical">
                        <div class="risk-icon">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>

                        <div class="risk-content">
                            <div class="risk-title">
                                <strong>GHI-9012</strong>
                                <span class="risk-level critical">Critical</span>
                            </div>

                            <p>
                                PMS threshold exceeded by 420 km and vehicle is
                                currently under maintenance.
                            </p>
                        </div>
                    </div>


                    <div class="risk-item warning">
                        <div class="risk-icon">
                            <i class="fa-solid fa-clock"></i>
                        </div>

                        <div class="risk-content">
                            <div class="risk-title">
                                <strong>ABC-1234</strong>
                                <span class="risk-level warning">High</span>
                            </div>

                            <p>
                                Only 650 km remaining before the next scheduled
                                preventive maintenance threshold.
                            </p>
                        </div>
                    </div>


                    <div class="risk-item warning">
                        <div class="risk-icon">
                            <i class="fa-solid fa-gauge-high"></i>
                        </div>

                        <div class="risk-content">
                            <div class="risk-title">
                                <strong>MNO-7890</strong>
                                <span class="risk-level warning">Monitor</span>
                            </div>

                            <p>
                                Projected to reach its next PMS threshold within
                                approximately two weeks.
                            </p>
                        </div>
                    </div>

                </div>

            </div>

        </section>


        {{-- =================================================
            MAINTENANCE ALERTS
        ================================================== --}}
        <section class="content-card alerts-section">

            <div class="section-header">
                <div>
                    <h2>Maintenance Alerts & Recommendations</h2>
                    <p>
                        Prescriptive actions generated from mileage thresholds
                        and fleet maintenance conditions.
                    </p>
                </div>

                <span class="alerts-count">
                    3 Active Alerts
                </span>
            </div>


            <div class="recommendations-grid">

                <article class="recommendation-card critical">

                    <div class="recommendation-icon">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>

                    <div class="recommendation-content">
                        <div class="recommendation-header">
                            <div>
                                <span class="recommendation-type">
                                    Immediate Maintenance
                                </span>

                                <h3>GHI-9012 exceeded its PMS threshold</h3>
                            </div>

                            <span class="priority critical">
                                Critical
                            </span>
                        </div>

                        <p>
                            Current mileage is 30,420 km while the scheduled PMS
                            threshold is 30,000 km.
                        </p>

                        <div class="recommended-action">
                            <i class="fa-solid fa-lightbulb"></i>

                            <div>
                                <span>Recommended Action</span>
                                <strong>
                                    Keep the bus unavailable for dispatch until
                                    preventive maintenance is completed.
                                </strong>
                            </div>
                        </div>
                    </div>

                </article>


                <article class="recommendation-card warning">

                    <div class="recommendation-icon">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>

                    <div class="recommendation-content">
                        <div class="recommendation-header">
                            <div>
                                <span class="recommendation-type">
                                    PMS Scheduling
                                </span>

                                <h3>ABC-1234 is approaching PMS</h3>
                            </div>

                            <span class="priority warning">
                                High
                            </span>
                        </div>

                        <p>
                            The bus has approximately 650 km remaining before
                            reaching its 25,000 km maintenance threshold.
                        </p>

                        <div class="recommended-action">
                            <i class="fa-solid fa-lightbulb"></i>

                            <div>
                                <span>Recommended Action</span>
                                <strong>
                                    Schedule PMS within approximately 7 days
                                    based on recent vehicle usage.
                                </strong>
                            </div>
                        </div>
                    </div>

                </article>


                <article class="recommendation-card info">

                    <div class="recommendation-icon">
                        <i class="fa-solid fa-eye"></i>
                    </div>

                    <div class="recommendation-content">
                        <div class="recommendation-header">
                            <div>
                                <span class="recommendation-type">
                                    Fleet Monitoring
                                </span>

                                <h3>MNO-7890 requires closer mileage monitoring</h3>
                            </div>

                            <span class="priority info">
                                Monitor
                            </span>
                        </div>

                        <p>
                            The bus has reached approximately 97% of its next
                            scheduled PMS mileage threshold.
                        </p>

                        <div class="recommended-action">
                            <i class="fa-solid fa-lightbulb"></i>

                            <div>
                                <span>Recommended Action</span>
                                <strong>
                                    Prepare a maintenance slot and continue
                                    monitoring accumulated mileage.
                                </strong>
                            </div>
                        </div>
                    </div>

                </article>

            </div>

        </section>


        {{-- =================================================
            METHODOLOGY NOTE
        ================================================== --}}
        <section class="analytics-note">
            <div class="note-icon">
                <i class="fa-solid fa-circle-info"></i>
            </div>

            <div>
                <strong>Bus Health Analytics</strong>

                <p>
                    PMS forecasts shown on this static page represent projected
                    maintenance timing based on accumulated mileage and recent
                    vehicle usage. These forecasts indicate when preventive
                    maintenance may be required and should not be interpreted as
                    an exact prediction of vehicle breakdown.
                </p>
            </div>
        </section>

    </main>

</x-layout.app>