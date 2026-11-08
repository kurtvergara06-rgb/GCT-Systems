@php
    $prescriptiveUrl = route('analytics.stage', ['stage' => 'prescriptive'], false);
    $activeDomain = in_array(request('domain'), ['inventory', 'restocking'], true) ? 'inventory' : 'fleet-trip';
    $isRestocking = $activeDomain === 'inventory';

    $operationsData = $operationsPrescriptive ?? $prescriptive?->operations;
    $restockingData = $restockingPrescriptive ?? $prescriptive?->restocking;

    $isDemo = $isRestocking ? ($restockingData->is_demo ?? true) : ($operationsData->is_demo ?? true);

    $title = 'Prescriptive Analytics';
    $subtitle = 'Operations scheduling and inventory restocking recommendations · Objective 6.4.';

    $periodOptions = [
        'this-month' => 'This Month',
        'this-week' => 'This Week',
        'last-30-days' => 'Last 30 Days',
        'last-90-days' => 'Last 90 Days',
        'last-12-months' => 'Last 12 Months',
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
            'coverageReorderPoint' => $restockingData->stock_coverage->reorder_point ?? [],
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
        'resources/css/Admin/Analytics/prescriptive/prescriptive-rebuild.css',
        'resources/css/Admin/Analytics/model-status-stage.css',
        'resources/css/Admin/Analytics/design-system.css',
        'resources/js/Admin/Analytics/prescriptive/prescriptive-analytics.js'
    ]"
    :partial-navigation="false"
