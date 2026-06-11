<x-layout.app
  title="FROMS - Warehouse Part Requests"
  :assets="[
    'resources/css/Main-style/main.css',
    'resources/css/Main-style/sidebar.css',
    'resources/css/Warehouse/part-requests.css',
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

      <x-layout.topbar
        title="Part Requests"
        subtitle="Approved purchase requests from Maintenance for warehouse processing"
        notification-count="6"
      />

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

      <section class="table-card inventory-card">

        <div class="section-header">
          <div>
            <h2>Warehouse Part Request Records</h2>
            <p>Track approved PRs, unavailable parts, delivered items, and issued parts</p>
          </div>
        </div>

        <x-ui.table-toolbar
          :action="route('part-requests')"
          class="toolbar inventory-toolbar"
          search-placeholder="Search PR no., JO no., bus, or item..."
          :show-button="false"
        >
          <div class="filter-group">
            <label for="warehouseStatusFilter">Status</label>

            <select
              name="status"
              id="warehouseStatusFilter"
              class="warehouse-status-select"
              onchange="this.form.submit()"
            >
              <option value="All Statuses" {{ request('status', 'All Statuses') == 'All Statuses' ? 'selected' : '' }}>
                All Statuses
              </option>

              <option value="Submitted" {{ request('status') == 'Submitted' ? 'selected' : '' }}>
                Submitted
              </option>

              <option value="Approved" {{ request('status') == 'Approved' ? 'selected' : '' }}>
                Approved
              </option>

              <option value="Rejected" {{ request('status') == 'Rejected' ? 'selected' : '' }}>
                Rejected
              </option>

              <option value="For Purchase" {{ request('status') == 'For Purchase' ? 'selected' : '' }}>
                For Purchase
              </option>

              <option value="Ordered" {{ request('status') == 'Ordered' ? 'selected' : '' }}>
                Ordered
              </option>

              <option value="For Pick-up" {{ request('status') == 'For Pick-up' ? 'selected' : '' }}>
                For Pick-up
              </option>

              <option value="For Delivery" {{ request('status') == 'For Delivery' ? 'selected' : '' }}>
                For Delivery
              </option>

              <option value="Delivered" {{ request('status') == 'Delivered' ? 'selected' : '' }}>
                Delivered
              </option>

              <option value="Picked Up" {{ request('status') == 'Picked Up' ? 'selected' : '' }}>
                Picked Up
              </option>

              <option value="Issued" {{ request('status') == 'Issued' ? 'selected' : '' }}>
                Issued
              </option>
            </select>
          </div>
        </x-ui.table-toolbar>

        <div class="table-wrap">
          <table class="inventory-table">
            <thead>
              <tr>
                <th>PR #</th>
                <th>JO No.</th>
                <th>Bus #</th>
                <th>Item</th>
                <th>Quantity</th>
                <th class="status-col">Status</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              @forelse($partRequests as $partRequest)
                @php
                  $statusClass = strtolower(str_replace([' ', '/'], ['-', '-'], $partRequest->status));
                @endphp

                <tr>
                  <td>
                    <strong>{{ $partRequest->pr_no }}</strong>
                  </td>

                  <td>
                    <strong>{{ $partRequest->job_order_no }}</strong>
                  </td>

                  <td>{{ $partRequest->bus_no }}</td>
                  <td>{{ $partRequest->item }}</td>
                  <td>{{ $partRequest->quantity }}</td>

                  <td class="status-col">
                    <span class="warehouse-status-badge {{ $statusClass }}">
                      {{ $partRequest->status }}
                    </span>
                  </td>

                  <td>{{ $partRequest->created_at->format('M d, Y') }}</td>

                  <td>
                    <div class="actions">

                      <button
                        type="button"
                        class="view-btn open-view-pr-modal"
                        title="View Details"
                        data-pr-no="{{ $partRequest->pr_no }}"
                        data-job-order-no="{{ $partRequest->job_order_no }}"
                        data-bus-no="{{ $partRequest->bus_no }}"
                        data-item="{{ $partRequest->item }}"
                        data-quantity="{{ $partRequest->quantity }}"
                        data-status="{{ $partRequest->status }}"
                        data-remarks="{{ $partRequest->remarks ?? 'No remarks' }}"
                        data-created="{{ $partRequest->created_at->format('M d, Y') }}"
                      >
                        <i class="fa-solid fa-eye"></i>
                      </button>

                      @if($partRequest->status === 'Approved')
                        <form action="{{ route('part-requests.send-to-purchase', $partRequest->id) }}" method="POST">
                          @csrf

                          <button type="submit" class="delete" title="For Purchase">
                            <i class="fa-solid fa-cart-shopping"></i>
                          </button>
                        </form>
                      @endif

                      @if(in_array($partRequest->status, ['Delivered', 'Picked Up'], true))
                        <form action="{{ route('part-requests.issue', $partRequest->id) }}" method="POST">
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
                <x-ui.empty-row
                  colspan="8"
                  message="No approved part requests found."
                />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$partRequests" />

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

  {{-- FEEDBACK MODAL FALLBACK --}}
  <div id="feedbackModal" class="modal-overlay">
    <div class="modal-box wide-modal">

      <div class="modal-header">
        <h2>Feedback Details</h2>

        <button type="button" id="closeFeedbackModal" class="close-btn">
          &times;
        </button>
      </div>

      <div class="form-section-title full-width">
        <h3>Feedback Information</h3>
        <p>This section displays feedback details for the selected record.</p>
      </div>

      <div class="details-grid">

        <div class="detail-item">
          <span>Reference No.</span>
          <strong id="feedback_reference_no">—</strong>
        </div>

        <div class="detail-item">
          <span>Status</span>
          <strong id="feedback_status">—</strong>
        </div>

        <div class="detail-item full-width">
          <span>Message</span>
          <strong id="feedback_message">No feedback available.</strong>
        </div>

        <div class="detail-item full-width">
          <span>Remarks</span>
          <strong id="feedback_remarks">—</strong>
        </div>

      </div>

      <div class="modal-actions full-width">
        <button type="button" id="closeFeedbackModalBottom" class="cancel-btn">
          Close
        </button>
      </div>

    </div>
  </div>

</x-layout.app>