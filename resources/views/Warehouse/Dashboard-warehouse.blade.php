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
  @endphp>


  <div class="app warehouse-dashboard-page">

    {{-- =====================================================
        SIDEBAR
    ====================================================== --}}
    <x-layout.sidebar
      department="Warehouse"
      subtitle="Warehouse Module"
      icon="fa-warehouse"
      :items="[
        [
    'label' => 'Dashboard',
    'route' => 'warehouse.dashboard',
    'icon' => 'fa-table-cells-large',
],
        [
          'label' => 'Inventory',
          'route' => 'inventory',
          'icon' => 'fa-boxes-stacked'
        ],
        [
          'label' => 'Part Requests',
          'route' => 'part-requests',
          'icon' => 'fa-clipboard-list'
        ],
        [
          'label' => 'Incoming Deliveries',
          'route' => 'incoming-deliveries',
          'icon' => 'fa-truck-ramp-box'
        ],
        [
          'label' => 'Stock Movements',
          'route' => 'stock-movements',
          'icon' => 'fa-right-left'
        ],
      ]"
    />


    <main class="main warehouse-dashboard-main">

      {{-- =====================================================
          TOPBAR
      ====================================================== --}}
      <x-layout.topbar
        title="Warehouse Dashboard"
        subtitle="Monitor inventory, part requests, deliveries, and stock movements"
        notification-count="6"
      />


      {{-- =====================================================
          SUMMARY CARDS
      ====================================================== --}}
      <section class="stats-grid warehouse-stats-grid">

        <x-ui.summary-card
          label="Inventory Items"
          value="{{ $totalInventory }}"
          small="Registered stock items"
          icon="fa-boxes-stacked"
          color="blue"
        />

        <x-ui.summary-card
          label="Low Stock"
          value="{{ $lowStockItems }}"
          small="Needs replenishment"
          icon="fa-triangle-exclamation"
          color="red"
        />

        <x-ui.summary-card
          label="Part Requests"
          value="{{ $pendingPartRequests }}"
          small="Waiting for warehouse action"
          icon="fa-clipboard-list"
          color="yellow"
        />

        <x-ui.summary-card
          label="Incoming Deliveries"
          value="{{ $incomingDeliveries }}"
          small="Expected deliveries"
          icon="fa-truck-ramp-box"
          color="green"
        />

      </section>


      {{-- =====================================================
          MAIN GRID
      ====================================================== --}}
      <section class="warehouse-dashboard-grid">

        {{-- =================================================
            INVENTORY OVERVIEW
        ================================================== --}}
        <div class="warehouse-dashboard-card">

          <div class="dashboard-card-header">

            <div>
              <span class="dashboard-eyebrow">
                INVENTORY STATUS
              </span>

              <h2>
                Stock Overview
              </h2>

              <p>
                Current warehouse inventory availability.
              </p>
            </div>


            <a
              href="{{ route('inventory') }}"
              class="dashboard-view-link"
            >
              View Inventory

              <i class="fa-solid fa-arrow-right"></i>
            </a>

          </div>


          <div class="warehouse-status-list">

            <div class="warehouse-status-row">

              <div class="warehouse-status-info">

                <span class="warehouse-status-icon available">

                  <i class="fa-solid fa-circle-check"></i>

                </span>

                <div>

                  <h3>
                    Available Stock
                  </h3>

                  <p>
                    Items currently available
                  </p>

                </div>

              </div>


              <strong>
                {{ $availableStock }}
              </strong>

            </div>


            <div class="warehouse-status-row">

              <div class="warehouse-status-info">

                <span class="warehouse-status-icon low">

                  <i class="fa-solid fa-triangle-exclamation"></i>

                </span>

                <div>

                  <h3>
                    Low Stock
                  </h3>

                  <p>
                    Items below reorder level
                  </p>

                </div>

              </div>


              <strong>
                {{ $lowStockItems }}
              </strong>

            </div>


            <div class="warehouse-status-row">

              <div class="warehouse-status-info">

                <span class="warehouse-status-icon out">

                  <i class="fa-solid fa-circle-xmark"></i>

                </span>

                <div>

                  <h3>
                    Out of Stock
                  </h3>

                  <p>
                    Items with no available quantity
                  </p>

                </div>

              </div>


              <strong>
                {{ $outOfStock }}
              </strong>

            </div>

          </div>

        </div>


        {{-- =================================================
            PART REQUESTS
        ================================================== --}}
        <div class="warehouse-dashboard-card">

          <div class="dashboard-card-header">

            <div>
              <span class="dashboard-eyebrow">
                REQUESTS
              </span>

              <h2>
                Part Request Overview
              </h2>

              <p>
                Current requests received from Maintenance.
              </p>
            </div>


            <a
              href="{{ route('part-requests') }}"
              class="dashboard-view-link"
            >
              View Requests

              <i class="fa-solid fa-arrow-right"></i>
            </a>

          </div>


          <div class="dashboard-mini-grid">

            <div class="dashboard-mini-card">

              <div class="dashboard-mini-icon yellow">

                <i class="fa-solid fa-clock"></i>

              </div>

              <div>

                <span>
                  Pending
                </span>

                <strong>
                  {{ $pendingPartRequests }}
                </strong>

                <p>
                  Waiting for review
                </p>

              </div>

            </div>


            <div class="dashboard-mini-card">

              <div class="dashboard-mini-icon green">

                <i class="fa-solid fa-box-open"></i>

              </div>

              <div>

                <span>
                  Issued Today
                </span>

                <strong>
                  {{ $issuedToday }}
                </strong>

                <p>
                  Released from inventory
                </p>

              </div>

            </div>

          </div>

        </div>

      </section>


      {{-- =====================================================
          SECOND ROW
      ====================================================== --}}
      <section class="warehouse-bottom-grid">

        {{-- =================================================
            RECENT INVENTORY
        ================================================== --}}
        <div class="warehouse-dashboard-card recent-inventory-card">

          <div class="dashboard-card-header">

            <div>
              <span class="dashboard-eyebrow">
                INVENTORY ACTIVITY
              </span>

              <h2>
                Recent Inventory Items
              </h2>

              <p>
                Recently added or updated warehouse items.
              </p>
            </div>


            <a
              href="{{ route('inventory') }}"
              class="dashboard-view-link"
            >
              View All

              <i class="fa-solid fa-arrow-right"></i>
            </a>

          </div>


          <div class="recent-warehouse-list">

            @forelse($recentInventoryItems as $item)

              <div class="recent-warehouse-item">

                <div class="recent-warehouse-icon">

                  <i class="fa-solid fa-box"></i>

                </div>


                <div class="recent-warehouse-content">

                  <div class="recent-warehouse-heading">

                    <div>

                      <h3>
                        {{ $item->item_name ?? $item->name ?? 'Inventory Item' }}
                      </h3>

                      <p>
                        {{
                          $item->item_code
                          ?? $item->sku
                          ?? 'No Item Code'
                        }}
                      </p>

                    </div>


                    @php
                      $quantity = $item->quantity ?? 0;
                      $reorderLevel = $item->reorder_level ?? 0;

                      $inventoryStatus = $quantity <= 0
                        ? 'out'
                        : ($quantity <= $reorderLevel ? 'low' : 'available');
                    @endphp


                    <span class="inventory-status {{ $inventoryStatus }}">

                      @if($inventoryStatus === 'out')
                        Out of Stock
                      @elseif($inventoryStatus === 'low')
                        Low Stock
                      @else
                        Available
                      @endif

                    </span>

                  </div>


                  <div class="recent-warehouse-meta">

                    <span>
                      <i class="fa-solid fa-cubes-stacked"></i>

                      Qty:
                      {{ $quantity }}
                    </span>


                    <span>
                      <i class="fa-solid fa-arrow-down"></i>

                      Reorder:
                      {{ $reorderLevel }}
                    </span>

                  </div>

                </div>

              </div>


            @empty

              <div class="dashboard-empty-state">

                <div class="dashboard-empty-icon">

                  <i class="fa-solid fa-boxes-stacked"></i>

                </div>

                <h3>
                  No recent inventory activity
                </h3>

                <p>
                  Inventory updates will appear here.
                </p>

              </div>

            @endforelse

          </div>

        </div>


        {{-- =================================================
            DELIVERY STATUS
        ================================================== --}}
        <div class="warehouse-dashboard-card">

          <div class="dashboard-card-header">

            <div>
              <span class="dashboard-eyebrow">
                DELIVERIES
              </span>

              <h2>
                Incoming Deliveries
              </h2>

              <p>
                Monitor deliveries expected by Warehouse.
              </p>

            </div>


            <a
              href="{{ route('incoming-deliveries') }}"
              class="dashboard-view-link"
            >
              View Deliveries

              <i class="fa-solid fa-arrow-right"></i>
            </a>

          </div>


          <div class="delivery-overview">

            <div class="delivery-main">

              <div class="delivery-main-icon">

                <i class="fa-solid fa-truck-ramp-box"></i>

              </div>


              <div>

                <span>
                  Incoming
                </span>

                <strong>
                  {{ $incomingDeliveries }}
                </strong>

                <p>
                  Expected warehouse deliveries
                </p>

              </div>

            </div>


            <a
              href="{{ route('incoming-deliveries') }}"
              class="delivery-action"
            >

              <span>
                Review incoming stock
              </span>

              <i class="fa-solid fa-chevron-right"></i>

            </a>

          </div>

        </div>


        {{-- =================================================
            QUICK ACTIONS
        ================================================== --}}
        <div class="warehouse-dashboard-card">

          <div class="dashboard-card-header">

            <div>
              <span class="dashboard-eyebrow">
                QUICK ACCESS
              </span>

              <h2>
                Warehouse Actions
              </h2>

              <p>
                Common warehouse tasks.
              </p>

            </div>

          </div>


          <div class="warehouse-quick-actions">

            <a
              href="{{ route('inventory') }}"
              class="warehouse-quick-item"
            >

              <span class="warehouse-quick-icon blue">

                <i class="fa-solid fa-boxes-stacked"></i>

              </span>


              <div>

                <h3>
                  Inventory
                </h3>

                <p>
                  Manage stock records
                </p>

              </div>


              <i class="fa-solid fa-chevron-right"></i>

            </a>


            <a
              href="{{ route('part-requests') }}"
              class="warehouse-quick-item"
            >

              <span class="warehouse-quick-icon yellow">

                <i class="fa-solid fa-clipboard-list"></i>

              </span>


              <div>

                <h3>
                  Part Requests
                </h3>

                <p>
                  Review requested parts
                </p>

              </div>


              <i class="fa-solid fa-chevron-right"></i>

            </a>


            <a
              href="{{ route('stock-movements') }}"
              class="warehouse-quick-item"
            >

              <span class="warehouse-quick-icon green">

                <i class="fa-solid fa-right-left"></i>

              </span>


              <div>

                <h3>
                  Stock Movements
                </h3>

                <p>
                  Review stock history
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