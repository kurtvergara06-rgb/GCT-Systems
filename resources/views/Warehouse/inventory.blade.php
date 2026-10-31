<x-layout.app
  title="FROMS - Warehouse Inventory"
  :assets="[
    'resources/css/Warehouse/inventory.css',
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/form-components.css',
    'resources/js/Warehouse/inventory.js'
  ]"
>
  @php
    $canEditWarehouse = auth()->user()?->hasSystemPermission('warehouse', 'edit') ?? false;
  @endphp

  <div class="app">

  <x-layout.sidebar department="Warehouse" />

    <main class="main warehouse-inventory-page records-page">

      <x-layout.topbar
        title="Warehouse Inventory"
        subtitle="Monitor vehicle parts stock levels, threshold alerts, and restocking needs"
      />

      {{-- SUMMARY CARDS --}}
          <section data-ajax-region="summary" class="stats-grid inventory-stats">

            <x-ui.summary-card
              label="Total Items in Stock"
              value="{{ number_format($totalItemsInStock) }}"
              small="Across all categories"
              icon="fa-boxes-stacked"
              color="green"
            />

            <x-ui.summary-card
              label="Low Stock Alerts"
              value="{{ $lowStockAlerts }}"
              small="Below reorder level"
              icon="fa-bell"
              color="yellow"
            />

            <x-ui.summary-card
              label="Critical Items"
              value="{{ $criticalItems }}"
              small="Need immediate restock"
              icon="fa-triangle-exclamation"
              color="red"
            />

            <x-ui.summary-card
              label="Forecasted Stockouts"
              value="{{ $forecastedStockouts }}"
              small="Items at risk"
              icon="fa-chart-line"
              color="blue"
            />

          </section>

      {{-- INVENTORY TABLE --}}
      <section data-ajax-region="records" class="table-card inventory-card">

        <div class="section-header">
          <div>
            <h2>Inventory Stock Records</h2>
            <p>Track warehouse stock levels, item thresholds, and item availability</p>
          </div>

          @if($canEditWarehouse)
            <div class="inventory-header-actions" aria-label="Inventory actions">
              <button type="button" class="primary-btn" id="openAddModal"><i class="fa-solid fa-plus"></i> Add Item</button>
            </div>
          @endif
        </div>

        <div class="inventory-list-controls">
          <x-ui.table-toolbar
            action="{{ route('inventory', [], false) }}"
            data-server-filter="true"
            data-lazy-pagination="true"
            search-label="Search Inventory"
            search-placeholder="Search item, code, supplier, or location..."
            :filter-count="(int) (request()->filled('category') && request('category') !== 'All Categories')"
            filter-title="Filter Inventory"
            :sort-options="['item_name' => 'Part Name', 'item_code' => 'Item Code', 'category' => 'Category', 'quantity' => 'On Hand', 'reorder_level' => 'Reorder Level']"
            default-sort="item_name"
          >
            <x-slot:filters>
              <x-ui.table-toolbar-field label="Category" name="category" default="All Categories">
                <option value="All Categories">All Categories</option>
                @foreach($categories as $category)
                  <option value="{{ $category }}" @selected(request('category', 'All Categories') === $category)>{{ $category }}</option>
                @endforeach
              </x-ui.table-toolbar-field>
            </x-slot:filters>
          </x-ui.table-toolbar>
        </div>

        <div class="table-wrap">
          <table class="inventory-table">
            <thead>
              <tr>
                <th>Item / Part</th>
                <th>Category</th>
                <th>On Hand</th>
                <th>Reserved</th>
                <th>Available</th>
                <th>Reorder Point</th>
                <th>Safety Stock</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              @forelse($inventoryItems as $item)
                @php
                  $status = $item->stock_status;
                  $reservedQuantity = max(0, (int) ($reservedByItem[$item->id] ?? 0));
                  $onHandQuantity = (int) ($item->quantity_available ?? $item->on_hand ?? 0);
                  $availableQuantity = max(0, $onHandQuantity - $reservedQuantity);

                  $rowClass = match($status) {
                    'Critical' => 'danger-row',
                    'Low Stock' => 'warning-row',
                    default => ''
                  };
                @endphp

                <tr class="{{ $rowClass }}">
                  <td class="inventory-item-cell">
                    <div class="inventory-item-identification">
                      <strong class="inventory-item-name">{{ $item->item_name }}</strong>
                      <span class="inventory-item-code">{{ $item->item_code }}</span>
                      <small class="inventory-item-meta" title="Supplier and storage details">Supplier: {{ $item->supplier ?: '—' }} <span aria-hidden="true">·</span> Location: {{ $item->storage_location ?: '—' }}</small>
                    </div>
                  </td>
                  <td class="inventory-category-cell">{{ $item->category }}</td>
                  <td class="inventory-stock-cell"><strong>{{ $onHandQuantity }} <small>{{ $item->unit_of_measurement }}</small></strong></td>
                  <td class="inventory-reserved-cell"><strong>{{ $reservedQuantity }} <small>{{ $item->unit_of_measurement }}</small></strong></td>
                  <td class="inventory-available-cell"><strong>{{ $availableQuantity }} <small>{{ $item->unit_of_measurement }}</small></strong></td>
                  <td><strong>{{ $item->reorder_level }}</strong></td>
                  <td class="inventory-safety-stock-cell"><span title="Safety Stock calculation has not yet been configured">Not set</span></td>
                  <td>
                    <div class="warehouse-inventory-status-stack">
                      <x-ui.status-badge
                        :status="$status"
                        type="inventory"
                      />
                    </div>
                  </td>
                  <td>
                    <div class="actions">

                      <button
                        type="button"
                        class="action-btn openMovementHistory"
                        title="Movement History"
                        data-item-id="{{ $item->id }}"
                        data-url="{{ route('inventory.movements', $item) }}"
                      >
                        <i class="fa-solid fa-clock-rotate-left"></i>
                      </button>

                      @if($canEditWarehouse)
                        <button
                          type="button"
                          class="action-btn edit openEditModal"
                          title="Edit Item"
                          data-action="/inventory/{{ $item->id }}"
                          data-code="{{ $item->item_code }}"
                          data-name="{{ $item->item_name }}"
                          data-category="{{ $item->category }}"
                          data-quantity="{{ $item->on_hand }}"
                          data-unit="{{ $item->unit_of_measurement }}"
                          data-reorder="{{ $item->reorder_level }}"
                          data-supplier="{{ $item->supplier }}"
                          data-location="{{ $item->storage_location }}"
                      >
                          <i class="fa-solid fa-pen-to-square"></i>
                        </button>

                        <form
                          action="/inventory/{{ $item->id }}"
                          method="POST"
                          data-confirm-form
                          data-confirm-title="Delete Inventory Item?"
                          data-confirm-message="Are you sure you want to delete {{ $item->item_name }}? This action cannot be undone."
                          data-confirm-button="Yes, Delete"
                          data-confirm-type="delete"
                        >
                          @csrf
                          @method('DELETE')

                          <button
                            type="submit"
                            class="action-btn delete"
                            title="Delete Item"
                            data-no-loading
                          >
                            <i class="fa-solid fa-trash"></i>
                          </button>
                        </form>
                      @endif

                    </div>
                  </td>
                </tr>
              @empty
                <x-ui.empty-row
                  colspan="9"
                  message="No inventory items found."
                />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer
          :items="$inventoryItems"
          data-lazy-pagination="true"
          data-server-filter="true"
        />

  @if($canEditWarehouse)
  {{-- ADD MODAL --}}
  <div class="modal-overlay inventory-form-modal-overlay" id="addModal">
    <div class="modal-box wide-modal">

      <div class="modal-header">
        <h2>Add Inventory Item</h2>
        <button type="button" class="close-btn closeModal">&times;</button>
      </div>

      <form
        action="/inventory"
        method="POST"
        data-confirm-form
        data-confirm-title="Add Inventory Item?"
        data-confirm-message="Are you sure you want to add this inventory item?"
        data-confirm-button="Yes, Add Item"
        data-confirm-type="create"
      >
        @csrf

        <section class="inventory-section-basic">
          <h3>01 — Item Information</h3>
          <div class="form-grid">
            <div class="form-group">
              <label>Item Code</label>
              <input type="text" name="item_code" required placeholder="Example: PART-001">
            </div>
            <div class="form-group">
              <label>Parts Name</label>
              <input type="text" name="item_name" required placeholder="Example: Air Filter">
            </div>
            <div class="form-group">
              <label>Category</label>
              <input type="text" name="category" required placeholder="Example: Engine Parts">
            </div>
          </div>
        </section>

        <section class="inventory-section-details">
          <h3>02 — Stock Information</h3>
          <div class="form-grid">
            <div class="form-group">
              <label>Quantity Available</label>
              <input type="number" name="on_hand" min="0" required>
            </div>
            <div class="form-group">
              <label>Unit of Measurement</label>
              <input type="text" name="unit_of_measurement" required placeholder="Example: pcs, liter, box">
            </div>
            <div class="form-group">
              <label>Reorder Level</label>
              <input type="number" name="reorder_level" min="0" required>
            </div>
          </div>
        </section>

        <section class="inventory-section-additional">
          <h3>03 — Supply &amp; Storage</h3>
          <div class="form-grid">
            <div class="form-group">
              <label>Supplier</label>
              <input type="text" name="supplier" placeholder="Supplier name">
            </div>
            <div class="form-group">
              <label>Storage Location</label>
              <input type="text" name="storage_location" placeholder="Example: A-02">
            </div>
          </div>
        </section>

        <div class="modal-actions full-width">
          <button type="button" class="secondary-btn cancel-btn closeModal">Cancel</button>
          <button type="submit" class="primary-btn">Save Item</button>
        </div>

      </form>

    </div>
  </div>

  {{-- EDIT MODAL --}}
  <div class="modal-overlay inventory-form-modal-overlay" id="editModal">
    <div class="modal-box wide-modal">

      <div class="modal-header">
        <h2>Edit Inventory Item</h2>
        <button type="button" class="close-btn closeModal">&times;</button>
      </div>

      <form
        id="editForm"
        method="POST"
        data-confirm-form
        data-confirm-title="Update Inventory Item?"
        data-confirm-message="Are you sure you want to update this inventory item?"
        data-confirm-button="Yes, Update Item"
        data-confirm-type="update"
      >
        @csrf
        @method('PUT')

        <section class="inventory-section-basic">
          <h3>01 — Item Information</h3>
          <div class="form-grid">
            <div class="form-group">
              <label>Item Code</label>
              <input type="text" name="item_code" id="edit_item_code" required>
            </div>
            <div class="form-group">
              <label>Parts Name</label>
              <input type="text" name="item_name" id="edit_item_name" required>
            </div>
            <div class="form-group">
              <label>Category</label>
              <input type="text" name="category" id="edit_category" required>
            </div>
          </div>
        </section>

        <section class="inventory-section-details">
          <h3>02 — Stock Information</h3>
          <div class="form-grid">
            <div class="form-group">
              <label>Quantity Available</label>
              <input type="number" name="on_hand" id="edit_quantity" min="0" required>
            </div>
            <div class="form-group">
              <label>Unit of Measurement</label>
              <input type="text" name="unit_of_measurement" id="edit_unit" required>
            </div>
            <div class="form-group">
              <label>Reorder Level</label>
              <input type="number" name="reorder_level" id="edit_reorder" min="0" required>
            </div>
            <div class="form-group full-width">
              <label for="edit_adjustment_reason">Stock adjustment reason <span id="editAdjustmentRequiredMark" hidden>*</span></label>
              <textarea name="adjustment_reason" id="edit_adjustment_reason" maxlength="500" placeholder="Explain why the on-hand quantity is changing"></textarea>
              <small id="editAdjustmentHelp">No reason is required when stock quantity remains unchanged.</small>
            </div>
          </div>
        </section>

        <section class="inventory-section-additional">
          <h3>03 — Supply &amp; Storage</h3>
          <div class="form-grid">
            <div class="form-group">
              <label>Supplier</label>
              <input type="text" name="supplier" id="edit_supplier">
            </div>
            <div class="form-group">
              <label>Storage Location</label>
              <input type="text" name="storage_location" id="edit_location">
            </div>
          </div>
        </section>

        <div class="modal-actions full-width">
          <button type="button" class="secondary-btn cancel-btn closeModal">Cancel</button>
          <button type="submit" class="primary-btn">Update Item</button>
        </div>

      </form>

    </div>
  </div>
  @endif

  {{-- VIEW MODAL --}}
  <div class="modal-overlay inventory-details-modal inventory-form-modal-overlay" id="viewModal">
    <div class="modal-box wide-modal">

      <div class="modal-header">
        <h2>Inventory Item Details</h2>
        <button type="button" class="close-btn closeModal">&times;</button>
      </div>

      <div class="inventory-details-scrollable">
        <section class="inventory-details-section inventory-section-basic">
          <h3>01 — Item Information</h3>
          <div class="details-grid">
            <div class="detail-item"><span>Item Code</span><strong id="view_code">—</strong></div>
            <div class="detail-item"><span>Parts Name</span><strong id="view_name">—</strong></div>
            <div class="detail-item"><span>Category</span><strong id="view_category">—</strong></div>
          </div>
        </section>

        <section class="inventory-details-section inventory-section-details">
          <h3>02 — Stock Information</h3>
          <div class="details-grid">
            <div class="detail-item"><span>Quantity Available</span><strong id="view_quantity">—</strong></div>
            <div class="detail-item"><span>Unit</span><strong id="view_unit">—</strong></div>
            <div class="detail-item"><span>Reorder Level</span><strong id="view_reorder">—</strong></div>
            <div class="detail-item"><span>Status</span><strong id="view_status">—</strong></div>
          </div>
        </section>

        <section class="inventory-details-section inventory-section-additional">
          <h3>03 — Supply &amp; Storage</h3>
          <div class="details-grid">
            <div class="detail-item"><span>Supplier</span><strong id="view_supplier">—</strong></div>
            <div class="detail-item"><span>Storage Location</span><strong id="view_location">—</strong></div>
            <div class="detail-item"><span>Last Updated</span><strong id="view_updated">—</strong></div>
          </div>
        </section>
      </div>

      <div class="modal-actions full-width inventory-details-footer">
        <button type="button" class="secondary-btn cancel-btn closeModal">Close</button>
      </div>

    </div>
  </div>

  {{-- MOVEMENT HISTORY MODAL --}}
  <div class="modal-overlay" id="movementHistoryModal" aria-hidden="true">
    <div class="modal-box wide-modal" role="dialog" aria-modal="true" aria-labelledby="movementHistoryModalTitle">
      <div class="modal-header">
        <h2 id="movementHistoryModalTitle">
          <span class="inventory-movement-title-icon"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span>
          Movement History
        </h2>
        <button type="button" class="close-btn closeModal" aria-label="Close movement history">&times;</button>
      </div>
      <div id="movementHistoryContent" data-movement-history-content></div>
      <div class="inventory-movement-modal__footer">
        <button type="button" class="secondary-btn closeModal">Close</button>
      </div>
    </div>
  </div>

  @if($canEditWarehouse)
  {{-- IMPORT MODAL --}}
  <div class="modal-overlay" id="importModal">
    <div class="modal-box">

      <div class="modal-header">
        <h2>Import Inventory Data</h2>
        <button type="button" class="close-btn closeModal">&times;</button>
      </div>

      <form
        action="/inventory/import"
        method="POST"
        enctype="multipart/form-data"
        data-confirm-form
        data-confirm-title="Import Inventory Data?"
        data-confirm-message="Are you sure you want to import this inventory data file?"
        data-confirm-button="Yes, Import Data"
        data-confirm-type="warning"
      >
        @csrf

        <div class="form-group full-width">
          <label>Upload CSV File</label>
          <input
            type="file"
            name="inventory_file"
            id="inventoryFile"
            accept=".csv,.txt"
            required
          >

          <small>
            CSV format: item_code, parts_name, category, on_hand, unit, reorder_level, supplier, storage_location
          </small>
        </div>

        <div class="modal-actions full-width">
          <button type="button" class="secondary-btn cancel-btn closeModal">Cancel</button>
          <button type="submit" class="primary-btn">Import Data</button>
        </div>

      </form>

    </div>
  </div>
  @endif

  @if($canEditWarehouse)
{{-- ISSUE STOCK MODAL --}}
  <div class="modal-overlay" id="issueModal">
    <div class="modal-box wide-modal">

      <div class="modal-header">
        <h2>Issue Stock</h2>
        <button type="button" class="close-btn closeModal">&times;</button>
      </div>

      <form
        id="issueForm"
        action="{{ route('inventory.issue') }}"
        method="POST"
        data-confirm-form
        data-confirm-title="Issue Stock?"
        data-confirm-message="Confirm the stock out transaction. On-hand inventory will be reduced and a Stock Out movement will be recorded."
        data-confirm-button="Yes, Issue Stock"
        data-confirm-type="warning"
      >
        @csrf

        <div class="issue-stock-preview">
          <div>
            <p class="preview-label">Item</p>
            <p class="preview-value" id="issue_preview_name">—</p>
          </div>
          <div class="preview-quantity">
            <p class="preview-label">On Hand</p>
            <p class="preview-value" id="issue_preview_quantity">—</p>
            <small id="issue_preview_unit">—</small>
          </div>
        </div>

        <div class="form-grid">

          <div class="form-group full-width">
            <label>Inventory Item</label>
            <select name="inventory_item_id" id="issue_item" required>
              <option value="" disabled selected>Select an item...</option>

              @foreach($issueItems as $item)
                <option
                  value="{{ $item->id }}"
                  data-quantity="{{ $item->quantity_available }}"
                  data-unit="{{ $item->unit_of_measurement }}"
                >
                  {{ $item->item_name }}
                  @if($item->item_code) ({{ $item->item_code }}) @endif —
                  on hand: {{ $item->quantity_available }} {{ $item->unit_of_measurement }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="form-group">
            <label>Quantity to Issue</label>
            <input type="number" name="quantity" id="issue_quantity" min="1" required>
          </div>

          <div class="form-group">
            <label>Issued To</label>
            <input type="text" name="issued_to" required placeholder="e.g., Maintenance">
          </div>

          <div class="form-group full-width">
            <label>Purpose</label>
            <input type="text" name="purpose" required placeholder="e.g., Preventive Maintenance">
          </div>

          <div class="form-group full-width">
            <label>Reference No. (optional)</label>
            <input type="text" name="reference_no" placeholder="e.g., JO-2026-015">
            <small>An Issue No. is generated automatically (e.g., ISS-2026-0001).</small>
          </div>

        </div>

        <div class="modal-actions full-width">
          <button type="button" class="secondary-btn cancel-btn closeModal">Cancel</button>
          <button type="submit" class="primary-btn" id="issueSubmitBtn">
            <i class="fa-solid fa-arrow-up-from-bracket"></i>
            Confirm Issue
          </button>
        </div>

      </form>

    </div>
  </div>
  @endif

</x-layout.app>
