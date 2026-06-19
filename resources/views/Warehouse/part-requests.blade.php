<x-layout.app
  title="FROMS - Warehouse Part Requests"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Warehouse/part-request.css',
    'resources/js/Warehouse/part-requests.js'
  ]"
>

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
          value="{{ $approved ?? 0 }}"
          small="Ready to process"
          icon="fa-check"
          color="green"
        />

        <x-ui.summary-card
          label="For Purchase"
          value="{{ $forPurchase ?? 0 }}"
          small="Parts unavailable"
          icon="fa-cart-shopping"
          color="blue"
        />

        <x-ui.summary-card
          label="Delivered"
          value="{{ $delivered ?? 0 }}"
          small="Supplier delivered"
          icon="fa-box"
          color="yellow"
        />

        <x-ui.summary-card
          label="Issued"
          value="{{ $issued ?? 0 }}"
          small="Released parts"
          icon="fa-box-open"
          color="gray"
        />

      </section>

      {{-- ACTIVE REQUESTS TABLE --}}
      <section class="table-card inventory-card warehouse-part-card">

        <div class="section-header">
          <div>
            <h2>Warehouse Part Request Records</h2>
            <p>Track approved PRs, unavailable parts, inventory availability, and issued parts</p>
          </div>
        </div>

        <form action="{{ route('part-requests') }}" method="GET" class="toolbar inventory-toolbar warehouse-part-toolbar">

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

              @foreach(($statuses ?? []) as $status)
                <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                  {{ $status }}
                </option>
              @endforeach
            </select>
          </div>

        </form>

        <div class="table-wrap">
          <table class="inventory-table warehouse-part-table">
            <thead>
              <tr>
                <th>PR #</th>
                <th>Item</th>
                <th class="qty-col">Quantity</th>
                <th class="qty-col">On Hand</th>
                <th class="status-col">Inventory</th>
                <th class="status-col">Purchase Status</th>
                <th>Date</th>
                <th class="actions-col">Actions</th>
              </tr>
            </thead>

            <tbody>
              @forelse($purchaseRequests as $partRequest)
                @php
                  $status = $partRequest->purchase_progress_status ?? $partRequest->status ?? 'Approved';
                  $statusClass = strtolower(str_replace([' ', '/'], ['-', '-'], $status));

                  $itemName = $partRequest->first_item_display ?? '—';
                  $quantity = $partRequest->first_quantity_display ?? '0';
                  $onHand = $partRequest->first_on_hand_display ?? '0';

                  $inventoryStatus = $partRequest->first_inventory_status ?? $partRequest->inventory_label ?? 'Not Available';

                  $inventoryClass = match ($inventoryStatus) {
                      'Available' => 'available',
                      'Not Fully Available' => 'not-fully-available',
                      default => 'not-available',
                  };

                  $onHandClass = $inventoryStatus === 'Available' ? 'enough' : 'low';

                  $canIssue = $partRequest->can_issue ?? false;

                  $needsPurchase = ($partRequest->needs_purchase ?? false)
                    && ! ($partRequest->missing_pr_already_created ?? false);
                @endphp

                <tr>
                  <td>
                    <strong>{{ $partRequest->pr_no ?? '—' }}</strong>
                  </td>

                  <td class="item-col">
                    {{ $itemName }}
                  </td>

                  <td class="qty-col">
                    {{ $quantity }}
                  </td>

                  <td class="qty-col">
                    <span class="on-hand-pill {{ $onHandClass }}">
                      {{ $onHand }}
                    </span>
                  </td>

                  <td class="status-col">
                    <x-ui.status-badge 
                      :status="$inventoryStatus"
                      type="inventory"
                    />
                  </td>

                  <td class="status-col">
                    <x-ui.status-badge 
                      :status="$status"
                      type="purchase"
                    />
                  </td>

                  <td>
                    {{ $partRequest->created_at ? $partRequest->created_at->format('M d, Y') : '—' }}
                  </td>

                  <td class="actions-col">
                    <div class="actions warehouse-actions">

                      <button
                        type="button"
                        class="view-btn open-view-pr-modal"
                        title="View Details"
                        data-pr-no="{{ $partRequest->pr_no }}"
                        data-job-order-no="{{ $partRequest->job_order_no }}"
                        data-bus-no="{{ $partRequest->bus_no }}"
                        data-item="{{ $partRequest->item }}"
                        data-quantity="{{ $quantity }}"
                        data-on-hand="{{ $onHand }}"
                        data-inventory-status="{{ $inventoryStatus }}"
                        data-status="{{ $status }}"
                        data-remarks="{{ $partRequest->remarks ?? 'No remarks' }}"
                        data-created="{{ $partRequest->created_at ? $partRequest->created_at->format('M d, Y') : '—' }}"
                        data-parts='@json($partRequest->parts_breakdown ?? [])'
                      >
                        <i class="fa-solid fa-eye"></i>
                      </button>

                      @if($needsPurchase)
                        <form
                          action="{{ route('part-requests.send-to-purchase', $partRequest->id) }}"
                          method="POST"
                          class="inline-action-form"
                        >
                          @csrf

                          <button
                            type="submit"
                            class="send-purchase-btn icon-only-btn"
                            title="Send to Purchase"
                          >
                            <i class="fa-solid fa-cart-shopping"></i>
                          </button>
                        </form>
                      @endif

                      @if($canIssue)
                        <form
                          action="{{ route('part-requests.issue', $partRequest->id) }}"
                          method="POST"
                          class="inline-action-form"
                        >
                          @csrf

                          <button
                            type="submit"
                            class="issue-part-btn icon-only-btn"
                            title="Issue Parts"
                          >
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
                  message="No active part requests found."
                />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$purchaseRequests" />

      </section>

      {{-- ISSUED HISTORY TABLE --}}
      <section class="table-card inventory-card warehouse-history-card">

        <div class="section-header">
          <div>
            <h2>Issued Parts History</h2>
            <p>Completed warehouse part requests that were already issued.</p>
          </div>
        </div>

        <div class="table-wrap">
          <table class="inventory-table warehouse-part-table">
            <thead>
              <tr>
                <th>PR #</th>
                <th>JO No.</th>
                <th>Bus #</th>
                <th>Item</th>
                <th class="qty-col">Quantity</th>
                <th class="status-col">Status</th>
                <th>Date Issued</th>
                <th class="actions-col">Actions</th>
              </tr>
            </thead>

            <tbody>
              @forelse(($issuedRequests ?? []) as $history)
                @php
                  $itemName = $history->first_item_display ?? $history->item ?? '—';
                  $quantity = $history->first_quantity_display ?? $history->quantity ?? '—';
                @endphp

                <tr>
                  <td>
                    <strong>{{ $history->pr_no ?? '—' }}</strong>
                  </td>

                  <td>{{ $history->job_order_no ?? '—' }}</td>

                  <td>{{ $history->bus_no ?? '—' }}</td>

                  <td>{{ $itemName }}</td>

                  <td class="qty-col">
                    {{ $quantity }}
                  </td>

                  <td class="status-col">
                    <x-ui.status-badge 
                      status="Issued"
                      type="purchase"
                    />
                  </td>

                  <td>
                    {{ $history->updated_at ? $history->updated_at->format('M d, Y') : '—' }}
                  </td>

                  <td class="actions-col">
                    <div class="actions warehouse-actions">
                      <button
                        type="button"
                        class="view-btn open-view-pr-modal"
                        title="View Details"
                        data-pr-no="{{ $history->pr_no }}"
                        data-job-order-no="{{ $history->job_order_no }}"
                        data-bus-no="{{ $history->bus_no }}"
                        data-item="{{ $history->item }}"
                        data-quantity="{{ $quantity }}"
                        data-on-hand="{{ $history->first_on_hand_display ?? '0' }}"
                        data-inventory-status="{{ $history->first_inventory_status ?? 'Available' }}"
                        data-status="Issued"
                        data-remarks="{{ $history->remarks ?? 'No remarks' }}"
                        data-created="{{ $history->updated_at ? $history->updated_at->format('M d, Y') : '—' }}"
                        data-parts='@json($history->parts_breakdown ?? [])'
                      >
                        <i class="fa-solid fa-eye"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              @empty
                <x-ui.empty-row
                  colspan="8"
                  message="No issued history yet."
                />
              @endforelse
            </tbody>
          </table>
        </div>

        @if(isset($issuedRequests))
          <x-ui.table-footer :items="$issuedRequests" />
        @endif

      </section>

    </main>

  </div>

  {{-- VIEW MODAL --}}
  <div id="viewPrModal" class="modal-overlay warehouse-view-overlay">
    <div class="warehouse-edit-style-modal">

      <div class="warehouse-edit-header">
        <div>
          <h2>Purchase Request Details</h2>
          <h3>PR Information</h3>
          <p>This is a read-only view of the selected purchase request.</p>
        </div>

        <button type="button" id="closeViewPrModal" class="warehouse-edit-close">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <div class="warehouse-edit-form-grid">

        <div class="warehouse-field">
          <label>PR No.</label>
          <input id="view_pr_no" type="text" value="—" readonly>
        </div>

        <div class="warehouse-field">
          <label>JO No.</label>
          <input id="view_job_order_no" type="text" value="—" readonly>
        </div>

        <div class="warehouse-field">
          <label>Bus #</label>
          <input id="view_bus_no" type="text" value="—" readonly>
        </div>

        <div class="warehouse-field">
          <label>Date Created</label>
          <input id="view_created" type="text" value="—" readonly>
        </div>

        <div class="warehouse-field full">
          <label>Requested Parts Breakdown</label>

          <div id="view_parts_breakdown" class="parts-breakdown-box">
            <div class="parts-breakdown-empty">No parts found.</div>
          </div>
        </div>

        <div class="warehouse-field full">
          <label>Remarks</label>
          <input id="view_remarks" type="text" value="No remarks" readonly>
        </div>

      </div>

      <div class="warehouse-edit-footer">
        <button type="button" id="closeViewPrModalBottom" class="warehouse-cancel-btn">
          Close
        </button>
      </div>

    </div>
  </div>

</x-layout.app>
