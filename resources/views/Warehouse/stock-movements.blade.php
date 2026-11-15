<x-layout.app
  title="FROMS - Stock Movements"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Warehouse/stock-movements.css',
    'resources/js/Main-js/sidebar.js'
  ]"
>

  @php
    $stockMovements = $stockMovements ?? collect();

    $totalMovements = $totalMovements ?? 0;
    $stockIn = $stockIn ?? 0;
    $stockOut = $stockOut ?? 0;
    $adjustments = $adjustments ?? 0;
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
          'route' => 'dashboard-warehouse',
          'icon' => 'fa-table-cells-large'
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


    <main class="main stock-movement-page">

      {{-- =====================================================
          TOPBAR
      ====================================================== --}}
      <x-layout.topbar
        title="Stock Movements"
        subtitle="Track stock-in, stock-out, and inventory adjustment history"
        notification-count="6"
      />


      {{-- =====================================================
          SUMMARY CARDS
      ====================================================== --}}
      <section class="stats-grid stock-movement-stats">

        <x-ui.summary-card
          label="Total Movements"
          value="{{ $totalMovements }}"
          small="Recorded stock transactions"
          icon="fa-right-left"
          color="blue"
        />

        <x-ui.summary-card
          label="Stock In"
          value="{{ $stockIn }}"
          small="Inventory received"
          icon="fa-arrow-down"
          color="green"
        />

        <x-ui.summary-card
          label="Stock Out"
          value="{{ $stockOut }}"
          small="Inventory issued"
          icon="fa-arrow-up"
          color="red"
        />

        <x-ui.summary-card
          label="Adjustments"
          value="{{ $adjustments }}"
          small="Inventory corrections"
          icon="fa-sliders"
          color="yellow"
        />

      </section>


      {{-- =====================================================
          STOCK MOVEMENT RECORDS
      ====================================================== --}}
      <section class="table-card stock-movement-card">

        <div class="section-header">

          <div>

            <h2>
              Stock Movement History
            </h2>

            <p>
              Review inventory transactions and quantity changes
              recorded by Warehouse.
            </p>

          </div>

        </div>


        {{-- =================================================
            TOOLBAR
        ================================================== --}}
        <form
          action="{{ route('stock-movements') }}"
          method="GET"
          class="toolbar stock-movement-toolbar"
        >

          <div class="search-box">

            <i class="fa-solid fa-magnifying-glass"></i>

            <input
              type="text"
              name="search"
              value="{{ request('search') }}"
              placeholder="Search item, reference no., or movement..."
            >

          </div>


          <div class="filter-group">

            <label for="movementTypeFilter">
              Type
            </label>

            <select
              name="type"
              id="movementTypeFilter"
            >

              <option
                value="All Types"
                {{ request('type', 'All Types') === 'All Types'
                  ? 'selected'
                  : ''
                }}
              >
                All Types
              </option>

              <option
                value="Stock In"
                {{ request('type') === 'Stock In'
                  ? 'selected'
                  : ''
                }}
              >
                Stock In
              </option>

              <option
                value="Stock Out"
                {{ request('type') === 'Stock Out'
                  ? 'selected'
                  : ''
                }}
              >
                Stock Out
              </option>

              <option
                value="Adjustment"
                {{ request('type') === 'Adjustment'
                  ? 'selected'
                  : ''
                }}
              >
                Adjustment
              </option>

            </select>

          </div>


          <div class="filter-group">

            <label for="movementDateFilter">
              Date
            </label>

            <select
              name="date_filter"
              id="movementDateFilter"
            >

              <option
                value="All Dates"
                {{ request('date_filter', 'All Dates') === 'All Dates'
                  ? 'selected'
                  : ''
                }}
              >
                All Dates
              </option>

              <option
                value="Today"
                {{ request('date_filter') === 'Today'
                  ? 'selected'
                  : ''
                }}
              >
                Today
              </option>

              <option
                value="This Week"
                {{ request('date_filter') === 'This Week'
                  ? 'selected'
                  : ''
                }}
              >
                This Week
              </option>

              <option
                value="This Month"
                {{ request('date_filter') === 'This Month'
                  ? 'selected'
                  : ''
                }}
              >
                This Month
              </option>

            </select>

          </div>

        </form>


        {{-- =================================================
            TABLE
        ================================================== --}}
        <div class="table-wrap">

          <table class="stock-movement-table">

            <thead>

              <tr>
                <th>Date / Time</th>
                <th>Item / Part</th>
                <th>Reference</th>
                <th>Movement</th>
                <th>Qty</th>
                <th>Previous</th>
                <th>New Stock</th>
                <th>Remarks</th>
              </tr>

            </thead>


            <tbody>

              @forelse($stockMovements as $movement)

                @php
                  $movementType =
                    $movement->movement_type
                    ?? $movement->type
                    ?? 'Adjustment';

                  $movementClass = match($movementType) {
                    'Stock In' => 'stock-in',
                    'Stock Out' => 'stock-out',
                    'Adjustment' => 'adjustment',
                    default => 'default',
                  };

                  $movementIcon = match($movementType) {
                    'Stock In' => 'fa-arrow-down',
                    'Stock Out' => 'fa-arrow-up',
                    'Adjustment' => 'fa-sliders',
                    default => 'fa-right-left',
                  };
                @endphp


                <tr>

                  <td>

                    @if(!empty($movement->created_at))

                      <div class="movement-date">

                        <span>
                          {{ $movement->created_at->format('M d, Y') }}
                        </span>

                        <small>
                          {{ $movement->created_at->format('h:i A') }}
                        </small>

                      </div>

                    @else

                      —

                    @endif

                  </td>


                  <td>

                    <div class="movement-item">

                      <strong>
                        {{
                          $movement->item_name
                          ?? $movement->item
                          ?? 'Inventory Item'
                        }}
                      </strong>

                      @if(
                        !empty($movement->item_code)
                        || !empty($movement->sku)
                      )

                        <small>
                          {{
                            $movement->item_code
                            ?? $movement->sku
                          }}
                        </small>

                      @endif

                    </div>

                  </td>


                  <td>
                    {{
                      $movement->reference_no
                      ?? $movement->reference
                      ?? '—'
                    }}
                  </td>


                  <td>

                    <span class="movement-badge {{ $movementClass }}">

                      <i class="fa-solid {{ $movementIcon }}"></i>

                      {{ $movementType }}

                    </span>

                  </td>


                  <td>

                    <strong class="movement-quantity {{ $movementClass }}">

                      @if($movementType === 'Stock In')
                        +
                      @elseif($movementType === 'Stock Out')
                        -
                      @endif

                      {{ $movement->quantity ?? 0 }}

                    </strong>

                  </td>


                  <td>
                    {{ $movement->previous_stock ?? 0 }}
                  </td>


                  <td>

                    <strong>
                      {{ $movement->new_stock ?? 0 }}
                    </strong>

                  </td>


                  <td class="movement-remarks">

                    {{
                      $movement->remarks
                      ?: '—'
                    }}

                  </td>

                </tr>


              @empty

                <tr>

                  <td
                    colspan="8"
                    class="empty-stock-movements"
                  >

                    <div class="stock-movement-empty-state">

                      <div class="stock-movement-empty-icon">

                        <i class="fa-solid fa-right-left"></i>

                      </div>


                      <h3>
                        No stock movements recorded
                      </h3>


                      <p>
                        Stock-in and stock-out transactions will
                        appear here once Warehouse activity is recorded.
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