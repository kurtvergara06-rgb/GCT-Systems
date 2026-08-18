<section class="analytics-kpi-strip analytics-domain-kpi-four">
    <x-analytics.kpi label="Total Items" :value="$inventoryTotal" small="Inventory records" icon="fa-boxes-stacked" />
    <x-analytics.kpi label="Well Stocked" :value="$inventoryHealthy" small="Above reorder threshold" icon="fa-box-open" tone="green" />
    <x-analytics.kpi label="Low Stock" :value="$inventoryLow" small="At or below reorder level" icon="fa-triangle-exclamation" tone="yellow" />
    <x-analytics.kpi label="Out of Stock" :value="$inventoryCritical" small="No on-hand stock" icon="fa-circle-exclamation" tone="red" />
</section>

<section class="analytics-domain-content">
    <div class="analytics-domain-grid">
        <x-analytics.panel title="Stock-Level Distribution" description="Current stock status across inventory records" :badge="$inventoryTotal . ' items'">
            <div class="analytics-status-overview"><div class="analytics-status-ring" style="--ring-active: {{ $inventoryHealthyAngle }}deg; --ring-maintenance: {{ $inventoryLowAngle }}deg;"><div><strong>{{ number_format($healthyPct) }}%</strong><span>well stocked</span></div></div><div class="analytics-status-legend"><div class="analytics-status-legend-row"><span><i class="analytics-status-dot green"></i>Well Stocked</span><strong>{{ $inventoryHealthy }} ({{ number_format($healthyPct) }}%)</strong></div><div class="analytics-status-legend-row"><span><i class="analytics-status-dot yellow"></i>Low Stock</span><strong>{{ $inventoryLow }} ({{ number_format($lowPct) }}%)</strong></div><div class="analytics-status-legend-row"><span><i class="analytics-status-dot red"></i>Out of Stock</span><strong>{{ $inventoryCritical }} ({{ number_format($criticalPct) }}%)</strong></div></div></div>
        </x-analytics.panel>
        <x-analytics.panel title="Stock Status Comparison" description="Relative item counts by current stock state"><x-analytics.horizontal-bars :items="$inventoryStatusBars" value-key="value" label-key="label" /></x-analytics.panel>
    </div>

    <div class="analytics-domain-grid equal">
        <x-analytics.panel title="Restock Exposure" description="Items currently at or below the reorder threshold"><div class="analytics-record-list"><div class="analytics-record-row"><span class="analytics-record-icon"><i class="fa-solid fa-triangle-exclamation"></i></span><div class="analytics-record-copy"><strong>Items requiring stock attention</strong><span>Low-stock plus out-of-stock inventory records</span></div><span class="analytics-record-value">{{ $inventoryLow + $inventoryCritical }}</span></div><div class="analytics-record-row"><span class="analytics-record-icon"><i class="fa-solid fa-ban"></i></span><div class="analytics-record-copy"><strong>Unavailable inventory records</strong><span>Items with no on-hand stock</span></div><span class="analytics-record-value">{{ $inventoryCritical }}</span></div></div></x-analytics.panel>
        <x-analytics.panel title="Inventory Interpretation" description="Snapshot derived from current warehouse stock fields"><div class="analytics-record-list"><div class="analytics-record-row"><span class="analytics-record-icon"><i class="fa-solid fa-database"></i></span><div class="analytics-record-copy"><strong>Source</strong><span>Inventory items, on-hand quantity, and reorder level</span></div><span class="analytics-record-value">Current snapshot</span></div><div class="analytics-record-row"><span class="analytics-record-icon"><i class="fa-solid fa-scale-balanced"></i></span><div class="analytics-record-copy"><strong>Category totals reconcile</strong><span>Well Stocked + Low Stock + Out of Stock</span></div><span class="analytics-record-value">{{ $inventoryHealthy + $inventoryLow + $inventoryCritical }} / {{ $inventoryTotal }}</span></div></div></x-analytics.panel>
    </div>
</section>
