<x-layout.app
    title="FROMS - Bus Health Analytics"
    :assets="[
        'resources/css/Admin/Analytics/bus-health.css',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main bus-health-page">
            <x-layout.topbar
                title="Bus Health Analytics"
                subtitle="Monitor PMS thresholds, maintenance patterns, service forecasts, and maintenance alerts"
                notification-count="6"
            />

            {{-- =====================================================
                MAINTENANCE READINESS HERO
            ====================================================== --}}
            <section class="health-hero">
                <div class="health-hero-copy">
                    <span class="health-hero-eyebrow">
                        <i class="fa-solid fa-screwdriver-wrench"></i>
                        Maintenance Decision Support
                    </span>

                    <h2>
                        PMS mileage and maintenance history identify which shuttle units require attention first.
                    </h2>

                    <p>
                        Bus Health Analytics uses transparent maintenance indicators rather than an arbitrary health score.
                        Priority should be based on current GPS mileage, configured PMS thresholds, active maintenance work,
                        and recurring recorded issues.
                    </p>

                    <div class="hero-alert-row">
                        <div class="hero-alert critical">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <div>
                                <span>Threshold Reached</span>
                                <strong>1 Bus</strong>
                            </div>
                        </div>

                        <div class="hero-alert warning">
                            <i class="fa-solid fa-gauge-high"></i>
                            <div>
                                <span>PMS Approaching</span>
                                <strong>2 Priority Buses</strong>
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
                            <strong>1,580</strong>
                            <span>km to next priority PMS</span>
                        </div>
                    </div>

                    <div class="readiness-caption">
                        <span class="readiness-state">
                            <i class="fa-solid fa-circle"></i>
                            Early Maintenance Alert
                        </span>
                        <p>
                            BUS-012 is the nearest unit below its configured PMS threshold in this prototype view.
                        </p>
                    </div>
                </div>
            </section>

            {{-- =====================================================
                DESCRIPTIVE MAINTENANCE KPIs
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
                    value="2"
                    small="Priority units nearing next PMS mileage"
                    icon="fa-screwdriver-wrench"
                    color="yellow"
                />

                <x-ui.summary-card
                    label="Threshold Reached"
                    value="1"
                    small="Mileage already at or above next PMS"
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
                    <span class="section-kicker">Maintenance Monitoring</span>
                    <h2>Fleet Maintenance Monitor</h2>
                    <p>Review mileage thresholds, maintenance workload, and recurring issue patterns.</p>
                </div>

                <div class="health-filters">
                    <select aria-label="Maintenance status">
                        <option>All Maintenance Status</option>
                        <option>Within PMS Range</option>
                        <option>PMS Soon</option>
                        <option>Threshold Reached</option>
                        <option>Under Maintenance</option>
                    </select>

                    <select aria-label="Shuttle bus">
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
                5.3 PREDICTIVE ANALYTICS - PMS MILEAGE RUNWAY
            ====================================================== --}}
            @php
                $pmsBuses = [
                    [
                        'bus' => 'BUS-015',
                        'mileage' => 50240,
                        'threshold' => 50000,
                        'remaining' => '240 km over',
                        'progress' => 100,
                        'forecast' => 'Threshold already reached',
                        'status' => 'Threshold Reached',
                        'class' => 'critical',
                    ],
                    [
                        'bus' => 'BUS-012',
                        'mileage' => 48420,
                        'threshold' => 50000,
                        'remaining' => '1,580 km left',
                        'progress' => 96.84,
                        'forecast' => 'Approx. 9 operating days at recent mileage rate',
                        'status' => 'PMS Soon',
                        'class' => 'warning',
                    ],
                    [
                        'bus' => 'BUS-007',
                        'mileage' => 47980,
                        'threshold' => 50000,
                        'remaining' => '2,020 km left',
                        'progress' => 95.96,
                        'forecast' => 'Approx. 12 operating days at recent mileage rate',
                        'status' => 'PMS Soon',
                        'class' => 'warning',
                    ],
                    [
                        'bus' => 'BUS-018',
                        'mileage' => 44510,
                        'threshold' => 50000,
                        'remaining' => '5,490 km left',
                        'progress' => 89.02,
                        'forecast' => 'No early PMS alert in current window',
                        'status' => 'Within Range',
                        'class' => 'healthy',
                    ],
                    [
                        'bus' => 'BUS-003',
                        'mileage' => 41280,
                        'threshold' => 50000,
                        'remaining' => '8,720 km left',
                        'progress' => 82.56,
                        'forecast' => 'No early PMS alert in current window',
                        'status' => 'Within Range',
                        'class' => 'healthy',
                    ],
                ];
            @endphp

            <section class="pms-runway-panel">
                <div class="panel-heading">
                    <div>
                        <span class="section-kicker">Predictive Analytics</span>
                        <h2>PMS Threshold Forecast</h2>
                        <p>
                            Forecast when a unit may reach its next configured PMS mileage using current GPS mileage
                            and its recent mileage accumulation rate.
                        </p>
                    </div>

                    <div class="threshold-key">
                        <i class="fa-solid fa-flag-checkered"></i>
                        Example Threshold: 50,000 km
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
                                    <span>{{ $bus['forecast'] }}</span>
                                    <strong>{{ number_format($bus['threshold']) }} km</strong>
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
                                <strong>{{ $bus['remaining'] }}</strong>
                                <span class="runway-status {{ $bus['class'] }}">{{ $bus['status'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- =====================================================
                DIAGNOSTIC + PRESCRIPTIVE MAINTENANCE INTELLIGENCE
            ====================================================== --}}
            <section class="maintenance-intelligence-grid">
                <article class="maintenance-panel attention-panel">
                    <div class="panel-heading compact">
                        <div>
                            <span class="section-kicker">Prescriptive Analytics</span>
                            <h2>Maintenance Alerts & Actions</h2>
                            <p>Recommended actions based on PMS thresholds and current maintenance context.</p>
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
                                <h3>Review before additional assignment</h3>
                                <p>
                                    The example mileage is already above the PMS threshold. Confirm maintenance status,
                                    active job orders, mechanic availability, and required parts before scheduling more trips.
                                </p>
                            </div>
                        </div>

                        <div class="attention-item warning">
                            <div class="attention-marker">
                                <i class="fa-solid fa-calendar-check"></i>
                            </div>
                            <div class="attention-content">
                                <div class="attention-meta">
                                    <span>Prepare PMS</span>
                                    <strong>BUS-012</strong>
                                </div>
                                <h3>Reserve maintenance capacity before threshold</h3>
                                <p>
                                    Coordinate the recommended PMS date with trip scheduling so service can occur before
                                    the unit reaches its next PMS mileage.
                                </p>
                            </div>
                        </div>

                        <div class="attention-item warning">
                            <div class="attention-marker">
                                <i class="fa-solid fa-bell"></i>
                            </div>
                            <div class="attention-content">
                                <div class="attention-meta">
                                    <span>Early Alert</span>
                                    <strong>BUS-007</strong>
                                </div>
                                <h3>Continue mileage monitoring</h3>
                                <p>
                                    Keep the unit in the upcoming PMS queue and reassess as GPS mileage accumulates.
                                </p>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="maintenance-panel pattern-panel">
                    <div class="panel-heading compact">
                        <div>
                            <span class="section-kicker">Diagnostic Analytics</span>
                            <h2>Recurring Maintenance Issues</h2>
                            <p>Recorded maintenance categories that appear repeatedly and warrant investigation.</p>
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
                                <div class="pattern-bar"><span style="width: 100%;"></span></div>
                            </div>
                        </div>

                        <div class="pattern-row">
                            <div class="pattern-rank">02</div>
                            <div class="pattern-content">
                                <div class="pattern-title">
                                    <strong>Engine / Oil Service</strong>
                                    <span>6 cases · 23%</span>
                                </div>
                                <div class="pattern-bar"><span style="width: 74%;"></span></div>
                            </div>
                        </div>

                        <div class="pattern-row">
                            <div class="pattern-rank">03</div>
                            <div class="pattern-content">
                                <div class="pattern-title">
                                    <strong>Cooling System</strong>
                                    <span>5 cases · 19%</span>
                                </div>
                                <div class="pattern-bar"><span style="width: 61%;"></span></div>
                            </div>
                        </div>

                        <div class="pattern-row">
                            <div class="pattern-rank">04</div>
                            <div class="pattern-content">
                                <div class="pattern-title">
                                    <strong>Electrical</strong>
                                    <span>4 cases · 15%</span>
                                </div>
                                <div class="pattern-bar"><span style="width: 48%;"></span></div>
                            </div>
                        </div>
                    </div>

                    <div class="pattern-insight">
                        <div><i class="fa-solid fa-chart-line"></i></div>
                        <p>
                            Repeated issue categories should trigger maintenance review, but frequency alone does not prove
                            a mechanical root cause. Job-order details and inspection findings should be checked first.
                        </p>
                    </div>
                </article>
            </section>

            {{-- =====================================================
                TRANSPARENT FLEET MAINTENANCE BOARD
            ====================================================== --}}
            @php
                $maintenanceCards = [
                    [
                        'bus' => 'BUS-003',
                        'mileage' => '41,280 km',
                        'lastService' => 'Jun 28, 2026',
                        'records' => 2,
                        'remaining' => '8,720 km',
                        'progress' => 82.56,
                        'status' => 'Within Range',
                        'class' => 'healthy',
                    ],
                    [
                        'bus' => 'BUS-018',
                        'mileage' => '44,510 km',
                        'lastService' => 'Jun 18, 2026',
                        'records' => 3,
                        'remaining' => '5,490 km',
                        'progress' => 89.02,
                        'status' => 'Within Range',
                        'class' => 'healthy',
                    ],
                    [
                        'bus' => 'BUS-007',
                        'mileage' => '47,980 km',
                        'lastService' => 'May 30, 2026',
                        'records' => 4,
                        'remaining' => '2,020 km',
                        'progress' => 95.96,
                        'status' => 'PMS Soon',
                        'class' => 'warning',
                    ],
                    [
                        'bus' => 'BUS-012',
                        'mileage' => '48,420 km',
                        'lastService' => 'May 22, 2026',
                        'records' => 5,
                        'remaining' => '1,580 km',
                        'progress' => 96.84,
                        'status' => 'PMS Soon',
                        'class' => 'warning',
                    ],
                    [
                        'bus' => 'BUS-015',
                        'mileage' => '50,240 km',
                        'lastService' => 'Apr 19, 2026',
                        'records' => 6,
                        'remaining' => '240 km over',
                        'progress' => 100,
                        'status' => 'Threshold Reached',
                        'class' => 'critical',
                    ],
                ];
            @endphp

            <section class="fleet-health-board">
                <div class="board-heading">
                    <div>
                        <span class="section-kicker">Fleet Comparison</span>
                        <h2>Maintenance Indicator Board</h2>
                        <p>Compare measurable PMS runway, service history, and maintenance-record volume.</p>
                    </div>
                    <span class="board-count">5 Priority Units</span>
                </div>

                <div class="health-card-strip">
                    @foreach($maintenanceCards as $record)
                        <article class="bus-health-card {{ $record['class'] }}">
                            <div class="bus-health-card-top">
                                <div class="health-bus-identity">
                                    <div class="health-bus-icon"><i class="fa-solid fa-bus"></i></div>
                                    <div>
                                        <strong>{{ $record['bus'] }}</strong>
                                        <span>{{ $record['mileage'] }}</span>
                                    </div>
                                </div>

                                <span class="health-status {{ $record['class'] }}">{{ $record['status'] }}</span>
                            </div>

                            <div class="health-score-block">
                                <div class="health-score-number">
                                    <strong>{{ $record['remaining'] }}</strong>
                                    <span>PMS Runway</span>
                                </div>

                                <div class="health-score-track">
                                    <span
                                        class="{{ $record['class'] }}"
                                        style="width: {{ $record['progress'] }}%;"
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
                PRESCRIPTIVE RECOMMENDATION
            ====================================================== --}}
            <section class="maintenance-recommendation">
                <div class="recommendation-symbol">
                    <i class="fa-solid fa-lightbulb"></i>
                </div>

                <div class="recommendation-copy">
                    <span>Prescriptive Analytics</span>
                    <h2>Prioritize threshold-reached units and schedule approaching PMS work before service limits are crossed.</h2>
                    <p>
                        Final scheduling should consider active job orders, mechanic availability, required inventory parts,
                        fleet availability, and upcoming trip assignments. Analytics should recommend actions, not automatically
                        remove or assign buses without authorized review.
                    </p>
                </div>

                <a href="{{ route('analytics.recommendations') }}" class="recommendation-button">
                    View Recommendations
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </section>
        </main>
    </div>
</x-layout.app>