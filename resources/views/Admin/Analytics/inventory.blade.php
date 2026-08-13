<x-layout.app
    title="FROMS - Inventory Analytics"
    :assets="[
        'resources/css/Admin/Analytics/inventory.css',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main inventory-analytics-page">
            <x-layout.topbar
                title="Inventory Analytics"
                subtitle="Monitor stock levels, threshold alerts, usage trends, stock forecasts, and restocking priorities"
                notification-count="6"
            />

            {{-- =====================================================
                5.1 DESCRIPTIVE ANALYTICS
            ====================================================== --}}
            <section data-ajax-region="summary" class="stats-grid inventory-summary-grid">
                <x-ui.summary-card
                    label="Total Inventory Items"
                    value="184"
                    small="Active parts and supplies"
                    icon="fa-boxes-stacked"
                    color="blue"
                />

                <x-ui.summary-card
                    label="At / Below Threshold"
                    value="20"
                    small="Items requiring stock-level attention"
                    icon="fa-triangle-exclamation"
                    color="red"
                />

                <x-ui.summary-card
                    label="Fast-Moving Items"
                    value="4"
                    small="Highest recorded stock-out activity"
                    icon="fa-arrow-trend-up"
                    color="yellow"
                />

                <x-ui.summary-card
                    label="Pending Restock"
                    value="9"
                    small="Items already awaiting replenishment"
                    icon="fa-cart-flatbed"
                    color="green"
                />
            </section>

            <section class="inventory-filter-card">
                <div>
                    <h2>Inventory Performance</h2>
                    <p>Review current stock condition, threshold status, and historical stock movement.</p>
                </div>

                <div class="inventory-filters">
                    <select aria-label="Inventory category">
                        <option>All Categories</option>
                        <option>Engine Parts</option>
                        <option>Brake System</option>
                        <option>Filters</option>
                        <option>Electrical</option>
                        <option>Belts & Hoses</option>
                    </select>

                    <select aria-label="Stock status">
                        <option>All Stock Status</option>
                        <option>In Stock</option>
                        <option>Low Stock</option>
                        <option>Critical</option>
                        <option>Pending Restock</option>
                    </select>
                </div>
            </section>

            <section class="inventory-grid">
                <article class="inventory-card">
                    <div class="inventory-card-header">
                        <div>
                            <h2>Stock Status Distribution</h2>
                            <p>Describes current inventory condition against configured reorder levels.</p>
                        </div>

                        <span class="analytics-badge descriptive">Descriptive</span>
                    </div>

                    <div class="stock-distribution">
                        <div class="inventory-donut">
                            <div class="inventory-donut-center">
                                <strong>184</strong>
                                <span>Total Items</span>
                            </div>
                        </div>

                        <div class="stock-distribution-list">
                            <div class="distribution-row">
                                <div><span class="stock-dot available"></span>In Stock</div>
                                <strong>155</strong>
                            </div>

                            <div class="distribution-row">
                                <div><span class="stock-dot low"></span>Low Stock</div>
                                <strong>14</strong>
                            </div>

                            <div class="distribution-row">
                                <div><span class="stock-dot critical"></span>Critical</div>
                                <strong>6</strong>
                            </div>

                            <div class="distribution-row">
                                <div><span class="stock-dot restock"></span>Pending Restock</div>
                                <strong>9</strong>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="inventory-card">
                    <div class="inventory-card-header">
                        <div>
                            <h2>Threshold Alerts</h2>
                            <p>Early stock alerts based on current quantity and configured reorder level.</p>
                        </div>

                        <span class="analytics-badge descriptive">Descriptive</span>
                    </div>

                    <div class="reorder-list">
                        <div class="reorder-item critical">
                            <div class="reorder-main">
                                <div class="reorder-icon"><i class="fa-solid fa-box-open"></i></div>
                                <div>
                                    <strong>Brake Pad Set</strong>
                                    <span>PART-0042 · Reorder level: 10</span>
                                </div>
                            </div>
                            <div class="reorder-status"><strong>4 left</strong><span>Critical</span></div>
                        </div>

                        <div class="reorder-item warning">
                            <div class="reorder-main">
                                <div class="reorder-icon"><i class="fa-solid fa-box-open"></i></div>
                                <div>
                                    <strong>Fan Belt</strong>
                                    <span>PART-0068 · Reorder level: 10</span>
                                </div>
                            </div>
                            <div class="reorder-status"><strong>6 left</strong><span>Low Stock</span></div>
                        </div>

                        <div class="reorder-item warning">
                            <div class="reorder-main">
                                <div class="reorder-icon"><i class="fa-solid fa-box-open"></i></div>
                                <div>
                                    <strong>Oil Filter</strong>
                                    <span>PART-0031 · Reorder level: 12</span>
                                </div>
                            </div>
                            <div class="reorder-status"><strong>8 left</strong><span>Low Stock</span></div>
                        </div>
                    </div>
                </article>
            </section>

            {{-- =====================================================
                DIAGNOSTIC + PREDICTIVE ANALYTICS
            ====================================================== --}}
            <section class="inventory-grid">
                <article class="inventory-card">
                    <div class="inventory-card-header">
                        <div>
                            <h2>Stock-Out Usage Pattern</h2>
                            <p>Compares items with the highest recorded quantity issued through stock movements.</p>
                        </div>

                        <span class="analytics-badge diagnostic">Diagnostic</span>
                    </div>

                    <div class="usage-list">
                        <div class="usage-item">
                            <div class="usage-header">
                                <div><strong>Oil Filter</strong><span>42 units issued this month</span></div>
                                <strong class="usage-percent">100%</strong>
                            </div>
                            <div class="usage-progress"><span style="width: 100%;"></span></div>
                        </div>

                        <div class="usage-item">
                            <div class="usage-header">
                                <div><strong>Brake Pad Set</strong><span>31 units issued this month</span></div>
                                <strong class="usage-percent">74%</strong>
                            </div>
                            <div class="usage-progress"><span style="width: 74%;"></span></div>
                        </div>

                        <div class="usage-item">
                            <div class="usage-header">
                                <div><strong>Engine Oil</strong><span>27 units issued this month</span></div>
                                <strong class="usage-percent">64%</strong>
                            </div>
                            <div class="usage-progress"><span style="width: 64%;"></span></div>
                        </div>

                        <div class="usage-item">
                            <div class="usage-header">
                                <div><strong>Fan Belt</strong><span>18 units issued this month</span></div>
                                <strong class="usage-percent">43%</strong>
                            </div>
                            <div class="usage-progress"><span style="width: 43%;"></span></div>
                        </div>
                    </div>
                </article>

                <article class="inventory-card">
                    <div class="inventory-card-header">
                        <div>
                            <h2>Inventory Level Forecast</h2>
                            <p>Projected stock runway using current quantity and recent stock-out rate.</p>
                        </div>

                        <span class="analytics-badge predictive">Predictive</span>
                    </div>

                    <div class="inventory-findings">
                        <div class="inventory-finding critical">
                            <div class="finding-icon"><i class="fa-solid fa-hourglass-end"></i></div>
                            <div>
                                <strong>Brake Pad Set: projected stockout in about 4 days</strong>
                                <p>Current stock is already below the reorder level. Future logic should use average daily Stock Out quantity.</p>
                            </div>
                        </div>

                        <div class="inventory-finding warning">
                            <div class="finding-icon"><i class="fa-solid fa-clock"></i></div>
                            <div>
                                <strong>Oil Filter: projected stockout in about 6 days</strong>
                                <p>High recent issuance and only 8 units remaining create an early replenishment alert.</p>
                            </div>
                        </div>

                        <div class="inventory-finding info">
                            <div class="finding-icon"><i class="fa-solid fa-chart-line"></i></div>
                            <div>
                                <strong>Forecast rule should use movement history, not a fixed guess</strong>
                                <p>Average daily usage = Stock Out quantity ÷ analysis days; estimated days remaining = current stock ÷ average daily usage.</p>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            {{-- =====================================================
                ITEM-LEVEL STOCK ANALYSIS
            ====================================================== --}}
            @php
                $inventoryRecords = [
                    ['code' => 'PART-0042', 'item' => 'Brake Pad Set', 'category' => 'Brake System', 'stock' => 4, 'reorder' => 10, 'issued' => 31, 'days' => '4 days', 'status' => 'Critical'],
                    ['code' => 'PART-0068', 'item' => 'Fan Belt', 'category' => 'Belts & Hoses', 'stock' => 6, 'reorder' => 10, 'issued' => 18, 'days' => '10 days', 'status' => 'Low Stock'],
                    ['code' => 'PART-0031', 'item' => 'Oil Filter', 'category' => 'Filters', 'stock' => 8, 'reorder' => 12, 'issued' => 42, 'days' => '6 days', 'status' => 'Low Stock'],
                    ['code' => 'PART-0084', 'item' => 'Engine Oil', 'category' => 'Engine Parts', 'stock' => 18, 'reorder' => 15, 'issued' => 27, 'days' => '20 days', 'status' => 'In Stock'],
                    ['code' => 'PART-0022', 'item' => 'Air Filter', 'category' => 'Filters', 'stock' => 24, 'reorder' => 12, 'issued' => 14, 'days' => '51 days', 'status' => 'In Stock'],
                ];
            @endphp

            <section data-ajax-region="records" class="inventory-card inventory-table-card">
                <div class="inventory-card-header">
                    <div>
                        <h2>Inventory Stock & Forecast Analysis</h2>
                        <p>Compare current quantity, reorder threshold, recent usage, and projected stock runway.</p>
                    </div>

                    <span class="analytics-badge predictive">Predictive</span>
                </div>

                <div class="table-wrap">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Current Stock</th>
                                <th>Reorder Level</th>
                                <th>Issued This Month</th>
                                <th>Est. Stock Runway</th>
                                <th>Stock Level</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($inventoryRecords as $record)
                                @php
                                    $statusClass = match($record['status']) {
                                        'Critical' => 'critical',
                                        'Low Stock' => 'warning',
                                        default => 'available',
                                    };

                                    $stockPercent = min(100, ($record['stock'] / max($record['reorder'], 1)) * 100);
                                @endphp

                                <tr>
                                    <td>
                                        <div class="item-cell">
                                            <div class="item-icon"><i class="fa-solid fa-box"></i></div>
                                            <div>
                                                <strong>{{ $record['item'] }}</strong>
                                                <span>{{ $record['code'] }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $record['category'] }}</td>
                                    <td>{{ $record['stock'] }}</td>
                                    <td>{{ $record['reorder'] }}</td>
                                    <td>{{ $record['issued'] }}</td>
                                    <td><strong>{{ $record['days'] }}</strong></td>
                                    <td>
                                        <div class="stock-level-cell">
                                            <strong>{{ $record['stock'] }} units</strong>
                                            <div class="stock-level-progress">
                                                <span class="{{ $statusClass }}" style="width: {{ $stockPercent }}%;"></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="inventory-status {{ $statusClass }}">{{ $record['status'] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- =====================================================
                5.4 PRESCRIPTIVE ANALYTICS
            ====================================================== --}}
            <section class="inventory-insight">
                <div class="insight-icon"><i class="fa-solid fa-lightbulb"></i></div>

                <div class="insight-content">
                    <span>Prescriptive Inventory Recommendation</span>
                    <h2>Prioritize restocking items that are below threshold or projected to run out before replenishment can arrive.</h2>
                    <p>
                        Brake Pad Set, Fan Belt, and Oil Filter currently require replenishment review. Future recommendation rules should combine
                        current stock, reorder level, average Stock Out rate, pending restock or purchase activity, and supplier lead time when available.
                        Recommendations should support authorized Warehouse and Purchase personnel rather than automatically creating orders.
                    </p>
                </div>

                <a href="{{ route('analytics.recommendations') }}" class="insight-link">
                    View Recommendations
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </section>
        </main>
    </div>
</x-layout.app>