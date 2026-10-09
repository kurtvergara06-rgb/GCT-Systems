<x-layout.app
  title="FROMS - Purchase Orders"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Purchase/purchase-orders.css',
    'resources/css/Purchase/purchase-module-ui.css',
    'resources/js/Purchase/purchase-orders.js'
  ]"
>

  @php
    $statuses = $statuses ?? ['Ordered', 'For Pick-up', 'For Delivery', 'Delivered', 'Picked Up'];
    $totalOrders = $totalOrders ?? 0;
    $ordered = $ordered ?? 0;
    $forPickup = $forPickup ?? 0;
    $delivered = $delivered ?? 0;
    $selectedPurchaseRequest = $selectedPurchaseRequest ?? null;
    $openPoModal = $openPoModal ?? false;
    $prefillItems = [];

    if ($selectedPurchaseRequest) {
      $rawItems = explode(',', $selectedPurchaseRequest->item ?? '');

      foreach ($rawItems as $rawItem) {
        $rawItem = trim($rawItem);
        if ($rawItem === '') continue;

        $itemName = $rawItem;
        $quantity = 1;
        $unit = 'PC';

        if (str_contains(strtolower($rawItem), ' - qty:')) {
          $parts = preg_split('/ - qty:/i', $rawItem, 2);
          $itemName = trim($parts[0] ?? $rawItem);
          $qtyUnit = trim($parts[1] ?? '1');

          if (preg_match('/^(\d+)\s*(.*)$/', $qtyUnit, $matches)) {
            $quantity = (int) ($matches[1] ?? 1);
            $unit = trim($matches[2] ?? 'PC') ?: 'PC';
          } else {
            $quantity = (int) ($selectedPurchaseRequest->quantity ?? 1);
          }
        }

        $prefillItems[] = [
          'pr_no' => $selectedPurchaseRequest->pr_no,
          'item_description' => $itemName,
          'quantity' => $quantity > 0 ? $quantity : 1,
          'unit' => $unit,
          'cost' => 0,
        ];
      }

      if (count($prefillItems) === 0) {
        $prefillItems[] = [
          'pr_no' => $selectedPurchaseRequest->pr_no,
          'item_description' => $selectedPurchaseRequest->item ?? '',
          'quantity' => $selectedPurchaseRequest->quantity ?? 1,
          'unit' => 'PC',
          'cost' => 0,
        ];
      }
    }

    $prefillData = $selectedPurchaseRequest ? [
      'id' => $selectedPurchaseRequest->id,
      'pr_no' => $selectedPurchaseRequest->pr_no,
      'items' => $prefillItems,
    ] : null;
  @endphp

  <div class="app">
    <x-layout.sidebar department="Purchase" />

    <main class="main purchase-orders-page purchase-module-page records-page">
      <x-layout.topbar
        title="Purchase Order"
        subtitle="Manage procurement records for vehicle parts, equipment & operational materials"
        notification-count="6"
      />

      <section data-ajax-region="summary" class="stats-grid">
        <x-ui.summary-card label="Total Purchase Orders" value="{{ $totalOrders }}" small="All procurement records" icon="fa-file-invoice" color="gray" />
        <x-ui.summary-card label="Ordered" value="{{ $ordered }}" small="Awaiting supplier action" icon="fa-file-invoice" color="blue" />
        <x-ui.summary-card label="For Pick-up" value="{{ $forPickup }}" small="Ready for collection" icon="fa-box" color="yellow" />
        <x-ui.summary-card label="Delivered / Picked Up" value="{{ $delivered }}" small="Completed procurement" icon="fa-circle-check" color="green" />
      </section>

      <section data-ajax-region="records" class="table-card purchase-order-card records-card">
        <div class="section-header po-section-header">
          <div class="section-heading">
            <span class="section-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span>
            <div>
              <span class="purchase-section-eyebrow">PROCUREMENT RECORDS</span>
              <h2>Purchase Order Records</h2>
              <p>Track procurement progress, request references, totals, and delivery status.</p>
            </div>
          </div>
          <div class="section-count"><span>{{ $purchaseOrders->total() }}</span> records</div>
        </div>

        <form action="/purchase-orders" method="GET" class="toolbar po-toolbar records-toolbar" data-server-filter="true">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search PO number, item, request no., or status...">
          </div>

          <div class="filter-group po-filter-field">
            <label for="poStatusFilter" class="sr-only">Status</label>
            <select id="poStatusFilter" name="status">
              <option value="All States" {{ request('status', 'All States') === 'All States' ? 'selected' : '' }}>All Statuses</option>
              @foreach($statuses as $status)
                <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ $status }}</option>
              @endforeach
            </select>
          </div>

          <button type="button" id="openPoModal" class="primary-btn compact-new-po-btn"><i class="fa-solid fa-plus"></i> New PO</button>
        </form>

        <div class="table-wrap records-table-wrap">
          <table class="records-table">
            <thead>
              <tr>
                <th>PO No.</th><th>Item</th><th>Request No.</th><th>Request Type</th><th>Qty</th><th>Total Amount</th><th>Status</th><th>Date</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($purchaseOrders as $purchaseOrder)
                @php
                  $items = is_array($purchaseOrder->items) ? $purchaseOrder->items : [];
                  $firstItem = $items[0] ?? [];
                  $itemName = $firstItem['item_description'] ?? $firstItem['item'] ?? '—';
                  $firstItemName = trim(explode(',', $itemName)[0] ?? $itemName);
                  $rawRequestNo = $firstItem['pr_no'] ?? '—';
                  $displayRequestNo = preg_replace('/-P$/', '', $rawRequestNo);
                  $hasRequest = trim((string) $displayRequestNo) !== '' && $displayRequestNo !== '—';
                  $isInventoryRestock = $hasRequest && str_starts_with(strtoupper($displayRequestNo), 'RST-');
                  $requestType = ! $hasRequest ? 'Manual Purchase' : ($isInventoryRestock ? 'Inventory Restock' : 'Maintenance Request');
                  $normalizedPoStatus = strtolower(trim((string) ($purchaseOrder->status ?? '')));
                  $isEditable = $normalizedPoStatus === 'ordered';
                  $isDraft = $normalizedPoStatus === 'draft';
                  $nextStatuses = $purchaseOrder->status === 'Ordered' ? ['For Pick-up', 'For Delivery'] : [];
                @endphp

                <tr>
                  <td><div class="po-number-cell"><strong>{{ $purchaseOrder->po_no }}</strong></div></td>
                  <td><strong>{{ $firstItemName ?: '—' }}</strong></td>
                  <td><span class="po-reference">{{ $hasRequest ? $displayRequestNo : '—' }}</span></td>
                  <td>
                    <span class="po-request-type {{ $isInventoryRestock ? 'inventory-restock' : 'maintenance-request' }}">
                      <i class="fa-solid {{ $isInventoryRestock ? 'fa-boxes-stacked' : ($hasRequest ? 'fa-screwdriver-wrench' : 'fa-file-circle-plus') }}"></i>
                      {{ $requestType }}
                    </span>
                  </td>
                  <td><span class="po-qty">{{ $firstItem['quantity'] ?? '—' }}</span></td>
                  <td><div class="po-amount">&#8369;{{ number_format((float) $purchaseOrder->net_amount, 2) }}</div></td>
                  <td class="po-status-cell"><x-ui.status-badge :status="$purchaseOrder->status" type="purchase" /></td>
                  <td>
                    <div class="po-date">
                      <strong>{{ $purchaseOrder->po_date ? \Carbon\Carbon::parse($purchaseOrder->po_date)->format('M d, Y') : '—' }}</strong>
                      <small>{{ $purchaseOrder->po_date ? \Carbon\Carbon::parse($purchaseOrder->po_date)->format('l') : '' }}</small>
                    </div>
                  </td>
                  <td>
                    <div class="actions record-actions">
                      <x-ui.action-button
                        type="view"
                        title="View PO"
                        class="open-view-po-modal"
                        data-id="{{ $purchaseOrder->id }}"
                        data-po-no="{{ $purchaseOrder->po_no }}"
                        data-po-date="{{ $purchaseOrder->po_date }}"
                        data-supplier-name="{{ $purchaseOrder->supplier_name }}"
                        data-status="{{ $purchaseOrder->status }}"
                        data-items='@json($items)'
                        data-update-url="/purchase-orders/{{ $purchaseOrder->id }}"
                      />

                      @if($isEditable)
                        <x-ui.action-button
                          type="edit"
                          title="Edit PO"
                          class="open-edit-po-modal"
                          data-id="{{ $purchaseOrder->id }}"
                          data-po-no="{{ $purchaseOrder->po_no }}"
                          data-po-date="{{ $purchaseOrder->po_date }}"
                          data-supplier-name="{{ $purchaseOrder->supplier_name }}"
                          data-status="{{ $purchaseOrder->status }}"
                          data-items='@json($items)'
                          data-update-url="/purchase-orders/{{ $purchaseOrder->id }}"
                        />
                      @endif

                      @if(count($nextStatuses) > 0)
                        <x-ui.action-button
                          type="status"
                          title="Update PO Status"
                          class="open-po-status-modal"
                          data-po-no="{{ $purchaseOrder->po_no }}"
                          data-current-status="{{ $purchaseOrder->status }}"
                          data-status-url="/purchase-orders/{{ $purchaseOrder->id }}/status"
                          data-next-statuses='@json($nextStatuses)'
                        />
                      @endif

                      @if($isDraft)
                        <form id="deletePoForm-{{ $purchaseOrder->id }}" action="/purchase-orders/{{ $purchaseOrder->id }}" method="POST">
                          @csrf @method('DELETE')
                          <button type="button" class="action-btn delete open-delete-po-modal" title="Delete" data-id="{{ $purchaseOrder->id }}" data-po-no="{{ $purchaseOrder->po_no }}"><i class="fa-solid fa-trash"></i></button>
                        </form>
                      @endif
                    </div>
                  </td>
                </tr>
              @empty
                <x-ui.empty-row colspan="9" message="No purchase orders found." />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$purchaseOrders" data-lazy-pagination="true" />
      </section>
    </main>
  </div>

  <div id="poModal" class="modal-overlay {{ $openPoModal ? 'show active' : '' }}">
    <div class="modal-card modal-box po-modal-box" role="dialog" aria-modal="true" aria-labelledby="poModalTitle">
      <div class="po-modal-header">
        <div class="po-modal-heading">
          <h2 id="poModalTitle">New Purchase Order</h2>
          <p id="poModalSubtitle">Create and review a supplier purchase order.</p>
        </div>
        <button type="button" id="closePoModal" class="po-close-btn" aria-label="Close purchase order form">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <form
        id="poForm"
        action="/purchase-orders"
        method="POST"
        class="po-form"
        data-store-url="/purchase-orders"
        data-confirm-form
        data-confirm-title="Create Purchase Order?"
        data-confirm-message="Are you sure you want to create this Purchase Order?"
        data-confirm-button="Yes, Create PO"
        data-confirm-type="create"
      >
        @csrf
        <input type="hidden" name="_method" id="poFormMethod" value="POST">
        <input type="hidden" name="purchase_request_id" id="purchase_request_id" value="{{ $selectedPurchaseRequest?->id }}">

        <section class="po-form-section po-order-information" aria-labelledby="poOrderInfoTitle">
          <div class="po-form-section-header">
            <span class="po-section-icon"><i class="fa-solid fa-file-invoice"></i></span>
            <div>
              <h3 id="poOrderInfoTitle">Order Information</h3>
              <p>Enter the purchase order details and supplier information.</p>
            </div>
          </div>

          <div class="po-form-section-body">
            <div class="po-form-grid">
              <div class="po-form-group">
                <label for="po_no">PO Number <span class="po-required">*</span></label>
                <input type="text" name="po_no" id="po_no" value="{{ $nextPoNo ?? '' }}" readonly>
              </div>

              <div class="po-form-group">
                <label for="po_date">Date <span class="po-required">*</span></label>
                <input type="date" name="po_date" id="po_date" value="{{ now()->toDateString() }}" readonly required>
              </div>

              <div class="po-form-group">
                <label for="supplier_name">Supplier <span class="po-required">*</span></label>
                <input
                  type="text"
                  name="supplier_name"
                  id="supplier_name"
                  value="N/A"
                  placeholder="Enter supplier name"
                  maxlength="255"
                  required
                >
              </div>

              <div class="po-form-group">
                <label for="po_status">Status <span class="po-required">*</span></label>
                <div class="po-status-field">
                  <span class="po-status-dot" aria-hidden="true"></span>
                  <input type="text" name="status" id="po_status" value="Ordered" readonly required>
                </div>
              </div>

              <div class="po-form-group po-request-reference" id="poRequestReference">
                <label for="main_pr_no">Linked Purchase Request</label>
                <input type="text" id="main_pr_no" placeholder="No linked request" value="{{ $selectedPurchaseRequest?->pr_no }}" readonly>
              </div>
            </div>
          </div>
        </section>

        <section class="po-form-section po-purchase-items-section" aria-labelledby="poItemsTitle">
          <div class="po-form-section-header">
            <span class="po-section-icon"><i class="fa-solid fa-box-open"></i></span>
            <div>
              <h3 id="poItemsTitle">Purchase Items</h3>
              <p>Add the items to be included in this purchase order.</p>
            </div>
          </div>

          <div class="po-form-section-body">
            <div class="po-items-section">
              <div class="po-items-header">
                <span>Item Description <b>*</b></span>
                <span>Qty <b>*</b></span>
                <span>Unit</span>
                <span>Unit Cost <b>*</b></span>
                <span>Line Total</span>
                <span></span>
              </div>

              <div id="poItemsContainer" class="po-items-container"></div>

              <button type="button" id="addPoItemBtn" class="add-po-item-btn">
                <i class="fa-solid fa-plus"></i>
                <span>Add Item</span>
              </button>
            </div>

            <div class="po-bottom-grid">
              <div class="po-items-helper">
                <i class="fa-solid fa-circle-info"></i>
                <span>Review item quantities and costs before saving.</span>
              </div>

              <aside class="po-summary-card" aria-label="Purchase order summary">
                <div class="po-summary-title">
                  <span><i class="fa-solid fa-calculator"></i></span>
                  <strong>Summary</strong>
                </div>
                <div class="po-summary-row">
                  <span>Subtotal</span>
                  <strong id="po_subtotal_display">₱0.00</strong>
                </div>
                <div class="po-summary-row po-summary-total">
                  <span>Total Amount</span>
                  <input type="text" id="net_amount_display" value="₱0.00" readonly aria-label="Total amount">
                </div>
              </aside>
            </div>
          </div>
        </section>

        <div class="po-modal-actions" id="poEditActions">
          <div class="po-action-hint">
            <i class="fa-solid fa-circle-info"></i>
            <span id="poActionHint">Review items before saving.</span>
          </div>
          <div class="po-action-buttons">
            <button type="button" id="cancelPoModal" class="secondary-btn po-cancel-btn">Cancel</button>
            <button type="submit" id="poSaveButton" class="primary-btn po-save-btn">
              <i class="fa-solid fa-floppy-disk"></i>
              <span id="poSaveButtonLabel">Save Purchase Order</span>
            </button>
          </div>
        </div>

      </form>
    </div>
  </div>

  <div id="poStatusModal" class="modal-overlay po-status-modal-overlay">
    <div class="po-status-modal" role="dialog" aria-modal="true" aria-labelledby="poStatusModalTitle">
      <div class="po-status-modal-icon"><i class="fa-solid fa-arrow-right-arrow-left"></i></div>
      <div class="po-status-modal-copy">
        <h2 id="poStatusModalTitle">Update Purchase Order Status</h2>
        <p>Select how <strong id="poStatusModalPoNo">this purchase order</strong> will be fulfilled.</p>
      </div>
      <div class="po-status-current"><span>Current Status</span><strong id="poStatusCurrentValue">—</strong></div>

      <form
        id="poStatusForm"
        method="POST"
        data-confirm-form
        data-confirm-title="Update PO Status?"
        data-confirm-message="Are you sure you want to update this purchase order status?"
        data-confirm-button="Yes, Update Status"
        data-confirm-type="status"
      >
        @csrf @method('PATCH')
        <div class="po-status-choice-list" id="poStatusChoiceList"></div>
        <input type="hidden" name="status" id="poStatusValue">
        <div class="po-status-modal-actions">
          <button type="button" id="cancelPoStatusModal" class="secondary-btn">Cancel</button>
          <button type="submit" id="confirmPoStatusBtn" class="primary-btn" disabled><i class="fa-solid fa-check"></i> Update Status</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    window.purchaseOrderPrefill = @json($prefillData);
    window.purchaseOrderShouldOpen = @json($openPoModal);
  </script>

  <x-ui.action-buttom-modal
    mode="delete"
    id="deletePoModal"
    delete-title="Delete Purchase Order?"
    delete-message="Are you sure you want to delete"
    name-id="deletePoNo"
    cancel-id="cancelDeletePo"
    confirm-id="confirmDeletePo"
  />
</x-layout.app>
