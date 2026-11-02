@php
    $prescriptiveUrl = route('analytics.stage', ['stage' => 'prescriptive'], false);
    $activeDomain = in_array(request('domain'), ['inventory', 'restocking'], true) ? 'inventory' : 'fleet-trip';
    $isRestocking = $activeDomain === 'inventory';

    $operationsData = $operationsPrescriptive ?? $prescriptive?->operations;
    $restockingData = $restockingPrescriptive ?? $prescriptive?->restocking;

    $isDemo = $isRestocking ? ($restockingData->is_demo ?? true) : ($operationsData->is_demo ?? true);

    $title = $isRestocking ? 'Prescriptive Analytics — Restocking' : 'Prescriptive Analytics — Operations';
    $subtitle = $isRestocking
        ? 'Inventory replenishment recommendations backed by historical demand, safety buffer deficits, and confirmed deliveries (Thesis Objective 6.4.2).'
        : 'Decision-support scheduling, eligible driver and bus assignment recommendations adhering to headway and rest rules (Thesis Objective 6.4.1).';

    $periodOptions = [
        'this-month' => 'This Month',
        'this-week' => 'This Week',
        'last-30-days' => 'Last 30 Days',
        'last-90-days' => 'Last 90 Days',
    ];

    $currentFilters = request()->only(['period', 'bus', 'demo']);
    $normalizedSelectedBus = strtoupper(trim((string) $selectedBus));

    if (! $isRestocking) {
        $chartPayload = [
            'activeDomain' => 'fleet-trip',
            'shiftLabels' => $operationsData->shift_distribution->labels ?? ['Morning', 'Afternoon', 'Evening'],
            'scheduledByShift' => $operationsData->shift_distribution->scheduled ?? [0, 0, 0],
            'recommendedByShift' => $operationsData->shift_distribution->recommended ?? [0, 0, 0],
            'readinessLabels' => collect($operationsData->readiness_distribution ?? [])->pluck('label')->toArray(),
            'readinessValues' => collect($operationsData->readiness_distribution ?? [])->pluck('count')->toArray(),
            'readinessColors' => collect($operationsData->readiness_distribution ?? [])->pluck('color')->toArray(),
        ];
    } else {
        $chartPayload = [
            'activeDomain' => 'inventory',
            'categoryLabels' => collect($restockingData->category_replenishment ?? [])->pluck('category')->toArray(),
            'categoryValues' => collect($restockingData->category_replenishment ?? [])->pluck('quantity')->toArray(),
            'categoryColors' => collect($restockingData->category_replenishment ?? [])->pluck('color')->toArray(),
            'coverageLabels' => $restockingData->stock_coverage->labels ?? [],
            'coverageOnHand' => $restockingData->stock_coverage->on_hand ?? [],
            'coverageBuffer' => $restockingData->stock_coverage->safety_buffer ?? [],
            'coverageRestock' => $restockingData->stock_coverage->suggested_restock ?? [],
            'priorityLabels' => collect($restockingData->priority_distribution ?? [])->pluck('label')->toArray(),
            'priorityValues' => collect($restockingData->priority_distribution ?? [])->pluck('count')->toArray(),
            'priorityColors' => collect($restockingData->priority_distribution ?? [])->pluck('color')->toArray(),
        ];
    }
@endphp

<x-layout.app
    title="FROMS - {{ $title }}"
    :assets="[
        'resources/css/Admin/Analytics/design-system.css',
        'resources/css/Admin/Analytics/prescriptive/prescriptive-rebuild.css',
        'resources/js/Admin/Analytics/prescriptive/prescriptive-analytics.js'
    ]"
    :partial-navigation="false"
