@php
    $d = $diagnostic->inventory;
    $total = max(1,$d->total);
    $healthyPct = ($d->healthy/$total)*100;
    $lowPct = ($d->low/$total)*100;
    $criticalPct = ($d->critical/$total)*100;
    $outPct = $criticalPct;
    $stockCoveragePct = (($d->total - $d->critical) / $total) * 100;
    $attention = $d->attention_rows;
    $categories = $d->categories;
    $maxAttention = max(1,(int)($categories->max('attention') ?? 0));
@endphp

<section class="diag-stack">
<section class="analytics-kpi-strip" aria-label="Inventory diagnostics summary">
        <x-analytics.kpi
            label="Total Items"
            :value="number_format($d->total)"
            description="Stock items in the selected scope."
            icon="fa-boxes-stacked"
            icon-variant="blue"
        />

        <x-analytics.kpi
            label="Low Stock"
            :value="number_format($d->low)"
            :description="number_format($lowPct, 1) . '% of scoped items.'"
            icon="fa-arrow-down"
            icon-variant="yellow"
        />

        <x-analytics.kpi
            label="Out of Stock"
            :value="number_format($d->critical)"
            :description="number_format($outPct, 1) . '% of scoped items.'"
            icon="fa-box-open"
            icon-variant="red"
        />

        <x-analytics.kpi
            label="Stock Coverage"
            :value="number_format($stockCoveragePct, 1) . '%'"
            description="Inventory records with stock on hand."
            icon="fa-sack-dollar"
            icon-variant="green"
        />
    </section>

    <div class="inventory-main-grid">
        <article class="diag-card">
            <x-analytics.card-header class="diag-card-head" title="Inventory Health" description="Current stock state based on on-hand quantity and reorder level." />
            <div class="diag-donut-layout">
                <div class="diag-donut inventory-health-donut" style="--healthy:{{ $healthyPct }};--low:{{ $lowPct }};--p1:{{ $healthyPct }}%;--p2:{{ $healthyPct+$lowPct }}%"><div class="diag-donut-center"><strong>{{ $d->total }}</strong><span>Total Items</span></div></div>
                <div class="diag-legend"><div class="diag-legend-row"><i class="diag-dot green"></i><span>Well Stocked</span><b>{{ $d->healthy }} ({{ number_format($healthyPct,1) }}%)</b></div><div class="diag-legend-row"><i class="diag-dot orange"></i><span>Low Stock</span><b>{{ $d->low }} ({{ number_format($lowPct,1) }}%)</b></div><div class="diag-legend-row"><i class="diag-dot red"></i><span>Out of Stock</span><b>{{ $d->critical }} ({{ number_format($criticalPct,1) }}%)</b></div></div>
            </div>
        </article>

        <article class="diag-card">
            <x-analytics.card-header class="diag-card-head" title="Top Inventory Risks" description="Categories with the largest number of records requiring stock attention." />
            @if($categories->where('attention','>',0)->isEmpty())<div class="diag-empty">No category currently contains low or out-of-stock items.</div>@else<div class="diag-bars">@foreach($categories->where('attention','>',0)->take(6) as $cat)<div class="diag-bar-row"><span>{{ $cat->category }}</span><div class="diag-bar-track"><i class="diag-bar-fill {{ $cat->critical > 0 ? 'red' : 'orange' }}" style="width:{{ ($cat->attention/$maxAttention)*100 }}%"></i></div><b>{{ $cat->attention }}</b></div>@endforeach</div>@endif
        </article>

        <article class="diag-card">
            <x-analytics.card-header class="diag-card-head" title="Stock Status Comparison" description="Relative count of current inventory states." />
            <div class="diag-bars">
                <div class="diag-bar-row"><span>Well Stocked</span><div class="diag-bar-track"><i class="diag-bar-fill green" style="width:{{ $healthyPct }}%"></i></div><b>{{ $d->healthy }}</b></div>
                <div class="diag-bar-row"><span>Low Stock</span><div class="diag-bar-track"><i class="diag-bar-fill orange" style="width:{{ $lowPct }}%"></i></div><b>{{ $d->low }}</b></div>
                <div class="diag-bar-row"><span>Out of Stock</span><div class="diag-bar-track"><i class="diag-bar-fill red" style="width:{{ $criticalPct }}%"></i></div><b>{{ $d->critical }}</b></div>
            </div>
            <div style="margin-top:16px" class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-crosshairs"></i></span><div><strong>Items Requiring Attention</strong><small>Low stock plus out-of-stock records</small></div><span class="diag-list-value">{{ $attention->count() }}</span></div>
        </article>
    </div>

    <div class="inventory-secondary-grid">
        <article class="diag-card">
            <x-analytics.card-header class="diag-card-head" title="Stockout Risk by Category" description="Out-of-stock and low-stock concentration." />
            <div>@forelse($categories->take(7) as $cat)<div class="inventory-category-row"><strong>{{ $cat->category }}</strong><span>{{ $cat->total }} items</span><span style="color:#d97706">{{ $cat->low }} low</span><span style="color:#dc2626">{{ $cat->critical }} out</span></div>@empty<div class="diag-empty">No category data.</div>@endforelse</div>
        </article>

        <article class="diag-card">
            <x-analytics.card-header class="diag-card-head" title="Reorder Exposure" description="Highest-priority item records by current stock state and reorder gap." />
            @if($attention->isEmpty())<div class="diag-empty">No inventory record currently needs reorder attention.</div>@else<div class="diag-list">@foreach($attention->take(6) as $row)<div class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-box"></i></span><div><strong>{{ $row->name }}</strong><small>{{ $row->category }} · on hand {{ $row->on_hand }} · reorder {{ $row->reorder_level }}</small></div><span class="diag-badge {{ $row->state === 'Out of Stock' ? 'high' : 'medium' }}">{{ $row->state }}</span></div>@endforeach</div>@endif
        </article>

        <article class="diag-card">
            <x-analytics.card-header class="diag-card-head" title="Inventory Interpretation" description="What the current warehouse fields can and cannot support diagnostically." />
            <div class="diag-list"><div class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-database"></i></span><div><strong>Supported</strong><small>On-hand quantity, reorder threshold, category, stock state.</small></div><span class="diag-badge low">Included</span></div><div class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-ban"></i></span><div><strong>Not inferred</strong><small>Monetary value, turnover, inventory age, overstock cost, and demand forecast are not available from the current item fields.</small></div><span class="diag-badge info">Boundary</span></div></div>
        </article>
    </div>

    <article class="diag-card">
        <x-analytics.card-header class="diag-card-head" title="Inventory Diagnostic Breakdown" description="Items currently at or below stock-attention thresholds." />
        @if($attention->isEmpty())<div class="diag-empty">No inventory attention records.</div>@else<div class="diag-table-wrap"><table class="diag-table"><thead><tr><th>Item Code</th><th>Item</th><th>Category</th><th>On Hand</th><th>Reorder Level</th><th>Gap</th><th>Status</th></tr></thead><tbody>@foreach($attention->take(15) as $row)<tr><td><strong>{{ $row->item_code ?: '—' }}</strong></td><td>{{ $row->name }}</td><td>{{ $row->category }}</td><td>{{ $row->on_hand }}</td><td>{{ $row->reorder_level }}</td><td>{{ $row->gap }}</td><td><span class="diag-badge {{ $row->state === 'Out of Stock' ? 'high' : 'medium' }}">{{ $row->state }}</span></td></tr>@endforeach</tbody></table></div>@endif
    </article>

    <div class="diag-grid-2">
        <article class="diag-card diag-insight"><div class="diag-insight-icon"><i class="fa-regular fa-lightbulb"></i></div><div><h3>Key Insight</h3><p>@if($attention->isNotEmpty()){{ $attention->count() }} inventory records require stock attention. {{ $d->critical }} are out of stock and {{ $d->low }} are at or below reorder level. The category view shows where that exposure is concentrated without inventing cost or demand values.@else Current inventory records are above the configured reorder attention thresholds. @endif</p></div></article>
        <article class="diag-card"><x-analytics.card-header class="diag-card-head" title="Investigation Priorities" /><ol class="diag-priority-list"><li>Review out-of-stock items before low-stock records.</li><li>Inspect categories with the highest concentration of attention records.</li><li>Verify reorder levels for repeatedly low-stock items.</li><li>Use stock-movement history separately when movement-based analysis is required.</li></ol></article>
    </div>
</section>
