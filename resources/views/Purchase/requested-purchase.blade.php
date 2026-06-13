<x-layout.app
  title="FROMS - Requested Purchases"
  :assets="[
    'resources/css/Main-style/main.css',
    'resources/css/Main-style/sidebar.css',
    'resources/css/Purchase/requested-purchase.css',
    'resources/js/Purchase/requested-purchase.js'
  ]"
>

  @php
    $statuses = $statuses ?? [
      'For Purchase',
      'Ordered',
      'For Pick-up',
      'For Delivery',
      'Delivered',
      'Picked Up',
    ];

    $totalRequests = $totalRequests ?? 0;
    $forPurchase = $forPurchase ?? 0;
    $ordered = $ordered ?? 0;
    $delivered = $delivered ?? 0;
  @endphp

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

  <div class="app">

    <x-layout.sidebar
      department="Purchase"
      subtitle="Department Module"
      icon="fa-cart-shopping"
      user-name="P. Admin"
      user-role="Purchase Admin"
      :items="[
        ['label' => 'Purchase Orders', 'route' => 'purchase-orders', 'icon' => 'fa-file-invoice'],
        ['label' => 'Requested Purchase', 'route' => 'requested-purchase', 'icon' => 'fa-clipboard-list'],
        ['label' => 'Scheduled Purchase', 'route' => 'scheduled-purchase', 'icon' => 'fa-calendar-check'],
      ]"
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
          value="{{ $totalRequests }}"
          small="Purchase inbox"
          icon="fa-file"
          color="gray"
        />

        <x-ui.summary-card
          label="For Purchase"
          value="{{ $forPurchase }}"
          small="Ready to create PO"
          icon="fa-cart-shopping"
          color="blue"
        />

        <x-ui.summary-card
          label="Ordered"
          value="{{ $ordered }}"
          small="PO already created"
          icon="fa-file-invoice"
          color="yellow"
        />

        <x-ui.summary-card
          label="Delivered / Picked Up"
          value="{{ $delivered }}"
          small="Ready for warehouse issue"
          icon="fa-box"
          color="green"
        />

      </section>

      <section class="table-card requested-purchase-card">

        <div class="section-header">
          <div>
            <h2>Requested Purchase Records</h2>
            <p>Only unavailable warehouse parts are shown here for purchasing.</p>
          </div>
        </div>

        <form action="{{ route('requested-purchase') }}" method="GET" class="requested-toolbar">

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
            <label for="requestedStatusFilter">Status</label>

            <select
              name="status"
              id="requestedStatusFilter"
              onchange="this.form.submit()"
            >
              <option value="All States" {{ request('status', 'All States') === 'All States' ? 'selected' : '' }}>
                All States
              </option>

              @foreach($statuses as $status)
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
                  $statusClass = strtolower(str_replace([' ', '/'], ['-', '-'], $purchaseRequest->status));
                  $partsBreakdown = $purchaseRequest->parts_breakdown ?? [];

                  $firstItem = $purchaseRequest->first_item_display ?? null;
                  $firstQuantity = $purchaseRequest->first_quantity_display ?? null;

                  if (! $firstItem) {
                    $firstItem = trim(explode(',', $purchaseRequest->item ?? '')[0] ?? '');

                    if (str_contains(strtolower($firstItem), ' - qty:')) {
                      $firstItem = trim(preg_split('/ - qty:/i', $firstItem)[0] ?? $firstItem);
                    }

                    if (preg_match('/^(.*?)\s*\((\d+\s*[^)]*)\)$/', $firstItem, $matches)) {
                      $firstItem = trim($matches[1] ?? $firstItem);
                    }
                  }

                  if (! $firstQuantity) {
                    $firstQuantity = $purchaseRequest->quantity ?? '0';
                  }
                @endphp

                <tr>
                  <td>
                    <strong>{{ $purchaseRequest->pr_no }}</strong>
                  </td>

                  <td>
                    <strong>{{ $purchaseRequest->job_order_no }}</strong>
                  </td>

                  <td>{{ $purchaseRequest->bus_no }}</td>

                  <td>
                    <strong>{{ $firstItem ?: '—' }}</strong>
                  </td>

                  <td>{{ $firstQuantity }}</td>

                  <td>
                    <span class="requested-status-badge {{ $statusClass }}">
                      {{ $purchaseRequest->status }}
                    </span>
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
                        data-pr-no="{{ $purchaseRequest->pr_no }}"
                        data-job-order-no="{{ $purchaseRequest->job_order_no }}"
                        data-bus-no="{{ $purchaseRequest->bus_no }}"
                        data-item="{{ $purchaseRequest->item }}"
                        data-quantity="{{ $purchaseRequest->quantity }}"
                        data-status="{{ $purchaseRequest->status }}"
                        data-created="{{ $purchaseRequest->created_at ? $purchaseRequest->created_at->format('m/d/y | h:i A') : '—' }}"
                        data-remarks="{{ $purchaseRequest->remarks ?? 'No remarks' }}"
                        data-parts='@json($partsBreakdown)'
                      >
                        <i class="fa-solid fa-eye"></i>
                      </button>

                      @if($purchaseRequest->status === 'For Purchase')
                        <form
                          action="{{ route('requested-purchase.create-po', $purchaseRequest->id) }}"
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

  {{-- VIEW REQUESTED PURCHASE MODAL --}}
  <div id="viewRequestedPrModal" class="modal-overlay requested-pr-view-overlay">
    <div class="requested-pr-style-modal">

      <div class="requested-pr-modal-header">
        <div>
          <h2>Purchase Request Details</h2>
          <h3>PR Information</h3>
          <p>This is a read-only view of unavailable parts sent by Warehouse.</p>
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
          <label>Status</label>
          <input id="viewRequestedStatus" type="text" value="—" readonly>
        </div>

        <div class="requested-pr-field full">
          <label>Requested Parts Breakdown</label>

          <div class="requested-pr-breakdown-box">
            <div class="requested-pr-breakdown-head">
              <span>Part Name</span>
              <span>Quantity</span>
            </div>

            <div id="viewRequestedPartsContainer" class="requested-pr-breakdown-body">
              <div class="requested-pr-breakdown-row">
                <span>No parts found.</span>
                <span>0</span>
              </div>
            </div>
          </div>
        </div>

        <div class="requested-pr-field">
          <label>Date Created</label>
          <input id="viewRequestedCreated" type="text" value="—" readonly>
        </div>

        <div class="requested-pr-field full">
          <label>Remarks</label>
          <input id="viewRequestedRemarks" type="text" value="No remarks" readonly>
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