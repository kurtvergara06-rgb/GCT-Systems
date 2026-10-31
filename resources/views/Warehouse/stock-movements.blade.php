<x-layout.app
  title="FROMS - Stock Movements"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Warehouse/stock-movements.css',
    'resources/js/Main-js/sidebar.js'
  ]"
>
  <div class="app">
    <x-layout.sidebar department="Warehouse" />

    <main class="main stock-movement-page">
      <x-layout.topbar
        title="Stock Movements"
        subtitle="Track stock-in, stock-out, and inventory adjustment history"
        notification-count="6"
      />

      <section data-ajax-region="summary" class="stats-grid stock-movement-stats">
        <x-ui.summary-card label="Total Movements" value="{{ $totalMovements }}" small="Recorded stock transactions" icon="fa-right-left" color="blue" />
        <x-ui.summary-card label="Stock In" value="{{ $stockIn }}" small="Inventory received" icon="fa-arrow-down" color="green" />
        <x-ui.summary-card label="Stock Out" value="{{ $stockOut }}" small="Inventory issued" icon="fa-arrow-up" color="red" />
        <x-ui.summary-card label="Adjustments" value="{{ $adjustments }}" small="Inventory corrections" icon="fa-sliders" color="purple" />
      </section>

      <section data-ajax-region="records" class="table-card stock-movement-card">
        <div class="section-header">
          <div>
            <h2>Stock Movement History</h2>
            <p>Complete inventory transaction and adjustment history.</p>
          </div>
          <div class="stock-movement-record-count" aria-label="{{ number_format($stockMovements->total()) }} matching movement records">
            <strong>{{ number_format($stockMovements->total()) }}</strong>
            <span>{{ $stockMovements->total() === 1 ? 'Record' : 'Records' }}</span>
          </div>
        </div>

        <x-ui.table-toolbar action="{{ route('stock-movements') }}" data-server-filter="true" search-label="Search Stock Movements" search-placeholder="Search item, reference no., or movement..." :filter-count="(int) (request()->filled('type') && request('type') !== 'All Types') + (int) (request()->filled('date_filter') && request('date_filter') !== 'All Dates')" filter-title="Filter Stock Movements" :sort-options="['created_at' => 'Date and Time', 'item_name' => 'Item Name', 'movement_type' => 'Movement Type', 'quantity' => 'Quantity', 'reference_no' => 'Reference Number']" default-sort="created_at" default-direction="desc">
          <x-slot:filters>
            <x-ui.table-toolbar-field label="Movement Type" name="type" default="All Types" id="movementTypeFilter">@foreach(['All Types', 'Stock In', 'Stock Out', 'Adjustment'] as $type)<option value="{{ $type }}" @selected(request('type', 'All Types') === $type)>{{ $type }}</option>@endforeach</x-ui.table-toolbar-field>
            <x-ui.table-toolbar-field label="Date Range" name="date_filter" default="All Dates" id="movementDateFilter">@foreach(['All Dates', 'Today', 'This Week', 'This Month'] as $dateFilter)<option value="{{ $dateFilter }}" @selected(request('date_filter', 'All Dates') === $dateFilter)>{{ $dateFilter }}</option>@endforeach</x-ui.table-toolbar-field>
          </x-slot:filters>
        </x-ui.table-toolbar>

        <div class="table-wrap stock-movement-scroll-table" role="region" aria-label="Scrollable stock movement records" tabindex="0">
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
                  $movementClass = match($movement->movement_type) {
                    'Stock In' => 'stock-in',
                    'Stock Out' => 'stock-out',
                    'Adjustment' => 'adjustment',
                    default => 'default',
                  };
                  $movementIcon = match($movement->movement_type) {
                    'Stock In' => 'fa-arrow-down',
                    'Stock Out' => 'fa-arrow-up',
                    'Adjustment' => 'fa-sliders',
                    default => 'fa-right-left',
                  };
                @endphp
                <tr>
                  <td>
                    <div class="movement-date">
                      <span>{{ $movement->created_at->format('M d, Y') }}</span>
                      <small>{{ $movement->created_at->format('h:i A') }}</small>
                    </div>
                  </td>
                  <td>
                    <div class="movement-item">
                      <strong>{{ $movement->item_name }}</strong>
                      @if($movement->item_code)<small>{{ $movement->item_code }}</small>@endif
                    </div>
                  </td>
                  <td>
                    @if($movement->reference_no)
                      @php
                        $movementReference = trim((string) $movement->reference_no);
                        $referenceUrl = str_starts_with(strtoupper($movementReference), 'PO-')
                          ? route('incoming-deliveries', ['search' => $movementReference])
                          : (str_starts_with(strtoupper($movementReference), 'IR-')
                            ? route('warehouse.item-requests', ['search' => $movementReference])
                            : null);
                      @endphp
                      @if($referenceUrl)
                        <a class="ref-badge stock-movement-reference-link" href="{{ $referenceUrl }}" title="Find {{ $movementReference }} in Warehouse records">{{ $movementReference }} <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                      @else
                        <span class="ref-badge">{{ $movementReference }}</span>
                      @endif
                    @else
                      <span>—</span>
                    @endif
                  </td>
                  <td>
                    <span class="movement-badge {{ $movementClass }}">
                      <i class="fa-solid {{ $movementIcon }}"></i>
                      {{ $movement->movement_type }}
                    </span>
                  </td>
                  <td>
                    <strong class="movement-quantity {{ $movementClass }}">
                      {{ $movement->quantity_change > 0 ? '+' : '' }}{{ $movement->quantity_change }}{{ $movement->unit ? ' '.$movement->unit : '' }}
                    </strong>
                  </td>
                  <td>{{ $movement->previous_stock }}</td>
                  <td><strong>{{ $movement->new_stock }}</strong></td>
                  <td class="movement-remarks">{{ $movement->remarks ?: '—' }}</td>
                </tr>
              @empty
                <x-ui.empty-row
                  colspan="8"
                  icon="fa-right-left"
                  message="No Stock Movements Recorded"
                  description="Stock-in, stock-out, and adjustment transactions will appear automatically when inventory changes."
                />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$stockMovements" data-lazy-pagination="true" />
      </section>
    </main>
  </div>
</x-layout.app>
