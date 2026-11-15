<x-layout.app
    title="FROMS - Analytics Overview"
    :assets="[
        'resources/css/Admin/Analytics/overview/overview.css',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main analytics-overview-page">
            <x-layout.topbar
                title="Analytics Overview"
                subtitle="Executive summary of descriptive, diagnostic, predictive, and prescriptive analytics across FROMS"
                notification-count="6"
            />

            {{-- =====================================================
                EXECUTIVE SNAPSHOT
            ====================================================== --}}
            <section class="executive-snapshot">
                <div class="executive-copy">
                    <span class="executive-eyebrow">
                        <i class="fa-solid fa-chart-line"></i>
                        Analytical Module Overview
                    </span>

                    <h2>
                        Current records show stable fleet activity, with maintenance, inventory, and peak-period trip conditions requiring review.
                    </h2>

                    <p>
                        The overview summarizes measurable operational indicators from Fleet & Trip, Fuel,
                        Bus Health, and Inventory Analytics, then surfaces decision-support recommendations
                        without assigning an arbitrary overall readiness score.
                    </p>

                    <div class="snapshot-actions">
                        <a href="{{ route('analytics.recommendations') }}" class="snapshot-primary">
                            <i class="fa-solid fa-lightbulb"></i>
                            View Recommendations
                        </a>

                        <a href="{{ route('analytics.fleet-trip') }}" class="snapshot-secondary">
                            Explore Fleet Analytics
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <div class="executive-score">
                    <div class="score-ring">
                        <div class="score-inner">
                            <strong>8</strong>
                            <span>Open Recommendations</span>
                        </div>
                    </div>

                    <div class="score-meta">
                        <div>
                            <span class="score-dot red"></span>
                            <div>
                                <strong>3 High</strong>
                                <small>Priority actions</small>
                            </div>
                        </div>

                        <div>
                            <span class="score-dot yellow"></span>
                            <div>
                                <strong>3 Medium</strong>
                                <small>Operational adjustments</small>
                            </div>
                        </div>

                        <div>
                            <span class="score-dot green"></span>
                            <div>
                                <strong>2 Monitor</strong>
                                <small>Continue observing</small>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- =====================================================
                5.1 DESCRIPTIVE ANALYTICS
            ====================================================== --}}
            <section data-ajax-region="summary" class="stats-grid analytics-kpi-grid">
                <x-ui.summary-card
                    label="Distance Traveled"
                    value="26,126 km"
                    small="Recorded fleet trip distance"
                    icon="fa-road"
                    color="blue"
                />

                <x-ui.summary-card
                    label="Fuel Used"
                    value="3,842 L"
                    small="Recorded fleet fuel usage"
                    icon="fa-gas-pump"
                    color="yellow"
                />

                <x-ui.summary-card
                    label="PMS Attention"
                    value="2"
                    small="Priority buses nearing next PMS"
                    icon="fa-screwdriver-wrench"
                    color="red"
                />

                <x-ui.summary-card
                    label="Stock Threshold Alerts"
                    value="20"
                    small="Items at or below reorder threshold"
                    icon="fa-box-open"
                    color="red"
                />
            </section>

            {{-- =====================================================
                CROSS-MODULE ANALYTICS
            ====================================================== --}}
            <section class="executive-main-grid">
                <article class="overview-panel operational-health-panel">
                    <div class="panel-header">
                        <div>
                            <span class="panel-kicker">Current State</span>
                            <h2>Cross-Module Indicators</h2>
                            <p>Direct measures from each analytics domain without composite scoring.</p>
                        </div>

                        <span class="live-label">
                            <i class="fa-solid fa-circle"></i>
                            Current Records
                        </span>
                    </div>

                    <div class="health-matrix">
                        <a href="{{ route('analytics.fleet-trip') }}" class="health-module fleet">
                            <div class="health-module-top">
                                <div class="health-module-icon"><i class="fa-solid fa-bus"></i></div>
                                <span class="health-state good">Stable</span>
                            </div>

                            <div class="health-module-content">
                                <span>Fleet & Trip</span>
                                <strong>286 Trips</strong>
                                <p>26,126 km · 42.6 km/h avg.</p>
                            </div>

                            <small>12 trips currently require performance review</small>
                        </a>

                        <a href="{{ route('analytics.fuel') }}" class="health-module fuel">
                            <div class="health-module-top">
                                <div class="health-module-icon"><i class="fa-solid fa-gas-pump"></i></div>
                                <span class="health-state watch">Monitor</span>
                            </div>

                            <div class="health-module-content">
                                <span>Fuel</span>
                                <strong>6.8 km/L</strong>
                                <p>3,842 L recorded fuel use</p>
                            </div>

                            <small>3 buses require efficiency-context review</small>
                        </a>

                        <a href="{{ route('analytics.bus-health') }}" class="health-module maintenance">
                            <div class="health-module-top">
                                <div class="health-module-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                                <span class="health-state warning">Attention</span>
                            </div>

                            <div class="health-module-content">
                                <span>Bus Health</span>
                                <strong>1,580 km</strong>
                                <p>Nearest priority PMS runway</p>
                            </div>

                            <small>1 threshold reached · 2 priority buses approaching PMS</small>
                        </a>

                        <a href="{{ route('analytics.inventory') }}" class="health-module inventory">
                            <div class="health-module-top">
                                <div class="health-module-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                                <span class="health-state critical">Attention</span>
                            </div>

                            <div class="health-module-content">
                                <span>Inventory</span>
                                <strong>20 Items</strong>
                                <p>At or below reorder threshold</p>
                            </div>

                            <small>Forecasting uses stock-out history for early alerts</small>
                        </a>
                    </div>
                </article>

                <article class="overview-panel analytics-lens-panel">
                    <div class="panel-header compact">
                        <div>
                            <span class="panel-kicker">Objective 5.1–5.4</span>
                            <h2>Analytics Framework</h2>
                            <p>How each analytical layer contributes to operational decision support.</p>
                        </div>
                    </div>

                    <div class="analytics-lens-list">
                        <div class="lens-item descriptive">
                            <div class="lens-index">01</div>
                            <div class="lens-icon"><i class="fa-solid fa-chart-column"></i></div>
                            <div class="lens-content">
                                <span>Descriptive · 5.1</span>
                                <strong>What happened?</strong>
                                <p>Distance, fuel used, speed, idle time, trip duration, and stock status.</p>
                            </div>
                        </div>

                        <div class="lens-item diagnostic">
                            <div class="lens-index">02</div>
                            <div class="lens-icon"><i class="fa-solid fa-magnifying-glass-chart"></i></div>
                            <div class="lens-content">
                                <span>Diagnostic · 5.2</span>
                                <strong>Why might it be happening?</strong>
                                <p>Delay, route-deviation, congestion, fuel-wastage, and recurring maintenance patterns.</p>
                            </div>
                        </div>

                        <div class="lens-item predictive">
                            <div class="lens-index">03</div>
                            <div class="lens-icon"><i class="fa-solid fa-chart-line"></i></div>
                            <div class="lens-content">
                                <span>Predictive · 5.3</span>
                                <strong>What is likely next?</strong>
                                <p>ETA, delay risk, peak periods, fuel use, PMS timing, and stock runway forecasts.</p>
                            </div>
                        </div>

                        <div class="lens-item prescriptive">
                            <div class="lens-index">04</div>
                            <div class="lens-icon"><i class="fa-solid fa-lightbulb"></i></div>
                            <div class="lens-content">
                                <span>Prescriptive · 5.4</span>
                                <strong>What should be considered?</strong>
                                <p>Assignment, route, schedule, PMS, maintenance-alert, and restocking actions.</p>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            {{-- =====================================================
                DESCRIPTIVE + PREDICTIVE SNAPSHOT
            ====================================================== --}}
            <section class="overview-panel performance-panel">
                <div class="panel-header">
                    <div>
                        <span class="panel-kicker">Operational Snapshot</span>
                        <h2>Recorded Performance and Early Alerts</h2>
                        <p>Selected current indicators and forward-looking conditions from the aligned analytics pages.</p>
                    </div>

                    <span class="period-label">Current Month</span>
                </div>

                <div class="performance-layout">
                    <div class="performance-trend">
                        <div class="trend-heading">
                            <div>
                                <span>Trip Activity</span>
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

                    <div class="performance-metrics">
                        <div class="performance-metric">
                            <div class="metric-icon fleet"><i class="fa-solid fa-clock"></i></div>
                            <div>
                                <span>Avg. Trip Duration</span>
                                <strong>54 min</strong>
                            </div>
                            <small class="metric-change warning">12 Review</small>
                        </div>

                        <div class="performance-metric">
                            <div class="metric-icon fuel"><i class="fa-solid fa-chart-line"></i></div>
                            <div>
                                <span>Fuel Forecast</span>
                                <strong>Next-period outlook</strong>
                            </div>
                            <small class="metric-change warning">Predictive</small>
                        </div>

                        <div class="performance-metric">
                            <div class="metric-icon maintenance"><i class="fa-solid fa-gauge-high"></i></div>
                            <div>
                                <span>Next PMS Alert</span>
                                <strong>1,580 km</strong>
                            </div>
                            <small class="metric-change critical">Early Alert</small>
                        </div>

                        <div class="performance-metric">
                            <div class="metric-icon inventory"><i class="fa-solid fa-box-open"></i></div>
                            <div>
                                <span>Stock Runway</span>
                                <strong>Usage-based forecast</strong>
                            </div>
                            <small class="metric-change critical">Threshold</small>
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
                        <span class="panel-kicker">Diagnostic + Prescriptive</span>
                        <h2>Priority Findings</h2>
                        <p>Evidence-backed findings that lead to reviewable operational recommendations.</p>
                    </div>

                    <a href="{{ route('analytics.recommendations') }}" class="view-all-link">
                        View All Recommendations
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>

                <div class="priority-findings-grid">
                    <article class="priority-finding high">
                        <div class="finding-top">
                            <div class="finding-icon maintenance"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                            <span class="finding-priority high">High Priority</span>
                        </div>
                        <span class="finding-module">Bus Health</span>
                        <h3>BUS-015 exceeded its PMS mileage threshold.</h3>
                        <p>50,240 km recorded against a configured next PMS threshold of 50,000 km.</p>
                        <a href="{{ route('analytics.bus-health') }}">Review Bus Health <i class="fa-solid fa-arrow-right"></i></a>
                    </article>

                    <article class="priority-finding high">
                        <div class="finding-top">
                            <div class="finding-icon inventory"><i class="fa-solid fa-box-open"></i></div>
                            <span class="finding-priority high">High Priority</span>
                        </div>
                        <span class="finding-module">Inventory</span>
                        <h3>Critical parts require replenishment review.</h3>
                        <p>Brake Pad Set remains below reorder level and stock-out history supports early restocking review.</p>
                        <a href="{{ route('analytics.inventory') }}">Review Inventory <i class="fa-solid fa-arrow-right"></i></a>
                    </article>

                    <article class="priority-finding medium">
                        <div class="finding-top">
                            <div class="finding-icon fuel"><i class="fa-solid fa-gas-pump"></i></div>
                            <span class="finding-priority medium">Investigate</span>
                        </div>
                        <span class="finding-module">Fuel</span>
                        <h3>Three buses require fuel-efficiency context review.</h3>
                        <p>Compare km/L with distance, idling, trip activity, and maintenance condition before classifying wastage.</p>
                        <a href="{{ route('analytics.fuel') }}">Review Fuel Analytics <i class="fa-solid fa-arrow-right"></i></a>
                    </article>

                    <article class="priority-finding low">
                        <div class="finding-top">
                            <div class="finding-icon fleet"><i class="fa-solid fa-route"></i></div>
                            <span class="finding-priority low">Operational Review</span>
                        </div>
                        <span class="finding-module">Fleet & Trip</span>
                        <h3>Peak-period performance may justify route or schedule review.</h3>
                        <p>Recurring delay patterns, lower speeds, and longer travel times should inform—not automatically apply—route or schedule changes.</p>
                        <a href="{{ route('analytics.fleet-trip') }}">Review Fleet Analytics <i class="fa-solid fa-arrow-right"></i></a>
                    </article>
                </div>
            </section>

            <section class="overview-note">
                <div class="overview-note-icon"><i class="fa-solid fa-circle-info"></i></div>
                <div>
                    <strong>Analytics are based on available FROMS records and explainable rules.</strong>
                    <p>
                        Descriptive values summarize recorded data; diagnostic outputs identify patterns and possible contributing factors;
                        predictive outputs estimate future conditions from historical trends and thresholds; and prescriptive outputs remain
                        recommendations for authorized personnel review rather than automatic operational changes.
                    </p>
                </div>
            </section>
        </main>
    </div>
</x-layout.app>