@php
    $inventoryPredict = $predictive->inventory;
    $totalStock = $inventoryPredict->total ?? 0;
    $healthyCount = $inventoryPredict->healthy ?? 0;
    $lowCount = $inventoryPredict->low ?? 0;
    $criticalCount = $inventoryPredict->critical ?? 0;
@endphp

<div class="predictive-page predictive-inventory-page">

    {{-- KPI STRIP --}}
    <section class="predictive-kpis">
        @foreach($inventoryPredict->kpis as $kpi)
            <article class="predictive-kpi">
                <div class="predictive-kpi-icon {{ $kpi['tone'] }}">
                    <i class="fa-solid {{ $kpi['icon'] }}"></i>
                </div>
                <div class="predictive-kpi-copy">
                    <span>{{ $kpi['label'] }}</span>
                    <strong>{{ $kpi['value'] }}</strong>
                    <small>{{ $kpi['caption'] }}</small>
                </div>
            </article>
        @endforeach
    </section>

    {{-- MAIN GRID --}}
    <section class="predictive-main-grid-two">

        <article class="predictive-card forecast-card">
            <div class="card-heading">
                <div>
                    <h3>Stock Level Distribution</h3>
                    <p>On-hand quantities by reorder exposure.</p>
                </div>
            </div>
            <div class="risk-content">
                <div class="donut-wrapper">
                    <canvas id="inventoryDonut"></canvas>
                    <div class="donut-center">
                        <strong id="inventoryDonutTotal">{{ $inventoryPredict->total ?? 0 }}</strong>
                        <span>Total<br>Items</span>
                    </div>
                </div>
                <div class="risk-legend">
                    <div>
                        <span class="legend-dot low"></span>
                        <span>Well Stocked</span>
                        <strong>{{ $healthyCount }}</strong>
                    </div>
                    <div>
                        <span class="legend-dot medium"></span>
                        <span>Low Stock</span>
                        <strong>{{ $lowCount }}</strong>
                    </div>
                    <div>
                        <span class="legend-dot high"></span>
                        <span>Out of Stock</span>
                        <strong>{{ $criticalCount }}</strong>
                    </div>
                </div>
            </div>
        </article>

        <article class="predictive-card health-issues-card">
            <div class="card-heading">
                <div>
                    <h3>Stock Risk Signals</h3>
                    <p>Reorder exposure derived from on-hand and reorder levels.</p>
                </div>
            </div>
            <div class="issue-list">
                @foreach($inventoryPredict->issues as $issue)
                    <div class="issue-row">
                        <div class="issue-icon {{ $issue->tone }}">
                            <i class="fa-solid {{ $issue->icon }}"></i>
                        </div>
                        <div class="issue-info">
                            <strong>{{ $issue->title }}</strong>
                            <span>{{ $issue->description }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="predictive-card issues-card">
            <div class="card-heading">
                <div>
                    <h3>Category Attention</h3>
                    <p>Inventory categories ranked by stock attention records.</p>
                </div>
            </div>
            <div class="issue-list">
                @forelse($inventoryPredict->categories as $category)
                    <div class="issue-row">
                        <span class="issue-rank">
                            <i class="fa-solid fa-boxes-stacked"></i>
                        </span>
                        <div class="issue-info">
                            <strong>{{ $category->category }}</strong>
                            <span>{{ $category->total }} items · {{ $category->attention }} needing attention</span>
                        </div>
                        <span class="risk-badge {{ $category->critical > 0 ? 'high' : ($category->low > 0 ? 'medium' : 'low') }}">
                            {{ $category->critical > 0 ? 'Critical' : ($category->low > 0 ? 'Low Stock' : 'OK') }}
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
        </article>

    </section>

    {{-- PREDICTION TABLE --}}
    <section class="predictive-card predictions-card">
        <div class="card-heading">
            <div>
                <h3>Inventory Reorder Forecast</h3>
                <p>Items ranked by stockout risk from reorder exposure.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="predictive-table">
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Item</th>
                        <th>Category</th>
                        <th>On Hand</th>
                        <th>Reorder Level</th>
                        <th>State</th>
                        <th>Gap</th>
                        <th>Risk Level</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($inventoryPredict->rows as $row)
                        <tr>
                            <td><strong>{{ $row[0] }}</strong></td>
                            <td>{{ $row[1] }}</td>
                            <td>{{ $row[2] }}</td>
                            <td>{{ number_format($row[3]) }}</td>
                            <td>{{ number_format($row[4]) }}</td>
                            <td>{{ $row[5] }}</td>
                            <td>{{ number_format($row[6]) }}</td>
                            <td><span class="risk-badge {{ strtolower($row[7]) }}">{{ $row[7] }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">No inventory items are registered yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <p class="predictive-footer">
        Stock forecasts are derived from on-hand quantity versus reorder level. Results may vary.
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