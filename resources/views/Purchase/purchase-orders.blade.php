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
          'bus_no' => $selectedPurchaseRequest->bus_no,
          'item_description' => $itemName,
          'quantity' => $quantity > 0 ? $quantity : 1,
          'unit' => $unit,
          'cost' => '',
        ];
      }

      if (count($prefillItems) === 0) {
        $prefillItems[] = [
          'pr_no' => $selectedPurchaseRequest->pr_no,
          'bus_no' => $selectedPurchaseRequest->bus_no,
          'item_description' => $selectedPurchaseRequest->item ?? '',
          'quantity' => $selectedPurchaseRequest->quantity ?? 1,
          'unit' => 'PC',
          'cost' => '',
        ];
      }
    }

    $prefillData = $selectedPurchaseRequest ? [
      'id' => $selectedPurchaseRequest->id,
      'pr_no' => $selectedPurchaseRequest->pr_no,
      'job_order_no' => $selectedPurchaseRequest->job_order_no,
      'bus_no' => $selectedPurchaseRequest->bus_no,
      'remarks' => $selectedPurchaseRequest->remarks,
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
        <div class="section-header po-section-header purchase-record-header">
          <div class="section-heading">
            <span class="section-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span>
            <div>
              <span class="purchase-section-eyebrow">PROCUREMENT RECORDS</span>
              <h2>Purchase Order Records</h2>
              <p>Track procurement progress, request references, totals, and delivery status.</p>
            </div>
          </div>
          <div class="section-count"><span>{{ $purchaseOrders->total() }}</span> records</div>
          @if(($activeTab ?? 'active') === 'active')
            <button type="button" id="openPoModal" class="primary-btn purchase-header-create-btn"><i class="fa-solid fa-plus"></i> New PO</button>
          @endif
        </div>

        <div class="po-record-controls purchase-list-controls">
          <nav class="po-record-tabs" aria-label="Purchase order record groups">
            <a href="{{ route('purchase-orders', array_merge(request()->except(['page', 'view', 'status']), ['view' => 'active'])) }}"
               class="po-record-tab {{ ($activeTab ?? 'active') === 'active' ? 'is-active' : '' }}"
               @if(($activeTab ?? 'active') === 'active') aria-current="page" @endif>
              <i class="fa-solid fa-list-check"></i> Active
            </a>
            <a href="{{ route('purchase-orders', array_merge(request()->except(['page', 'view', 'status']), ['view' => 'history'])) }}"
               class="po-record-tab {{ ($activeTab ?? 'active') === 'history' ? 'is-active' : '' }}"
               @if(($activeTab ?? 'active') === 'history') aria-current="page" @endif>
              <i class="fa-solid fa-clock-rotate-left"></i> History
            </a>
          </nav>
          <div class="po-record-actions purchase-list-tools">
            <x-ui.table-toolbar action="/purchase-orders" class="records-toolbar" data-server-filter="true"
              search-label="Search Purchase Orders" search-placeholder="PO number, supplier, purpose, or status..."
              :filter-count="(int) request()->filled('status')"
              filter-title="Filter Purchase Orders"
              :sort-options="['po_date' => 'PO Date', 'po_no' => 'PO Number', 'supplier_name' => 'Supplier', 'total_amount' => 'Total Amount', 'status' => 'Status']"
              default-sort="po_date" default-direction="desc">
              <x-slot:hidden><input type="hidden" name="view" value="{{ $activeTab ?? 'active' }}"></x-slot:hidden>
              <x-slot:filters>
                <x-ui.table-toolbar-field label="Status" name="status" default="">
                  <option value="">All {{ ($activeTab ?? 'active') === 'history' ? 'Completed' : 'Active' }} Statuses</option>
                  @foreach(($activeTab ?? 'active') === 'history' ? ['Delivered', 'Picked Up'] : ['Ordered', 'For Pick-up', 'For Delivery'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                  @endforeach
                </x-ui.table-toolbar-field>
              </x-slot:filters>
            </x-ui.table-toolbar>
          </div>
        </div>

        <div class="table-wrap records-table-wrap">
          <table class="records-table">
            <thead>
              <tr>
                <th>PO ID</th><th>Item</th><th>Request ID</th><th>Request Type</th><th>Qty</th><th>Total Amount</th><th>Status</th><th>Date</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($purchaseOrders as $purchaseOrder)
                @php
                  $items = is_array($purchaseOrder->items) ? $purchaseOrder->items : [];
                  $firstItem = $items[0] ?? [];
                  $itemName = $firstItem['item_description'] ?? $firstItem['item'] ?? '—';
                  $firstItemName = trim(preg_replace('/\\s*\\[item:\\s*\\d+\\]/i', '', explode(',', $itemName)[0] ?? $itemName));
                  $rawRequestNo = $firstItem['pr_no'] ?? '—';
                  $displayRequestNo = preg_replace('/-P$/', '', $rawRequestNo);
                  $hasRequest = trim((string) $displayRequestNo) !== '' && $displayRequestNo !== '—';
                  $isInventoryRestock = $hasRequest && str_starts_with(strtoupper($displayRequestNo), 'RST-');
                  $requestType = ! $hasRequest ? 'Manual Purchase' : ($isInventoryRestock ? 'Inventory Restock' : 'Maintenance PR');
                  $normalizedPoStatus = strtolower(trim((string) ($purchaseOrder->status ?? '')));
                  $isEditable = $normalizedPoStatus === 'ordered';
                  $isDraft = $normalizedPoStatus === 'draft';
                  $nextStatuses = $purchaseOrder->status === 'Ordered' ? ['For Pick-up', 'For Delivery'] : [];
                @endphp

                <tr>
                  <td><div class="po-number-cell"><strong>{{ $purchaseOrder->po_no }}</strong></div></td>
                  <td>
                    <div class="po-item-preview">
                      <strong>{{ $firstItemName ?: '—' }}</strong>
                      @if(count($items) > 1)
                        <button type="button" class="po-items-more-trigger" aria-label="Show all {{ count($items) }} purchase order items" aria-expanded="false">
                          +{{ count($items) - 1 }} more <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                        </button>
                        <div class="po-items-more-source" hidden>
                          @foreach($items as $poItem)
                            <div class="po-items-more-row">
                              <span>{{ trim(preg_replace('/\\s*\\[item:\\s*\\d+\\]/i', '', $poItem['item_description'] ?? $poItem['item'] ?? '—')) }}</span>
                              <strong>{{ $poItem['quantity'] ?? '—' }} {{ $poItem['unit'] ?? '' }}</strong>
                            </div>
                          @endforeach
                        </div>
                      @endif
                    </div>
                  </td>
                  <td><span class="po-reference">{{ $hasRequest ? $displayRequestNo : '—' }}</span></td>
                  <td>
                    <span class="po-request-type {{ $isInventoryRestock ? 'inventory-restock' : ($hasRequest ? 'maintenance-request' : 'manual-purchase') }}">
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
                        data-purchase-request-id="{{ $purchaseOrder->purchase_request_id }}"
                        data-supplier-name="{{ $purchaseOrder->supplier_name }}"
                        data-supplier-address-tel="{{ $purchaseOrder->supplier_address_tel }}"
                        data-terms="{{ $purchaseOrder->terms }}"
                        data-terms-of-payment="{{ $purchaseOrder->terms_of_payment }}"
                        data-purpose="{{ $purchaseOrder->purpose }}"
                        data-delivery-fee="{{ $purchaseOrder->delivery_fee }}"
                        data-discount="{{ $purchaseOrder->discount }}"
                        data-vat="{{ $purchaseOrder->vat }}"
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
                          data-purchase-request-id="{{ $purchaseOrder->purchase_request_id }}"
                        data-supplier-name="{{ $purchaseOrder->supplier_name }}"
                        data-supplier-address-tel="{{ $purchaseOrder->supplier_address_tel }}"
                        data-terms="{{ $purchaseOrder->terms }}"
                        data-terms-of-payment="{{ $purchaseOrder->terms_of_payment }}"
                        data-purpose="{{ $purchaseOrder->purpose }}"
                        data-delivery-fee="{{ $purchaseOrder->delivery_fee }}"
                        data-discount="{{ $purchaseOrder->discount }}"
                        data-vat="{{ $purchaseOrder->vat }}"
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
                <x-ui.empty-row
                  colspan="9"
                  icon="fa-file-invoice"
                  message="No Purchase Orders Found"
                  description="No purchase orders match your search or filter criteria. Try adjusting your filters."
                />
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
        <input type="hidden" name="status" id="po_status" value="Ordered">
        <input type="hidden" name="purchase_request_id" id="purchase_request_id" value="{{ $selectedPurchaseRequest?->id }}">

        <section class="po-form-section po-order-information" aria-labelledby="poOrderInfoTitle">
          <div class="po-form-section-header">
            <span class="po-section-icon"><i class="fa-solid fa-file-invoice"></i></span>
            <div>
              <h3 id="poOrderInfoTitle">Order Information</h3>
              <p>Supplier, payment terms, and linked requisition details.</p>
            </div>
          </div>

          <div class="po-form-section-body">
            <div class="po-form-grid po-paper-header-grid">
              <div class="po-form-group">
                <label for="po_no">PO ID <span class="po-required">*</span></label>
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
                  value=""
                  placeholder="Enter supplier name"
                  maxlength="255"
                  required
                >
              </div>

              <div class="po-form-group">
                <label for="supplier_address_tel">Supplier Address / Contact</label>
                <input type="text" name="supplier_address_tel" id="supplier_address_tel" placeholder="Address or telephone (optional)">
              </div>
              <div class="po-form-group">
                <label for="terms">Terms (Days)</label>
                <input type="text" name="terms" id="terms" placeholder="e.g. 15" maxlength="255">
              </div>
              <div class="po-form-group">
                <label for="terms_of_payment">Terms of Payment</label>
                <select name="terms_of_payment" id="terms_of_payment">
                  <option value="">Select payment method</option>
                  <option value="Check">Check</option>
                  <option value="Cash">Cash</option>
                  <option value="Bank Transfer">Bank Transfer</option>
                </select>
              </div>
              <div class="po-form-group po-request-reference" id="poRequestReference">
                <label for="main_pr_no">Linked Purchase Requisition</label>
                <input type="text" id="main_pr_no" placeholder="No linked request" value="{{ $selectedPurchaseRequest?->pr_no }}" readonly>
              </div>
            </div>
            <dl class="po-request-context" id="poRequestContext" @if(!$selectedPurchaseRequest) hidden @endif>
              <div><dt>Linked Job Order</dt><dd id="poRequestJobOrder">{{ $selectedPurchaseRequest?->job_order_no ?? '—' }}</dd></div>
              <div><dt>Bus ID</dt><dd id="poRequestBus">{{ $selectedPurchaseRequest?->bus_no ?? '—' }}</dd></div>
              <div class="po-request-context-remarks"><dt>Request Remarks</dt><dd id="poRequestRemarks">{{ $selectedPurchaseRequest?->remarks ?? '—' }}</dd></div>
            </dl>
          </div>
        </section>

        <section class="po-form-section po-purchase-items-section" aria-labelledby="poItemsTitle">
          <div class="po-form-section-header">
            <span class="po-section-icon"><i class="fa-solid fa-box-open"></i></span>
            <div>
              <h3 id="poItemsTitle">Purchase Items</h3>
              <p>Items linked to a Purchase Requisition are automatically populated.</p>
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
                <span class="po-item-action-heading" aria-label="Item actions"></span>
              </div>

              <div id="poItemsContainer" class="po-items-container"></div>

              <button type="button" id="addPoItemBtn" class="add-po-item-btn">
                <i class="fa-solid fa-plus"></i>
                <span>Add Item</span>
              </button>
            </div>

            <div class="po-bottom-grid po-paper-bottom-grid">
              <div class="po-form-group po-purpose-field">
                <label for="purpose">Purpose</label>
                <textarea name="purpose" id="purpose" rows="3" placeholder="e.g. For maintenance of assigned bus"></textarea>
              </div>
              <aside class="po-summary-card" aria-label="Purchase order financial summary">
                <div class="po-summary-title"><span><i class="fa-solid fa-calculator"></i></span><strong>Financial Summary</strong></div>
                <div class="po-summary-row"><span>Gross Amount</span><strong id="po_subtotal_display">₱0.00</strong></div>
                <div class="po-summary-row po-adjustment"><label for="delivery_fee">Delivery Fee</label><input type="number" name="delivery_fee" id="delivery_fee" value="0" min="0" step="0.01"></div>
                <div class="po-summary-row po-adjustment"><label for="discount">Discount</label><input type="number" name="discount" id="discount" value="0" min="0" step="0.01"></div>
                <div class="po-summary-row po-adjustment"><label for="vat">VAT Amount</label><input type="number" name="vat" id="vat" value="0" min="0" step="0.01"></div>
                <div class="po-summary-row po-summary-total"><span>Net Amount</span><input type="text" id="net_amount_display" value="₱0.00" readonly aria-label="Net amount"></div>
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
