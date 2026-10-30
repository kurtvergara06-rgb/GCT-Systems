<x-layout.app
  title="FROMS - Maintenance Requests"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Main-styles/pr-details-modal.css',
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
                <th scope="col">PR ID</th>
                <th scope="col">Bus ID</th>
                <th scope="col">Requested Parts</th>
                <th scope="col">QTY</th>
                <th scope="col">Total Items</th>
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
                  <td><div class="requested-pr-reference"><strong>{{ $purchaseRequest->display_pr_no }}</strong></div></td>
                  <td class="requested-pr-bus"><strong>{{ $purchaseRequest->bus_no ?: '—' }}</strong></td>
                  <td>
                    <div class="requested-pr-part">
                      <strong>{{ $firstItem }}</strong>
                      @if(count($parts) > 1)
                        <details class="requested-pr-more">
                          <summary>+{{ count($parts) - 1 }} more parts</summary>
                          <div class="requested-pr-more-panel">
                            @foreach(array_slice($parts, 1) as $part)
                              <div>{{ $part['name'] ?? $part['item'] ?? 'Part' }}</div>
                            @endforeach
                          </div>
                        </details>
                      @endif
                    </div>
                  </td>
                  <td class="requested-pr-part-qty">
                    <strong>{{ $firstQuantity }}</strong>
                    @if(count($parts) > 1)
                      <details class="requested-pr-more requested-pr-more-qty">
                        <summary>+{{ count($parts) - 1 }} more</summary>
                        <div class="requested-pr-more-panel">
                          @foreach(array_slice($parts, 1) as $part)
                            <div>{{ $part['quantity_display'] ?? ($part['quantity'] ?? '—') }}</div>
                          @endforeach
                        </div>
                      </details>
                    @endif
                  </td>
                  <td class="requested-pr-quantity">{{ count($parts) }} {{ count($parts) === 1 ? 'Part' : 'Parts' }}</td>
                  <td class="requested-pr-status">
                    @if($purchaseRequest->status === 'Ordered')
                      <span class="requested-pr-ordered-badge"><i class="fa-solid fa-box" aria-hidden="true"></i> Ordered</span>
                    @else
                      <x-ui.status-badge
                        :status="$purchaseRequest->status ?? '—'"
                        type="purchase"
                      />
                    @endif
                  </td>
                  <td class="requested-pr-actions-cell">
                    <div class="record-actions">
                      <button
                        type="button"
                        class="action-btn view open-view-requested-pr-modal requested-pr-review-button"
                        title="{{ $purchaseRequest->status === 'For Purchase' ? 'Review Purchase Requisition' : 'View Purchase Requisition' }}"
                        data-create-po-url="{{ $purchaseRequest->status === 'For Purchase' ? route('maintenance-requests.create-po', $purchaseRequest->id, false) : '' }}"
                        data-pr-no="{{ $purchaseRequest->display_pr_no }}"
                        data-job-order-no="{{ $purchaseRequest->job_order_no }}"
                        data-bus-no="{{ $purchaseRequest->bus_no }}"
                        data-created="{{ $purchaseRequest->created_at ? $purchaseRequest->created_at->format('m/d/y | h:i A') : '—' }}"
                        data-requested-by="{{ $purchaseRequest->requester?->name ?? '—' }}"
                        data-remarks="{{ $purchaseRequest->remarks ?? 'No remarks' }}"
                        data-status="{{ $purchaseRequest->status ?? '—' }}"
                        data-jo-approver="{{ $purchaseRequest->jo_approver ?? 'Not available' }}"
                        data-jo-approval-status="{{ $purchaseRequest->jo_approval_status ?? 'Not verified' }}"
                        data-jo-approval-signature="{{ $purchaseRequest->jo_approval_signature ?? '' }}"
                        data-jo-approved-at="{{ $purchaseRequest->jo_approved_at ?? '' }}"
                        data-item="{{ $purchaseRequest->item }}"
                        data-quantity="{{ $firstQuantity }}"
                        data-parts='@json($parts)'
                      >
                        <i class="fa-solid {{ $purchaseRequest->status === 'For Purchase' ? 'fa-clipboard-check' : 'fa-eye' }}"></i><span>{{ $purchaseRequest->status === 'For Purchase' ? 'Review' : 'View' }}</span>
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

  <x-ui.pr-details-modal
    modal-id="viewRequestedPrModal" title-id="requestedPrModalTitle" close-id="closeRequestedPrModal"
    pr-id="viewRequestedPrNo" jo-id="viewRequestedJoNo" bus-id="viewRequestedBusNo"
    parts-id="viewRequestedPartsContainer" requested-by-id="viewRequestedBy" created-id="viewRequestedCreated"
    processing-id="viewRequestedStatus" parts-count-id="viewRequestedPartsCount"
    stock-reference-id="viewRequestedStockReference" remarks-id="viewRequestedRemarks"
    status-badge-id="viewRequestedStatusBadge"
  >
    <x-slot:footer>
      <button type="button" id="closeRequestedPrModalBottom" class="shared-pr-close-button">Close</button>
      <form id="requestedPrCreatePoForm" method="POST" hidden>@csrf<button type="submit" class="requested-pr-create-po-btn"><i class="fa-solid fa-cart-plus"></i> Create PO</button></form>
    </x-slot:footer>
  </x-ui.pr-details-modal>
</x-layout.app>
