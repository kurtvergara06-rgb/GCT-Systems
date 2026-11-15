@php
    $inventoryAttentionTotal = $inventoryLow + $inventoryCritical;
    $inventoryLowEndPct = min(100, $healthyPct + $lowPct);
    $inventoryComparisonRows = collect([
        (object) [
            'label' => 'Well Stocked',
            'value' => $inventoryHealthy,
            'display' => number_format($inventoryHealthy) . ' (' . number_format($healthyPct) . '%)',
        ],
        (object) [
            'label' => 'Low Stock',
            'value' => $inventoryLow,
            'display' => number_format($inventoryLow) . ' (' . number_format($lowPct) . '%)',
        ],
        (object) [
            'label' => 'Out of Stock',
            'value' => $inventoryCritical,
            'display' => number_format($inventoryCritical) . ' (' . number_format($criticalPct) . '%)',
        ],
    ]);
@endphp

<section class="analytics-kpi-strip analytics-domain-kpi-four inventory-kpi-strip">
    <x-analytics.kpi label="Total Items" :value="$inventoryTotal" description="Inventory records" icon="fa-boxes-stacked" />
    <x-analytics.kpi label="Well Stocked" :value="$inventoryHealthy" description="Above reorder threshold" icon="fa-box-open" tone="green" />
    <x-analytics.kpi label="Low Stock" :value="$inventoryLow" description="At or below reorder level" icon="fa-triangle-exclamation" tone="yellow" />
    <x-analytics.kpi label="Out of Stock" :value="$inventoryCritical" description="No on-hand stock" icon="fa-circle-exclamation" tone="red" />
</section>

<section class="inventory-reference-layout">
    <div class="inventory-reference-main">
        <x-analytics.panel
            class="inventory-panel inventory-distribution-panel"
            title="Stock-Level Distribution"
            description="Current stock status across inventory records"
            :badge="$inventoryTotal . ' items'"
        >
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
                        <strong>{{ number_format($inventoryHealthy) }} <small>({{ number_format($healthyPct) }}%)</small></strong>
                    </div>
                    <div class="inventory-legend-row tone-yellow">
                        <span><i></i>Low Stock</span>
                        <strong>{{ number_format($inventoryLow) }} <small>({{ number_format($lowPct) }}%)</small></strong>
                    </div>
                    <div class="inventory-legend-row tone-red">
                        <span><i></i>Out of Stock</span>
                        <strong>{{ number_format($inventoryCritical) }} <small>({{ number_format($criticalPct) }}%)</small></strong>
                    </div>
                </div>
            </div>
        </x-analytics.panel>

        <x-analytics.panel
            class="inventory-panel inventory-restock-panel"
            title="Restock Exposure"
            description="Items currently at or below the reorder threshold"
        >
            <div class="inventory-restock-list">
                <div class="inventory-restock-row warning">
                    <span class="inventory-restock-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
                    <div>
                        <strong>Items requiring stock attention</strong>
                        <small>Low-stock plus out-of-stock inventory records</small>
                    </div>
                    <b>{{ number_format($inventoryAttentionTotal) }}</b>
                </div>

                <div class="inventory-restock-row danger">
                    <span class="inventory-restock-icon"><i class="fa-solid fa-circle-xmark"></i></span>
                    <div>
                        <strong>Unavailable inventory records</strong>
                        <small>Items with no on-hand stock</small>
                    </div>
                    <b>{{ number_format($inventoryCritical) }}</b>
                </div>
            </div>
        </x-analytics.panel>
    </div>

    <aside class="inventory-reference-side">
        <x-analytics.panel
            class="inventory-panel inventory-comparison-panel"
            title="Stock Status Comparison"
            description="Relative item counts by current stock state"
        >
            <div class="inventory-comparison-bars">
                <x-analytics.horizontal-bars
                    :items="$inventoryComparisonRows"
                    value-key="value"
                    label-key="label"
                    display-key="display"
                    empty-text="No inventory status records are available."
                />
            </div>
        </x-analytics.panel>

        <x-analytics.panel
            class="inventory-panel inventory-interpretation-panel"
            title="Inventory Interpretation"
            description="Key insights derived from current warehouse stock fields"
        >
            <div class="inventory-interpretation-list">
                <div class="inventory-interpretation-row">
                    <span class="inventory-interpretation-icon"><i class="fa-solid fa-database"></i></span>
                    <div>
                        <strong>Source</strong>
                        <small>Inventory items, on-hand quantity, and reorder level</small>
                    </div>
                    <b class="info">Current snapshot</b>
                </div>

                <div class="inventory-interpretation-row">
                    <span class="inventory-interpretation-icon purple"><i class="fa-solid fa-scale-balanced"></i></span>
                    <div>
                        <strong>Category totals reconcile</strong>
                        <small>Well Stocked + Low Stock + Out of Stock</small>
                    </div>
                    <b>{{ number_format($inventoryHealthy + $inventoryLow + $inventoryCritical) }} / {{ number_format($inventoryTotal) }}</b>
                </div>
            </div>
        </x-analytics.panel>

        <x-analytics.panel
            class="inventory-panel inventory-attention-panel"
            title="Attention Snapshot"
            description="Quick view of the current inventory state"
        >
            <div class="inventory-attention-grid">
                <div class="inventory-attention-cell tone-blue">
                    <span><i class="fa-solid fa-boxes-stacked"></i></span>
                    <strong>{{ number_format($inventoryTotal) }}</strong>
                    <small>Total Items</small>
                </div>
                <div class="inventory-attention-cell tone-green">
                    <span><i class="fa-solid fa-box-open"></i></span>
                    <strong>{{ number_format($inventoryHealthy) }}</strong>
                    <small>Well Stocked</small>
                </div>
                <div class="inventory-attention-cell tone-yellow">
                    <span><i class="fa-solid fa-triangle-exclamation"></i></span>
                    <strong>{{ number_format($inventoryLow) }}</strong>
                    <small>Low Stock</small>
                </div>
                <div class="inventory-attention-cell tone-red">
                    <span><i class="fa-solid fa-circle-exclamation"></i></span>
                    <strong>{{ number_format($inventoryCritical) }}</strong>
                    <small>Out of Stock</small>
                </div>
            </div>
        </x-analytics.panel>
    </aside>
</section>
