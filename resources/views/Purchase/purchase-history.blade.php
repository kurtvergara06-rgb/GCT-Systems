<x-layout.app
  title="FROMS - Purchase History"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Purchase/Requested_Purchase/maintenance-requests.css',
    'resources/css/Purchase/purchase-module-ui.css',
    'resources/css/Purchase/purchase-history.css',
    'resources/js/Purchase/Requested_Purchase/maintenance-requests.js'
  ]"
>
  <div class="app">
    <x-layout.sidebar department="Purchase" />

    <main class="main purchase-history-page purchase-module-page records-page">
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

      <section data-ajax-region="records" class="table-card requested-purchase-card records-card">
        <div class="section-header purchase-record-header">
          <div>
            <span class="purchase-section-eyebrow">AUDIT ARCHIVE</span>
            <h2>Completed Purchase Records</h2>
            <p>Completed requests are kept here for reference and audit history.</p>
          </div>
          <span class="purchase-section-count">{{ $historyRecords->total() }} records</span>
        </div>

        <div class="purchase-list-controls purchase-list-controls--toolbar-only">
          <x-ui.table-toolbar action="{{ route('maintenance-requests', [], false) }}" class="records-toolbar" data-server-filter="true" search-label="Search Purchase History" search-placeholder="Search request, JO, bus, item, source, or status..." :filter-count="(int) (request()->filled('source') && request('source') !== 'All Sources') + (int) (request()->filled('status') && request('status') !== 'All Statuses')" filter-title="Filter Purchase History" :sort-options="['updated_at' => 'Completed Date', 'pr_no' => 'Request Number', 'status' => 'Status']" default-sort="updated_at" default-direction="desc">
            <x-slot:hidden><input type="hidden" name="view" value="history"></x-slot:hidden>
            <x-slot:filters>
              <x-ui.table-toolbar-field label="Source" name="source" default="All Sources">@foreach(['All Sources', 'Maintenance Request', 'Inventory Restock'] as $source)<option value="{{ $source }}" @selected(request('source', 'All Sources') === $source)>{{ $source }}</option>@endforeach</x-ui.table-toolbar-field>
              <x-ui.table-toolbar-field label="Status" name="status" default="All Statuses">@foreach(['All Statuses', 'Delivered', 'Picked Up', 'Issued'] as $status)<option value="{{ $status }}" @selected(request('status', 'All Statuses') === $status)>{{ $status }}</option>@endforeach</x-ui.table-toolbar-field>
            </x-slot:filters>
          </x-ui.table-toolbar>
        </div>

        <div class="table-wrap records-table-wrap purchase-history-table-wrap">
          <table class="requested-purchase-table records-table purchase-history-reference-table">
            <thead>
              <tr>
                <th scope="col">PR ID</th>
                <th scope="col">Source</th>
                <th scope="col">Bus ID</th>
                <th scope="col">Requested Parts</th>
                <th scope="col">QTY</th>
                <th scope="col">Total Items</th>
                <th scope="col">PR Status</th>
                <th scope="col">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($historyRecords as $history)
                @php
                  $parts = $history->parts_breakdown ?? [];
                  $itemName = $history->first_item_display ?? $history->item ?? '—';
                  $quantity = $history->first_quantity_display ?? $history->quantity ?? '—';
                  $isRestock = $history->source_type === 'Auto Restock';
                @endphp
                <tr>
                  <td><div class="requested-pr-reference"><strong>{{ $history->display_pr_no }}</strong></div></td>
                  <td><span class="purchase-history-source">{{ $isRestock ? 'Inventory Restock' : 'Maintenance Request' }}</span></td>
                  <td class="requested-pr-bus"><strong>{{ ! $isRestock && $history->bus_no && $history->bus_no !== 'RESTOCK' ? $history->bus_no : '—' }}</strong></td>
                  <td>
                    <div class="requested-pr-part">
                      <strong>{{ preg_replace('/\\s*\\[item:\\d+\\]/i', '', $itemName) }}</strong>
                      @if(count($parts) > 1)
                        <details class="requested-pr-more">
                          <summary>+{{ count($parts) - 1 }} more parts</summary>
                          <div class="requested-pr-more-panel">
                            @foreach(array_slice($parts, 1) as $part)
                              <div>{{ preg_replace('/\\s*\\[item:\\d+\\]/i', '', $part['name'] ?? $part['item'] ?? 'Part') }}</div>
                            @endforeach
                          </div>
                        </details>
                      @endif
                    </div>
                  </td>
                  <td class="requested-pr-part-qty">
                    <strong>{{ $quantity }}</strong>
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
                  <td class="requested-pr-quantity">{{ count($parts) ?: 1 }} {{ (count($parts) ?: 1) === 1 ? 'Part' : 'Parts' }}</td>
                  <td class="requested-pr-status"><x-ui.status-badge :status="$history->status ?? '—'" type="purchase" /></td>
                  <td class="requested-pr-actions-cell">
                    <div class="record-actions">
                      <button type="button" class="open-view-requested-pr-modal purchase-history-view-btn"
                        title="View Purchase History" aria-label="View {{ $history->display_pr_no }}"
                        data-pr-no="{{ $history->display_pr_no }}"
                        data-job-order-no="{{ ! $isRestock && $history->job_order_no !== 'RESTOCK' ? $history->job_order_no : '—' }}"
                        data-bus-no="{{ ! $isRestock && $history->bus_no !== 'RESTOCK' ? $history->bus_no : '—' }}"
                        data-created="{{ $history->updated_at ? $history->updated_at->format('m/d/y | h:i A') : '—' }}"
                        data-remarks="{{ $history->remarks ?? 'No remarks' }}"
                        data-item="{{ $history->item }}"
                        data-quantity="{{ $quantity }}"
                        data-parts='@json($parts)'>
                        <i class="fa-solid fa-eye" aria-hidden="true"></i><span>View</span>
                      </button>
                    </div>
                  </td>
                </tr>
              @empty
                <x-ui.empty-row colspan="8" icon="fa-clock-rotate-left"
                  message="No Purchase History Found"
                  description="Completed purchase requests will appear here." />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$historyRecords" data-lazy-pagination="true" />
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
          <label>Bus ID</label>
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
