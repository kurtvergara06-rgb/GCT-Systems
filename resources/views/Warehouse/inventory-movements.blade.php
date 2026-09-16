<x-layout.app
  title="FROMS - Item Movement History"
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
        title="Item Movement History"
        subtitle="Audit trail for {{ $inventoryItem->item_name }}"
        notification-count="6"
      />

      <section class="table-card stock-movement-card">
        <div class="section-header item-history-header">
          <div>
            <h2>{{ $inventoryItem->item_name }}</h2>
            <p>
              Item Code:
              <span class="ref-badge">{{ $inventoryItem->item_code ?: '—' }}</span>
              &nbsp;·&nbsp; Unit: <strong>{{ $inventoryItem->unit_of_measurement }}</strong>
              &nbsp;·&nbsp; Current On Hand: <strong>{{ $inventoryItem->on_hand }}</strong>
            </p>
          </div>

          <a href="{{ route('inventory') }}" class="secondary-btn">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Inventory
          </a>
        </div>

        <div class="stats-grid item-movement-stats">
          <x-ui.summary-card label="Stock In" value="{{ $stockIn }}" small="Receipts recorded" icon="fa-arrow-down" color="green" />
          <x-ui.summary-card label="Stock Out" value="{{ $stockOut }}" small="Issuances recorded" icon="fa-arrow-up" color="red" />
          <x-ui.summary-card label="Adjustments" value="{{ $adjustments }}" small="Corrections recorded" icon="fa-sliders" color="yellow" />
          <x-ui.summary-card label="Demo Records" value="{{ $demotedMovements }}" small="Sample seeder rows (isolated)" icon="fa-flask" color="gray" />
        </div>

        @if($demotedMovements > 0)
          <div class="history-demo-notice">
            <i class="fa-solid fa-circle-info"></i>
            This item has <strong>{{ $demotedMovements }}</strong> legacy sample movement{{ $demotedMovements === 1 ? '' : 's' }}
            labelled <span class="source-badge source-badge--demo">Demo</span>.
            They were created by the demo seeder and are isolated from genuine
            operational records used for model training.
          </div>
        @endif

        <form action="{{ route('inventory.movements', $inventoryItem) }}" method="GET" class="toolbar stock-movement-toolbar item-history-toolbar">
          <div class="filter-group">
            <select name="type" id="itemMovementTypeFilter">
              @foreach(['All Types', 'Stock In', 'Stock Out', 'Adjustment'] as $type)
                <option value="{{ $type }}" @selected(request('type', 'All Types') === $type)>{{ $type }}</option>
              @endforeach
            </select>
          </div>
        </form>

        <div class="table-wrap">
          <table class="stock-movement-table">
            <thead>
              <tr>
                <th>Date / Time</th>
                <th>Movement</th>
                <th>Qty Change</th>
                <th>Previous</th>
                <th>New Stock</th>
                <th>Reference</th>
                <th>Source</th>
                <th>Purpose / Remarks</th>
              </tr>
            </thead>
            <tbody>
              @forelse($movements as $movement)
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
                  <td>
                    @if($movement->reference_no)
                      <span class="ref-badge">{{ $movement->reference_no }}</span>
                    @else
                      <span>—</span>
                    @endif
                  </td>
                  <td>
                    <span class="source-badge source-badge--{{ $movement->source === 'app' ? 'app' : 'demo' }}">
                      {{ $movement->source === 'app' ? 'App' : 'Demo' }}
                    </span>
                  </td>
                  <td class="movement-remarks">{{ $movement->remarks ?: '—' }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="8" class="empty-stock-movements">
                    <x-ui.empty-state
                      class="stock-movement-empty-state"
                      icon="fa-clock-rotate-left"
                      title="No movements recorded"
                      description="Stock-in, stock-out, and adjustment activity for this item will appear here when they happen."
                    />
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$movements" />
      </section>
    </main>
  </div>
</x-layout.app>