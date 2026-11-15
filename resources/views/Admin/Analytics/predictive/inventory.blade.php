@php
    $inventoryPredict = $predictive->inventory;
    $totalStock = $inventoryPredict->total ?? 0;
    $healthyCount = $inventoryPredict->healthy ?? 0;
    $lowCount = $inventoryPredict->low ?? 0;
    $criticalCount = $inventoryPredict->critical ?? 0;
    $priorityRows = collect($inventoryPredict->rows ?? [])->take(4);

    $safeTotal = max(1, $totalStock);
    $healthyPct = round(($healthyCount / $safeTotal) * 100, 1);
    $lowPct = round(($lowCount / $safeTotal) * 100, 1);
    $criticalPct = round(($criticalCount / $safeTotal) * 100, 1);

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
        return 'fa-boxes-stacked';
    };
@endphp

<div class="predictive-page predictive-inventory-page">

    {{-- AI PREDICTIVE INSIGHT BANNER --}}
    <div class="predictive-ai-banner">
        <div class="predictive-ai-banner__icon-wrap">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
        </div>
        <div class="predictive-ai-banner__content">
            <div class="predictive-ai-banner__top">
                <span class="ai-chip">AI Reorder Forecast</span>
                <span class="ai-status-pulse">
                    <span class="pulse-dot"></span>
                    @if($criticalCount > 0)
                        Active Fleet Risk: Grounding Potential
                    @elseif($lowCount > 0)
                        Moderate Depletion Warning
                    @else
                        Fleet Stock Reserve Optimal
                    @endif
                </span>
            </div>
            <p class="predictive-ai-banner__text">
                @if($criticalCount > 0)
                    <strong>{{ $criticalCount }} items have depleted to zero stock</strong> and <strong>{{ $lowCount }} items</strong> are operating below safe threshold. Projections indicate fleet maintenance delays within <strong>48 to 72 hours</strong> unless emergency purchase requisitions are dispatched.
                @elseif($lowCount > 0)
                    <strong>{{ $lowCount }} items</strong> have breached safety reorder thresholds. Procurement orders should be initiated within 5 business days to match supplier lead times.
                @else
                    All registered inventory items remain comfortably above reorder points. Operating reserves are sufficient for ongoing dispatch and PMS schedules.
                @endif
            </p>
        </div>
        <div class="predictive-ai-banner__action">
            <a href="{{ route('warehouse.dashboard') }}" class="btn-ai-reorder">
                <i class="fa-solid fa-boxes-packing"></i>
                <span>Manage Warehouse</span>
            </a>
        </div>
    </div>

    {{-- KPI STRIP --}}
    <section class="analytics-kpi-strip">
        @foreach($inventoryPredict->kpis as $kpi)
            <x-analytics.kpi
                :label="$kpi['label']"
                :value="$kpi['value']"
                :description="$kpi['caption']"
                :icon="$kpi['icon']"
                :icon-variant="match ($kpi['tone']) {
                    'danger' => 'red',
                    'warning', 'orange' => 'yellow',
                    'success' => 'green',
                    'info' => 'blue',
                    default => 'blue',
                }"
            />
        @endforeach
    </section>

    {{-- MAIN GRID --}}
    <section class="predictive-main-grid-two">

        {{-- STOCK LEVEL DISTRIBUTION CARD --}}
        <x-analytics.card class="predictive-card forecast-card inventory-distribution-card" title="Stock Level Distribution" description="Proportion of on-hand inventory relative to safety reorder thresholds.">
            <div class="risk-content inventory-donut-content">
                <div class="donut-wrapper">
                    <canvas id="inventoryDonut"></canvas>
                    <div class="donut-center">
                        <strong id="inventoryDonutTotal">{{ $totalStock }}</strong>
                        <span>Total<br>Items</span>
                    </div>
                </div>

                <div class="risk-legend inventory-legend-enhanced">
                    <div class="legend-row">
                        <div class="legend-header">
                            <span class="legend-dot low"></span>
                            <span>Well Stocked</span>
                        </div>
                        <div class="legend-numbers">
                            <strong>{{ $healthyCount }}</strong>
                            <span class="legend-pct">({{ $healthyPct }}%)</span>
                        </div>
                    </div>
                    <div class="legend-row">
                        <div class="legend-header">
                            <span class="legend-dot medium"></span>
                            <span>Low Stock</span>
                        </div>
                        <div class="legend-numbers">
                            <strong>{{ $lowCount }}</strong>
                            <span class="legend-pct">({{ $lowPct }}%)</span>
                        </div>
                    </div>
                    <div class="legend-row">
                        <div class="legend-header">
                            <span class="legend-dot high"></span>
                            <span>Out of Stock</span>
                        </div>
                        <div class="legend-numbers">
                            <strong>{{ $criticalCount }}</strong>
                            <span class="legend-pct">({{ $criticalPct }}%)</span>
                        </div>
                    </div>

                    {{-- Distribution Visual Ratio Bar --}}
                    <div class="inventory-ratio-bar-wrap">
                        <span class="ratio-label">Safety Coverage Ratio</span>
                        <div class="inventory-ratio-bar" title="{{ $healthyCount }} Well Stocked, {{ $lowCount }} Low, {{ $criticalCount }} Out">
                            <div class="ratio-segment ratio-healthy" style="width: {{ $healthyPct }}%"></div>
                            <div class="ratio-segment ratio-low" style="width: {{ $lowPct }}%"></div>
                            <div class="ratio-segment ratio-critical" style="width: {{ $criticalPct }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </x-analytics.card>

        {{-- RUNOUT HORIZON TIMELINE CARD --}}
        <x-analytics.card class="predictive-card health-issues-card inventory-horizon-card" title="Projected Stockout Horizon" description="Forecasted depletion timeline based on consumption velocity and reorder thresholds.">
            <div class="inventory-horizon-list">
                @php
                    $horizons = $inventoryPredict->horizons ?? [
                        'immediate' => (object) [
                            'label' => 'Critical (< 48 Hours)',
                            'badge' => 'CRITICAL',
                            'count' => $criticalCount,
                            'tone' => 'danger',
                            'description' => 'Zero stock; parts needed for scheduled bus PMS/trips',
                            'sample' => 'AC-REFRIG, TRS-FLUID-4L',
                        ],
                        'high' => (object) [
                            'label' => 'Depleting (3–7 Days)',
                            'badge' => 'HIGH RISK',
                            'count' => collect($inventoryPredict->rows ?? [])->filter(fn($r) => ($r[3] ?? 0) > 0 && ($r[4] ?? 0) > 0 && (($r[3] ?? 0) / ($r[4] ?? 1)) <= 0.5)->count(),
                            'tone' => 'warning',
                            'description' => 'Less than 50% safety buffer remaining',
                            'sample' => 'FLT-OIL, FLT-AIR',
                        ],
                        'restock' => (object) [
                            'label' => 'Reorder Buffer (8–14 Days)',
                            'badge' => 'RESTOCK',
                            'count' => collect($inventoryPredict->rows ?? [])->filter(fn($r) => ($r[3] ?? 0) > 0 && ($r[4] ?? 0) > 0 && (($r[3] ?? 0) / ($r[4] ?? 1)) > 0.5 && ($r[3] ?? 0) <= ($r[4] ?? 1))->count(),
                            'tone' => 'info',
                            'description' => 'Approaching reorder threshold; vendor lead time reorder needed',
                            'sample' => 'VLV-STEM, PST-BELT',
                        ],
                        'safe' => (object) [
                            'label' => 'Well Stocked (15+ Days)',
                            'badge' => 'HEALTHY',
                            'count' => $healthyCount,
                            'tone' => 'success',
                            'description' => 'Stock exceeds baseline operating requirements',
                            'sample' => '',
                        ],
                    ];
                @endphp

                @foreach($horizons as $key => $horizon)
                    <div class="horizon-row horizon-row--{{ $horizon->tone }}">
                        <div class="horizon-indicator">
                            <span class="horizon-pill horizon-pill--{{ $horizon->tone }}">{{ $horizon->badge }}</span>
                            <span class="horizon-count">{{ $horizon->count }}</span>
                        </div>
                        <div class="horizon-content">
                            <div class="horizon-headline">
                                <strong>{{ $horizon->label }}</strong>
                                @if(!empty($horizon->sample))
                                    <span class="horizon-sample-tag">Items: {{ $horizon->sample }}</span>
                                @endif
                            </div>
                            <span class="horizon-desc">{{ $horizon->description }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-analytics.card>

    </section>

    {{-- ATTENTION GRID --}}
    <section class="predictive-inventory-attention-grid">

        {{-- CATEGORY ATTENTION WITH HEALTH METERS --}}
        <x-analytics.card class="predictive-card issues-card inventory-category-card" title="Category Exposure & Risk" description="Stock health breakdown ranked by parts needing restock.">
            <div class="issue-list category-attention-list">
                @forelse($inventoryPredict->categories as $category)
                    @php
                        $catName = $category->category ?? 'General';
                        $catTotal = max(1, (int) ($category->total ?? 0));
                        $catAttn = (int) ($category->attention ?? 0);
                        $catCritical = (int) ($category->critical ?? 0);
                        $catHealthy = max(0, $catTotal - $catAttn);
                        $catHealthyPct = (int) round(($catHealthy / $catTotal) * 100);
                        $catIcon = $getCategoryIcon($catName);
                        $statusTone = $catCritical > 0 ? 'high' : ($catAttn > 0 ? 'medium' : 'low');
                        $badgeLabel = $catCritical > 0 ? 'Critical' : ($catAttn > 0 ? 'Low Stock' : 'Optimal');
                    @endphp
                    <div class="issue-row category-row">
                        <span class="issue-rank category-icon-wrap category-icon--{{ $statusTone }}">
                            <i class="fa-solid {{ $catIcon }}"></i>
                        </span>
                        <div class="issue-info category-info">
                            <div class="category-info-top">
                                <strong>{{ $catName }}</strong>
                                <span class="category-meta">{{ $category->total }} items · {{ $catAttn }} needing attention</span>
                            </div>
                            <div class="category-health-track" title="{{ $catHealthyPct }}% healthy stock">
                                <div class="category-health-fill category-health-fill--{{ $statusTone }}" style="width: {{ $catHealthyPct }}%"></div>
                            </div>
                        </div>
                        <span class="risk-badge {{ $statusTone }}">
                            {{ $badgeLabel }}
                        </span>
                    </div>
                @empty
                    <div class="issue-row">
                        <div class="issue-info">
                            <strong>No categories</strong>
                            <span>No inventory items are registered yet.</span>
                        </div>
                    </div>
                @endforelse
            </div>
        </x-analytics.card>

        {{-- REORDER PRIORITY CARD --}}
        <x-analytics.card class="predictive-card reorder-priority-card" title="Immediate Reorder Priority" description="Items requiring the most urgent replenishment.">
            <div class="inventory-priority-list">
                @forelse($priorityRows as $row)
                    @php
                        $itemCode = $row[0] ?? $row['item_code'] ?? '—';
                        $itemName = $row[1] ?? $row['name'] ?? '—';
                        $onHand = (int) ($row[3] ?? $row['on_hand'] ?? 0);
                        $reorder = (int) ($row[4] ?? $row['reorder_level'] ?? 0);
                        $gap = (int) ($row[6] ?? $row['gap'] ?? 0);
                        $riskLevel = $row[7] ?? $row['risk_level'] ?? 'Medium';
                        $runout = $row[9] ?? $row['runout'] ?? ($onHand <= 0 ? '< 24h / Grounded' : '~3–5 Days');
                        $recommendedOrder = $row[10] ?? $row['recommended_order'] ?? ($gap > 0 ? $gap + 5 : 0);
                    @endphp
                    <div class="inventory-priority-item priority--{{ strtolower($riskLevel) }}">
                        <div class="inventory-priority-item__topline">
                            <div class="item-title-group">
                                <span class="item-code-chip">{{ $itemCode }}</span>
                                <strong class="item-name">{{ $itemName }}</strong>
                            </div>
                            <span class="risk-badge {{ strtolower($riskLevel) }}">{{ $riskLevel }}</span>
                        </div>
                        <div class="inventory-priority-metrics">
                            <div class="metric-chip">
                                <span class="metric-label">On Hand:</span>
                                <strong class="{{ $onHand <= 0 ? 'color-danger' : 'color-warning' }}">{{ number_format($onHand) }} / {{ number_format($reorder) }}</strong>
                            </div>
                            <div class="metric-chip">
                                <span class="metric-label">Runout:</span>
                                <strong class="metric-runout">{{ $runout }}</strong>
                            </div>
                            <div class="metric-chip highlight">
                                <span class="metric-label">Suggest PO:</span>
                                <strong>+{{ number_format($recommendedOrder) }} pcs</strong>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="analytics-compact-empty">No inventory items require reorder attention.</div>
                @endforelse
            </div>
        </x-analytics.card>

    </section>

    {{-- PREDICTION TABLE --}}
    <x-analytics.card class="predictive-card predictions-card" title="Inventory Reorder Forecast" description="Parts ranked by stockout risk, depletion horizon, and recommended purchase quantities.">
        <div class="table-wrap predictive-inventory-table-wrap" tabindex="0" aria-label="Scrollable inventory reorder forecast table">
            <table class="predictive-table predictive-inventory-table">
                <thead>
                    <tr>
                        <th style="width: 11%;">Item Code</th>
                        <th style="width: 17%;">Item & Description</th>
                        <th style="width: 13%;">Category</th>
                        <th style="width: 17%;">Stock Buffer & Level</th>
                        <th style="width: 13%;">Runout Horizon</th>
                        <th style="width: 7%;">Gap</th>
                        <th style="width: 11%;">Suggested PO</th>
                        <th style="width: 11%;">Risk State</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($inventoryPredict->rows as $row)
                        @php
                            $code = $row[0] ?? $row['item_code'] ?? '—';
                            $name = $row[1] ?? $row['name'] ?? '—';
                            $category = $row[2] ?? $row['category'] ?? '—';
                            $onHand = (int) ($row[3] ?? $row['on_hand'] ?? 0);
                            $reorder = (int) ($row[4] ?? $row['reorder_level'] ?? 0);
                            $state = $row[5] ?? $row['state'] ?? '—';
                            $gap = (int) ($row[6] ?? $row['gap'] ?? 0);
                            $riskLevel = $row[7] ?? $row['risk_level'] ?? 'Low';
                            $bufferPct = (int) ($row[8] ?? $row['buffer_pct'] ?? ($reorder > 0 ? min(100, round(($onHand / $reorder) * 100)) : 100));
                            $runout = $row[9] ?? $row['runout'] ?? ($onHand <= 0 ? '< 24h / Grounded' : '~5–7 Days');
                            $recommendedOrder = (int) ($row[10] ?? $row['recommended_order'] ?? ($gap > 0 ? $gap + 5 : 0));
                            $bufferTone = $onHand <= 0 ? 'danger' : ($bufferPct <= 50 ? 'warning' : 'success');
                            $catIcon = $getCategoryIcon($category);
                        @endphp
                        <tr>
                            <td>
                                <span class="table-item-code-chip">{{ $code }}</span>
                            </td>
                            <td>
                                <strong class="table-item-name">{{ $name }}</strong>
                            </td>
                            <td>
                                <span class="table-category-cell">
                                    <i class="fa-solid {{ $catIcon }}"></i>
                                    {{ $category }}
                                </span>
                            </td>
                            <td>
                                <div class="table-stock-buffer-cell">
                                    <div class="buffer-text">
                                        <span class="buffer-onhand {{ $onHand <= 0 ? 'buffer-zero' : '' }}">{{ number_format($onHand) }}</span>
                                        <span class="buffer-sep">/</span>
                                        <span class="buffer-reorder">{{ number_format($reorder) }} min</span>
                                    </div>
                                    <div class="buffer-track" title="{{ $bufferPct }}% safety stock">
                                        <div class="buffer-fill buffer-fill--{{ $bufferTone }}" style="width: {{ $bufferPct }}%"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="runout-pill runout-pill--{{ $bufferTone }}">
                                    <i class="fa-regular fa-clock"></i>
                                    {{ $runout }}
                                </span>
                            </td>
                            <td>
                                <span class="table-gap {{ $gap > 0 ? 'table-gap--exposed' : '' }}">
                                    {{ $gap > 0 ? '-' . number_format($gap) : '0' }}
                                </span>
                            </td>
                            <td>
                                @if($recommendedOrder > 0)
                                    <span class="po-chip">
                                        <i class="fa-solid fa-cart-plus"></i>
                                        +{{ number_format($recommendedOrder) }}
                                    </span>
                                @else
                                    <span class="po-chip po-chip--none">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="risk-badge {{ strtolower($riskLevel) }}">{{ $riskLevel }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">No inventory items are registered yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-analytics.card>

    <p class="predictive-footer">
        <i class="fa-solid fa-circle-info"></i> Stock forecasts are derived from on-hand quantity, safety reorder thresholds, and active maintenance consumption patterns.
    </p>

</div>

<script>
    window.predictiveChartData = {
        inventory: {
            healthy: {{ $healthyCount }},
            low: {{ $lowCount }},
            critical: {{ $criticalCount }},
            total: {{ $totalStock }},
        },
    };
</script>