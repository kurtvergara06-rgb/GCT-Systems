<x-layout.app
    title="FROMS - Inventory Analytics"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/Analytics/inventory.css',
        'resources/js/Main-js/sidebar.js'
    ]"
>
    <div class="app">

        <x-layout.sidebar department="Admin" />

        <main class="main inventory-analytics-page">

            <x-layout.topbar
                title="Inventory Analytics"
                subtitle="Monitor stock levels, reorder risk, parts usage, and inventory demand"
                notification-count="6"
            />

            {{-- SUMMARY --}}
            <section data-ajax-region="summary" class="stats-grid inventory-summary-grid">

                <x-ui.summary-card
                    label="Total Inventory Items"
                    value="184"
                    small="Active parts and supplies"
                    icon="fa-boxes-stacked"
                    color="blue"
                />

                <x-ui.summary-card
                    label="Critical Stock"
                    value="6"
                    small="Below reorder threshold"
                    icon="fa-triangle-exclamation"
                    color="red"
                />

                <x-ui.summary-card
                    label="Low Stock"
                    value="14"
                    small="Approaching reorder level"
                    icon="fa-box-open"
                    color="yellow"
                />

                <x-ui.summary-card
                    label="Pending Restock"
                    value="9"
                    small="Items awaiting replenishment"
                    icon="fa-cart-flatbed"
                    color="green"
                />

            </section>


            {{-- FILTER --}}
            <section class="inventory-filter-card">

                <div>
                    <h2>Inventory Performance</h2>
                    <p>
                        Review current stock condition and historical parts usage.
                    </p>
                </div>

                <div class="inventory-filters">

                    <select>
                        <option>All Categories</option>
                        <option>Engine Parts</option>
                        <option>Brake System</option>
                        <option>Filters</option>
                        <option>Electrical</option>
                        <option>Belts & Hoses</option>
                    </select>

                    <select>
                        <option>All Stock Status</option>
                        <option>Available</option>
                        <option>Low Stock</option>
                        <option>Critical</option>
                        <option>Pending Restock</option>
                    </select>

                </div>

            </section>


            {{-- FIRST ROW --}}
            <section class="inventory-grid">

                {{-- STOCK DISTRIBUTION --}}
                <article class="inventory-card">

                    <div class="inventory-card-header">

                        <div>
                            <h2>Stock Status Distribution</h2>
                            <p>Current inventory condition across all active items.</p>
                        </div>

                        <span class="analytics-badge descriptive">
                            Descriptive
                        </span>

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
                                <div>
                                    <span class="stock-dot available"></span>
                                    Available
                                </div>
                                <strong>155</strong>
                            </div>

                            <div class="distribution-row">
                                <div>
                                    <span class="stock-dot low"></span>
                                    Low Stock
                                </div>
                                <strong>14</strong>
                            </div>

                            <div class="distribution-row">
                                <div>
                                    <span class="stock-dot critical"></span>
                                    Critical
                                </div>
                                <strong>6</strong>
                            </div>

                            <div class="distribution-row">
                                <div>
                                    <span class="stock-dot restock"></span>
                                    Pending Restock
                                </div>
                                <strong>9</strong>
                            </div>

                        </div>

                    </div>

                </article>


                {{-- REORDER PRIORITY --}}
                <article class="inventory-card">

                    <div class="inventory-card-header">

                        <div>
                            <h2>Reorder Priority</h2>
                            <p>Items requiring immediate or upcoming replenishment.</p>
                        </div>

                        <span class="analytics-badge predictive">
                            Predictive
                        </span>

                    </div>

                    <div class="reorder-list">

                        <div class="reorder-item critical">

                            <div class="reorder-main">

                                <div class="reorder-icon">
                                    <i class="fa-solid fa-box-open"></i>
                                </div>

                                <div>
                                    <strong>Brake Pad Set</strong>
                                    <span>PART-0042 · Reorder level: 10</span>
                                </div>

                            </div>

                            <div class="reorder-status">
                                <strong>4 left</strong>
                                <span>Critical</span>
                            </div>

                        </div>


                        <div class="reorder-item warning">

                            <div class="reorder-main">

                                <div class="reorder-icon">
                                    <i class="fa-solid fa-box-open"></i>
                                </div>

                                <div>
                                    <strong>Fan Belt</strong>
                                    <span>PART-0068 · Reorder level: 10</span>
                                </div>

                            </div>

                            <div class="reorder-status">
                                <strong>6 left</strong>
                                <span>Low Stock</span>
                            </div>

                        </div>


                        <div class="reorder-item warning">

                            <div class="reorder-main">

                                <div class="reorder-icon">
                                    <i class="fa-solid fa-box-open"></i>
                                </div>

                                <div>
                                    <strong>Oil Filter</strong>
                                    <span>PART-0031 · Reorder level: 12</span>
                                </div>

                            </div>

                            <div class="reorder-status">
                                <strong>8 left</strong>
                                <span>Low Stock</span>
                            </div>

                        </div>

                    </div>

                </article>

            </section>


            {{-- SECOND ROW --}}
            <section class="inventory-grid">

                {{-- FAST MOVING PARTS --}}
                <article class="inventory-card">

                    <div class="inventory-card-header">

                        <div>
                            <h2>Fast-Moving Parts</h2>
                            <p>Parts with the highest recorded usage this month.</p>
                        </div>

                        <span class="analytics-badge diagnostic">
                            Diagnostic
                        </span>

                    </div>

                    <div class="usage-list">

                        <div class="usage-item">

                            <div class="usage-header">

                                <div>
                                    <strong>Oil Filter</strong>
                                    <span>42 units issued</span>
                                </div>

                                <strong class="usage-percent">100%</strong>

                            </div>

                            <div class="usage-progress">
                                <span style="width: 100%;"></span>
                            </div>

                        </div>


                        <div class="usage-item">

                            <div class="usage-header">

                                <div>
                                    <strong>Brake Pad Set</strong>
                                    <span>31 units issued</span>
                                </div>

                                <strong class="usage-percent">74%</strong>

                            </div>

                            <div class="usage-progress">
                                <span style="width: 74%;"></span>
                            </div>

                        </div>


                        <div class="usage-item">

                            <div class="usage-header">

                                <div>
                                    <strong>Engine Oil</strong>
                                    <span>27 units issued</span>
                                </div>

                                <strong class="usage-percent">64%</strong>

                            </div>

                            <div class="usage-progress">
                                <span style="width: 64%;"></span>
                            </div>

                        </div>


                        <div class="usage-item">

                            <div class="usage-header">

                                <div>
                                    <strong>Fan Belt</strong>
                                    <span>18 units issued</span>
                                </div>

                                <strong class="usage-percent">43%</strong>

                            </div>

                            <div class="usage-progress">
                                <span style="width: 43%;"></span>
                            </div>

                        </div>

                    </div>

                </article>


                {{-- INVENTORY FINDINGS --}}
                <article class="inventory-card">

                    <div class="inventory-card-header">

                        <div>
                            <h2>Inventory Findings</h2>
                            <p>Important observations from stock and issuance records.</p>
                        </div>

                        <span class="analytics-badge diagnostic">
                            Diagnostic
                        </span>

                    </div>

                    <div class="inventory-findings">

                        <div class="inventory-finding critical">

                            <div class="finding-icon">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>

                            <div>
                                <strong>6 items are below reorder threshold</strong>

                                <p>
                                    Immediate replenishment review is recommended for critical items.
                                </p>
                            </div>

                        </div>


                        <div class="inventory-finding warning">

                            <div class="finding-icon">
                                <i class="fa-solid fa-arrow-trend-up"></i>
                            </div>

                            <div>
                                <strong>Oil filters have the highest monthly usage</strong>

                                <p>
                                    High issuance frequency may require a higher reorder quantity.
                                </p>
                            </div>

                        </div>


                        <div class="inventory-finding info">

                            <div class="finding-icon">
                                <i class="fa-solid fa-screwdriver-wrench"></i>
                            </div>

                            <div>
                                <strong>Brake parts align with frequent maintenance activity</strong>

                                <p>
                                    Brake-related maintenance records may be contributing to higher parts consumption.
                                </p>
                            </div>

                        </div>

                    </div>

                </article>

            </section>


            {{-- TABLE --}}
            @php
                $inventoryRecords = [
                    [
                        'code' => 'PART-0042',
                        'item' => 'Brake Pad Set',
                        'category' => 'Brake System',
                        'stock' => 4,
                        'reorder' => 10,
                        'issued' => 31,
                        'status' => 'Critical',
                    ],
                    [
                        'code' => 'PART-0068',
                        'item' => 'Fan Belt',
                        'category' => 'Belts & Hoses',
                        'stock' => 6,
                        'reorder' => 10,
                        'issued' => 18,
                        'status' => 'Low Stock',
                    ],
                    [
                        'code' => 'PART-0031',
                        'item' => 'Oil Filter',
                        'category' => 'Filters',
                        'stock' => 8,
                        'reorder' => 12,
                        'issued' => 42,
                        'status' => 'Low Stock',
                    ],
                    [
                        'code' => 'PART-0084',
                        'item' => 'Engine Oil',
                        'category' => 'Engine Parts',
                        'stock' => 18,
                        'reorder' => 15,
                        'issued' => 27,
                        'status' => 'Available',
                    ],
                    [
                        'code' => 'PART-0022',
                        'item' => 'Air Filter',
                        'category' => 'Filters',
                        'stock' => 24,
                        'reorder' => 12,
                        'issued' => 14,
                        'status' => 'Available',
                    ],
                ];
            @endphp

            <section data-ajax-region="records" class="inventory-card inventory-table-card">

                <div class="inventory-card-header">

                    <div>
                        <h2>Inventory Stock Analysis</h2>
                        <p>Compare current quantity, reorder level, and recent item usage.</p>
                    </div>

                    <span class="analytics-badge predictive">
                        Predictive
                    </span>

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

                                    $stockPercent = min(
                                        100,
                                        ($record['stock'] / max($record['reorder'], 1)) * 100
                                    );
                                @endphp

                                <tr>

                                    <td>

                                        <div class="item-cell">

                                            <div class="item-icon">
                                                <i class="fa-solid fa-box"></i>
                                            </div>

                                            <div>
                                                <strong>{{ $record['item'] }}</strong>
                                                <span>{{ $record['code'] }}</span>
                                            </div>

                                        </div>

                                    </td>

                                    <td>
                                        {{ $record['category'] }}
                                    </td>

                                    <td>
                                        {{ $record['stock'] }}
                                    </td>

                                    <td>
                                        {{ $record['reorder'] }}
                                    </td>

                                    <td>
                                        {{ $record['issued'] }}
                                    </td>

                                    <td>

                                        <div class="stock-level-cell">

                                            <strong>
                                                {{ $record['stock'] }} units
                                            </strong>

                                            <div class="stock-level-progress">
                                                <span
                                                    class="{{ $statusClass }}"
                                                    style="width: {{ $stockPercent }}%;"
                                                ></span>
                                            </div>

                                        </div>

                                    </td>

                                    <td>
                                        <span class="inventory-status {{ $statusClass }}">
                                            {{ $record['status'] }}
                                        </span>
                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

            </section>


            {{-- INSIGHT --}}
            <section class="inventory-insight">

                <div class="insight-icon">
                    <i class="fa-solid fa-lightbulb"></i>
                </div>

                <div class="insight-content">

                    <span>Inventory Recommendation</span>

                    <h2>
                        Prioritize critical parts and review reorder quantities for frequently issued items.
                    </h2>

                    <p>
                        Brake Pad Set, Fan Belt, and Oil Filter are currently below their configured
                        reorder levels. Historical usage should also be considered when setting future
                        reorder quantities, especially for fast-moving maintenance parts.
                    </p>

                </div>

                <a
                    href="{{ route('analytics.recommendations') }}"
                    class="insight-link"
                >
                    View Recommendations
                    <i class="fa-solid fa-arrow-right"></i>
                </a>

            </section>

        </main>

    </div>

</x-layout.app>