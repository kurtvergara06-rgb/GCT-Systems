@php
    $inventoryAttentionTotal = $inventoryLow + $inventoryCritical;
    $inventoryLowEndPct = min(100, $healthyPct + $lowPct);

    $getCategoryIcon = function (string $cat): string {
        $c = strtolower(trim($cat));
        if (str_contains($c, 'air') || str_contains($c, 'cool')) return 'fa-snowflake';
        if (str_contains($c, 'lubricant') || str_contains($c, 'fluid') || str_contains($c, 'oil')) return 'fa-oil-can';
        if (str_contains($c, 'electr') || str_contains($c, 'wire') || str_contains($c, 'battery')) return 'fa-bolt';
        if (str_contains($c, 'filter')) return 'fa-filter';
        if (str_contains($c, 'engine')) return 'fa-gears';
        if (str_contains($c, 'body') || str_contains($c, 'trim') || str_contains($c, 'paint')) return 'fa-truck-front';
        if (str_contains($c, 'tire') || str_contains($c, 'wheel')) return 'fa-circle-dot';
        if (str_contains($c, 'brake')) return 'fa-circle-stop';
        if (str_contains($c, 'suspension') || str_contains($c, 'chassis')) return 'fa-shield-halved';
        return 'fa-boxes-stacked';
    };
@endphp

{{-- 1. TOP EXECUTIVE KPI STRIP --}}
<section class="analytics-kpi-strip analytics-domain-kpi-four inventory-kpi-strip" aria-label="Inventory summary KPIs">
    <div class="analytics-kpi-card tone-blue">
        <div class="analytics-kpi-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
        <div>
            <span>Total Catalogued Parts</span>
            <strong>{{ number_format($inventoryTotal) }}</strong>
            <small>Active inventory records</small>
        </div>
    </div>

    <div class="analytics-kpi-card tone-green">
        <div class="analytics-kpi-icon"><i class="fa-solid fa-box-open"></i></div>
        <div>
            <span>Well Stocked</span>
            <strong>{{ number_format($inventoryHealthy) }}</strong>
            <small class="positive">{{ number_format($healthyPct, 1) }}% buffer compliance</small>
        </div>
    </div>

    <div class="analytics-kpi-card tone-yellow">
        <div class="analytics-kpi-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div>
            <span>Low Stock Warning</span>
            <strong>{{ number_format($inventoryLow) }}</strong>
            <small class="negative">Below safety reorder point</small>
        </div>
    </div>

    <div class="analytics-kpi-card tone-red">
        <div class="analytics-kpi-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
        <div>
            <span>Out of Stock</span>
            <strong>{{ number_format($inventoryCritical) }}</strong>
            <small class="negative">Immediate procurement needed</small>
        </div>
    </div>
</section>

