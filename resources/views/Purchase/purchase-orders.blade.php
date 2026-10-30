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

        // Internal stock identifiers belong to PR data, not the supplier-facing description.
        $itemName = trim(preg_replace('/\s*\[item:\s*\d+\]\s*/i', ' ', $itemName));

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
          'item_description' => trim(preg_replace('/\s*\[item:\s*\d+\]\s*/i', ' ', (string) ($selectedPurchaseRequest->item ?? ''))),
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
        title="Purchase Orders"
        subtitle="Manage procurement records for vehicle parts, equipment & operational materials"
        notification-count="6"
      />

      <section data-ajax-region="summary" class="stats-grid">
        <x-ui.summary-card label="Total Purchase Orders" value="{{ $totalOrders }}" small="All procurement records" icon="fa-file-invoice" color="gray" />
        <x-ui.summary-card label="Ordered" value="{{ $ordered }}" small="Awaiting supplier action" icon="fa-file-invoice" color="blue" />
        <x-ui.summary-card label="For Delivery" value="{{ $forDelivery }}" small="Awaiting Warehouse receipt" icon="fa-truck" color="yellow" />
        <x-ui.summary-card label="Delivered" value="{{ $delivered }}" small="Completed procurement" icon="fa-circle-check" color="green" />
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

        <nav class="po-record-tabs" aria-label="Purchase Order record views">
          <a href="{{ route('purchase-orders', ['view' => 'active']) }}" class="po-record-tab {{ ($activeTab ?? 'active') === 'active' ? 'is-active' : '' }}" @if(($activeTab ?? 'active') === 'active') aria-current="page" @endif><i class="fa-solid fa-list-check" aria-hidden="true"></i> Active</a>
          <a href="{{ route('purchase-orders', ['view' => 'history']) }}" class="po-record-tab {{ ($activeTab ?? 'active') === 'history' ? 'is-active' : '' }}" @if(($activeTab ?? 'active') === 'history') aria-current="page" @endif><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> History</a>
        </nav>
        <div class="po-record-controls purchase-list-controls">
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
                  @foreach(($activeTab ?? 'active') === 'history' ? ['Delivered', 'Picked Up', 'Rejected'] : ['For Review', 'Revision Required', 'Ordered', 'For Delivery'] as $status)
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
                <th>PO ID</th><th>PR ID</th><th>Item</th><th>Source</th><th>Qty</th><th>Total (₱)</th><th>Status</th><th>PO Date</th><th>Actions</th>
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
                  $isEditable = $normalizedPoStatus === 'revision required';
                  $isForReview = $normalizedPoStatus === 'for review';
                  $canReviewPo = ((strtolower(trim((string) (auth()->user()->department ?? ''))) === 'purchase' && strtolower(trim((string) (auth()->user()->role ?? ''))) === 'head') || strtolower(trim((string) (auth()->user()->role ?? ''))) === 'system admin' || (strtolower(trim((string) (auth()->user()->department ?? ''))) === 'admin' && strtolower(trim((string) (auth()->user()->role ?? ''))) === 'head'));
                  $isDraft = $normalizedPoStatus === 'draft';
                  $nextStatuses = [];
                @endphp

                <tr>
                  <td><div class="po-number-cell"><strong>{{ $purchaseOrder->po_no }}</strong></div></td>
                  <td><span class="po-reference">{{ $hasRequest ? $displayRequestNo : '—' }}</span></td>
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

                  <td>
                    <span class="po-request-type {{ $isInventoryRestock ? 'inventory-restock' : ($hasRequest ? 'maintenance-request' : 'manual-purchase') }}">
                      <i class="fa-solid {{ $isInventoryRestock ? 'fa-boxes-stacked' : ($hasRequest ? 'fa-screwdriver-wrench' : 'fa-file-circle-plus') }}"></i>
                      {{ $requestType }}
                    </span>
                  </td>
                  <td><span class="po-qty">{{ $firstItem['quantity'] ?? '—' }}</span></td>
                  <td><div class="po-amount">&#8369;{{ number_format((float) $purchaseOrder->net_amount, 2) }}</div></td>
                  <td class="po-status-cell"><span class="po-request-type po-status-box"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>{{ $purchaseOrder->status }}</span></td>
                  <td>
                    <div class="po-date">
                      <strong>{{ $purchaseOrder->po_date ? \Carbon\Carbon::parse($purchaseOrder->po_date)->format('M d, Y') : '—' }}</strong>
                      <small>{{ $purchaseOrder->po_date ? \Carbon\Carbon::parse($purchaseOrder->po_date)->format('l') : '' }}</small>
                    </div>
                  </td>
                  <td>
                    <div class="actions record-actions">
                      @unless($isForReview)
                      @php
                        $detailPr = $purchaseOrder->relatedMaintenanceRequest();
                        $detailJo = $detailPr && $detailPr->job_order_no !== 'RESTOCK' ? \App\Models\Maintenance\JobOrder::where('job_order_no', $detailPr->job_order_no)->first() : null;
                        $detailJoApprover = $detailJo?->approved_by ? \App\Models\Admin\User::find($detailJo->approved_by) : null;
                        $detailPoApprover = $purchaseOrder->approved_by ? \App\Models\Admin\User::find($purchaseOrder->approved_by) : null;
                      @endphp
                      <button type="button"
                        title="View PO"
                        class="action-btn view open-view-po-modal po-labeled-view"
                        data-detail-pr="{{ $detailPr?->pr_no ?? $purchaseOrder->first_pr_no ?? '' }}"
                        data-detail-jo="{{ $detailJo?->job_order_no ?? '' }}"
                        data-detail-jo-approver="{{ $detailJoApprover?->name ?? '' }}"
                        data-detail-jo-date="{{ $detailJo?->approved_at?->format('M d, Y h:i A') ?? '' }}"
                        data-detail-jo-signature="{{ $detailJo && $detailJo->review_status === 'Approved' ? route('purchase.job-orders.approval-signature', $detailJo) : '' }}"
                        data-detail-po-approver="{{ $detailPoApprover?->name ?? '' }}"
                        data-detail-po-date="{{ $purchaseOrder->approved_at?->format('M d, Y h:i A') ?? '' }}"
                        data-detail-po-signature="{{ $purchaseOrder->approval_signature_path && $purchaseOrder->approved_at ? route('purchase-orders.approval-signature', $purchaseOrder) : '' }}"
                        data-detail-total="{{ $purchaseOrder->net_amount }}"
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
                      ><i class="fa-solid fa-eye" aria-hidden="true"></i><span>View</span></button>

                      @endunless

                      @if($isForReview && $canReviewPo)
                        @php
$prForReview = $purchaseOrder->relatedMaintenanceRequest();
$joForReview = $prForReview && $prForReview->job_order_no !== 'RESTOCK' ? \App\Models\Maintenance\JobOrder::where('job_order_no', $prForReview->job_order_no)->first() : null;
$joApproverForReview = $joForReview?->approved_by ? \App\Models\Admin\User::find($joForReview->approved_by) : null;
@endphp
                        <button type="button" class="action-btn po-review-action open-po-review-modal"
                          
