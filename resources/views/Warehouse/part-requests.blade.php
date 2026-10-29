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
        <x-ui.summary-card label="Authorized Requests" value="{{ $approved ?? 0 }}" small="Approved Job Orders ready for Warehouse" icon="fa-clipboard-check" color="yellow" />
        <x-ui.summary-card label="For Purchase" value="{{ $forPurchase ?? 0 }}" small="Parts unavailable in stock" icon="fa-cart-shopping" color="blue" />
        <x-ui.summary-card label="Delivered" value="{{ $delivered ?? 0 }}" small="Supplier delivered" icon="fa-truck-ramp-box" color="green" />
        <x-ui.summary-card label="Issued" value="{{ $issued ?? 0 }}" small="Released to maintenance" icon="fa-box-open" color="gray" />
      </section>

      <section data-ajax-region="records" class="table-card inventory-card warehouse-part-card">
        <div class="section-header warehouse-record-header">
          <div>
            <h2>{{ $isJo ? 'Job Order Requests' : 'Part Request Records' }}</h2>
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
            <a href="{{ route('part-requests', ['view' => 'pr']) }}" class="warehouse-record-tab {{ !$isJo && !$isHistory ? 'is-active' : '' }}" role="tab" aria-selected="{{ !$isJo && !$isHistory ? 'true' : 'false' }}" data-allow-partial-navigation="true"><i class="fa-solid fa-file-lines"></i> PR Requests</a>
            <a href="{{ route('part-requests', ['view' => 'history']) }}" class="warehouse-record-tab {{ $isHistory ? 'is-active' : '' }}" role="tab" aria-selected="{{ $isHistory ? 'true' : 'false' }}" data-allow-partial-navigation="true"><i class="fa-solid fa-clock-rotate-left"></i> History</a>
          </nav>
        <div class="gct-table-toolbar-row">

          @if($isJo)
          <x-ui.table-toolbar action="{{ route('part-requests') }}" data-server-filter="true"
            search-label="Search JO Requests" search-placeholder="Search JO no., bus, or part..."
            :filter-count="(int) (request()->filled('maintenance_type') && request('maintenance_type') !== 'All Types')"
            filter-title="Filter JO Requests"
            :sort-options="['created_at' => 'Created Date', 'job_order_no' => 'JO Number', 'bus_no' => 'Bus Number']"
            default-sort="created_at" default-direction="desc">
            <x-slot:hidden><input type="hidden" name="view" value="jo"></x-slot:hidden>
            <x-slot:filters>
              <x-ui.table-toolbar-field label="Maintenance Type" name="maintenance_type" default="All Types">
                <option value="All Types">All Types</option>
                @foreach(($joMaintenanceTypes ?? []) as $type)
                  <option value="{{ $type }}" @selected(request('maintenance_type') === $type)>{{ $type }}</option>
                @endforeach
              </x-ui.table-toolbar-field>
            </x-slot:filters>
          </x-ui.table-toolbar>
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
                @if($isJo)
                  <th class="status-col">Availability</th>
                @else
                  <th class="qty-col">Qty</th>
                  <th class="qty-col">On Hand</th>
                  <th class="status-col">Inventory</th>
                  <th class="status-col">Status</th>
                @endif
                <th class="actions-col">Actions</th>
              </tr>
            </thead>
            @if($isJo)
            <tbody>
              @foreach($jobOrderRequests as $job)
                <tr>
                  <td>
                    <div class="pr-ref-cell"><strong class="pr-main-num">{{ $job->job_order_no }}</strong>
                    <div class="pr-sub-tags"><span class="pr-meta-tag bus-tag"><i class="fa-solid fa-bus"></i> {{ $job->bus_no }}</span></div></div>
                  </td>
                  <td class="item-col">
                    @php($joVisibleParts = collect($job->warehouse_available_parts)->values())
                    <div class="warehouse-jo-parts-summary">
                      <strong class="item-name-text">{{ $joVisibleParts->first()['name'] ?? '—' }}</strong>
                      @if($joVisibleParts->count() > 1)
                        <button type="button" class="warehouse-jo-parts-more" aria-expanded="false" aria-haspopup="dialog"
                          aria-label="Show {{ $joVisibleParts->count() - 1 }} additional parts for {{ $job->job_order_no }}">
                          +{{ $joVisibleParts->count() - 1 }} more <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                        </button>
                        <div class="warehouse-jo-parts-template" hidden>
                          <div class="warehouse-jo-parts-heading">Other requested parts</div>
                          @foreach($joVisibleParts->skip(1) as $part)
                            <div class="warehouse-jo-parts-row">
                              <span>{{ $part['name'] }}</span>
                              <strong>{{ $part['quantity'] }} {{ $part['unit'] }}</strong>
                            </div>
                          @endforeach
                        </div>
                      @endif
                    </div>
                  </td>
                  <td class="status-col">
                    <x-ui.status-badge
                      :status="$job->warehouse_stock_status"
                      class="warehouse-jo-availability warehouse-jo-availability-{{ strtolower($job->warehouse_stock_status) }}"
                    />
                  </td>
                  <td class="actions-col">
                    @if($job->warehouse_can_issue && $canEditWarehouse)
                      <form action="{{ route('part-requests.job-order.issue', $job) }}" method="POST" class="inline-action-form"
                        data-confirm-form data-confirm-title="Issue JO Parts?"
                        data-confirm-message="Confirm the parts were physically handed to the mechanic for {{ $job->job_order_no }}. This deducts inventory."
                        data-confirm-button="Issue Parts" data-confirm-type="warning">
                        @csrf
                        <button type="submit" class="issue-part-btn icon-only-btn" title="Record physical parts handover"><i class="fa-solid fa-box-open"></i></button>
                      </form>
                    @else
                      @if($job->warehouse_has_linked_pr)
                        <a href="{{ route('part-requests', ['view' => 'pr', 'search' => $job->job_order_no]) }}" class="warehouse-jo-linked-pr" title="Open linked requisition in PR Requests">
                          <i class="fa-solid fa-eye" aria-hidden="true"></i> View PR
                        </a>
                      @else
                        <span class="pr-meta-tag" title="Complete stock and assigned mechanic required before issuance">Pending</span>
                      @endif
                    @endif
                  </td>
                </tr>
              @endforeach
              @if($jobOrderRequests->isEmpty())
                <x-ui.empty-row colspan="4" icon="fa-wrench" message="No approved JO parts requests found" description="Approved open Job Orders with required parts appear here." />
              @endif
            </tbody>
            @endif
            @if(!$isJo)
              @include('Warehouse.part-request-table-body', ['purchaseRequests' => $purchaseRequests, 'isHistory' => $isHistory, 'canEditWarehouse' => $canEditWarehouse])
            @endif
          </table>
        </div>

        <x-ui.table-footer :items="$isJo ? $jobOrderRequests : $purchaseRequests" data-lazy-pagination="true" />

        @if($isHistory && $jobOrderIssuances->isNotEmpty())
          <div class="warehouse-jo-history" style="margin-top:18px">
            <h3>JO Parts Issuance History</h3>
            <p>Recorded Warehouse handovers for approved Job Orders without requisitions.</p>
            <div class="table-wrap">
              <table class="inventory-table warehouse-part-table warehouse-part-table-clean">
                <thead><tr><th>JO ID</th><th>Issue Reference</th><th>Parts Issued</th><th>Issued By</th><th>Date Issued</th></tr></thead>
                <tbody>
                  @foreach($jobOrderIssuances as $issuance)
                    <tr>
                      <td><strong class="pr-main-num">{{ $issuance->reference_no }}</strong></td>
                      <td>{{ $issuance->issue_no }}</td>
                      <td>{{ $issuance->items->map(fn ($item) => $item->item_name.' ('.$item->quantity.' '.$item->unit.')')->implode(', ') }}</td>
                      <td>{{ $issuance->issuer?->name ?? '—' }}</td>
                      <td>{{ $issuance->issued_at?->format('M d, Y h:i A') ?? '—' }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        @endif

      </section>
    </main>
  </div>

  <div id="viewPrModal" class="modal-overlay warehouse-view-overlay" aria-hidden="true">
    <div class="warehouse-edit-style-modal warehouse-request-details-modal warehouse-pr-reference-view" role="dialog" aria-modal="true" aria-labelledby="warehouseRequestDetailsTitle">
      <div class="warehouse-edit-header">
        <span class="warehouse-modal-title-icon" aria-hidden="true"><i class="fa-solid fa-file-lines"></i></span>
        <div class="warehouse-modal-title-copy">
          <h2 id="warehouseRequestDetailsTitle">Purchase Request Details</h2>
          <p>Review requested shortages, stock reference, and procurement details.</p>
        </div>
        <span id="view_warehouse_status_badge" class="warehouse-pr-view-status">—</span>
        <button type="button" id="closeViewPrModal" class="warehouse-edit-close" aria-label="Close Purchase Request Details"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="warehouse-modal-scrollable">
        <div class="warehouse-request-summary warehouse-pr-reference-strip" aria-label="Purchase Request summary">
          <div class="warehouse-summary-item"><span>PR ID</span><strong id="view_pr_no">—</strong></div>
          <div class="warehouse-summary-item"><span>Linked JO</span><strong id="view_job_order_no">—</strong></div>
          <div class="warehouse-summary-item"><span>Requested By</span><strong id="view_requested_by">—</strong></div>
          <div class="warehouse-summary-item"><span>Date Requested</span><strong id="view_created">—</strong></div>
          <div class="warehouse-summary-item"><span>Department</span><strong>Maintenance</strong></div>
          <div class="warehouse-summary-item"><span>Total Parts</span><strong id="view_pr_parts_count">—</strong></div>
        </div>
        <div class="warehouse-pr-reference-secondary">Bus: <strong id="view_bus_no">—</strong><span aria-hidden="true"> | </span> Processing: <strong id="view_processing">—</strong></div>
        <section class="warehouse-modal-section warehouse-pr-reference-section">
          <header class="warehouse-modal-section-header">
            <span class="warehouse-modal-section-icon"><i class="fa-solid fa-cube"></i></span>
            <div><h3>Purchase Request Parts</h3><p>Missing parts requiring procurement, with Warehouse stock reference.</p></div>
          </header>
          <div class="warehouse-modal-section-body">
            <div id="view_parts_breakdown" class="parts-breakdown-box"><div class="parts-breakdown-empty">No parts found.</div></div>
            <p class="warehouse-pr-reference-info"><i class="fa-solid fa-circle-info"></i> Warehouse stock shown for reference only. PR quantities represent unavailable shortages to be procured.</p>
          </div>
        </section>
        <section class="warehouse-modal-section warehouse-pr-reference-section">
          <header class="warehouse-modal-section-header">
            <span class="warehouse-modal-section-icon"><i class="fa-solid fa-warehouse"></i></span>
            <div><h3>Warehouse Stock Reference</h3><p>Physical stock currently visible to Warehouse while reviewing this PR.</p></div>
          </header>
          <div class="warehouse-modal-section-body">
            <div id="view_stock_reference" class="parts-breakdown-box"><div class="parts-breakdown-empty">No stock reference found.</div></div>
          </div>
        </section>
        <section class="warehouse-modal-section warehouse-pr-reference-section">
          <header class="warehouse-modal-section-header">
            <span class="warehouse-modal-section-icon"><i class="fa-solid fa-comment-dots"></i></span>
            <div><h3>Remarks</h3></div>
          </header>
          <div class="warehouse-modal-section-body"><div class="warehouse-readonly-note" id="view_remarks">No remarks provided.</div></div>
        </section>
      </div>
      <div class="warehouse-edit-footer"><button type="button" id="closeViewPrModalBottom" class="warehouse-cancel-btn">Close</button></div>
    </div>
  </div>

  <div id="warehouseSendMissingModal" class="modal-overlay warehouse-view-overlay" aria-hidden="true">
    <div class="warehouse-edit-style-modal warehouse-request-details-modal warehouse-pr-reference-view warehouse-send-missing-view" role="dialog" aria-modal="true" aria-labelledby="warehouseSendMissingTitle">
      <div class="warehouse-edit-header">
        <span class="warehouse-modal-title-icon" aria-hidden="true"><i class="fa-solid fa-cart-shopping"></i></span>
        <div class="warehouse-modal-title-copy">
          <h2 id="warehouseSendMissingTitle">Send Missing Parts to Purchase</h2>
          <p>Review the missing parts before submitting the purchase request.</p>
        </div>
        <button type="button" class="warehouse-edit-close" data-send-missing-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="warehouse-modal-scrollable">
        <div class="warehouse-request-summary warehouse-pr-reference-strip warehouse-send-missing-meta">
          <div class="warehouse-summary-item"><span>PR ID</span><strong id="sendMissingPr">—</strong></div>
          <div class="warehouse-summary-item"><span>Linked JO</span><strong id="sendMissingJo">—</strong></div>
          <div class="warehouse-summary-item"><span>Bus</span><strong id="sendMissingBus">—</strong></div>
        </div>
        <section class="warehouse-modal-section warehouse-pr-reference-section">
          <header class="warehouse-modal-section-header">
            <span class="warehouse-modal-section-icon"><i class="fa-solid fa-boxes-stacked"></i></span>
            <div><h3>Parts Requiring Purchase</h3><p>Preview of the requested shortages, verified again by the server on submission.</p></div>
          </header>
          <div class="warehouse-modal-section-body">
            <div class="warehouse-pr-view-table-wrap">
              <table class="warehouse-pr-view-table"><thead><tr><th>PART NAME</th><th>PR QTY</th><th>PHYSICAL ON HAND</th></tr></thead>
                <tbody id="sendMissingPartsRows"><tr><td colspan="3">No parts selected.</td></tr></tbody>
              </table>
            </div>
            <p class="warehouse-pr-reference-info"><i class="fa-solid fa-circle-info"></i> Only missing quantities will be submitted. Current availability, duplicate requests, and permissions are checked by the existing server workflow.</p>
          </div>
        </section>
      </div>
      <div class="warehouse-edit-footer">
        <button type="button" class="warehouse-cancel-btn" data-send-missing-close>Cancel</button>
        <button type="button" id="sendMissingConfirm" class="confirm-issue-btn warehouse-send-purchase-yellow"><i class="fa-solid fa-cart-shopping"></i> Send to Purchase</button>
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
