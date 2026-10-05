<x-layout.app
  title="FROMS - Warehouse Dashboard"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Warehouse/dashboard-warehouse.css',
    'resources/js/Main-js/sidebar.js',
    'resources/js/Warehouse/dashboard-warehouse.js'
  ]"
>
  @php
    $totalInventory = $totalInventory ?? 0;
    $availableStock = $availableStock ?? 0;
    $lowStockItems = $lowStockItems ?? 0;
    $outOfStock = $outOfStock ?? 0;
    $activePartRequestCount = $activePartRequestCount ?? 0;
    $incomingDeliveries = $incomingDeliveries ?? 0;
    $issuedToday = $issuedToday ?? 0;

    $activePartRequests = $activePartRequests ?? collect();
    $expectedDeliveries = $expectedDeliveries ?? collect();
    $criticalStockItems = $criticalStockItems ?? collect();
    $recentStockMovements = $recentStockMovements ?? collect();
    $topIssuedItems = $topIssuedItems ?? collect();
    $warehouseChartData = $warehouseChartData ?? [
      'inventory' => ['labels' => [], 'values' => []],
      'movementTrend' => ['labels' => [], 'received' => [], 'issued' => [], 'adjusted' => []],
    ];

    $dashboardAlerts = collect();

    foreach ($criticalStockItems->take(2) as $item) {
      $qty = (int) ($item->quantity_available ?? $item->on_hand ?? 0);
      $dashboardAlerts->push([
        'type' => $qty <= 0 ? 'critical' : 'warning',
        'icon' => $qty <= 0 ? 'fa-circle-xmark' : 'fa-triangle-exclamation',
        'title' => $qty <= 0 ? 'Out of Stock' : 'Low Stock',
        'message' => ($item->item_name ?? $item->parts_name ?? 'Inventory Item').' · '.($item->item_code ?? 'No code'),
        'time' => $item->updated_at?->diffForHumans() ?? 'Recently',
        'url' => route('inventory', ['search' => $item->item_code ?? $item->item_name]),
      ]);
    }

    foreach ($expectedDeliveries->take(2) as $delivery) {
      $dashboardAlerts->push([
        'type' => 'info',
        'icon' => 'fa-truck-fast',
        'title' => 'Incoming Delivery',
        'message' => ($delivery->po_no ?? 'Purchase Order').' · '.($delivery->supplier_name ?? 'Supplier'),
        'time' => $delivery->updated_at?->diffForHumans() ?? 'Recently',
        'url' => route('incoming-deliveries', ['search' => $delivery->po_no]),
      ]);
    }

    if ($activePartRequests->isNotEmpty()) {
      $request = $activePartRequests->first();
      $dashboardAlerts->push([
        'type' => 'success',
        'icon' => 'fa-clipboard-check',
        'title' => 'Part Request Waiting',
        'message' => ($request->pr_no ?? 'Part Request').' · '.($request->job_order_no ?? 'Maintenance'),
        'time' => $request->updated_at?->diffForHumans() ?? 'Recently',
        'url' => route('part-requests', ['search' => $request->pr_no]),
      ]);
    }

    $maxIssued = max(1, (int) ($topIssuedItems->max('total_issued') ?? 1));
    $notificationCount = $lowStockItems + $outOfStock + $activePartRequestCount + $incomingDeliveries;
  @endphp

  <div class="app warehouse-dashboard-page">
    <x-layout.sidebar department="Warehouse" />

    <main class="main warehouse-dashboard-main">
      <x-layout.topbar
        title="Warehouse Dashboard"
        subtitle="Monitor inventory, deliveries, part requests, and stock movements in real time"
        :notification-count="$notificationCount"
      />

      <section class="warehouse-kpi-grid" data-ajax-region="warehouse-kpis">
        <a href="{{ route('inventory') }}" class="warehouse-kpi-card">
          <span class="warehouse-kpi-icon blue"><i class="fa-solid fa-boxes-stacked"></i></span>
          <span class="warehouse-kpi-content">
            <small>Total Inventory Items</small>
            <strong>{{ number_format($totalInventory) }}</strong>
            <span>Registered stock items</span>
          </span>
        </a>

        <a href="{{ route('inventory') }}" class="warehouse-kpi-card">
          <span class="warehouse-kpi-icon green"><i class="fa-solid fa-cube"></i></span>
          <span class="warehouse-kpi-content">
            <small>Available Stock</small>
            <strong>{{ number_format($availableStock) }}</strong>
            <span>Items with sufficient stock</span>
          </span>
        </a>

        <a href="{{ route('inventory') }}" class="warehouse-kpi-card">
          <span class="warehouse-kpi-icon yellow"><i class="fa-solid fa-triangle-exclamation"></i></span>
          <span class="warehouse-kpi-content">
            <small>Low Stock Items</small>
            <strong>{{ number_format($lowStockItems) }}</strong>
            <span>At or below reorder level</span>
          </span>
        </a>

        <a href="{{ route('inventory') }}" class="warehouse-kpi-card">
          <span class="warehouse-kpi-icon red"><i class="fa-solid fa-box-open"></i></span>
          <span class="warehouse-kpi-content">
            <small>Out of Stock</small>
            <strong>{{ number_format($outOfStock) }}</strong>
            <span>No stock currently available</span>
          </span>
        </a>

        <a href="{{ route('part-requests') }}" class="warehouse-kpi-card">
          <span class="warehouse-kpi-icon blue"><i class="fa-solid fa-file-circle-check"></i></span>
          <span class="warehouse-kpi-content">
            <small>Pending Part Requests</small>
            <strong>{{ number_format($activePartRequestCount) }}</strong>
            <span>{{ number_format($issuedToday) }} issued today</span>
          </span>
        </a>

        <a href="{{ route('incoming-deliveries') }}" class="warehouse-kpi-card">
          <span class="warehouse-kpi-icon green"><i class="fa-solid fa-truck-ramp-box"></i></span>
          <span class="warehouse-kpi-content">
            <small>Incoming Deliveries</small>
            <strong>{{ number_format($incomingDeliveries) }}</strong>
            <span>Ready for Warehouse receiving</span>
          </span>
        </a>
      </section>

      <section class="warehouse-overview-grid">
        <article class="warehouse-panel warehouse-chart-panel" data-ajax-region="inventory-overview">
          <header class="warehouse-panel-header">
            <div>
              <span class="warehouse-panel-eyebrow">INVENTORY OVERVIEW</span>
              <h2>Stock Level Distribution</h2>
              <p>Current inventory health across active Warehouse items.</p>
            </div>
            <a href="{{ route('inventory') }}" class="warehouse-panel-link">View Inventory <i class="fa-solid fa-arrow-right"></i></a>
          </header>
          <div class="warehouse-chart-wrap bar-chart-wrap">
            <canvas
              id="warehouseInventoryBar"
              data-labels='@json($warehouseChartData["inventory"]["labels"] ?? [])'
              data-values='@json($warehouseChartData["inventory"]["values"] ?? [])'
              aria-label="Warehouse inventory stock distribution chart"
            ></canvas>
          </div>
        </article>

        <article class="warehouse-panel warehouse-chart-panel" data-ajax-region="stock-distribution">
          <header class="warehouse-panel-header compact">
            <div>
              <span class="warehouse-panel-eyebrow">Stock Status</span>
              <h2>Inventory Health</h2>
            </div>
          </header>
          <div class="warehouse-donut-layout">
            <div class="warehouse-chart-wrap donut-chart-wrap">
              <canvas
                id="warehouseInventoryDonut"
                data-labels='@json($warehouseChartData["inventory"]["labels"] ?? [])'
                data-values='@json($warehouseChartData["inventory"]["values"] ?? [])'
                aria-label="Warehouse inventory health doughnut chart"
              ></canvas>
              <div class="warehouse-donut-center">
                <strong>{{ number_format($totalInventory) }}</strong>
                <span>Total Items</span>
              </div>
            </div>
            <div class="warehouse-chart-legend">
              <div><span class="legend-dot green"></span><span>Available</span><strong>{{ $availableStock }}</strong></div>
              <div><span class="legend-dot yellow"></span><span>Low Stock</span><strong>{{ $lowStockItems }}</strong></div>
              <div><span class="legend-dot red"></span><span>Out of Stock</span><strong>{{ $outOfStock }}</strong></div>
            </div>
          </div>
        </article>

        <article class="warehouse-panel warehouse-alert-panel" data-ajax-region="warehouse-alerts">
          <header class="warehouse-panel-header compact">
            <div>
              <span class="warehouse-panel-eyebrow">LIVE WATCHLIST</span>
              <h2>Alerts & Updates</h2>
            </div>
          </header>
          <div class="warehouse-alert-list">
            @forelse($dashboardAlerts->take(5) as $alert)
              <a href="{{ $alert['url'] }}" class="warehouse-alert-row">
                <span class="warehouse-alert-icon {{ $alert['type'] }}"><i class="fa-solid {{ $alert['icon'] }}"></i></span>
                <span class="warehouse-alert-copy">
                  <strong>{{ $alert['title'] }}</strong>
                  <small>{{ $alert['message'] }}</small>
                </span>
                <time>{{ $alert['time'] }}</time>
                <i class="fa-solid fa-chevron-right"></i>
              </a>
            @empty
              <div class="warehouse-empty compact-empty">
                <i class="fa-solid fa-circle-check"></i>
                <strong>No urgent Warehouse alerts</strong>
                <span>Inventory, deliveries, and requests are currently stable.</span>
              </div>
            @endforelse
          </div>
        </article>
      </section>

      <section class="warehouse-table-grid">
        <article class="warehouse-panel" data-ajax-region="dashboard-incoming-deliveries">
          <header class="warehouse-panel-header">
            <div>
              <span class="warehouse-panel-eyebrow">PURCHASE SHIPMENTS</span>
              <h2>Recent Incoming Deliveries</h2>
            </div>
            <a href="{{ route('incoming-deliveries') }}" class="warehouse-panel-link">View All <i class="fa-solid fa-arrow-right"></i></a>
          </header>
          <div class="warehouse-table-scroll">
            <table class="warehouse-dashboard-table">
              <thead>
                <tr>
                  <th>PO No.</th>
                  <th>Supplier</th>
                  <th>Items</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                @forelse($expectedDeliveries as $delivery)
                  @php
                    $deliveryItems = collect($delivery->items ?? []);
                    $deliveryQty = (int) $deliveryItems->sum(fn ($item) => (int) ($item['quantity'] ?? 0));
                  @endphp
                  <tr>
                    <td><span class="warehouse-reference">{{ $delivery->po_no }}</span></td>
                    <td>{{ $delivery->supplier_name ?? 'Supplier' }}</td>
                    <td>{{ $deliveryQty }}</td>
                    <td><span class="warehouse-status {{ $delivery->status === 'For Delivery' ? 'blue' : 'yellow' }}">{{ $delivery->status }}</span></td>
                    <td><a href="{{ route('incoming-deliveries', ['search' => $delivery->po_no]) }}" class="warehouse-row-arrow" title="Open delivery"><i class="fa-solid fa-chevron-right"></i></a></td>
                  </tr>
                @empty
                  <tr><td colspan="5"><div class="warehouse-table-empty">No incoming deliveries.</div></td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </article>

        <article class="warehouse-panel" data-ajax-region="dashboard-part-requests">
          <header class="warehouse-panel-header">
            <div>
              <span class="warehouse-panel-eyebrow">MAINTENANCE REQUISITIONS</span>
              <h2>Active Part Requests</h2>
            </div>
            <a href="{{ route('part-requests') }}" class="warehouse-panel-link">View All <i class="fa-solid fa-arrow-right"></i></a>
          </header>
          <div class="warehouse-table-scroll">
            <table class="warehouse-dashboard-table">
              <thead>
                <tr>
                  <th>PR No.</th>
                  <th>Job Order</th>
                  <th>Status</th>
                  <th>Requested</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                @forelse($activePartRequests as $request)
                  @php
                    $requestStatusClass = match($request->status) {
                      'Approved' => 'green',
                      'For Purchase' => 'yellow',
                      'Ordered' => 'purple',
                      default => 'blue',
                    };
                  @endphp
                  <tr>
                    <td><span class="warehouse-reference">{{ $request->pr_no }}</span></td>
                    <td>{{ $request->job_order_no ?? '—' }}</td>
                    <td><span class="warehouse-status {{ $requestStatusClass }}">{{ $request->status }}</span></td>
                    <td>{{ $request->created_at?->format('M d') ?? '—' }}</td>
                    <td><a href="{{ route('part-requests', ['search' => $request->pr_no]) }}" class="warehouse-row-arrow" title="Open part request"><i class="fa-solid fa-chevron-right"></i></a></td>
                  </tr>
                @empty
                  <tr><td colspan="5"><div class="warehouse-table-empty">No active part requests.</div></td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </article>

        <article class="warehouse-panel" data-ajax-region="recent-stock-movements">
          <header class="warehouse-panel-header">
            <div>
              <span class="warehouse-panel-eyebrow">TRANSACTION AUDIT</span>
              <h2>Recent Stock Movements</h2>
            </div>
            <a href="{{ route('stock-movements') }}" class="warehouse-panel-link">View All <i class="fa-solid fa-arrow-right"></i></a>
          </header>
          <div class="warehouse-table-scroll">
            <table class="warehouse-dashboard-table">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Type</th>
                  <th>Qty</th>
                  <th>Reference</th>
                </tr>
              </thead>
              <tbody>
                @forelse($recentStockMovements as $movement)
                  @php
                    $movementType = strtolower((string) ($movement->movement_type ?? ''));
                    $movementClass = str_contains($movementType, 'in') ? 'green' : (str_contains($movementType, 'out') ? 'red' : 'blue');
                    $qty = (int) ($movement->quantity_change ?? 0);
                  @endphp
                  <tr>
                    <td>
                      <strong class="warehouse-table-primary">{{ $movement->item_name ?? 'Inventory Item' }}</strong>
                      <small>{{ $movement->created_at?->format('M d, h:i A') ?? '—' }}</small>
                    </td>
                    <td><span class="warehouse-status {{ $movementClass }}">{{ $movement->movement_type }}</span></td>
                    <td class="{{ $qty < 0 ? 'warehouse-negative' : 'warehouse-positive' }}">{{ $qty > 0 ? '+' : '' }}{{ $qty }}</td>
                    <td>{{ $movement->reference_no ?? '—' }}</td>
                  </tr>
                @empty
                  <tr><td colspan="4"><div class="warehouse-table-empty">No stock movements recorded.</div></td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </article>
      </section>

      <section class="warehouse-bottom-grid">
        <article class="warehouse-panel" data-ajax-region="dashboard-low-stock">
          <header class="warehouse-panel-header">
            <div>
              <span class="warehouse-panel-eyebrow">REPLENISHMENT WATCH</span>
              <h2>Top Low Stock Items</h2>
            </div>
            <a href="{{ route('inventory') }}" class="warehouse-panel-link">View All <i class="fa-solid fa-arrow-right"></i></a>
          </header>
          <div class="warehouse-table-scroll">
            <table class="warehouse-dashboard-table compact-table">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Current</th>
                  <th>Reorder</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse($criticalStockItems as $item)
                  @php
                    $stock = (int) ($item->quantity_available ?? $item->on_hand ?? 0);
                    $critical = $stock <= 0;
                  @endphp
                  <tr>
                    <td>
                      <strong class="warehouse-table-primary">{{ $item->item_name ?? $item->parts_name ?? 'Inventory Item' }}</strong>
                      <small>{{ $item->item_code ?? '—' }}</small>
                    </td>
                    <td class="{{ $critical ? 'warehouse-negative' : '' }}">{{ $stock }}</td>
                    <td>{{ (int) ($item->reorder_level ?? 0) }}</td>
                    <td><span class="warehouse-status {{ $critical ? 'red' : 'yellow' }}">{{ $critical ? 'Out of Stock' : 'Low Stock' }}</span></td>
                  </tr>
                @empty
                  <tr><td colspan="4"><div class="warehouse-table-empty">No low-stock items.</div></td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </article>

        <article class="warehouse-panel" data-ajax-region="dashboard-top-issued">
          <header class="warehouse-panel-header">
            <div>
              <span class="warehouse-panel-eyebrow">MONTHLY USAGE</span>
              <h2>Most Issued Items</h2>
            </div>
            <span class="warehouse-panel-period">{{ $trendPeriodLabel ?? now()->format('F Y') }}</span>
          </header>
          <div class="warehouse-issued-ranking">
            @forelse($topIssuedItems as $item)
              <div class="warehouse-issued-row">
                <div class="warehouse-issued-copy">
                  <strong>{{ $item['item_name'] }}</strong>
                  <small>{{ $item['item_code'] }}</small>
                </div>
                <div class="warehouse-issued-meter">
                  <span style="width: {{ max(8, round(($item['total_issued'] / $maxIssued) * 100)) }}%"></span>
                </div>
                <strong class="warehouse-issued-total">{{ number_format($item['total_issued']) }}</strong>
              </div>
            @empty
              <div class="warehouse-empty compact-empty">
                <i class="fa-solid fa-chart-simple"></i>
                <strong>No issued items recorded</strong>
                <span>Issued parts will be ranked here automatically.</span>
              </div>
            @endforelse
          </div>
        </article>

        <article class="warehouse-panel warehouse-trend-panel" data-ajax-region="dashboard-movement-trend">
          <header class="warehouse-panel-header">
            <div>
              <span class="warehouse-panel-eyebrow">STOCK MOVEMENT TREND</span>
              <h2>Received vs Issued</h2>
            </div>
            <span class="warehouse-panel-period">{{ $trendPeriodLabel ?? 'This Month' }}</span>
          </header>
          <div class="warehouse-chart-wrap trend-chart-wrap">
            <canvas
              id="warehouseMovementTrend"
              data-labels='@json($warehouseChartData["movementTrend"]["labels"] ?? [])'
              data-received='@json($warehouseChartData["movementTrend"]["received"] ?? [])'
              data-issued='@json($warehouseChartData["movementTrend"]["issued"] ?? [])'
              data-adjusted='@json($warehouseChartData["movementTrend"]["adjusted"] ?? [])'
              aria-label="Warehouse stock movement trend chart"
            ></canvas>
          </div>
        </article>
      </section>
    </main>
  </div>
</x-layout.app>
