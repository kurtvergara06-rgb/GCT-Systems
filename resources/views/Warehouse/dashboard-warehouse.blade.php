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
    $forReorder = $forReorder ?? ($lowStockItems + $outOfStock);
    $inventoryAddedThisMonth = $inventoryAddedThisMonth ?? 0;
    $activePartRequestCount = $activePartRequestCount ?? 0;
    $incomingDeliveries = $incomingDeliveries ?? 0;
    $incomingToday = $incomingToday ?? 0;
    $issuedToday = $issuedToday ?? 0;

    $activePartRequests = $activePartRequests ?? collect();
    $expectedDeliveries = $expectedDeliveries ?? collect();
    $criticalStockItems = $criticalStockItems ?? collect();
    $recentStockMovements = $recentStockMovements ?? collect();
    $topIssuedItems = $topIssuedItems ?? collect();
    $inventoryCategoryOptions = $inventoryCategoryOptions ?? collect();
    $inventoryStatusByCategory = $inventoryStatusByCategory ?? [];
    $warehouseChartData = $warehouseChartData ?? [
      'inventory' => ['labels' => [], 'values' => []],
      'statusDistribution' => ['labels' => [], 'values' => []],
      'statusByCategory' => [],
      'movementTrend' => ['labels' => [], 'received' => [], 'issued' => [], 'adjusted' => []],
    ];

    $availablePercent = $totalInventory > 0 ? round(($availableStock / $totalInventory) * 100) : 0;
    $lowStockPercent = $totalInventory > 0 ? round(($lowStockItems / $totalInventory) * 100) : 0;
    $outOfStockPercent = $totalInventory > 0 ? round(($outOfStock / $totalInventory) * 100) : 0;

    $dashboardAlerts = collect();

    foreach ($criticalStockItems->take(5) as $item) {
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

      <section class="warehouse-kpi-grid warehouse-reference-kpis" data-ajax-region="warehouse-kpis">
        <a href="{{ route('inventory') }}" class="warehouse-kpi-card warehouse-kpi-blue">
          <span class="warehouse-kpi-icon blue"><i class="fa-solid fa-cube"></i></span>
          <span class="warehouse-kpi-content">
            <small>Total Inventory Items</small>
            <span class="warehouse-kpi-metric-row">
              <strong>{{ number_format($totalInventory) }}</strong>
              @if($inventoryAddedThisMonth > 0)
                <span class="warehouse-kpi-trend positive"><i class="fa-solid fa-arrow-up"></i> {{ $inventoryAddedThisMonth }} new</span>
              @endif
            </span>
            <span class="warehouse-kpi-footer"><span>Active items in inventory</span></span>
          </span>
        </a>

        <a href="{{ route('inventory') }}" class="warehouse-kpi-card warehouse-kpi-green">
          <span class="warehouse-kpi-icon green"><i class="fa-solid fa-cubes-stacked"></i></span>
          <span class="warehouse-kpi-content">
            <small>Available Stock</small>
            <span class="warehouse-kpi-metric-row">
              <strong>{{ number_format($availableStock) }}</strong>
              <span class="warehouse-kpi-trend positive">{{ $availablePercent }}%</span>
            </span>
            <span class="warehouse-kpi-footer"><span>Items with sufficient stock</span><b>View</b></span>
          </span>
        </a>

        <a href="{{ route('inventory') }}" class="warehouse-kpi-card warehouse-kpi-yellow">
          <span class="warehouse-kpi-icon yellow"><i class="fa-solid fa-triangle-exclamation"></i></span>
          <span class="warehouse-kpi-content">
            <small>Low Stock Items</small>
            <span class="warehouse-kpi-metric-row">
              <strong>{{ number_format($lowStockItems) }}</strong>
              <span class="warehouse-kpi-trend warning">{{ $lowStockPercent }}%</span>
            </span>
            <span class="warehouse-kpi-footer"><span>At or below reorder level</span><b>View</b></span>
          </span>
        </a>

        <a href="{{ route('inventory') }}" class="warehouse-kpi-card warehouse-kpi-red">
          <span class="warehouse-kpi-icon red"><i class="fa-solid fa-box-open"></i></span>
          <span class="warehouse-kpi-content">
            <small>Out of Stock</small>
            <span class="warehouse-kpi-metric-row">
              <strong>{{ number_format($outOfStock) }}</strong>
              <span class="warehouse-kpi-trend danger">{{ $outOfStockPercent }}%</span>
            </span>
            <span class="warehouse-kpi-footer"><span>Items with no available stock</span><b>View</b></span>
          </span>
        </a>

        <a href="{{ route('part-requests') }}" class="warehouse-kpi-card warehouse-kpi-purple">
          <span class="warehouse-kpi-icon blue"><i class="fa-solid fa-file-lines"></i></span>
          <span class="warehouse-kpi-content">
            <small>Pending Part Requests</small>
            <span class="warehouse-kpi-metric-row">
              <strong>{{ number_format($activePartRequestCount) }}</strong>
              <span class="warehouse-kpi-trend neutral">{{ number_format($issuedToday) }} today</span>
            </span>
            <span class="warehouse-kpi-footer"><span>For warehouse action</span><b>View</b></span>
          </span>
        </a>

        <a href="{{ route('incoming-deliveries') }}" class="warehouse-kpi-card warehouse-kpi-green">
          <span class="warehouse-kpi-icon green"><i class="fa-solid fa-truck"></i></span>
          <span class="warehouse-kpi-content">
            <small>Incoming Deliveries</small>
            <span class="warehouse-kpi-metric-row">
              <strong>{{ number_format($incomingDeliveries) }}</strong>
              <span class="warehouse-kpi-trend info">{{ number_format($incomingToday) }} today</span>
            </span>
            <span class="warehouse-kpi-footer"><span>Scheduled / In transit</span><b>View</b></span>
          </span>
        </a>
      </section>

      <section class="warehouse-overview-grid warehouse-reference-overview">
        <article class="warehouse-panel warehouse-chart-panel warehouse-overview-card" data-ajax-region="inventory-overview">
          <header class="warehouse-panel-header warehouse-reference-panel-header">
            <div class="warehouse-reference-title">
              <span class="warehouse-reference-title-icon blue"><i class="fa-solid fa-chart-column"></i></span>
              <div>
                <h2>Inventory Overview</h2>
                <p>Stock level distribution</p>
              </div>
            </div>
            <select
              id="warehouseCategoryFilter"
              class="warehouse-category-filter"
              data-status-map='@json($warehouseChartData["statusByCategory"] ?? $inventoryStatusByCategory, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'
              aria-label="Filter dashboard inventory charts by category"
            >
              <option value="All Categories">All Categories</option>
              @foreach($inventoryCategoryOptions as $category)
                <option value="{{ $category }}">{{ $category }}</option>
              @endforeach
            </select>
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

        <article class="warehouse-panel warehouse-chart-panel warehouse-stock-status-card" data-ajax-region="stock-distribution">
          <header class="warehouse-panel-header warehouse-reference-panel-header compact">
            <div class="warehouse-reference-title">
              <span class="warehouse-reference-title-icon blue"><i class="fa-solid fa-chart-pie"></i></span>
              <div>
                <h2>Stock Status by Category</h2>
              </div>
            </div>
          </header>

          <div class="warehouse-donut-layout">
            <div class="warehouse-chart-wrap donut-chart-wrap">
              <canvas
                id="warehouseInventoryDonut"
                data-labels='@json($warehouseChartData["statusDistribution"]["labels"] ?? [])'
                data-values='@json($warehouseChartData["statusDistribution"]["values"] ?? [])'
                aria-label="Warehouse inventory health doughnut chart"
              ></canvas>
              <div class="warehouse-donut-center">
                <strong data-dashboard-total>{{ number_format($totalInventory) }}</strong>
                <span>Total Items</span>
              </div>
            </div>

            <div class="warehouse-chart-legend warehouse-reference-legend">
              <div>
                <span class="legend-dot green"></span>
                <span>Sufficient Stock</span>
                <strong><span data-dashboard-count="available">{{ $availableStock }}</span> <em data-dashboard-percent="available">({{ $availablePercent }}%)</em></strong>
              </div>
              <div>
                <span class="legend-dot yellow"></span>
                <span>Low Stock</span>
                <strong><span data-dashboard-count="low">{{ $lowStockItems }}</span> <em data-dashboard-percent="low">({{ $lowStockPercent }}%)</em></strong>
              </div>
              <div>
                <span class="legend-dot red"></span>
                <span>Out of Stock</span>
                <strong><span data-dashboard-count="out">{{ $outOfStock }}</span> <em data-dashboard-percent="out">({{ $outOfStockPercent }}%)</em></strong>
              </div>
              <div class="warehouse-reference-metric-only">
                <span class="legend-dot blue"></span>
                <span>For Reorder</span>
                <strong><span data-dashboard-count="reorder">{{ $forReorder }}</span></strong>
              </div>
            </div>
          </div>
        </article>

        <article class="warehouse-panel warehouse-alert-panel warehouse-reference-alerts" data-ajax-region="warehouse-alerts">
          <header class="warehouse-panel-header warehouse-reference-panel-header compact">
            <div class="warehouse-reference-title">
              <span class="warehouse-reference-title-icon blue"><i class="fa-solid fa-bell"></i></span>
              <div>
                <h2>Alerts & Notifications</h2>
              </div>
            </div>
            <a href="{{ route('inventory') }}" class="warehouse-reference-view-all">View All <i class="fa-solid fa-arrow-right"></i></a>
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
        <article class="warehouse-panel warehouse-queue-panel" data-ajax-region="dashboard-incoming-deliveries">
          <header class="warehouse-panel-header">
            <div>
              <span class="warehouse-panel-eyebrow">PURCHASE SHIPMENTS</span>
              <h2><span class="warehouse-queue-title-icon blue"><i class="fa-solid fa-truck-fast"></i></span>Recent Incoming Deliveries</h2>
            </div>
            <a href="{{ route('incoming-deliveries') }}" class="warehouse-panel-link">View All <i class="fa-solid fa-arrow-right"></i></a>
          </header>
          <div class="warehouse-table-scroll">
            <table class="warehouse-dashboard-table">
              <thead>
                <tr>
                  <th><i class="fa-regular fa-calendar"></i> PO No.</th>
                  <th><i class="fa-solid fa-truck"></i> Supplier</th>
                  <th><i class="fa-solid fa-cube"></i> Items</th>
                  <th><i class="fa-solid fa-circle-notch"></i> Status</th>
                  <th><i class="fa-regular fa-calendar-days"></i> PO Date</th>
                </tr>
              </thead>
              <tbody>
                @forelse($expectedDeliveries->take(5) as $delivery)
                  @php
                    $deliveryItems = collect($delivery->items ?? []);
                    $deliveryQty = (int) $deliveryItems->sum(fn ($item) => (int) ($item['quantity'] ?? 0));
                  @endphp
                  <tr>
                    <td><a href="{{ route('incoming-deliveries', ['search' => $delivery->po_no]) }}" class="warehouse-reference">{{ $delivery->po_no }}</a></td>
                    <td title="{{ $delivery->supplier_name ?? 'Supplier' }}">{{ $delivery->supplier_name ?? 'Supplier' }}</td>
                    <td>{{ $deliveryQty }}</td>
                    <td><span class="warehouse-status {{ $delivery->status === 'For Delivery' ? 'green' : 'yellow' }}"><i class="fa-solid fa-circle"></i>{{ $delivery->status }}</span></td>
                    <td>{{ $delivery->po_date?->format('M d, Y') ?? 'No date' }}</td>
                  </tr>
                @empty
                  <tr class="warehouse-queue-empty-row"><td colspan="5"><div class="warehouse-table-empty">No incoming deliveries.</div></td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </article>

        <article class="warehouse-panel warehouse-queue-panel" data-ajax-region="dashboard-part-requests">
          <header class="warehouse-panel-header">
            <div>
              <span class="warehouse-panel-eyebrow">MAINTENANCE REQUISITIONS</span>
              <h2><span class="warehouse-queue-title-icon purple"><i class="fa-solid fa-clipboard-list"></i></span>Active Part Requests</h2>
            </div>
            <a href="{{ route('part-requests') }}" class="warehouse-panel-link">View All <i class="fa-solid fa-arrow-right"></i></a>
          </header>
          <div class="warehouse-table-scroll">
            <table class="warehouse-dashboard-table">
              <thead>
                <tr>
                  <th><i class="fa-regular fa-file-lines"></i> PR No.</th>
                  <th><i class="fa-solid fa-screwdriver-wrench"></i> Job Order</th>
                  <th><i class="fa-solid fa-circle-notch"></i> Status</th>
                  <th><i class="fa-regular fa-calendar-days"></i> Requested</th>
                </tr>
              </thead>
              <tbody>
                @forelse($activePartRequests->take(5) as $request)
                  @php
                    $requestStatusClass = match($request->status) {
                      'Approved' => 'green',
                      'For Purchase' => 'yellow',
                      'Ordered' => 'purple',
                      default => 'blue',
                    };
                  @endphp
                  <tr>
                    <td><a href="{{ route('part-requests', ['search' => $request->pr_no]) }}" class="warehouse-reference">{{ $request->pr_no }}</a></td>
                    <td title="{{ $request->job_order_no ?? '—' }}">{{ $request->job_order_no ?? '—' }}</td>
                    <td><span class="warehouse-status {{ $requestStatusClass }}"><i class="fa-solid fa-circle"></i>{{ $request->status }}</span></td>
                    <td>{{ $request->created_at?->format('M d') ?? '—' }}</td>
                  </tr>
                @empty
                  <tr class="warehouse-queue-empty-row"><td colspan="4"><div class="warehouse-table-empty">No active part requests.</div></td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </article>

        <article class="warehouse-panel warehouse-audit-panel" data-ajax-region="recent-stock-movements">
          <header class="warehouse-panel-header warehouse-feature-header">
            <div class="warehouse-feature-heading">
              <span class="warehouse-feature-icon audit"><i class="fa-solid fa-right-left"></i></span>
              <div>
                <span class="warehouse-panel-eyebrow">TRANSACTION AUDIT</span>
                <h2>Recent Stock Movements</h2>
                <p>Latest inventory transactions across all items.</p>
              </div>
            </div>
            <a href="{{ route('stock-movements') }}" class="warehouse-panel-link">View All <i class="fa-solid fa-arrow-right"></i></a>
          </header>
          <div class="warehouse-table-scroll">
            <table class="warehouse-dashboard-table warehouse-audit-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th><i class="fa-solid fa-cube"></i> Item</th>
                  <th><i class="fa-solid fa-right-left"></i> Type</th>
                  <th><i class="fa-regular fa-file-lines"></i> Qty</th>
                  <th><i class="fa-solid fa-link"></i> Reference</th>
                  <th><i class="fa-regular fa-calendar-days"></i> Date / Time</th>
                  <th><i class="fa-regular fa-user"></i> Updated By</th>
                  <th><span class="sr-only">Action</span></th>
                </tr>
              </thead>
              <tbody>
                @forelse($recentStockMovements as $movement)
                  @php
                    $movementType = strtolower((string) ($movement->movement_type ?? ''));
                    $movementClass = str_contains($movementType, 'in') ? 'green' : (str_contains($movementType, 'out') ? 'red' : 'blue');
                    $qty = (int) ($movement->quantity_change ?? 0);
                    $creatorName = $movement->creator?->name ?? 'System';
                    $creatorRole = $movement->creator?->role ?? 'Automated update';
                    $creatorInitials = collect(preg_split('/\s+/', trim($creatorName)))
                      ->filter()
                      ->take(2)
                      ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                      ->implode('');
                  @endphp
                  <tr>
                    <td class="warehouse-row-number">{{ $loop->iteration }}</td>
                    <td>
                      <div class="warehouse-item-cell">
                        <span class="warehouse-item-icon tone-{{ (($loop->iteration - 1) % 5) + 1 }}"><i class="fa-solid fa-box"></i></span>
                        <span><strong class="warehouse-table-primary">{{ $movement->item_name ?? 'Inventory Item' }}</strong><small>{{ $movement->item_code ?? 'No item code' }}</small></span>
                      </div>
                    </td>
                    <td><span class="warehouse-status {{ $movementClass }}"><i class="fa-solid {{ $qty < 0 ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>{{ $movement->movement_type }}</span></td>
                    <td class="warehouse-movement-qty {{ $qty < 0 ? 'warehouse-negative' : 'warehouse-positive' }}">{{ $qty > 0 ? '+' : '' }}{{ number_format($qty) }} {{ $movement->unit ?? 'pcs' }}</td>
                    <td><span class="warehouse-audit-reference">{{ $movement->reference_no ?? 'No reference' }}</span></td>
                    <td>
                      <div class="warehouse-date-cell"><i class="fa-regular fa-clock"></i><span><strong>{{ $movement->created_at?->format('M d, Y') ?? 'No date' }}</strong><small>{{ $movement->created_at?->format('h:i A') ?? '' }}</small></span></div>
                    </td>
                    <td>
                      <div class="warehouse-user-cell"><span class="warehouse-user-avatar tone-{{ (($loop->iteration - 1) % 3) + 1 }}">{{ $creatorInitials ?: 'SY' }}</span><span><strong>{{ $creatorName }}</strong><small>{{ $creatorRole }}</small></span></div>
                    </td>
                    <td><a href="{{ route('stock-movements', ['search' => $movement->reference_no ?: $movement->item_code]) }}" class="warehouse-view-action"><i class="fa-regular fa-eye"></i> View</a></td>
                  </tr>
                @empty
                  <tr><td colspan="8"><div class="warehouse-table-empty">No stock movements recorded.</div></td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </article>
      </section>

      <section class="warehouse-bottom-grid">
        <article class="warehouse-panel warehouse-replenishment-panel" data-ajax-region="dashboard-low-stock">
          <header class="warehouse-panel-header warehouse-feature-header">
            <div class="warehouse-feature-heading">
              <span class="warehouse-feature-icon danger"><i class="fa-solid fa-circle-exclamation"></i></span>
              <div>
                <span class="warehouse-panel-eyebrow">REPLENISHMENT WATCH</span>
                <h2>Top Low Stock Items</h2>
                <p>Items that are low or out of stock.</p>
              </div>
            </div>
            <a href="{{ route('inventory') }}" class="warehouse-panel-link">View All <i class="fa-solid fa-arrow-right"></i></a>
          </header>
          <div class="warehouse-table-scroll">
            <table class="warehouse-dashboard-table compact-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th><i class="fa-solid fa-cube"></i> Item</th>
                  <th>Current</th>
                  <th>Reorder</th>
                  <th><i class="fa-solid fa-layer-group"></i> Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse($criticalStockItems as $item)
                  @php
                    $stock = (int) ($item->quantity_available ?? $item->on_hand ?? 0);
                    $reorder = max(1, (int) ($item->reorder_level ?? 0));
                    $critical = $stock <= 0;
                  @endphp
                  <tr>
                    <td class="warehouse-row-number">{{ $loop->iteration }}</td>
                    <td>
                      <div class="warehouse-item-cell">
                        <span class="warehouse-item-icon tone-{{ (($loop->iteration - 1) % 5) + 1 }}"><i class="fa-solid fa-box"></i></span>
                        <span>
                      <strong class="warehouse-table-primary">{{ $item->item_name ?? $item->parts_name ?? 'Inventory Item' }}</strong>
                      <small>{{ $item->item_code ?? '—' }}</small>
                        </span>
                      </div>
                    </td>
                    <td class="{{ $critical ? 'warehouse-negative' : 'warehouse-positive' }}"><strong>{{ number_format($stock) }}</strong></td>
                    <td>
                      <strong>{{ number_format($reorder) }}</strong>
                    </td>
                    <td><span class="warehouse-status {{ $critical ? 'red' : 'yellow' }}"><i class="fa-solid fa-circle"></i>{{ $critical ? 'Out of Stock' : 'Low Stock' }}</span></td>
                  </tr>
                @empty
                  <tr><td colspan="5"><div class="warehouse-table-empty">No low-stock items.</div></td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </article>

        <article class="warehouse-panel warehouse-usage-panel" data-ajax-region="dashboard-top-issued">
          <header class="warehouse-panel-header warehouse-feature-header">
            <div class="warehouse-feature-heading">
              <span class="warehouse-feature-icon usage"><i class="fa-solid fa-chart-simple"></i></span>
              <div>
                <span class="warehouse-panel-eyebrow">MONTHLY USAGE</span>
                <h2>Most Issued Items</h2>
                <p>Items with the highest issuance in the selected period.</p>
              </div>
            </div>
            <span class="warehouse-panel-period">{{ $trendPeriodLabel ?? now()->format('F Y') }}</span>
          </header>
          <div class="warehouse-issued-ranking">
            @forelse($topIssuedItems as $item)
              <div class="warehouse-issued-row">
                <span class="warehouse-issued-rank">{{ $loop->iteration }}</span>
                <div class="warehouse-issued-copy">
                  <strong>{{ $item['item_name'] }}</strong>
                  <small>{{ $item['item_code'] }}</small>
                </div>
                <div class="warehouse-issued-meter">
                  <span style="width: {{ max(8, round(($item['total_issued'] / $maxIssued) * 100)) }}%"></span>
                </div>
                <strong class="warehouse-issued-total">{{ number_format($item['total_issued']) }}<small>pcs</small></strong>
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
          <header class="warehouse-panel-header warehouse-feature-header">
            <div class="warehouse-feature-heading">
              <span class="warehouse-feature-icon trend"><i class="fa-solid fa-arrow-trend-up"></i></span>
              <div>
                <span class="warehouse-panel-eyebrow">STOCK MOVEMENT TREND</span>
                <h2>Received vs Issued</h2>
                <p>Inventory movement trend over time.</p>
              </div>
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
