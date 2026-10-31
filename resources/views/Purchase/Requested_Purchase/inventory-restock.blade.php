<x-layout.app
  title="FROMS - Inventory Restock"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Purchase/purchase-module-ui.css',
    'resources/css/Purchase/Requested_Purchase/inventory-restock.css',
    'resources/js/Purchase/Requested_Purchase/inventory-restock.js'
  ]"
>
  <div class="app">
    <x-layout.sidebar department="Purchase" />

    <main class="main purchase-module-page records-page inventory-restock-page">
      <x-layout.topbar
        title="Inventory Restock"
        subtitle="Automatic restock requests from warehouse inventory"
        notification-count="6"
      />

      <section data-ajax-region="summary" class="stats-grid">
        <x-ui.summary-card
          label="Total Requests"
          value="{{ $totalRequests ?? 0 }}"
          small="Active restock inbox"
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
          label="Completed"
          value="{{ $delivered ?? 0 }}"
          small="Available in Purchase History"
          icon="fa-clock-rotate-left"
          color="green"
        />
      </section>

      <section data-ajax-region="records" class="table-card restock-card records-card">
        <div class="section-header">
          <div>
            <span class="purchase-section-eyebrow">INVENTORY REPLENISHMENT</span>
            <h2>Inventory Restock Records</h2>
            <p>Only active automatic warehouse restock requests are shown here. Completed records are available in Purchase History.</p>
          </div>
        </div>

        <x-ui.table-toolbar
          action="{{ route('inventory-restock', [], false) }}"
          class="restock-toolbar records-toolbar"
          data-server-filter="true"
          search-label="Search Inventory Restock Requests"
          search-placeholder="Search restock no., item, or status..."
          :filter-count="(int) (request()->filled('status') && request('status') !== 'All States')"
          filter-title="Filter Restock Requests"
          :sort-options="['created_at' => 'Created Date', 'pr_no' => 'Restock Number', 'status' => 'Status']"
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

        <div class="table-wrap records-table-wrap">
          <table class="restock-table records-table">
            <thead>
              <tr>
                <th>Restock #</th>
                <th>Source</th>
                <th>Item</th>
                <th>Quantity</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              @forelse(($restockRequests ?? []) as $restockRequest)
                @php
                  $itemName = trim($restockRequest->item ?? '—');
                  $quantity = $restockRequest->quantity ?? '1';
                  $unit = '—';

                  if (str_contains(strtolower($itemName), ' - qty:')) {
                    $parts = preg_split('/ - qty:/i', $itemName, 2);
                    $itemName = trim($parts[0] ?? $itemName);
                    $quantityUnitText = trim($parts[1] ?? '');

                    if (preg_match('/^(\d+)\s*(.*)$/', $quantityUnitText, $matches)) {
                      $quantity = trim($matches[1] ?? $quantity);
                      $unit = trim($matches[2] ?? '—') ?: '—';
                    }
                  }

                  if (preg_match('/^(.*?)\s*\((\d+)\s*([^)]*)\)$/', $itemName, $matches)) {
                    $itemName = trim($matches[1] ?? $itemName);
                    $quantity = trim($matches[2] ?? $quantity);
                    $unit = trim($matches[3] ?? '—') ?: '—';
                  }

                  $structuredItems = collect(json_decode((string) ($restockRequest->restock_items ?? ''), true) ?: [])
                    ->filter(fn ($line) => is_array($line) && trim((string) ($line['name'] ?? '')) !== '')
                    ->values();
                  $displayItems = $structuredItems->isNotEmpty()
                    ? $structuredItems->map(fn ($line) => [
                        'name' => trim((string) $line['name']),
                        'quantity' => (int) ($line['quantity'] ?? 0),
                        'unit' => trim((string) ($line['unit'] ?? '')),
                      ])->values()
                    : collect([['name' => $itemName, 'quantity' => (int) $quantity, 'unit' => $unit === '—' ? '' : $unit]]);
                  $firstItem = $displayItems->first();
                  $itemName = $firstItem['name'];
                  $quantity = $firstItem['quantity'];
                  $unit = $firstItem['unit'] ?: '—';
                  $extraCount = max(0, $displayItems->count() - 1);
                  $quantityDisplay = $extraCount ? $displayItems->count().' items' : trim($quantity.' '.($unit !== '—' ? $unit : ''));
                  $createdAt = $restockRequest->created_at;

                @endphp

                <tr>
                  <td>
                    <strong class="restock-no">{{ $restockRequest->display_pr_no }}</strong>
                  </td>
                  <td><span class="source-badge"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Auto Restock</span></td>
                  <td>
                    <div class="restock-item-summary">
                      <span class="restock-item-name">{{ $itemName }}</span>
                      @if($extraCount)
                        <details class="restock-item-more">
                          <summary>+{{ $extraCount }} more <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                          <div class="restock-item-popover">
                            @foreach($displayItems->skip(1) as $line)
                              <div><span>{{ $line['name'] }}</span><strong>{{ $line['quantity'] }} {{ $line['unit'] }}</strong></div>
                            @endforeach
                          </div>
                        </details>
                      @endif
                    </div>
                  </td>
                  <td class="center-text"><span class="restock-quantity">{{ $quantityDisplay }}</span></td>
                  <td class="center-text restock-status-cell">
                    <x-ui.status-badge
                      :status="$restockRequest->status ?? '—'"
                      type="purchase"
                    />
                  </td>
                  <td>
                    @if($createdAt)
                      <div class="restock-created-date"><strong>{{ $createdAt->format('M d, Y') }}</strong><small>{{ $createdAt->format('l') }}</small></div>
                    @else
                      —
                    @endif
                  </td>
                  <td class="center-text">
                    <div class="actions record-actions">
                      <button
                        type="button"
                        class="restock-review-btn open-restock-view-modal"
                        title="Review Restocking Request"
                        data-restock-no="{{ $restockRequest->display_pr_no }}"
                        data-source-type="{{ $restockRequest->source_type ?? 'Auto Restock' }}"
                        data-item="{{ $itemName }}"
                        data-quantity="{{ $quantity }}"
                        data-unit="{{ $unit }}"
                        data-status="{{ $restockRequest->status ?? '—' }}"
                        data-created="{{ $restockRequest->created_at ? $restockRequest->created_at->format('m/d/y | h:i A') : '—' }}"
                        data-remarks="{{ $restockRequest->remarks ?? 'No remarks' }}"
                        data-items="{{ $displayItems->toJson() }}"
                        data-create-po-url="{{ ($restockRequest->status ?? '') === 'For Purchase' ? route('purchase-orders', ['create_from_pr' => $restockRequest->id]) : '' }}"
                      >
                        <i class="fa-solid fa-eye" aria-hidden="true"></i> Review
                      </button>
                    </div>
                  </td>
                </tr>
              @empty
                <x-ui.empty-row
                  colspan="7"
                  message="No active inventory restock requests found."
                />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$restockRequests" />
      </section>
    </main>
  </div>

  <div id="restockViewModal" class="modal-overlay restock-view-overlay">
    <div class="restock-view-modal">
      <div class="restock-modal-header">
        <div>
          <h2>Inventory Restock Details</h2>
          <h3>Restock Information</h3>
          <p>This is a read-only view of the selected automatic restock request.</p>
        </div>
        <button type="button" id="closeRestockViewModal" class="restock-close-btn">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <div class="restock-form-grid">
        <div class="restock-field">
          <label>Restock No.</label>
          <input id="viewRestockNo" type="text" value="—" readonly>
        </div>
        <div class="restock-field">
          <label>Source</label>
          <input id="viewRestockSource" type="text" value="Auto Restock" readonly>
        </div>
        <div class="restock-field">
          <label>Status</label>
          <input id="viewRestockStatus" type="text" value="—" readonly>
        </div>
        <div class="restock-field">
          <label>Date Created</label>
          <input id="viewRestockCreated" type="text" value="—" readonly>
        </div>

        <div class="restock-field restock-full">
          <label>Requested Item / Part</label>
          <div class="restock-breakdown-box">
            <div class="restock-breakdown-head">
              <span>Part Name</span>
              <span>Quantity</span>
              <span>Unit</span>
            </div>
            <div class="restock-breakdown-list" id="viewRestockItems">
              <span id="viewRestockItem">—</span>
              <span id="viewRestockQuantity">—</span>
              <span id="viewRestockUnit">—</span>
            </div>
          </div>
        </div>

        <div class="restock-field restock-full">
          <label>Remarks</label>
          <textarea id="viewRestockRemarks" rows="3" readonly>No remarks</textarea>
        </div>
      </div>

      <div class="restock-modal-footer">
        <button type="button" id="closeRestockViewModalBottom" class="restock-close-bottom">Close</button>
        <a id="restockReviewCreatePo" class="restock-review-create-po" href="#" hidden><i class="fa-solid fa-cart-plus" aria-hidden="true"></i> Create PO</a>
      </div>
    </div>
  </div>
</x-layout.app>
