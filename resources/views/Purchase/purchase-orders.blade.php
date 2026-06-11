<x-layout.app
  title="FROMS - Purchase Order"
  :assets="[
    'resources/css/Main-style/main.css',
    'resources/css/Main-style/sidebar.css',
    'resources/css/Purchase/purchase-orders.css',
    'resources/js/Purchase/purchase-orders.js'
  ]"
>

  <x-ui.action-buttom-modal
    mode="feedback"
    feedback-type="success"
    :message="session('success')"
  />

  <x-ui.action-buttom-modal
    mode="feedback"
    feedback-type="error"
    :message="session('error')"
  />

  @php
    $purchaseRequestOptions = $availablePurchaseRequests->map(function ($pr) {
      return [
        'id' => $pr->id,
        'pr_no' => $pr->pr_no,
        'job_order_no' => $pr->job_order_no,
        'bus_no' => $pr->bus_no,
        'employee' => $pr->employee ?? $pr->requested_by ?? $pr->created_by ?? $pr->job_order_no ?? '',
        'item' => $pr->item,
        'quantity' => $pr->quantity,
        'unit' => $pr->unit ?? 'PC',
      ];
    })->values();

    $selectedPurchaseRequestData = null;

    if ($selectedPurchaseRequest ?? false) {
      $selectedPurchaseRequestData = [
        'id' => $selectedPurchaseRequest->id,
        'pr_no' => $selectedPurchaseRequest->pr_no,
        'job_order_no' => $selectedPurchaseRequest->job_order_no,
        'bus_no' => $selectedPurchaseRequest->bus_no,
        'employee' => $selectedPurchaseRequest->employee
          ?? $selectedPurchaseRequest->requested_by
          ?? $selectedPurchaseRequest->created_by
          ?? $selectedPurchaseRequest->job_order_no
          ?? '',
        'item' => $selectedPurchaseRequest->item,
        'quantity' => $selectedPurchaseRequest->quantity,
        'unit' => $selectedPurchaseRequest->unit ?? 'PC',
      ];
    }
  @endphp

  <script type="application/json" id="purchaseRequestOptionsJson">
    @json($purchaseRequestOptions)
  </script>

  @if($selectedPurchaseRequestData)
    <script type="application/json" id="selectedPurchaseRequestJson">
      @json($selectedPurchaseRequestData)
    </script>
  @endif

  @if($openPoModal ?? false)
    <span id="openPoModalFlag" hidden></span>
  @endif

  <datalist id="purchaseRequestList">
    @foreach($availablePurchaseRequests as $pr)
      <option value="{{ $pr->pr_no }}">
    @endforeach

    @if($selectedPurchaseRequest ?? false)
      <option value="{{ $selectedPurchaseRequest->pr_no }}">
    @endif
  </datalist>

  <div class="app">

    <x-layout.sidebar
      department="Purchase"
      subtitle="Department Module"
      icon="fa-truck"
      user-name="R. Lim"
      user-role="Purchase Admin"
      :items="[
        ['label' => 'Purchase Orders', 'route' => 'purchase-orders', 'icon' => 'fa-file-invoice'],
        ['label' => 'Requested Purchase', 'route' => 'requested-purchase', 'icon' => 'fa-clipboard-list'],
        ['label' => 'Scheduled Purchase', 'route' => 'scheduled-purchase', 'icon' => 'fa-calendar-check'],
      ]"
    />

    <main class="main">

      <x-layout.topbar
        title="Purchase Order"
        subtitle="Manage procurement records for vehicle parts, equipment & operational materials"
        notification-count="6"
      />

      @if($errors->any())
        <div class="alert-error">
          <ul>
            @foreach($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <section class="stats-grid">

        <x-ui.summary-card
          label="Total Orders"
          value="{{ $totalOrders }}"
          small="Purchase orders"
          icon="fa-file-invoice"
          color="gray"
        />

        <x-ui.summary-card
          label="Ordered"
          value="{{ $ordered }}"
          small="PO created"
          icon="fa-file-invoice"
          color="blue"
        />

        <x-ui.summary-card
          label="For Pick-up"
          value="{{ $forPickup }}"
          small="Waiting pickup"
          icon="fa-box"
          color="yellow"
        />

        <x-ui.summary-card
          label="For Delivery / Done"
          value="{{ $forDelivery + $delivered }}"
          small="Delivery and completed"
          icon="fa-circle-check"
          color="green"
        />

      </section>

      <section class="table-card po-card">

        <div class="section-header">
          <div>
            <h2>Purchase Order Records</h2>
            <p>Track purchase orders, supplier status, and procurement progress</p>
          </div>
        </div>

        <form action="{{ route('purchase-orders') }}" method="GET" class="toolbar po-toolbar">

          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input
              type="text"
              name="search"
              value="{{ request('search') }}"
              placeholder="Search PO number, supplier, purpose, or status..."
            >
          </div>

          <select name="status" onchange="this.form.submit()">
            <option value="All States" {{ request('status') == 'All States' ? 'selected' : '' }}>
              All States
            </option>

            @foreach($statuses as $status)
              <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                {{ $status }}
              </option>
            @endforeach
          </select>

          <button type="button" id="openPoModal" class="primary-btn">
            <i class="fa-solid fa-plus"></i>
            New PO
          </button>

        </form>

        <div class="table-wrap">
          <table class="po-table">
            <thead>
              <tr>
                <th>PO Number</th>
                <th>Supplier</th>
                <th>Item</th>
                <th>PR No.</th>
                <th>Bus No.</th>
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
                  $poItems = $purchaseOrder->items ?? [];
                  $firstItem = $poItems[0] ?? null;
                  $statusClass = strtolower(str_replace(' ', '-', $purchaseOrder->status));
                @endphp

                <tr>
                  <td>{{ $purchaseOrder->po_no }}</td>

                  <td>{{ $purchaseOrder->supplier_name }}</td>

                  <td>
                    <strong>{{ $firstItem['item_description'] ?? '—' }}</strong>
                  </td>

                  <td>{{ $firstItem['pr_no'] ?? '—' }}</td>

                  <td>{{ $firstItem['bus_no'] ?? '—' }}</td>

                  <td>{{ $firstItem['quantity'] ?? '—' }}</td>

                  <td>
                    <strong>₱{{ number_format($purchaseOrder->net_amount, 2) }}</strong>
                  </td>

                  <td>
                    <form
                      action="{{ route('purchase-orders.update-status', $purchaseOrder->id) }}"
                      method="POST"
                      class="po-status-form"
                    >
                      @csrf
                      @method('PATCH')

                      <select
                        name="status"
                        class="po-status-dropdown {{ $statusClass }}"
                        onchange="this.form.submit()"
                        title="Change PO status"
                      >
                        @foreach($statuses as $status)
                          <option value="{{ $status }}" {{ $purchaseOrder->status === $status ? 'selected' : '' }}>
                            {{ $status }}
                          </option>
                        @endforeach
                      </select>
                    </form>
                  </td>

                  <td>{{ $purchaseOrder->po_date ? $purchaseOrder->po_date->format('m/d/y') : '—' }}</td>

                  <td>
                    <div class="po-actions">

                      <button
                        type="button"
                        class="edit open-edit-po-modal"
                        title="View / Edit"
                        data-id="{{ $purchaseOrder->id }}"
                        data-update-url="{{ route('purchase-orders.update', $purchaseOrder->id) }}"
                        data-po-no="{{ $purchaseOrder->po_no }}"
                        data-po-date="{{ $purchaseOrder->po_date ? $purchaseOrder->po_date->format('Y-m-d') : '' }}"
                        data-supplier-name="{{ $purchaseOrder->supplier_name }}"
                        data-supplier-address-tel="{{ $purchaseOrder->supplier_address_tel }}"
                        data-terms="{{ $purchaseOrder->terms }}"
                        data-terms-of-payment="{{ $purchaseOrder->terms_of_payment }}"
                        data-purpose="{{ $purchaseOrder->purpose }}"
                        data-delivery-fee="{{ $purchaseOrder->delivery_fee }}"
                        data-discount="{{ $purchaseOrder->discount }}"
                        data-vat="{{ $purchaseOrder->vat }}"
                        data-status="{{ $purchaseOrder->status }}"
                        data-items='@json($poItems)'
                      >
                        <i class="fa-solid fa-pen-to-square"></i>
                      </button>

                      <form
                        id="deletePoForm-{{ $purchaseOrder->id }}"
                        action="{{ route('purchase-orders.destroy', $purchaseOrder->id) }}"
                        method="POST"
                      >
                        @csrf
                        @method('DELETE')

                        <button
                          type="button"
                          class="delete open-delete-po-modal"
                          title="Delete"
                          data-id="{{ $purchaseOrder->id }}"
                          data-po-no="{{ $purchaseOrder->po_no }}"
                        >
                          <i class="fa-solid fa-trash-can"></i>
                        </button>
                      </form>

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

  {{-- NEW PO MODAL --}}
  <div id="poModal" class="modal-overlay">
    <div class="modal-box po-document-modal">

      <div class="modal-header">
        <h2>New Purchase Order</h2>

        <button type="button" id="closePoModal" class="close-btn">
          &times;
        </button>
      </div>

      <form action="{{ route('purchase-orders.store') }}" method="POST" class="po-document-form">
        @csrf

        <input
          type="hidden"
          name="purchase_request_id"
          id="purchaseRequestIdInput"
          value="{{ $selectedPurchaseRequest?->id }}"
        >

        <div class="po-doc-header">
          <div>
            <h3>GCT TRANSPORT SERVICES INC.</h3>
            <p>PURCHASE ORDER</p>
          </div>
        </div>

        <div class="po-doc-grid">
          <div class="form-group">
            <label>Supplier / To</label>
            <input type="text" name="supplier_name" placeholder="Supplier name" required>
          </div>

          <div class="form-group">
            <label>PO Number</label>
            <input type="text" value="{{ $nextPoNo }}" readonly>
          </div>

          <div class="form-group">
            <label>Address / Tel No.</label>
            <input type="text" name="supplier_address_tel" placeholder="Supplier address / contact">
          </div>

          <div class="form-group">
            <label>Date</label>
            <input type="text" value="{{ now()->format('m/d/Y') }}" readonly>
          </div>

          <div class="form-group">
            <label>Terms</label>
            <input type="text" name="terms" placeholder="Example: 15">
          </div>

          <div class="form-group">
            <label>Terms of Payment</label>
            <input type="text" name="terms_of_payment" placeholder="Example: Check">
          </div>
        </div>

        <div class="po-items-section">
          <label>Items</label>

          <div class="po-items-head">
            <span>PR #</span>
            <span>Bus No.</span>
            <span>Employee</span>
            <span>Item Description</span>
            <span>Qty</span>
            <span>Unit</span>
            <span>Cost</span>
            <span>PO Amount</span>
            <span></span>
          </div>

          <div id="poItemsWrapper" class="po-items-wrapper">
            <div class="po-item-row">
              <input
                type="text"
                class="po-pr-no"
                name="items[0][pr_no]"
                list="purchaseRequestList"
                placeholder="PR No."
                value="{{ $selectedPurchaseRequest?->pr_no }}"
              >

              <input
                type="text"
                class="po-bus-no"
                name="items[0][bus_no]"
                placeholder="Bus No."
                value="{{ $selectedPurchaseRequest?->bus_no }}"
                readonly
              >

              <input
                type="text"
                class="po-employee"
                name="items[0][employee]"
                placeholder="Employee"
                value="{{ $selectedPurchaseRequest?->job_order_no }}"
              >

              <input
                type="text"
                class="po-item-description"
                name="items[0][item_description]"
                placeholder="Item description"
                value="{{ $selectedPurchaseRequest?->item }}"
                required
              >

              <input
                type="number"
                class="po-item-quantity"
                name="items[0][quantity]"
                min="1"
                step="1"
                placeholder="Qty"
                value="{{ $selectedPurchaseRequest?->quantity }}"
                required
              >

              <input
                type="text"
                class="po-item-unit"
                name="items[0][unit]"
                placeholder="Unit"
                value="PC"
              >

              <input
                type="text"
                class="po-item-cost"
                name="items[0][cost]"
                placeholder="₱0.00"
                required
              >

              <input
                type="text"
                class="po-item-amount"
                value="₱0.00"
                readonly
              >

              <button type="button" class="remove-po-item-btn" style="display: none;">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </div>
          </div>

          <button type="button" id="addPoItemBtn" class="add-part-btn">
            <i class="fa-solid fa-plus"></i>
            Add Other Item
          </button>
        </div>

        <div class="po-bottom-grid">
          <div class="form-group">
            <label>Purpose</label>
            <textarea name="purpose" placeholder="Example: For Warehouse Stock.">{{ $selectedPurchaseRequest ? 'Created from ' . $selectedPurchaseRequest->pr_no : '' }}</textarea>
          </div>

          <div class="po-totals-box">
            <div>
              <label>Gross Amount</label>
              <input type="text" id="po_gross_display" value="₱0.00" readonly>
            </div>

            <div>
              <label>Delivery Fee</label>
              <input type="text" id="po_delivery_fee" name="delivery_fee" value="₱0.00">
            </div>

            <div>
              <label>Discount</label>
              <input type="text" id="po_discount" name="discount" value="₱0.00">
            </div>

            <div>
              <label>VAT</label>
              <input type="text" id="po_vat" name="vat" value="₱0.00">
            </div>

            <div>
              <label>Net Amount</label>
              <input type="text" id="po_net_display" value="₱0.00" readonly>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>Status</label>
          <select name="status" required>
            @foreach($statuses as $status)
              <option value="{{ $status }}" {{ $status === 'Ordered' ? 'selected' : '' }}>
                {{ $status }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="modal-actions full-width">
          <button type="button" id="cancelPoModal" class="cancel-btn">
            Cancel
          </button>

          <button type="submit" class="save-btn">
            Save Purchase Order
          </button>
        </div>
      </form>

    </div>
  </div>

  {{-- EDIT PO MODAL --}}
  <div id="editPoModal" class="modal-overlay">
    <div class="modal-box po-document-modal">

      <div class="modal-header">
        <h2>Purchase Order Details</h2>

        <button type="button" id="closeEditPoModal" class="close-btn">
          &times;
        </button>
      </div>

      <form id="editPoForm" method="POST" class="po-document-form">
        @csrf
        @method('PUT')

        <div class="po-doc-header">
          <div>
            <h3>GCT TRANSPORT SERVICES INC.</h3>
            <p>PURCHASE ORDER</p>
          </div>
        </div>

        <div class="po-doc-grid">
          <div class="form-group">
            <label>Supplier / To</label>
            <input type="text" name="supplier_name" id="edit_supplier_name" required>
          </div>

          <div class="form-group">
            <label>PO Number</label>
            <input type="text" name="po_no" id="edit_po_no" readonly required>
          </div>

          <div class="form-group">
            <label>Address / Tel No.</label>
            <input type="text" name="supplier_address_tel" id="edit_supplier_address_tel">
          </div>

          <div class="form-group">
            <label>Date</label>
            <input type="date" name="po_date" id="edit_po_date" required>
          </div>

          <div class="form-group">
            <label>Terms</label>
            <input type="text" name="terms" id="edit_terms">
          </div>

          <div class="form-group">
            <label>Terms of Payment</label>
            <input type="text" name="terms_of_payment" id="edit_terms_of_payment">
          </div>
        </div>

        <div class="po-items-section">
          <label>Items</label>

          <div class="po-items-head">
            <span>PR #</span>
            <span>Bus No.</span>
            <span>Employee</span>
            <span>Item Description</span>
            <span>Qty</span>
            <span>Unit</span>
            <span>Cost</span>
            <span>PO Amount</span>
            <span></span>
          </div>

          <div id="editPoItemsWrapper" class="po-items-wrapper"></div>

          <button type="button" id="editAddPoItemBtn" class="add-part-btn">
            <i class="fa-solid fa-plus"></i>
            Add Other Item
          </button>
        </div>

        <div class="po-bottom-grid">
          <div class="form-group">
            <label>Purpose</label>
            <textarea name="purpose" id="edit_purpose"></textarea>
          </div>

          <div class="po-totals-box">
            <div>
              <label>Gross Amount</label>
              <input type="text" id="edit_po_gross_display" value="₱0.00" readonly>
            </div>

            <div>
              <label>Delivery Fee</label>
              <input type="text" id="edit_po_delivery_fee" name="delivery_fee" value="₱0.00">
            </div>

            <div>
              <label>Discount</label>
              <input type="text" id="edit_po_discount" name="discount" value="₱0.00">
            </div>

            <div>
              <label>VAT</label>
              <input type="text" id="edit_po_vat" name="vat" value="₱0.00">
            </div>

            <div>
              <label>Net Amount</label>
              <input type="text" id="edit_po_net_display" value="₱0.00" readonly>
            </div>
          </div>
        </div>

        <input type="hidden" name="purchase_request_id" id="edit_purchase_request_id">

        <div class="form-group">
          <label>Status</label>
          <select name="status" id="edit_status" required>
            @foreach($statuses as $status)
              <option value="{{ $status }}">
                {{ $status }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="modal-actions full-width">
          <button type="button" id="cancelEditPoModal" class="cancel-btn">
            Cancel
          </button>

          <button type="submit" class="save-btn">
            Update Purchase Order
          </button>
        </div>
      </form>

    </div>
  </div>

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