<x-layout.app
  title="FROMS - Purchase Dashboard"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Purchase/dashboard-purchase.css',
    'resources/js/Main-js/sidebar.js'
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
  @endphp


  <div class="app purchase-dashboard-page">

    {{-- =====================================================
        SIDEBAR
    ====================================================== --}}
    <x-layout.sidebar
      department="Purchase"
      subtitle="Department Module"
      icon="fa-cart-shopping"
      user-name="P. Admin"
      user-role="Purchase Admin"
      :items="[
        [
          'label' => 'Dashboard',
          'route' => 'dashboard-purchase',
          'icon' => 'fa-table-cells-large'
        ],
        [
          'label' => 'Purchase Orders',
          'route' => 'purchase-orders',
          'icon' => 'fa-file-invoice'
        ],
        [
          'label' => 'Requested Purchase',
          'icon' => 'fa-clipboard-list',
          'children' => [
            [
              'label' => 'Maintenance Requests',
              'route' => 'maintenance-requests',
              'icon' => 'fa-screwdriver-wrench'
            ],
            [
              'label' => 'Inventory Restock',
              'route' => 'inventory-restock',
              'icon' => 'fa-boxes-stacked'
            ],
          ],
        ],
        [
          'label' => 'Scheduled Purchase',
          'route' => 'scheduled-purchase',
          'icon' => 'fa-calendar-check'
        ],
      ]"
    />


    <main class="main purchase-dashboard-main">

      {{-- =====================================================
          TOPBAR
      ====================================================== --}}
      <x-layout.topbar
        title="Purchase Dashboard"
        subtitle="Monitor purchase requests, purchase orders, and scheduled procurement"
        notification-count="6"
      />


      {{-- =====================================================
          SUMMARY CARDS
      ====================================================== --}}
      <section class="stats-grid purchase-dashboard-stats">

        <x-ui.summary-card
          label="Purchase Requests"
          value="{{ $totalRequests }}"
          small="Requests received"
          icon="fa-file-circle-check"
          color="blue"
        />

        <x-ui.summary-card
          label="For Purchase"
          value="{{ $forPurchase }}"
          small="Waiting for purchasing"
          icon="fa-cart-plus"
          color="yellow"
        />

        <x-ui.summary-card
          label="Active Purchase Orders"
          value="{{ $activePurchaseOrders }}"
          small="Orders being processed"
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

      </section>


      {{-- =====================================================
          MAIN ROW
      ====================================================== --}}
      <section class="purchase-dashboard-grid">

        {{-- =================================================
            PURCHASE WORKFLOW
        ================================================== --}}
        <div class="purchase-dashboard-card">

          <div class="dashboard-card-header">

            <div>
              <span class="dashboard-eyebrow">
                PROCUREMENT STATUS
              </span>

              <h2>
                Purchase Workflow
              </h2>

              <p>
                Current purchasing progress from order to delivery.
              </p>
            </div>


            <a
              href="{{ route('maintenance-requests') }}"
              class="dashboard-view-link"
            >
              View Requests

              <i class="fa-solid fa-arrow-right"></i>
            </a>

          </div>


          <div class="purchase-status-list">

            {{-- FOR PURCHASE --}}
            <div class="purchase-status-row">

              <div class="purchase-status-info">

                <span class="purchase-status-icon purchase">
                  <i class="fa-solid fa-cart-plus"></i>
                </span>

                <div>
                  <h3>
                    For Purchase
                  </h3>

                  <p>
                    Approved requests ready for procurement
                  </p>
                </div>

              </div>

              <strong>
                {{ $forPurchase }}
              </strong>

            </div>


            {{-- ORDERED --}}
            <div class="purchase-status-row">

              <div class="purchase-status-info">

                <span class="purchase-status-icon ordered">
                  <i class="fa-solid fa-box"></i>
                </span>

                <div>
                  <h3>
                    Ordered
                  </h3>

                  <p>
                    Purchase orders placed with supplier
                  </p>
                </div>

              </div>

              <strong>
                {{ $ordered }}
              </strong>

            </div>


            {{-- FOR PICK-UP --}}
            <div class="purchase-status-row">

              <div class="purchase-status-info">

                <span class="purchase-status-icon pickup">
                  <i class="fa-solid fa-box-open"></i>
                </span>

                <div>
                  <h3>
                    For Pick-up
                  </h3>

                  <p>
                    Orders ready for collection
                  </p>
                </div>

              </div>

              <strong>
                {{ $forPickup }}
              </strong>

            </div>


            {{-- FOR DELIVERY --}}
            <div class="purchase-status-row">

              <div class="purchase-status-info">

                <span class="purchase-status-icon delivery">
                  <i class="fa-solid fa-truck"></i>
                </span>

                <div>
                  <h3>
                    For Delivery
                  </h3>

                  <p>
                    Orders currently in transit
                  </p>
                </div>

              </div>

              <strong>
                {{ $forDelivery }}
              </strong>

            </div>

          </div>

        </div>


        {{-- =================================================
            PURCHASE ORDERS
        ================================================== --}}
        <div class="purchase-dashboard-card">

          <div class="dashboard-card-header">

            <div>
              <span class="dashboard-eyebrow">
                PURCHASE ORDERS
              </span>

              <h2>
                Order Overview
              </h2>

              <p>
                Current purchase order activity.
              </p>
            </div>


            <a
              href="{{ route('purchase-orders') }}"
              class="dashboard-view-link"
            >
              View Orders

              <i class="fa-solid fa-arrow-right"></i>
            </a>

          </div>


          <div class="dashboard-mini-grid">

            <div class="dashboard-mini-card">

              <div class="dashboard-mini-icon blue">
                <i class="fa-solid fa-file-invoice-dollar"></i>
              </div>

              <div>
                <span>
                  Active Orders
                </span>

                <strong>
                  {{ $activePurchaseOrders }}
                </strong>

                <p>
                  Currently being processed
                </p>
              </div>

            </div>


            <div class="dashboard-mini-card">

              <div class="dashboard-mini-icon green">
                <i class="fa-solid fa-circle-check"></i>
              </div>

              <div>
                <span>
                  Delivered
                </span>

                <strong>
                  {{ $deliveredOrders }}
                </strong>

                <p>
                  Completed orders
                </p>
              </div>

            </div>

          </div>


          <div class="scheduled-purchase-summary">

            <div class="scheduled-purchase-icon">
              <i class="fa-solid fa-calendar-check"></i>
            </div>

            <div class="scheduled-purchase-content">
              <span>
                Scheduled Purchases
              </span>

              <strong>
                {{ $scheduledPurchases }}
              </strong>

              <p>
                Upcoming scheduled procurement
              </p>
            </div>

            <a href="{{ route('scheduled-purchase') }}">
              <i class="fa-solid fa-chevron-right"></i>
            </a>

          </div>

        </div>

      </section>


      {{-- =====================================================
          SECOND ROW
      ====================================================== --}}
      <section class="purchase-bottom-grid">

        {{-- =================================================
            RECENT PURCHASE REQUESTS
        ================================================== --}}
        <div class="purchase-dashboard-card recent-request-card">

          <div class="dashboard-card-header">

            <div>
              <span class="dashboard-eyebrow">
                REQUEST ACTIVITY
              </span>

              <h2>
                Recent Maintenance Requests
              </h2>

              <p>
                Latest purchase requests received from Maintenance.
              </p>
            </div>


            <a
              href="{{ route('maintenance-requests') }}"
              class="dashboard-view-link"
            >
              View All

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
                      <h3>
                        {{ $requestItem->pr_no ?? 'Purchase Request' }}
                      </h3>

                      <p>
                        Job Order:
                        {{ $requestItem->job_order_no ?? '—' }}
                      </p>
                    </div>


                    <span
                      class="purchase-dashboard-badge {{ $requestStatusClass }}"
                    >
                      {{ $requestStatus }}
                    </span>

                  </div>


                  <div class="recent-purchase-description">
                    {{
                      $requestItem->item
                      ?? 'No requested item specified.'
                    }}
                  </div>


                  <div class="recent-purchase-meta">

                    <span>
                      <i class="fa-solid fa-bus"></i>

                      {{ $requestItem->bus_no ?? 'No Bus' }}
                    </span>

                    <span>
                      <i class="fa-solid fa-cubes"></i>

                      Qty:
                      {{ $requestItem->quantity ?? 0 }}
                    </span>

                  </div>

                </div>

              </div>


            @empty

              <div class="dashboard-empty-state">

                <div class="dashboard-empty-icon">
                  <i class="fa-solid fa-file-circle-check"></i>
                </div>

                <h3>
                  No recent maintenance requests
                </h3>

                <p>
                  Maintenance requests sent to Purchase will appear here.
                </p>

              </div>

            @endforelse

          </div>

        </div>


        {{-- =================================================
            RECENT PURCHASE ORDERS
        ================================================== --}}
        <div class="purchase-dashboard-card">

          <div class="dashboard-card-header">

            <div>
              <span class="dashboard-eyebrow">
                ORDER ACTIVITY
              </span>

              <h2>
                Recent Purchase Orders
              </h2>

              <p>
                Latest supplier purchase orders.
              </p>
            </div>


            <a
              href="{{ route('purchase-orders') }}"
              class="dashboard-view-link"
            >
              View All

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

                    <h3>
                      {{ $order->po_no ?? 'Purchase Order' }}
                    </h3>


                    @if(!empty($order->total_amount))

                      <strong>
                        ₱{{ number_format($order->total_amount, 2) }}
                      </strong>

                    @endif

                  </div>


                  <p>
                    {{ $order->supplier ?? 'No Supplier' }}
                  </p>


                  @if(!empty($order->po_date))

                    <span>

                      <i class="fa-regular fa-calendar"></i>

                      {{
                        \Carbon\Carbon::parse(
                          $order->po_date
                        )->format('M d, Y')
                      }}

                    </span>

                  @endif

                </div>

              </div>


            @empty

              <div class="dashboard-empty-state">

                <div class="dashboard-empty-icon">
                  <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>

                <h3>
                  No purchase orders
                </h3>

                <p>
                  Created purchase orders will appear here.
                </p>

              </div>

            @endforelse

          </div>

        </div>


        {{-- =================================================
            QUICK ACTIONS
        ================================================== --}}
        <div class="purchase-dashboard-card">

          <div class="dashboard-card-header">

            <div>
              <span class="dashboard-eyebrow">
                QUICK ACCESS
              </span>

              <h2>
                Purchase Actions
              </h2>

              <p>
                Common purchasing tasks.
              </p>
            </div>

          </div>


          <div class="purchase-quick-actions">

            <a
              href="{{ route('maintenance-requests') }}"
              class="purchase-quick-item"
            >

              <span class="purchase-quick-icon blue">
                <i class="fa-solid fa-screwdriver-wrench"></i>
              </span>

              <div>
                <h3>
                  Maintenance Requests
                </h3>

                <p>
                  Review Maintenance purchase requests
                </p>
              </div>

              <i class="fa-solid fa-chevron-right"></i>

            </a>


            <a
              href="{{ route('inventory-restock') }}"
              class="purchase-quick-item"
            >

              <span class="purchase-quick-icon yellow">
                <i class="fa-solid fa-boxes-stacked"></i>
              </span>

              <div>
                <h3>
                  Inventory Restock
                </h3>

                <p>
                  Review Warehouse restock requests
                </p>
              </div>

              <i class="fa-solid fa-chevron-right"></i>

            </a>


            <a
              href="{{ route('purchase-orders') }}"
              class="purchase-quick-item"
            >

              <span class="purchase-quick-icon green">
                <i class="fa-solid fa-file-invoice-dollar"></i>
              </span>

              <div>
                <h3>
                  Purchase Orders
                </h3>

                <p>
                  Manage supplier orders
                </p>
              </div>

              <i class="fa-solid fa-chevron-right"></i>

            </a>


            <a
              href="{{ route('scheduled-purchase') }}"
              class="purchase-quick-item"
            >

              <span class="purchase-quick-icon yellow">
                <i class="fa-solid fa-calendar-check"></i>
              </span>

              <div>
                <h3>
                  Scheduled Purchase
                </h3>

                <p>
                  Review upcoming purchases
                </p>
              </div>

              <i class="fa-solid fa-chevron-right"></i>

            </a>

          </div>

        </div>

      </section>

    </main>

  </div>

</x-layout.app>