<x-layout.app
    title="FROMS - Analytics Recommendations"
    :assets="[
        'resources/css/Admin/Analytics/recommendations.css',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main recommendations-page">
            <x-layout.topbar
                title="Analytics Recommendations"
                subtitle="Review explainable actions for shuttle assignment, routes, schedules, maintenance, fuel, and inventory"
                notification-count="6"
            />

            {{-- =====================================================
                5.4 PRESCRIPTIVE ANALYTICS
            ====================================================== --}}
            <section class="decision-hero">
                <div class="decision-hero-copy">
                    <span class="decision-eyebrow">
                        <i class="fa-solid fa-lightbulb"></i>
                        Prescriptive Analytics
                    </span>

                    <h2>
                        Eight operational recommendations are available for administrative review.
                    </h2>

                    <p>
                        Recommendations translate descriptive, diagnostic, and predictive findings into
                        explainable decision-support actions. FROMS should recommend what to review or adjust,
                        while authorized personnel remain responsible for applying operational changes.
                    </p>

                    <div class="decision-priority-summary">
                        <div class="priority-summary high">
                            <span>High Priority</span>
                            <strong>3</strong>
                            <small>Threshold or readiness risk</small>
                        </div>

                        <div class="priority-summary medium">
                            <span>Medium Priority</span>
                            <strong>3</strong>
                            <small>Operational adjustment</small>
                        </div>

                        <div class="priority-summary low">
                            <span>Monitoring</span>
                            <strong>2</strong>
                            <small>Continue observation</small>
                        </div>
                    </div>
                </div>

                <div class="decision-hero-side">
                    <div class="decision-score">
                        <span>Open Actions</span>
                        <strong>8</strong>
                        <small>Decision-support recommendations</small>
                    </div>

                    <div class="decision-source-list">
                        <div>
                            <span class="source-dot maintenance"></span>
                            <span>Bus Health</span>
                            <strong>2</strong>
                        </div>
                        <div>
                            <span class="source-dot inventory"></span>
                            <span>Inventory</span>
                            <strong>2</strong>
                        </div>
                        <div>
                            <span class="source-dot fuel"></span>
                            <span>Fuel</span>
                            <strong>1</strong>
                        </div>
                        <div>
                            <span class="source-dot fleet"></span>
                            <span>Fleet & Trip</span>
                            <strong>3</strong>
                        </div>
                    </div>
                </div>
            </section>

            <section class="recommendation-toolbar">
                <div>
                    <span class="section-kicker">Decision Queue</span>
                    <h2>Recommended Actions</h2>
                    <p>Review recommendations by analytics source and priority.</p>
                </div>

                <div class="recommendation-filters">
                    <select aria-label="Analytics source">
                        <option>All Modules</option>
                        <option>Fleet & Trip</option>
                        <option>Fuel</option>
                        <option>Bus Health</option>
                        <option>Inventory</option>
                    </select>

                    <select aria-label="Recommendation priority">
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
                            <p>Threshold-based findings that may affect maintenance readiness or parts availability.</p>
                        </div>
                    </div>
                    <span class="priority-count high">3 Actions</span>
                </div>

                <div class="decision-list">
                    <article class="decision-item high">
                        <div class="decision-sequence">01</div>
                        <div class="decision-module-icon maintenance">
                            <i class="fa-solid fa-screwdriver-wrench"></i>
                        </div>
                        <div class="decision-content">
                            <div class="decision-meta">
                                <span class="module-tag maintenance">Bus Health</span>
                                <span class="priority-tag high">High Priority</span>
                            </div>
                            <h3>Review BUS-015 for immediate PMS / maintenance action.</h3>
                            <p>
                                Trigger: current mileage is 50,240 km against a configured next PMS threshold of 50,000 km.
                            </p>
                            <div class="recommended-step">
                                <i class="fa-solid fa-arrow-right"></i>
                                <span>
                                    Check active Job Orders, mechanic availability, required parts, and fleet availability before assigning more trips or confirming service work.
                                </span>
                            </div>
                        </div>
                        <div class="decision-action">
                            <span>Evidence</span>
                            <a href="{{ route('analytics.bus-health') }}">
                                View Bus Health
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    </article>

                    <article class="decision-item high">
                        <div class="decision-sequence">02</div>
                        <div class="decision-module-icon inventory">
                            <i class="fa-solid fa-box-open"></i>
                        </div>
                        <div class="decision-content">
                            <div class="decision-meta">
                                <span class="module-tag inventory">Inventory</span>
                                <span class="priority-tag high">High Priority</span>
                            </div>
                            <h3>Prioritize restocking items already at or below threshold.</h3>
                            <p>
                                Trigger: critical maintenance parts have current stock at or below their configured reorder levels; Brake Pad Set has 4 units against a reorder level of 10.
                            </p>
                            <div class="recommended-step">
                                <i class="fa-solid fa-arrow-right"></i>
                                <span>
                                    Check pending restock or purchase activity first, then create or prioritize replenishment only when no adequate incoming quantity already covers the requirement.
                                </span>
                            </div>
                        </div>
                        <div class="decision-action">
                            <span>Evidence</span>
                            <a href="{{ route('analytics.inventory') }}">
                                View Inventory
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    </article>

                    <article class="decision-item high">
                        <div class="decision-sequence">03</div>
                        <div class="decision-module-icon maintenance">
                            <i class="fa-solid fa-gauge-high"></i>
                        </div>
                        <div class="decision-content">
                            <div class="decision-meta">
                                <span class="module-tag maintenance">Bus Health</span>
                                <span class="priority-tag high">High Priority</span>
                            </div>
                            <h3>Prepare PMS capacity for BUS-012 and BUS-007 before threshold.</h3>
                            <p>
                                Trigger: BUS-012 has 1,580 km remaining and BUS-007 has 2,020 km remaining before their configured PMS thresholds.
                            </p>
                            <div class="recommended-step">
                                <i class="fa-solid fa-arrow-right"></i>
                                <span>
                                    Coordinate expected PMS timing with trip demand, available mechanics, parts availability, and currently operational buses to reduce service disruption.
                                </span>
                            </div>
                        </div>
                        <div class="decision-action">
                            <span>Evidence</span>
                            <a href="{{ route('analytics.bus-health') }}">
                                View Bus Health
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    </article>
                </div>
            </section>

            {{-- =====================================================
                MEDIUM PRIORITY - OPERATIONAL ADJUSTMENTS
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
                            <p>Prescriptive actions for assignments, routes, schedules, and efficiency review.</p>
                        </div>
                    </div>
                    <span class="priority-count medium">3 Actions</span>
                </div>

                <div class="medium-decision-grid">
                    <article class="compact-decision-card medium">
                        <div class="compact-card-top">
                            <div class="compact-icon fleet"><i class="fa-solid fa-bus"></i></div>
                            <span class="priority-tag medium">Medium</span>
                        </div>
                        <span class="compact-module">Fleet & Trip</span>
                        <h3>Balance shuttle assignments across suitable available buses.</h3>
                        <p>
                            Trigger: BUS-012 and BUS-007 show higher utilization than several other operational units.
                        </p>
                        <div class="compact-action-note">
                            Consider a lower-utilization available unit only after checking maintenance status, route suitability, capacity, and driver/bus assignment constraints.
                        </div>
                        <a href="{{ route('analytics.fleet-trip') }}">
                            Review Fleet Analytics
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </article>

                    <article class="compact-decision-card medium">
                        <div class="compact-card-top">
                            <div class="compact-icon fleet"><i class="fa-solid fa-route"></i></div>
                            <span class="priority-tag medium">Medium</span>
                        </div>
                        <span class="compact-module">Fleet & Trip</span>
                        <h3>Review route adjustment for repeatedly slow trip patterns.</h3>
                        <p>
                            Trigger: selected routes show longer-than-expected duration together with lower speed or prolonged idle / stop patterns.
                        </p>
                        <div class="compact-action-note">
                            Compare expected route, recorded GPS trip path, route deviation indicators, and historical travel time before recommending an alternate route.
                        </div>
                        <a href="{{ route('analytics.fleet-trip') }}">
                            Review Route Findings
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </article>

                    <article class="compact-decision-card medium">
                        <div class="compact-card-top">
                            <div class="compact-icon fleet"><i class="fa-solid fa-calendar-days"></i></div>
                            <span class="priority-tag medium">Medium</span>
                        </div>
                        <span class="compact-module">Fleet & Trip</span>
                        <h3>Consider schedule modification during recurring peak-delay periods.</h3>
                        <p>
                            Trigger: historical trip duration is consistently longer during the identified 4:30 PM - 6:30 PM peak window.
                        </p>
                        <div class="compact-action-note">
                            Consider an earlier or later departure only when operational requirements and existing trip schedules allow the adjustment.
                        </div>
                        <a href="{{ route('analytics.fleet-trip') }}">
                            Review Schedule Findings
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </article>
                </div>
            </section>

            {{-- =====================================================
                MONITORING / SUPPORTING RECOMMENDATIONS
            ====================================================== --}}
            <section class="monitoring-section">
                <div class="monitoring-heading">
                    <div>
                        <span class="section-kicker">Continuous Monitoring</span>
                        <h2>Watch List</h2>
                        <p>Supporting findings that should remain under review before stronger action is recommended.</p>
                    </div>
                    <span class="priority-count low">2 Items</span>
                </div>

                <div class="monitoring-list">
                    <article class="monitoring-item">
                        <div class="monitoring-number">01</div>
                        <div class="monitoring-icon fuel"><i class="fa-solid fa-gas-pump"></i></div>
                        <div class="monitoring-content">
                            <span>Fuel</span>
                            <strong>Investigate possible fuel-wastage conditions before recommending corrective action.</strong>
                            <p>
                                Compare km/L, distance, trip activity, GPS idling, and maintenance context for buses below their historical or fleet efficiency baseline.
                            </p>
                        </div>
                        <a href="{{ route('analytics.fuel') }}">
                            View Fuel
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </article>

                    <article class="monitoring-item">
                        <div class="monitoring-number">02</div>
                        <div class="monitoring-icon inventory"><i class="fa-solid fa-boxes-stacked"></i></div>
                        <div class="monitoring-content">
                            <span>Inventory</span>
                            <strong>Review reorder quantity for fast-moving items before the next replenishment cycle.</strong>
                            <p>
                                Use recent Stock Out activity and projected days remaining to determine whether the configured reorder level or requested quantity should be adjusted.
                            </p>
                        </div>
                        <a href="{{ route('analytics.inventory') }}">
                            View Inventory
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </article>
                </div>
            </section>

            <section class="decision-note">
                <div class="decision-note-icon">
                    <i class="fa-solid fa-circle-info"></i>
                </div>
                <div>
                    <strong>Recommendations are explainable decision-support outputs, not automatic transactions.</strong>
                    <p>
                        Each recommendation should retain its trigger, source data, and recommended action. Authorized personnel should review findings before changing shuttle assignments, routes, schedules, PMS plans, maintenance work, or inventory replenishment activity.
                    </p>
                </div>
            </section>
        </main>
    </div>
</x-layout.app>