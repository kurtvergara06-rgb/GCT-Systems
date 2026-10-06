<x-layout.app
  title="FROMS - Warehouse Inventory"
  :assets="[
    'resources/css/Warehouse/inventory.css',
    'resources/css/Warehouse/stock-movements.css',
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

    <main class="main warehouse-inventory-page">

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
        </div>

        <form action="{{ route('inventory') }}" method="GET" class="toolbar inventory-toolbar" data-server-filter="true">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input
              type="text"
              name="search"
              value="{{ request('search') }}"
              autocomplete="off"
              placeholder="Search by item, item code, supplier, or location..."
              aria-label="Search inventory records"
            >
          </div>

          <div class="filter-group">
            <select name="category" aria-label="Filter inventory by category">
              <option value="All Categories" @selected(request('category', 'All Categories') === 'All Categories')>All Categories</option>

              @foreach($categories as $category)
                <option value="{{ $category }}" @selected(request('category') === $category)>
                  {{ $category }}
                </option>
              @endforeach
            </select>
          </div>

          @if($canEditWarehouse)
            <button type="button" class="primary-btn" id="openAddModal">
              <i class="fa-solid fa-plus"></i>
              Add Item
            </button>
          @endif
        </form>

        <div class="table-wrap">
          <table class="inventory-table">
            <thead>
              <tr>
                <th>Item</th>
                <th>Category</th>
                <th>Stock</th>
                <th>Reorder Level</th>
                <th>Status</th>
                <th>Supplier</th>
                <th>Location</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              @forelse($inventoryItems as $item)
                @php
                  $status = $item->stock_status;

                  $rowClass = match($status) {
                    'Critical' => 'danger-row',
                    'Low Stock' => 'warning-row',
                    default => ''
                  };
                @endphp

                <tr class="{{ $rowClass }}">
                  <td>
                    <div class="inventory-item-cell">
                      <strong>{{ $item->item_name }}</strong>
                      <div class="inventory-item-meta">
                        <small>{{ $item->item_code }}</small>
                      </div>
                    </div>
                  </td>
                  <td>{{ $item->category }}</td>
                  <td>
                    <span class="inventory-stock-cell">
                      <strong>{{ $item->quantity_available }}</strong>
                      <small>{{ $item->unit_of_measurement }}</small>
                    </span>
                  </td>
                  <td><strong>{{ $item->reorder_level }}</strong></td>
                  <td>
                    <x-ui.status-badge
                      :status="$status"
                      type="inventory"
                    />
                  </td>
                  <td class="inventory-supplier" title="{{ $item->supplier ?? 'No supplier recorded' }}">
                    {{ $item->supplier ?? '—' }}
                  </td>
                  <td>{{ $item->storage_location ?? '—' }}</td>

                  <td>
                    <div class="actions">

                      <button
                        type="button"
                        class="action-btn openMovementHistory"
                        title="Movement History"
                        aria-haspopup="dialog"
                        aria-controls="movementHistoryModal"
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
                        data-ajax-submit="true"
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
                  colspan="8"
                  message="No inventory items found."
                />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer
          :items="$inventoryItems"
          data-lazy-pagination="true"
        />
      </section>
    </main>
  </div>

  {{-- MOVEMENT HISTORY MODAL --}}
  <div
    class="modal-overlay"
    id="movementHistoryModal"
    aria-hidden="true"
  >
    <div
      class="modal-box inventory-movement-modal stock-movement-page"
      role="dialog"
      aria-modal="true"
      aria-labelledby="movementHistoryModalTitle"
    >
      <div class="modal-header inventory-movement-modal__header">
        <h2 id="movementHistoryModalTitle">Movement History</h2>
        <button type="button" class="close-btn closeModal" aria-label="Close movement history">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <div id="movementHistoryContent" class="inventory-movement-modal__body" aria-live="polite">
        <div class="movement-history-loading">
          <i class="fa-solid fa-clock-rotate-left"></i>
          <span>Select an inventory item to view its movement history.</span>
        </div>
      </div>
    </div>
  </div>

  @if($canEditWarehouse)
    {{-- ADD INVENTORY ITEM MODAL --}}
    <div class="modal-overlay ui-form-overlay inventory-form-modal-overlay" id="addModal">
      <div class="ui-form-modal ui-form-modal-xl inventory-form-modal" role="dialog" aria-modal="true" aria-labelledby="addInventoryModalTitle">
        <div class="ui-form-modal-header inventory-form-modal-header">
          <div class="ui-form-title-wrap">
            <div class="ui-form-title-icon">
              <i class="fa-solid fa-box"></i>
            </div>
            <div class="ui-form-heading">
              <h2 id="addInventoryModalTitle">Add Inventory Item</h2>
              <p>Enter the required information to add a new item to Warehouse inventory.</p>
            </div>
          </div>

          <button type="button" class="ui-form-close closeModal" aria-label="Close Add Inventory Item">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <form
          id="addInventoryForm"
          action="/inventory"
          method="POST"
          class="ui-form-content inventory-form-content"
          data-confirm-form
          data-confirm-title="Add Inventory Item?"
          data-confirm-message="Are you sure you want to add this inventory item?"
          data-confirm-button="Yes, Add Item"
          data-confirm-type="create"
          data-ajax-submit="true"
          data-parent-modal-id="addModal"
        >
          @csrf

          <div class="ui-form-modal-body inventory-form-modal-body">
            <div class="ui-form-modal-scrollable inventory-form-scrollable">
              <section class="ui-form-section inventory-modal-section inventory-section-basic">
                <div class="ui-form-section-header">
                  <div class="ui-form-section-title">
                    <div class="ui-form-section-icon">
                      <i class="fa-solid fa-file-lines"></i>
                    </div>
                    <div>
                      <h3>Basic Information</h3>
                      <p>Provide the item reference, name, category, and opening stock.</p>
                    </div>
                  </div>
                </div>

                <div class="ui-form-section-body">
                  <div class="ui-form-grid">
                    <div class="ui-form-group">
                      <label for="add_item_code">Item Code <span class="ui-required">*</span></label>
                      <div class="ui-input-wrap has-icon">
                        <span class="ui-input-icon"><i class="fa-solid fa-cube"></i></span>
                        <input id="add_item_code" type="text" name="item_code" required placeholder="e.g. PART-001" autocomplete="off">
                      </div>
                      <small class="inventory-field-help">Unique Warehouse reference for this item.</small>
                    </div>

                    <div class="ui-form-group">
                      <label for="add_item_name">Parts Name <span class="ui-required">*</span></label>
                      <div class="ui-input-wrap has-icon">
                        <span class="ui-input-icon"><i class="fa-solid fa-tag"></i></span>
                        <input id="add_item_name" type="text" name="item_name" required placeholder="e.g. Air Filter" autocomplete="off">
                      </div>
                      <small class="inventory-field-help">Descriptive name used in requests and stock records.</small>
                    </div>

                    <div class="ui-form-group">
                      <label for="add_category">Category</label>
                      <div class="ui-input-wrap has-icon">
                        <span class="ui-input-icon"><i class="fa-solid fa-table-cells-large"></i></span>
                        <input id="add_category" type="text" name="category" placeholder="e.g. Engine Parts" list="inventoryCategoryOptions" autocomplete="off">
                      </div>
                      <small class="inventory-field-help">Use an existing category or enter a new one.</small>
                    </div>

                    <div class="ui-form-group">
                      <label for="add_quantity">Quantity Available <span class="ui-required">*</span></label>
                      <div class="ui-input-wrap has-icon">
                        <span class="ui-input-icon"><i class="fa-solid fa-layer-group"></i></span>
                        <input id="add_quantity" type="number" name="on_hand" min="0" value="0" required>
                      </div>
                      <small class="inventory-field-help">Opening quantity physically available in Warehouse.</small>
                    </div>
                  </div>
                </div>
              </section>

              <section class="ui-form-section inventory-modal-section inventory-section-details">
                <div class="ui-form-section-header">
                  <div class="ui-form-section-title">
                    <div class="ui-form-section-icon">
                      <i class="fa-solid fa-gears"></i>
                    </div>
                    <div>
                      <h3>Inventory Details</h3>
                      <p>Set the measurement unit, reorder threshold, supplier, and storage location.</p>
                    </div>
                  </div>
                </div>

                <div class="ui-form-section-body">
                  <div class="ui-form-grid">
                    <div class="ui-form-group">
                      <label for="add_unit">Unit of Measurement <span class="ui-required">*</span></label>
                      <div class="ui-input-wrap has-icon">
                        <span class="ui-input-icon"><i class="fa-solid fa-ruler"></i></span>
                        <input id="add_unit" type="text" name="unit_of_measurement" required placeholder="e.g. pcs, liter, box" list="inventoryUnitOptions" autocomplete="off">
                      </div>
                      <small class="inventory-field-help">Unit used when receiving and issuing this item.</small>
                    </div>

                    <div class="ui-form-group">
                      <label for="add_reorder">Reorder Level <span class="ui-required">*</span></label>
                      <div class="ui-input-wrap has-icon">
                        <span class="ui-input-icon"><i class="fa-solid fa-chart-simple"></i></span>
                        <input id="add_reorder" type="number" name="reorder_level" min="0" value="0" required>
                      </div>
                      <small class="inventory-field-help">Minimum stock level before replenishment is needed.</small>
                    </div>

                    <div class="ui-form-group">
                      <label for="add_supplier">Supplier</label>
                      <div class="ui-input-wrap has-icon">
                        <span class="ui-input-icon"><i class="fa-solid fa-truck"></i></span>
                        <input id="add_supplier" type="text" name="supplier" placeholder="e.g. Supplier name" autocomplete="off">
                      </div>
                      <small class="inventory-field-help">Primary supplier for this item, if known.</small>
                    </div>

                    <div class="ui-form-group">
                      <label for="add_location">Storage Location</label>
                      <div class="ui-input-wrap has-icon">
                        <span class="ui-input-icon"><i class="fa-solid fa-location-dot"></i></span>
                        <input id="add_location" type="text" name="storage_location" placeholder="e.g. Shelf B-1" autocomplete="off">
                      </div>
                      <small class="inventory-field-help">Physical Warehouse shelf, rack, or storage area.</small>
                    </div>
                  </div>
                </div>
              </section>

              <section class="ui-form-section inventory-modal-section inventory-section-additional">
                <div class="ui-form-section-header">
                  <div class="ui-form-section-title">
                    <div class="ui-form-section-icon">
                      <i class="fa-solid fa-note-sticky"></i>
                    </div>
                    <div>
                      <h3>Additional Information</h3>
                      <p>Add an optional note explaining the opening inventory quantity.</p>
                    </div>
                  </div>
                </div>

                <div class="ui-form-section-body">
                  <div class="ui-form-group">
                    <label for="add_adjustment_reason">Opening Balance Note</label>
                    <textarea
                      id="add_adjustment_reason"
                      name="adjustment_reason"
                      maxlength="500"
                      rows="3"
                      placeholder="e.g. Initial physical count, beginning inventory, transferred stock..."
                    ></textarea>
                    <small class="inventory-field-help">This note will be recorded with the initial stock movement when opening stock is greater than zero.</small>
                  </div>
                </div>
              </section>

              <datalist id="inventoryCategoryOptions">
                @foreach($categories as $category)
                  <option value="{{ $category }}"></option>
                @endforeach
              </datalist>

              <datalist id="inventoryUnitOptions">
                <option value="pcs"></option>
                <option value="liter"></option>
                <option value="box"></option>
                <option value="set"></option>
                <option value="pair"></option>
                <option value="bottle"></option>
              </datalist>
            </div>

            <div class="ui-form-actions inventory-form-actions">
              <button type="button" class="ui-form-btn ui-form-btn-cancel closeModal">
                <i class="fa-solid fa-xmark"></i>
                <span>Cancel</span>
              </button>
              <button type="submit" class="ui-form-btn ui-form-btn-primary">
                <x-ui.spinner size="sm" hidden />
                <i class="fa-solid fa-floppy-disk"></i>
                <span data-loading-label>Save Item</span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    {{-- EDIT INVENTORY ITEM MODAL --}}
    <div class="modal-overlay ui-form-overlay inventory-form-modal-overlay" id="editModal">
      <div class="ui-form-modal ui-form-modal-xl inventory-form-modal" role="dialog" aria-modal="true" aria-labelledby="editInventoryModalTitle">
        <div class="ui-form-modal-header inventory-form-modal-header">
          <div class="ui-form-title-wrap">
            <div class="ui-form-title-icon">
              <i class="fa-solid fa-pen-to-square"></i>
            </div>
            <div class="ui-form-heading">
              <h2 id="editInventoryModalTitle">Edit Inventory Item</h2>
              <p>Update item information while keeping stock adjustments traceable.</p>
            </div>
          </div>

          <button type="button" class="ui-form-close closeModal" aria-label="Close Edit Inventory Item">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <form
          id="editForm"
          method="POST"
          class="ui-form-content inventory-form-content"
          data-confirm-form
          data-confirm-title="Update Inventory Item?"
          data-confirm-message="Are you sure you want to update this inventory item?"
          data-confirm-button="Yes, Update Item"
          data-confirm-type="update"
          data-ajax-submit="true"
          data-parent-modal-id="editModal"
        >
          @csrf
          @method('PUT')

          <div class="ui-form-modal-body inventory-form-modal-body">
            <div class="ui-form-modal-scrollable inventory-form-scrollable">
              <section class="ui-form-section inventory-modal-section inventory-section-basic">
                <div class="ui-form-section-header">
                  <div class="ui-form-section-title">
                    <div class="ui-form-section-icon">
                      <i class="fa-solid fa-file-lines"></i>
                    </div>
                    <div>
                      <h3>Basic Information</h3>
                      <p>Review the item reference, name, category, and current stock quantity.</p>
                    </div>
                  </div>
                </div>

                <div class="ui-form-section-body">
                  <div class="ui-form-grid">
                    <div class="ui-form-group">
                      <label for="edit_item_code">Item Code <span class="ui-required">*</span></label>
                      <div class="ui-input-wrap has-icon">
                        <span class="ui-input-icon"><i class="fa-solid fa-cube"></i></span>
                        <input type="text" name="item_code" id="edit_item_code" required autocomplete="off">
                      </div>
                      <small class="inventory-field-help">Unique Warehouse reference for this item.</small>
                    </div>

                    <div class="ui-form-group">
                      <label for="edit_item_name">Parts Name <span class="ui-required">*</span></label>
                      <div class="ui-input-wrap has-icon">
                        <span class="ui-input-icon"><i class="fa-solid fa-tag"></i></span>
                        <input type="text" name="item_name" id="edit_item_name" required autocomplete="off">
                      </div>
                      <small class="inventory-field-help">Name used across Inventory and Warehouse requests.</small>
                    </div>

                    <div class="ui-form-group">
                      <label for="edit_category">Category</label>
                      <div class="ui-input-wrap has-icon">
                        <span class="ui-input-icon"><i class="fa-solid fa-table-cells-large"></i></span>
                        <input type="text" name="category" id="edit_category" list="inventoryCategoryOptions" autocomplete="off">
                      </div>
                      <small class="inventory-field-help">Inventory classification for this item.</small>
                    </div>

                    <div class="ui-form-group">
                      <label for="edit_quantity">Quantity Available <span class="ui-required">*</span></label>
                      <div class="ui-input-wrap has-icon">
                        <span class="ui-input-icon"><i class="fa-solid fa-layer-group"></i></span>
                        <input type="number" name="on_hand" id="edit_quantity" min="0" required>
                      </div>
                      <small class="inventory-field-help">Changing this value creates an auditable stock adjustment.</small>
                    </div>
                  </div>
                </div>
              </section>

              <section class="ui-form-section inventory-modal-section inventory-section-details">
                <div class="ui-form-section-header">
                  <div class="ui-form-section-title">
                    <div class="ui-form-section-icon">
                      <i class="fa-solid fa-gears"></i>
                    </div>
                    <div>
                      <h3>Inventory Details</h3>
                      <p>Maintain the measurement unit, threshold, supplier, and storage details.</p>
                    </div>
                  </div>
                </div>

                <div class="ui-form-section-body">
                  <div class="ui-form-grid">
                    <div class="ui-form-group">
                      <label for="edit_unit">Unit of Measurement <span class="ui-required">*</span></label>
                      <div class="ui-input-wrap has-icon">
                        <span class="ui-input-icon"><i class="fa-solid fa-ruler"></i></span>
                        <input type="text" name="unit_of_measurement" id="edit_unit" list="inventoryUnitOptions" required autocomplete="off">
                      </div>
                      <small class="inventory-field-help">Unit used for receiving and issuing stock.</small>
                    </div>

                    <div class="ui-form-group">
                      <label for="edit_reorder">Reorder Level <span class="ui-required">*</span></label>
                      <div class="ui-input-wrap has-icon">
                        <span class="ui-input-icon"><i class="fa-solid fa-chart-simple"></i></span>
                        <input type="number" name="reorder_level" id="edit_reorder" min="0" required>
                      </div>
                      <small class="inventory-field-help">Threshold used for low-stock and restock alerts.</small>
                    </div>

                    <div class="ui-form-group">
                      <label for="edit_supplier">Supplier</label>
                      <div class="ui-input-wrap has-icon">
                        <span class="ui-input-icon"><i class="fa-solid fa-truck"></i></span>
                        <input type="text" name="supplier" id="edit_supplier" autocomplete="off">
                      </div>
                      <small class="inventory-field-help">Primary supplier, if one is assigned.</small>
                    </div>

                    <div class="ui-form-group">
                      <label for="edit_location">Storage Location</label>
                      <div class="ui-input-wrap has-icon">
                        <span class="ui-input-icon"><i class="fa-solid fa-location-dot"></i></span>
                        <input type="text" name="storage_location" id="edit_location" autocomplete="off">
                      </div>
                      <small class="inventory-field-help">Shelf, rack, or storage area in Warehouse.</small>
                    </div>
                  </div>
                </div>
              </section>

              <section class="ui-form-section inventory-modal-section inventory-section-additional">
                <div class="ui-form-section-header">
                  <div class="ui-form-section-title">
                    <div class="ui-form-section-icon">
                      <i class="fa-solid fa-clipboard-list"></i>
                    </div>
                    <div>
                      <h3>Stock Adjustment</h3>
                      <p>Explain quantity changes so Inventory history remains auditable.</p>
                    </div>
                  </div>
                </div>

                <div class="ui-form-section-body">
                  <div class="ui-form-group">
                    <label for="edit_adjustment_reason">
                      Adjustment Reason
                      <span id="editAdjustmentRequiredMark" class="ui-required" hidden>*</span>
                    </label>
                    <textarea
                      name="adjustment_reason"
                      id="edit_adjustment_reason"
                      maxlength="500"
                      rows="3"
                      placeholder="Required only when Quantity Available is changed"
                    ></textarea>
                    <small id="editAdjustmentHelp" class="inventory-field-help">No reason is required when stock quantity remains unchanged.</small>
                  </div>
                </div>
              </section>
            </div>

            <div class="ui-form-actions inventory-form-actions">
              <button type="button" class="ui-form-btn ui-form-btn-cancel closeModal">
                <i class="fa-solid fa-xmark"></i>
                <span>Cancel</span>
              </button>
              <button type="submit" class="ui-form-btn ui-form-btn-primary">
                <x-ui.spinner size="sm" hidden />
                <i class="fa-solid fa-floppy-disk"></i>
                <span data-loading-label>Update Item</span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  @endif

  {{-- VIEW MODAL --}}
  <div class="modal-overlay" id="viewModal">
    <div class="modal-box wide-modal">

      <div class="modal-header">
        <h2>Inventory Item Details</h2>
        <button type="button" class="close-btn closeModal">&times;</button>
      </div>

      <div class="details-grid">

        <div class="detail-item">
          <span>Item Code</span>
          <strong id="view_code">—</strong>
        </div>

        <div class="detail-item">
          <span>Parts Name</span>
          <strong id="view_name">—</strong>
        </div>

        <div class="detail-item">
          <span>Category</span>
          <strong id="view_category">—</strong>
        </div>

        <div class="detail-item">
          <span>Quantity Available</span>
          <strong id="view_quantity">—</strong>
        </div>

        <div class="detail-item">
          <span>Unit</span>
          <strong id="view_unit">—</strong>
        </div>

        <div class="detail-item">
          <span>Reorder Level</span>
          <strong id="view_reorder">—</strong>
        </div>

        <div class="detail-item">
          <span>Status</span>
          <strong id="view_status">—</strong>
        </div>

        <div class="detail-item">
          <span>Supplier</span>
          <strong id="view_supplier">—</strong>
        </div>

        <div class="detail-item">
          <span>Storage Location</span>
          <strong id="view_location">—</strong>
        </div>

        <div class="detail-item">
          <span>Last Updated</span>
          <strong id="view_updated">—</strong>
        </div>

      </div>

      <div class="modal-actions full-width">
        <button type="button" class="secondary-btn cancel-btn closeModal">Close</button>
      </div>

    </div>
  </div>

</x-layout.app>
