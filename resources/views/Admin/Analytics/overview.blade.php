<x-layout.app
    title="FROMS - Analytics Overview"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/Analytics/overview.css',
        'resources/js/Main-js/sidebar.js'
    ]"
>
    <div class="app">

        <x-layout.sidebar department="Admin" />

        <main class="main analytics-overview-page">

            <x-layout.topbar
                title="Analytics Overview"
                subtitle="Executive view of fleet activity, fuel performance, maintenance health, inventory risk, and recommendations"
                notification-count="6"
            />

            {{-- =====================================================
                EXECUTIVE SNAPSHOT
            ====================================================== --}}
            <section class="executive-snapshot">

                <div class="executive-copy">

                    <span class="executive-eyebrow">
                        <i class="fa-solid fa-chart-line"></i>
                        Executive Snapshot
                    </span>

                    <h2>
                        Fleet operations remain stable, with maintenance and inventory requiring attention.
                    </h2>

                    <p>
                        Current FROMS records show strong fleet availability and trip activity,
                        while several buses are approaching PMS thresholds and critical maintenance
                        parts require replenishment.
                    </p>

                    <div class="snapshot-actions">

                        <a
                            href="{{ route('analytics.recommendations') }}"
                            class="snapshot-primary"
                        >
                            <i class="fa-solid fa-lightbulb"></i>
                            View Recommendations
                        </a>

                        <a
                            href="{{ route('analytics.fleet-trip') }}"
                            class="snapshot-secondary"
                        >
                            Explore Fleet Analytics
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>

                    </div>

                </div>

                <div class="executive-score">

                    <div class="score-ring">

                        <div class="score-inner">
                            <strong>82%</strong>
                            <span>Operational Readiness</span>
                        </div>

                    </div>

                    <div class="score-meta">

                        <div>
                            <span class="score-dot green"></span>
                            <div>
                                <strong>Stable</strong>
                                <small>Overall condition</small>
                            </div>
                        </div>

                        <div>
                            <span class="score-dot yellow"></span>
                            <div>
                                <strong>4 PMS</strong>
                                <small>Need attention</small>
                            </div>
                        </div>

                        <div>
                            <span class="score-dot red"></span>
                            <div>
                                <strong>6 Stock</strong>
                                <small>Critical items</small>
                            </div>
                        </div>

                    </div>

                </div>

            </section>


            {{-- =====================================================
                KPI STRIP
            ====================================================== --}}
            <section data-ajax-region="summary" class="stats-grid analytics-kpi-grid">

                <x-ui.summary-card
                    label="Active Buses"
                    value="18"
                    small="Out of 22 registered buses"
                    icon="fa-bus"
                    color="blue"
                />

                <x-ui.summary-card
                    label="Trips This Month"
                    value="286"
                    small="Completed scheduled trips"
                    icon="fa-route"
                    color="green"
                />

                <x-ui.summary-card
                    label="Average Fuel Efficiency"
                    value="6.8"
                    small="km/L fleet average"
                    icon="fa-gas-pump"
                    color="yellow"
                />

                <x-ui.summary-card
                    label="Open Recommendations"
                    value="8"
                    small="3 high-priority findings"
                    icon="fa-lightbulb"
                    color="red"
                />

            </section>


            {{-- =====================================================
                MAIN EXECUTIVE GRID
            ====================================================== --}}
            <section class="executive-main-grid">

                {{-- OPERATIONAL HEALTH --}}
                <article class="overview-panel operational-health-panel">

                    <div class="panel-header">

                        <div>
                            <span class="panel-kicker">Current State</span>
                            <h2>Operational Health</h2>
                            <p>
                                One consolidated view of major FROMS operational areas.
                            </p>
                        </div>

                        <span class="live-label">
                            <i class="fa-solid fa-circle"></i>
                            Current Records
                        </span>

                    </div>


                    <div class="health-matrix">

                        {{-- FLEET --}}
                        <a
                            href="{{ route('analytics.fleet-trip') }}"
                            class="health-module fleet"
                        >

                            <div class="health-module-top">

                                <div class="health-module-icon">
                                    <i class="fa-solid fa-bus"></i>
                                </div>

                                <span class="health-state good">
                                    Stable
                                </span>

                            </div>

                            <div class="health-module-content">

                                <span>Fleet & Trip</span>

                                <strong>81.8%</strong>

                                <p>
                                    Fleet availability
                                </p>

                            </div>

                            <div class="health-progress">
                                <span style="width: 81.8%;"></span>
                            </div>

                            <small>
                                18 operational · 286 trips
                            </small>

                        </a>


                        {{-- FUEL --}}
                        <a
                            href="{{ route('analytics.fuel') }}"
                            class="health-module fuel"
                        >

                            <div class="health-module-top">

                                <div class="health-module-icon">
                                    <i class="fa-solid fa-gas-pump"></i>
                                </div>

                                <span class="health-state watch">
                                    Monitor
                                </span>

                            </div>

                            <div class="health-module-content">

                                <span>Fuel</span>

                                <strong>6.8 km/L</strong>

                                <p>
                                    Average efficiency
                                </p>

                            </div>

                            <div class="health-progress">
                                <span style="width: 68%;"></span>
                            </div>

                            <small>
                                3 buses below average
                            </small>

                        </a>


                        {{-- BUS HEALTH --}}
                        <a
                            href="{{ route('analytics.bus-health') }}"
                            class="health-module maintenance"
                        >

                            <div class="health-module-top">

                                <div class="health-module-icon">
                                    <i class="fa-solid fa-heart-pulse"></i>
                                </div>

                                <span class="health-state warning">
                                    Attention
                                </span>

                            </div>

                            <div class="health-module-content">

                                <span>Bus Health</span>

                                <strong>4</strong>

                                <p>
                                    PMS attention
                                </p>

                            </div>

                            <div class="health-progress">
                                <span style="width: 62%;"></span>
                            </div>

                            <small>
                                1 threshold already reached
                            </small>

                        </a>


                        {{-- INVENTORY --}}
                        <a
                            href="{{ route('analytics.inventory') }}"
                            class="health-module inventory"
                        >

                            <div class="health-module-top">

                                <div class="health-module-icon">
                                    <i class="fa-solid fa-boxes-stacked"></i>
                                </div>

                                <span class="health-state critical">
                                    Critical
                                </span>

                            </div>

                            <div class="health-module-content">

                                <span>Inventory</span>

                                <strong>6</strong>

                                <p>
                                    Critical stock items
                                </p>

                            </div>

                            <div class="health-progress">
                                <span style="width: 38%;"></span>
                            </div>

                            <small>
                                14 additional low-stock items
                            </small>

                        </a>

                    </div>

                </article>


                {{-- ANALYTICS LENS --}}
                <article class="overview-panel analytics-lens-panel">

                    <div class="panel-header compact">

                        <div>
                            <span class="panel-kicker">Analytics Framework</span>
                            <h2>Analytics Lens</h2>
                            <p>
                                How FROMS turns records into decisions.
                            </p>
                        </div>

                    </div>


                    <div class="analytics-lens-list">

                        <div class="lens-item descriptive">

                            <div class="lens-index">
                                01
                            </div>

                            <div class="lens-icon">
                                <i class="fa-solid fa-chart-column"></i>
                            </div>

                            <div class="lens-content">
                                <span>Descriptive</span>
                                <strong>What is happening?</strong>
                                <p>Summarizes current operational performance.</p>
                            </div>

                        </div>


                        <div class="lens-item diagnostic">

                            <div class="lens-index">
                                02
                            </div>

                            <div class="lens-icon">
                                <i class="fa-solid fa-magnifying-glass-chart"></i>
                            </div>

                            <div class="lens-content">
                                <span>Diagnostic</span>
                                <strong>Why is it happening?</strong>
                                <p>Identifies patterns and possible contributing factors.</p>
                            </div>

                        </div>


                        <div class="lens-item predictive">

                            <div class="lens-index">
                                03
                            </div>

                            <div class="lens-icon">
                                <i class="fa-solid fa-chart-line"></i>
                            </div>

                            <div class="lens-content">
                                <span>Predictive</span>
                                <strong>What may require attention?</strong>
                                <p>Uses thresholds and historical trends to flag future needs.</p>
                            </div>

                        </div>


                        <div class="lens-item prescriptive">

                            <div class="lens-index">
                                04
                            </div>

                            <div class="lens-icon">
                                <i class="fa-solid fa-lightbulb"></i>
                            </div>

                            <div class="lens-content">
                                <span>Prescriptive</span>
                                <strong>What should be considered?</strong>
                                <p>Suggests actions for authorized personnel to review.</p>
                            </div>

                        </div>

                    </div>

                </article>

            </section>


            {{-- =====================================================
                PERFORMANCE SNAPSHOT
            ====================================================== --}}
            <section class="overview-panel performance-panel">

                <div class="panel-header">

                    <div>
                        <span class="panel-kicker">Cross-Module Comparison</span>
                        <h2>Performance Snapshot</h2>
                        <p>
                            Key indicators from fleet, fuel, maintenance, and inventory records.
                        </p>
                    </div>

                    <span class="period-label">
                        Current Month
                    </span>

                </div>


                <div class="performance-layout">

                    {{-- LEFT: TREND --}}
                    <div class="performance-trend">

                        <div class="trend-heading">

                            <div>
                                <span>Operational Activity</span>
                                <strong>286 completed trips</strong>
                            </div>

                            <span class="trend-change positive">
                                <i class="fa-solid fa-arrow-trend-up"></i>
                                +8.2%
                            </span>

                        </div>


                        <div class="mini-chart">

                            <div class="chart-grid line-1"></div>
                            <div class="chart-grid line-2"></div>
                            <div class="chart-grid line-3"></div>

                            <div class="chart-bar-group">
                                <div class="mini-bar" style="height: 52%;"></div>
                                <span>W1</span>
                            </div>

                            <div class="chart-bar-group">
                                <div class="mini-bar" style="height: 67%;"></div>
                                <span>W2</span>
                            </div>

                            <div class="chart-bar-group">
                                <div class="mini-bar" style="height: 82%;"></div>
                                <span>W3</span>
                            </div>

                            <div class="chart-bar-group">
                                <div class="mini-bar" style="height: 72%;"></div>
                                <span>W4</span>
                            </div>

                        </div>

                    </div>


                    {{-- RIGHT: QUICK METRICS --}}
                    <div class="performance-metrics">

                        <div class="performance-metric">

                            <div class="metric-icon fuel">
                                <i class="fa-solid fa-gas-pump"></i>
                            </div>

                            <div>
                                <span>Fuel Used</span>
                                <strong>3,842 L</strong>
                            </div>

                            <small class="metric-change warning">
                                +5.4%
                            </small>

                        </div>


                        <div class="performance-metric">

                            <div class="metric-icon maintenance">
                                <i class="fa-solid fa-screwdriver-wrench"></i>
                            </div>

                            <div>
                                <span>PMS Attention</span>
                                <strong>4 Buses</strong>
                            </div>

                            <small class="metric-change warning">
                                Review
                            </small>

                        </div>


                        <div class="performance-metric">

                            <div class="metric-icon inventory">
                                <i class="fa-solid fa-box-open"></i>
                            </div>

                            <div>
                                <span>Critical Stock</span>
                                <strong>6 Items</strong>
                            </div>

                            <small class="metric-change critical">
                                Action
                            </small>

                        </div>


                        <div class="performance-metric">

                            <div class="metric-icon fleet">
                                <i class="fa-solid fa-bus"></i>
                            </div>

                            <div>
                                <span>Fleet Availability</span>
                                <strong>81.8%</strong>
                            </div>

                            <small class="metric-change good">
                                Stable
                            </small>

                        </div>

                    </div>

                </div>

            </section>


            {{-- =====================================================
                PRIORITY FINDINGS
            ====================================================== --}}
            <section class="priority-findings-section">

                <div class="findings-heading">

                    <div>
                        <span class="panel-kicker">Decision Support</span>
                        <h2>Priority Findings</h2>
                        <p>
                            Current issues that may require administrative or department review.
                        </p>
                    </div>

                    <a
                        href="{{ route('analytics.recommendations') }}"
                        class="view-all-link"
                    >
                        View All Recommendations
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>

                </div>


                <div class="priority-findings-grid">

                    {{-- MAINTENANCE --}}
                    <article class="priority-finding high">

                        <div class="finding-top">

                            <div class="finding-icon maintenance">
                                <i class="fa-solid fa-screwdriver-wrench"></i>
                            </div>

                            <span class="finding-priority high">
                                High Priority
                            </span>

                        </div>

                        <span class="finding-module">
                            Bus Health
                        </span>

                        <h3>
                            BUS-015 exceeded its PMS mileage threshold.
                        </h3>

                        <p>
                            The unit has reached 50,240 km against its configured
                            50,000 km PMS threshold.
                        </p>

                        <a href="{{ route('analytics.bus-health') }}">
                            Review Bus Health
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>

                    </article>


                    {{-- INVENTORY --}}
                    <article class="priority-finding high">

                        <div class="finding-top">

                            <div class="finding-icon inventory">
                                <i class="fa-solid fa-box-open"></i>
                            </div>

                            <span class="finding-priority high">
                                High Priority
                            </span>

                        </div>

                        <span class="finding-module">
                            Inventory
                        </span>

                        <h3>
                            Six maintenance items are below reorder level.
                        </h3>

                        <p>
                            Brake Pad Set is currently among the most critical,
                            with only 4 units remaining.
                        </p>

                        <a href="{{ route('analytics.inventory') }}">
                            Review Inventory
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>

                    </article>


                    {{-- FUEL --}}
                    <article class="priority-finding medium">

                        <div class="finding-top">

                            <div class="finding-icon fuel">
                                <i class="fa-solid fa-gas-pump"></i>
                            </div>

                            <span class="finding-priority medium">
                                Monitor
                            </span>

                        </div>

                        <span class="finding-module">
                            Fuel
                        </span>

                        <h3>
                            Three buses are below fleet-average fuel efficiency.
                        </h3>

                        <p>
                            Compare fuel usage with distance, route activity,
                            mileage, and maintenance condition.
                        </p>

                        <a href="{{ route('analytics.fuel') }}">
                            Review Fuel Analytics
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>

                    </article>


                    {{-- FLEET --}}
                    <article class="priority-finding low">

                        <div class="finding-top">

                            <div class="finding-icon fleet">
                                <i class="fa-solid fa-route"></i>
                            </div>

                            <span class="finding-priority low">
                                Opportunity
                            </span>

                        </div>

                        <span class="finding-module">
                            Fleet & Trip
                        </span>

                        <h3>
                            Trip assignments may be distributed more evenly.
                        </h3>

                        <p>
                            BUS-012 and BUS-007 currently show higher utilization
                            than several available shuttle units.
                        </p>

                        <a href="{{ route('analytics.fleet-trip') }}">
                            Review Fleet Analytics
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>

                    </article>

                </div>

            </section>


            {{-- =====================================================
                DATA NOTE
            ====================================================== --}}
            <section class="overview-note">

                <div class="overview-note-icon">
                    <i class="fa-solid fa-circle-info"></i>
                </div>

                <div>
                    <strong>
                        Analytics are based on available FROMS records.
                    </strong>

                    <p>
                        Indicators use recorded trip, fuel, maintenance, mileage,
                        inventory, and processed historical data. Recommendations
                        support decision-making but do not replace authorized personnel review.
                    </p>
                </div>

            </section>

        </main>

    </div>

</x-layout.app>