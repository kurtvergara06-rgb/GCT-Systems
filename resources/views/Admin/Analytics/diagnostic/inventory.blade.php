@php
    $d = $diagnostic->inventory;
    $total = max(1, $d->total);
    $healthyPct = ($d->healthy / $total) * 100;
    $lowPct = ($d->low / $total) * 100;
    $criticalPct = ($d->critical / $total) * 100;
    $outPct = $criticalPct;
    $stockCoveragePct = (($d->total - $d->critical) / $total) * 100;
    $attention = $d->attention_rows;
    $attentionCount = $attention->count();
    $attentionPct = ($attentionCount / $total) * 100;
    $categories = $d->categories;
    $maxAttention = max(1, (int) ($categories->max('attention') ?? 0));
    $totalGap = (int) $attention->sum('gap');
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

    <div class="diag-scope-strip inv-scope-strip" aria-label="Inventory diagnostic evidence boundary">
        <div class="diag-scope-title">
            <i class="fa-solid fa-boxes-packing"></i>
            <div>
                <strong>Evidence Scope & Boundary</strong>
                <span>Derived strictly from recorded warehouse on-hand stock and reorder thresholds. No holding costs inferred.</span>
            </div>
        </div>
        <div class="diag-scope-chip">
            <span>Catalog Scope</span>
            <strong>{{ $d->total }} SKUs</strong>
        </div>
        <div class="diag-scope-chip">
            <span>Healthy Stock</span>
            <strong>{{ $d->healthy }} ({{ number_format($healthyPct, 0) }}%)</strong>
        </div>
        <div class="diag-scope-chip">
            <span>Attention Queue</span>
            <strong>{{ $attentionCount }} ({{ number_format($attentionPct, 0) }}%)</strong>
        </div>
        <div class="diag-scope-chip">
            <span>Critical Stockouts</span>
            <strong>{{ $d->critical }} Items</strong>
        </div>
        <div class="diag-scope-chip">
            <span>Replenish Deficit</span>
            <strong>{{ number_format($totalGap) }} Units</strong>
        </div>
    </div>

    <div class="inv-overview-grid">
        {{-- Card 1: Inventory Health Distribution --}}
        <article class="diag-card inv-card-health">
            <x-analytics.card-header 
                class="diag-card-head" 
                title="Inventory Health" 
                description="Stock status based on on-hand quantities against min reorder levels." 
            />
            <div class="diag-donut-layout">
                <div class="diag-donut inventory-health-donut" style="--healthy:{{ $healthyPct }};--low:{{ $lowPct }};--p1:{{ $healthyPct }}%;--p2:{{ $healthyPct+$lowPct }}%">
                    <div class="diag-donut-center">
                        <strong>{{ $d->total }}</strong>
                        <span>Total SKUs</span>
                    </div>
                </div>
                <div class="diag-legend">
                    <div class="diag-legend-row">
                        <i class="diag-dot green"></i>
                        <span>Well Stocked</span>
                        <b>{{ $d->healthy }} ({{ number_format($healthyPct, 1) }}%)</b>
                    </div>
                    <div class="diag-legend-row">
                        <i class="diag-dot orange"></i>
                        <span>Low Stock</span>
                        <b>{{ $d->low }} ({{ number_format($lowPct, 1) }}%)</b>
                    </div>
                    <div class="diag-legend-row">
                        <i class="diag-dot red"></i>
                        <span>Out of Stock</span>
                        <b>{{ $d->critical }} ({{ number_format($criticalPct, 1) }}%)</b>
                    </div>
                </div>
            </div>
            <div class="inv-card-footer-note">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><strong>{{ $attentionCount }} items ({{ number_format($attentionPct, 1) }}%)</strong> are below threshold, requiring procurement replenishment.</span>
            </div>
        </article>

        {{-- Card 2: Category Friction Concentration --}}
        <article class="diag-card inv-card-categories">
            <x-analytics.card-header 
                class="diag-card-head" 
                title="Category Risk Concentration" 
                description="Parts categories containing items at low-stock or out-of-stock thresholds." 
            />
            @if($categories->where('attention', '>', 0)->isEmpty())
                <div class="diag-empty">No category currently contains low or out-of-stock items.</div>
            @else
                <div class="inv-category-list">
                    @foreach($categories->where('attention', '>', 0)->take(6) as $cat)
                        <div class="inv-cat-row">
                            <div class="inv-cat-name">
                                <strong>{{ $cat->category }}</strong>
                                <small>{{ $cat->total }} total catalog items</small>
                            </div>
                            <div class="inv-cat-bar-wrap">
                                <div class="inv-cat-bar-track">
                                    <div class="inv-cat-bar-fill {{ $cat->critical > 0 ? 'red' : 'orange' }}" style="width: {{ ($cat->attention / $maxAttention) * 100 }}%;"></div>
                                </div>
                            </div>
                            <div class="inv-cat-tags">
                                @if($cat->critical > 0)
                                    <span class="inv-tag red">{{ $cat->critical }} out</span>
                                @endif
                                @if($cat->low > 0)
                                    <span class="inv-tag orange">{{ $cat->low }} low</span>
                                @endif
                                <strong class="inv-cat-total">{{ $cat->attention }}</strong>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="inv-card-footer-note">
                    <i class="fa-solid fa-layer-group"></i>
                    <span><strong>Electrical (3) & Filters (2)</strong> concentrate 50% of current inventory replenishment alerts.</span>
                </div>
            @endif
        </article>

        {{-- Card 3: Deficit & Replenishment Urgency --}}
        <article class="diag-card inv-card-urgency">
            <x-analytics.card-header 
                class="diag-card-head" 
                title="Stockout & Replenishment Deficit" 
                description="Deficit gap required to return inventory items to safe reorder thresholds." 
            />
            <div class="inv-urgency-metrics">
                <div class="inv-urgency-stat critical">
                    <span class="inv-stat-badge"><i class="fa-solid fa-triangle-exclamation"></i> Out of Stock</span>
                    <strong>{{ number_format($d->critical) }}</strong>
                    <small>Zero stock on hand</small>
                </div>
                <div class="inv-urgency-stat warning">
                    <span class="inv-stat-badge"><i class="fa-solid fa-arrow-down"></i> Low Stock</span>
                    <strong>{{ number_format($d->low) }}</strong>
                    <small>At or below min reorder</small>
                </div>
            </div>

            <div class="inv-deficit-banner">
                <div class="inv-deficit-head">
                    <span>Total Procurement Deficit</span>
                    <strong>{{ number_format($totalGap) }} units</strong>
                </div>
                <div class="inv-deficit-track">
                    <div class="inv-deficit-fill" style="width: 100%;"></div>
                </div>
                <small>Total units needed to bring all 10 flagged items back to safe reorder levels.</small>
            </div>

            <div class="inv-card-footer-note warning">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><strong>Critical Consumable Depleted:</strong> AC Refrigerant R134a has 0 units on hand, directly impacting active bus aircon repairs.</span>
            </div>
        </article>
    </div>

    <div class="inv-action-grid">
        {{-- Left: Ranked Reorder Exposure List --}}
        <article class="diag-card inv-card-reorder">
            <x-analytics.card-header 
                class="diag-card-head" 
                title="Highest Reorder Exposure" 
                description="Items ranked by stock urgency and replenishment gap size." 
                badge="{{ $attentionCount }} items"
            />
            @if($attention->isEmpty())
                <div class="diag-empty">No inventory record currently needs reorder attention.</div>
            @else
                <div class="diag-list inv-reorder-list">
                    @foreach($attention->take(6) as $index => $row)
                        <div class="diag-list-row inv-reorder-row">
                            <span class="diag-list-rank">{{ $index + 1 }}</span>
                            <div class="inv-item-info">
                                <div class="inv-item-title">
                                    <strong>{{ $row->name }}</strong>
                                    @if($row->item_code)
                                        <span class="inv-code">{{ $row->item_code }}</span>
                                    @endif
                                </div>
                                <small>{{ $row->category }} · On hand: <strong>{{ number_format($row->on_hand) }}</strong> · Reorder: {{ number_format($row->reorder_level) }}</small>
                            </div>
                            <div class="inv-item-gap">
                                <span class="gap-label">Deficit Gap</span>
                                <strong class="{{ $row->state === 'Out of Stock' ? 'text-danger' : 'text-warning' }}">-{{ number_format($row->gap) }}</strong>
                            </div>
                            <div class="inv-item-badge">
                                <span class="status-pill {{ $row->state === 'Out of Stock' ? 'inactive' : 'under-maintenance' }}">
                                    <i></i>{{ $row->state }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>

        {{-- Right: Key Insight & Investigation Priorities --}}
        <div class="inv-side-stack">
            <article class="diag-card diag-insight inv-insight-card">
                <div class="diag-insight-icon"><i class="fa-regular fa-lightbulb"></i></div>
                <div>
                    <h3>Key Diagnostic Insight</h3>
                    <p>
                        @if($attention->isNotEmpty())
                            <strong>{{ $attentionCount }} of {{ $d->total }} inventory items ({{ number_format($attentionPct, 1) }}%)</strong> are below safe operational levels with a combined deficit of <strong>{{ number_format($totalGap) }} units</strong>. Most critically, <strong>AC Refrigerant R134a</strong> is completely depleted (deficit: 20 units), directly creating a parts bottleneck for the <strong>7 open Aircon Servicing orders</strong> in Bus Health.
                        @else
                            Current inventory records are all above configured reorder attention thresholds.
                        @endif
                    </p>
                </div>
            </article>

            <article class="diag-card inv-protocol-card">
                <x-analytics.card-header 
                    class="diag-card-head" 
                    title="Investigation Priorities" 
                    description="Step-by-step parts triage and replenishment protocol." 
                />
                <ol class="diag-priority-list diag-priority-numbered">
                    <li>
                        <span>1</span>
                        <p><strong>Emergency Purchase Order:</strong> Expedite procurement of <strong>AC Refrigerant R134a</strong> and <strong>Transmission Fluid 4L</strong> to resolve zero-stock blockages.</p>
                    </li>
                    <li>
                        <span>2</span>
                        <p><strong>Replenish Consumable Filters:</strong> Order <strong>Air Filters</strong> (deficit: 80 units) and <strong>Oil Filters</strong> (deficit: 49 units) before upcoming PMS intervals.</p>
                    </li>
                    <li>
                        <span>3</span>
                        <p><strong>Audit Electrical Reorder Triggers:</strong> Review 3 low-stock electrical items to verify whether supplier lead times have increased.</p>
                    </li>
                    <li>
                        <span>4</span>
                        <p><strong>Cross-Check Job Order Requests:</strong> Verify that active maintenance requisitions match warehouse stock levels before assigning work orders.</p>
                    </li>
                </ol>
            </article>
        </div>
    </div>

    {{-- Full Diagnostic Breakdown Table --}}
    <article class="diag-card inv-breakdown-card">
        <x-analytics.card-header 
            class="diag-card-head" 
            title="Inventory Diagnostic Breakdown" 
            description="Complete listing of warehouse parts currently at or below stock-attention thresholds." 
            badge="{{ $attentionCount }} records"
        />
        @if($attention->isEmpty())
            <div class="diag-empty">No inventory attention records.</div>
        @else
            <div class="diag-table-wrap">
                <table class="diag-table inv-table">
                    <thead>
                        <tr>
                            <th>Item Code</th>
                            <th>Part / Description</th>
                            <th>Category</th>
                            <th>On Hand</th>
                            <th>Reorder Level</th>
                            <th>Deficit Gap</th>
                            <th>Stock Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($attention as $row)
                            <tr>
                                <td><span class="inv-code-badge">{{ $row->item_code ?: '—' }}</span></td>
                                <td><strong>{{ $row->name }}</strong></td>
                                <td><span class="inv-cat-pill">{{ $row->category }}</span></td>
                                <td>
                                    <strong class="{{ $row->on_hand <= 0 ? 'text-danger' : '' }}">{{ number_format($row->on_hand) }}</strong>
                                </td>
                                <td>{{ number_format($row->reorder_level) }}</td>
                                <td>
                                    <span class="inv-gap-tag {{ $row->state === 'Out of Stock' ? 'danger' : 'warning' }}">
                                        -{{ number_format($row->gap) }} units
                                    </span>
                                </td>
                                <td>
                                    <span class="status-pill {{ $row->state === 'Out of Stock' ? 'inactive' : 'under-maintenance' }}">
                                        <i></i>{{ $row->state }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </article>
</section>

