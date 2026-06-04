<x-layout.app
  title="FROMS - Warehouse Part Requests"
  :assets="[
    'resources/css/Warehouse/part-requests.css',
    'resources/css/Main-style/main.css',
    'resources/js/Warehouse/part-requests.js'
  ]"
>

  <div class="app">

    <x-layout.sidebar
      department="Warehouse"
      subtitle="Department Module"
      icon="fa-warehouse"
      user-name="W. Admin"
      user-role="Warehouse Admin"
      :items="[
        ['label' => 'Inventory', 'route' => 'inventory', 'icon' => 'fa-boxes-stacked'],
        ['label' => 'Part Requests', 'route' => 'part-requests', 'icon' => 'fa-clipboard-list'],
      ]"
    />

    <main class="main">

      {{-- TOP BAR --}}
      <header class="topbar">
        <div>
          <h1>Part Requests</h1>
          <p>Approved purchase requests from Maintenance for warehouse processing</p>
        </div>

        <div class="top-actions">
          <button class="icon-btn notification">
            <i class="fa-regular fa-bell"></i>
            <span>6</span>
          </button>

          <button class="icon-btn">
            <i class="fa-regular fa-circle-question"></i>
          </button>

          <button class="icon-btn">
            <i class="fa-solid fa-user"></i>
          </button>
        </div>
      </header>

      {{-- SUMMARY CARDS --}}
      <section class="stats-grid inventory-stats">

        <x-ui.summary-card
          label="Approved"
          value="{{ $approved }}"
          small="Ready to process"
          icon="fa-check"
          color="green"
        />

        <x-ui.summary-card
          label="For Purchase"
          value="{{ $forPurchase }}"
          small="Parts unavailable"
          icon="fa-cart-shopping"
          color="blue"
        />

        <x-ui.summary-card
          label="Delivered"
          value="{{ $delivered }}"
          small="Supplier delivered"
          icon="fa-box"
          color="yellow"
        />

        <x-ui.summary-card
          label="Issued"
          value="{{ $issued }}"
          small="Released parts"
          icon="fa-box-open"
          color="gray"
        />

      </section>

      {{-- PART REQUEST TABLE --}}
      <section class="table-card inventory-card">

        <div class="section-header">
          <div>
            <h2>Warehouse Part Request Records</h2>
            <p>Track approved PRs, unavailable parts, delivered items, and issued parts</p>
          </div>
        </div>

        <form method="GET" action="{{ route('part-requests') }}" class="toolbar inventory-toolbar">
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
            <label>Status</label>
            <select name="status" onchange="this.form.submit()">
              <option value="All Statuses" {{ request('status') == 'All Statuses' ? 'selected' : '' }}>
                All Statuses
              </option>

              <option value="Approved" {{ request('status') == 'Approved' ? 'selected' : '' }}>
                Under Review
              </option>

              <option value="For Purchase" {{ request('status') == 'For Purchase' ? 'selected' : '' }}>
                For Purchase
              </option>

              <option value="Pending Purchase" {{ request('status') == 'Pending Purchase' ? 'selected' : '' }}>
                Pending Purchase
              </option>

              <option value="Delivering" {{ request('status') == 'Delivering' ? 'selected' : '' }}>
                Delivering
              </option>

              <option value="Delivered" {{ request('status') == 'Delivered' ? 'selected' : '' }}>
                Delivered
              </option>

              <option value="Issued" {{ request('status') == 'Issued' ? 'selected' : '' }}>
                Issued
              </option>
            </select>
          </div>
        </form>

        <div class="table-wrap">
          <table class="inventory-table">
            <thead>
              <tr>
                <th>PR #</th>
                <th>JO No.</th>
                <th>Bus #</th>
                <th>Item</th>
                <th>Quantity</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              @forelse($partRequests as $request)
                <tr>
                  <td><strong>{{ $request->pr_no }}</strong></td>
                  <td><strong>{{ $request->job_order_no }}</strong></td>
                  <td>{{ $request->bus_no }}</td>
                  <td>{{ $request->item }}</td>
                  <td>{{ $request->quantity }}</td>

                  <td>
                   @php
                      $badgeClass = match($request->status) {
                        'Approved' => 'under-review',
                        'For Purchase' => 'for-purchase',
                        'Pending Purchase' => 'pending-purchase',
                        'Delivering' => 'delivering',
                        'Delivered' => 'delivered',
                        'Issued' => 'issued',
                        default => 'pending-purchase'
                      };

                      $statusLabel = match($request->status) {
                        'Approved' => 'Under Review',
                        default => $request->status
                      };
                    @endphp

                    <span class="badge {{ $badgeClass }}">
                      {{ $statusLabel }}
                    </span>
                  </td>

                  <td>{{ $request->created_at->format('M d, Y') }}</td>

                  <td>
                    <div class="actions">

                      {{-- VIEW DETAILS BUTTON --}}
                      <button
                        type="button"
                        class="view-btn open-view-pr-modal"
                        title="View Details"
                        data-pr-no="{{ $request->pr_no }}"
                        data-job-order-no="{{ $request->job_order_no }}"
                        data-bus-no="{{ $request->bus_no }}"
                        data-item="{{ $request->item }}"
                        data-quantity="{{ $request->quantity }}"
                        data-status="{{ $request->status }}"
                        data-remarks="{{ $request->remarks ?? 'No remarks' }}"
                        data-created="{{ $request->created_at->format('M d, Y') }}"
                      >
                        <i class="fa-solid fa-eye"></i>
                      </button>

                      @if($request->status === 'Approved')
                        <form action="{{ route('purchase-requests.issue', $request->id) }}" method="POST">
                          @csrf

                          <button type="submit" class="edit" title="Issue Parts">
                            <i class="fa-solid fa-box-open"></i>
                          </button>
                        </form>

                        <form action="{{ route('purchase-requests.for-purchase', $request->id) }}" method="POST">
                          @csrf

                          <button type="submit" class="delete" title="For Purchase">
                            <i class="fa-solid fa-cart-shopping"></i>
                          </button>
                        </form>
                      @endif

                      @if($request->status === 'Delivered')
                        <form action="{{ route('purchase-requests.issue', $request->id) }}" method="POST">
                          @csrf

                          <button type="submit" class="edit" title="Issue Parts">
                            <i class="fa-solid fa-box-open"></i>
                          </button>
                        </form>
                      @endif

                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="8" style="text-align:center; padding: 30px;">
                    No approved part requests found.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="table-footer">
          <p>
            Showing {{ $partRequests->firstItem() ?? 0 }} to {{ $partRequests->lastItem() ?? 0 }} of {{ $partRequests->total() }} entries
          </p>

          <div class="pagination">
            {{ $partRequests->links() }}
          </div>
        </div>

      </section>

    </main>

  </div>

  {{-- VIEW PR DETAILS MODAL --}}
  <div id="viewPrModal" class="modal-overlay">
    <div class="modal-box wide-modal">

      <div class="modal-header">
        <h2>Purchase Request Details</h2>

        <button type="button" id="closeViewPrModal" class="close-btn">
          &times;
        </button>
      </div>

      <div class="form-section-title full-width">
        <h3>PR Information</h3>
        <p>This is a read-only view of the selected purchase request.</p>
      </div>

      <div class="details-grid">

        <div class="detail-item">
          <span>PR No.</span>
          <strong id="view_pr_no">—</strong>
        </div>

        <div class="detail-item">
          <span>JO No.</span>
          <strong id="view_job_order_no">—</strong>
        </div>

        <div class="detail-item">
          <span>Bus #</span>
          <strong id="view_bus_no">—</strong>
        </div>

        <div class="detail-item">
          <span>Status</span>
          <strong id="view_status">—</strong>
        </div>

        <div class="detail-item full-width">
          <span>Requested Item / Part</span>
          <strong id="view_item">—</strong>
        </div>

        <div class="detail-item">
          <span>Quantity</span>
          <strong id="view_quantity">—</strong>
        </div>

        <div class="detail-item">
          <span>Created</span>
          <strong id="view_created">—</strong>
        </div>

        <div class="detail-item full-width">
          <span>Remarks</span>
          <strong id="view_remarks">—</strong>
        </div>

      </div>

      <div class="modal-actions full-width">
        <button type="button" id="closeViewPrModalBottom" class="cancel-btn">
          Close
        </button>
      </div>

    </div>
  </div>

</x-layout.app>