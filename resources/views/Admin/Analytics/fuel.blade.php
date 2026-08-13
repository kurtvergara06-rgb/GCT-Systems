<x-layout.app
    title="FROMS - Fuel Analytics"
    :assets="[
        'resources/css/Admin/Analytics/fuel.css',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main fuel-analytics-page">
            <x-layout.topbar
                title="Fuel Analytics"
                subtitle="Analyze fuel usage, efficiency, possible wastage factors, and projected fuel demand"
                notification-count="6"
            />

            {{-- =====================================================
                5.1 DESCRIPTIVE ANALYTICS
            ====================================================== --}}
            <section data-ajax-region="summary" class="stats-grid fuel-summary-grid">
                <x-ui.summary-card
                    label="Fuel Used"
                    value="3,842 L"
                    small="Recorded fuel volume for the selected period"
                    icon="fa-gas-pump"
                    color="blue"
                />

                <x-ui.summary-card
                    label="Distance Traveled"
                    value="26,126 km"
                    small="Distance linked to recorded fuel reports"
                    icon="fa-road"
                    color="blue"
                />

                <x-ui.summary-card
                    label="Average Efficiency"
                    value="6.8 km/L"
                    small="Distance divided by recorded fuel volume"
                    icon="fa-gauge-high"
                    color="green"
                />

                <x-ui.summary-card
                    label="Units for Review"
                    value="3"
                    small="Below-average efficiency requiring context review"
                    icon="fa-triangle-exclamation"
                    color="yellow"
                />
            </section>

            <section class="fuel-filter-card">
                <div>
                    <h2>Fuel Performance</h2>
                    <p>Review fuel reports together with trip mileage and GPS-derived operating indicators.</p>
                </div>

                <div class="fuel-filters">
                    <select aria-label="Analysis period">
                        <option>This Month</option>
                        <option>Last 30 Days</option>
                        <option>Last 3 Months</option>
                        <option>This Year</option>
                    </select>

                    <select aria-label="Shuttle bus">
                        <option>All Buses</option>
                        <option>BUS-001</option>
                        <option>BUS-003</option>
                        <option>BUS-007</option>
                        <option>BUS-012</option>
                        <option>BUS-015</option>
                    </select>
                </div>
            </section>

            <section class="fuel-grid">
                <article class="fuel-card">
                    <div class="fuel-card-header">
                        <div>
                            <h2>Fuel Consumption Trend</h2>
                            <p>Recorded fuel volume throughout the current analysis period.</p>
                        </div>

                        <span class="analytics-badge descriptive">Descriptive</span>
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

                <article class="fuel-card">
                    <div class="fuel-card-header">
                        <div>
                            <h2>Fuel Efficiency Overview</h2>
                            <p>Describes how far shuttle units travel for each recorded liter of fuel.</p>
                        </div>

                        <span class="analytics-badge descriptive">Descriptive</span>
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
                                <span>Lowest Recorded</span>
                                <strong>5.7 km/L</strong>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            {{-- =====================================================
                5.2 DIAGNOSTIC ANALYTICS
            ====================================================== --}}
            <section class="fuel-grid">
                <article class="fuel-card">
                    <div class="fuel-card-header">
                        <div>
                            <h2>Possible Fuel Wastage Review</h2>
                            <p>Low efficiency is a review signal, not proof of vehicle-caused fuel wastage.</p>
                        </div>

                        <span class="analytics-badge diagnostic">Diagnostic</span>
                    </div>

                    <div class="high-consumption-list">
                        <div class="consumption-item">
                            <div class="consumption-main">
                                <div class="consumption-icon critical">
                                    <i class="fa-solid fa-bus"></i>
                                </div>

                                <div>
                                    <strong>BUS-012</strong>
                                    <span>312 L · 1,778 km · efficiency below fleet average</span>
                                </div>
                            </div>

                            <div class="consumption-value critical">
                                <strong>5.7 km/L</strong>
                                <span>Priority Review</span>
                            </div>
                        </div>

                        <div class="consumption-item">
                            <div class="consumption-main">
                                <div class="consumption-icon warning">
                                    <i class="fa-solid fa-bus"></i>
                                </div>

                                <div>
                                    <strong>BUS-007</strong>
                                    <span>286 L · 1,687 km · efficiency below fleet average</span>
                                </div>
                            </div>

                            <div class="consumption-value warning">
                                <strong>5.9 km/L</strong>
                                <span>Review</span>
                            </div>
                        </div>

                        <div class="consumption-item">
                            <div class="consumption-main">
                                <div class="consumption-icon warning">
                                    <i class="fa-solid fa-bus"></i>
                                </div>

                                <div>
                                    <strong>BUS-015</strong>
                                    <span>224 L · 1,366 km · efficiency below fleet average</span>
                                </div>
                            </div>

                            <div class="consumption-value warning">
                                <strong>6.1 km/L</strong>
                                <span>Review</span>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="fuel-card">
                    <div class="fuel-card-header">
                        <div>
                            <h2>Fuel Wastage Factors</h2>
                            <p>Context that should be checked before assigning a cause to abnormal fuel use.</p>
                        </div>

                        <span class="analytics-badge diagnostic">Diagnostic</span>
                    </div>

                    <div class="fuel-findings">
                        <div class="fuel-finding warning">
                            <div class="finding-icon">
                                <i class="fa-solid fa-hourglass-half"></i>
                            </div>

                            <div>
                                <strong>Extended idling can reduce effective fuel efficiency.</strong>
                                <p>Use linked GPS trip records to compare idling minutes with fuel consumption for the same unit and period.</p>
                            </div>
                        </div>

                        <div class="fuel-finding info">
                            <div class="finding-icon">
                                <i class="fa-solid fa-route"></i>
                            </div>

                            <div>
                                <strong>Trip distance and activity can explain higher total fuel use.</strong>
                                <p>Compare mileage, trip volume, trip duration, and route conditions before classifying consumption as waste.</p>
                            </div>
                        </div>

                        <div class="fuel-finding critical">
                            <div class="finding-icon">
                                <i class="fa-solid fa-screwdriver-wrench"></i>
                            </div>

                            <div>
                                <strong>Persistent efficiency decline should be checked against maintenance history.</strong>
                                <p>Repeated low km/L together with maintenance findings is stronger evidence than a single fuel report.</p>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            {{-- =====================================================
                5.3 PREDICTIVE ANALYTICS
            ====================================================== --}}
            <section class="fuel-grid">
                <article class="fuel-card">
                    <div class="fuel-card-header">
                        <div>
                            <h2>Fuel Usage Forecast</h2>
                            <p>Future fuel demand should be estimated from recent fuel usage and expected trip distance.</p>
                        </div>

                        <span class="analytics-badge predictive">Predictive</span>
                    </div>

                    <div class="efficiency-list">
                        <div class="efficiency-row">
                            <span>Current Period</span>
                            <strong>3,842 L</strong>
                        </div>

                        <div class="efficiency-row">
                            <span>Projected Next Period</span>
                            <strong>4,060 L</strong>
                        </div>

                        <div class="efficiency-row">
                            <span>Projected Change</span>
                            <strong>+5.7%</strong>
                        </div>
                    </div>

                    <div class="fuel-findings fuel-forecast-note">
                        <div class="fuel-finding info">
                            <div class="finding-icon">
                                <i class="fa-solid fa-chart-line"></i>
                            </div>

                            <div>
                                <strong>Forecast method</strong>
                                <p>Future backend logic should combine scheduled/expected distance with historical km/L instead of extending fuel volume alone.</p>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="fuel-card">
                    <div class="fuel-card-header">
                        <div>
                            <h2>Early Fuel Efficiency Alerts</h2>
                            <p>Threshold detection should flag sustained deterioration before it becomes a larger operating issue.</p>
                        </div>

                        <span class="analytics-badge predictive">Predictive</span>
                    </div>

                    <div class="fuel-findings">
                        <div class="fuel-finding critical">
                            <div class="finding-icon">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>

                            <div>
                                <strong>BUS-012 shows the strongest current efficiency-decline signal.</strong>
                                <p>Future logic should alert only when the decline persists across multiple comparable records or crosses a defined threshold.</p>
                            </div>
                        </div>

                        <div class="fuel-finding warning">
                            <div class="finding-icon">
                                <i class="fa-solid fa-gauge-high"></i>
                            </div>

                            <div>
                                <strong>Compare against each bus's own historical baseline.</strong>
                                <p>A unit-specific baseline is more meaningful than labeling every bus below the fleet average as wasteful.</p>
                            </div>
                        </div>

                        <div class="fuel-finding info">
                            <div class="finding-icon">
                                <i class="fa-solid fa-database"></i>
                            </div>

                            <div>
                                <strong>Use linked trip and fuel records for prediction.</strong>
                                <p>Distance, idling time, trip duration, fuel liters, and historical km/L are already compatible with this future analytical rule set.</p>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            {{-- =====================================================
                BUS COMPARISON
            ====================================================== --}}
            @php
                $fuelRecords = [
                    ['bus' => 'BUS-003', 'distance' => '1,520 km', 'fuel' => '200 L', 'efficiency' => '7.6 km/L', 'change' => '+3.2%', 'status' => 'Efficient'],
                    ['bus' => 'BUS-018', 'distance' => '1,404 km', 'fuel' => '195 L', 'efficiency' => '7.2 km/L', 'change' => '+1.4%', 'status' => 'Normal'],
                    ['bus' => 'BUS-009', 'distance' => '1,368 km', 'fuel' => '198 L', 'efficiency' => '6.9 km/L', 'change' => '+0.5%', 'status' => 'Normal'],
                    ['bus' => 'BUS-015', 'distance' => '1,366 km', 'fuel' => '224 L', 'efficiency' => '6.1 km/L', 'change' => '-4.8%', 'status' => 'Review'],
                    ['bus' => 'BUS-007', 'distance' => '1,687 km', 'fuel' => '286 L', 'efficiency' => '5.9 km/L', 'change' => '-6.2%', 'status' => 'Review'],
                    ['bus' => 'BUS-012', 'distance' => '1,778 km', 'fuel' => '312 L', 'efficiency' => '5.7 km/L', 'change' => '-8.1%', 'status' => 'Priority Review'],
                ];
            @endphp

            <section data-ajax-region="records" class="fuel-card fuel-table-card">
                <div class="fuel-card-header">
                    <div>
                        <h2>Bus Fuel Efficiency Comparison</h2>
                        <p>Compare recorded distance, fuel volume, efficiency, and direction of change across shuttle units.</p>
                    </div>

                    <span class="analytics-badge diagnostic">Diagnostic Support</span>
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
                                        'Priority Review' => 'critical',
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
                                        <strong class="efficiency-value">{{ $record['efficiency'] }}</strong>
                                    </td>

                                    <td>
                                        <span class="change-value {{ $changeClass }}">{{ $record['change'] }}</span>
                                    </td>

                                    <td>
                                        <span class="fuel-status {{ $statusClass }}">{{ $record['status'] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- =====================================================
                5.4 PRESCRIPTIVE HANDOFF
            ====================================================== --}}
            <section class="fuel-insight">
                <div class="insight-icon">
                    <i class="fa-solid fa-lightbulb"></i>
                </div>

                <div class="insight-content">
                    <span>Prescriptive Analytics</span>

                    <h2>
                        Review high-idle, declining-efficiency units and their maintenance context before changing assignments or scheduling service.
                    </h2>

                    <p>
                        Fuel Analytics should provide evidence to the Recommendations layer, where authorized personnel can consider maintenance review,
                        trip reassignment, route/schedule changes, or other actions based on verified thresholds and supporting records.
                    </p>
                </div>

                <a href="{{ route('analytics.recommendations') }}" class="insight-link">
                    View Recommendations
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </section>
        </main>
    </div>
</x-layout.app>