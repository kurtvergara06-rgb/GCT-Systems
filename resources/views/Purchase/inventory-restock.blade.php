<x-layout.app
  title="FROMS - Inventory Restock"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Purchase/inventory-restock.css',
    'resources/js/Purchase/inventory-restock.js'
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
      department="Purchase"
      subtitle="Department Module"
      icon="fa-cart-shopping"
      user-name="P. Admin"
      user-role="Purchase Admin"
      :items="[
        [
          'label' => 'Purchase Orders',
          'route' => 'purchase-orders',
          'icon' => 'fa-file-invoice'
        ],
        [
          'label' => 'Requested Purchase',
          'icon' => 'fa-clipboard-list',
          'children' => [
            [
              'label' => 'Maintenance Requests',
              'route' => 'maintenance-requests',
              'icon' => 'fa-screwdriver-wrench'
            ],
            [
              'label' => 'Inventory Restock',
              'route' => 'inventory-restock',
              'icon' => 'fa-boxes-stacked'
            ],
          ],
        ],
        [
          'label' => 'Scheduled Purchase',
          'route' => 'scheduled-purchase',
          'icon' => 'fa-calendar-check'
        ],
      ]"
    />

    <main class="main">

      <x-layout.topbar
        title="Inventory Restock"
        subtitle="Automatic restock requests from warehouse inventory"
        notification-count="6"
      />

      <section class="stats-grid">

        <x-ui.summary-card
          label="Total Requests"
          value="{{ $totalRequests ?? 0 }}"
          small="Auto restock inbox"
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
          small="Ready for warehouse receive"
          icon="fa-box"
          color="green"
        />

      </section>

      <section class="table-card restock-card">

        <div class="section-header">
          <div>
            <h2>Inventory Restock Records</h2>
            <p>Only automatic warehouse restock requests are shown here.</p>
          </div>
        </div>

        <form action="{{ route('inventory-restock') }}" method="GET" class="restock-toolbar">

          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>

            <input
              type="text"
              name="search"
              value="{{ request('search') }}"
              placeholder="Search restock no., item, status..."
            >
          </div>

          <div class="filter-group">
            <label for="restockStatusFilter">Status</label>

            <select
              name="status"
              id="restockStatusFilter"
              onchange="this.form.submit()"
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
          <table class="restock-table">
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
                  $statusClass = strtolower(str_replace([' ', '/'], ['-', '-'], $restockRequest->status ?? ''));

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

                  $quantityDisplay = trim($quantity . ' ' . ($unit !== '—' ? $unit : ''));
                @endphp

                <tr>
                  <td>
                    <strong class="restock-no">
                      {{ $restockRequest->pr_no ?? '—' }}
                    </strong>
                  </td>

                  <td>
                    <span class="source-badge">
                      Auto Restock
                    </span>
                  </td>

                  <td>{{ $itemName }}</td>

                  <td class="center-text">
                    {{ $quantityDisplay }}
                  </td>

                  <td class="center-text">
                    <span class="restock-status-badge {{ $statusClass }}">
                      {{ $restockRequest->status ?? '—' }}
                    </span>
                  </td>

                  <td>
                    {{ $restockRequest->created_at ? $restockRequest->created_at->format('m/d/y | h:i A') : '—' }}
                  </td>

                  <td class="center-text">
                    <div class="actions">

                      <button
                        type="button"
                        class="action-btn view open-restock-view-modal"
                        title="View Details"
                        data-restock-no="{{ $restockRequest->pr_no ?? '—' }}"
                        data-source-type="{{ $restockRequest->source_type ?? 'Auto Restock' }}"
                        data-item="{{ $itemName }}"
                        data-quantity="{{ $quantity }}"
                        data-unit="{{ $unit }}"
                        data-status="{{ $restockRequest->status ?? '—' }}"
                        data-created="{{ $restockRequest->created_at ? $restockRequest->created_at->format('m/d/y | h:i A') : '—' }}"
                        data-remarks="{{ $restockRequest->remarks ?? 'No remarks' }}"
                      >
                        <i class="fa-solid fa-eye"></i>
                      </button>

                      @if(($restockRequest->status ?? '') === 'For Purchase')
                        <a
                          href="{{ route('purchase-orders', ['create_from_pr' => $restockRequest->id]) }}"
                          class="action-btn create-po"
                          title="Create Purchase Order"
                        >
                          <i class="fa-solid fa-cart-plus"></i>
                        </a>
                      @endif

                    </div>
                  </td>
                </tr>
              @empty
                <x-ui.empty-row
                  colspan="7"
                  message="No inventory restock requests found."
                />
              @endforelse
            </tbody>
          </table>
        </div>

        @if(isset($restockRequests))
          <x-ui.table-footer :items="$restockRequests" />
        @endif

      </section>

    </main>
  </div>

  {{-- VIEW MODAL --}}
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

            <div class="restock-breakdown-row">
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
        <button type="button" id="closeRestockViewModalBottom" class="restock-close-bottom">
          Close
        </button>
      </div>

    </div>
  </div>

</x-layout.app>