{{-- 2. MID SECTION: DONUT/HEALTH SCORE + CATEGORY BREAKDOWN --}}
<section class="inventory-dashboard-grid">
    <div class="inventory-dashboard-col">
        {{-- Stock-Level Distribution Donut --}}
        <article class="analytics-card inventory-panel inventory-distribution-card">
            <div class="inventory-card-header">
                <div>
                    <h3>Stock-Level Distribution <i class="fa-solid fa-chart-pie"></i></h3>
                    <p>Current warehouse stock safety distribution against reorder levels.</p>
                </div>
                <span class="inventory-card-badge">{{ number_format($inventoryTotal) }} Items</span>
            </div>

            <div class="inventory-distribution-body">
                <div class="inventory-donut-shell">
                    <div
                        class="inventory-status-donut"
                        style="--inventory-healthy: {{ number_format($healthyPct, 2, '.', '') }}%; --inventory-low-end: {{ number_format($inventoryLowEndPct, 2, '.', '') }}%;"
                        aria-label="Inventory stock-level distribution"
                    >
                        <div class="inventory-status-donut-center">
                            <strong>{{ number_format($inventoryTotal) }}</strong>
                            <span>Total Items</span>
                        </div>
                    </div>
                </div>

                <div class="inventory-distribution-legend">
                    <div class="inventory-legend-row tone-green">
                        <span><i></i>Well Stocked</span>
                        <strong>{{ number_format($inventoryHealthy) }} <small>({{ number_format($healthyPct, 1) }}%)</small></strong>
                    </div>
                    <div class="inventory-legend-row tone-yellow">
                        <span><i></i>Low Stock</span>
                        <strong>{{ number_format($inventoryLow) }} <small>({{ number_format($lowPct, 1) }}%)</small></strong>
                    </div>
                    <div class="inventory-legend-row tone-red">
                        <span><i></i>Out of Stock</span>
                        <strong>{{ number_format($inventoryCritical) }} <small>({{ number_format($criticalPct, 1) }}%)</small></strong>
                    </div>
                </div>
            </div>

            {{-- Fleet Stock Health Compliance Bar --}}
            <div class="inventory-health-compliance">
                <div class="compliance-header">
                    <span><i class="fa-solid fa-shield-heart"></i> Overall Fleet Parts Health Score</span>
                    <strong class="{{ $healthyPct >= 80 ? 'positive' : ($healthyPct >= 60 ? 'warning' : 'negative') }}">{{ number_format($healthyPct, 1) }}%</strong>
                </div>
                <div class="compliance-track">
                    <span style="width: {{ $healthyPct }}%;"></span>
                </div>
                <div class="compliance-footer">
                    @if($inventoryCritical > 0)
                        <span class="compliance-status danger"><i class="fa-solid fa-circle-exclamation"></i> {{ $inventoryCritical }} parts depleted to zero</span>
                    @elseif($inventoryLow > 0)
                        <span class="compliance-status warning"><i class="fa-solid fa-triangle-exclamation"></i> {{ $inventoryLow }} parts below safety margin</span>
                    @else
                        <span class="compliance-status safe"><i class="fa-solid fa-check"></i> All parts within safe buffer</span>
                    @endif
                    <small>{{ $inventoryTotal - $inventoryAttentionTotal }} / {{ $inventoryTotal }} safe parts</small>
                </div>
            </div>
        </article>

        {{-- Restock Exposure Summary --}}
        <article class="analytics-card inventory-panel inventory-restock-panel">
            <div class="inventory-card-header">
                <div>
                    <h3>Restock Exposure & Procurement Readiness <i class="fa-solid fa-truck-ramp-box"></i></h3>
                    <p>Current replenishment urgency across warehouse inventory</p>
                </div>
                <span class="inventory-card-badge {{ $inventoryAttentionTotal > 0 ? 'warning' : 'safe' }}">
                    {{ $inventoryAttentionTotal }} Items at Risk
                </span>
            </div>

            <div class="inventory-restock-list">
                <div class="inventory-restock-row {{ $inventoryCritical > 0 ? 'danger' : 'safe' }}">
                    <span class="inventory-restock-icon"><i class="fa-solid fa-circle-xmark"></i></span>
                    <div>
                        <strong>Unavailable Parts (Out of Stock)</strong>
                        <small>Zero on-hand units available for scheduled or emergency bus repairs</small>
                    </div>
                    <b>{{ number_format($inventoryCritical) }}</b>
                </div>

                <div class="inventory-restock-row {{ $inventoryLow > 0 ? 'warning' : 'safe' }}">
                    <span class="inventory-restock-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
                    <div>
                        <strong>Threshold Breached (Low Stock)</strong>
                        <small>Parts currently at or below minimum operating reorder buffer</small>
                    </div>
                    <b>{{ number_format($inventoryLow) }}</b>
                </div>
            </div>

            <div class="inventory-restock-actions">
                <a href="{{ route('part-requests') }}" class="inventory-action-btn primary">
                    <i class="fa-solid fa-file-circle-plus"></i> Create Part Request
                </a>
                <a href="{{ route('inventory') }}" class="inventory-action-btn secondary">
                    <i class="fa-solid fa-boxes-stacked"></i> View Inventory Master
                </a>
            </div>
        </article>
    </div>

    <div class="inventory-dashboard-col">
        {{-- Category Stock Health Breakdown --}}
        <article class="analytics-card inventory-panel inventory-category-card">
            <div class="inventory-card-header">
                <div>
                    <h3>Category Stock Health Breakdown <i class="fa-solid fa-layer-group"></i></h3>
                    <p>Parts volume and safety threshold compliance grouped by vehicle subsystem</p>
                </div>
                <span class="inventory-card-badge">{{ count($inventoryCategoryBreakdown) }} Subsystems</span>
            </div>

            <div class="inventory-category-list">
                @forelse($inventoryCategoryBreakdown as $category)
                    <div class="inventory-category-row">
                        <div class="category-info-col">
                            <span class="category-icon-box">
                                <i class="fa-solid {{ $getCategoryIcon($category->category) }}"></i>
                            </span>
                            <div class="category-titles">
                                <strong>{{ $category->category }}</strong>
                                <small>{{ $category->total }} catalogued &middot; {{ number_format($category->total_units) }} units on hand</small>
                            </div>
                        </div>

                        <div class="category-health-col">
                            <div class="category-health-meta">
                                @if($category->at_risk > 0)
                                    <span class="cat-badge warning">{{ $category->at_risk }} at risk</span>
                                @else
                                    <span class="cat-badge safe"><i class="fa-solid fa-check"></i> 100% Safe</span>
                                @endif
                                <strong class="category-pct">{{ number_format($category->health_pct, 0) }}%</strong>
                            </div>
                            <div class="category-health-track">
                                <span class="{{ $category->health_pct < 60 ? 'danger' : ($category->health_pct < 85 ? 'warning' : 'safe') }}" style="width: {{ $category->health_pct }}%;"></span>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="category-empty-note">No inventory categories available.</p>
                @endforelse
            </div>
        </article>
    </div>
