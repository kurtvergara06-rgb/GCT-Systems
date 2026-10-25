<x-layout.app
  title="FROMS - Warehouse Part Requests"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Main-styles/form-components.css',
    'resources/css/Warehouse/part-requests.css',
    'resources/css/Warehouse/part-requests-cleanup.css',
    'resources/js/Warehouse/part-requests.js',
    'resources/js/Warehouse/part-requests-cleanup.js'
  ]"
>
  @php
    $canEditWarehouse = auth()->user()?->hasSystemPermission('warehouse', 'edit') ?? false;
    $currentView = $currentView ?? 'pr';
    $isJo = $currentView === 'jo';
    $isHistory = $currentView === 'history';
  @endphp
  <div class="app">
    <x-layout.sidebar department="Warehouse" />

    <main class="main warehouse-part-main">
      <x-layout.topbar
        title="Part Requests"
        subtitle="Review stock availability, prepare releases, and issue parts for maintenance"
        notification-count="6"
      />

      <section data-ajax-region="summary" class="stats-grid inventory-stats">
        <x-ui.summary-card label="Approved Requests" value="{{ $approved ?? 0 }}" small="Ready for Warehouse processing" icon="fa-clipboard-check" color="yellow" />
        <x-ui.summary-card label="For Purchase" value="{{ $forPurchase ?? 0 }}" small="Parts unavailable in stock" icon="fa-cart-shopping" color="blue" />
        <x-ui.summary-card label="Delivered" value="{{ $delivered ?? 0 }}" small="Supplier delivered" icon="fa-truck-ramp-box" color="green" />
        <x-ui.summary-card label="Issued" value="{{ $issued ?? 0 }}" small="Released to maintenance" icon="fa-box-open" color="gray" />
      </section>

      <section data-ajax-region="records" class="table-card inventory-card warehouse-part-card">
        <div class="section-header warehouse-record-header">
          <div>
            <span class="dashboard-eyebrow">REQUISITION MANAGEMENT</span>
            <h2>{{ $isHistory ? 'Part Request History' : ($isJo ? 'Job Order Requests' : 'Purchase Requisitions') }}</h2>
            <p>Review stock availability, authorize releases, and track Warehouse processing.</p>
          </div>
        <div class="warehouse-request-count">
          <span class="toolbar-count-chip"><i class="fa-solid fa-list-check"></i>{{ $isJo ? $jobOrderRequests->total() : $purchaseRequests->total() }} {{ $isJo ? \Illuminate\Support\Str::plural('Job Order', $jobOrderRequests->total()) : \Illuminate\Support\Str::plural('Requisition', $purchaseRequests->total()) }}</span>
        </div>


        </div>

        @if($isHistory)
          <p class="warehouse-history-note">Completed and closed Warehouse requisitions are kept here for reference and audit history.</p>
        @endif

        <div class="warehouse-list-controls">

          <nav class="warehouse-record-tabs" aria-label="Warehouse request type" role="tablist">
            <a href="{{ route('part-requests', ['view' => 'jo']) }}" class="warehouse-record-tab {{ $isJo ? 'is-active' : '' }}" role="tab" aria-selected="{{ $isJo ? 'true' : 'false' }}" data-allow-partial-navigation="true"><i class="fa-solid fa-wrench"></i> JO Requests</a>
            <a href="{{ route('part-requests', ['view' => 'pr']) }}" class="warehouse-record-tab {{ !$isJo && !$isHistory ? 'is-active' : '' }}" role="tab" aria-selected="{{ !$isJo && !$isHistory ? 'true' : 'false' }}" data-allow-partial-navigation="true"><i class="fa-solid fa-file-lines"></i> PR Requests</a>
            <a href="{{ route('part-requests', ['view' => 'history']) }}" class="warehouse-record-tab {{ $isHistory ? 'is-active' : '' }}" role="tab" aria-selected="{{ $isHistory ? 'true' : 'false' }}" data-allow-partial-navigation="true"><i class="fa-solid fa-clock-rotate-left"></i> History</a>
          </nav>
        <div class="gct-table-toolbar-row">

          @if($isJo)
            <form action="{{ route('part-requests') }}" method="GET" class="warehouse-jo-search">
              <input type="hidden" name="view" value="jo">
              <input type="search" name="search" value="{{ request('search') }}" placeholder="Search JO, bus, or part..." aria-label="Search JO requests">
              <button type="submit" class="warehouse-record-tab"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
            </form>
          @else
          <x-ui.table-toolbar action="{{ route('part-requests') }}" data-server-filter="true" search-label="Search Part Requests" search-placeholder="Search PR no., JO no., bus, or item..." :filter-count="(int) (request()->filled('status') && request('status') !== 'All Statuses')" filter-title="Filter Part Requests" :sort-options="['pr_no' => 'PR Number', 'created_at' => 'Request Date', 'status' => 'Status']" default-sort="created_at" default-direction="desc">
            <x-slot:hidden><input type="hidden" name="view" value="{{ $currentView }}"></x-slot:hidden>
            <x-slot:filters><x-ui.table-toolbar-field label="Status" name="status" default="All Statuses"><option value="All Statuses">All Statuses</option>@foreach(($statusOptions ?? []) as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</x-ui.table-toolbar-field></x-slot:filters>
          </x-ui.table-toolbar>
          @endif
        </div>
        </div>


        <div class="table-wrap">
          <table class="inventory-table warehouse-part-table warehouse-part-table-clean">
            <thead>
              <tr>
                <th>{{ $isJo ? 'JO ID' : 'PR ID' }}</th>
                <th>Item</th>
                <th class="qty-col">Qty</th>
                <th class="qty-col">On Hand</th>
                <th class="status-col">Inventory</th>
                <th class="status-col">Purchase</th>
                <th class="status-col">Warehouse</th>
                <th class="actions-col">Actions</th>
              </tr>
            </thead>
            @if($isJo)
            <tbody>
              @forelse($jobOrderRequests as $job)
                <tr>
                  <td>
                    <div class="pr-ref-cell"><strong class="pr-main-num">{{ $job->job_order_no }}</strong>
                    <div class="pr-sub-tags"><span class="pr-meta-tag bus-tag"><i class="fa-solid fa-bus"></i> {{ $job->bus_no }}</span></div></div>
                  </td>
                  <td class="item-col"><strong class="item-name-text">{{ collect($job->warehouse_available_parts)->pluck('name')->implode(', ') }}</strong></td>
                  <td class="qty-col">{{ collect($job->warehouse_available_parts)->sum('quantity') }}</td>
                  <td class="qty-col">{{ collect($job->warehouse_available_parts)->sum(fn ($part) => min((int) $part['available'], (int) $part['quantity'])) }}</td>
                  <td class="status-col"><x-ui.status-badge status="Available" type="inventory" /></td>
                  <td class="status-col">—</td>
                  <td class="status-col"><x-ui.status-badge status="Pending Issuance" /></td>
                  <td class="actions-col"><span title="JO-only issuance is not yet enabled. No inventory is deducted." class="pr-meta-tag">Pending setup</span></td>
                </tr>
              @empty
                <x-ui.empty-row colspan="8" icon="fa-wrench" message="No JO parts requests with available stock" description="Approved open Job Orders with available stock and no existing PR appear here." />
              @endforelse
            </tbody>
            @else
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
                  $canSendToPurchase = ($partRequest->needs_purchase ?? false) && $canEditWarehouse
                    && !$missingPrAlreadyCreated
                    && $status === 'Approved';
                  $canIssue = ($partRequest->can_issue ?? false) && $canEditWarehouse
                    && $inventoryStatus === 'Available';
                  $warehouseStatus = $isHistory
                    ? ($partRequest->warehouse_status ?: $partRequest->status)
                    : ($partRequest->warehouse_workflow_status ?? 'Ready to Issue');
                  $warehouseStatusLabel = $warehouseStatus;
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
                    <x-ui.status-badge :status="$warehouseStatusLabel" class="warehouse-status-pill" title="{{ $warehouseStatus }}" />
                  </td>
                  <td class="actions-col">
                    <div class="actions warehouse-actions">
                      <button
                        type="button"
                        class="action-btn view view-btn open-view-pr-modal"
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
                <x-ui.empty-row
                  colspan="8"
                  icon="fa-clipboard-list"
                  :message="$isHistory ? 'No Part Request History Found' : 'No Active Part Requests Found'"
                  :description="$isHistory ? 'Issued and fulfilled part requests will appear here.' : 'Maintenance part requisitions awaiting fulfillment will appear here.'"
                />
              @endforelse
            </tbody>
            @endif
          </table>
        </div>

        <x-ui.table-footer :items="$isJo ? $jobOrderRequests : $purchaseRequests" data-lazy-pagination="true" />
      </section>
    </main>
  </div>

  <div id="viewPrModal" class="modal-overlay warehouse-view-overlay" aria-hidden="true">
    <div class="warehouse-edit-style-modal warehouse-request-details-modal" role="dialog" aria-modal="true" aria-labelledby="warehouseRequestDetailsTitle">
      <div class="warehouse-edit-header">
        <span class="warehouse-modal-title-icon" aria-hidden="true"><i class="fa-solid fa-file-circle-check"></i></span>
        <div class="warehouse-modal-title-copy">
          <h2 id="warehouseRequestDetailsTitle">Purchase Request Details</h2>
          <p>Warehouse requisition, stock availability, and issuance information</p>
        </div>
        <button type="button" id="closeViewPrModal" class="warehouse-edit-close" aria-label="Close Purchase Request Details">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <div class="warehouse-modal-scrollable">
        <div class="warehouse-request-summary" aria-label="Purchase Request summary">
          <div class="warehouse-summary-item">
            <span>PR ID</span>
            <strong id="view_pr_no">—</strong>
          </div>
          <div class="warehouse-summary-item">
            <span>Purchase Status</span>
            <strong id="view_purchase_status">—</strong>
          </div>
          <div class="warehouse-summary-item">
            <span>Warehouse Status</span>
            <strong id="view_warehouse_status">—</strong>
          </div>
          <div class="warehouse-summary-item">
            <span>Date Created</span>
            <strong id="view_created">—</strong>
          </div>
        </div>

        <section class="warehouse-modal-section">
          <header class="warehouse-modal-section-header">
            <span class="warehouse-modal-section-icon"><i class="fa-solid fa-clipboard-list"></i></span>
            <div>
              <h3>01 — Request Information</h3>
              <p>Linked maintenance and vehicle references</p>
            </div>
          </header>
          <div class="warehouse-modal-section-body warehouse-request-meta-grid">
            <div class="warehouse-readonly-field">
              <span>Job Order ID</span>
              <strong id="view_job_order_no">—</strong>
            </div>
            <div class="warehouse-readonly-field">
              <span>Bus ID</span>
              <strong id="view_bus_no">—</strong>
            </div>
          </div>
        </section>

        <section class="warehouse-modal-section">
          <header class="warehouse-modal-section-header">
            <span class="warehouse-modal-section-icon"><i class="fa-solid fa-boxes-packing"></i></span>
            <div>
              <h3>02 — Requested Parts</h3>
              <p>Requested quantities compared with Warehouse stock</p>
            </div>
          </header>
          <div class="warehouse-modal-section-body">
            <div id="view_parts_breakdown" class="parts-breakdown-box">
              <div class="parts-breakdown-empty">No parts found.</div>
            </div>
          </div>
        </section>

        <section class="warehouse-modal-section">
          <header class="warehouse-modal-section-header">
            <span class="warehouse-modal-section-icon"><i class="fa-solid fa-box-open"></i></span>
            <div>
              <h3>03 — Issuance Record</h3>
              <p>Actual quantities physically released by Warehouse</p>
            </div>
          </header>
          <div class="warehouse-modal-section-body">
            <div id="view_issue_quantities" class="warehouse-issued-quantities">
              <div class="parts-breakdown-empty">No parts have been issued yet.</div>
            </div>
          </div>
        </section>

        <section class="warehouse-modal-section">
          <header class="warehouse-modal-section-header">
            <span class="warehouse-modal-section-icon"><i class="fa-solid fa-note-sticky"></i></span>
            <div>
              <h3>04 — Remarks</h3>
              <p>Request notes and processing context</p>
            </div>
          </header>
          <div class="warehouse-modal-section-body">
            <div class="warehouse-readonly-note" id="view_remarks">No remarks</div>
          </div>
        </section>
      </div>

      <div class="warehouse-edit-footer">
        <button type="button" id="closeViewPrModalBottom" class="warehouse-cancel-btn">Close</button>
      </div>
    </div>
  </div>

  <div id="issuePartsModal" class="modal-overlay warehouse-view-overlay" aria-hidden="true">
    <form id="issuePartsForm" method="POST" class="warehouse-edit-style-modal warehouse-issue-modal" role="dialog" aria-modal="true" aria-labelledby="warehouseIssuePartsTitle">
      @csrf
      <div class="warehouse-edit-header">
        <span class="warehouse-modal-title-icon" aria-hidden="true"><i class="fa-solid fa-box-open"></i></span>
        <div class="warehouse-modal-title-copy">
          <h2 id="warehouseIssuePartsTitle">Issue Parts</h2>
          <p><strong id="issue_pr_no">Purchase Request</strong> · Confirm the approved parts being released from Warehouse inventory.</p>
        </div>
        <button type="button" id="closeIssuePartsModal" class="warehouse-edit-close" aria-label="Close Issue Parts">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <div class="warehouse-modal-scrollable warehouse-issue-scrollable">
        <section class="warehouse-modal-section">
          <header class="warehouse-modal-section-header">
            <span class="warehouse-modal-section-icon"><i class="fa-solid fa-boxes-packing"></i></span>
            <div>
              <h3>Parts for Release</h3>
              <p>Review requested and available quantities before recording handover</p>
            </div>
          </header>
          <div class="warehouse-modal-section-body">
            <div id="issue_parts_fields" class="issue-parts-fields"></div>
          </div>
        </section>
      </div>

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
