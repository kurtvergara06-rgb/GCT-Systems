@php
    $d = $diagnostic->inventory;
    $total = max(1,$d->total);
    $healthyPct = ($d->healthy/$total)*100;
    $lowPct = ($d->low/$total)*100;
    $criticalPct = ($d->critical/$total)*100;
    $attention = $d->attention_rows;
    $categories = $d->categories;
    $maxAttention = max(1,(int)($categories->max('attention') ?? 0));
@endphp

<section class="diag-stack">
    <div class="diag-kpis">
        <article class="diag-kpi"><div class="diag-kpi-icon"><i class="fa-solid fa-boxes-stacked"></i></div><div class="diag-kpi-copy"><span class="diag-kpi-label">Total Items</span><div class="diag-kpi-value">{{ number_format($d->total) }}</div><small>Current inventory records in the warehouse master list.</small></div></article>
        <article class="diag-kpi" data-tone="green"><div class="diag-kpi-icon"><i class="fa-solid fa-circle-check"></i></div><div class="diag-kpi-copy"><span class="diag-kpi-label">Well Stocked</span><div class="diag-kpi-value">{{ number_format($d->healthy) }}</div><small>{{ number_format($healthyPct,1) }}% above reorder attention.</small></div></article>
        <article class="diag-kpi" data-tone="orange"><div class="diag-kpi-icon"><i class="fa-solid fa-triangle-exclamation"></i></div><div class="diag-kpi-copy"><span class="diag-kpi-label">Low Stock</span><div class="diag-kpi-value">{{ number_format($d->low) }}</div><small>On-hand stock at or below reorder level.</small></div></article>
        <article class="diag-kpi" data-tone="red"><div class="diag-kpi-icon"><i class="fa-solid fa-circle-xmark"></i></div><div class="diag-kpi-copy"><span class="diag-kpi-label">Out of Stock</span><div class="diag-kpi-value">{{ number_format($d->critical) }}</div><small>Records with zero or negative on-hand quantity.</small></div></article>
    </div>

    <div class="inventory-main-grid">
        <article class="diag-card">
            <div class="diag-card-head"><div><h3>Inventory Health</h3><p>Current stock state based on on-hand quantity and reorder level.</p></div></div>
            <div class="diag-donut-layout">
                <div class="diag-donut inventory-health-donut" style="--healthy:{{ $healthyPct }};--low:{{ $lowPct }};--p1:{{ $healthyPct }}%;--p2:{{ $healthyPct+$lowPct }}%"><div class="diag-donut-center"><strong>{{ $d->total }}</strong><span>Total Items</span></div></div>
                <div class="diag-legend"><div class="diag-legend-row"><i class="diag-dot green"></i><span>Well Stocked</span><b>{{ $d->healthy }} ({{ number_format($healthyPct,1) }}%)</b></div><div class="diag-legend-row"><i class="diag-dot orange"></i><span>Low Stock</span><b>{{ $d->low }} ({{ number_format($lowPct,1) }}%)</b></div><div class="diag-legend-row"><i class="diag-dot red"></i><span>Out of Stock</span><b>{{ $d->critical }} ({{ number_format($criticalPct,1) }}%)</b></div></div>
            </div>
        </article>

        <article class="diag-card">
            <div class="diag-card-head"><div><h3>Top Inventory Risks</h3><p>Categories with the largest number of records requiring stock attention.</p></div></div>
            @if($categories->where('attention','>',0)->isEmpty())<div class="diag-empty">No category currently contains low or out-of-stock items.</div>@else<div class="diag-bars">@foreach($categories->where('attention','>',0)->take(6) as $cat)<div class="diag-bar-row"><span>{{ $cat->category }}</span><div class="diag-bar-track"><i class="diag-bar-fill {{ $cat->critical > 0 ? 'red' : 'orange' }}" style="width:{{ ($cat->attention/$maxAttention)*100 }}%"></i></div><b>{{ $cat->attention }}</b></div>@endforeach</div>@endif
        </article>

        <article class="diag-card">
            <div class="diag-card-head"><div><h3>Stock Status Comparison</h3><p>Relative count of current inventory states.</p></div></div>
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
            <div class="diag-card-head"><div><h3>Stockout Risk by Category</h3><p>Out-of-stock and low-stock concentration.</p></div></div>
            <div>@forelse($categories->take(7) as $cat)<div class="inventory-category-row"><strong>{{ $cat->category }}</strong><span>{{ $cat->total }} items</span><span style="color:#d97706">{{ $cat->low }} low</span><span style="color:#dc2626">{{ $cat->critical }} out</span></div>@empty<div class="diag-empty">No category data.</div>@endforelse</div>
        </article>

        <article class="diag-card">
            <div class="diag-card-head"><div><h3>Reorder Exposure</h3><p>Highest-priority item records by current stock state and reorder gap.</p></div></div>
            @if($attention->isEmpty())<div class="diag-empty">No inventory record currently needs reorder attention.</div>@else<div class="diag-list">@foreach($attention->take(6) as $row)<div class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-box"></i></span><div><strong>{{ $row->name }}</strong><small>{{ $row->category }} · on hand {{ $row->on_hand }} · reorder {{ $row->reorder_level }}</small></div><span class="diag-badge {{ $row->state === 'Out of Stock' ? 'high' : 'medium' }}">{{ $row->state }}</span></div>@endforeach</div>@endif
        </article>

        <article class="diag-card">
            <div class="diag-card-head"><div><h3>Inventory Interpretation</h3><p>What the current warehouse fields can and cannot support diagnostically.</p></div></div>
            <div class="diag-list"><div class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-database"></i></span><div><strong>Supported</strong><small>On-hand quantity, reorder threshold, category, stock state.</small></div><span class="diag-badge low">Included</span></div><div class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-ban"></i></span><div><strong>Not inferred</strong><small>Monetary value, turnover, inventory age, overstock cost, and demand forecast are not available from the current item fields.</small></div><span class="diag-badge info">Boundary</span></div></div>
        </article>
    </div>

    <article class="diag-card">
        <div class="diag-card-head"><div><h3>Inventory Diagnostic Breakdown</h3><p>Items currently at or below stock-attention thresholds.</p></div></div>
        @if($attention->isEmpty())<div class="diag-empty">No inventory attention records.</div>@else<div class="diag-table-wrap"><table class="diag-table"><thead><tr><th>Item Code</th><th>Item</th><th>Category</th><th>On Hand</th><th>Reorder Level</th><th>Gap</th><th>Status</th></tr></thead><tbody>@foreach($attention->take(15) as $row)<tr><td><strong>{{ $row->item_code ?: '—' }}</strong></td><td>{{ $row->name }}</td><td>{{ $row->category }}</td><td>{{ $row->on_hand }}</td><td>{{ $row->reorder_level }}</td><td>{{ $row->gap }}</td><td><span class="diag-badge {{ $row->state === 'Out of Stock' ? 'high' : 'medium' }}">{{ $row->state }}</span></td></tr>@endforeach</tbody></table></div>@endif
    </article>

    <div class="diag-grid-2">
        <article class="diag-card diag-insight"><div class="diag-insight-icon"><i class="fa-regular fa-lightbulb"></i></div><div><h3>Key Insight</h3><p>@if($attention->isNotEmpty()){{ $attention->count() }} inventory records require stock attention. {{ $d->critical }} are out of stock and {{ $d->low }} are at or below reorder level. The category view shows where that exposure is concentrated without inventing cost or demand values.@else Current inventory records are above the configured reorder attention thresholds. @endif</p></div></article>
        <article class="diag-card"><div class="diag-card-head"><div><h3>Investigation Priorities</h3></div></div><ol class="diag-priority-list"><li>Review out-of-stock items before low-stock records.</li><li>Inspect categories with the highest concentration of attention records.</li><li>Verify reorder levels for repeatedly low-stock items.</li><li>Use stock-movement history separately when movement-based analysis is required.</li></ol></article>
    </div>
</section>
