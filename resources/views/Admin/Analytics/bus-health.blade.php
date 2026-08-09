<x-layout.app
    title="FROMS - Bus Health Analytics"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/Analytics/bus-health.css',
        'resources/js/Main-js/sidebar.js'
    ]"
>
    <div class="app">

        <x-layout.sidebar department="Admin" />

        <main class="main bus-health-page">

            <x-layout.topbar
                title="Bus Health Analytics"
                subtitle="Monitor PMS mileage, maintenance pressure, recurring issues, and shuttle health priorities"
                notification-count="6"
            />


            {{-- =====================================================
                MAINTENANCE READINESS HERO
            ====================================================== --}}
            <section class="health-hero">

                <div class="health-hero-copy">

                    <span class="health-hero-eyebrow">
                        <i class="fa-solid fa-heart-pulse"></i>
                        Maintenance Readiness
                    </span>

                    <h2>
                        One bus has crossed its PMS threshold while several units are approaching service range.
                    </h2>

                    <p>
                        Mileage-based monitoring identifies BUS-015 as the immediate priority.
                        BUS-012 and BUS-007 should be considered when planning upcoming preventive
                        maintenance activities.
                    </p>

                    <div class="hero-alert-row">

                        <div class="hero-alert critical">
                            <i class="fa-solid fa-triangle-exclamation"></i>

                            <div>
                                <span>Immediate Review</span>
                                <strong>BUS-015</strong>
                            </div>
                        </div>

                        <div class="hero-alert warning">
                            <i class="fa-solid fa-screwdriver-wrench"></i>

                            <div>
                                <span>PMS Approaching</span>
                                <strong>4 Buses</strong>
                            </div>
                        </div>

                        <div class="hero-alert good">
                            <i class="fa-solid fa-circle-check"></i>

                            <div>
                                <span>Operational</span>
                                <strong>18 Buses</strong>
                            </div>
                        </div>

                    </div>

                </div>


                <div class="health-hero-status">

                    <div class="readiness-gauge">

                        <div class="readiness-inner">
                            <strong>82%</strong>
                            <span>Fleet Maintenance Readiness</span>
                        </div>

                    </div>

                    <div class="readiness-caption">

                        <span class="readiness-state">
                            <i class="fa-solid fa-circle"></i>
                            Attention Required
                        </span>

                        <p>
                            Based on PMS thresholds and current maintenance status.
                        </p>

                    </div>

                </div>

            </section>


            {{-- =====================================================
                HEALTH KPIs
            ====================================================== --}}
            <section data-ajax-region="summary" class="stats-grid health-summary-grid">

                <x-ui.summary-card
                    label="Operational Buses"
                    value="18"
                    small="Currently available for operation"
                    icon="fa-bus"
                    color="green"
                />

                <x-ui.summary-card
                    label="PMS Attention"
                    value="4"
                    small="Buses nearing service threshold"
                    icon="fa-screwdriver-wrench"
                    color="yellow"
                />

                <x-ui.summary-card
                    label="Threshold Reached"
                    value="1"
                    small="Requires immediate review"
                    icon="fa-triangle-exclamation"
                    color="red"
                />

                <x-ui.summary-card
                    label="Under Maintenance"
                    value="3"
                    small="Currently unavailable units"
                    icon="fa-wrench"
                    color="blue"
                />

            </section>


            {{-- =====================================================
                FILTER
            ====================================================== --}}
            <section class="health-filter-bar">

                <div>
                    <span class="section-kicker">
                        Maintenance Monitoring
                    </span>

                    <h2>Fleet Health Monitor</h2>

                    <p>
                        Evaluate shuttle condition using accumulated mileage and maintenance records.
                    </p>
                </div>

                <div class="health-filters">

                    <select>
                        <option>All Health Status</option>
                        <option>Healthy</option>
                        <option>PMS Soon</option>
                        <option>Threshold Reached</option>
                        <option>Under Maintenance</option>
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
                PMS MILEAGE RUNWAY
            ====================================================== --}}
            @php
                $pmsBuses = [
                    [
                        'bus' => 'BUS-015',
                        'mileage' => 50240,
                        'threshold' => 50000,
                        'remaining' => '240 km over',
                        'progress' => 100,
                        'status' => 'Threshold Reached',
                        'class' => 'critical',
                    ],
                    [
                        'bus' => 'BUS-012',
                        'mileage' => 48420,
                        'threshold' => 50000,
                        'remaining' => '1,580 km left',
                        'progress' => 96.84,
                        'status' => 'PMS Soon',
                        'class' => 'warning',
                    ],
                    [
                        'bus' => 'BUS-007',
                        'mileage' => 47980,
                        'threshold' => 50000,
                        'remaining' => '2,020 km left',
                        'progress' => 95.96,
                        'status' => 'PMS Soon',
                        'class' => 'warning',
                    ],
                    [
                        'bus' => 'BUS-018',
                        'mileage' => 44510,
                        'threshold' => 50000,
                        'remaining' => '5,490 km left',
                        'progress' => 89.02,
                        'status' => 'Healthy',
                        'class' => 'healthy',
                    ],
                    [
                        'bus' => 'BUS-003',
                        'mileage' => 41280,
                        'threshold' => 50000,
                        'remaining' => '8,720 km left',
                        'progress' => 82.56,
                        'status' => 'Healthy',
                        'class' => 'healthy',
                    ],
                ];
            @endphp

            <section class="pms-runway-panel">

                <div class="panel-heading">

                    <div>
                        <span class="section-kicker">
                            Predictive Analytics
                        </span>

                        <h2>PMS Mileage Runway</h2>

                        <p>
                            See how close priority shuttle units are to their configured
                            50,000 km preventive maintenance threshold.
                        </p>
                    </div>

                    <div class="threshold-key">
                        <i class="fa-solid fa-flag-checkered"></i>
                        PMS Threshold: 50,000 km
                    </div>

                </div>


                <div class="pms-runway-list">

                    @foreach($pmsBuses as $bus)

                        <div class="runway-row {{ $bus['class'] }}">

                            <div class="runway-bus">

                                <div class="runway-bus-icon">
                                    <i class="fa-solid fa-bus"></i>
                                </div>

                                <div>
                                    <strong>{{ $bus['bus'] }}</strong>
                                    <span>{{ number_format($bus['mileage']) }} km</span>
                                </div>

                            </div>


                            <div class="runway-track-wrap">

                                <div class="runway-labels">
                                    <span>Current Mileage</span>
                                    <strong>
                                        {{ number_format($bus['threshold']) }} km
                                    </strong>
                                </div>

                                <div class="runway-track">

                                    <span
                                        class="runway-fill {{ $bus['class'] }}"
                                        style="width: {{ $bus['progress'] }}%;"
                                    ></span>

                                    <span class="threshold-marker"></span>

                                </div>

                            </div>


                            <div class="runway-remaining">

                                <strong>
                                    {{ $bus['remaining'] }}
                                </strong>

                                <span class="runway-status {{ $bus['class'] }}">
                                    {{ $bus['status'] }}
                                </span>

                            </div>

                        </div>

                    @endforeach

                </div>

            </section>


            {{-- =====================================================
                MAINTENANCE INTELLIGENCE
            ====================================================== --}}
            <section class="maintenance-intelligence-grid">

                {{-- ATTENTION QUEUE --}}
                <article class="maintenance-panel attention-panel">

                    <div class="panel-heading compact">

                        <div>
                            <span class="section-kicker">
                                Priority Queue
                            </span>

                            <h2>Needs Attention</h2>

                            <p>
                                Buses that should be considered first during maintenance planning.
                            </p>
                        </div>

                    </div>


                    <div class="attention-timeline">

                        <div class="attention-item critical">

                            <div class="attention-marker">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>

                            <div class="attention-content">

                                <div class="attention-meta">
                                    <span>Immediate</span>
                                    <strong>BUS-015</strong>
                                </div>

                                <h3>PMS threshold exceeded</h3>

                                <p>
                                    Current mileage is 50,240 km. Review maintenance status
                                    before assigning additional trips.
                                </p>

                            </div>

                        </div>


                        <div class="attention-item warning">

                            <div class="attention-marker">
                                <i class="fa-solid fa-screwdriver-wrench"></i>
                            </div>

                            <div class="attention-content">

                                <div class="attention-meta">
                                    <span>Upcoming</span>
                                    <strong>BUS-012</strong>
                                </div>

                                <h3>1,580 km before PMS threshold</h3>

                                <p>
                                    Coordinate upcoming service with fleet and mechanic availability.
                                </p>

                            </div>

                        </div>


                        <div class="attention-item warning">

                            <div class="attention-marker">
                                <i class="fa-solid fa-screwdriver-wrench"></i>
                            </div>

                            <div class="attention-content">

                                <div class="attention-meta">
                                    <span>Upcoming</span>
                                    <strong>BUS-007</strong>
                                </div>

                                <h3>2,020 km before PMS threshold</h3>

                                <p>
                                    Continue monitoring accumulated mileage and trip assignments.
                                </p>

                            </div>

                        </div>

                    </div>

                </article>


                {{-- MAINTENANCE PATTERN --}}
                <article class="maintenance-panel pattern-panel">

                    <div class="panel-heading compact">

                        <div>
                            <span class="section-kicker">
                                Diagnostic Analytics
                            </span>

                            <h2>Recurring Maintenance Issues</h2>

                            <p>
                                Most frequently recorded categories in maintenance history.
                            </p>
                        </div>

                    </div>


                    <div class="pattern-ranking">

                        <div class="pattern-row">

                            <div class="pattern-rank">01</div>

                            <div class="pattern-content">

                                <div class="pattern-title">
                                    <strong>Brake System</strong>
                                    <span>8 cases · 31%</span>
                                </div>

                                <div class="pattern-bar">
                                    <span style="width: 100%;"></span>
                                </div>

                            </div>

                        </div>


                        <div class="pattern-row">

                            <div class="pattern-rank">02</div>

                            <div class="pattern-content">

                                <div class="pattern-title">
                                    <strong>Engine / Oil Service</strong>
                                    <span>6 cases · 23%</span>
                                </div>

                                <div class="pattern-bar">
                                    <span style="width: 74%;"></span>
                                </div>

                            </div>

                        </div>


                        <div class="pattern-row">

                            <div class="pattern-rank">03</div>

                            <div class="pattern-content">

                                <div class="pattern-title">
                                    <strong>Cooling System</strong>
                                    <span>5 cases · 19%</span>
                                </div>

                                <div class="pattern-bar">
                                    <span style="width: 61%;"></span>
                                </div>

                            </div>

                        </div>


                        <div class="pattern-row">

                            <div class="pattern-rank">04</div>

                            <div class="pattern-content">

                                <div class="pattern-title">
                                    <strong>Electrical</strong>
                                    <span>4 cases · 15%</span>
                                </div>

                                <div class="pattern-bar">
                                    <span style="width: 48%;"></span>
                                </div>

                            </div>

                        </div>

                    </div>


                    <div class="pattern-insight">

                        <div>
                            <i class="fa-solid fa-chart-line"></i>
                        </div>

                        <p>
                            Brake-system work currently appears most often in recorded
                            maintenance activities and should continue to be monitored.
                        </p>

                    </div>

                </article>

            </section>


            {{-- =====================================================
                FLEET HEALTH BOARD
            ====================================================== --}}
            @php
                $healthCards = [
                    [
                        'bus' => 'BUS-003',
                        'mileage' => '41,280 km',
                        'lastService' => 'Jun 28, 2026',
                        'records' => 2,
                        'status' => 'Healthy',
                        'class' => 'healthy',
                        'score' => 88,
                    ],
                    [
                        'bus' => 'BUS-018',
                        'mileage' => '44,510 km',
                        'lastService' => 'Jun 18, 2026',
                        'records' => 3,
                        'status' => 'Healthy',
                        'class' => 'healthy',
                        'score' => 81,
                    ],
                    [
                        'bus' => 'BUS-007',
                        'mileage' => '47,980 km',
                        'lastService' => 'May 30, 2026',
                        'records' => 4,
                        'status' => 'PMS Soon',
                        'class' => 'warning',
                        'score' => 67,
                    ],
                    [
                        'bus' => 'BUS-012',
                        'mileage' => '48,420 km',
                        'lastService' => 'May 22, 2026',
                        'records' => 5,
                        'status' => 'PMS Soon',
                        'class' => 'warning',
                        'score' => 61,
                    ],
                    [
                        'bus' => 'BUS-015',
                        'mileage' => '50,240 km',
                        'lastService' => 'Apr 19, 2026',
                        'records' => 6,
                        'status' => 'Threshold Reached',
                        'class' => 'critical',
                        'score' => 39,
                    ],
                ];
            @endphp

            <section class="fleet-health-board">

                <div class="board-heading">

                    <div>
                        <span class="section-kicker">
                            Fleet Comparison
                        </span>

                        <h2>Bus Health Board</h2>

                        <p>
                            Quick comparison of mileage, service history, and current health status.
                        </p>
                    </div>

                    <span class="board-count">
                        5 Priority Units
                    </span>

                </div>


                <div class="health-card-strip">

                    @foreach($healthCards as $record)

                        <article class="bus-health-card {{ $record['class'] }}">

                            <div class="bus-health-card-top">

                                <div class="health-bus-identity">

                                    <div class="health-bus-icon">
                                        <i class="fa-solid fa-bus"></i>
                                    </div>

                                    <div>
                                        <strong>{{ $record['bus'] }}</strong>
                                        <span>{{ $record['mileage'] }}</span>
                                    </div>

                                </div>

                                <span class="health-status {{ $record['class'] }}">
                                    {{ $record['status'] }}
                                </span>

                            </div>


                            <div class="health-score-block">

                                <div class="health-score-number">
                                    <strong>{{ $record['score'] }}</strong>
                                    <span>Health Index</span>
                                </div>

                                <div class="health-score-track">
                                    <span
                                        class="{{ $record['class'] }}"
                                        style="width: {{ $record['score'] }}%;"
                                    ></span>
                                </div>

                            </div>


                            <div class="health-card-details">

                                <div>
                                    <span>Last Service</span>
                                    <strong>{{ $record['lastService'] }}</strong>
                                </div>

                                <div>
                                    <span>Maintenance Records</span>
                                    <strong>{{ $record['records'] }}</strong>
                                </div>

                            </div>

                        </article>

                    @endforeach

                </div>

            </section>


            {{-- =====================================================
                RECOMMENDATION
            ====================================================== --}}
            <section class="maintenance-recommendation">

                <div class="recommendation-symbol">
                    <i class="fa-solid fa-lightbulb"></i>
                </div>

                <div class="recommendation-copy">

                    <span>
                        Maintenance Recommendation
                    </span>

                    <h2>
                        Prioritize BUS-015, then prepare PMS capacity for BUS-012 and BUS-007.
                    </h2>

                    <p>
                        Maintenance planning should consider active job orders, available mechanics,
                        required inventory parts, and fleet availability before assigning service dates.
                    </p>

                </div>

                <a
                    href="{{ route('analytics.recommendations') }}"
                    class="recommendation-button"
                >
                    View Recommendations
                    <i class="fa-solid fa-arrow-right"></i>
                </a>

            </section>

        </main>

    </div>

</x-layout.app>