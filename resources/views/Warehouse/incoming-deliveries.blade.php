<x-layout.app
  title="FROMS - Incoming Deliveries"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Warehouse/incoming-deliveries.css',
    'resources/js/Main-js/sidebar.js',
    'resources/js/Warehouse/incoming-deliveries.js'
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
            <h2>{{ $isHistory ? 'Delivery History' : 'Active Incoming Deliveries' }}</h2>
            <p>Purchase Orders ready for Warehouse receiving and completed receipt records.</p>
          </div>


        </div>

        <div class="incoming-delivery-controls">
          <nav class="warehouse-record-tabs" aria-label="Incoming delivery record view" role="tablist">
            <a
              href="{{ route('incoming-deliveries', ['view' => 'active']) }}"
              class="warehouse-record-tab {{ !$isHistory ? 'is-active' : '' }}"
              data-allow-partial-navigation="true"
              role="tab"
              aria-selected="{{ !$isHistory ? 'true' : 'false' }}"
            >
              <i class="fa-solid fa-list-check"></i>
              <span>Active</span><span class="gct-record-tab-count">{{ $activeCount }}</span>
            </a>
            <a
              href="{{ route('incoming-deliveries', ['view' => 'history']) }}"
              class="warehouse-record-tab {{ $isHistory ? 'is-active' : '' }}"
              data-allow-partial-navigation="true"
              role="tab"
              aria-selected="{{ $isHistory ? 'true' : 'false' }}"
            >
              <i class="fa-solid fa-clock-rotate-left"></i>
              <span>History</span><span class="gct-record-tab-count">{{ $historyCount }}</span>
            </a>
          </nav>
          <div class="incoming-delivery-toolbar">
        <x-ui.table-toolbar action="{{ route('incoming-deliveries') }}" data-server-filter="true" search-label="Search Deliveries" search-placeholder="Search PO no., supplier, item, or delivery..." :filter-count="(int) (request()->filled('status') && request('status') !== 'All Statuses')" filter-title="Filter Deliveries" :sort-options="['po_date' => 'PO Date', 'po_no' => 'PO Number', 'supplier_name' => 'Supplier', 'status' => 'Status', 'inventory_posted_at' => 'Received Date']" default-sort="po_date" default-direction="desc">
          <x-slot:hidden><input type="hidden" name="view" value="{{ $currentView }}"></x-slot:hidden>
          <x-slot:filters><x-ui.table-toolbar-field label="Status" name="status" default="All Statuses">@foreach(array_merge(['All Statuses'], $statusOptions ?? []) as $status)<option value="{{ $status }}" @selected(request('status', 'All Statuses') === $status)>{{ $status }}</option>@endforeach</x-ui.table-toolbar-field></x-slot:filters>
        </x-ui.table-toolbar>
          </div>
        </div>

        <div class="table-wrap">
          <table class="incoming-delivery-table">
            <thead>
              <tr>
                <th>PO ID</th>
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
                  $partNames = $items->pluck('item_description')->filter()->map(fn ($name) => trim(preg_replace('/\\s*\\[item:\\s*\\d+\\]/i', '', (string) $name)))->filter()->values();
                  $quantitySummary = $items->sum(fn ($item) => (int) ($item['quantity'] ?? 0));
                  $received = !is_null($delivery->inventory_posted_at);
                  $displayStatus = $received ? 'Received' : $delivery->status;
                  $statusClass = match($displayStatus) {
                    'Ordered', 'For Delivery', 'For Pick-up' => 'for-delivery',
                    'Delivered', 'Picked Up' => 'delivered',
                    'Received' => 'received',
                    default => 'default',
                  };
                @endphp
                <tr>
                  <td><strong>{{ $delivery->po_no }}</strong></td>
                  <td>{{ $delivery->supplier_name ?: '—' }}</td>
                  <td class="incoming-part-cell">
                    <span class="incoming-first-part">{{ $partNames->first() ?: '—' }}</span>
                    @if($partNames->count() > 1)
                      <details class="incoming-part-more">
                        <summary>+{{ $partNames->count() - 1 }} more <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                        <div class="incoming-part-more-list">
                          @foreach($partNames->skip(1) as $partName)
                            <div>{{ $partName }}</div>
                          @endforeach
                        </div>
                      </details>
                    @endif
                  </td>
                  <td>{{ $quantitySummary }}</td>
                  <td>
                    @if($delivery->po_date)
                      <div class="incoming-date-stack">
                        <strong>{{ $delivery->po_date->format('M d, Y') }}</strong>
                        <small>{{ ($delivery->created_at ?? $delivery->po_date)->format('h:i A') }}</small>
                      </div>
                    @else
                      —
                    @endif
                  </td>
                  <td>
                    @if($delivery->inventory_posted_at)
                      <div class="incoming-date-stack">
                        <strong>{{ $delivery->inventory_posted_at->format('M d, Y') }}</strong>
                        <small>{{ $delivery->inventory_posted_at->format('h:i A') }}</small>
                      </div>
                    @else
                      —
                    @endif
                  </td>
                  <td>
                    <x-ui.status-badge :status="$displayStatus" class="delivery-status {{ $statusClass }}" />
                  </td>
                  <td>
                    @if($isHistory || $received)
                      <span class="delivery-status received"><i class="fa-solid fa-lock"></i>&nbsp; Read only</span>
                    @elseif($canEditWarehouse && in_array($delivery->status, ['Ordered', 'For Delivery', 'For Pick-up'], true))
                      @php
                        $receiptLines = $items->map(function ($line, $lineIndex) use ($delivery) {
                          $ordered = max(0, (int) ($line['quantity'] ?? 0));
                          $already = max(0, (int) (($delivery->received_quantities ?? [])[$lineIndex] ?? 0));
                          return [
                            'index' => $lineIndex,
                            'name' => trim(preg_replace('/\\s*\\[item:\\s*\\d+\\]/i', '', (string) ($line['item_description'] ?? 'Item'))),
                            'ordered' => $ordered,
                            'received' => $already,
                            'remaining' => max(0, $ordered - $already),
                            'unit' => $line['unit'] ?? '',
                          ];
                        })->values();
                      @endphp
                      <button type="button" class="primary-btn receive-delivery-btn open-incoming-receipt"
                        data-receipt-url="{{ route('incoming-deliveries.receive', $delivery) }}"
                        data-po="{{ $delivery->po_no }}"
                        data-supplier="{{ $delivery->supplier_name }}"
                        data-lines="{{ $receiptLines->toJson() }}"
                        aria-label="Review and receive items for {{ $delivery->po_no }}">
                        <i class="fa-solid fa-box-open" aria-hidden="true"></i> Receive
                      </button>
                    @else
                      <span class="delivery-status for-delivery"><i class="fa-solid fa-lock"></i>&nbsp; View only</span>
                    @endif
                  </td>
                </tr>
              @empty
                <x-ui.empty-row
                  colspan="8"
                  icon="fa-truck-ramp-box"
                  :message="$isHistory ? 'No Delivery History Found' : 'No Incoming Deliveries Found'"
                  :description="$isHistory ? 'Warehouse-received purchase orders will appear here automatically.' : 'Ordered Purchase Orders awaiting Warehouse receipt will appear here automatically.'"
                />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$deliveries" data-lazy-pagination="true" />
      </section>
    </main>
  </div>
  <div class="incoming-receipt-overlay" id="incomingReceiptModal" role="presentation" hidden>
    <div class="incoming-receipt-dialog" role="dialog" aria-modal="true" aria-labelledby="incomingReceiptTitle">
      <header class="incoming-receipt-header">
        <div><small>WAREHOUSE RECEIVING</small><h2 id="incomingReceiptTitle">Receive Purchase Order</h2>
          <p>Record only the quantities physically received. Unreceived quantities remain outstanding.</p></div>
        <button type="button" id="incomingReceiptClose" aria-label="Close receiving" class="incoming-receipt-close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </header>
      <form id="incomingReceiptForm" method="POST" class="incoming-receipt-form" data-confirm-form
        data-confirm-title="Confirm Physical Receipt?"
        data-confirm-message="Only the entered quantities will be posted to inventory. Any outstanding quantities will remain open."
        data-confirm-button="Yes, Receive Items" data-confirm-type="approve">
        @csrf
        <div class="incoming-receipt-content">
          <div class="incoming-receipt-meta">
            <div><small>Purchase Order</small><strong id="incomingReceiptPo">—</strong></div>
            <div><small>Supplier</small><strong id="incomingReceiptSupplier">—</strong></div>
          </div>
          <div class="incoming-receipt-table-wrap">
            <table class="incoming-receipt-items"><thead><tr><th>Item / Part</th><th>Ordered</th><th>Previously Received</th><th>Outstanding</th><th>Receive Now</th></tr></thead>
              <tbody id="incomingReceiptLines"></tbody>
            </table>
          </div>
          <p class="incoming-receipt-help">Enter 0 for items not included in this delivery. At least one item must have a received quantity greater than 0.</p>
          <div id="incomingReceiptError" class="incoming-receipt-error" role="alert" hidden></div>
        </div>
        <footer class="incoming-receipt-footer">
          <button type="button" id="incomingReceiptCancel" class="incoming-receipt-cancel">Cancel</button>
          <button type="submit" id="incomingReceiptSubmit" class="primary-btn receive-delivery-btn"><i class="fa-solid fa-box-open" aria-hidden="true"></i> Confirm Receipt</button>
        </footer>
      </form>
    </div>
  </div>
</x-layout.app>
