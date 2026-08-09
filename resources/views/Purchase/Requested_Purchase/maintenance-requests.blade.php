<x-layout.app
  title="FROMS - Maintenance Requests"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Purchase/Requested_Purchase/maintenance-requests.css',
    'resources/js/Purchase/Requested_Purchase/maintenance-requests.js'
  ]"
>
  <div class="app">
    <x-layout.sidebar
      department="Purchase"
      subtitle="Department Module"
      icon="fa-cart-shopping"
      :items="[]"
    />

    <main class="main">
      <x-layout.topbar
        title="Requested Purchases"
        subtitle="Purchase Department request inbox from Warehouse"
        notification-count="6"
      />

      <section class="stats-grid">
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

      <section class="table-card requested-purchase-card">
        <div class="section-header">
          <div>
            <h2>Requested Purchase Records</h2>
            <p>Only active maintenance purchase requests are shown here. Completed records are available in Purchase History.</p>
          </div>
        </div>

        <form
          action="{{ route('maintenance-requests', [], false) }}"
          method="GET"
          class="toolbar requested-toolbar"
        >
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input
              type="text"
              name="search"
              value="{{ request('search') }}"
              placeholder="Search PR no., JO no., bus, item, or status..."
            >
          </div>

          <div class="filter-group">
            <select
              name="status"
              id="requestedStatusFilter"
              aria-label="Status"
            >
              <option value="All States" {{ request('status', 'All States') === 'All States' ? 'selected' : '' }}>
                All States
              </option>
              @foreach(($statuses ?? []) as $status)
                <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                  {{ $status }}
                </option>
              @endforeach
            </select>
          </div>
        </form>

        <div class="table-wrap">
          <table class="requested-purchase-table">
            <thead>
              <tr>
                <th>PR #</th>
                <th>JO No.</th>
                <th>Bus #</th>
                <th>Item</th>
                <th>Quantity</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
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
                  <td><strong>{{ $purchaseRequest->display_pr_no }}</strong></td>
                  <td><strong>{{ $purchaseRequest->job_order_no ?? '—' }}</strong></td>
                  <td>{{ $purchaseRequest->bus_no ?? '—' }}</td>
                  <td>{{ $firstItem }}</td>
                  <td>{{ $firstQuantity }}</td>
                  <td>
                    <x-ui.status-badge
                      :status="$purchaseRequest->status ?? '—'"
                      type="purchase"
                    />
                  </td>
                  <td>
                    {{ $purchaseRequest->created_at ? $purchaseRequest->created_at->format('m/d/y | h:i A') : '—' }}
                  </td>
                  <td>
                    <div class="actions">
                      <button
                        type="button"
                        class="action-btn view open-view-requested-pr-modal"
                        title="View"
                        data-pr-no="{{ $purchaseRequest->display_pr_no }}"
                        data-job-order-no="{{ $purchaseRequest->job_order_no }}"
                        data-bus-no="{{ $purchaseRequest->bus_no }}"
                        data-created="{{ $purchaseRequest->created_at ? $purchaseRequest->created_at->format('m/d/y | h:i A') : '—' }}"
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
                  colspan="8"
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

  <div id="viewRequestedPrModal" class="modal-overlay requested-pr-view-overlay">
    <div class="requested-pr-style-modal">
      <div class="requested-pr-modal-header">
        <div>
          <h2>Requested Purchase Details</h2>
          <h3>PR Information</h3>
          <p>This is a read-only view of the selected purchase request.</p>
        </div>
        <button type="button" id="closeRequestedPrModal" class="requested-pr-close-btn">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <div class="requested-pr-form-grid">
        <div class="requested-pr-field">
          <label>PR No.</label>
          <input id="viewRequestedPrNo" type="text" value="—" readonly>
        </div>
        <div class="requested-pr-field">
          <label>JO No.</label>
          <input id="viewRequestedJoNo" type="text" value="—" readonly>
        </div>
        <div class="requested-pr-field">
          <label>Bus #</label>
          <input id="viewRequestedBusNo" type="text" value="—" readonly>
        </div>
        <div class="requested-pr-field">
          <label>Created Date</label>
          <input id="viewRequestedCreated" type="text" value="—" readonly>
        </div>

        <div class="requested-pr-field requested-pr-full">
          <label>Requested Item / Part</label>
          <div class="requested-pr-breakdown-box">
            <div class="requested-pr-breakdown-head">
              <span>Part Name</span>
              <span>Quantity</span>
              <span>Unit</span>
            </div>
            <div id="viewRequestedPartsContainer">
              <div class="requested-pr-breakdown-row">
                <span>—</span>
                <span>—</span>
                <span>—</span>
              </div>
            </div>
          </div>
        </div>

        <div class="requested-pr-field requested-pr-full">
          <label>Remarks</label>
          <textarea id="viewRequestedRemarks" rows="3" readonly>No remarks</textarea>
        </div>
      </div>

      <div class="requested-pr-footer">
        <button type="button" id="closeRequestedPrModalBottom" class="requested-pr-close-bottom">
          Close
        </button>
      </div>
    </div>
  </div>
</x-layout.app>
