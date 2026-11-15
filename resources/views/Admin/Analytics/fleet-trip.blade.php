<x-layout.app
    title="FROMS - Fleet & Trip Analytics"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/Analytics/fleet-trip.css',
        'resources/js/Main-js/sidebar.js'
    ]"
>
    <div class="app">

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

        <main class="main fleet-trip-page">

            <x-layout.topbar
                title="Fleet & Trip Analytics"
                subtitle="Analyze fleet utilization, trip activity, route performance, and shuttle usage"
                notification-count="6"
            />


            {{-- =====================================================
                TRIP PERFORMANCE HERO
            ====================================================== --}}
            <section class="trip-hero">

                <div class="trip-hero-content">

                    <span class="trip-hero-label">
                        <i class="fa-solid fa-route"></i>
                        Monthly Operations
                    </span>

                    <h2>
                        Trip activity increased while fleet availability remains stable.
                    </h2>

                    <p>
                        FROMS recorded 286 completed trips this month with 18 of 22 shuttle
                        units currently operational.
                    </p>

                    <div class="trip-hero-stats">

                        <div>
                            <span>Completed Trips</span>
                            <strong>286</strong>
                        </div>

                        <div>
                            <span>Fleet Availability</span>
                            <strong>81.8%</strong>
                        </div>

                        <div>
                            <span>Trip Growth</span>
                            <strong class="positive">+8.2%</strong>
                        </div>

                    </div>

                </div>

                <div class="trip-hero-visual">

                    <div class="trip-route-line">

                        <div class="route-node start">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>

                        <div class="route-track">

                            <span class="route-progress"></span>

                            <div class="bus-marker">
                                <i class="fa-solid fa-bus"></i>
                            </div>

                        </div>

                        <div class="route-node finish">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>

                    </div>

                    <div class="trip-route-meta">

                        <span>Fleet Activity</span>

                        <strong>
                            18 Active Buses
                        </strong>

                        <small>
                            4 buses currently unavailable
                        </small>

                    </div>

                </div>

            </section>


            {{-- =====================================================
                KPI STRIP
            ====================================================== --}}
            <section class="stats-grid fleet-summary-grid">

                <x-ui.summary-card
                    label="Total Buses"
                    value="22"
                    small="Registered shuttle units"
                    icon="fa-bus"
                    color="blue"
                />

                <x-ui.summary-card
                    label="Active Buses"
                    value="18"
                    small="Currently operational"
                    icon="fa-circle-check"
                    color="green"
                />

                <x-ui.summary-card
                    label="Trips This Month"
                    value="286"
                    small="Completed trip records"
                    icon="fa-route"
                    color="yellow"
                />

                <x-ui.summary-card
                    label="Fleet Availability"
                    value="81.8%"
                    small="Operational buses versus total fleet"
                    icon="fa-chart-line"
                    color="blue"
                />

            </section>


            {{-- =====================================================
                FILTER
            ====================================================== --}}
            <section class="fleet-filter-bar">

                <div>

                    <span class="section-kicker">
                        Trip Analysis
                    </span>

                    <h2>
                        Fleet Performance
                    </h2>

                    <p>
                        Review operational trends from recorded shuttle and trip data.
                    </p>

                </div>

                <div class="fleet-filters">

                    <select>
                        <option>This Month</option>
                        <option>Last 30 Days</option>
                        <option>Last 3 Months</option>
                        <option>This Year</option>
                    </select>

                    <select>
                        <option>All Buses</option>
                        <option>BUS-003</option>
                        <option>BUS-007</option>
                        <option>BUS-012</option>
                        <option>BUS-015</option>
                        <option>BUS-018</option>
                    </select>

                </div>

            </section>


            {{-- =====================================================
                PRIMARY ANALYTICS
            ====================================================== --}}
            <section class="trip-primary-grid">

                {{-- TRIP ACTIVITY --}}
                <article class="trip-panel trip-activity-panel">

                    <div class="trip-panel-header">

                        <div>
                            <span class="section-kicker">
                                Descriptive Analytics
                            </span>

                            <h2>
                                Trip Activity Trend
                            </h2>

                            <p>
                                Completed trip records across the current month.
                            </p>
                        </div>

                        <span class="trend-badge positive">
                            <i class="fa-solid fa-arrow-trend-up"></i>
                            +8.2%
                        </span>

                    </div>


                    <div class="trip-chart-area">

                        <div class="chart-scale">

                            <span>80</span>
                            <span>60</span>
                            <span>40</span>
                            <span>20</span>
                            <span>0</span>

                        </div>

                        <div class="trip-bars">

                            <div class="chart-grid-line grid-1"></div>
                            <div class="chart-grid-line grid-2"></div>
                            <div class="chart-grid-line grid-3"></div>
                            <div class="chart-grid-line grid-4"></div>

                            <div class="trip-bar-column">

                                <span class="bar-value">
                                    52
                                </span>

                                <div
                                    class="trip-bar"
                                    style="height: 60%;"
                                ></div>

                                <small>
                                    Week 1
                                </small>

                            </div>

                            <div class="trip-bar-column">

                                <span class="bar-value">
                                    67
                                </span>

                                <div
                                    class="trip-bar"
                                    style="height: 76%;"
                                ></div>

                                <small>
                                    Week 2
                                </small>

                            </div>

                            <div class="trip-bar-column">

                                <span class="bar-value">
                                    82
                                </span>

                                <div
                                    class="trip-bar"
                                    style="height: 92%;"
                                ></div>

                                <small>
                                    Week 3
                                </small>

                            </div>

                            <div class="trip-bar-column">

                                <span class="bar-value">
                                    85
                                </span>

                                <div
                                    class="trip-bar"
                                    style="height: 96%;"
                                ></div>

                                <small>
                                    Week 4
                                </small>

                            </div>

                        </div>

                    </div>


                    <div class="trip-chart-footer">

                        <div>
                            <span class="chart-dot"></span>

                            <span>
                                Completed Trips
                            </span>
                        </div>

                        <strong>
                            286 Total
                        </strong>

                    </div>

                </article>


                {{-- FLEET AVAILABILITY --}}
                <article class="trip-panel fleet-availability-panel">

                    <div class="trip-panel-header">

                        <div>
                            <span class="section-kicker">
                                Fleet Status
                            </span>

                            <h2>
                                Fleet Availability
                            </h2>

                            <p>
                                Current operational condition of registered shuttle buses.
                            </p>
                        </div>

                    </div>


                    <div class="availability-score">

                        <div class="availability-ring">

                            <div class="availability-ring-center">
                                <strong>81.8%</strong>
                                <span>Available</span>
                            </div>

                        </div>

                    </div>


                    <div class="availability-breakdown">

                        <div class="availability-row">

                            <div>
                                <span class="availability-dot operational"></span>

                                <span>
                                    Operational
                                </span>
                            </div>

                            <strong>
                                18
                            </strong>

                        </div>


                        <div class="availability-row">

                            <div>
                                <span class="availability-dot maintenance"></span>

                                <span>
                                    Under Maintenance
                                </span>
                            </div>

                            <strong>
                                3
                            </strong>

                        </div>


                        <div class="availability-row">

                            <div>
                                <span class="availability-dot inactive"></span>

                                <span>
                                    Inactive
                                </span>
                            </div>

                            <strong>
                                1
                            </strong>

                        </div>

                    </div>

                </article>

            </section>


            {{-- =====================================================
                ROUTE LEADERBOARD
            ====================================================== --}}
            <section class="trip-panel route-leaderboard-panel">

                <div class="trip-panel-header">

                    <div>
                        <span class="section-kicker">
                            Diagnostic Analytics
                        </span>

                        <h2>
                            Route Activity Leaderboard
                        </h2>

                        <p>
                            Compare trip volume across frequently recorded routes.
                        </p>
                    </div>

                    <span class="period-pill">
                        Current Month
                    </span>

                </div>


                <div class="route-leaderboard">

                    <div class="route-ranking first">

                        <div class="ranking-number">
                            01
                        </div>

                        <div class="route-ranking-icon">
                            <i class="fa-solid fa-route"></i>
                        </div>

                        <div class="route-ranking-content">

                            <strong>
                                Malvar - Lipa
                            </strong>

                            <span>
                                78 completed trips
                            </span>

                            <div class="route-ranking-progress">
                                <span style="width: 100%;"></span>
                            </div>

                        </div>

                        <div class="route-ranking-value">
                            <strong>27.3%</strong>
                            <span>of total trips</span>
                        </div>

                    </div>


                    <div class="route-ranking">

                        <div class="ranking-number">
                            02
                        </div>

                        <div class="route-ranking-icon">
                            <i class="fa-solid fa-route"></i>
                        </div>

                        <div class="route-ranking-content">

                            <strong>
                                Malvar - Tanauan
                            </strong>

                            <span>
                                69 completed trips
                            </span>

                            <div class="route-ranking-progress">
                                <span style="width: 88%;"></span>
                            </div>

                        </div>

                        <div class="route-ranking-value">
                            <strong>24.1%</strong>
                            <span>of total trips</span>
                        </div>

                    </div>


                    <div class="route-ranking">

                        <div class="ranking-number">
                            03
                        </div>

                        <div class="route-ranking-icon">
                            <i class="fa-solid fa-route"></i>
                        </div>

                        <div class="route-ranking-content">

                            <strong>
                                Malvar - Sto. Tomas
                            </strong>

                            <span>
                                61 completed trips
                            </span>

                            <div class="route-ranking-progress">
                                <span style="width: 78%;"></span>
                            </div>

                        </div>

                        <div class="route-ranking-value">
                            <strong>21.3%</strong>
                            <span>of total trips</span>
                        </div>

                    </div>


                    <div class="route-ranking">

                        <div class="ranking-number">
                            04
                        </div>

                        <div class="route-ranking-icon">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>

                        <div class="route-ranking-content">

                            <strong>
                                Other Routes
                            </strong>

                            <span>
                                78 completed trips
                            </span>

                            <div class="route-ranking-progress">
                                <span style="width: 100%;"></span>
                            </div>

                        </div>

                        <div class="route-ranking-value">
                            <strong>27.3%</strong>
                            <span>of total trips</span>
                        </div>

                    </div>

                </div>

            </section>


            {{-- =====================================================
                BUS UTILIZATION
            ====================================================== --}}
            @php
                $buses = [
                    [
                        'bus' => 'BUS-012',
                        'trips' => 26,
                        'distance' => '1,482 km',
                        'avgTrip' => '57 km',
                        'utilization' => 92,
                        'status' => 'High Use',
                    ],
                    [
                        'bus' => 'BUS-007',
                        'trips' => 23,
                        'distance' => '1,318 km',
                        'avgTrip' => '57 km',
                        'utilization' => 88,
                        'status' => 'High Use',
                    ],
                    [
                        'bus' => 'BUS-003',
                        'trips' => 20,
                        'distance' => '1,108 km',
                        'avgTrip' => '55 km',
                        'utilization' => 79,
                        'status' => 'Normal',
                    ],
                    [
                        'bus' => 'BUS-018',
                        'trips' => 18,
                        'distance' => '974 km',
                        'avgTrip' => '54 km',
                        'utilization' => 72,
                        'status' => 'Normal',
                    ],
                    [
                        'bus' => 'BUS-015',
                        'trips' => 11,
                        'distance' => '608 km',
                        'avgTrip' => '55 km',
                        'utilization' => 44,
                        'status' => 'Maintenance',
                    ],
                ];
            @endphp

            <section class="trip-utilization-section">

                <div class="section-heading">

                    <div>
                        <span class="section-kicker">
                            Fleet Comparison
                        </span>

                        <h2>
                            Bus Utilization
                        </h2>

                        <p>
                            Compare shuttle usage using recorded trips and distance traveled.
                        </p>
                    </div>

                    <span class="diagnostic-pill">
                        Diagnostic
                    </span>

                </div>


                <div class="utilization-card-grid">

                    @foreach($buses as $bus)

                        @php
                            $statusClass = match($bus['status']) {
                                'High Use' => 'high',
                                'Maintenance' => 'maintenance',
                                default => 'normal',
                            };
                        @endphp

                        <article class="bus-utilization-card">

                            <div class="bus-utilization-header">

                                <div class="bus-identity">

                                    <div class="bus-icon">
                                        <i class="fa-solid fa-bus"></i>
                                    </div>

                                    <div>
                                        <strong>
                                            {{ $bus['bus'] }}
                                        </strong>

                                        <span>
                                            {{ $bus['trips'] }} completed trips
                                        </span>
                                    </div>

                                </div>

                                <span class="usage-badge {{ $statusClass }}">
                                    {{ $bus['status'] }}
                                </span>

                            </div>


                            <div class="bus-utilization-score">

                                <strong>
                                    {{ $bus['utilization'] }}%
                                </strong>

                                <span>
                                    Utilization
                                </span>

                            </div>


                            <div class="bus-utilization-progress">

                                <span
                                    class="{{ $statusClass }}"
                                    style="width: {{ $bus['utilization'] }}%;"
                                ></span>

                            </div>


                            <div class="bus-utilization-details">

                                <div>
                                    <span>Distance</span>
                                    <strong>{{ $bus['distance'] }}</strong>
                                </div>

                                <div>
                                    <span>Avg. Trip</span>
                                    <strong>{{ $bus['avgTrip'] }}</strong>
                                </div>

                            </div>

                        </article>

                    @endforeach

                </div>

            </section>


            {{-- =====================================================
                FINDINGS
            ====================================================== --}}
            <section class="trip-findings-layout">

                <div class="trip-findings-heading">

                    <span class="section-kicker">
                        Operational Findings
                    </span>

                    <h2>
                        What the trip records indicate
                    </h2>

                    <p>
                        Key observations generated from current fleet and trip activity.
                    </p>

                </div>


                <div class="trip-findings-list">

                    <article class="trip-finding good">

                        <div class="finding-icon">
                            <i class="fa-solid fa-arrow-trend-up"></i>
                        </div>

                        <div>
                            <span>Trip Activity</span>

                            <strong>
                                Trip volume increased by 8.2%.
                            </strong>

                            <p>
                                More completed trips were recorded compared with the previous period.
                            </p>
                        </div>

                    </article>


                    <article class="trip-finding warning">

                        <div class="finding-icon">
                            <i class="fa-solid fa-clock"></i>
                        </div>

                        <div>
                            <span>Travel Time</span>

                            <strong>
                                12 trips exceeded average travel time.
                            </strong>

                            <p>
                                Review affected route conditions and historical trip duration.
                            </p>
                        </div>

                    </article>


                    <article class="trip-finding info">

                        <div class="finding-icon">
                            <i class="fa-solid fa-bus"></i>
                        </div>

                        <div>
                            <span>Fleet Usage</span>

                            <strong>
                                BUS-012 and BUS-007 show higher utilization.
                            </strong>

                            <p>
                                Available underutilized buses may help distribute accumulated mileage.
                            </p>
                        </div>

                    </article>

                </div>

            </section>


            {{-- =====================================================
                RECOMMENDATION
            ====================================================== --}}
            <section class="fleet-recommendation">

                <div class="recommendation-icon">
                    <i class="fa-solid fa-lightbulb"></i>
                </div>

                <div class="recommendation-content">

                    <span>
                        Operational Recommendation
                    </span>

                    <h2>
                        Balance trip assignments across available shuttle units.
                    </h2>

                    <p>
                        BUS-012 and BUS-007 currently have higher utilization than the
                        fleet average. Assigning suitable trips to available underutilized
                        buses may help distribute mileage more evenly.
                    </p>

                </div>

                <a
                    href="{{ route('analytics.recommendations') }}"
                    class="recommendation-link"
                >
                    View Recommendations
                    <i class="fa-solid fa-arrow-right"></i>
                </a>

            </section>

        </main>

    </div>

</x-layout.app>