data-jo-no="{{ $joForReview?->job_order_no ?? '' }}"
data-jo-bus="{{ $joForReview?->bus_no ?? '' }}"
data-jo-status="{{ $joForReview?->review_status ?? '' }}"
data-jo-type="{{ $joForReview?->maintenance_type ?? '' }}"
data-jo-approver="{{ $joApproverForReview?->name ?? '' }}"
data-jo-date="{{ $joForReview?->approved_at?->format('M d, Y h:i A') ?? '' }}"
data-jo-signature="{{ $joForReview && $joForReview->review_status === 'Approved' ? route('purchase.job-orders.approval-signature', $joForReview) : '' }}"
data-po-no="{{ $purchaseOrder->po_no }}"
                          data-po-date="{{ optional($purchaseOrder->po_date)->format('M d, Y') }}"
                          data-supplier="{{ $purchaseOrder->supplier_name }}"
                          data-pr-no="{{ $purchaseOrder->first_pr_no }}"
                          data-purpose="{{ $purchaseOrder->purpose }}"
                          data-items='@json($items)'
                          data-gross="{{ $purchaseOrder->gross_amount }}"
                          data-delivery="{{ $purchaseOrder->delivery_fee }}"
                          data-discount="{{ $purchaseOrder->discount }}"
                          data-vat="{{ $purchaseOrder->vat }}"
                          data-net="{{ $purchaseOrder->net_amount }}"
                          data-approve-url="{{ route('purchase-orders.approve', $purchaseOrder) }}"
                          data-decision-url="{{ route('purchase-orders.review-decision', $purchaseOrder) }}"
                          aria-label="Review {{ $purchaseOrder->po_no }}">
                          <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i><span>Review</span>
                        </button>
                      @endif

                      @if(($activeTab ?? 'active') === 'active' && $isEditable && ! $isForReview)
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

                      @if(($activeTab ?? 'active') === 'active' && count($nextStatuses) > 0)
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

                      @if(($activeTab ?? 'active') === 'active' && $isDraft)
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
        <input type="hidden" name="status" id="po_status" value="For Review">
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

  <div id="poDetailOverlay" class="modal-overlay po-detail-overlay" style="display:none" role="presentation">
    <div class="po-detail-dialog" role="dialog" aria-modal="true" aria-labelledby="poDetailTitle">
      <header class="po-detail-header"><div><h2 id="poDetailTitle">Purchase Order Details</h2><p>View purchase items and recorded approval signatures.</p></div><button type="button" id="poDetailCloseTop" aria-label="Close"><i class="fa-solid fa-xmark"></i></button></header>
      <div class="po-detail-body">
        <section class="po-detail-card"><div class="po-detail-facts">
          <div><span class="po-detail-fact-icon" aria-hidden="true"><i class="fa-solid fa-file-invoice"></i></span><span class="po-detail-fact-copy"><small>PO Number</small><strong id="poDetailNo">—</strong></span></div><div><span class="po-detail-fact-icon" aria-hidden="true"><i class="fa-solid fa-file-lines"></i></span><span class="po-detail-fact-copy"><small>PR Number</small><strong id="poDetailPr">—</strong></span></div>
          <div><span class="po-detail-fact-icon" aria-hidden="true"><i class="fa-solid fa-calendar-days"></i></span><span class="po-detail-fact-copy"><small>Date</small><strong id="poDetailDate">—</strong></span></div><div><span class="po-detail-fact-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span><span class="po-detail-fact-copy"><small>Status</small><strong id="poDetailStatus">—</strong></span></div>
          <div><span class="po-detail-fact-icon" aria-hidden="true"><i class="fa-solid fa-building"></i></span><span class="po-detail-fact-copy"><small>Supplier</small><strong id="poDetailSupplier">—</strong></span></div><div><span class="po-detail-fact-icon" aria-hidden="true"><i class="fa-solid fa-screwdriver-wrench"></i></span><span class="po-detail-fact-copy"><small>Linked JO</small><strong id="poDetailJo">—</strong></span></div>
          <div><span class="po-detail-fact-icon" aria-hidden="true"><i class="fa-solid fa-circle-info"></i></span><span class="po-detail-fact-copy"><small>Purpose</small><strong id="poDetailPurpose">—</strong></span></div>
        </div></section>
        <section class="po-detail-card"><h3><i class="fa-solid fa-box"></i> Purchase Items</h3><div class="po-detail-table-scroll"><table><thead><tr><th>#</th><th>Item Description</th><th>Quantity</th><th>Unit</th><th>Unit Cost</th><th>Amount</th></tr></thead><tbody id="poDetailItems"></tbody></table></div><div class="po-detail-total"><span>NET TOTAL</span><strong id="poDetailTotal">—</strong></div></section>
        <section class="po-detail-card"><h3><i class="fa-solid fa-file-signature"></i> Authorization Signatures</h3><div class="po-detail-signatures">
          <div class="po-detail-signature-card"><h4>Maintenance Head — JO Approval</h4><div class="po-detail-signature-image"><img id="poDetailJoSignature" alt="Maintenance Head JO approval" hidden><p id="poDetailJoMissing">No approved JO signature available</p></div><strong id="poDetailJoApprover">—</strong><small id="poDetailJoDate">—</small></div>
          <div class="po-detail-signature-card"><h4>Purchase Head — PO Approval</h4><div class="po-detail-signature-image"><img id="poDetailPoSignature" alt="Purchase Head PO approval" hidden><p id="poDetailPoMissing">No approved PO signature available</p></div><strong id="poDetailPoApprover">—</strong><small id="poDetailPoDate">—</small></div>
        </div></section>
      </div><footer class="po-detail-footer"><button type="button" id="poDetailCloseBottom">Close</button></footer>
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

  <div id="poHeadReviewModal" class="modal-overlay po-head-review-overlay" role="presentation" style="display:none">
    <div class="po-head-review-dialog" role="dialog" aria-modal="true" aria-labelledby="poHeadReviewTitle">
      <header class="po-head-review-header">
        <div class="po-head-review-heading"><span class="po-head-review-heading-icon" aria-hidden="true"><i class="fa-solid fa-file-invoice"></i></span><div><small>PURCHASE HEAD APPROVAL</small><h2 id="poHeadReviewTitle">Review Purchase Order</h2><p>Verify supplier, ordered items and totals before your decision.</p></div></div>
        <button type="button" id="poHeadReviewClose" aria-label="Close review" class="po-head-review-x"><i class="fa-solid fa-xmark"></i></button>
      </header>
      <div class="po-head-review-content">
        <div class="po-head-review-meta">
          <div><span class="po-review-field-icon" aria-hidden="true"><i class="fa-solid fa-file-invoice"></i></span><span class="po-review-field-copy"><span>Purchase Order</span><strong id="headReviewNo">—</strong></span></div>
          <div><span class="po-review-field-icon" aria-hidden="true"><i class="fa-solid fa-calendar-days"></i></span><span class="po-review-field-copy"><span>Date</span><strong id="headReviewDate">—</strong></span></div>
          <div><span class="po-review-field-icon" aria-hidden="true"><i class="fa-solid fa-file-lines"></i></span><span class="po-review-field-copy"><span>Purchase Requisition</span><strong id="headReviewPr">—</strong></span></div>
          <div><span class="po-review-field-icon" aria-hidden="true"><i class="fa-solid fa-building"></i></span><span class="po-review-field-copy"><span>Supplier</span><strong id="headReviewSupplier">—</strong></span></div>
        </div>
        <section class="po-head-review-section po-jo-review">
