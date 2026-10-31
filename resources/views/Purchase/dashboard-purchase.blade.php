<x-layout.app
  title="FROMS - Purchase Dashboard"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Purchase/dashboard-purchase.css',
    'resources/css/Purchase/purchase-module-ui.css',
    'resources/js/Main-js/sidebar.js',
    'resources/js/Purchase/dashboard-purchase.js'
  ]"
>

  @php
    $totalRequests = $totalRequests ?? 0;
    $forPurchase = $forPurchase ?? 0;
    $activePurchaseOrders = $activePurchaseOrders ?? 0;
    $deliveredOrders = $deliveredOrders ?? 0;

    $ordered = $ordered ?? 0;
    $forPickup = $forPickup ?? 0;
    $forDelivery = $forDelivery ?? 0;

    $scheduledPurchases = $scheduledPurchases ?? 0;

    $recentPurchaseRequests = $recentPurchaseRequests ?? collect();
    $recentPurchaseOrders = $recentPurchaseOrders ?? collect();

    $totalSpend = $totalSpend ?? 0;
    $totalOrdersCount = $totalOrdersCount ?? 0;
    $avgOrderValue = $avgOrderValue ?? 0;
    $fulfillmentRate = $fulfillmentRate ?? 0;
    $totalPipeline = $totalPipeline ?? 0;

    $spendTrendMonthly = $spendTrendMonthly ?? [];
    $spendTrendWeekly = $spendTrendWeekly ?? [];
    $fulfillmentDistribution = $fulfillmentDistribution ?? [
      'labels' => ['For Purchase', 'Ordered', 'For Pick-up', 'For Delivery', 'Delivered'],
      'values' => [$forPurchase, $ordered, $forPickup, $forDelivery, $deliveredOrders],
      'colors' => ['#f59e0b', '#3b82f6', '#8b5cf6', '#06b6d4', '#10b981'],
    ];
    $topSuppliers = $topSuppliers ?? collect();
    $joRequestsCount = $joRequestsCount ?? 0;
    $restockRequestsCount = $restockRequestsCount ?? 0;
    $activeSchedulesCount = $activeSchedulesCount ?? 0;
    $scheduledDueSoon = $scheduledDueSoon ?? 0;
    $totalEstScheduledSpend = $totalEstScheduledSpend ?? 0;
  @endphp

  <div class="app purchase-dashboard-page">

    {{-- =====================================================
        SIDEBAR
    ====================================================== --}}
    <x-layout.sidebar department="Purchase" />

    <main class="main purchase-dashboard-main purchase-module-page records-page">

      {{-- =====================================================
          TOPBAR
      ====================================================== --}}
      <x-layout.topbar
        title="Purchase Dashboard"
        subtitle="Monitor purchase requests, purchase orders, financial spending, and scheduled procurement"
        notification-count="6"
      />

      {{-- =====================================================
          SUMMARY CARDS (TOP METRIC KPI ROW)
      ====================================================== --}}
      <section data-ajax-region="summary" class="stats-grid purchase-dashboard-stats">

        <x-ui.summary-card
          label="Purchase Requests"
          value="{{ $totalRequests }}"
          small="Active requests received"
          icon="fa-file-circle-check"
          color="blue"
        />

        <x-ui.summary-card
          label="For Purchase"
          value="{{ $forPurchase }}"
          small="Waiting for PO creation"
          icon="fa-cart-plus"
          color="yellow"
        />

        <x-ui.summary-card
          label="Active Purchase Orders"
          value="{{ $activePurchaseOrders }}"
          small="Orders in fulfillment"
          icon="fa-file-invoice-dollar"
          color="purple"
        />

        <x-ui.summary-card
          label="Delivered"
          value="{{ $deliveredOrders }}"
          small="Completed deliveries"
          icon="fa-circle-check"
          color="green"
        />

        <div class="stat-card purchase-spend-card">
          <div class="stat-icon purchase-spend-icon">
            <i class="fa-solid fa-peso-sign"></i>
          </div>
          <div class="purchase-spend-details">
            <span class="stat-label">Total Spend</span>
            <strong class="stat-value">₱{{ number_format($totalSpend, 2) }}</strong>
            <small class="stat-subtext">Avg ₱{{ number_format($avgOrderValue, 0) }} / order</small>
          </div>
        </div>

      </section>

      {{-- =====================================================
          CHARTS SECTION (CORE ANALYTICAL ROW)
      ====================================================== --}}
      <section class="purchase-analytics-grid mb-4">

        {{-- 1. PROCUREMENT SPEND & PO VOLUME TREND --}}
        <div class="purchase-dashboard-card purchase-chart-card">
          <div class="dashboard-card-header">
            <div>
              <span class="dashboard-eyebrow">FINANCIAL ANALYTICS</span>
              <h2>Procurement Spend & PO Volume</h2>
              <p>Expenditure tracking and purchase order issuance over time</p>
            </div>

            <div class="chart-header-actions">
              <div class="chart-kpi-pill">
                <i class="fa-solid fa-coins text-amber"></i>
                <span>Total: <strong>₱{{ number_format($totalSpend, 2) }}</strong></span>
              </div>
              <div class="chart-kpi-pill">
                <i class="fa-solid fa-file-invoice text-blue"></i>
                <span>Orders: <strong>{{ $totalOrdersCount }}</strong></span>
              </div>
              <div class="chart-group-selector-wrap">
                <select id="purchaseSpendGrouping" class="chart-group-select" aria-label="Select trend period grouping">
                  <option value="monthly">Monthly (6 mos)</option>
                  <option value="weekly">Weekly (6 wks)</option>
                </select>
              </div>
            </div>
          </div>

          <div class="purchase-chart-shell">
            <canvas
              id="purchaseSpendTrendChart"
              role="img"
              aria-label="Procurement spend and order volume trend"
              data-monthly="{{ json_encode($spendTrendMonthly) }}"
              data-weekly="{{ json_encode($spendTrendWeekly) }}"
            ></canvas>

            <div id="purchaseSpendEmpty" class="purchase-chart-empty" style="display: none;">
              <i class="fa-solid fa-chart-simple"></i>
              <strong>No procurement spend recorded</strong>
              <span>Spending trend will appear as purchase orders are placed.</span>
            </div>
          </div>
        </div>

        {{-- 2. ORDER FULFILLMENT & LIFECYCLE DISTRIBUTION --}}
        <div class="purchase-dashboard-card purchase-fulfillment-card">
          <div class="dashboard-card-header">
            <div>
              <span class="dashboard-eyebrow">LIFECYCLE PIPELINE</span>
              <h2>Fulfillment & Order Stages</h2>
              <p>Status breakdown from requisition to warehouse receipt</p>
            </div>

            <a href="{{ route('purchase-orders') }}" class="dashboard-view-link">
              <span>View Orders</span>
              <i class="fa-solid fa-arrow-right"></i>
            </a>
          </div>

          <div class="fulfillment-visual-wrap">
            {{-- Doughnut Chart Container --}}
            <div class="fulfillment-chart-container">
              <canvas
                id="purchaseFulfillmentChart"
                role="img"
                aria-label="Order fulfillment distribution chart"
                data-distribution="{{ json_encode($fulfillmentDistribution) }}"
              ></canvas>
              <div class="fulfillment-chart-center" aria-hidden="true">
                <strong>{{ $fulfillmentRate }}%</strong>
                <span>Fulfillment</span>
              </div>
            </div>

            {{-- Stage Status Breakdown Stack --}}
            <div class="fulfillment-status-stack">

              {{-- For Purchase --}}
              <div class="fulfillment-status-row">
                <div class="fulfillment-status-left">
                  <span class="status-indicator-dot dot-amber"></span>
                  <div class="status-name-desc">
                    <strong>For Purchase</strong>
                    <small>Awaiting PO generation</small>
                  </div>
                </div>
                <div class="fulfillment-status-right">
                  <span class="fulfillment-count">{{ $forPurchase }}</span>
                  <small>{{ $totalPipeline > 0 ? round(($forPurchase / $totalPipeline) * 100) : 0 }}%</small>
                </div>
              </div>

              {{-- Ordered --}}
              <div class="fulfillment-status-row">
                <div class="fulfillment-status-left">
                  <span class="status-indicator-dot dot-blue"></span>
                  <div class="status-name-desc">
                    <strong>Ordered</strong>
                    <small>Placed with suppliers</small>
                  </div>
                </div>
                <div class="fulfillment-status-right">
                  <span class="fulfillment-count">{{ $ordered }}</span>
                  <small>{{ $totalPipeline > 0 ? round(($ordered / $totalPipeline) * 100) : 0 }}%</small>
                </div>
              </div>

              {{-- For Pick-up --}}
              <div class="fulfillment-status-row">
                <div class="fulfillment-status-left">
                  <span class="status-indicator-dot dot-purple"></span>
                  <div class="status-name-desc">
                    <strong>For Pick-up</strong>
                    <small>Ready at vendor</small>
                  </div>
                </div>
                <div class="fulfillment-status-right">
                  <span class="fulfillment-count">{{ $forPickup }}</span>
                  <small>{{ $totalPipeline > 0 ? round(($forPickup / $totalPipeline) * 100) : 0 }}%</small>
                </div>
              </div>

              {{-- For Delivery --}}
              <div class="fulfillment-status-row">
                <div class="fulfillment-status-left">
                  <span class="status-indicator-dot dot-cyan"></span>
                  <div class="status-name-desc">
                    <strong>For Delivery</strong>
                    <small>Orders in transit</small>
                  </div>
                </div>
                <div class="fulfillment-status-right">
                  <span class="fulfillment-count">{{ $forDelivery }}</span>
                  <small>{{ $totalPipeline > 0 ? round(($forDelivery / $totalPipeline) * 100) : 0 }}%</small>
                </div>
              </div>

              {{-- Delivered --}}
              <div class="fulfillment-status-row">
                <div class="fulfillment-status-left">
                  <span class="status-indicator-dot dot-green"></span>
                  <div class="status-name-desc">
                    <strong>Delivered</strong>
                    <small>Warehouse confirmed</small>
                  </div>
                </div>
                <div class="fulfillment-status-right">
                  <span class="fulfillment-count text-green">{{ $deliveredOrders }}</span>
                  <small class="text-green">{{ $totalPipeline > 0 ? round(($deliveredOrders / $totalPipeline) * 100) : 0 }}%</small>
                </div>
              </div>

            </div>
          </div>
        </div>

      </section>

      {{-- =====================================================
          SUPPLIER ALLOCATION & REQUISITION SOURCES (ROW 2)
      ====================================================== --}}
      <section class="purchase-secondary-grid mb-4">

        {{-- TOP SUPPLIERS BY SPEND --}}
        <div class="purchase-dashboard-card supplier-card">
          <div class="dashboard-card-header">
            <div>
              <span class="dashboard-eyebrow">SUPPLIER ALLOCATION</span>
              <h2>Top Suppliers by Spend</h2>
              <p>Vendor distribution and order value concentration</p>
            </div>
            <a href="{{ route('purchase-orders') }}" class="dashboard-view-link">
              <span>All Vendors</span>
              <i class="fa-solid fa-arrow-right"></i>
            </a>
          </div>

          <div class="supplier-list">
            @forelse($topSuppliers as $supplier)
              <div class="supplier-row">
                <div class="supplier-info">
                  <div class="supplier-avatar">
                    <i class="fa-solid fa-building"></i>
                  </div>
                  <div class="supplier-meta">
                    <strong>{{ $supplier['name'] }}</strong>
                    <small>{{ $supplier['orders'] }} order{{ $supplier['orders'] === 1 ? '' : 's' }} issued</small>
                  </div>
                </div>

                <div class="supplier-spend">
                  <strong>₱{{ number_format($supplier['spend'], 2) }}</strong>
                  <div class="supplier-progress-bar" role="progressbar" aria-valuenow="{{ $supplier['share'] }}" aria-valuemin="0" aria-valuemax="100">
                    <span style="width: {{ $supplier['share'] }}%"></span>
                  </div>
                </div>
              </div>
            @empty
              <div class="dashboard-empty-state">
                <div class="dashboard-empty-icon">
                  <i class="fa-solid fa-building"></i>
                </div>
                <h3>No vendor records yet</h3>
                <p>Suppliers will appear here once purchase orders are created.</p>
              </div>
            @endforelse
          </div>
        </div>

        {{-- REQUISITION SOURCES & SCHEDULED PROCUREMENT --}}
        <div class="purchase-dashboard-card sources-card">
          <div class="dashboard-card-header">
            <div>
              <span class="dashboard-eyebrow">DEMAND & PLANNING</span>
              <h2>Requisition Sources & Schedules</h2>
              <p>Breakdown of incoming requisitions and active recurring orders</p>
            </div>
            <a href="{{ route('scheduled-purchase') }}" class="dashboard-view-link">
              <span>Schedules</span>
              <i class="fa-solid fa-arrow-right"></i>
            </a>
          </div>

          {{-- Source Breakdown Pills --}}
          <div class="requisition-source-cards">
            <div class="source-card">
              <div class="source-icon blue">
                <i class="fa-solid fa-wrench"></i>
              </div>
              <div class="source-details">
                <span class="source-label">Maintenance JOs</span>
                <strong class="source-val">{{ $joRequestsCount }}</strong>
                <small>Vehicle part requests</small>
              </div>
            </div>

            <div class="source-card">
              <div class="source-icon yellow">
                <i class="fa-solid fa-boxes-stacked"></i>
              </div>
              <div class="source-details">
                <span class="source-label">Warehouse Restocks</span>
                <strong class="source-val">{{ $restockRequestsCount }}</strong>
                <small>Inventory replenishment</small>
              </div>
            </div>
          </div>

          {{-- Scheduled Purchases Banner --}}
          <div class="scheduled-highlight-box">
            <div class="scheduled-highlight-header">
              <div class="scheduled-highlight-icon">
                <i class="fa-solid fa-calendar-check"></i>
              </div>
              <div class="scheduled-highlight-info">
                <div class="scheduled-title-row">
                  <strong>Recurring Scheduled Purchases</strong>
                  <span class="scheduled-status-pill {{ $scheduledDueSoon > 0 ? 'is-warning' : 'is-active' }}">
                    <i class="fa-solid fa-{{ $scheduledDueSoon > 0 ? 'clock' : 'circle-check' }}"></i>
                    {{ $scheduledPurchases }} Active
                  </span>
                </div>
                <p>Est. Recurring Value: <strong>₱{{ number_format($totalEstScheduledSpend, 2) }}</strong></p>
              </div>
            </div>

            <div class="scheduled-highlight-footer">
              @if($scheduledDueSoon > 0)
                <span class="due-soon-note text-amber">
                  <i class="fa-solid fa-triangle-exclamation"></i>
                  {{ $scheduledDueSoon }} schedule{{ $scheduledDueSoon === 1 ? '' : 's' }} due within 7 days
                </span>
              @else
                <span class="due-soon-note text-green">
                  <i class="fa-solid fa-check"></i>
                  All recurring procurement schedules on track
                </span>
              @endif
              <a href="{{ route('scheduled-purchase') }}" class="scheduled-action-btn">
                <span>Manage</span>
                <i class="fa-solid fa-chevron-right"></i>
              </a>
            </div>
          </div>
        </div>

      </section>

      {{-- =====================================================
          BOTTOM ROW (RECENT REQUESTS, RECENT ORDERS, ACTIONS)
      ====================================================== --}}
      <section class="purchase-bottom-grid">

        {{-- 1. RECENT MAINTENANCE REQUESTS --}}
        <div class="purchase-dashboard-card recent-request-card">
          <div class="dashboard-card-header">
            <div>
              <span class="dashboard-eyebrow">REQUEST ACTIVITY</span>
              <h2>Recent Maintenance Requests</h2>
              <p>Latest purchase requests received from Maintenance</p>
            </div>

            <a href="{{ route('maintenance-requests') }}" class="dashboard-view-link">
              <span>View All</span>
              <i class="fa-solid fa-arrow-right"></i>
            </a>
          </div>

          <div class="recent-purchase-list">
            @forelse($recentPurchaseRequests as $requestItem)
              @php
                $requestStatus = $requestItem->status ?? 'For Purchase';
                $requestStatusClass = match($requestStatus) {
                  'For Purchase' => 'for-purchase',
                  'Ordered' => 'ordered',
                  'For Pick-up' => 'for-pickup',
                  'For Delivery' => 'for-delivery',
                  'Delivered' => 'delivered',
                  'Picked Up' => 'picked-up',
                  default => 'default',
                };
              @endphp

              <div class="recent-purchase-item">
                <div class="recent-purchase-icon">
                  <i class="fa-solid fa-file-invoice"></i>
                </div>

                <div class="recent-purchase-content">
                  <div class="recent-purchase-heading">
                    <div>
                      <h3>{{ $requestItem->pr_no ?? 'Purchase Request' }}</h3>
                      <p>Job Order: {{ $requestItem->job_order_no ?? '—' }}</p>
                    </div>

                    <span class="purchase-dashboard-badge {{ $requestStatusClass }}">
                      {{ $requestStatus }}
                    </span>
                  </div>

                  <div class="recent-purchase-description">
                    {{ $requestItem->item ?? 'No requested item specified.' }}
                  </div>

                  <div class="recent-purchase-meta">
                    <span>
                      <i class="fa-solid fa-bus"></i>
                      {{ $requestItem->bus_no ?? 'No Bus' }}
                    </span>
                    <span>
                      <i class="fa-solid fa-cubes"></i>
                      Qty: {{ $requestItem->quantity ?? 0 }}
                    </span>
                  </div>
                </div>
              </div>
            @empty
              <div class="dashboard-empty-state">
                <div class="dashboard-empty-icon">
                  <i class="fa-solid fa-file-circle-check"></i>
                </div>
                <h3>No recent maintenance requests</h3>
                <p>Maintenance requests sent to Purchase will appear here.</p>
              </div>
            @endforelse
          </div>
        </div>

        {{-- 2. RECENT PURCHASE ORDERS --}}
        <div class="purchase-dashboard-card recent-order-card">
          <div class="dashboard-card-header">
            <div>
              <span class="dashboard-eyebrow">ORDER ACTIVITY</span>
              <h2>Recent Purchase Orders</h2>
              <p>Latest supplier purchase orders</p>
            </div>

            <a href="{{ route('purchase-orders') }}" class="dashboard-view-link">
              <span>View All</span>
              <i class="fa-solid fa-arrow-right"></i>
            </a>
          </div>

          <div class="recent-order-list">
            @forelse($recentPurchaseOrders as $order)
              <div class="recent-order-item">
                <div class="recent-order-icon">
                  <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>

                <div class="recent-order-details">
                  <div class="recent-order-heading">
                    <h3>{{ $order->po_no ?? 'Purchase Order' }}</h3>
                    @if(!empty($order->total_amount))
                      <strong>₱{{ number_format($order->total_amount, 2) }}</strong>
                    @endif
                  </div>

                  <p>{{ $order->supplier ?? 'No Supplier' }}</p>

                  <div class="recent-order-footer">
                    @if(!empty($order->po_date))
                      <span>
                        <i class="fa-regular fa-calendar"></i>
                        {{ \Carbon\Carbon::parse($order->po_date)->format('M d, Y') }}
                      </span>
                    @endif

                    @if(!empty($order->status))
                      <span class="po-status-tag status-{{ strtolower(str_replace(' ', '-', $order->status)) }}">
                        {{ $order->status }}
                      </span>
                    @endif
                  </div>
                </div>
              </div>
            @empty
              <div class="dashboard-empty-state">
                <div class="dashboard-empty-icon">
                  <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
                <h3>No purchase orders</h3>
                <p>Created purchase orders will appear here.</p>
              </div>
            @endforelse
          </div>
        </div>

        {{-- 3. QUICK ACTIONS --}}
        <div class="purchase-dashboard-card quick-actions-card">
          <div class="dashboard-card-header">
            <div>
              <span class="dashboard-eyebrow">QUICK ACCESS</span>
              <h2>Purchase Actions</h2>
              <p>Common purchasing tasks</p>
            </div>
          </div>

          <div class="purchase-quick-actions">
            <a href="{{ route('maintenance-requests') }}" class="purchase-quick-item">
              <span class="purchase-quick-icon blue">
                <i class="fa-solid fa-screwdriver-wrench"></i>
              </span>
              <div>
                <h3>Maintenance Requests</h3>
                <p>Review Maintenance purchase requests</p>
              </div>
              <i class="fa-solid fa-chevron-right"></i>
            </a>

            <a href="{{ route('inventory-restock') }}" class="purchase-quick-item">
              <span class="purchase-quick-icon yellow">
                <i class="fa-solid fa-boxes-stacked"></i>
              </span>
              <div>
                <h3>Inventory Restock</h3>
                <p>Review Warehouse restock requests</p>
              </div>
              <i class="fa-solid fa-chevron-right"></i>
            </a>

            <a href="{{ route('purchase-orders') }}" class="purchase-quick-item">
              <span class="purchase-quick-icon green">
                <i class="fa-solid fa-file-invoice-dollar"></i>
              </span>
              <div>
                <h3>Purchase Orders</h3>
                <p>Manage supplier orders & deliveries</p>
              </div>
              <i class="fa-solid fa-chevron-right"></i>
            </a>

            <a href="{{ route('scheduled-purchase') }}" class="purchase-quick-item">
              <span class="purchase-quick-icon purple">
                <i class="fa-solid fa-calendar-check"></i>
              </span>
              <div>
                <h3>Scheduled Purchase</h3>
                <p>Review upcoming recurring orders</p>
              </div>
              <i class="fa-solid fa-chevron-right"></i>
            </a>
          </div>
        </div>

      </section>

    </main>
  </div>

</x-layout.app>
