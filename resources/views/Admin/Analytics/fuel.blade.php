<x-layout.app
    title="FROMS - Fuel Analytics"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/Analytics/fuel.css',
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

        <main class="main fuel-analytics-page">

            <x-layout.topbar
                title="Fuel Analytics"
                subtitle="Analyze fuel consumption, efficiency, mileage, and high-consumption shuttle units"
                notification-count="6"
            />

            {{-- SUMMARY CARDS --}}
            <section class="stats-grid fuel-summary-grid">

                <x-ui.summary-card
                    label="Fuel Used This Month"
                    value="3,842 L"
                    small="Recorded fleet fuel consumption"
                    icon="fa-gas-pump"
                    color="blue"
                />

                <x-ui.summary-card
                    label="Average Efficiency"
                    value="6.8 km/L"
                    small="Fleet average fuel efficiency"
                    icon="fa-gauge-high"
                    color="green"
                />

                <x-ui.summary-card
                    label="High Consumption"
                    value="3"
                    small="Buses requiring review"
                    icon="fa-triangle-exclamation"
                    color="yellow"
                />

                <x-ui.summary-card
                    label="Distance Traveled"
                    value="26,126 km"
                    small="Recorded fleet distance this month"
                    icon="fa-road"
                    color="blue"
                />

            </section>


            {{-- FILTERS --}}
            <section class="fuel-filter-card">

                <div>
                    <h2>Fuel Performance</h2>
                    <p>Review fuel usage from recorded fuel reports and mileage data.</p>
                </div>

                <div class="fuel-filters">

                    <select>
                        <option>This Month</option>
                        <option>Last 30 Days</option>
                        <option>Last 3 Months</option>
                        <option>This Year</option>
                    </select>

                    <select>
                        <option>All Buses</option>
                        <option>BUS-001</option>
                        <option>BUS-003</option>
                        <option>BUS-007</option>
                        <option>BUS-012</option>
                        <option>BUS-015</option>
                    </select>

                </div>

            </section>


            {{-- FIRST ROW --}}
            <section class="fuel-grid">

                {{-- FUEL TREND --}}
                <article class="fuel-card">

                    <div class="fuel-card-header">

                        <div>
                            <h2>Fuel Consumption Trend</h2>
                            <p>Recorded fuel usage throughout the current month.</p>
                        </div>

                        <span class="analytics-badge descriptive">
                            Descriptive
                        </span>

                    </div>

                    <div class="fuel-chart">

                        <div class="chart-y-axis">
                            <span>1,200 L</span>
                            <span>900 L</span>
                            <span>600 L</span>
                            <span>300 L</span>
                            <span>0 L</span>
                        </div>

                        <div class="chart-content">

                            <div class="chart-line line-1"></div>
                            <div class="chart-line line-2"></div>
                            <div class="chart-line line-3"></div>
                            <div class="chart-line line-4"></div>

                            <div class="chart-column">
                                <div class="chart-bar" style="height: 72%;"></div>
                                <span>Week 1</span>
                            </div>

                            <div class="chart-column">
                                <div class="chart-bar" style="height: 78%;"></div>
                                <span>Week 2</span>
                            </div>

                            <div class="chart-column">
                                <div class="chart-bar" style="height: 86%;"></div>
                                <span>Week 3</span>
                            </div>

                            <div class="chart-column">
                                <div class="chart-bar" style="height: 84%;"></div>
                                <span>Week 4</span>
                            </div>

                        </div>

                    </div>

                    <div class="chart-summary">

                        <div>
                            <span class="chart-dot"></span>
                            Fuel Used
                        </div>

                        <strong>3,842 L Total</strong>

                    </div>

                </article>


                {{-- EFFICIENCY OVERVIEW --}}
                <article class="fuel-card">

                    <div class="fuel-card-header">

                        <div>
                            <h2>Fuel Efficiency Overview</h2>
                            <p>Fleet efficiency compared with the current average.</p>
                        </div>

                        <span class="analytics-badge diagnostic">
                            Diagnostic
                        </span>

                    </div>

                    <div class="efficiency-overview">

                        <div class="efficiency-gauge">

                            <div class="gauge-center">
                                <strong>6.8</strong>
                                <span>km/L</span>
                            </div>

                        </div>

                        <div class="efficiency-list">

                            <div class="efficiency-row">
                                <span>Best Performing</span>
                                <strong>7.6 km/L</strong>
                            </div>

                            <div class="efficiency-row">
                                <span>Fleet Average</span>
                                <strong>6.8 km/L</strong>
                            </div>

                            <div class="efficiency-row">
                                <span>Lowest Efficiency</span>
                                <strong>5.7 km/L</strong>
                            </div>

                        </div>

                    </div>

                </article>

            </section>


            {{-- SECOND ROW --}}
            <section class="fuel-grid">

                {{-- HIGH CONSUMPTION --}}
                <article class="fuel-card">

                    <div class="fuel-card-header">

                        <div>
                            <h2>High Consumption Buses</h2>
                            <p>Buses currently below the fleet's average fuel efficiency.</p>
                        </div>

                        <span class="analytics-badge diagnostic">
                            Diagnostic
                        </span>

                    </div>

                    <div class="high-consumption-list">

                        <div class="consumption-item">

                            <div class="consumption-main">

                                <div class="consumption-icon critical">
                                    <i class="fa-solid fa-bus"></i>
                                </div>

                                <div>
                                    <strong>BUS-012</strong>
                                    <span>Fuel Used: 312 L · Distance: 1,778 km</span>
                                </div>

                            </div>

                            <div class="consumption-value critical">
                                <strong>5.7 km/L</strong>
                                <span>Review</span>
                            </div>

                        </div>


                        <div class="consumption-item">

                            <div class="consumption-main">

                                <div class="consumption-icon warning">
                                    <i class="fa-solid fa-bus"></i>
                                </div>

                                <div>
                                    <strong>BUS-007</strong>
                                    <span>Fuel Used: 286 L · Distance: 1,687 km</span>
                                </div>

                            </div>

                            <div class="consumption-value warning">
                                <strong>5.9 km/L</strong>
                                <span>Below Average</span>
                            </div>

                        </div>


                        <div class="consumption-item">

                            <div class="consumption-main">

                                <div class="consumption-icon warning">
                                    <i class="fa-solid fa-bus"></i>
                                </div>

                                <div>
                                    <strong>BUS-015</strong>
                                    <span>Fuel Used: 224 L · Distance: 1,366 km</span>
                                </div>

                            </div>

                            <div class="consumption-value warning">
                                <strong>6.1 km/L</strong>
                                <span>Below Average</span>
                            </div>

                        </div>

                    </div>

                </article>


                {{-- FUEL FINDINGS --}}
                <article class="fuel-card">

                    <div class="fuel-card-header">

                        <div>
                            <h2>Fuel Findings</h2>
                            <p>Key observations from current fuel and mileage records.</p>
                        </div>

                        <span class="analytics-badge diagnostic">
                            Diagnostic
                        </span>

                    </div>

                    <div class="fuel-findings">

                        <div class="fuel-finding warning">

                            <div class="finding-icon">
                                <i class="fa-solid fa-gas-pump"></i>
                            </div>

                            <div>
                                <strong>Fuel consumption increased by 5.4%</strong>
                                <p>
                                    Total recorded fuel usage is higher than the previous period.
                                </p>
                            </div>

                        </div>


                        <div class="fuel-finding info">

                            <div class="finding-icon">
                                <i class="fa-solid fa-route"></i>
                            </div>

                            <div>
                                <strong>Higher usage aligns with increased trip activity</strong>
                                <p>
                                    Compare route distance and trip volume before classifying usage as abnormal.
                                </p>
                            </div>

                        </div>


                        <div class="fuel-finding critical">

                            <div class="finding-icon">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>

                            <div>
                                <strong>3 buses remain below the fleet efficiency average</strong>
                                <p>
                                    Review mileage, maintenance condition, and historical fuel reports.
                                </p>
                            </div>

                        </div>

                    </div>

                </article>

            </section>


            {{-- BUS TABLE --}}
            @php
                $fuelRecords = [
                    [
                        'bus' => 'BUS-003',
                        'distance' => '1,520 km',
                        'fuel' => '200 L',
                        'efficiency' => '7.6 km/L',
                        'change' => '+3.2%',
                        'status' => 'Efficient',
                    ],
                    [
                        'bus' => 'BUS-018',
                        'distance' => '1,404 km',
                        'fuel' => '195 L',
                        'efficiency' => '7.2 km/L',
                        'change' => '+1.4%',
                        'status' => 'Normal',
                    ],
                    [
                        'bus' => 'BUS-009',
                        'distance' => '1,368 km',
                        'fuel' => '198 L',
                        'efficiency' => '6.9 km/L',
                        'change' => '+0.5%',
                        'status' => 'Normal',
                    ],
                    [
                        'bus' => 'BUS-015',
                        'distance' => '1,366 km',
                        'fuel' => '224 L',
                        'efficiency' => '6.1 km/L',
                        'change' => '-4.8%',
                        'status' => 'Review',
                    ],
                    [
                        'bus' => 'BUS-007',
                        'distance' => '1,687 km',
                        'fuel' => '286 L',
                        'efficiency' => '5.9 km/L',
                        'change' => '-6.2%',
                        'status' => 'Review',
                    ],
                    [
                        'bus' => 'BUS-012',
                        'distance' => '1,778 km',
                        'fuel' => '312 L',
                        'efficiency' => '5.7 km/L',
                        'change' => '-8.1%',
                        'status' => 'High Consumption',
                    ],
                ];
            @endphp

            <section class="fuel-card fuel-table-card">

                <div class="fuel-card-header">

                    <div>
                        <h2>Bus Fuel Efficiency Analysis</h2>
                        <p>Compare recorded mileage and fuel usage across shuttle units.</p>
                    </div>

                    <span class="analytics-badge diagnostic">
                        Diagnostic
                    </span>

                </div>

                <div class="table-wrap">

                    <table class="fuel-table">

                        <thead>
                            <tr>
                                <th>Bus</th>
                                <th>Distance Traveled</th>
                                <th>Fuel Used</th>
                                <th>Fuel Efficiency</th>
                                <th>Change</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            @foreach($fuelRecords as $record)

                                @php
                                    $statusClass = match($record['status']) {
                                        'Efficient' => 'efficient',
                                        'Normal' => 'normal',
                                        'High Consumption' => 'critical',
                                        default => 'review',
                                    };

                                    $changeClass = str_starts_with($record['change'], '-')
                                        ? 'negative'
                                        : 'positive';
                                @endphp

                                <tr>

                                    <td>
                                        <div class="bus-cell">

                                            <div class="bus-icon">
                                                <i class="fa-solid fa-bus"></i>
                                            </div>

                                            <strong>{{ $record['bus'] }}</strong>

                                        </div>
                                    </td>

                                    <td>{{ $record['distance'] }}</td>

                                    <td>{{ $record['fuel'] }}</td>

                                    <td>
                                        <strong class="efficiency-value">
                                            {{ $record['efficiency'] }}
                                        </strong>
                                    </td>

                                    <td>
                                        <span class="change-value {{ $changeClass }}">
                                            {{ $record['change'] }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="fuel-status {{ $statusClass }}">
                                            {{ $record['status'] }}
                                        </span>
                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

            </section>


            {{-- PRESCRIPTIVE INSIGHT --}}
            <section class="fuel-insight">

                <div class="insight-icon">
                    <i class="fa-solid fa-lightbulb"></i>
                </div>

                <div class="insight-content">

                    <span>Fuel Efficiency Insight</span>

                    <h2>
                        Review BUS-012, BUS-007, and BUS-015 before assuming excessive fuel use is caused by the vehicle alone.
                    </h2>

                    <p>
                        Compare their route distance, trip volume, accumulated mileage,
                        maintenance condition, and previous fuel reports. This helps separate
                        legitimate high fuel usage from possible efficiency issues.
                    </p>

                </div>

                <a
                    href="{{ route('analytics.recommendations') }}"
                    class="insight-link"
                >
                    View Recommendations
                    <i class="fa-solid fa-arrow-right"></i>
                </a>

            </section>

        </main>

    </div>

</x-layout.app>