<h3><i class="fa-solid fa-link"></i> Linked Job Order Authorization</h3>
<div class="po-jo-review-grid">
<div class="po-jo-review-facts">
<div><span class="po-review-field-icon" aria-hidden="true"><i class="fa-solid fa-file-lines"></i></span><span class="po-review-field-copy"><span>Job Order</span><strong id="poJoNo">—</strong></span></div>
<div><span class="po-review-field-icon" aria-hidden="true"><i class="fa-solid fa-bus"></i></span><span class="po-review-field-copy"><span>Bus / Plate</span><strong id="poJoBus">—</strong></span></div>
<div><span class="po-review-field-icon" aria-hidden="true"><i class="fa-solid fa-screwdriver-wrench"></i></span><span class="po-review-field-copy"><span>Maintenance Type</span><strong id="poJoType">—</strong></span></div>
<div><span class="po-review-field-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span><span class="po-review-field-copy"><span>Approval Status</span><strong id="poJoStatus">—</strong></span></div>
<div><span class="po-review-field-icon" aria-hidden="true"><i class="fa-solid fa-user"></i></span><span class="po-review-field-copy"><span>Approved By</span><strong id="poJoApprover">—</strong></span></div>
<div><span class="po-review-field-icon" aria-hidden="true"><i class="fa-solid fa-clock"></i></span><span class="po-review-field-copy"><span>Approved At</span><strong id="poJoDate">—</strong></span></div>
</div>
<div class="po-jo-review-signature"><strong>Authorized JO Signature</strong><img id="poJoSignature" alt="Authorized Maintenance Job Order approval signature" hidden><p id="poJoSignatureEmpty">No approved JO signature available.</p></div>
</div></section>
<div class="po-head-review-section"><h3>Purchase Items</h3><div class="po-head-review-table-wrap records-table-wrap"><table class="records-table po-review-items-table"><thead><tr><th>Item Description</th><th>Qty</th><th>Unit</th><th>Unit Cost</th><th>Amount</th></tr></thead><tbody id="headReviewItems"></tbody></table></div></div>
        <div class="po-head-review-summary po-totals-grid" aria-label="Purchase Order amount summary">
          <span class="po-totals-icon" aria-hidden="true"><i class="fa-solid fa-calculator"></i></span>
          <div class="po-total-entry"><span>Gross Amount</span><strong id="headReviewGross">—</strong></div>
          <div class="po-total-entry"><span>Delivery</span><strong id="headReviewDelivery">—</strong></div>
          <div class="po-total-entry"><span>Discount</span><strong id="headReviewDiscount">—</strong></div>
          <div class="po-total-entry"><span>VAT</span><strong id="headReviewVat">—</strong></div>
          <div class="po-total-entry po-total-net"><span>NET TOTAL</span><strong id="headReviewNet">—</strong></div>
        </div>
        <form id="poHeadReviewForm" method="POST" enctype="multipart/form-data">
          @csrf @method('PATCH')
          <div class="po-head-review-fields po-head-authorization-fields">
            <div class="po-head-signature-column">
              <label for="headReviewSignature"><i class="fa-solid fa-file-signature"></i> Signature upload <strong>*</strong></label>
              <div class="po-head-upload-zone" id="poHeadUploadZone">
                <i class="fa-solid fa-cloud-arrow-up po-head-upload-icon" aria-hidden="true"></i>
                <strong id="poHeadUploadTitle">Upload signature</strong>
                <small>JPG or PNG · Max 2 MB</small>
                <img id="headReviewSignaturePreview" alt="Cropped signature preview" hidden>
                <div class="po-head-upload-actions">
                  <label for="headReviewSignature" class="po-head-choose-file"><i class="fa-regular fa-image"></i> Choose file</label>
                  <button type="button" id="poHeadRemoveSignature" class="po-head-remove-signature" hidden>Remove</button>
                </div>
                <input id="headReviewSignature" name="signature" type="file" accept="image/png,image/jpeg" class="po-head-native-file">
              </div>
              <div id="poHeadCropModal" class="po-head-crop-modal" role="dialog" aria-modal="true" aria-label="Crop Purchase Head signature" hidden>
                <div class="po-head-crop-dialog">
                  <header><strong>Crop signature</strong><button type="button" id="poHeadCropCancel" aria-label="Cancel crop"><i class="fa-solid fa-xmark"></i></button></header>
                  <p>Drag the image to position it inside the landscape frame, then adjust the zoom.</p>
                  <canvas id="poHeadCropCanvas" width="720" height="280" aria-label="Signature cropping preview"></canvas>
                  <label for="poHeadCropZoom">Zoom</label><input id="poHeadCropZoom" type="range" min="1" max="3" value="1" step="0.01">
                  <footer><button type="button" id="poHeadCropBack">Cancel</button><button type="button" id="poHeadCropApply">Apply Crop</button></footer>
                </div>
              </div>
              <small id="poHeadUploadHelp">Your uploaded signature will appear here before approval.</small>
            </div>
            <div class="po-head-review-notes-column">
              <label for="poHeadSignatoryName"><i class="fa-regular fa-user"></i> Signatory name <strong>*</strong></label>
              <input id="poHeadSignatoryName" type="text" value="{{ auth()->user()->name ?? '' }}" readonly aria-readonly="true">
              <small>Your authenticated name will appear as the approving officer.</small>
              <label for="headReviewRemarks"><i class="fa-regular fa-note-sticky"></i> Review notes <small>(required for revision or rejection)</small></label>
              <textarea id="headReviewRemarks" name="review_remarks" rows="4" maxlength="2000" placeholder="Add any notes or remarks for this approval..."></textarea>
            </div>
          </div>
          <footer class="po-head-review-footer">
            <button type="button" id="headReviewRevision" class="po-head-review-revision">Request Revision</button>
            <button type="button" id="headReviewReject" class="po-head-review-reject">Reject PO</button>
            <button type="submit" id="headReviewApprove" class="po-head-review-approve" disabled>Approve Purchase Order</button>
          </footer>
        </form>
      </div>
    </div>
  </div>
  <style>
    .po-head-review-overlay{align-items:center;justify-content:center;padding:20px;z-index:1100}
    .purchase-orders-page .purchase-order-card .po-status-cell .po-status-box{display:inline-flex!important;align-items:center!important;gap:5px!important;padding:6px 9px!important;min-height:27px!important;border:1px solid #c4d9f5!important;border-radius:5px!important;background:#edf5ff!important;color:#15549e!important;box-shadow:none!important;outline:none!important;white-space:nowrap!important}.purchase-orders-page .purchase-order-card .po-status-cell .po-status-box::before,.purchase-orders-page .purchase-order-card .po-status-cell .po-status-box::after{content:none!important;display:none!important}
    .purchase-orders-page .po-review-action,.purchase-orders-page .po-review-action:hover,.purchase-orders-page .po-review-action:focus-visible{transform:none!important;scale:1!important;box-shadow:none!important}
    .purchase-orders-page .po-review-action{transition:background-color .16s ease,color .16s ease,border-color .16s ease!important}
    .purchase-orders-page .po-review-action:hover{background:#cfe3ff!important;color:#084aa9!important;border-color:#92b9ec!important}
    .po-head-review-dialog{width:min(850px,100%);max-height:calc(100vh - 40px);overflow:auto;background:#fff;border-radius:15px;box-shadow:0 25px 80px #0e27444d;font-family:inherit}
    .po-head-review-header{display:flex;justify-content:space-between;gap:14px;padding:22px 26px;color:#fff;background:linear-gradient(110deg,#163f79,#265dac)}
    .po-head-review-header small{font-size:10px;letter-spacing:.13em;font-weight:800;color:#c9e0ff}.po-head-review-header h2{margin:6px 0;font-size:21px}.po-head-review-header p{font-size:12px;margin:0;color:#dceaff}
    .po-head-review-x{align-self:flex-start;border:0;border-radius:7px;background:#ffffff20;color:#fff;padding:9px;cursor:pointer}
    .po-head-review-content{padding:22px 26px}.po-head-review-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:18px}
    .po-head-review-meta>div{border:1px solid #e2e9f2;border-radius:8px;padding:11px 13px;min-width:0}
    .po-head-review-meta span{display:block;font-size:11px;color:#708198;margin-bottom:4px}.po-head-review-meta strong{font-size:13px;color:#15385d;overflow-wrap:anywhere}
    .po-head-review-section h3{margin:0 0 10px;font-size:14px;color:#183d67}.po-head-review-table-wrap{overflow-x:auto}
    .po-head-review-table-wrap table{width:100%;border-collapse:collapse;font-size:12px}.po-head-review-table-wrap th{background:#edf3fb;text-align:left;color:#25476e;padding:11px 10px;font-size:11px}.po-head-review-table-wrap td{border-bottom:1px solid #e6edf5;padding:11px 10px;color:#264666}
    .po-head-review-summary{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:flex-end;padding:15px 0;border-bottom:1px solid #e7edf5;color:#56708c;font-size:11px}.po-head-review-summary>strong{color:#173e73;font-size:14px}
    .po-head-review-fields{display:flex;flex-direction:column;gap:8px;margin-top:16px}.po-head-review-fields label{font-size:12px;font-weight:750;color:#15385d}.po-head-review-fields label small,.po-head-review-fields>small{font-size:10px;color:#71849c;font-weight:400}
    .po-head-review-fields textarea,.po-head-review-fields input{border:1px solid #cedaeb;border-radius:7px;padding:10px;font:inherit;font-size:12px;width:100%;box-sizing:border-box}.po-head-review-fields img{max-width:260px;max-height:90px;object-fit:contain;border:1px solid #dce6f2;border-radius:7px;padding:9px}
    .po-head-review-footer{display:flex;justify-content:flex-end;flex-wrap:wrap;gap:9px;margin-top:20px}.po-head-review-footer button{border-radius:7px;padding:11px 17px;font-weight:750;font-size:12px;cursor:pointer}
    .po-head-review-revision{background:#fff6e5;border:1px solid #ecd2a0;color:#91621a}.po-head-review-reject{background:#fff0f1;border:1px solid #e9b0b7;color:#a82e3a}.po-head-review-approve{background:#2054a3;border:1px solid #2054a3;color:#fff}

    /* Align Purchase Head review with the Purchase Request Details modal. */
    .po-head-review-overlay{padding:18px!important}
    .po-head-review-dialog{width:min(815px,100%)!important;max-height:calc(100dvh - 36px)!important;display:flex;flex-direction:column;overflow:hidden!important;border-radius:14px!important;background:#fff!important;box-shadow:0 24px 70px rgba(13,35,66,.24)!important}
    .po-head-review-header{flex:none;align-items:center;padding:16px 20px!important;background:#fff!important;color:#112f55!important;border-bottom:1px solid #dce6f2!important}
    .po-head-review-header small{display:block;color:#3572b9!important;font-size:10px!important;letter-spacing:.09em}
    .po-head-review-header h2{font-size:18px!important;color:#102d50!important;margin:3px 0!important}
    .po-head-review-header p{color:#657b96!important;font-size:11px!important}
    .po-head-review-x{background:#f7faff!important;border:1px solid #dce6f2!important;color:#17365b!important;width:34px;height:34px;padding:0!important;display:grid;place-items:center;border-radius:7px!important}
    .po-head-review-x:hover{background:#eaf2ff!important}
    .po-head-review-content{overflow-y:auto;min-height:0;padding:16px 18px 0!important;background:#f8fafd}
    .po-head-review-meta{grid-template-columns:repeat(2,minmax(0,1fr));gap:0!important;margin-bottom:12px!important;background:#fff;border:1px solid #dae5f5;border-radius:10px;padding:5px 8px}
    .po-head-review-meta>div{border:0!important;border-radius:0!important;padding:11px 13px!important}
    .po-head-review-meta>div:nth-child(odd){border-right:1px solid #dfe8f4!important}
    .po-head-review-meta>div:nth-child(-n+2){border-bottom:1px solid #dfe8f4!important}
    .po-head-review-meta span{text-transform:uppercase;letter-spacing:.06em;font-size:10px!important;font-weight:750}
    .po-head-review-meta strong{font-size:12px!important}
    .po-head-review-section{border:1px solid #d9e5f5;border-radius:10px;background:white;padding:13px;margin-bottom:12px}
    .po-head-review-section h3{font-size:12px!important;margin:0 0 12px!important}
    .po-head-review-table-wrap{max-width:100%;overflow-x:auto}
    .po-head-review-table-wrap table{width:100%;min-width:0;table-layout:auto}
    .po-head-review-table-wrap th{white-space:nowrap;padding:10px 9px!important;background:#eaf1fa!important}
    .po-head-review-table-wrap td{padding:10px 9px!important;overflow-wrap:anywhere}
    .po-head-review-table-wrap th:last-child,.po-head-review-table-wrap td:last-child{text-align:right;white-space:nowrap}
    .po-head-review-summary{background:#fff;border:1px solid #dae5f5;border-radius:9px;padding:12px!important;gap:9px 15px!important;margin-bottom:12px;justify-content:flex-start!important}
    .po-head-review-summary>strong{margin-left:auto}
    .po-head-review-fields{background:#fff;border:1px solid #dae5f5;border-radius:10px;padding:13px;margin:0 0 12px!important}
    .po-head-review-fields textarea{min-height:65px;resize:vertical}
    .po-head-review-footer{position:sticky;bottom:0;z-index:2;margin:0 -18px!important;padding:12px 18px!important;background:#fff;border-top:1px solid #dce6f2;gap:8px!important}
    .po-head-review-footer button{padding:10px 13px!important;border-radius:7px!important}
    .po-jo-review-grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(220px,1fr);gap:12px}
    .po-jo-review-facts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
    .po-jo-review-facts>div{padding:9px;border:1px solid #e0e9f6;background:#f8faff;border-radius:7px}
    .po-jo-review-facts span{display:block;font-size:10px;color:#657d9a;margin-bottom:4px}
    .po-jo-review-facts strong{font-size:12px;color:#15385d;overflow-wrap:anywhere}
    .po-jo-review-signature{padding:12px;border:1px solid #dbe6f6;border-radius:8px;background:#f1f6ff;text-align:center;display:flex;flex-direction:column;align-items:center;justify-content:center}
    .po-jo-review-signature strong{font-size:11px;color:#32567d}
    .po-jo-review-signature img{width:100%;height:100px;object-fit:contain;background:white;border-radius:6px;margin-top:8px}
    .po-jo-review-signature p{font-size:11px;color:#71849c}
    @media(max-width:650px){.po-jo-review-grid{grid-template-columns:1fr}}
    @media(max-width:650px){.po-head-review-overlay{padding:9px!important}.po-head-review-dialog{max-height:calc(100dvh - 18px)!important}.po-head-review-content{padding:12px 12px 0!important}.po-head-review-footer{margin:0 -12px!important;padding:10px 12px!important}.po-head-review-summary>strong{margin-left:0}.po-head-review-table-wrap table{min-width:560px}}

    /* Wide desktop Purchase Head review matching the approved mockup. */
    #poHeadReviewModal .po-head-review-dialog{width:min(1120px,calc(100vw - 48px))!important;max-width:1120px!important}
    #poHeadReviewModal .po-head-review-meta{grid-template-columns:repeat(4,minmax(0,1fr))!important;padding:7px 9px!important;gap:0!important}
    #poHeadReviewModal .po-head-review-meta>div{border-bottom:0!important;border-right:1px solid #dfe8f4!important;padding:15px 14px!important}
    #poHeadReviewModal .po-head-review-meta>div:last-child{border-right:0!important}
    #poHeadReviewModal .po-jo-review-grid{grid-template-columns:minmax(0,1.65fr) minmax(300px,1fr)!important;align-items:stretch;gap:14px}
    #poHeadReviewModal .po-jo-review-facts{grid-template-columns:repeat(2,minmax(0,1fr));gap:0!important}
    #poHeadReviewModal .po-jo-review-facts>div{background:#fff;border:0;border-bottom:1px solid #e2eaf5;border-radius:0;padding:12px!important}
    #poHeadReviewModal .po-jo-review-facts>div:nth-last-child(-n+2){border-bottom:0}
    #poHeadReviewModal .po-jo-review-signature img{height:125px}
    #poHeadReviewModal .po-head-review-content{padding:16px 20px 0!important}
    #poHeadReviewModal .po-head-review-table-wrap table{min-width:0!important;width:100%!important}
    #poHeadReviewModal .po-head-review-table-wrap th,#poHeadReviewModal .po-head-review-table-wrap td{padding:11px 12px!important}
    #poHeadReviewModal .po-head-review-footer{margin:0 -20px!important;padding:14px 20px!important}
    @media(max-width:900px){
      #poHeadReviewModal .po-head-review-dialog{width:min(815px,calc(100vw - 24px))!important}
      #poHeadReviewModal .po-head-review-meta{grid-template-columns:repeat(2,minmax(0,1fr))!important}
      #poHeadReviewModal .po-head-review-meta>div:nth-child(2){border-right:0!important}
      #poHeadReviewModal .po-head-review-meta>div:nth-child(-n+2){border-bottom:1px solid #dfe8f4!important}
      #poHeadReviewModal .po-jo-review-grid{grid-template-columns:1fr!important}
    }
    @media(max-width:560px){
      #poHeadReviewModal .po-head-review-dialog{width:calc(100vw - 18px)!important}
      #poHeadReviewModal .po-head-review-meta{grid-template-columns:1fr!important}
      #poHeadReviewModal .po-head-review-meta>div{border-right:0!important;border-bottom:1px solid #dfe8f4!important}
      #poHeadReviewModal .po-head-review-meta>div:last-child{border-bottom:0!important}
      #poHeadReviewModal .po-head-review-content{padding:12px 12px 0!important}
      #poHeadReviewModal .po-head-review-footer{margin:0 -12px!important;padding:10px 12px!important}
      #poHeadReviewModal .po-head-review-table-wrap table{min-width:560px!important}
    }
    /* Final PO review polish: one level of dividers, clean cards, title icon. */
    #poHeadReviewModal .po-head-review-heading{display:flex;align-items:center;gap:12px;min-width:0}
    #poHeadReviewModal .po-head-review-heading-icon{display:grid;place-items:center;width:43px;height:43px;flex:0 0 43px;border:1px solid #d7e6fb;border-radius:10px;background:#eaf3ff;color:#1764c5;font-size:19px}
    #poHeadReviewModal .po-head-review-meta{border:1px solid #d9e5f5!important;border-radius:10px!important;padding:0 12px!important;box-shadow:0 1px 3px rgba(22,55,100,.03)}
    #poHeadReviewModal .po-head-review-meta>div{position:relative;border:0!important;padding:15px 14px!important}
    #poHeadReviewModal .po-head-review-meta>div:not(:last-child)::after{content:"";position:absolute;right:0;top:20%;height:60%;width:1px;background:#dfe9f6}
    #poHeadReviewModal .po-jo-review{border-color:#d9e5f5!important}
    #poHeadReviewModal .po-jo-review-facts>div{border:0!important;border-radius:0!important;position:relative;background:transparent!important}
    #poHeadReviewModal .po-jo-review-facts>div:nth-child(odd)::after{content:"";position:absolute;right:0;top:17%;height:66%;width:1px;background:#e2eaf5}
    #poHeadReviewModal .po-jo-review-facts>div:nth-child(-n+4){border-bottom:1px solid #e2eaf5!important}
    #poHeadReviewModal .po-jo-review-signature{border:1px solid #dbe6f6;border-radius:9px;background:#f3f7ff}
    @media(max-width:900px){#poHeadReviewModal .po-head-review-meta>div:nth-child(2)::after{display:none}#poHeadReviewModal .po-head-review-meta>div:nth-child(-n+2){border-bottom:1px solid #e2eaf5!important}}
    @media(max-width:560px){#poHeadReviewModal .po-head-review-meta>div::after{display:none}#poHeadReviewModal .po-head-review-meta>div:not(:last-child){border-bottom:1px solid #e2eaf5!important}#poHeadReviewModal .po-head-review-heading-icon{width:37px;height:37px;flex-basis:37px;font-size:16px}}
    /* Reuse GCT records-table typography, scoped to Purchase Head review. */
    #poHeadReviewModal .po-head-review-dialog,
    #poHeadReviewModal .po-head-review-dialog button,
    #poHeadReviewModal .po-head-review-dialog input,
    #poHeadReviewModal .po-head-review-dialog textarea{font-family:inherit!important}
    #poHeadReviewModal .po-review-items-table{font-family:inherit!important;font-size:12px!important;border-collapse:collapse!important;letter-spacing:normal!important}
    #poHeadReviewModal .po-review-items-table thead th{font-family:inherit!important;font-size:11px!important;font-weight:800!important;letter-spacing:0!important;line-height:1.35!important;text-transform:uppercase!important;color:#18365c!important}
    #poHeadReviewModal .po-review-items-table tbody td{font-family:inherit!important;font-size:12px!important;font-weight:600!important;letter-spacing:0!important;line-height:1.45!important;color:#18365c!important}
    #poHeadReviewModal .po-review-items-table tbody td:first-child{font-weight:700!important}
    #poHeadReviewModal .po-review-items-table tbody tr:last-child td{border-bottom:0!important}
    #poHeadReviewModal .po-head-review-summary,#poHeadReviewModal .po-head-review-summary span,#poHeadReviewModal .po-head-review-summary strong{font-family:inherit!important;letter-spacing:normal!important}
    #poHeadReviewModal .po-head-review-summary span{font-size:11px!important;font-weight:500}
    #poHeadReviewModal .po-head-review-summary strong{font-size:12px!important;font-weight:800!important}
    #poHeadReviewModal .po-head-review-fields label{font-family:inherit!important;font-size:12px!important;font-weight:750!important;letter-spacing:normal!important}
    #poHeadReviewModal .po-head-review-fields textarea,#poHeadReviewModal .po-head-review-fields input{font-family:inherit!important;font-size:12px!important;font-weight:500!important}
    #poHeadReviewModal .po-head-authorization-fields{display:grid!important;grid-template-columns:minmax(0,1fr) minmax(0,1fr)!important;gap:16px!important;align-items:start}
    #poHeadReviewModal .po-head-signature-column{border-right:1px solid #dce6f2;padding-right:16px;min-width:0}
    #poHeadReviewModal .po-head-review-notes-column{display:flex;flex-direction:column;gap:7px;min-width:0}
    #poHeadReviewModal .po-head-signature-column>label,#poHeadReviewModal .po-head-review-notes-column>label{display:block;margin-bottom:7px}
    #poHeadReviewModal .po-head-signature-column>small,#poHeadReviewModal .po-head-review-notes-column>small{display:block;font-size:10px;color:#708198;margin-top:7px}
    #poHeadReviewModal .po-head-upload-zone{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:7px;min-height:164px;padding:14px;border:1px dashed #bdd5f8;border-radius:10px;background:#f8faff;text-align:center}
    #poHeadReviewModal .po-head-upload-icon{font-size:23px;color:#587ba9}
    #poHeadReviewModal .po-head-upload-zone>strong{font-size:14px;color:#193d6d}
    #poHeadReviewModal .po-head-upload-zone>small{font-size:10px;color:#6a7e98}
    #poHeadReviewModal .po-head-native-file{position:absolute!important;width:1px!important;height:1px!important;opacity:0;overflow:hidden;pointer-events:none}
    #poHeadReviewModal .po-head-choose-file{cursor:pointer;display:inline-flex!important;gap:6px;align-items:center;padding:8px 12px;border:1px solid #c9dcf5;border-radius:7px;background:#fff;color:#1c4c87;font-size:11px;font-weight:700}
    #poHeadReviewModal .po-head-choose-file:hover{background:#eaf3ff}
    #poHeadReviewModal .po-head-upload-zone.has-preview .po-head-upload-icon{display:none}
    #poHeadReviewModal .po-head-upload-zone.has-preview img{display:block;max-width:100%;max-height:95px;object-fit:contain;background:#fff}
    #poHeadReviewModal .po-head-review-notes-column #poHeadSignatoryName{background:#f1f5fa!important;font-weight:650!important;color:#19385b}
    #poHeadReviewModal .po-head-review-notes-column textarea{min-height:105px!important}
    @media(max-width:650px){#poHeadReviewModal .po-head-authorization-fields{grid-template-columns:1fr!important}#poHeadReviewModal .po-head-signature-column{border-right:0;border-bottom:1px solid #dce6f2;padding-right:0;padding-bottom:14px}}
    /* Reference-matched blue icon tiles for PO and JO summary fields. */
    #poHeadReviewModal .po-head-review-meta>div,#poHeadReviewModal .po-jo-review-facts>div{display:flex!important;align-items:center!important;gap:11px!important;min-width:0}
    #poHeadReviewModal .po-review-field-icon{display:grid;place-items:center;flex:0 0 35px;width:35px;height:35px;border-radius:9px;background:#eaf3ff;color:#1767c9;font-size:14px}
    #poHeadReviewModal .po-review-field-copy{min-width:0;display:flex;flex-direction:column;gap:4px}
    #poHeadReviewModal .po-review-field-copy>span{font-size:10px!important;text-transform:uppercase;letter-spacing:.035em;color:#7084a0;font-weight:700}
    #poHeadReviewModal .po-review-field-copy>strong{font-size:12px!important;color:#0f315d;overflow-wrap:anywhere}
    #poHeadReviewModal #poJoStatus[data-approved="true"]{display:inline-flex;align-items:center;align-self:flex-start;border:1px solid #a8e1b9;border-radius:6px;padding:3px 9px;background:#e6f9ed;color:#17823c}
    /* Purchase Order totals — single component-like summary row, matching Purchase Requisitions. */
    #poHeadReviewModal .po-totals-grid{display:grid!important;grid-template-columns:44px repeat(5,minmax(0,1fr))!important;align-items:stretch!important;gap:0!important;padding:0!important;min-height:59px;margin:0 0 12px!important;overflow:hidden;background:#f5f8ff!important;border:1px solid #d6e4f7!important;border-radius:10px!important}
    #poHeadReviewModal .po-totals-icon{display:flex!important;align-items:center;justify-content:center;border-right:1px solid #dfebf9;color:#1766c3;font-size:16px!important}
    #poHeadReviewModal .po-total-entry{display:flex;flex-direction:column;justify-content:center;align-items:flex-start;gap:4px;min-width:0;padding:10px 12px;border-right:1px solid #dfebf9}
    #poHeadReviewModal .po-total-entry:last-child{border-right:0}
    #poHeadReviewModal .po-total-entry>span{font-family:inherit!important;font-size:10px!important;font-weight:500!important;line-height:1.2;color:#6c83a0;white-space:nowrap}
    #poHeadReviewModal .po-total-entry>strong{font-family:inherit!important;font-size:12px!important;line-height:1.3;font-weight:800!important;color:#17385f;white-space:nowrap}
    #poHeadReviewModal .po-total-net{background:#e9f2ff!important}
    #poHeadReviewModal .po-total-net>span{color:#1260bf;font-weight:800!important}
    #poHeadReviewModal .po-total-net>strong{color:#075ac2;font-size:14px!important}
    @media(max-width:680px){#poHeadReviewModal .po-totals-grid{grid-template-columns:38px repeat(3,minmax(0,1fr))!important}#poHeadReviewModal .po-total-net{grid-column:2/-1;border-top:1px solid #dfebf9}#poHeadReviewModal .po-total-entry:nth-child(5){border-top:1px solid #dfebf9}#poHeadReviewModal .po-total-entry{padding:9px!important}}
    /* Purchase Head approval is yellow only with a validated signature. */
    #poHeadReviewModal .po-head-review-approve{background:#ffbc00!important;border:1px solid #e6a600!important;color:#152b4d!important;font-weight:800!important;box-shadow:none!important;transform:none!important;transition:background-color .16s ease,border-color .16s ease!important}
    #poHeadReviewModal .po-head-review-approve:not(:disabled):hover{background:#f2aa00!important;border-color:#d99800!important;transform:none!important}
    #poHeadReviewModal .po-head-review-approve:disabled{background:#e5e7eb!important;border-color:#d1d5db!important;color:#727987!important;cursor:not-allowed!important;opacity:1!important;box-shadow:none!important}
    #poHeadReviewModal .po-head-upload-zone.has-preview img{display:block!important;width:100%!important;max-height:180px!important;height:150px!important;object-fit:contain!important;background:#fff!important}
    #poHeadReviewModal .po-head-remove-signature{background:#fff;border:1px solid #c9dcf5;border-radius:6px;padding:7px 12px;color:#214c83;cursor:pointer}
    #poHeadReviewModal .po-head-upload-zone.has-preview .po-head-upload-icon{display:none!important}
    #poHeadReviewModal .po-head-crop-modal[hidden]{display:none!important}
    #poHeadReviewModal .po-head-crop-modal{position:fixed;inset:0;z-index:3000;display:grid;place-items:center;background:rgba(9,27,53,.7);padding:16px}
    #poHeadReviewModal .po-head-crop-dialog{width:min(700px,100%);max-height:calc(100dvh - 32px);overflow:auto;background:#fff;border-radius:12px;padding:18px;box-shadow:0 18px 75px #0004;text-align:left}
    #poHeadReviewModal .po-head-crop-dialog header,#poHeadReviewModal .po-head-crop-dialog footer{display:flex;align-items:center;justify-content:space-between;gap:12px}
    #poHeadReviewModal .po-head-crop-dialog header strong{font-size:16px}
    #poHeadReviewModal .po-head-crop-dialog p{font-size:12px;color:#667991}
    #poHeadReviewModal #poHeadCropCanvas{display:block;width:100%;height:auto;touch-action:none;cursor:grab;border:1px dashed #aac9f0;background:white;border-radius:7px}
    #poHeadReviewModal #poHeadCropZoom{width:100%;margin:12px 0}
    #poHeadReviewModal .po-head-crop-dialog footer button{border:1px solid #bfd4ed;border-radius:7px;background:#fff;color:#194478;padding:10px 14px;cursor:pointer}
    #poHeadReviewModal #poHeadCropApply{background:#ffbc00;color:#142b49;border-color:#e6a600}
    /* Cropped signature state matches Maintenance JO: landscape image, then paired actions. */
    #poHeadReviewModal .po-head-upload-zone.has-preview{gap:0!important;min-height:0!important;padding:0!important;overflow:hidden!important;background:#fff!important;justify-content:flex-start!important}
    #poHeadReviewModal .po-head-upload-zone.has-preview #poHeadUploadTitle,
    #poHeadReviewModal .po-head-upload-zone.has-preview > small{display:none!important}
    #poHeadReviewModal .po-head-upload-actions{display:flex!important;align-items:center;justify-content:center;gap:8px;width:100%}
    #poHeadReviewModal .po-head-upload-zone.has-preview #headReviewSignaturePreview{display:block!important;order:0;width:100%!important;max-width:100%!important;height:156px!important;max-height:none!important;object-fit:contain!important;box-sizing:border-box!important;background:#fff!important;border:0!important;border-radius:0!important;margin:0!important;padding:5px!important}
    #poHeadReviewModal .po-head-upload-zone.has-preview .po-head-upload-actions{order:1;min-height:44px;padding:7px!important;background:#f3f7fc!important;border-top:1px solid #e2eaf5}
    #poHeadReviewModal .po-head-upload-zone.has-preview .po-head-choose-file,
    #poHeadReviewModal .po-head-upload-zone.has-preview .po-head-remove-signature{display:inline-flex!important;align-items:center;justify-content:center;min-height:29px!important;padding:5px 12px!important;margin:0!important;font-size:11px!important;font-weight:700!important}
    #poHeadReviewModal .po-head-upload-zone.has-preview .po-head-remove-signature[hidden]{display:none!important}
    @media(max-width:560px){.po-head-review-meta{grid-template-columns:1fr}.po-head-review-content{padding:15px}.po-head-review-header{padding:17px}.po-head-review-footer button{flex:1 1 100%}}
  </style>
  <script>
    (function(){
      const initializePoHeadReview=function(){
        const modal=document.getElementById('poHeadReviewModal'),form=document.getElementById('poHeadReviewForm');
        if(!modal||!form||modal.dataset.reviewInitialized==='true')return;
        modal.dataset.reviewInitialized='true';
        const byId=id=>document.getElementById(id);
        const money=v=>'₱'+(Number(v)||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
        let approveUrl='',decisionUrl='',previewUrl=null;
        const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        const close=()=>{byId('headReviewApprove').disabled=true;modal.classList.remove('show','active');modal.style.display='none';form.reset();byId('headReviewSignaturePreview').hidden=true;byId('poHeadUploadZone').classList.remove('has-preview');byId('poHeadUploadTitle').textContent='Upload signature';byId('poHeadRemoveSignature').hidden=true;byId('poHeadCropModal').hidden=true;if(previewUrl){URL.revokeObjectURL(previewUrl);previewUrl=null}window.GCTModalBackdrop?.sync?.()};
        document.querySelectorAll('.open-po-review-modal').forEach(button=>button.addEventListener('click',()=>{
          const d=button.dataset;approveUrl=d.approveUrl;decisionUrl=d.decisionUrl;byId('headReviewApprove').disabled=true;
          for(const [id,value] of Object.entries({headReviewNo:d.poNo,headReviewDate:d.poDate,headReviewPr:d.prNo||'—',headReviewSupplier:d.supplier,headReviewGross:money(d.gross),headReviewDelivery:money(d.delivery),headReviewDiscount:money(d.discount),headReviewVat:money(d.vat),headReviewNet:money(d.net)})){byId(id).textContent=value||'—'}
          for(const [id,val] of Object.entries({poJoNo:d.joNo,poJoBus:d.joBus,poJoType:d.joType,poJoStatus:d.joStatus,poJoApprover:d.joApprover,poJoDate:d.joDate})){byId(id).textContent=val||'—'}
          byId('poJoStatus').dataset.approved=d.joStatus==='Approved'?'true':'false';
          const joImg=byId('poJoSignature'),joEmpty=byId('poJoSignatureEmpty');
          joImg.hidden=true;joImg.removeAttribute('src');joEmpty.hidden=false;
          if(d.joSignature&&d.joStatus==='Approved'){
            joImg.onload=()=>{joImg.hidden=false;joEmpty.hidden=true};
            joImg.onerror=()=>{joImg.hidden=true;joEmpty.hidden=false};
            joImg.src=d.joSignature;
          }
          let items=[];try{items=JSON.parse(d.items||'[]')}catch(e){}
          byId('headReviewItems').innerHTML=items.map(item=>'<tr><td>'+esc(item.item_description)+'</td><td>'+esc(item.quantity)+'</td><td>'+esc(item.unit)+'</td><td>'+money(item.cost)+'</td><td>'+money(item.amount??(Number(item.quantity)*Number(item.cost)))+'</td></tr>').join('')||'<tr><td colspan="5">No items</td></tr>';
          form.action=approveUrl;modal.style.display='flex';modal.classList.add('show','active');window.GCTModalBackdrop?.sync?.();window.GCTSystemAnimations?.animateModalOpen?.(modal);
        }));
        byId('poHeadReviewClose').addEventListener('click',close);
        const signature=byId('headReviewSignature'),approveButton=byId('headReviewApprove'),crop=byId('poHeadCropModal'),canvas=byId('poHeadCropCanvas'),ctx=canvas.getContext('2d'),zoom=byId('poHeadCropZoom'),preview=byId('headReviewSignaturePreview'),zone=byId('poHeadUploadZone'),remove=byId('poHeadRemoveSignature'),title=byId('poHeadUploadTitle');
        const validSignature=()=>{const f=signature.files?.[0];return !!f&&['image/png','image/jpeg'].includes(f.type)&&f.size<=2097152&&zone.classList.contains('has-preview')&&!preview.hidden};
        let cropImage=null,cropUrl=null,cropX=0,cropY=0,drag=null;
        const clearSignature=()=>{signature.value='';approveButton.disabled=true;preview.hidden=true;preview.removeAttribute('src');zone.classList.remove('has-preview');remove.hidden=true;title.textContent='Upload signature';if(previewUrl){URL.revokeObjectURL(previewUrl);previewUrl=null}};
        const closeCrop=()=>{crop.hidden=true;if(cropUrl){URL.revokeObjectURL(cropUrl);cropUrl=null}cropImage=null;drag=null};
        const drawCrop=()=>{if(!cropImage)return;const w=canvas.width,h=canvas.height,scale=Math.max(w/cropImage.width,h/cropImage.height)*Number(zoom.value),iw=cropImage.width*scale,ih=cropImage.height*scale;cropX=Math.min(0,Math.max(w-iw,cropX));cropY=Math.min(0,Math.max(h-ih,cropY));ctx.fillStyle='#fff';ctx.fillRect(0,0,w,h);ctx.drawImage(cropImage,cropX,cropY,iw,ih)};
        signature.addEventListener('change',()=>{const file=signature.files?.[0];if(!file){clearSignature();return}if(!['image/png','image/jpeg'].includes(file.type)||file.size>2097152){clearSignature();signature.setCustomValidity('Choose JPG or PNG no larger than 2 MB');signature.reportValidity();return}signature.setCustomValidity('');approveButton.disabled=true;remove.hidden=true;cropUrl=URL.createObjectURL(file);const image=new Image();image.onload=()=>{cropImage=image;zoom.value='1';cropX=(canvas.width-Math.max(canvas.width/image.width,canvas.height/image.height)*image.width)/2;cropY=(canvas.height-Math.max(canvas.width/image.width,canvas.height/image.height)*image.height)/2;crop.hidden=false;drawCrop()};image.onerror=()=>{closeCrop();clearSignature();signature.setCustomValidity('Could not read signature image');signature.reportValidity()};image.src=cropUrl});
        zoom.addEventListener('input',drawCrop);
        canvas.addEventListener('pointerdown',e=>{if(!cropImage)return;drag={x:e.clientX,y:e.clientY};canvas.setPointerCapture(e.pointerId)});
        canvas.addEventListener('pointermove',e=>{if(!drag)return;const rect=canvas.getBoundingClientRect();cropX+=(e.clientX-drag.x)*canvas.width/rect.width;cropY+=(e.clientY-drag.y)*canvas.height/rect.height;drag={x:e.clientX,y:e.clientY};drawCrop()});
        canvas.addEventListener('pointerup',()=>drag=null);canvas.addEventListener('pointercancel',()=>drag=null);
        const cancelCrop=()=>{closeCrop();clearSignature()};
        byId('poHeadCropCancel').addEventListener('click',cancelCrop);byId('poHeadCropBack').addEventListener('click',cancelCrop);
        remove.addEventListener('click',clearSignature);
        byId('poHeadCropApply').addEventListener('click',()=>{if(!cropImage)return;drawCrop();canvas.toBlob(blob=>{if(!blob)return;if(blob.size>2097152){signature.setCustomValidity('Cropped image exceeds 2 MB');signature.reportValidity();return}const file=new File([blob],'po-head-signature.png',{type:'image/png'}),dt=new DataTransfer();dt.items.add(file);signature.files=dt.files;if(previewUrl)URL.revokeObjectURL(previewUrl);previewUrl=URL.createObjectURL(blob);preview.src=previewUrl;preview.hidden=false;zone.classList.add('has-preview');title.textContent='Cropped signature';remove.hidden=false;closeCrop();approveButton.disabled=!validSignature()},'image/png')});
                form.addEventListener('submit',e=>{if(!validSignature()){e.preventDefault();signature.setCustomValidity('Upload a valid JPG or PNG signature (maximum 2 MB) to approve.');signature.reportValidity();return}signature.setCustomValidity('')});
        signature.addEventListener('change',()=>signature.setCustomValidity(''));
        function decide(decision){const remarks=byId('headReviewRemarks');if(!remarks.value.trim()){remarks.setCustomValidity('Please explain your decision.');remarks.reportValidity();return}remarks.setCustomValidity('');form.action=decisionUrl;const input=document.createElement('input');input.type='hidden';input.name='decision';input.value=decision;form.append(input);form.submit()}
        byId('headReviewRevision').addEventListener('click',()=>decide('revision'));
        byId('headReviewReject').addEventListener('click',()=>decide('reject'));
        byId('headReviewRemarks').addEventListener('input',e=>e.target.setCustomValidity(''));
      };
      window.GCTPartialNavigation?.registerInitializer?.('po-head-review','.purchase-orders-page',initializePoHeadReview);
      if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initializePoHeadReview,{once:true});
      else initializePoHeadReview();
    })();
  </script>

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
