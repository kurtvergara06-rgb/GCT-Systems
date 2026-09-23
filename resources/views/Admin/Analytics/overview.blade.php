<x-layout.app
    title="FROMS - Analytics Overview"
    :assets="[
        'resources/css/Admin/Analytics/overview/overview.css',
        'resources/css/Admin/Analytics/overview/live-data.css',
        'resources/css/Admin/Analytics/design-system.css',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main analytics-overview-page">
            <x-layout.topbar
                title="Analytics Overview"
                subtitle="Executive summary of descriptive, diagnostic, predictive, and prescriptive analytics across FROMS"
            />

            <form class="overview-period-filter" method="GET" action="{{ route('analytics.overview') }}">
                <div>
                    <label for="overview-period">Reporting period</label>
                    <small>Trip and fuel metrics use this period; maintenance and inventory remain current snapshots.</small>
                </div>
                <select id="overview-period" name="period" onchange="this.form.submit()">
                    @foreach($periodOptions as $value => $label)
                        <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>

            {{-- =====================================================
                EXECUTIVE SNAPSHOT
            ====================================================== --}}
            <section class="executive-snapshot">
                <div class="executive-copy">
                    <span class="executive-eyebrow">
                        <i class="fa-solid fa-chart-line"></i>
                        Analytical Module Overview
                    </span>

                    <h2>{{ $summaryText }}</h2>

                    <p>
                        The overview summarizes recorded Fleet & Trip and Fuel activity for {{ $periodLabel }},
                        together with current Bus Health and Inventory thresholds. Findings are generated only
                        when the available records meet an explainable review rule.
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
                            <strong>{{ $openRecommendationCount }}</strong>
                            <span>Current Findings</span>
                        </div>
                    </div>

                    <div class="score-meta">
                        <div>
                            <span class="score-dot red"></span>
                            <div>
                                <strong>{{ $highRecommendationCount }} High</strong>
                                <small>Priority review findings</small>
                            </div>
                        </div>

                        <div>
                            <span class="score-dot yellow"></span>
                            <div>
                                <strong>{{ $mediumRecommendationCount }} Review</strong>
                                <small>Operational review findings</small>
                            </div>
                        </div>

                        <div>
                            <span class="score-dot green"></span>
                            <div>
                                <strong>{{ $monitorRecommendationCount }} Monitor</strong>
                                <small>Monitoring-only findings</small>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- =====================================================
                5.1 DESCRIPTIVE ANALYTICS
            ====================================================== --}}
            <section data-ajax-region="summary" class="analytics-kpi-strip">
                <x-analytics.kpi
                    icon="fa-road"
                    label="Distance Traveled"
                    :value="number_format($totalDistance, 1) . ' km'"
                    :description="$periodLabel . ' processed GPS trip distance'"
                    icon-variant="blue"
                />

                <x-analytics.kpi
                    icon="fa-gas-pump"
                    label="Fuel Used"
                    :value="number_format($totalFuel, 1) . ' L'"
                    :description="$periodLabel . ' recorded fuel usage'"
                    icon-variant="yellow"
                />

                <x-analytics.kpi
                    icon="fa-screwdriver-wrench"
                    label="PMS Attention"
                    :value="(string) $pmsAttentionCount"
                    description="Current buses due soon or beyond PMS threshold"
                    icon-variant="red"
                />

                <x-analytics.kpi
                    icon="fa-box-open"
                    label="Stock Threshold Alerts"
                    :value="(string) $inventoryAttentionCount"
                    description="Current items at or below reorder threshold"
                    icon-variant="red"
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
                            <p>Direct measures from available records without composite scoring.</p>
                        </div>

                        <span class="live-label">
                            <i class="fa-solid fa-circle"></i>
                            Live Database Values
                        </span>
                    </div>

                    <div class="health-matrix">
                        <a href="{{ route('analytics.fleet-trip') }}" class="health-module fleet">
                            <div class="health-module-top">
                                <div class="health-module-icon"><i class="fa-solid fa-bus"></i></div>
                                <span class="health-state {{ $moduleStates['fleet']->class }}">{{ $moduleStates['fleet']->label }}</span>
                            </div>

                            <div class="health-module-content">
                                <span>Fleet & Trip</span>
                                <strong>{{ $tripCount }} {{ $tripCount === 1 ? 'Trip' : 'Trips' }}</strong>
                                <p>{{ number_format($totalDistance, 1) }} km · {{ number_format($averageSpeed, 1) }} km/h avg.</p>
                            </div>

                            <small>
                                @if($tripCount === 0)
                                    No processed GPS trip records in {{ $periodLabel }}
                                @elseif($tripDiagnostics->review_count > 0)
                                    {{ $tripDiagnostics->review_count }} trip record(s) meet review thresholds
                                @else
                                    No trip records meet the current review thresholds
                                @endif
                            </small>
                        </a>

                        <a href="{{ route('analytics.fuel') }}" class="health-module fuel">
                            <div class="health-module-top">
                                <div class="health-module-icon"><i class="fa-solid fa-gas-pump"></i></div>
                                <span class="health-state {{ $moduleStates['fuel']->class }}">{{ $moduleStates['fuel']->label }}</span>
                            </div>

                            <div class="health-module-content">
                                <span>Fuel</span>
                                <strong>{{ $fuelRecordCount > 0 ? number_format($fleetFuelAverage, 2) . ' km/L' : 'No data' }}</strong>
                                <p>{{ number_format($totalFuel, 1) }} L recorded fuel use</p>
                            </div>

                            <small>
                                @if($fuelRecordCount === 0)
                                    No fuel reports in {{ $periodLabel }}
                                @elseif($fuelReviewCount > 0)
                                    {{ $fuelReviewCount }} bus(es) meet fuel review rules
                                @else
                                    No buses meet the current fuel review rules
                                @endif
                            </small>
                        </a>

                        <a href="{{ route('analytics.bus-health') }}" class="health-module maintenance">
                            <div class="health-module-top">
                                <div class="health-module-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                                <span class="health-state {{ $moduleStates['bus']->class }}">{{ $moduleStates['bus']->label }}</span>
                            </div>

                            <div class="health-module-content">
                                <span>Bus Health</span>
                                <strong>{{ $pmsMilestoneValue }}</strong>
                                <p>{{ $pmsMilestoneDescription }}</p>
                            </div>

                            <small>{{ $overduePmsCount }} overdue · {{ $dueSoonPmsCount }} due soon</small>
                        </a>

                        <a href="{{ route('analytics.inventory') }}" class="health-module inventory">
                            <div class="health-module-top">
                                <div class="health-module-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                                <span class="health-state {{ $moduleStates['inventory']->class }}">{{ $moduleStates['inventory']->label }}</span>
                            </div>

                            <div class="health-module-content">
                                <span>Inventory</span>
                                <strong>{{ $inventoryAttentionCount }} {{ $inventoryAttentionCount === 1 ? 'Item' : 'Items' }}</strong>
                                <p>At or below current reorder threshold</p>
                            </div>

                            <small>{{ $inventoryCriticalCount }} out of stock · {{ $inventoryLowCount }} low stock</small>
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
                        <a href="{{ url('/analytics/descriptive') }}" class="lens-item descriptive">
                            <div class="lens-index">01</div>
                            <div class="lens-icon"><i class="fa-solid fa-chart-column"></i></div>
                            <div class="lens-content">
                                <span>Descriptive · 5.1</span>
                                <strong>What happened?</strong>
                                <p>Distance, fuel used, speed, idle time, trip duration, and stock status.</p>
                            </div>
                            <span class="lens-arrow"><i class="fa-solid fa-chevron-right"></i></span>
                        </a>

                        <a href="{{ url('/analytics/diagnostic') }}" class="lens-item diagnostic">
                            <div class="lens-index">02</div>
                            <div class="lens-icon"><i class="fa-solid fa-magnifying-glass-chart"></i></div>
                            <div class="lens-content">
                                <span>Diagnostic · 5.2</span>
                                <strong>Why might it be happening?</strong>
                                <p>Delay, route-deviation, congestion, fuel-wastage, and recurring maintenance patterns.</p>
                            </div>
                            <span class="lens-arrow"><i class="fa-solid fa-chevron-right"></i></span>
                        </a>

                        <a href="{{ url('/analytics/predictive') }}" class="lens-item predictive">
                            <div class="lens-index">03</div>
                            <div class="lens-icon"><i class="fa-solid fa-chart-line"></i></div>
                            <div class="lens-content">
                                <span>Predictive · 5.3</span>
                                <strong>What is likely next?</strong>
                                <p>ETA, delay risk, peak periods, fuel use, PMS timing, and stock runway forecasts.</p>
                            </div>
                            <span class="lens-arrow"><i class="fa-solid fa-chevron-right"></i></span>
                        </a>

                        <a href="{{ url('/analytics/prescriptive') }}" class="lens-item prescriptive">
                            <div class="lens-index">04</div>
                            <div class="lens-icon"><i class="fa-solid fa-lightbulb"></i></div>
                            <div class="lens-content">
                                <span>Prescriptive · 5.4</span>
                                <strong>What should be considered?</strong>
                                <p>Assignment, route, schedule, PMS, maintenance-alert, and restocking actions.</p>
                            </div>
                            <span class="lens-arrow"><i class="fa-solid fa-chevron-right"></i></span>
                        </a>
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
                        <p>Selected current indicators and data-backed baseline conditions.</p>
                    </div>

                    <span class="period-label">{{ $periodLabel }}</span>
                </div>

                <div class="performance-layout">
                    <div class="performance-trend">
                        <div class="trend-heading">
                            <div>
                                <span>Trip Activity</span>
                                <strong>{{ $tripCount }} recorded {{ $tripCount === 1 ? 'trip' : 'trips' }}</strong>
                            </div>

                            @if($tripGrowth !== null)
                                <span class="trend-change {{ $tripGrowth >= 0 ? 'positive' : 'negative' }}">
                                    <i class="fa-solid {{ $tripGrowth >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }}"></i>
                                    {{ sprintf('%+.1f%%', $tripGrowth) }}
                                </span>
                            @else
                                <span class="trend-change neutral">No prior baseline</span>
                            @endif
                        </div>

                        <div class="mini-chart">
                            <div class="chart-grid line-1"></div>
                            <div class="chart-grid line-2"></div>
                            <div class="chart-grid line-3"></div>

                            @foreach($tripTrend as $point)
                                <div class="chart-bar-group" title="{{ $point->count }} trip record(s)">
                                    <div class="mini-bar" style="height: {{ $point->height }}%;"></div>
                                    <span>{{ $point->label }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="performance-metrics">
                        <x-analytics.kpi
                            label="Avg. Trip Duration"
                            :value="$tripCount > 0 ? number_format($averageTripDuration, 1) . ' min' : 'No data'"
                            :description="$activeRouteCount . ' recorded route' . ($activeRouteCount === 1 ? '' : 's')"
                            icon="fa-clock"
                            icon-variant="blue"
                        />

                        <x-analytics.kpi
                            label="Projected Fuel Burn"
                            :value="$projectedFuelValue"
                            :description="$projectedFuelDescription"
                            icon="fa-gas-pump"
                            icon-variant="yellow"
                        />

                        <x-analytics.kpi
                            label="Next PMS Milestone"
                            :value="$pmsMilestoneValue"
                            :description="$pmsMilestoneDescription"
                            icon="fa-screwdriver-wrench"
                            icon-variant="red"
                        />

                        <x-analytics.kpi
                            label="Parts Requiring Restock"
                            :value="$inventoryAttentionCount . ' ' . ($inventoryAttentionCount === 1 ? 'Item' : 'Items')"
                            :description="$inventoryCriticalCount . ' out of stock · ' . $inventoryLowCount . ' low stock'"
                            icon="fa-box-open"
                            icon-variant="red"
                        />
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
                        <p>Only findings supported by the current database records are shown here.</p>
                    </div>

                    <a href="{{ route('analytics.recommendations') }}" class="view-all-link">
                        View All Recommendations
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>

                <div class="priority-findings-grid">
                    @forelse($findings as $finding)
                        <article class="priority-finding {{ $finding->severity }}">
                            <div class="finding-top">
                                <div class="finding-icon {{ $finding->icon_class }}">
                                    <i class="fa-solid {{ $finding->icon }}"></i>
                                </div>
                                <span class="finding-priority {{ $finding->severity }}">{{ $finding->label }}</span>
                            </div>
                            <span class="finding-module">{{ $finding->module }}</span>
                            <h3>{{ $finding->title }}</h3>
                            <p>{{ $finding->description }}</p>
                            <a href="{{ route($finding->route) }}">
                                Review {{ $finding->module }}
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </article>
                    @empty
                        <article class="priority-finding empty">
                            <div class="finding-top">
                                <div class="finding-icon fleet"><i class="fa-solid fa-circle-check"></i></div>
                                <span class="finding-priority low">No Current Finding</span>
                            </div>
                            <span class="finding-module">Cross-Module</span>
                            <h3>No threshold-based priority finding is supported by the available records.</h3>
                            <p>As new operational records are added, this section will populate from the same database-backed review rules.</p>
                        </article>
                    @endforelse
                </div>
            </section>

            <section class="overview-note">
                <div class="overview-note-icon"><i class="fa-solid fa-circle-info"></i></div>
                <div>
                    <strong>Analytics are based on available FROMS records and explainable rules.</strong>
                    <p>
                        Descriptive values summarize recorded data; diagnostic outputs identify measurable review signals;
                        predictive baseline values appear only when sufficient historical records are available; and prescriptive
                        outputs remain recommendations for authorized personnel review rather than automatic operational changes.
                    </p>
                </div>
            </section>
        </main>
    </div>
</x-layout.app>
