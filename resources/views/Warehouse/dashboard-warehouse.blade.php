<x-layout.app
  title="FROMS - Warehouse Dashboard"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Warehouse/dashboard-warehouse.css',
    'resources/js/Main-js/sidebar.js'
  ]"
>

  @php
    $totalInventory = $totalInventory ?? 0;
    $lowStockItems = $lowStockItems ?? 0;
    $pendingPartRequests = $pendingPartRequests ?? 0;
    $incomingDeliveries = $incomingDeliveries ?? 0;

    $availableStock = $availableStock ?? 0;
    $outOfStock = $outOfStock ?? 0;
    $issuedToday = $issuedToday ?? 0;

    $recentInventoryItems = $recentInventoryItems ?? collect();
    $recentPartRequests = $recentPartRequests ?? collect();
    $activePartRequests = $activePartRequests ?? collect();
    $expectedDeliveries = $expectedDeliveries ?? collect();
    $criticalStockItems = $criticalStockItems ?? collect();
    $recentStockMovements = $recentStockMovements ?? collect();
  @endphp

  <div class="app warehouse-dashboard-page">
    <x-layout.sidebar department="Warehouse" />

    <main class="main warehouse-dashboard-main">
      <x-layout.topbar
        title="Warehouse Dashboard"
        subtitle="Monitor inventory health, urgent part requisitions, deliveries, and stock transactions"
        notification-count="6"
      />

      <section data-ajax-region="summary" class="stats-grid warehouse-stats-grid">
        <x-ui.summary-card label="Inventory Items" value="{{ $totalInventory }}" small="Registered stock items" icon="fa-boxes-stacked" color="blue" />
        <x-ui.summary-card label="Stockout & Low Stock" value="{{ $lowStockItems + $outOfStock }}" small="Needs replenishment" icon="fa-triangle-exclamation" color="red" />
        <x-ui.summary-card label="Part Requests" value="{{ $pendingPartRequests }}" small="Waiting for warehouse action" icon="fa-clipboard-list" color="yellow" />
        <x-ui.summary-card label="Incoming Deliveries" value="{{ $incomingDeliveries }}" small="Expected shipments" icon="fa-truck-ramp-box" color="green" />
      </section>

      {{-- Row 1: Primary Action Queues (Part Requests from Maintenance & Incoming Deliveries from Purchase) --}}
      <section class="warehouse-dashboard-grid">
        {{-- Card 1: Maintenance Part Requests Queue --}}
        <div class="warehouse-dashboard-card">
          <div class="dashboard-card-header">
            <div>
              <span class="dashboard-eyebrow">MAINTENANCE REQUISITIONS</span>
              <h2>Active Part Requests</h2>
              <p>Job order part requests awaiting warehouse issuance.</p>
            </div>
            <a href="{{ route('part-requests') }}" class="dashboard-view-link">View All <i class="fa-solid fa-arrow-right"></i></a>
          </div>

          <div class="warehouse-action-list">
            @forelse($activePartRequests as $req)
              @php
                $status = $req->status ?? 'Approved';
                $statusClass = match($status) {
                  'Approved' => 'status-approved',
                  'For Purchase' => 'status-for-purchase',
                  'Ordered' => 'status-ordered',
                  'For Delivery', 'For Pick-up' => 'status-delivery',
                  default => 'status-default',
                };
              @endphp
              <div class="warehouse-action-row">
                <div class="warehouse-action-icon yellow">
                  <i class="fa-solid fa-clipboard-list"></i>
                </div>
                <div class="warehouse-action-body">
                  <div class="warehouse-action-title-row">
                    <span class="action-ref-badge">{{ $req->pr_no }}</span>
                    @if($req->job_order_no)
                      <span class="action-sub-badge"><i class="fa-solid fa-wrench"></i> {{ $req->job_order_no }}</span>
                    @endif
                    @if($req->bus_no)
                      <span class="action-sub-badge bus-badge"><i class="fa-solid fa-bus"></i> {{ $req->bus_no }}</span>
                    @endif
                  </div>
                  <div class="warehouse-action-desc">
                    <strong>{{ $req->item }}</strong>
                  </div>
                </div>
                <div class="warehouse-action-meta">
                  <span class="action-status-pill {{ $statusClass }}">{{ $status }}</span>
                  <a href="{{ route('part-requests', ['search' => $req->pr_no]) }}" class="warehouse-row-btn" title="Review part request">
                    Review <i class="fa-solid fa-chevron-right"></i>
                  </a>
                </div>
              </div>
            @empty
              <div class="dashboard-empty-state">
                <div class="dashboard-empty-icon"><i class="fa-solid fa-clipboard-check"></i></div>
                <h3>No pending part requests</h3>
                <p>All maintenance job order requisitions are currently fulfilled.</p>
              </div>
            @endforelse
          </div>
        </div>

        {{-- Card 2: Expected Incoming Deliveries Queue --}}
        <div class="warehouse-dashboard-card">
          <div class="dashboard-card-header">
            <div>
              <span class="dashboard-eyebrow">PURCHASE SHIPMENTS</span>
              <h2>Incoming Deliveries</h2>
              <p>Purchase orders in transit or ready for warehouse receiving.</p>
            </div>
            <a href="{{ route('incoming-deliveries') }}" class="dashboard-view-link">View All <i class="fa-solid fa-arrow-right"></i></a>
          </div>

          <div class="warehouse-action-list">
            @forelse($expectedDeliveries as $delivery)
              @php
                $items = collect($delivery->items ?? []);
                $itemsText = $items->map(function ($i) {
                  $desc = $i['item_description'] ?? $i['item'] ?? 'Item';
                  $qty = $i['quantity'] ?? 1;
                  return "{$desc} (Qty: {$qty})";
                })->take(2)->implode(', ');
                if ($items->count() > 2) {
                  $itemsText .= ' +' . ($items->count() - 2) . ' more';
                }
                $delStatus = $delivery->status ?? 'For Delivery';
                $delStatusClass = match($delStatus) {
                  'For Delivery' => 'status-for-delivery',
                  'For Pick-up' => 'status-for-pickup',
                  default => 'status-default',
                };
              @endphp
              <div class="warehouse-action-row">
                <div class="warehouse-action-icon green">
                  <i class="fa-solid fa-truck-ramp-box"></i>
                </div>
                <div class="warehouse-action-body">
                  <div class="warehouse-action-title-row">
                    <span class="action-ref-badge">{{ $delivery->po_no }}</span>
                    <span class="action-supplier-name"><i class="fa-solid fa-building"></i> {{ $delivery->supplier_name ?? 'Supplier' }}</span>
                  </div>
                  <div class="warehouse-action-desc">
                    <span>{{ $itemsText ?: 'Supplies & Parts' }}</span>
                  </div>
                </div>
                <div class="warehouse-action-meta">
                  <span class="action-status-pill {{ $delStatusClass }}">{{ $delStatus }}</span>
                  <a href="{{ route('incoming-deliveries', ['search' => $delivery->po_no]) }}" class="warehouse-row-btn receive-btn" title="Receive delivery">
                    Receive <i class="fa-solid fa-chevron-right"></i>
                  </a>
                </div>
              </div>
            @empty
              <div class="dashboard-empty-state">
                <div class="dashboard-empty-icon"><i class="fa-solid fa-truck"></i></div>
                <h3>No incoming deliveries</h3>
                <p>All expected purchase orders have been received and posted.</p>
              </div>
            @endforelse
          </div>
        </div>
      </section>

      {{-- Row 2: Secondary Operational Overview (Stock Health & Replenishment Watchlist + Recent Stock Movements) --}}
      <section class="warehouse-dashboard-grid">
        {{-- Card 3: Stock Status & Replenishment Watchlist --}}
        <div class="warehouse-dashboard-card">
          <div class="dashboard-card-header">
            <div>
              <span class="dashboard-eyebrow">INVENTORY HEALTH</span>
              <h2>Stock Status & Alerts</h2>
              <p>Availability levels and items requiring replenishment.</p>
            </div>
            <a href="{{ route('inventory') }}" class="dashboard-view-link">View Inventory <i class="fa-solid fa-arrow-right"></i></a>
          </div>

          {{-- Stock Breakdown Pill Strip --}}
          <div class="warehouse-stock-summary-strip">
            <div class="stock-summary-pill available">
              <span class="stock-pill-dot"></span>
              <span class="stock-pill-label">Available</span>
              <strong>{{ $availableStock }}</strong>
            </div>
            <div class="stock-summary-pill low">
              <span class="stock-pill-dot"></span>
              <span class="stock-pill-label">Low Stock</span>
              <strong>{{ $lowStockItems }}</strong>
            </div>
            <div class="stock-summary-pill critical">
              <span class="stock-pill-dot"></span>
              <span class="stock-pill-label">Out of Stock</span>
              <strong>{{ $outOfStock }}</strong>
            </div>
          </div>

          {{-- Critical & Low Stock Alert List --}}
          <div class="warehouse-action-list">
            @forelse($criticalStockItems as $item)
              @php
                $qty = (int) ($item->on_hand ?? $item->quantity_available ?? 0);
                $reorder = (int) ($item->reorder_level ?? 0);
                $isCritical = $qty <= 0;
              @endphp
              <div class="warehouse-action-row">
                <div class="warehouse-action-icon {{ $isCritical ? 'red' : 'yellow' }}">
                  <i class="fa-solid {{ $isCritical ? 'fa-circle-xmark' : 'fa-triangle-exclamation' }}"></i>
                </div>
                <div class="warehouse-action-body">
                  <div class="warehouse-action-title-row">
                    <strong>{{ $item->item_name ?? $item->parts_name ?? 'Inventory Item' }}</strong>
                    <span class="action-sub-badge">{{ $item->item_code ?? 'SKU' }}</span>
                  </div>
                  <div class="warehouse-action-desc">
                    <span>On Hand: <strong>{{ $qty }}</strong> {{ $item->unit ?? 'units' }} &bull; Min Reorder: <strong>{{ $reorder }}</strong></span>
                  </div>
                </div>
                <div class="warehouse-action-meta">
                  <span class="action-status-pill {{ $isCritical ? 'status-critical' : 'status-low' }}">
                    {{ $isCritical ? 'Out of Stock' : 'Low Stock' }}
                  </span>
                  <a href="{{ route('inventory', ['search' => $item->item_code ?? $item->item_name]) }}" class="warehouse-row-btn" title="Inspect inventory item">
                    Details <i class="fa-solid fa-chevron-right"></i>
                  </a>
                </div>
              </div>
            @empty
              <div class="dashboard-empty-state">
                <div class="dashboard-empty-icon"><i class="fa-solid fa-circle-check"></i></div>
                <h3>All items adequately stocked</h3>
                <p>No inventory items are currently at or below reorder thresholds.</p>
              </div>
            @endforelse
          </div>
        </div>

        {{-- Card 4: Recent Stock Movements Audit Trail --}}
        <div class="warehouse-dashboard-card">
          <div class="dashboard-card-header">
            <div>
              <span class="dashboard-eyebrow">TRANSACTION AUDIT</span>
              <h2>Recent Stock Movements</h2>
              <p>Real-time log of stock receipts, issuances, and adjustments.</p>
            </div>
            <a href="{{ route('stock-movements') }}" class="dashboard-view-link">View All <i class="fa-solid fa-arrow-right"></i></a>
          </div>

          <div class="warehouse-action-list">
            @forelse($recentStockMovements as $movement)
              @php
                $type = $movement->movement_type ?? 'Stock Movement';
                $isStockIn = str_contains(strtolower($type), 'in');
                $isStockOut = str_contains(strtolower($type), 'out');
                $iconClass = $isStockIn ? 'green' : ($isStockOut ? 'blue' : 'yellow');
                $icon = $isStockIn ? 'fa-arrow-down' : ($isStockOut ? 'fa-arrow-up' : 'fa-sliders');
                $qtyChange = (int) ($movement->quantity_change ?? 0);
                $qtySign = $qtyChange > 0 ? "+{$qtyChange}" : "{$qtyChange}";
              @endphp
              <div class="warehouse-action-row">
                <div class="warehouse-action-icon {{ $iconClass }}">
                  <i class="fa-solid {{ $icon }}"></i>
                </div>
                <div class="warehouse-action-body">
                  <div class="warehouse-action-title-row">
                    <strong>{{ $movement->item_name ?? 'Item' }}</strong>
                    @if($movement->reference_no)
                      <span class="action-sub-badge"><i class="fa-solid fa-receipt"></i> {{ $movement->reference_no }}</span>
                    @endif
                  </div>
                  <div class="warehouse-action-desc">
                    <span>
                      Qty: <strong class="{{ $isStockIn ? 'text-green' : 'text-blue' }}">{{ $qtySign }}</strong>
                      &bull; Balance: {{ $movement->new_stock ?? 0 }} {{ $movement->unit ?? 'units' }}
                      &bull; <time>{{ $movement->created_at?->diffForHumans() ?? 'Recently' }}</time>
                    </span>
                  </div>
                </div>
                <div class="warehouse-action-meta">
                  <span class="action-status-pill {{ $isStockIn ? 'status-stock-in' : ($isStockOut ? 'status-stock-out' : 'status-adjustment') }}">
                    {{ $type }}
                  </span>
                </div>
              </div>
            @empty
              <div class="dashboard-empty-state">
                <div class="dashboard-empty-icon"><i class="fa-solid fa-right-left"></i></div>
                <h3>No stock movements recorded</h3>
                <p>Transactions will appear here as items are received or issued.</p>
              </div>
            @endforelse
          </div>
        </div>
      </section>
    </main>
  </div>
</x-layout.app>
