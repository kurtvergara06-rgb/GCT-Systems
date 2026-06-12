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
            <p>Only purchase process PRs are shown here. Issued requests are hidden.</p>
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
          <table>
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
                  $firstItem = trim(explode(',', $purchaseRequest->item ?? '')[0] ?? '');

                  if (str_contains(strtolower($firstItem), ' - qty:')) {
                    $firstItem = trim(preg_split('/ - qty:/i', $firstItem)[0] ?? $firstItem);
                  }

                  $statusClass = strtolower(str_replace([' ', '/'], ['-', '-'], $purchaseRequest->status));
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

                  <td>{{ $purchaseRequest->quantity }}</td>

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
                        data-remarks="{{ $purchaseRequest->remarks }}"
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
  <div id="viewRequestedPrModal" class="modal-overlay">
    <div class="modal-box requested-pr-modal">

      <div class="requested-pr-modal-header">
        <div>
          <h2>Requested Purchase Details</h2>
          <p>View the selected purchase request information.</p>
        </div>

        <button type="button" id="closeRequestedPrModal" class="requested-pr-close-btn">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <div class="requested-pr-grid">

        <div class="requested-pr-field">
          <label>PR No.</label>
          <div id="viewRequestedPrNo">—</div>
        </div>

        <div class="requested-pr-field">
          <label>JO No.</label>
          <div id="viewRequestedJoNo">—</div>
        </div>

        <div class="requested-pr-field">
          <label>Bus #</label>
          <div id="viewRequestedBusNo">—</div>
        </div>

        <div class="requested-pr-field">
          <label>Status</label>
          <div id="viewRequestedStatus">—</div>
        </div>

        {{-- ITEM ROWS LIKE:
             tire | 8
             oil  | 2
        --}}
        <div class="requested-pr-field full-width requested-parts-field">
          <label>Requested Item / Part</label>

          <div id="viewRequestedPartsContainer" class="requested-parts-container">
            {{-- JS will render rows here --}}
          </div>
        </div>

        <div class="requested-pr-field">
          <label>Created</label>
          <div id="viewRequestedCreated">—</div>
        </div>

        <div class="requested-pr-field full-width">
          <label>Remarks</label>
          <div id="viewRequestedRemarks">—</div>
        </div>

      </div>

      <div class="requested-pr-actions">
        <button type="button" id="closeRequestedPrModalBottom" class="requested-pr-close-bottom">
          Close
        </button>
      </div>

    </div>
  </div>

</x-layout.app>