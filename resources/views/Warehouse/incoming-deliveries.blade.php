<x-layout.app
  title="FROMS - Incoming Deliveries"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Warehouse/incoming-deliveries.css',
    'resources/js/Main-js/sidebar.js'
  ]"
>

  @php
    $deliveries = $deliveries ?? collect();

    $totalIncoming = $totalIncoming ?? 0;
    $forDelivery = $forDelivery ?? 0;
    $delivered = $delivered ?? 0;
    $receivedToday = $receivedToday ?? 0;
  @endphp


  <div class="app">

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


    <main class="main incoming-delivery-page">

      {{-- =====================================================
          TOPBAR
      ====================================================== --}}
      <x-layout.topbar
        title="Incoming Deliveries"
        subtitle="Monitor parts and supplies arriving from purchasing"
        notification-count="6"
      />


      {{-- =====================================================
          SUMMARY CARDS
      ====================================================== --}}
      <section class="stats-grid delivery-stats-grid">

        <x-ui.summary-card
          label="Incoming"
          value="{{ $totalIncoming }}"
          small="Expected deliveries"
          icon="fa-truck"
          color="blue"
        />

        <x-ui.summary-card
          label="For Delivery"
          value="{{ $forDelivery }}"
          small="Currently in transit"
          icon="fa-truck-fast"
          color="yellow"
        />

        <x-ui.summary-card
          label="Delivered"
          value="{{ $delivered }}"
          small="Completed deliveries"
          icon="fa-box-circle-check"
          color="green"
        />

        <x-ui.summary-card
          label="Received Today"
          value="{{ $receivedToday }}"
          small="Received by Warehouse"
          icon="fa-box-open"
          color="purple"
        />

      </section>


      {{-- =====================================================
          DELIVERY RECORDS
      ====================================================== --}}
      <section class="table-card incoming-delivery-card">

        <div class="section-header">

          <div>

            <h2>
              Delivery Records
            </h2>

            <p>
              Track incoming purchased parts before they are added
              to Warehouse inventory.
            </p>

          </div>

        </div>


        {{-- =================================================
            TOOLBAR
        ================================================== --}}
        <form
          action="{{ route('incoming-deliveries') }}"
          method="GET"
          class="toolbar delivery-toolbar"
        >

          <div class="search-box">

            <i class="fa-solid fa-magnifying-glass"></i>

            <input
              type="text"
              name="search"
              value="{{ request('search') }}"
              placeholder="Search PO no., supplier, item, or delivery..."
            >

          </div>


          <div class="filter-group">

            <label for="deliveryStatus">
              Status
            </label>

            <select
              name="status"
              id="deliveryStatus"
            >

              <option value="All Statuses">
                All Statuses
              </option>

              <option value="For Delivery">
                For Delivery
              </option>

              <option value="Delivered">
                Delivered
              </option>

              <option value="Received">
                Received
              </option>

            </select>

          </div>

        </form>


        {{-- =================================================
            TABLE
        ================================================== --}}
        <div class="table-wrap">

          <table class="incoming-delivery-table">

            <thead>

              <tr>

                <th>PO #</th>

                <th>Supplier</th>

                <th>Item / Part</th>

                <th>Qty</th>

                <th>Expected Date</th>

                <th>Received Date</th>

                <th>Status</th>

              </tr>

            </thead>


            <tbody>

              @forelse($deliveries as $delivery)

                @php
                  $status = $delivery->status ?? 'For Delivery';

                  $statusClass = match($status) {
                    'For Delivery' => 'for-delivery',
                    'Delivered' => 'delivered',
                    'Received' => 'received',
                    default => 'default',
                  };
                @endphp


                <tr>

                  <td>
                    {{ $delivery->po_no ?? '—' }}
                  </td>


                  <td>
                    {{ $delivery->supplier ?? '—' }}
                  </td>


                  <td>
                    {{ $delivery->item ?? '—' }}
                  </td>


                  <td>
                    {{ $delivery->quantity ?? '—' }}
                  </td>


                  <td>

                    @if(!empty($delivery->expected_date))

                      {{
                        \Carbon\Carbon::parse(
                          $delivery->expected_date
                        )->format('M d, Y')
                      }}

                    @else

                      —

                    @endif

                  </td>


                  <td>

                    @if(!empty($delivery->received_date))

                      {{
                        \Carbon\Carbon::parse(
                          $delivery->received_date
                        )->format('M d, Y')
                      }}

                    @else

                      —

                    @endif

                  </td>


                  <td>

                    <span class="delivery-status {{ $statusClass }}">

                      {{ $status }}

                    </span>

                  </td>

                </tr>


              @empty

                <tr>

                  <td
                    colspan="7"
                    class="empty-deliveries"
                  >

                    <div class="delivery-empty-state">

                      <div class="delivery-empty-icon">

                        <i class="fa-solid fa-truck-ramp-box"></i>

                      </div>


                      <h3>
                        No incoming deliveries
                      </h3>


                      <p>
                        Purchased parts that are currently for delivery
                        will appear here.
                      </p>

                    </div>

                  </td>

                </tr>

              @endforelse

            </tbody>

          </table>

        </div>

      </section>

    </main>

  </div>

</x-layout.app>