</section>

{{-- 3. CRITICAL & THRESHOLD BREACH INVENTORY TABLE --}}
<section class="inventory-table-section">
    <article class="analytics-card inventory-panel inventory-table-card">
        <div class="inventory-card-header">
            <div>
                <h3>Critical & Threshold Breach Inventory Items <i class="fa-solid fa-triangle-exclamation"></i></h3>
                <p>Specific parts requiring procurement review or replenishment to avoid fleet grounding</p>
            </div>
            <div class="inventory-card-actions">
                <span class="inventory-card-badge danger">{{ count($inventoryAtRiskItems) }} Items Requiring Restock</span>
                <a href="{{ route('inventory') }}" class="table-link-btn">
                    Open Inventory Manager <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </div>

        @if(count($inventoryAtRiskItems) > 0)
            <div class="inventory-table-responsive">
                <table class="inventory-atrisk-table">
                    <thead>
                        <tr>
                            <th>Part / Description</th>
                            <th>Subsystem</th>
                            <th>Storage Location</th>
                            <th>Stock vs. Threshold</th>
                            <th>Shortfall</th>
                            <th>Stock Status</th>
                            <th>Primary Supplier</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($inventoryAtRiskItems as $item)
                            @php
                                $isCritical = $item->on_hand <= 0;
                                $shortfall = max(0, $item->reorder_level - $item->on_hand);
                                $unit = $item->unit ?: $item->unit_of_measurement ?: 'pcs';
                                $pct = $item->reorder_level > 0 ? min(100, round(($item->on_hand / $item->reorder_level) * 100)) : 0;
                            @endphp
                            <tr class="{{ $isCritical ? 'row-critical' : 'row-warning' }}">
                                <td class="part-name-cell">
                                    <div class="part-info">
                                        <strong>{{ $item->item_name ?: $item->parts_name }}</strong>
                                        <code>{{ $item->item_code }}</code>
                                    </div>
                                </td>
                                <td>
                                    <span class="category-pill">
                                        <i class="fa-solid {{ $getCategoryIcon($item->category) }}"></i>
                                        {{ $item->category }}
                                    </span>
                                </td>
                                <td>
                                    <span class="location-badge">
                                        <i class="fa-solid fa-location-dot"></i>
                                        {{ $item->storage_location ?: ($item->location ?: 'Warehouse A') }}
                                    </span>
                                </td>
                                <td>
                                    <div class="stock-ratio-cell">
                                        <strong>{{ number_format($item->on_hand) }} <small>/ {{ number_format($item->reorder_level) }} {{ $unit }}</small></strong>
                                        <div class="mini-buffer-track">
                                            <span class="{{ $isCritical ? 'danger' : 'warning' }}" style="width: {{ $pct }}%;"></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($shortfall > 0)
                                        <span class="shortfall-badge {{ $isCritical ? 'critical' : 'warning' }}">
                                            -{{ number_format($shortfall) }} {{ $unit }}
                                        </span>
                                    @else
                                        <span class="shortfall-zero">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($isCritical)
                                        <span class="status-pill out-of-stock">
                                            <i class="pulse-dot"></i> Out of Stock
                                        </span>
                                    @else
                                        <span class="status-pill low-stock">
                                            <i class="warning-dot"></i> Low Stock
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <span class="supplier-text">
                                        {{ $item->supplier ?: 'Standard Fleet Vendor' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="inventory-empty-state">
                <i class="fa-solid fa-circle-check"></i>
                <h4>All Inventory Items Within Safe Thresholds</h4>
                <p>No catalogued items are currently operating at or below their reorder points.</p>
            </div>
        @endif
    </article>
</section>
