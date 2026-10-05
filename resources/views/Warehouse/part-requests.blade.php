<x-layout.app
  title="FROMS - Warehouse Part Requests"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Warehouse/part-requests.css',
    'resources/css/Warehouse/part-requests-cleanup.css',
    'resources/js/Warehouse/part-requests.js',
    'resources/js/Warehouse/part-requests-cleanup.js'
  ]"
>
  @php
    $canEditWarehouse = auth()->user()?->hasSystemPermission('warehouse', 'edit') ?? false;
    $canApproveWarehouse = auth()->user()?->hasSystemPermission('warehouse', 'approve') ?? false;
    $currentView = $currentView ?? 'active';
    $isHistory = $currentView === 'history';
  @endphp
  <div class="app">
    <x-layout.sidebar department="Warehouse" />

    <main class="main warehouse-part-main">
      <x-layout.topbar
        title="Part Requests"
        subtitle="Review stock availability, authorize releases, and issue parts for maintenance"
        notification-count="6"
      />

      <section data-ajax-region="summary" class="stats-grid inventory-stats">
        <x-ui.summary-card label="Pending Approval" value="{{ $approved ?? 0 }}" small="Awaiting Warehouse approval" icon="fa-clipboard-check" color="yellow" />
        <x-ui.summary-card label="For Purchase" value="{{ $forPurchase ?? 0 }}" small="Parts unavailable in stock" icon="fa-cart-shopping" color="blue" />
        <x-ui.summary-card label="Delivered" value="{{ $delivered ?? 0 }}" small="Supplier delivered" icon="fa-truck-ramp-box" color="green" />
        <x-ui.summary-card label="Issued" value="{{ $issued ?? 0 }}" small="Released to maintenance" icon="fa-box-open" color="gray" />
      </section>

      <section data-ajax-region="records" class="table-card inventory-card warehouse-part-card">
        <div class="section-header">
          <div>
            <span class="dashboard-eyebrow">REQUISITION MANAGEMENT</span>
            <h2>{{ $isHistory ? 'Part Request History' : 'Active Part Requests' }}</h2>
            <p>{{ $isHistory ? 'Completed and closed Warehouse requisitions are retained as read-only records.' : 'Review stock availability, authorize releases, and track Warehouse processing.' }}</p>
          </div>
        </div>

        <nav class="warehouse-record-tabs" aria-label="Part request record view">
          <a
            href="{{ route('part-requests', ['view' => 'active']) }}"
            class="warehouse-record-tab {{ !$isHistory ? 'active' : '' }}"
            @if(!$isHistory) aria-current="page" @endif
          >
            <i class="fa-solid fa-list-check"></i>
            Active
            <span>{{ $activeCount ?? 0 }}</span>
          </a>
          <a
            href="{{ route('part-requests', ['view' => 'history']) }}"
            class="warehouse-record-tab {{ $isHistory ? 'active' : '' }}"
            @if($isHistory) aria-current="page" @endif
          >
            <i class="fa-solid fa-clock-rotate-left"></i>
            History
            <span>{{ $historyCount ?? 0 }}</span>
          </a>
        </nav>

        <form action="{{ route('part-requests') }}" method="GET" class="toolbar inventory-toolbar warehouse-part-toolbar" data-server-filter="true">
          <input type="hidden" name="view" value="{{ $currentView }}">
          <div class="toolbar-left">
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
                @foreach(($statusOptions ?? []) as $status)
                  <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <div class="toolbar-right">
            <span class="toolbar-count-chip">
              <i class="fa-solid fa-list-check"></i>
              {{ $purchaseRequests->total() }} {{ \Illuminate\Support\Str::plural('Requisition', $purchaseRequests->total()) }}
            </span>
          </div>
        </form>

        <div class="table-wrap">
          <table class="inventory-table warehouse-part-table warehouse-part-table-clean">
            <thead>
              <tr>
                <th>PR #</th>
                <th>Item</th>
                <th class="qty-col">Qty</th>
                <th class="qty-col">On Hand</th>
                <th class="status-col">Inventory</th>
                <th class="status-col">Purchase</th>
                <th class="status-col">Warehouse</th>
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
                  $canSendToPurchase = ($partRequest->needs_purchase ?? false) && $canApproveWarehouse
                    && !$missingPrAlreadyCreated
                    && $status === 'Approved';
                  $canIssue = ($partRequest->can_issue ?? false) && $canEditWarehouse
                    && $inventoryStatus === 'Available';
                  $canApproveForIssue = ($partRequest->can_approve_for_issue ?? false) && $canApproveWarehouse;
                  $canHold = ($partRequest->can_hold ?? false) && $canApproveWarehouse;
                  $canPrepare = ($partRequest->can_prepare ?? false) && $canEditWarehouse;
                  $warehouseStatus = $isHistory
                    ? ($partRequest->warehouse_status ?: $partRequest->status)
                    : ($partRequest->warehouse_workflow_status ?? 'Pending Warehouse Approval');
                  $warehouseStatusLabel = match ($warehouseStatus) {
                    'Pending Warehouse Approval' => 'Pending Approval',
                    'Approved for Issue' => 'Approved',
                    default => $warehouseStatus,
                  };
                  $warehouseStatusClass = match ($warehouseStatus) {
                    'Pending Warehouse Approval' => 'pending',
                    'Approved for Issue' => 'approved',
                    'Preparing' => 'preparing',
                    'On Hold' => 'hold',
                    'Issued' => 'issued',
                    default => 'neutral',
                  };
                @endphp

                <tr>
                  <td>
                    <div class="pr-ref-cell">
                      <strong class="pr-main-num">{{ $partRequest->pr_no ?? '—' }}</strong>
                      @if($partRequest->job_order_no || $partRequest->bus_no)
                        <div class="pr-sub-tags">
                          @if($partRequest->job_order_no)
                            <span class="pr-meta-tag jo-tag" title="Job Order"><i class="fa-solid fa-wrench"></i> {{ $partRequest->job_order_no }}</span>
                          @endif
                          @if($partRequest->bus_no)
                            <span class="pr-meta-tag bus-tag" title="Bus Number"><i class="fa-solid fa-bus"></i> {{ $partRequest->bus_no }}</span>
                          @endif
                        </div>
                      @endif
                    </div>
                  </td>
                  <td class="item-col" title="{{ $itemName }}">
                    <div class="item-display-cell">
                      <strong class="item-name-text">{{ $itemName }}</strong>
                    </div>
                  </td>
                  <td class="qty-col">
                    <span class="qty-needed-badge">{{ $quantity }}</span>
                  </td>
                  <td class="qty-col">
                    <span class="on-hand-pill {{ $onHandClass }}" title="Quantity on hand in inventory">
                      <i class="fa-solid {{ $onHandClass === 'enough' ? 'fa-circle-check' : 'fa-triangle-exclamation' }}"></i>
                      {{ $onHand }}
                    </span>
                  </td>
                  <td class="status-col"><x-ui.status-badge :status="$inventoryStatus" type="inventory" /></td>
                  <td class="status-col"><x-ui.status-badge :status="$status" type="purchase" /></td>
                  <td class="status-col">
                    <span class="warehouse-status-pill {{ $warehouseStatusClass }}" title="{{ $warehouseStatus }}">
                      {{ $warehouseStatusLabel }}
                    </span>
                  </td>
                  <td class="actions-col">
                    <div class="actions warehouse-actions">
                      <button
                        type="button"
                        class="view-btn open-view-pr-modal"
                        title="View Requisition Details"
                        data-pr-no="{{ $partRequest->pr_no }}"
                        data-job-order-no="{{ $partRequest->job_order_no }}"
                        data-bus-no="{{ $partRequest->bus_no }}"
                        data-item="{{ $partRequest->item }}"
                        data-quantity="{{ $quantity }}"
                        data-on-hand="{{ $onHand }}"
                        data-inventory-status="{{ $inventoryStatus }}"
                        data-status="{{ $status }}"
                        data-warehouse-status="{{ $warehouseStatusLabel }}"
                        data-approved-by="{{ $partRequest->warehouse_approved_by ? 'User #'.$partRequest->warehouse_approved_by : '—' }}"
                        data-approved-at="{{ $partRequest->warehouse_approved_at?->format('M d, Y h:i A') ?? '—' }}"
                        data-prepared-by="{{ $partRequest->warehouse_prepared_by ? 'User #'.$partRequest->warehouse_prepared_by : '—' }}"
                        data-prepared-at="{{ $partRequest->warehouse_prepared_at?->format('M d, Y h:i A') ?? '—' }}"
                        data-issued-quantities='@json($partRequest->warehouse_issue_quantities ?? [])'
                        data-remarks="{{ $partRequest->remarks ?? 'No remarks' }}"
                        data-created="{{ $partRequest->created_at?->format('M d, Y') ?? '—' }}"
                        data-parts='@json($partRequest->parts_breakdown ?? [])'
                      >
                        <i class="fa-solid fa-eye"></i>
                      </button>

                      @unless($isHistory)
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
                      @endif

                      @if($canApproveForIssue)
                        <form
                          action="{{ route('part-requests.approve-for-issue', $partRequest->id) }}"
                          method="POST"
                          class="inline-action-form"
                          data-confirm-form
                          data-confirm-title="Approve Part Issuance?"
                          data-confirm-message="Authorize Warehouse personnel to prepare {{ $partRequest->pr_no }} for release?"
                          data-confirm-button="Yes, Approve"
                          data-confirm-type="approve"
                        >
                          @csrf
                          <button type="submit" class="approve-issue-btn icon-only-btn" title="Approve for Issue">
                            <i class="fa-solid fa-circle-check"></i>
                          </button>
                        </form>
                      @endif

                      @if($canHold)
                        <form action="{{ route('part-requests.hold', $partRequest->id) }}" method="POST" class="inline-action-form" data-confirm-form data-confirm-title="Hold Part Issuance?" data-confirm-message="Place {{ $partRequest->pr_no }} on hold?" data-confirm-button="Yes, Hold" data-confirm-type="warning">
                          @csrf
                          <button type="submit" class="hold-issue-btn icon-only-btn" title="Reject or Hold Release">
                            <i class="fa-solid fa-ban"></i>
                          </button>
                        </form>
                      @endif

                      @if($canPrepare)
                        <form action="{{ route('part-requests.prepare', $partRequest->id) }}" method="POST" class="inline-action-form">
                          @csrf
                          <button type="submit" class="prepare-part-btn icon-only-btn" title="Prepare Parts">
                            <i class="fa-solid fa-box"></i>
                          </button>
                        </form>
                      @endif

                      @if($canIssue)
                        <button
                          type="button"
                          class="issue-part-btn icon-only-btn open-issue-modal"
                          title="Record and Issue Parts"
                          data-action="{{ route('part-requests.issue', $partRequest->id) }}"
                          data-pr-no="{{ $partRequest->pr_no }}"
                          data-parts='@json($partRequest->parts_breakdown ?? [])'
                        >
                          <i class="fa-solid fa-box-open"></i>
                        </button>
                      @endif
                      @endunless
                    </div>
                  </td>
                </tr>
              @empty
                <x-ui.empty-row colspan="8" :message="$isHistory ? 'No part request history found.' : 'No active part requests found.'" />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$purchaseRequests" data-lazy-pagination="true" />
      </section>
    </main>
  </div>

  <div id="viewPrModal" class="modal-overlay warehouse-view-overlay">
    <div class="warehouse-edit-style-modal warehouse-request-details-modal">
      <div class="warehouse-edit-header">
        <div>
          <h2>Purchase Request Details</h2>
          <h3>PR Information</h3>
          <p>Request, approval, preparation, and issuance details.</p>
        </div>
        <button type="button" id="closeViewPrModal" class="warehouse-edit-close">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <div class="warehouse-edit-form-grid warehouse-request-meta-grid">
        <div class="warehouse-field">
          <label>PR No.</label>
          <input id="view_pr_no" type="text" value="—" readonly>
        </div>
        <div class="warehouse-field">
          <label>Date Created</label>
          <input id="view_created" type="text" value="—" readonly>
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
          <label>Purchase Status</label>
          <input id="view_purchase_status" type="text" value="—" readonly>
        </div>
        <div class="warehouse-field">
          <label>Warehouse Status</label>
          <input id="view_warehouse_status" type="text" value="—" readonly>
        </div>
        <div class="warehouse-field">
          <label>Approved By</label>
          <input id="view_approved_by" type="text" value="—" readonly>
        </div>
        <div class="warehouse-field">
          <label>Approved At</label>
          <input id="view_approved_at" type="text" value="—" readonly>
        </div>
        <div class="warehouse-field">
          <label>Prepared By</label>
          <input id="view_prepared_by" type="text" value="—" readonly>
        </div>
        <div class="warehouse-field">
          <label>Prepared At</label>
          <input id="view_prepared_at" type="text" value="—" readonly>
        </div>
        <div class="warehouse-field full">
          <label>Requested Parts Breakdown</label>
          <div id="view_parts_breakdown" class="parts-breakdown-box">
            <div class="parts-breakdown-empty">No parts found.</div>
          </div>
        </div>
        <div class="warehouse-field full">
          <label>Actual Issued Quantities</label>
          <div id="view_issue_quantities" class="warehouse-issued-quantities">
            <div class="parts-breakdown-empty">No parts have been issued yet.</div>
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

  <div id="issuePartsModal" class="modal-overlay warehouse-view-overlay">
    <form id="issuePartsForm" method="POST" class="warehouse-edit-style-modal warehouse-issue-modal">
      @csrf
      <div class="warehouse-edit-header">
        <div>
          <h2>Issue Prepared Parts</h2>
          <h3 id="issue_pr_no">Purchase Request</h3>
          <p>Record the quantities physically released from the warehouse.</p>
        </div>
        <button type="button" id="closeIssuePartsModal" class="warehouse-edit-close" title="Close">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <div id="issue_parts_fields" class="issue-parts-fields"></div>

      <div class="warehouse-edit-footer issue-modal-footer">
        <button type="button" id="cancelIssueParts" class="warehouse-cancel-btn">Cancel</button>
        <button type="submit" class="confirm-issue-btn">
          <i class="fa-solid fa-box-open"></i>
          Confirm Issue
        </button>
      </div>
    </form>
  </div>
</x-layout.app>
