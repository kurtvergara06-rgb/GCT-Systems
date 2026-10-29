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

    <main class="main purchase-maintenance-requests-page purchase-module-page records-page">
      <x-layout.topbar
        title="Requested Purchases"
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
        <div class="section-header purchase-record-header">
          <div>
            <span class="purchase-section-eyebrow">REQUEST INBOX</span>
            <h2>Purchase Requisition Records</h2>
            <p>Purchase Requisitions awaiting procurement. Completed records remain available in Purchase History.</p>
          </div>
          <span class="purchase-section-count">{{ $purchaseRequests->total() }} active</span>
        </div>

        <div class="purchase-list-controls requested-pr-list-controls">
          <x-ui.table-toolbar action="{{ route('maintenance-requests', [], false) }}" class="records-toolbar" data-server-filter="true" search-label="Search Purchase Requisitions" search-placeholder="Search PR no., JO no., bus, item, or status..." :filter-count="(int) (request()->filled('status') && request('status') !== 'All States')" filter-title="Filter Purchase Requisitions" :sort-options="['created_at' => 'Created Date', 'pr_no' => 'PR Number', 'status' => 'Status']" default-sort="created_at" default-direction="desc">
            <x-slot:filters><x-ui.table-toolbar-field label="Status" name="status" default="All States"><option value="All States">All States</option>@foreach(($statuses ?? []) as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</x-ui.table-toolbar-field></x-slot:filters>
          </x-ui.table-toolbar>
        </div>

        <div class="table-wrap records-table-wrap">
          <table class="requested-purchase-table records-table">
            <thead>
              <tr>
                <th scope="col">Request / JO</th>
                <th scope="col">Submitted At</th>
                <th scope="col">Parts for Purchase</th>
                <th scope="col">Qty</th>
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
                      <small>{{ $purchaseRequest->job_order_no ?? '—' }}</small>
                    </div>
                  </td>
                  <td class="requested-pr-date">
                    @if($purchaseRequest->created_at)
                      <div class="requested-pr-date-stack">
                        <strong>{{ $purchaseRequest->created_at->format('M d, Y') }}</strong>
                        <small>{{ $purchaseRequest->created_at->format('h:i A') }}</small>
                        <small class="requested-pr-date-note">Request created date</small>
                      </div>
                    @else
                      <span>—</span>
                    @endif
                  </td>
                  <td>
                    <div class="requested-pr-part requested-pr-part-inline">
                      <strong>{{ $firstItem }}</strong>
                      @if(count($parts) > 1)
                        <details class="requested-pr-more">
                          <summary>+{{ count($parts) - 1 }} more <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                          <div class="requested-pr-more-panel">
                            @foreach($parts as $part)
                              <div class="requested-pr-more-row">
                                <span>{{ $part['name'] ?? '—' }}</span>
                                <strong>{{ $part['quantity_display'] ?? '—' }}</strong>
                              </div>
                            @endforeach
                          </div>
                        </details>
                      @endif
                    </div>
                  </td>
                  <td class="requested-pr-quantity">{{ $firstQuantity }}</td>
                  <td class="requested-pr-status">
                    <x-ui.status-badge
                      :status="$purchaseRequest->status ?? '—'"
                      type="purchase"
                    />
                  </td>
                  <td class="requested-pr-actions-cell">
                    <div class="actions record-actions">
                      <button
                        type="button"
                        class="action-btn view open-view-requested-pr-modal"
                        title="View"
                        data-pr-no="{{ $purchaseRequest->display_pr_no }}"
                        data-job-order-no="{{ $purchaseRequest->job_order_no }}"
                        data-bus-no="{{ $purchaseRequest->bus_no }}"
                        data-created="{{ $purchaseRequest->created_at ? $purchaseRequest->created_at->format('m/d/y | h:i A') : '—' }}"
                        data-status="{{ $purchaseRequest->status }}"
                        data-remarks="{{ $purchaseRequest->remarks ?? 'No remarks' }}"
                        data-item="{{ $purchaseRequest->item }}"
                        data-quantity="{{ $firstQuantity }}"
                        data-parts='@json($parts)'
                      >
                        <i class="fa-solid fa-eye"></i>
                      </button>

                      @if($purchaseRequest->status === 'For Purchase')
                        <form
                          action="{{ route('maintenance-requests.create-po', $purchaseRequest->id, false) }}"
                          method="POST"
                          class="inline-action-form"
                        >
                          @csrf
                          <button
                            type="submit"
                            class="action-btn create-po"
                            title="Create Purchase Order"
                          >
                            <i class="fa-solid fa-cart-plus"></i>
                          </button>
                        </form>
                      @endif
                    </div>
                  </td>
                </tr>
              @empty
                <x-ui.empty-row
                  colspan="6"
                  icon="fa-wrench"
                  message="No Maintenance Requests Found"
                  description="Maintenance requisitions endorsed for purchasing will appear here."
                />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$purchaseRequests" />
      </section>
    </main>
  </div>

  <div id="viewRequestedPrModal" class="modal-overlay requested-pr-view-overlay">
    <div class="requested-pr-style-modal" role="dialog" aria-modal="true" aria-labelledby="viewRequestedPrTitle">
      <div class="requested-pr-modal-header">
        <div>
          <h2 id="viewRequestedPrTitle">Requested Purchase Details</h2>
          <h3>PR Information</h3>
          <p>This is a read-only view of the selected purchase request.</p>
        </div>
        <button type="button" id="closeRequestedPrModal" class="requested-pr-close-btn" aria-label="Close purchase request details">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <div class="requested-pr-modal-body">
        <dl class="requested-pr-information-strip">
          <div><dt>PR No.</dt><dd id="viewRequestedPrNo">—</dd></div>
          <div><dt>JO No.</dt><dd id="viewRequestedJoNo">—</dd></div>
          <div><dt>Bus ID</dt><dd id="viewRequestedBusNo">—</dd></div>
          <div><dt>Created Date</dt><dd id="viewRequestedCreated">—</dd></div>
          <div class="requested-pr-status-information">
            <dt>Procurement Status</dt>
            <dd><span id="viewRequestedStatus" class="requested-pr-modal-status">—</span></dd>
          </div>
        </dl>

        <section class="requested-pr-detail-section" aria-labelledby="viewRequestedPartsTitle">
          <h3 id="viewRequestedPartsTitle">Requested Item / Part</h3>
          <div class="requested-pr-breakdown-box">
            <table class="requested-pr-breakdown-table">
              <thead>
                <tr><th scope="col">Part Name</th><th scope="col">Quantity</th><th scope="col">Unit</th></tr>
              </thead>
              <tbody id="viewRequestedPartsContainer">
                <tr><td>—</td><td>—</td><td>—</td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <section class="requested-pr-detail-section requested-pr-remarks-section" aria-labelledby="viewRequestedRemarksTitle">
          <h3 id="viewRequestedRemarksTitle">Remarks</h3>
          <p id="viewRequestedRemarks">No remarks</p>
        </section>
      </div>

      <div class="requested-pr-footer">
        <button type="button" id="closeRequestedPrModalBottom" class="requested-pr-close-bottom">
          Close
        </button>
      </div>
    </div>
  </div>
</x-layout.app>
