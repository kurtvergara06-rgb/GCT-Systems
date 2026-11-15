<x-layout.app
  title="FROMS - Purchase History"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Purchase/Requested_Purchase/maintenance-requests.css',
    'resources/js/Purchase/Requested_Purchase/maintenance-requests.js'
  ]"
>
  <div class="app">
    <x-layout.sidebar department="Purchase" />

    <main class="main">
      <x-layout.topbar
        title="Purchase History"
        subtitle="Review completed maintenance and inventory restock procurement records"
        notification-count="6"
      />

      <section data-ajax-region="summary" class="stats-grid">
        <x-ui.summary-card
          label="Total History"
          value="{{ $totalHistory ?? 0 }}"
          small="Completed procurement records"
          icon="fa-clock-rotate-left"
          color="gray"
        />
        <x-ui.summary-card
          label="Maintenance"
          value="{{ $maintenanceHistory ?? 0 }}"
          small="Issued maintenance requests"
          icon="fa-screwdriver-wrench"
          color="blue"
        />
        <x-ui.summary-card
          label="Inventory Restock"
          value="{{ $restockHistory ?? 0 }}"
          small="Completed restock requests"
          icon="fa-boxes-stacked"
          color="yellow"
        />
        <x-ui.summary-card
          label="This Month"
          value="{{ $thisMonthHistory ?? 0 }}"
          small="Completed this month"
          icon="fa-calendar-check"
          color="green"
        />
      </section>

      <section data-ajax-region="records" class="table-card requested-purchase-card">
        <div class="section-header">
          <div>
            <h2>Completed Purchase Records</h2>
            <p>Completed requests are kept here for reference and audit history.</p>
          </div>
        </div>

        <form
          action="{{ route('maintenance-requests', [], false) }}"
          method="GET"
          class="toolbar requested-toolbar"
        >
          <input type="hidden" name="view" value="history">

          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input
              type="text"
              name="search"
              value="{{ request('search') }}"
              placeholder="Search request no., JO no., bus, item, source, or status..."
            >
          </div>

          <div class="filter-group">
            <select name="source" aria-label="Source">
              @foreach(['All Sources', 'Maintenance Request', 'Inventory Restock'] as $source)
                <option value="{{ $source }}" {{ request('source', 'All Sources') === $source ? 'selected' : '' }}>
                  {{ $source }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="filter-group">
            <select name="status" aria-label="Status">
              @foreach(['All Statuses', 'Delivered', 'Picked Up', 'Issued'] as $status)
                <option value="{{ $status }}" {{ request('status', 'All Statuses') === $status ? 'selected' : '' }}>
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
                <th>Request #</th>
                <th>Source</th>
                <th>JO No.</th>
                <th>Bus #</th>
                <th>Item</th>
                <th>Quantity</th>
                <th>Status</th>
                <th>Completed</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($historyRecords as $history)
                @php
                  $parts = $history->parts_breakdown ?? [];
                  $itemName = $history->first_item_display ?? $history->item ?? '—';
                  $quantity = $history->first_quantity_display ?? $history->quantity ?? '—';
                @endphp
                <tr>
                  <td><strong>{{ $history->display_pr_no }}</strong></td>
                  <td>{{ $history->history_source_label ?? 'Maintenance Request' }}</td>
                  <td><strong>{{ $history->job_order_no && $history->job_order_no !== 'RESTOCK' ? $history->job_order_no : '—' }}</strong></td>
                  <td>{{ $history->bus_no && $history->bus_no !== 'RESTOCK' ? $history->bus_no : '—' }}</td>
                  <td>{{ $itemName }}</td>
                  <td>{{ $quantity }}</td>
                  <td>
                    <x-ui.status-badge :status="$history->status ?? '—'" type="purchase" />
                  </td>
                  <td>{{ $history->updated_at ? $history->updated_at->format('m/d/y | h:i A') : '—' }}</td>
                  <td>
                    <div class="actions">
                      <button
                        type="button"
                        class="action-btn view open-view-requested-pr-modal"
                        title="View"
                        data-pr-no="{{ $history->display_pr_no }}"
                        data-job-order-no="{{ $history->job_order_no && $history->job_order_no !== 'RESTOCK' ? $history->job_order_no : '—' }}"
                        data-bus-no="{{ $history->bus_no && $history->bus_no !== 'RESTOCK' ? $history->bus_no : '—' }}"
                        data-created="{{ $history->updated_at ? $history->updated_at->format('m/d/y | h:i A') : '—' }}"
                        data-remarks="{{ $history->remarks ?? 'No remarks' }}"
                        data-item="{{ $history->item }}"
                        data-quantity="{{ $quantity }}"
                        data-parts='@json($parts)'
                      >
                        <i class="fa-solid fa-eye"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              @empty
                <x-ui.empty-row colspan="9" message="No completed purchase history found." />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$historyRecords" />
      </section>
    </main>
  </div>

  <div id="viewRequestedPrModal" class="modal-overlay requested-pr-view-overlay">
    <div class="requested-pr-style-modal">
      <div class="requested-pr-modal-header">
        <div>
          <h2>Purchase History Details</h2>
          <h3>Request Information</h3>
          <p>This is a read-only view of the completed procurement record.</p>
        </div>
        <button type="button" id="closeRequestedPrModal" class="requested-pr-close-btn">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <div class="requested-pr-form-grid">
        <div class="requested-pr-field">
          <label>Request No.</label>
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
          <label>Completed Date</label>
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
                <span>—</span><span>—</span><span>—</span>
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
        <button type="button" id="closeRequestedPrModalBottom" class="requested-pr-close-bottom">Close</button>
      </div>
    </div>
  </div>
</x-layout.app>
