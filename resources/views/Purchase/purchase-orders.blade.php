<x-layout.app
  title="FROMS - Purchase Orders"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Purchase/purchase-orders.css',
    'resources/js/Purchase/purchase-orders.js'
  ]"
>

  @php
    $statuses = $statuses ?? [
      'Ordered',
      'For Pick-up',
      'For Delivery',
      'Delivered',
      'Picked Up',
    ];

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

        if ($rawItem === '') {
          continue;
        }

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
          'employee' => '',
          'item_description' => $itemName,
          'quantity' => $quantity > 0 ? $quantity : 1,
          'unit' => $unit,
          'cost' => 0,
        ];
      }

      if (count($prefillItems) === 0) {
        $prefillItems[] = [
          'pr_no' => $selectedPurchaseRequest->pr_no,
          'bus_no' => $selectedPurchaseRequest->bus_no,
          'employee' => '',
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
      'bus_no' => $selectedPurchaseRequest->bus_no,
      'employee' => '',
      'purpose' => $selectedPurchaseRequest->remarks ?? 'For purchase request ' . $selectedPurchaseRequest->pr_no,
      'items' => $prefillItems,
    ] : null;
  @endphp

  <div class="app">

    <x-layout.sidebar
      department="Purchase"
      subtitle="Department Module"
      icon="fa-cart-shopping"
      user-name="P. Admin"
      user-role="Purchase Admin"
      :items="[
        [
          'label' => 'Purchase Orders',
          'route' => 'purchase-orders',
          'icon' => 'fa-file-invoice'
        ],
        [
          'label' => 'Requested Purchase',
          'icon' => 'fa-clipboard-list',
          'children' => [
            [
              'label' => 'Maintenance Requests',
              'route' => 'maintenance-requests',
              'icon' => 'fa-screwdriver-wrench'
            ],
            [
              'label' => 'Inventory Restock',
              'route' => 'inventory-restock',
              'icon' => 'fa-boxes-stacked'
            ],
          ],
        ],
        [
          'label' => 'Scheduled Purchase',
          'route' => 'scheduled-purchase',
          'icon' => 'fa-calendar-check'
        ],
      ]"
    />

    <main class="main">

      <x-layout.topbar
        title="Purchase Order"
        subtitle="Manage procurement records for vehicle parts, equipment & operational materials"
        notification-count="6"
      />

      <section class="stats-grid">

        <x-ui.summary-card
          label="Total Purchase Orders"
          value="{{ $totalOrders }}"
          small="All procurement records"
          icon="fa-file-invoice"
          color="gray"
        />

        <x-ui.summary-card
          label="Ordered"
          value="{{ $ordered }}"
          small="Awaiting supplier action"
          icon="fa-file-invoice"
          color="blue"
        />

        <x-ui.summary-card
          label="For Pick-up"
          value="{{ $forPickup }}"
          small="Ready for collection"
          icon="fa-box"
          color="yellow"
        />

        <x-ui.summary-card
          label="Delivered / Picked Up"
          value="{{ $delivered }}"
          small="Completed procurement"
          icon="fa-circle-check"
          color="green"
        />

      </section>

      <section class="table-card purchase-order-card">

        <div class="section-header po-section-header">
          <div class="section-heading">
            <span class="section-icon">
              <i class="fa-solid fa-file-invoice-dollar"></i>
            </span>

            <div>
              <h2>Purchase Order Records</h2>
              <p>Track supplier orders, procurement progress, amounts, and delivery status.</p>
            </div>
          </div>

          <div class="section-count">
            <span>{{ $purchaseOrders->total() }}</span>
            records
          </div>
        </div>

        <form action="{{ route('purchase-orders') }}" method="GET" class="po-toolbar">

          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>

            <input
              type="text"
              name="search"
              value="{{ request('search') }}"
              placeholder="Search PO number, supplier, purpose, or status..."
            >
          </div>

          <div class="filter-group po-filter-field">
            <label for="poStatusFilter"></label>
            <select id="poStatusFilter" name="status" onchange="this.form.submit()">
              <option value="All States" {{ request('status', 'All States') === 'All States' ? 'selected' : '' }}>
                All Statuses
              </option>

              @foreach($statuses as $status)
                <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                  {{ $status }}
                </option>
              @endforeach
            </select>
          </div>

          <button type="button" id="openPoModal" class="primary-btn compact-new-po-btn">
            <i class="fa-solid fa-plus"></i>
            New PO
          </button>

        </form>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>PO NO.</th>
                <th>Supplier</th>
                <th>Item</th>
                <th>Request No.</th>
                <th>Request Type</th>
                <th>Qty</th>
                <th>Net Amount</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
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

                  $isInventoryRestock =
                    str_starts_with(strtoupper($displayRequestNo), 'RST-')
                    || strtoupper((string) ($firstItem['bus_no'] ?? '')) === 'RESTOCK';

                  $requestType = $isInventoryRestock
                    ? 'Inventory Restock'
                    : 'Maintenance Request';

                  $statusClass = strtolower(str_replace([' ', '/'], ['-', '-'], $purchaseOrder->status));
                  $isDraft = strtolower($purchaseOrder->status ?? '') === 'draft';
                  $isFinalStatus = in_array($purchaseOrder->status, ['Delivered', 'Picked Up'], true);
                @endphp

                <tr>
                  <td>
                    <div class="po-number-cell">
                      <span class="po-document-icon"><i class="fa-solid fa-file-invoice"></i></span>
                      <div>
                        <strong>{{ $purchaseOrder->po_no }}</strong>
                        <small>Purchase order</small>
                      </div>
                    </div>
                  </td>

                  <td>
                    <div class="po-primary-text">{{ $purchaseOrder->supplier_name }}</div>
                    <div class="po-secondary-text">{{ $purchaseOrder->supplier_address_tel ?: 'No contact details' }}</div>
                  </td>

                  <td>
                    <strong>{{ $firstItemName ?: '—' }}</strong>
                  </td>

                  <td>
                    <span class="po-reference">
                      {{ $displayRequestNo }}
                    </span>
                  </td>

                  <td>
                    <span class="po-request-type {{ $isInventoryRestock ? 'inventory-restock' : 'maintenance-request' }}">
                      <i class="fa-solid {{ $isInventoryRestock ? 'fa-boxes-stacked' : 'fa-screwdriver-wrench' }}"></i>
                      {{ $requestType }}
                    </span>
                  </td>

                  <td><span class="po-qty">{{ $firstItem['quantity'] ?? '—' }}</span></td>

                  <td>
                    <div class="po-amount">&#8369;{{ number_format((float) $purchaseOrder->net_amount, 2) }}</div>
                  </td>

                  <td>
                    <form
                      action="{{ route('purchase-orders.update-status', $purchaseOrder->id) }}"
                      method="POST"
                      class="status-update-form"
                    >
                      @csrf
                      @method('PATCH')

                      <select
                        name="status"
                        class="po-status-select {{ $statusClass }} {{ $isFinalStatus ? 'is-final' : '' }}"
                        onchange="this.form.submit()"
                        title="{{ $isFinalStatus ? 'Final status — this purchase order can no longer be changed.' : 'Change purchase order status' }}"
                        {{ $isFinalStatus ? 'disabled' : '' }}
                      >
                        @foreach($statuses as $status)
                          <option value="{{ $status }}" {{ $purchaseOrder->status === $status ? 'selected' : '' }}>
                            {{ $status }}
                          </option>
                        @endforeach
                      </select>
                    </form>
                  </td>

                  <td>
                    <div class="po-date"><strong>{{ $purchaseOrder->po_date ? \Carbon\Carbon::parse($purchaseOrder->po_date)->format('M d, Y') : '—' }}</strong><small>{{ $purchaseOrder->po_date ? \Carbon\Carbon::parse($purchaseOrder->po_date)->format('l') : '' }}</small></div>
                  </td>

                  <td>
                    <div class="actions">

                      <button
                        type="button"
                        class="action-btn {{ $isDraft ? 'edit open-edit-po-modal' : 'view open-view-po-modal' }}"
                        title="{{ $isDraft ? 'Edit PO' : 'View PO' }}"
                        data-id="{{ $purchaseOrder->id }}"
                        data-po-no="{{ $purchaseOrder->po_no }}"
                        data-po-date="{{ $purchaseOrder->po_date }}"
                        data-supplier-name="{{ $purchaseOrder->supplier_name }}"
                        data-supplier-address-tel="{{ $purchaseOrder->supplier_address_tel }}"
                        data-terms="{{ $purchaseOrder->terms }}"
                        data-terms-of-payment="{{ $purchaseOrder->terms_of_payment }}"
                        data-purpose="{{ $purchaseOrder->purpose }}"
                        data-status="{{ $purchaseOrder->status }}"
                        data-delivery-fee="{{ $purchaseOrder->delivery_fee }}"
                        data-discount="{{ $purchaseOrder->discount }}"
                        data-vat="{{ $purchaseOrder->vat }}"
                        data-items='@json($items)'
                        data-update-url="{{ route('purchase-orders.update', $purchaseOrder->id) }}"
                      >
                        <i class="fa-solid {{ $isDraft ? 'fa-pen-to-square' : 'fa-eye' }}"></i>
                      </button>

                      @if($isDraft)
                        <form
                          id="deletePoForm-{{ $purchaseOrder->id }}"
                          action="{{ route('purchase-orders.destroy', $purchaseOrder->id) }}"
                          method="POST"
                        >
                          @csrf
                          @method('DELETE')

                          <button
                            type="button"
                            class="action-btn delete open-delete-po-modal"
                            title="Delete"
                            data-id="{{ $purchaseOrder->id }}"
                            data-po-no="{{ $purchaseOrder->po_no }}"
                          >
                            <i class="fa-solid fa-trash"></i>
                          </button>
                        </form>
                      @endif

                    </div>
                  </td>
                </tr>
              @empty
                <x-ui.empty-row
                  colspan="10"
                  message="No purchase orders found."
                />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$purchaseOrders" />

      </section>

    </main>
  </div>

  {{-- CREATE / EDIT / VIEW PO MODAL --}}
  <div
    id="poModal"
    class="modal-overlay {{ $openPoModal ? 'show active' : '' }}"
  >
    <div class="modal-card modal-box po-modal-box">

      <div class="po-modal-header">
        <div>
          <h2 id="poModalTitle">New Purchase Order</h2>
        </div>

        <button type="button" id="closePoModal" class="po-close-btn">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <div class="po-company-title">
        <h3>GCT TRANSPORT SERVICES INC.</h3>
        <p>PURCHASE ORDER</p>
      </div>

      <form
        id="poForm"
        action="{{ route('purchase-orders.store') }}"
        method="POST"
        class="po-form"
        data-store-url="{{ route('purchase-orders.store') }}"
      >
        @csrf

        <input type="hidden" name="_method" id="poFormMethod" value="POST">

        <input
          type="hidden"
          name="purchase_request_id"
          id="purchase_request_id"
          value="{{ $selectedPurchaseRequest?->id }}"
        >

        <div class="po-form-grid">

          <div class="po-form-group">
            <label>Supplier / To</label>
            <input
              type="text"
              name="supplier_name"
              id="supplier_name"
              placeholder="Supplier name"
              required
            >
          </div>

          <div class="po-form-group">
            <label>PO Number</label>
            <input
              type="text"
              name="po_no"
              id="po_no"
              value="{{ $nextPoNo ?? '' }}"
              readonly
            >
          </div>

          <div class="po-form-group">
            <label>Address / Tel No.</label>
            <input
              type="text"
              name="supplier_address_tel"
              id="supplier_address_tel"
              placeholder="Supplier address / contact"
            >
          </div>

          <div class="po-form-group">
            <label>Date</label>
            <input
              type="date"
              name="po_date"
              id="po_date"
              value="{{ now()->toDateString() }}"
              required
            >
          </div>

          <div class="po-form-group">
            <label>Terms</label>
            <input
              type="text"
              name="terms"
              id="terms"
              placeholder="Example: 15"
            >
          </div>

          <div class="po-form-group">
            <label>Terms of Payment</label>
            <input
              type="text"
              name="terms_of_payment"
              id="terms_of_payment"
              placeholder="Example: Check"
            >
          </div>

          <div class="po-form-group">
            <label>Status</label>
            <select name="status" id="po_status" required>
              @foreach($statuses as $status)
                <option value="{{ $status }}" {{ $status === 'Ordered' ? 'selected' : '' }}>
                  {{ $status }}
                </option>
              @endforeach
            </select>
          </div>

        </div>

        <div class="po-request-info">
          <div class="po-form-group">
            <label>PR #</label>
            <input
              type="text"
              id="main_pr_no"
              placeholder="PR No."
              value="{{ $selectedPurchaseRequest?->pr_no }}"
              readonly
            >
          </div>

          <div class="po-form-group">
            <label>Bus No.</label>
            <input
              type="text"
              id="main_bus_no"
              placeholder="Bus No."
              value="{{ $selectedPurchaseRequest?->bus_no }}"
            >
          </div>

          <div class="po-form-group">
            <label>Employee</label>
            <input
              type="text"
              id="main_employee"
              placeholder="Employee"
              readonly
            >
          </div>
        </div>

        <div class="po-items-section">
          <label class="po-items-title">Items</label>

          <div class="po-items-header">
            <span>Item Description</span>
            <span>Qty</span>
            <span>Unit</span>
            <span>Cost</span>
            <span>PO Amount</span>
            <span></span>
          </div>

          <div id="poItemsContainer" class="po-items-container">
            {{-- JS renders rows --}}
          </div>

          <button type="button" id="addPoItemBtn" class="add-po-item-btn">
            <i class="fa-solid fa-plus"></i>
            Add Other Item
          </button>
        </div>

        <div class="po-bottom-grid">

          <div class="po-form-group">
            <label>Purpose</label>
            <textarea
              name="purpose"
              id="purpose"
              rows="5"
              placeholder="Example: For Warehouse Stock."
            >{{ $selectedPurchaseRequest?->remarks }}</textarea>
          </div>

          <div class="po-totals-box">

            <div class="po-total-row">
              <label>Gross Amount</label>
              <input type="text" id="gross_amount_display" value="₱0.00" readonly>
            </div>

            <div class="po-total-row">
              <label>Delivery Fee</label>
              <input
                type="number"
                name="delivery_fee"
                id="delivery_fee"
                min="0"
                step="0.01"
                value="0"
              >
            </div>

            <div class="po-total-row">
              <label>Discount</label>
              <input
                type="number"
                name="discount"
                id="discount"
                min="0"
                step="0.01"
                value="0"
              >
            </div>

            <div class="po-total-row">
              <label>VAT</label>
              <input
                type="number"
                name="vat"
                id="vat"
                min="0"
                step="0.01"
                value="0"
              >
            </div>

            <div class="po-total-row">
              <label>Net Amount</label>
              <input type="text" id="net_amount_display" value="₱0.00" readonly>
            </div>

          </div>

        </div>

        <div class="po-modal-actions" id="poEditActions">
          <button type="button" id="cancelPoModal" class="secondary-btn po-cancel-btn">
            Cancel
          </button>

          <button type="submit" class="primary-btn po-save-btn">
            Save Purchase Order
          </button>
        </div>

        <div class="po-modal-actions hidden" id="poViewActions">
          <button type="button" id="closeViewPoModal" class="secondary-btn po-cancel-btn">
            Close
          </button>
        </div>

      </form>
    </div>
  </div>

  <script>
    window.purchaseOrderPrefill = @json($prefillData);
    window.purchaseOrderShouldOpen = @json($openPoModal);
  </script>

  {{-- DELETE MODAL --}}
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