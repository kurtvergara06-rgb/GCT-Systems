<x-layout.app
  title="FROMS - Maintenance Requests"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Purchase/purchase-module-ui.css',
    'resources/css/Purchase/Requested_Purchase/maintenance-requests.css',
    'resources/js/Purchase/Requested_Purchase/maintenance-requests.js'
  ]"
>
  <div class="app">
    <x-layout.sidebar department="Purchase" />

    <main class="main purchase-module-page records-page purchase-maintenance-requests-page">
      <x-layout.topbar
        title="Purchase Requisitions"
        subtitle="Purchase Department request inbox from Warehouse"
        notification-count="6"
      />

      <section data-ajax-region="summary" class="stats-grid">
        <x-ui.summary-card
          label="Total Requests"
          value="{{ $totalRequests ?? 0 }}"
          small="Purchase inbox"
          icon="fa-file"
          color="gray"
        />
        <x-ui.summary-card
          label="For Purchase"
          value="{{ $forPurchase ?? 0 }}"
          small="Ready to create PO"
          icon="fa-cart-shopping"
          color="blue"
        />
        <x-ui.summary-card
          label="Ordered"
          value="{{ $ordered ?? 0 }}"
          small="PO already created"
          icon="fa-file-invoice"
          color="yellow"
        />
        <x-ui.summary-card
          label="Delivered / Picked Up"
          value="{{ $delivered ?? 0 }}"
          small="Ready for warehouse issue"
          icon="fa-box"
          color="green"
        />
      </section>

      <section data-ajax-region="records" class="table-card requested-purchase-card records-card">
        <div class="section-header">
          <div>
            <span class="purchase-section-eyebrow">REQUEST INBOX</span>
            <h2>Purchase Requisition Records</h2>
            <p>Only active maintenance purchase requests are shown here. Completed records are available in Purchase History.</p>
          </div>
        </div>

        <div class="requested-pr-list-controls">
        <x-ui.table-toolbar
          action="{{ route('maintenance-requests', [], false) }}"
          class="records-toolbar"
          data-server-filter="true"
          search-label="Search Purchase Requests"
          search-placeholder="Search PR no., JO no., bus, part, or status..."
          :filter-count="(int) (request()->filled('status') && request('status') !== 'All States')"
          filter-title="Filter Purchase Requests"
          :sort-options="['created_at' => 'Submitted At', 'pr_no' => 'PR Number', 'status' => 'Status']"
          default-sort="created_at"
          default-direction="desc"
        >

          <x-slot:filters>
            <x-ui.table-toolbar-field label="Status" name="status" default="All States">
              <option value="All States">All States</option>
              @foreach(($statuses ?? []) as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
              @endforeach
            </x-ui.table-toolbar-field>
          </x-slot:filters>
        </x-ui.table-toolbar>
        </div>

        <div class="table-wrap records-table-wrap">
          <table class="requested-purchase-table records-table">
            <thead>
              <tr>
                <th scope="col">PR Number / JO</th>
                <th scope="col">Requested Parts</th>
                <th scope="col">Total Items</th>
                <th scope="col">Purpose / Bus</th>
                <th scope="col">Target Date</th>
                <th scope="col">PR Status</th>
                <th scope="col">Actions</th>
              </tr>
            </thead>

            <tbody>
              @forelse($purchaseRequests as $purchaseRequest)
                @php
                  $parts = $purchaseRequest->parts_breakdown ?? [];
                  $firstItem = $purchaseRequest->first_item_display ?? $purchaseRequest->item ?? '—';
                  $firstQuantity = $purchaseRequest->first_quantity_display ?? $purchaseRequest->quantity ?? '—';
                @endphp

                <tr>
                  <td>
                    <div class="requested-pr-reference">
                      <strong>{{ $purchaseRequest->display_pr_no }}</strong>
                      <small>JO {{ $purchaseRequest->job_order_no ?? '—' }}</small>
                    </div>
                  </td>
                  <td>
                    <div class="requested-pr-part">
                      <strong>{{ $firstItem }}</strong>
                      <small>{{ $firstQuantity }}</small>
                      @if(count($parts) > 1)
                        <details class="requested-pr-more">
                          <summary>+{{ count($parts) - 1 }} more parts</summary>
                          <div class="requested-pr-more-panel">
                            @foreach(array_slice($parts, 1) as $part)
                              <div>{{ $part['name'] ?? $part['item'] ?? 'Part' }} <span>{{ $part['quantity_display'] ?? ($part['quantity'] ?? '—') }}</span></div>
                            @endforeach
                          </div>
                        </details>
                      @endif
                    </div>
                  </td>
                  <td class="requested-pr-quantity">{{ count($parts) }} {{ count($parts) === 1 ? 'Part' : 'Parts' }}</td>
                  <td class="requested-pr-purpose"><strong>{{ $purchaseRequest->purpose ?: '—' }}</strong><small>Bus {{ $purchaseRequest->bus_no ?: '—' }}</small></td>
                  <td class="requested-pr-target-date">{{ $purchaseRequest->target_date ? \Illuminate\Support\Carbon::parse($purchaseRequest->target_date)->format('M d, Y') : '—' }}</td>
                  <td class="requested-pr-status">
                    <x-ui.status-badge
                      :status="$purchaseRequest->status ?? '—'"
                      type="purchase"
                    />
                  </td>
                  <td class="requested-pr-actions-cell">
                    <div class="record-actions">
                      <button
                        type="button"
                        class="action-btn view open-view-requested-pr-modal"
                        title="Review Purchase Requisition"
                        data-create-po-url="{{ $purchaseRequest->status === 'For Purchase' ? route('maintenance-requests.create-po', $purchaseRequest->id, false) : '' }}"
                        data-pr-no="{{ $purchaseRequest->display_pr_no }}"
                        data-job-order-no="{{ $purchaseRequest->job_order_no }}"
                        data-bus-no="{{ $purchaseRequest->bus_no }}"
                        data-created="{{ $purchaseRequest->created_at ? $purchaseRequest->created_at->format('m/d/y | h:i A') : '—' }}"
                        data-remarks="{{ $purchaseRequest->remarks ?? 'No remarks' }}"
                        data-status="{{ $purchaseRequest->status ?? '—' }}"
                        data-item="{{ $purchaseRequest->item }}"
                        data-quantity="{{ $firstQuantity }}"
                        data-parts='@json($parts)'
                      >
                        <i class="fa-solid fa-clipboard-check"></i><span>Review</span>
                      </button>

                    </div>
                  </td>
                </tr>
              @empty
                <x-ui.empty-row
                  colspan="7"
                  message="No requested purchases found."
                />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$purchaseRequests" />
      </section>
    </main>
  </div>

  <div id="viewRequestedPrModal" class="modal-overlay requested-pr-view-overlay" role="dialog" aria-modal="true" aria-labelledby="requestedPrModalTitle">
    <div class="requested-pr-style-modal">
      <div class="requested-pr-modal-header">
        <div>
          <h2 id="requestedPrModalTitle">Purchase Request Details</h2>
          <h3>Maintenance purchase requisition</h3>
          <p>Review the request, parts, procurement status, and remarks.</p>
        </div>
        <button type="button" id="closeRequestedPrModal" class="requested-pr-close-btn" aria-label="Close purchase request details">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <div class="requested-pr-modal-body">
        <dl class="requested-pr-information-strip">
          <div>
            <dt>PR Number</dt>
            <dd id="viewRequestedPrNo">—</dd>
          </div>
          <div>
            <dt>Linked Job Order</dt>
            <dd id="viewRequestedJoNo">—</dd>
          </div>
          <div>
            <dt>Bus Number</dt>
            <dd id="viewRequestedBusNo">—</dd>
          </div>
        </dl>

        <section class="requested-pr-detail-section">
          <h3>Parts Requiring Purchase</h3>
          <div class="requested-pr-breakdown-box">
            <table class="requested-pr-breakdown-table">
              <thead>
                <tr><th>Part Name</th><th>Quantity</th><th>Unit</th></tr>
              </thead>
              <tbody id="viewRequestedPartsContainer">
                <tr><td>—</td><td>—</td><td>—</td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <details class="requested-pr-additional">
          <summary>Additional Request Information <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
          <dl class="requested-pr-additional-grid">
          <div>
            <dt>Submitted At</dt>
            <dd id="viewRequestedCreated">—</dd>
          </div>
          <div class="requested-pr-status-information">
            <dt>Procurement Status</dt>
            <dd><span id="viewRequestedStatus" class="requested-pr-modal-status">—</span></dd>
          </div>

          </dl>
        <section class="requested-pr-detail-section requested-pr-remarks-section">
          <h3>Remarks</h3>
          <p id="viewRequestedRemarks">No remarks</p>
        </section>
        </details>
      </div>

      <div class="requested-pr-footer">
        <form id="requestedPrCreatePoForm" method="POST" hidden>@csrf<button type="submit" class="requested-pr-create-po-btn"><i class="fa-solid fa-cart-plus"></i> Create PO</button></form>
        <button type="button" id="closeRequestedPrModalBottom" class="requested-pr-close-bottom">
          Close
        </button>
      </div>
    </div>
  </div>
</x-layout.app>
