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
    $deliveryQuery = \App\Models\Purchase\PurchaseOrder::query()
      ->whereIn('status', ['For Delivery', 'Delivered', 'For Pick-up', 'Picked Up']);

    if (request()->filled('search')) {
      $search = trim(request('search'));
      $deliveryQuery->where(function ($query) use ($search) {
        $query->where('po_no', 'like', "%{$search}%")
          ->orWhere('supplier_name', 'like', "%{$search}%")
          ->orWhere('purpose', 'like', "%{$search}%")
          ->orWhere('status', 'like', "%{$search}%");
      });
    }

    if (request()->filled('status') && request('status') !== 'All Statuses') {
      if (request('status') === 'Received') {
        $deliveryQuery->whereNotNull('inventory_posted_at');
      } else {
        $deliveryQuery->where('status', request('status'));
      }
    }

    $deliveries = $deliveryQuery->latest()->paginate(20)->withQueryString();

    $totalIncoming = \App\Models\Purchase\PurchaseOrder::whereIn('status', ['For Delivery', 'For Pick-up'])
      ->whereNull('inventory_posted_at')
      ->count();
    $forDelivery = \App\Models\Purchase\PurchaseOrder::where('status', 'For Delivery')
      ->whereNull('inventory_posted_at')
      ->count();
    $delivered = \App\Models\Purchase\PurchaseOrder::whereIn('status', ['Delivered', 'Picked Up'])
      ->whereNotNull('inventory_posted_at')
      ->count();
    $receivedToday = \App\Models\Purchase\PurchaseOrder::whereDate('inventory_posted_at', today())->count();
  @endphp

  <div class="app">
    <x-layout.sidebar
      department="Warehouse"
      subtitle="Warehouse Module"
      icon="fa-warehouse"
      :items="[
        ['label' => 'Dashboard', 'route' => 'warehouse.dashboard', 'icon' => 'fa-table-cells-large'],
        ['label' => 'Inventory', 'route' => 'inventory', 'icon' => 'fa-boxes-stacked'],
        ['label' => 'Part Requests', 'route' => 'part-requests', 'icon' => 'fa-clipboard-list'],
        ['label' => 'Incoming Deliveries', 'route' => 'incoming-deliveries', 'icon' => 'fa-truck-ramp-box'],
        ['label' => 'Stock Movements', 'route' => 'stock-movements', 'icon' => 'fa-right-left'],
      ]"
    />

    <main class="main incoming-delivery-page">
      <x-layout.topbar
        title="Incoming Deliveries"
        subtitle="Monitor parts and supplies arriving from purchasing"
        notification-count="6"
      />

      <section class="stats-grid delivery-stats-grid">
        <x-ui.summary-card label="Incoming" value="{{ $totalIncoming }}" small="Expected deliveries" icon="fa-truck" color="blue" />
        <x-ui.summary-card label="For Delivery" value="{{ $forDelivery }}" small="Currently in transit" icon="fa-truck-fast" color="yellow" />
        <x-ui.summary-card label="Delivered" value="{{ $delivered }}" small="Completed deliveries" icon="fa-box" color="green" />
        <x-ui.summary-card label="Received Today" value="{{ $receivedToday }}" small="Received by Warehouse" icon="fa-box-open" color="purple" />
      </section>

      <section class="table-card incoming-delivery-card">
        <div class="section-header">
          <div>
            <h2>Delivery Records</h2>
            <p>Purchase Orders marked for delivery appear here for Warehouse receiving.</p>
          </div>
        </div>

        <form action="{{ route('incoming-deliveries') }}" method="GET" class="toolbar delivery-toolbar">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search PO no., supplier, item, or delivery...">
          </div>

          <div class="filter-group">
            <select name="status" id="deliveryStatus" onchange="this.form.requestSubmit()">
              @foreach(['All Statuses', 'For Delivery', 'For Pick-up', 'Delivered', 'Picked Up', 'Received'] as $status)
                <option value="{{ $status }}" @selected(request('status', 'All Statuses') === $status)>{{ $status }}</option>
              @endforeach
            </select>
          </div>
        </form>

        <div class="table-wrap">
          <table class="incoming-delivery-table">
            <thead>
              <tr>
                <th>PO #</th>
                <th>Supplier</th>
                <th>Item / Part</th>
                <th>Qty</th>
                <th>PO Date</th>
                <th>Received Date</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($deliveries as $delivery)
                @php
                  $items = collect($delivery->items ?? []);
                  $itemSummary = $items->pluck('item_description')->filter()->implode(', ');
                  $quantitySummary = $items->sum(fn ($item) => (int) ($item['quantity'] ?? 0));
                  $received = !is_null($delivery->inventory_posted_at);
                  $displayStatus = $received ? 'Received' : $delivery->status;
                  $statusClass = match($displayStatus) {
                    'For Delivery', 'For Pick-up' => 'for-delivery',
                    'Delivered', 'Picked Up' => 'delivered',
                    'Received' => 'received',
                    default => 'default',
                  };
                @endphp
                <tr>
                  <td><strong>{{ $delivery->po_no }}</strong></td>
                  <td>{{ $delivery->supplier_name ?: '—' }}</td>
                  <td>{{ $itemSummary ?: '—' }}</td>
                  <td>{{ $quantitySummary }}</td>
                  <td>{{ $delivery->po_date?->format('M d, Y') ?? '—' }}</td>
                  <td>{{ $delivery->inventory_posted_at?->format('M d, Y') ?? '—' }}</td>
                  <td>
                    <x-ui.status-badge
                      :status="$displayStatus"
                      class="delivery-status {{ $statusClass }}"
                    />
                  </td>
                  <td>
                    @if(!$received && in_array($delivery->status, ['For Delivery', 'For Pick-up'], true))
                      <form
                        action="{{ route('purchase-orders.update-status', $delivery) }}"
                        method="POST"
                        class="inline-action-form"
                        data-confirm-form
                        data-confirm-title="Receive Delivery?"
                        data-confirm-message="Confirm that {{ $delivery->po_no }} has been physically received by Warehouse. Inventory will be updated automatically."
                        data-confirm-button="Yes, Receive Delivery"
                        data-confirm-type="approve"
                      >
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ $delivery->status === 'For Pick-up' ? 'Picked Up' : 'Delivered' }}">
                        <button type="submit" class="primary-btn receive-delivery-btn" title="Receive Delivery">
                          <i class="fa-solid fa-box-open"></i>
                          Receive
                        </button>
                      </form>
                    @else
                      <span class="delivery-status received"><i class="fa-solid fa-circle-check"></i>&nbsp; Received</span>
                    @endif
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="8" class="empty-deliveries">
                    <x-ui.empty-state
                      class="delivery-empty-state"
                      icon="fa-truck-ramp-box"
                      title="No incoming deliveries"
                      description="Purchase Orders marked For Delivery or For Pick-up will appear here automatically."
                    />
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$deliveries" />
      </section>
    </main>
  </div>
</x-layout.app>
