<x-layout.app
  title="FROMS - Warehouse Part Requests"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Warehouse/part-requests.css',
    'resources/js/Warehouse/part-requests.js'
  ]"
>
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

    <main class="main">
      <x-layout.topbar
        title="Part Requests"
        subtitle="Approved purchase requests from Maintenance for warehouse processing"
        notification-count="6"
      />

      <section class="stats-grid inventory-stats">
        <x-ui.summary-card label="Approved" value="{{ $approved ?? 0 }}" small="Ready to process" icon="fa-check" color="green" />
        <x-ui.summary-card label="For Purchase" value="{{ $forPurchase ?? 0 }}" small="Parts unavailable" icon="fa-cart-shopping" color="blue" />
        <x-ui.summary-card label="Delivered" value="{{ $delivered ?? 0 }}" small="Supplier delivered" icon="fa-box" color="yellow" />
        <x-ui.summary-card label="Issued" value="{{ $issued ?? 0 }}" small="Released parts" icon="fa-box-open" color="gray" />
      </section>

      <section class="table-card inventory-card warehouse-part-card">
        <div class="section-header">
          <div>
            <h2>Warehouse Part Request Records</h2>
            <p>Track active approved requests, unavailable parts, and inventory availability.</p>
          </div>
        </div>

        <form action="{{ route('part-requests') }}" method="GET" class="toolbar inventory-toolbar warehouse-part-toolbar">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input
              type="text"
              name="search"
              value="{{ request('search') }}"
              placeholder="Search PR no., JO no., bus, or item..."
            >
          </div>

          <div class="filter-group">
            <select
              name="status"
              id="warehouseStatusFilter"
              class="warehouse-status-select"
              onchange="this.form.requestSubmit()"
            >
              <option value="All Statuses" @selected(request('status', 'All Statuses') === 'All Statuses')>All Statuses</option>
              @foreach(($statuses ?? []) as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
              @endforeach
            </select>
          </div>
        </form>

        <div class="table-wrap">
          <table class="inventory-table warehouse-part-table">
            <thead>
              <tr>
                <th>PR #</th>
                <th>Item</th>
                <th class="qty-col">Quantity</th>
                <th class="qty-col">On Hand</th>
                <th class="status-col">Inventory</th>
                <th class="status-col">Purchase Status</th>
                <th>Date</th>
                <th class="actions-col">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($purchaseRequests as $partRequest)
                @php
                  $status = $partRequest->purchase_progress_status ?? $partRequest->status ?? 'Approved';
                  $itemName = $partRequest->first_item_display ?? '—';
                  $quantity = $partRequest->first_quantity_display ?? '0';
                  $onHand = $partRequest->first_on_hand_display ?? '0';
                  $inventoryStatus = $partRequest->first_inventory_status ?? $partRequest->inventory_label ?? 'Not Available';
                  $onHandClass = $inventoryStatus === 'Available' ? 'enough' : 'low';
                  $missingPrAlreadyCreated = $partRequest->missing_pr_already_created ?? false;
                  $canSendToPurchase = ($partRequest->needs_purchase ?? false)
                    && !$missingPrAlreadyCreated
                    && $status === 'Approved';
                  $canIssue = ($partRequest->can_issue ?? false)
                    && $inventoryStatus === 'Available'
                    && in_array($status, ['Approved', 'Delivered', 'Picked Up'], true);
                @endphp

                <tr>
                  <td><strong>{{ $partRequest->pr_no ?? '—' }}</strong></td>
                  <td class="item-col">{{ $itemName }}</td>
                  <td class="qty-col">{{ $quantity }}</td>
                  <td class="qty-col"><span class="on-hand-pill {{ $onHandClass }}">{{ $onHand }}</span></td>
                  <td class="status-col"><x-ui.status-badge :status="$inventoryStatus" type="inventory" /></td>
                  <td class="status-col"><x-ui.status-badge :status="$status" type="purchase" /></td>
                  <td>{{ $partRequest->created_at?->format('M d, Y') ?? '—' }}</td>
                  <td class="actions-col">
                    <div class="actions warehouse-actions">
                      <button
                        type="button"
                        class="view-btn open-view-pr-modal"
                        title="View Details"
                        data-pr-no="{{ $partRequest->pr_no }}"
                        data-job-order-no="{{ $partRequest->job_order_no }}"
                        data-bus-no="{{ $partRequest->bus_no }}"
                        data-item="{{ $partRequest->item }}"
                        data-quantity="{{ $quantity }}"
                        data-on-hand="{{ $onHand }}"
                        data-inventory-status="{{ $inventoryStatus }}"
                        data-status="{{ $status }}"
                        data-remarks="{{ $partRequest->remarks ?? 'No remarks' }}"
                        data-created="{{ $partRequest->created_at?->format('M d, Y') ?? '—' }}"
                        data-parts='@json($partRequest->parts_breakdown ?? [])'
                      >
                        <i class="fa-solid fa-eye"></i>
                      </button>

                      @if($canSendToPurchase)
                        <form
                          action="{{ route('part-requests.send-to-purchase', $partRequest->id) }}"
                          method="POST"
                          class="inline-action-form"
                          data-confirm-form
                          data-confirm-title="Send Missing Parts to Purchase?"
                          data-confirm-message="Are you sure you want to send the missing parts for {{ $partRequest->pr_no }} to Purchase?"
                          data-confirm-button="Yes, Send to Purchase"
                          data-confirm-type="warning"
                        >
                          @csrf
                          <button type="submit" class="send-purchase-btn icon-only-btn" title="Send Missing Parts to Purchase">
                            <i class="fa-solid fa-cart-shopping"></i>
                          </button>
                        </form>
                      @else
                        <button type="button" class="send-purchase-btn icon-only-btn disabled-action-btn" title="No purchase action available" disabled>
                          <i class="fa-solid fa-cart-shopping"></i>
                        </button>
                      @endif

                      @if($canIssue)
                        <form
                          action="{{ route('part-requests.issue', $partRequest->id) }}"
                          method="POST"
                          class="inline-action-form"
                          data-confirm-form
                          data-confirm-title="Issue Parts?"
                          data-confirm-message="Are you sure you want to release these parts from inventory?"
                          data-confirm-button="Yes, Issue Parts"
                          data-confirm-type="issue"
                        >
                          @csrf
                          <button type="submit" class="issue-part-btn icon-only-btn" title="Issue Parts">
                            <i class="fa-solid fa-box-open"></i>
                          </button>
                        </form>
                      @else
                        <button type="button" class="issue-part-btn icon-only-btn disabled-action-btn" title="Parts are not ready to issue" disabled>
                          <i class="fa-solid fa-box-open"></i>
                        </button>
                      @endif
                    </div>
                  </td>
                </tr>
              @empty
                <x-ui.empty-row colspan="8" message="No active part requests found." />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$purchaseRequests" />
      </section>
    </main>
  </div>

  <div id="viewPrModal" class="modal-overlay warehouse-view-overlay">
    <div class="warehouse-edit-style-modal">
      <div class="warehouse-edit-header">
        <div>
          <h2>Purchase Request Details</h2>
          <h3>PR Information</h3>
          <p>This is a read-only view of the selected purchase request.</p>
        </div>
        <button type="button" id="closeViewPrModal" class="warehouse-edit-close">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <div class="warehouse-edit-form-grid">
        <div class="warehouse-field">
          <label>PR No.</label>
          <input id="view_pr_no" type="text" value="—" readonly>
        </div>
        <div class="warehouse-field">
          <label>JO No.</label>
          <input id="view_job_order_no" type="text" value="—" readonly>
        </div>
        <div class="warehouse-field">
          <label>Bus #</label>
          <input id="view_bus_no" type="text" value="—" readonly>
        </div>
        <div class="warehouse-field">
          <label>Date Created</label>
          <input id="view_created" type="text" value="—" readonly>
        </div>
        <div class="warehouse-field full">
          <label>Requested Parts Breakdown</label>
          <div id="view_parts_breakdown" class="parts-breakdown-box">
            <div class="parts-breakdown-empty">No parts found.</div>
          </div>
        </div>
        <div class="warehouse-field full">
          <label>Remarks</label>
          <input id="view_remarks" type="text" value="No remarks" readonly>
        </div>
      </div>

      <div class="warehouse-edit-footer">
        <button type="button" id="closeViewPrModalBottom" class="warehouse-cancel-btn">Close</button>
      </div>
    </div>
  </div>
</x-layout.app>