>
    <div class="app">
        <x-layout.sidebar department="Admin" />
        <main class="main prescriptive-rebuild">
            <x-layout.topbar
                :title="$title"
                :subtitle="$subtitle"
            />

            <!-- Hidden JSON payload consumed by Chart.js script -->
            <script type="application/json" id="prescriptiveAnalyticsData">
                {!! json_encode($chartPayload) !!}
            </script>

            <!-- Controls & Filters Toolbar -->
            <div class="prescriptive-controls-bar">
                <nav class="prescriptive-nav-pills" aria-label="Prescriptive analytics domain">
                    <a href="{{ $prescriptiveUrl . '?' . http_build_query(array_merge($currentFilters, ['domain' => 'fleet-trip'])) }}"
                       class="{{ ! $isRestocking ? 'active' : '' }}"
                       @if(! $isRestocking) aria-current="page" @endif>
                        <i class="fa-solid fa-bus"></i> Operations Recommendations
                    </a>
                    <a href="{{ $prescriptiveUrl . '?' . http_build_query(array_merge($currentFilters, ['domain' => 'inventory'])) }}"
                       class="{{ $isRestocking ? 'active' : '' }}"
                       @if($isRestocking) aria-current="page" @endif>
                        <i class="fa-solid fa-boxes-stacked"></i> Restocking Recommendations
                    </a>
                </nav>

                <form method="GET" action="{{ $prescriptiveUrl }}" class="prescriptive-filters-form">
                    <input type="hidden" name="domain" value="{{ $activeDomain }}">
                    @if(request()->has('demo'))
                        <input type="hidden" name="demo" value="{{ request('demo') }}">
                    @endif

                    <div class="prescriptive-filter-group">
                        <label for="prescriptivePeriod"><i class="fa-regular fa-calendar"></i> Planning Period</label>
                        <select id="prescriptivePeriod" name="period" onchange="this.form.submit()">
                            @foreach($periodOptions as $val => $lbl)
                                <option value="{{ $val }}" @selected($period === $val)>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if(! $isRestocking)
                        <div class="prescriptive-filter-group">
                            <label for="prescriptiveBus"><i class="fa-solid fa-bus-simple"></i> Bus</label>
                            <select id="prescriptiveBus" name="bus" onchange="this.form.submit()">
                                <option value="all" @selected($normalizedSelectedBus === 'ALL')>All Fleet Buses</option>
                                @foreach($busOptions as $busNo)
                                    <option value="{{ $busNo }}" @selected($normalizedSelectedBus === strtoupper((string)$busNo))>
                                        {{ $busNo }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="prescriptive-date-badge">
                        <i class="fa-solid fa-clock-rotate-left"></i> {{ ucfirst(str_replace('-', ' ', $period)) }}
                    </div>
                </form>
            </div>

            <!-- Unified Status, Provenance & Decision Support Mandate Bar -->
            <div class="prescriptive-status-strip">
                <div class="status-strip-left">
                    @if($isDemo)
                        <span class="demo-banner-pill"><i class="fa-solid fa-flask"></i> Demo Data</span>
                        <span class="status-strip-text">
                            <strong>Demonstration Mode:</strong> Illustrating Thesis Objective 6.4 algorithmic guidance
                            @if(! $isRestocking)
                                (headway spacing &amp; PMS clearance). Live records: <strong>{{ $operationsData->real_count }} trip(s)</strong>.
                            @else
                                (reorder thresholds &amp; buffer deficits). Live catalog: <strong>{{ $restockingData->real_count }} item(s)</strong>.
                            @endif
                        </span>
                        @if(request()->boolean('demo'))
                            <a href="{{ $prescriptiveUrl . '?' . http_build_query(array_merge(request()->except('demo'), ['domain' => $activeDomain])) }}" class="strip-switch-link">
                                <span>Switch to Live</span> <i class="fa-solid fa-rotate"></i>
                            </a>
                        @endif
                    @else
                        <span class="live-banner-pill"><i class="fa-solid fa-circle-check"></i> Live Database Records</span>
                        <span class="status-strip-text">
                            Displaying verified operational recommendations computed from FROMS Operations &amp; Warehouse records.
                        </span>
                    @endif
                </div>

                <div class="status-strip-mandate" role="note">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>
                        <strong>Thesis Objective 6.4 Decision Support Mandate:</strong>
                        @if(! $isRestocking)
                            Prescriptive suggestions require review &amp; manual confirmation by the <strong>Operations Head</strong>. No automated vehicle dispatch executed.
                        @else
                            Replenishment volumes require review &amp; electronic signature by the <strong>Warehouse Head</strong> before PR creation. No automated PR/PO issued.
                        @endif
                    </span>
                </div>
            </div>

            @if(! $isRestocking)
                <!-- =========================================================================
                     THESIS OBJECTIVE 6.4.1 — OPERATIONS PRESCRIPTIVE RECOMMENDATIONS
                     ========================================================================= -->

                <!-- 1. Top 4 KPI Summary Cards Grid -->
                <section class="prescriptive-stats-grid">
                    <!-- Trips Needing Assignment -->
                    <x-ui.summary-card
                        label="Trips Needing Assignment"
                        value="{{ $operationsData->trips_needing_assignment }}"
                        small="{{ $operationsData->assigned_trips_count }} assigned · {{ $operationsData->total_scheduled_trips }} total scheduled"
                        icon="fa-calendar-days"
                        color="blue"
                        href="{{ route('trip-schedule') }}"
                    />

                    <!-- Eligible Drivers -->
                    <x-ui.summary-card
                        label="Eligible Drivers"
                        value="{{ $operationsData->eligible_drivers_count }}"
                        small="Attendance & rest compliance verified"
                        icon="fa-user-check"
                        color="green"
                        href="{{ route('driver-attendance') }}"
                    />

                    <!-- Available Buses -->
                    <x-ui.summary-card
                        label="Available Buses"
                        value="{{ $operationsData->available_buses_count }}"
                        small="Active status · 0 open Job Orders"
                        icon="fa-bus"
                        color="purple"
                        href="{{ route('bus-master-list') }}"
                    />

                    <!-- Scheduling Conflicts -->
                    <x-ui.summary-card
                        label="Scheduling Conflicts"
                        value="{{ $operationsData->conflicts_count }}"
                        small="{{ $operationsData->conflicts_count === 0 ? 'Zero headway overlaps detected' : 'Requires stagger or resource adjustment' }}"
                        icon="fa-triangle-exclamation"
                        color="{{ $operationsData->conflicts_count > 0 ? 'red' : 'green' }}"
                    />
                </section>

                <!-- 2. Analytical Charts Grid -->
                <section class="prescriptive-dashboard-grid">
                    <!-- Shift & Departure Recommendation Distribution -->
                    <article class="prescriptive-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <h3><i class="fa-solid fa-chart-column"></i> Shift &amp; Departure Distribution</h3>
                                <p class="dashboard-eyebrow">Scheduled trip volume vs prescribed headway-adjusted dispatches per shift</p>
                            </div>
                            <span class="dashboard-badge">Shift Allocation</span>
                        </div>
                        <div class="prescriptive-chart-wrap">
                            <canvas id="prescriptiveShiftChart"></canvas>
                        </div>
                    </article>

                    <!-- Resource Readiness & Dispatch Status -->
                    <article class="prescriptive-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <h3><i class="fa-solid fa-chart-pie"></i> Resource &amp; Dispatch Readiness</h3>
                                <p class="dashboard-eyebrow">Operational clearance breakdown for fleet and personnel</p>
                            </div>
                            <span class="dashboard-badge">Dispatch Status</span>
                        </div>
                        <div class="fleet-health-visual-wrap">
                            <canvas id="prescriptiveReadinessDonutChart"></canvas>
                            <div class="fleet-health-chart-center">
                                <span class="chart-center-metric">{{ $operationsData->available_buses_count }}</span>
                                <span class="chart-center-sub">Available Units</span>
                            </div>
                        </div>
                    </article>
                </section>

                <!-- 3. Scheduling Conflicts (If Any) -->
                @if(collect($operationsData->conflicts ?? [])->isNotEmpty())
                    <section class="prescriptive-dashboard-card" style="margin-bottom: 18px; border-left: 4px solid #ea580c;">
                        <div class="dashboard-card-header" style="border-bottom:none; margin-bottom: 8px;">
                            <div>
                                <h3 style="color: #c2410c;"><i class="fa-solid fa-triangle-exclamation" style="color:#ea580c;"></i> Detected Scheduling Conflicts &amp; Headway Warnings</h3>
                                <p class="dashboard-eyebrow">Algorithmic checks identified the following potential headway or resource bottlenecks requiring review:</p>
                            </div>
                            <span class="badge-priority warning">{{ collect($operationsData->conflicts)->count() }} Alerts</span>
                        </div>
                        <div class="playbook-list">
                            @foreach($operationsData->conflicts as $conflict)
                                <div class="playbook-card warning">
                                    <div class="playbook-info">
                                        <h4>
                                            <span class="badge-priority warning">{{ $conflict->severity }}</span>
                                            {{ $conflict->title }} — <span style="font-weight:600; color:#64748b;">{{ $conflict->affected }}</span>
                                        </h4>
                                        <p>{{ $conflict->description }}</p>
                                        <p style="margin-top:4px; color:#1d4ed8; font-weight:600;"><i class="fa-solid fa-lightbulb"></i> Recommended Mitigation: {{ $conflict->mitigation }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <!-- 4. Schedule Recommendations Table (Full Width) -->
                <section class="prescriptive-dashboard-card" style="margin-bottom: 18px;">
                    <div class="dashboard-card-header">
                        <div>
                            <h3><i class="fa-solid fa-calendar-check"></i> Recommended Departure &amp; Trip Schedules</h3>
                            <p class="dashboard-eyebrow">Prescribed departure times, corridor headway spacing, and candidate resource assignments</p>
                        </div>
                        <span class="dashboard-badge">{{ collect($operationsData->schedule_recommendations)->count() }} Trips</span>
                    </div>

                    <div class="prescriptive-table-container">
                        <table class="prescriptive-table">
                            <thead>
                                <tr>
                                    <th>Trip Code</th>
                                    <th>Route &amp; Corridor</th>
                                    <th>Shift / Date</th>
                                    <th>Suggested Departure</th>
                                    <th>Est. Arrival</th>
                                    <th>Duration</th>
                                    <th>Recommended Unit</th>
                                    <th>Recommended Driver</th>
                                    <th>Algorithmic Recommendation Reason</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($operationsData->schedule_recommendations as $rec)
                                    <tr>
                                        <td>
                                            <span class="code-pill">
                                                <i class="fa-solid fa-hashtag"></i> {{ $rec->trip_code }}
                                            </span>
                                        </td>
                                        <td>
                                            <strong style="color:#0f172a;">{{ $rec->route_name }}</strong>
                                            <br><small style="color:#64748b;">{{ $rec->route_code }}</small>
                                        </td>
                                        <td>
                                            <span class="dashboard-badge">{{ $rec->shift }}</span>
                                            <br><small style="color:#64748b;">{{ $rec->trip_date }}</small>
                                        </td>
                                        <td>
                                            <strong style="color:#1d4ed8; font-size:13px;">{{ $rec->suggested_departure }}</strong>
                                            <br><span class="headway-pill"><i class="fa-solid fa-arrows-left-right"></i> {{ $rec->headway_gap }}</span>
                                        </td>
                                        <td>
                                            <strong>{{ $rec->estimated_arrival }}</strong>
                                        </td>
                                        <td>
                                            <span style="font-weight:600; color:#475569;">{{ $rec->historical_duration }}</span>
                                        </td>
                                        <td>
                                            <span class="bus-chip">
                                                <i class="fa-solid fa-bus"></i> {{ $rec->recommended_bus }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="driver-chip">
                                                <i class="fa-solid fa-id-card"></i> {{ $rec->recommended_driver }}
                                            </span>
                                        </td>
                                        <td style="max-width: 320px;">
                                            <small style="color:#334155; line-height:1.45; display:block;">
                                                <i class="fa-solid fa-circle-check text-green" style="color:#16a34a;"></i> {{ $rec->reason }}
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge-status optimal">
                                                {{ $rec->status }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" style="text-align:center; padding:32px; color:#64748b;">
                                            <i class="fa-solid fa-circle-info" style="font-size:24px; margin-bottom:8px; display:block; color:#94a3b8;"></i>
                                            No pending scheduled trips require assignment for this selection.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- 5. Side-by-Side: Eligible Drivers & Available Buses (Equal Height Scrollable) -->
                <section class="prescriptive-split-grid">
                    <!-- Eligible Driver Assignment Recommendations -->
                    <article class="prescriptive-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <h3><i class="fa-solid fa-id-card"></i> Eligible Driver Assignment Recommendations</h3>
                                <p class="dashboard-eyebrow">Ranked drivers with verified shift attendance and zero scheduling conflicts</p>
                            </div>
                            <span class="dashboard-badge">{{ collect($operationsData->driver_recommendations)->count() }} Eligible</span>
                        </div>

                        <div class="prescriptive-table-container scrollable-body">
                            <table class="prescriptive-table">
                                <thead>
                                    <tr>
                                        <th>Driver &amp; Authorization</th>
                                        <th>Shift &amp; Attendance</th>
                                        <th>Duty Hours</th>
                                        <th>Clearance &amp; Rationale</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($operationsData->driver_recommendations as $driver)
                                        <tr>
                                            <td>
                                                <strong style="color:#0f172a; font-size:12.5px;">{{ $driver->driver_name }}</strong>
                                                <br><small style="color:#64748b;"><i class="fa-solid fa-id-card"></i> {{ $driver->bus_authorization }}</small>
                                            </td>
                                            <td>
                                                <span class="badge-status optimal">{{ $driver->attendance_status }}</span>
                                                <br><small style="color:#64748b;">{{ $driver->shift }}</small>
                                            </td>
                                            <td>
                                                <span class="code-pill">{{ $driver->duty_hours }}</span>
                                            </td>
                                            <td>
                                                <span class="badge-status optimal"><i class="fa-solid fa-check"></i> {{ $driver->eligibility }}</span>
                                                <small style="color:#475569; display:block; line-height:1.35; margin-top:3px;">{{ $driver->reason }}</small>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" style="text-align:center; padding:24px; color:#64748b;">
                                                No eligible drivers logged in current attendance.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <!-- Available Bus Assignment Recommendations -->
                    <article class="prescriptive-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <h3><i class="fa-solid fa-bus-simple"></i> Available Bus Recommendations</h3>
                                <p class="dashboard-eyebrow">Active units cleared of maintenance orders and within PMS limits</p>
                            </div>
                            <span class="dashboard-badge">{{ collect($operationsData->bus_recommendations)->count() }} Cleared</span>
                        </div>

                        <div class="prescriptive-table-container scrollable-body">
                            <table class="prescriptive-table">
                                <thead>
                                    <tr>
                                        <th>Bus / Plate</th>
                                        <th>Status &amp; PMS</th>
                                        <th>Capacity</th>
                                        <th>Clearance Rationale</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($operationsData->bus_recommendations as $bus)
                                        <tr>
                                            <td>
                                                <span class="bus-chip"><i class="fa-solid fa-bus"></i> {{ $bus->bus_no }}</span>
                                                <br><small style="color:#64748b;">{{ $bus->plate_no }}</small>
                                            </td>
                                            <td>
                                                <span class="badge-status optimal">{{ $bus->status }}</span>
                                                <br><small style="font-weight:700; color:#15803d;"><i class="fa-solid fa-gauge-high"></i> {{ $bus->pms_headroom }}</small>
                                            </td>
                                            <td>
                                                <span class="dashboard-badge">{{ $bus->capacity }} Seats</span>
                                            </td>
                                            <td>
                                                <small style="color:#475569; display:block; line-height:1.35;">{{ $bus->reason }}</small>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" style="text-align:center; padding:24px; color:#64748b;">
                                                No buses currently cleared of active maintenance.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </article>
                </section>

                <!-- 6. Operational Approval & Safeguards Standards Card -->
                <div class="prescriptive-safeguard-card">
                    <h4><i class="fa-solid fa-user-shield"></i> Operations Dispatch Safeguards &amp; Compliance Standards</h4>
                    <p>
                        Prescriptive recommendations respect strict operational and labor safety boundaries. Dispatch officers must confirm compliance before approving trip assignments in Operations:
                    </p>
                    <div class="prescriptive-safeguard-grid">
                        <div class="safeguard-pill-item">
                            <i class="fa-solid fa-arrows-left-right"></i>
                            <div>
                                <strong>15-Minute Corridor Headway</strong>
                                <small>Enforces minimum departure gap between buses on same corridor to avoid bunching and passenger delays.</small>
                            </div>
                        </div>
                        <div class="safeguard-pill-item">
                            <i class="fa-solid fa-business-time"></i>
                            <div>
                                <strong>8-Hour Maximum Duty Hours</strong>
                                <small>Prevents driver fatigue by enforcing rest intervals and blocking overtime beyond 8 consecutive duty hours.</small>
                            </div>
                        </div>
                        <div class="safeguard-pill-item">
                            <i class="fa-solid fa-wrench"></i>
                            <div>
                                <strong>Strict PMS Clearance</strong>
                                <small>Restricts buses undergoing active maintenance or exceeding next PMS mileage threshold from dispatch.</small>
                            </div>
                        </div>
                    </div>
                </div>

            @else
                <!-- =========================================================================
                     THESIS OBJECTIVE 6.4.2 — RESTOCKING PRESCRIPTIVE RECOMMENDATIONS
                     ========================================================================= -->

                <!-- 1. Top 4 KPI Summary Cards Grid -->
                <section class="prescriptive-stats-grid">
                    <!-- Inventory Items Monitored -->
                    <x-ui.summary-card
                        label="Inventory Items Monitored"
                        value="{{ $restockingData->inventory_items_count }}"
                        small="Active automotive spare parts catalog"
                        icon="fa-boxes-stacked"
                        color="blue"
                        href="{{ route('inventory') }}"
                    />

                    <!-- Low Stock Attention -->
                    <x-ui.summary-card
                        label="Low Stock Attention"
                        value="{{ $restockingData->low_stock_count }}"
                        small="On-hand stock at or below reorder level"
                        icon="fa-triangle-exclamation"
                        color="yellow"
                        href="{{ route('inventory') }}"
                    />

                    <!-- Critical / Out of Stock -->
                    <x-ui.summary-card
                        label="Critical / Out of Stock"
                        value="{{ $restockingData->out_of_stock_count }}"
                        small="Zero on-hand units posing grounding risk"
                        icon="fa-circle-xmark"
                        color="red"
                        href="{{ route('inventory') }}"
                    />

                    <!-- Recommended Restock Qty -->
                    <x-ui.summary-card
                        label="Recommended Restock Qty"
                        value="{{ number_format($restockingData->recommended_restock_qty) }}"
                        small="Consolidated replenishment units needed"
                        icon="fa-cart-shopping"
                        color="purple"
                    />
                </section>

                <!-- 2. Analytical Charts Grid (3 Columns) -->
                <section class="prescriptive-three-grid">
                    <!-- Recommended Quantity by Category -->
                    <article class="prescriptive-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <h3><i class="fa-solid fa-chart-column"></i> Restock by Category</h3>
                                <p class="dashboard-eyebrow">Suggested replenishment volume per category</p>
                            </div>
                            <span class="dashboard-badge">Units</span>
                        </div>
                        <div class="prescriptive-chart-wrap">
                            <canvas id="prescriptiveCategoryBarChart"></canvas>
                        </div>
                    </article>

                    <!-- Stock Coverage Projection -->
                    <article class="prescriptive-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <h3><i class="fa-solid fa-chart-line"></i> Stock Coverage Projection</h3>
                                <p class="dashboard-eyebrow">On-hand vs safety buffer vs suggested restock</p>
                            </div>
                            <span class="dashboard-badge">Deficit Gap</span>
                        </div>
                        <div class="prescriptive-chart-wrap">
                            <canvas id="prescriptiveStockCoverageChart"></canvas>
                        </div>
                    </article>

                    <!-- Replenishment Priority Distribution -->
                    <article class="prescriptive-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <h3><i class="fa-solid fa-chart-pie"></i> Priority Distribution</h3>
                                <p class="dashboard-eyebrow">Replenishment urgency based on shortage risk</p>
                            </div>
                            <span class="dashboard-badge">Risk Breakdown</span>
                        </div>
                        <div class="fleet-health-visual-wrap">
                            <canvas id="prescriptivePriorityDonutChart"></canvas>
                            <div class="fleet-health-chart-center">
                                <span class="chart-center-metric">{{ $restockingData->out_of_stock_count + $restockingData->low_stock_count }}</span>
                                <span class="chart-center-sub">Action Items</span>
                            </div>
                        </div>
                    </article>
                </section>

                <!-- 3. Restocking Recommendations Table (Full Width) -->
                <section class="prescriptive-dashboard-card" style="margin-bottom: 18px;">
                    <div class="dashboard-card-header">
                        <div>
                            <h3><i class="fa-solid fa-list-check"></i> Spare-Parts Replenishment Recommendations</h3>
                            <p class="dashboard-eyebrow">Recommended order volumes calculated from available stock, Job Order reservations, and supplier lead times</p>
                        </div>
                        <span class="dashboard-badge">{{ collect($restockingData->restock_items)->count() }} Parts</span>
                    </div>

                    <div class="prescriptive-table-container">
                        <table class="prescriptive-table">
                            <thead>
                                <tr>
                                    <th>Part Code</th>
                                    <th>Part Name &amp; Category</th>
                                    <th>Available Stock</th>
                                    <th>Est. Monthly Demand</th>
                                    <th>Incoming Supply</th>
                                    <th>Suggested Restock Qty</th>
                                    <th>Priority</th>
                                    <th>Projected Shortage Risk</th>
                                    <th>Suggested Action &amp; Rationale</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($restockingData->restock_items as $item)
                                    <tr>
                                        <td>
                                            <span class="code-pill">
                                                <i class="fa-solid fa-barcode"></i> {{ $item->part_code }}
                                            </span>
                                        </td>
                                        <td>
                                            <strong style="color:#0f172a;">{{ $item->part_name }}</strong>
                                            <br><small style="color:#64748b;">{{ $item->category }}</small>
                                        </td>
                                        <td>
                                            @if($item->available_stock <= 0)
                                                <span class="stock-depleted-badge"><i class="fa-solid fa-triangle-exclamation"></i> 0 pcs</span>
                                            @else
                                                <strong style="font-size:13px; color:#0f172a;">
                                                    {{ $item->available_stock }} pcs
                                                </strong>
                                            @endif
                                        </td>
                                        <td>
                                            <span style="font-weight:600; color:#475569;">{{ $item->monthly_demand }} units</span>
                                        </td>
                                        <td>
                                            @if($item->incoming_supply > 0)
                                                <span class="dashboard-badge" style="background:#eff6ff; color:#1d4ed8;">
                                                    <i class="fa-solid fa-truck"></i> +{{ $item->incoming_supply }} in transit
                                                </span>
                                            @else
                                                <span style="color:#94a3b8; font-size:11px;">0 pending</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="restock-qty-pill">+{{ $item->suggested_qty }}</span>
                                        </td>
                                        <td>
                                            <span class="badge-priority {{ $item->priority_badge }}">
                                                {{ $item->priority }}
                                            </span>
                                        </td>
                                        <td>
                                            <small style="font-weight:700; color: {{ $item->priority === 'Critical' ? '#dc2626' : ($item->priority === 'High' ? '#ea580c' : '#2563eb') }};">
                                                {{ $item->shortage_risk }}
                                            </small>
                                        </td>
                                        <td style="max-width: 320px;">
                                            <div class="action-cell-card">
                                                <i class="fa-solid fa-arrow-right-long"></i>
                                                <span>{{ $item->suggested_action }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" style="text-align:center; padding:32px; color:#64748b;">
                                            <i class="fa-solid fa-circle-check" style="font-size:24px; margin-bottom:8px; display:block; color:#16a34a;"></i>
                                            All inventory items are currently above safety stock and reorder levels.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- 4. Key Recommendations & Action Playbooks -->
                <section class="prescriptive-dashboard-card" style="margin-bottom: 18px;">
                    <div class="dashboard-card-header">
                        <div>
                            <h3><i class="fa-solid fa-lightbulb"></i> Strategic Restocking Playbooks</h3>
                            <p class="dashboard-eyebrow">Actionable inventory batching and procurement guidance for Warehouse Head review</p>
                        </div>
                        <span class="dashboard-badge">Playbooks</span>
                    </div>

                    <div class="playbook-list">
                        @foreach($restockingData->key_recommendations as $playbook)
                            <div class="playbook-card {{ $playbook->badge }}">
                                <div class="playbook-info">
                                    <h4>
                                        <span class="badge-priority {{ $playbook->badge }}">{{ $playbook->severity }}</span>
                                        {{ $playbook->title }}
                                    </h4>
                                    <p>{{ $playbook->summary }}</p>
                                </div>
                                <span class="action-btn-pill">
                                    <i class="fa-solid fa-file-pen"></i> {{ $playbook->action_label }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </section>

                <!-- 5. Restocking Timeline & Action Plan -->
                <section class="prescriptive-dashboard-card" style="margin-bottom: 18px;">
                    <div class="dashboard-card-header">
                        <div>
                            <h3><i class="fa-solid fa-timeline"></i> Restocking Timeline &amp; Approval Action Plan</h3>
                            <p class="dashboard-eyebrow">Multi-stage procurement workflow from Warehouse verification to stock receipt</p>
                        </div>
                        <span class="dashboard-badge">Standard Workflow</span>
                    </div>

                    <div class="timeline-stepper">
                        @foreach($restockingData->timeline_plan as $step)
                            <div class="timeline-step-item">
                                <span class="timeline-step-badge">{{ $step->step }}</span>
                                <h5>{{ $step->title }}</h5>
                                <p>{{ $step->desc }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>

                <!-- 6. Warehouse Approval Safeguards Card -->
                <div class="prescriptive-safeguard-card">
                    <h4><i class="fa-solid fa-boxes-stacked"></i> Warehouse Head Inventory Safeguards &amp; Policy</h4>
                    <p>
                        In strict compliance with GCT-Systems business workflow, no automated Purchase Requisition (PR) or Purchase Order (PO) is generated by analytics. The Warehouse Head must manually verify parts against open Maintenance Job Orders and electronically sign the PR submission.
                    </p>
                </div>
            @endif

        </main>
    </div>
</x-layout.app>