>
    <div class="app">
        <x-layout.sidebar department="Admin" />
        <main class="main analytics-stage-page prescriptive-rebuild">
            <x-layout.topbar
                :title="$title"
                :subtitle="$subtitle"
            />

            <!-- Hidden JSON payload consumed by Chart.js script -->
            <script type="application/json" id="prescriptiveAnalyticsData">
                {!! json_encode($chartPayload) !!}
            </script>

            <x-analytics.stage-controls
                :url="$prescriptiveUrl"
                :tabs="[
                    'fleet-trip' => ['Operations Scheduling', 'fa-bus'],
                    'inventory' => ['Inventory Restocking', 'fa-boxes-stacked'],
                ]"
                :active="$activeDomain"
                :period="$period"
                :period-options="$periodOptions"
                :bus-options="$busOptions"
                :selected-bus="$selectedBus"
                :show-bus="! $isRestocking"
                :date-label="ucwords(str_replace('-', ' ', $period))"
                period-label="Planning Period"
            />
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
                        label="Active Driver Candidates"
                        value="{{ $operationsData->eligible_drivers_count }}"
                        small="Attendance and rest require review"
                        icon="fa-user-check"
                        color="green"
                        href="{{ route('driver-attendance') }}"
                    />

                    <!-- Available Buses -->
                    <x-ui.summary-card
                        label="Active Bus Candidates"
                        value="{{ $operationsData->available_buses_count }}"
                        small="Active status · no open Job Orders"
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
                                <h3><i class="fa-solid fa-clock"></i> Departure Time Distribution</h3>
                                <p class="dashboard-eyebrow">Scheduled and unassigned trips grouped by actual departure time</p>
                            </div>
                            <span class="dashboard-badge">Departure Times</span>
                        </div>
                        <div class="prescriptive-chart-wrap">
                            <canvas id="prescriptiveShiftChart"></canvas>
                        </div>
                    </article>

                    <!-- Resource Readiness & Dispatch Status -->
                    <article class="prescriptive-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <h3><i class="fa-solid fa-chart-column"></i> Resource &amp; Dispatch Signals</h3>
                                <p class="dashboard-eyebrow">Independent counts; categories may overlap and do not imply dispatch clearance</p>
                            </div>
                            <span class="dashboard-badge">Dispatch Status</span>
                        </div>
                        <div class="prescriptive-chart-wrap">
                            <canvas id="prescriptiveReadinessDonutChart"></canvas>
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
                            <p class="dashboard-eyebrow">Recorded departure times and candidate resources; final timing and assignments require Operations approval</p>
                        </div>
                        <span class="dashboard-badge">{{ collect($operationsData->schedule_recommendations)->count() }} Trips</span>
                    </div>

                    <div class="prescriptive-table-container">
                        <table class="prescriptive-table">
                            <thead>
                                <tr>
                                    <th>Trip Code</th>
                                    <th>Route &amp; Corridor</th>
                                    <th>Trip Date</th>
                                    <th>Recorded Departure</th>
                                    <th>Est. Arrival</th>
                                    <th>Duration</th>
                                    <th>Recommended Unit</th>
                                    <th>Recommended Driver</th>
                                    <th>Review Required</th>
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
                                            <span class="dashboard-badge">{{ $rec->trip_date }}</span>
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
                                                <i class="fa-solid fa-circle-info" style="color:#2563eb;"></i> {{ $rec->reason }}
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
                                <h3><i class="fa-solid fa-id-card"></i> Active Driver Candidates</h3>
                                <p class="dashboard-eyebrow">Employment status is active; attendance, rest, and authorization remain unverified here</p>
                            </div>
                            <span class="dashboard-badge">{{ collect($operationsData->driver_recommendations)->count() }} Candidates</span>
                        </div>

                        <div class="prescriptive-table-container scrollable-body candidate-table-wrap">
                            <table class="prescriptive-table candidate-table candidate-table--drivers">
                                <thead>
                                    <tr>
                                        <th>Driver &amp; Authorization</th>
                                        <th>Attendance</th>
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
                                                <span class="badge-status warning">{{ $driver->attendance_status }}</span>
                                            </td>
                                            <td>
                                                <span class="code-pill">{{ $driver->duty_hours }}</span>
                                            </td>
                                            <td>
                                                <span class="badge-status warning"><i class="fa-solid fa-circle-info"></i> {{ $driver->eligibility }}</span>
                                                <small style="color:#475569; display:block; line-height:1.35; margin-top:3px;">{{ $driver->reason }}</small>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" style="text-align:center; padding:24px; color:#64748b;">
                                                No active drivers found for candidate review.
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
                                <h3><i class="fa-solid fa-bus-simple"></i> Active Bus Candidates</h3>
                                <p class="dashboard-eyebrow">Active units without open Job Orders; PMS and pre-trip checks remain separate</p>
                            </div>
                            <span class="dashboard-badge">{{ collect($operationsData->bus_recommendations)->count() }} Candidates</span>
                        </div>

                        <div class="prescriptive-table-container scrollable-body">
                            <table class="prescriptive-table candidate-table candidate-table--buses">
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
                                                <span class="dashboard-badge">{{ $bus->capacity > 0 ? $bus->capacity . ' Seats' : 'Not recorded' }}</span>
                                            </td>
                                            <td>
                                                <small style="color:#475569; display:block; line-height:1.35;">{{ $bus->reason }}</small>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" style="text-align:center; padding:24px; color:#64748b;">
                                                No active buses without open Job Orders found.
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
                    <h4><i class="fa-solid fa-user-shield"></i> Operations Scheduling Safeguards &amp; Review Requirements</h4>
                    <p>
                        These checks support Operations review. Confirm attendance, duty hours, route spacing, and bus clearance before approving assignments:
                    </p>
                    <div class="prescriptive-safeguard-grid">
                        <div class="safeguard-pill-item">
                            <i class="fa-solid fa-arrows-left-right"></i>
                            <div>
                                <strong>15-Minute Route Headway</strong>
                                <small>Identifies departures on the same route scheduled less than 15 minutes apart for Operations review.</small>
                            </div>
                        </div>
                        <div class="safeguard-pill-item">
                            <i class="fa-solid fa-business-time"></i>
                            <div>
                                <strong>Driver Duty &amp; Rest Verification</strong>
                                <small>Requires confirmation of driver attendance, duty hours, and rest eligibility before assignment.</small>
                            </div>
                        </div>
                        <div class="safeguard-pill-item">
                            <i class="fa-solid fa-wrench"></i>
                            <div>
                                <strong>Maintenance &amp; PMS Verification</strong>
                                <small>Screens active bus status and open Job Orders; final PMS and dispatch eligibility require Operations confirmation.</small>
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
                        small="Zero on-hand units recorded"
                        icon="fa-circle-xmark"
                        color="red"
                        href="{{ route('inventory') }}"
                    />

                    <!-- Recommended Restock Qty -->
                    <x-ui.summary-card
                        label="Threshold Gap Units"
                        value="{{ number_format($restockingData->recommended_restock_qty) }}"
                        small="Planning estimate; confirm demand and incoming stock"
                        icon="fa-cart-shopping"
                        color="purple"
                    />
                </section>

                <!-- 2. Analytical Charts Grid (3 Columns) -->
                @if(collect($restockingData->restock_items)->isNotEmpty())
                <section class="prescriptive-three-grid">
                    <!-- Recommended Quantity by Category -->
                    <article class="prescriptive-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <h3><i class="fa-solid fa-chart-column"></i> Threshold Gap by Category</h3>
                                <p class="dashboard-eyebrow">{{ $isDemo ? 'Demonstration replenishment volumes' : 'Threshold gaps grouped by recorded part category' }}</p>
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
                                <h3><i class="fa-solid fa-chart-line"></i> Available Stock vs Reorder Point</h3>
                                <p class="dashboard-eyebrow">Available stock, reorder points, and advisory replenishment gaps</p>
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
                @else
                    <section class="prescriptive-dashboard-card analytics-stage-empty" role="status">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        <div><h3>No restocking threshold gaps</h3><p>Recorded items are above their configured reorder points. No replenishment chart is inferred for this selection.</p></div>
                    </section>
                @endif

                <!-- 3. Restocking Recommendations Table (Full Width) -->
                <section class="prescriptive-dashboard-card" style="margin-bottom: 18px;">
                    <div class="dashboard-card-header">
                        <div>
                            <h3><i class="fa-solid fa-list-check"></i> Spare-Parts Replenishment Recommendations</h3>
                            <p class="dashboard-eyebrow">Advisory quantities use reorder points, available stock after JO reservations, and explicitly coded pending PO quantities; confirm uncoded PO lines before ordering</p>
                        </div>
                        <span class="dashboard-badge">{{ collect($restockingData->restock_items)->count() }} Parts</span>
                    </div>

                    <div class="prescriptive-table-container">
                        <table class="prescriptive-table">
                            <thead>
                                <tr>
                                    <th>Part Code</th>
                                    <th>Part Name &amp; Category</th>
                                    <th>Available After Reservations</th>
                                    <th>Monthly Demand</th>
                                    <th>Incoming Supply</th>
                                    <th>Suggested Qty</th>
                                    <th>Priority</th>
                                    <th>Recorded Stock State</th>
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
                                            <span style="font-weight:600; color:#475569;">{{ $item->monthly_demand !== null ? $item->monthly_demand . ' units' : 'Not measured' }}</span>
                                        </td>
                                        <td>
                                            @if($item->incoming_supply === null)
                                                <span style="color:#64748b; font-size:11px;">Not verified</span>
                                            @elseif($item->incoming_supply > 0)
                                                <span class="dashboard-badge" style="background:#eff6ff; color:#1d4ed8;">
                                                    <i class="fa-solid fa-truck"></i> +{{ $item->incoming_supply }} in transit
                                                </span>
                                            @else
                                                <span style="color:#94a3b8; font-size:11px;">0 coded pending</span>
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
                                            No inventory item is at or below its configured reorder point.
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
                            <p class="dashboard-eyebrow">{{ $isDemo ? 'Demonstration scenarios for Warehouse Head review' : 'Recorded parts ranked by threshold gap for Warehouse Head review' }}</p>
                        </div>
                        <span class="dashboard-badge">Playbooks</span>
                    </div>

                    <div class="playbook-list">
                        @forelse($restockingData->key_recommendations as $playbook)
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
                        @empty
                            <p class="analytics-stage-empty__text">No recorded part needs a restocking review at its current reorder point.</p>
                        @endforelse
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
