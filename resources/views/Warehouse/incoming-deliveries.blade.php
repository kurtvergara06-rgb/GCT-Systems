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
    $canEditWarehouse = auth()->user()?->hasSystemPermission('warehouse', 'edit') ?? false;
    $currentView = $currentView ?? 'active';
    $isHistory = $currentView === 'history';
  @endphp

  <div class="app">
    <x-layout.sidebar department="Warehouse" />

    <main class="main incoming-delivery-page">
      <x-layout.topbar
        title="Incoming Deliveries"
        subtitle="Monitor parts and supplies arriving from purchasing"
        notification-count="6"
      />

      <section data-ajax-region="summary" class="stats-grid delivery-stats-grid">
        <x-ui.summary-card label="Incoming" value="{{ $totalIncoming }}" small="Expected deliveries" icon="fa-truck" color="blue" />
        <x-ui.summary-card label="For Delivery" value="{{ $forDelivery }}" small="Currently in transit" icon="fa-truck-fast" color="yellow" />
        <x-ui.summary-card label="Delivered" value="{{ $delivered }}" small="Completed deliveries" icon="fa-box" color="green" />
        <x-ui.summary-card label="Received Today" value="{{ $receivedToday }}" small="Received by Warehouse" icon="fa-box-open" color="purple" />
      </section>

      <section data-ajax-region="records" class="table-card incoming-delivery-card">
        <div class="section-header warehouse-record-header">
          <div>
            <h2>Delivery Records</h2>
            <p>Purchase Orders ready for Warehouse receiving and completed receipt records.</p>
          </div>

          <nav class="warehouse-record-tabs" aria-label="Incoming delivery record view" role="tablist">
            <a
              href="{{ route('incoming-deliveries', ['view' => 'active']) }}"
              class="warehouse-record-tab {{ !$isHistory ? 'is-active' : '' }}"
              data-allow-partial-navigation="true"
              role="tab"
              aria-selected="{{ !$isHistory ? 'true' : 'false' }}"
            >
              <i class="fa-solid fa-list-check"></i>
              <span>Active</span>
            </a>
            <a
              href="{{ route('incoming-deliveries', ['view' => 'history']) }}"
              class="warehouse-record-tab {{ $isHistory ? 'is-active' : '' }}"
              data-allow-partial-navigation="true"
              role="tab"
              aria-selected="{{ $isHistory ? 'true' : 'false' }}"
            >
              <i class="fa-solid fa-clock-rotate-left"></i>
              <span>History</span>
            </a>
          </nav>
        </div>

        @if($isHistory)
          <p class="warehouse-history-note">Warehouse-received purchase orders are kept here for reference and audit history.</p>
        @endif

        <form action="{{ route('incoming-deliveries') }}" method="GET" class="toolbar delivery-toolbar" data-server-filter="true">
          <input type="hidden" name="view" value="{{ $currentView }}">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search PO no., supplier, item, or delivery...">
          </div>

          <div class="filter-group">
            <select name="status" id="deliveryStatus">
              @foreach(array_merge(['All Statuses'], $statusOptions ?? []) as $status)
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
                    <x-ui.status-badge :status="$displayStatus" class="delivery-status {{ $statusClass }}" />
                  </td>
                  <td>
                    @if($isHistory || $received)
                      <span class="delivery-status received"><i class="fa-solid fa-lock"></i>&nbsp; Read only</span>
                    @elseif($canEditWarehouse && in_array($delivery->status, ['For Delivery', 'For Pick-up'], true))
                      <form
                        action="{{ route('incoming-deliveries.receive', $delivery) }}"
                        method="POST"
                        class="inline-action-form"
                        data-confirm-form
                        data-confirm-title="Receive Delivery?"
                        data-confirm-message="Confirm that {{ $delivery->po_no }} has been physically received by Warehouse. Inventory will be updated automatically."
                        data-confirm-button="Yes, Receive Delivery"
                        data-confirm-type="approve"
                      >
                        @csrf
                        <button type="submit" class="primary-btn receive-delivery-btn" title="Receive Delivery">
                          <i class="fa-solid fa-box-open"></i>
                          Receive
                        </button>
                      </form>
                    @else
                      <span class="delivery-status for-delivery"><i class="fa-solid fa-lock"></i>&nbsp; View only</span>
                    @endif
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="8" class="empty-deliveries">
                    <x-ui.empty-state
                      class="delivery-empty-state"
                      icon="fa-truck-ramp-box"
                      :title="$isHistory ? 'No delivery history' : 'No incoming deliveries'"
                      :description="$isHistory ? 'Warehouse-received purchase orders will appear here automatically.' : 'Purchase Orders marked For Delivery or For Pick-up will appear here automatically.'"
                    />
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$deliveries" data-lazy-pagination="true" />
      </section>
    </main>
  </div>
</x-layout.app>
