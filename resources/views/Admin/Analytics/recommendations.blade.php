<x-layout.app
    title="FROMS - Analytics Recommendations"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/Analytics/recommendations.css',
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

        <main class="main recommendations-page">

            <x-layout.topbar
                title="Analytics Recommendations"
                subtitle="Review prioritized actions generated from fleet, fuel, maintenance, and inventory analytics"
                notification-count="6"
            />


            {{-- =====================================================
                ACTION CENTER HERO
            ====================================================== --}}
            <section class="decision-hero">

                <div class="decision-hero-copy">

                    <span class="decision-eyebrow">
                        <i class="fa-solid fa-lightbulb"></i>
                        Decision Support Center
                    </span>

                    <h2>
                        Eight recommendations are currently available for administrative review.
                    </h2>

                    <p>
                        FROMS combines operational findings, configured thresholds, and historical
                        patterns to highlight actions that may improve maintenance readiness,
                        inventory availability, fuel monitoring, and fleet utilization.
                    </p>

                    <div class="decision-priority-summary">

                        <div class="priority-summary high">
                            <span>High Priority</span>
                            <strong>3</strong>
                            <small>Immediate review</small>
                        </div>

                        <div class="priority-summary medium">
                            <span>Medium Priority</span>
                            <strong>3</strong>
                            <small>Follow-up actions</small>
                        </div>

                        <div class="priority-summary low">
                            <span>Monitoring</span>
                            <strong>2</strong>
                            <small>Continue observing</small>
                        </div>

                    </div>

                </div>


                <div class="decision-hero-side">

                    <div class="decision-score">

                        <span>Action Readiness</span>

                        <strong>8</strong>

                        <small>
                            Open recommendations
                        </small>

                    </div>

                    <div class="decision-source-list">

                        <div>
                            <span class="source-dot maintenance"></span>
                            <span>Bus Health</span>
                            <strong>3</strong>
                        </div>

                        <div>
                            <span class="source-dot inventory"></span>
                            <span>Inventory</span>
                            <strong>2</strong>
                        </div>

                        <div>
                            <span class="source-dot fuel"></span>
                            <span>Fuel</span>
                            <strong>2</strong>
                        </div>

                        <div>
                            <span class="source-dot fleet"></span>
                            <span>Fleet & Trip</span>
                            <strong>1</strong>
                        </div>

                    </div>

                </div>

            </section>


            {{-- =====================================================
                FILTER
            ====================================================== --}}
            <section class="recommendation-toolbar">

                <div>

                    <span class="section-kicker">
                        Decision Queue
                    </span>

                    <h2>
                        Recommended Actions
                    </h2>

                    <p>
                        Review recommendations by module and priority level.
                    </p>

                </div>

                <div class="recommendation-filters">

                    <select>
                        <option>All Modules</option>
                        <option>Fleet & Trip</option>
                        <option>Fuel</option>
                        <option>Bus Health</option>
                        <option>Inventory</option>
                    </select>

                    <select>
                        <option>All Priorities</option>
                        <option>High Priority</option>
                        <option>Medium Priority</option>
                        <option>Monitoring</option>
                    </select>

                </div>

            </section>


            {{-- =====================================================
                HIGH PRIORITY
            ====================================================== --}}
            <section class="priority-section high-priority-section">

                <div class="priority-section-heading">

                    <div class="priority-title-wrap">

                        <div class="priority-symbol high">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>

                        <div>
                            <span>Immediate Review</span>
                            <h2>High Priority</h2>
                            <p>
                                Items that may directly affect fleet readiness or maintenance operations.
                            </p>
                        </div>

                    </div>

                    <span class="priority-count high">
                        3 Actions
                    </span>

                </div>


                <div class="decision-list">

                    {{-- BUS-015 --}}
                    <article class="decision-item high">

                        <div class="decision-sequence">
                            01
                        </div>

                        <div class="decision-module-icon maintenance">
                            <i class="fa-solid fa-screwdriver-wrench"></i>
                        </div>

                        <div class="decision-content">

                            <div class="decision-meta">

                                <span class="module-tag maintenance">
                                    Bus Health
                                </span>

                                <span class="priority-tag high">
                                    High Priority
                                </span>

                            </div>

                            <h3>
                                Schedule maintenance review for BUS-015.
                            </h3>

                            <p>
                                BUS-015 has accumulated 50,240 km and already exceeded
                                its configured 50,000 km PMS threshold.
                            </p>

                            <div class="recommended-step">

                                <i class="fa-solid fa-arrow-right"></i>

                                <span>
                                    Review active job orders, mechanic availability, required parts,
                                    and fleet scheduling before assigning additional trips.
                                </span>

                            </div>

                        </div>

                        <div class="decision-action">

                            <span>Source</span>

                            <a href="{{ route('analytics.bus-health') }}">
                                View Bus Health
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>

                        </div>

                    </article>


                    {{-- INVENTORY --}}
                    <article class="decision-item high">

                        <div class="decision-sequence">
                            02
                        </div>

                        <div class="decision-module-icon inventory">
                            <i class="fa-solid fa-box-open"></i>
                        </div>

                        <div class="decision-content">

                            <div class="decision-meta">

                                <span class="module-tag inventory">
                                    Inventory
                                </span>

                                <span class="priority-tag high">
                                    High Priority
                                </span>

                            </div>

                            <h3>
                                Prioritize replenishment of critical maintenance parts.
                            </h3>

                            <p>
                                Six inventory items are currently below their configured
                                reorder levels. Brake Pad Set has only 4 units remaining.
                            </p>

                            <div class="recommended-step">

                                <i class="fa-solid fa-arrow-right"></i>

                                <span>
                                    Review pending restock and purchase requests to reduce
                                    the risk of maintenance delays.
                                </span>

                            </div>

                        </div>

                        <div class="decision-action">

                            <span>Source</span>

                            <a href="{{ route('analytics.inventory') }}">
                                View Inventory
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>

                        </div>

                    </article>


                    {{-- UPCOMING PMS --}}
                    <article class="decision-item high">

                        <div class="decision-sequence">
                            03
                        </div>

                        <div class="decision-module-icon maintenance">
                            <i class="fa-solid fa-gauge-high"></i>
                        </div>

                        <div class="decision-content">

                            <div class="decision-meta">

                                <span class="module-tag maintenance">
                                    Bus Health
                                </span>

                                <span class="priority-tag high">
                                    High Priority
                                </span>

                            </div>

                            <h3>
                                Prepare upcoming PMS capacity for BUS-012 and BUS-007.
                            </h3>

                            <p>
                                BUS-012 has 1,580 km remaining before threshold while BUS-007
                                has approximately 2,020 km remaining.
                            </p>

                            <div class="recommended-step">

                                <i class="fa-solid fa-arrow-right"></i>

                                <span>
                                    Coordinate preventive maintenance with current fleet availability
                                    to reduce operational disruption.
                                </span>

                            </div>

                        </div>

                        <div class="decision-action">

                            <span>Source</span>

                            <a href="{{ route('analytics.bus-health') }}">
                                View Bus Health
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>

                        </div>

                    </article>

                </div>

            </section>


            {{-- =====================================================
                MEDIUM PRIORITY
            ====================================================== --}}
            <section class="priority-section">

                <div class="priority-section-heading">

                    <div class="priority-title-wrap">

                        <div class="priority-symbol medium">
                            <i class="fa-solid fa-circle-exclamation"></i>
                        </div>

                        <div>
                            <span>Operational Follow-up</span>
                            <h2>Medium Priority</h2>
                            <p>
                                Recommended actions that may improve efficiency and resource distribution.
                            </p>
                        </div>

                    </div>

                    <span class="priority-count medium">
                        3 Actions
                    </span>

                </div>


                <div class="medium-decision-grid">

                    {{-- FUEL --}}
                    <article class="compact-decision-card medium">

                        <div class="compact-card-top">

                            <div class="compact-icon fuel">
                                <i class="fa-solid fa-gas-pump"></i>
                            </div>

                            <span class="priority-tag medium">
                                Medium
                            </span>

                        </div>

                        <span class="compact-module">
                            Fuel
                        </span>

                        <h3>
                            Review buses with below-average fuel efficiency.
                        </h3>

                        <p>
                            BUS-012, BUS-007, and BUS-015 are currently below the
                            6.8 km/L fleet average.
                        </p>

                        <div class="compact-action-note">
                            Compare route distance, trip activity, mileage, and maintenance condition.
                        </div>

                        <a href="{{ route('analytics.fuel') }}">
                            Review Fuel Analytics
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>

                    </article>


                    {{-- FLEET --}}
                    <article class="compact-decision-card medium">

                        <div class="compact-card-top">

                            <div class="compact-icon fleet">
                                <i class="fa-solid fa-bus"></i>
                            </div>

                            <span class="priority-tag medium">
                                Medium
                            </span>

                        </div>

                        <span class="compact-module">
                            Fleet & Trip
                        </span>

                        <h3>
                            Balance trip assignments across available buses.
                        </h3>

                        <p>
                            BUS-012 and BUS-007 currently show higher utilization
                            than several other operational units.
                        </p>

                        <div class="compact-action-note">
                            Consider assigning suitable trips to available underutilized buses.
                        </div>

                        <a href="{{ route('analytics.fleet-trip') }}">
                            Review Fleet Analytics
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>

                    </article>


                    {{-- INVENTORY --}}
                    <article class="compact-decision-card medium">

                        <div class="compact-card-top">

                            <div class="compact-icon inventory">
                                <i class="fa-solid fa-boxes-stacked"></i>
                            </div>

                            <span class="priority-tag medium">
                                Medium
                            </span>

                        </div>

                        <span class="compact-module">
                            Inventory
                        </span>

                        <h3>
                            Review reorder quantities for fast-moving parts.
                        </h3>

                        <p>
                            Oil Filter and Brake Pad Set are among the most frequently
                            issued maintenance items.
                        </p>

                        <div class="compact-action-note">
                            Historical usage may support adjustment of future reorder quantities.
                        </div>

                        <a href="{{ route('analytics.inventory') }}">
                            Review Inventory
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>

                    </article>

                </div>

            </section>


            {{-- =====================================================
                MONITORING
            ====================================================== --}}
            <section class="monitoring-section">

                <div class="monitoring-heading">

                    <div>
                        <span class="section-kicker">
                            Continuous Monitoring
                        </span>

                        <h2>
                            Watch List
                        </h2>

                        <p>
                            Findings that do not currently require immediate action.
                        </p>
                    </div>

                    <span class="priority-count low">
                        2 Items
                    </span>

                </div>


                <div class="monitoring-list">

                    <article class="monitoring-item">

                        <div class="monitoring-number">
                            01
                        </div>

                        <div class="monitoring-icon fuel">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>

                        <div class="monitoring-content">

                            <span>Fuel</span>

                            <strong>
                                Continue monitoring monthly fuel consumption.
                            </strong>

                            <p>
                                Recorded fuel usage increased by 5.4% compared with the
                                previous period. Continue comparing this with trip volume and distance.
                            </p>

                        </div>

                        <a href="{{ route('analytics.fuel') }}">
                            View Fuel
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>

                    </article>


                    <article class="monitoring-item">

                        <div class="monitoring-number">
                            02
                        </div>

                        <div class="monitoring-icon maintenance">
                            <i class="fa-solid fa-chart-column"></i>
                        </div>

                        <div class="monitoring-content">

                            <span>Bus Health</span>

                            <strong>
                                Continue tracking recurring brake-system maintenance.
                            </strong>

                            <p>
                                Brake-related work is currently the most frequently recorded
                                maintenance category in analyzed records.
                            </p>

                        </div>

                        <a href="{{ route('analytics.bus-health') }}">
                            View Bus Health
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>

                    </article>

                </div>

            </section>


            {{-- =====================================================
                DECISION NOTE
            ====================================================== --}}
            <section class="decision-note">

                <div class="decision-note-icon">
                    <i class="fa-solid fa-circle-info"></i>
                </div>

                <div>

                    <strong>
                        Recommendations are decision-support outputs, not automatic transactions.
                    </strong>

                    <p>
                        FROMS recommendations are based on available records, thresholds,
                        and observed operational patterns. Authorized personnel should review
                        findings before scheduling maintenance, changing trip assignments,
                        creating purchase actions, or adjusting inventory settings.
                    </p>

                </div>

            </section>

        </main>

    </div>

</x-layout.app>