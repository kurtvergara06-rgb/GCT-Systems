<x-layout.app
  title="FROMS - Warehouse Inventory"
  :assets="[
    'resources/css/Warehouse/inventory.css',
    'resources/css/Main-style/main.css',
    'resources/js/Warehouse/inventory.js'
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
          <h1>Warehouse Inventory</h1>
          <p>Monitor vehicle parts stock levels, threshold alerts, and restocking needs</p>
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

      {{-- ALERTS --}}
      @if(session('success'))
        <div class="alert success-alert">
          {{ session('success') }}
        </div>
      @endif

      @if($errors->any())
        <div class="alert error-alert">
          {{ $errors->first() }}
        </div>
      @endif

          {{-- SUMMARY CARDS --}}
          <section class="stats-grid inventory-stats">

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
      <section class="table-card inventory-card">

        <div class="section-header">
          <div>
            <h2>Inventory Stock Records</h2>
            <p>Track warehouse stock levels, item thresholds, and item availability</p>
          </div>
        </div>

        <form method="GET" action="{{ route('inventory') }}" class="toolbar inventory-toolbar">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input
              type="text"
              name="search"
              value="{{ request('search') }}"
              placeholder="Search by item, item code, supplier, or location..."
            >
          </div>

          <div class="filter-group">
            <label>Category</label>
            <select name="category" onchange="this.form.submit()">
              <option value="All Categories" {{ request('category') == 'All Categories' ? 'selected' : '' }}>
                All Categories
              </option>

              @foreach($categories as $category)
                <option value="{{ $category }}" {{ request('category') == $category ? 'selected' : '' }}>
                  {{ $category }}
                </option>
              @endforeach
            </select>
          </div>

          <button type="button" class="secondary-btn" id="openImportModal">
            <i class="fa-solid fa-file-import"></i>
            Import Inventory Data
          </button>

          <button type="button" class="primary-btn" id="openAddModal">
            <i class="fa-solid fa-plus"></i>
            Add Item
          </button>
        </form>

        <div class="table-wrap">
          <table class="inventory-table">
            <thead>
              <tr>
                <th>Item Code</th>
                <th>Parts Name</th>
                <th>Category</th>
                <th>On Hand</th>
                <th>Unit</th>
                <th>Reorder Level</th>
                <th>Status</th>
                <th>Supplier</th>
                <th>Location</th>
                <th>Last Updated</th>
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

                  $badgeClass = match($status) {
                    'Critical' => 'critical',
                    'Low Stock' => 'low-stock',
                    default => 'in-stock'
                  };
                @endphp

                <tr class="{{ $rowClass }}">
                  <td>{{ $item->item_code }}</td>
                  <td>{{ $item->item_name }}</td>
                  <td>{{ $item->category }}</td>
                  <td><strong>{{ $item->quantity_available }}</strong></td>
                  <td>{{ $item->unit_of_measurement }}</td>
                  <td><strong>{{ $item->reorder_level }}</strong></td>
                  <td>
                    <span class="badge {{ $badgeClass }}">
                      {{ $status }}
                    </span>
                  </td>
                  <td>{{ $item->supplier ?? '—' }}</td>
                  <td>{{ $item->storage_location ?? '—' }}</td>
                  <td>{{ $item->updated_at->format('M d, Y') }}</td>

                  <td>
                    <div class="actions">

                      <button
                        type="button"
                        class="edit openEditModal"
                        title="Edit Item"
                        data-action="{{ route('inventory.update', $item->id) }}"
                        data-code="{{ $item->item_code }}"
                        data-name="{{ $item->item_name }}"
                        data-category="{{ $item->category }}"
                        data-quantity="{{ $item->quantity_available }}"
                        data-unit="{{ $item->unit_of_measurement }}"
                        data-reorder="{{ $item->reorder_level }}"
                        data-supplier="{{ $item->supplier }}"
                        data-location="{{ $item->storage_location }}"
                      >
                        <i class="fa-solid fa-pen-to-square"></i>
                      </button>

                      <form action="{{ route('inventory.destroy', $item->id) }}" method="POST">
                        @csrf
                        @method('DELETE')

                        <button
                          type="submit"
                          class="delete"
                          title="Delete Item"
                          onclick="return confirm('Delete this inventory item?')"
                        >
                          <i class="fa-solid fa-trash"></i>
                        </button>
                      </form>

                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="11" style="text-align:center; padding: 30px;">
                    No inventory items found.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        {{-- CUSTOM PAGINATION --}}
        <div class="table-footer">
          <p>
            Showing {{ $inventoryItems->firstItem() ?? 0 }} to {{ $inventoryItems->lastItem() ?? 0 }} of {{ $inventoryItems->total() }} entries
          </p>

          <div class="custom-pagination">
            @if ($inventoryItems->onFirstPage())
              <span class="page-btn disabled">Previous</span>
            @else
              <a href="{{ $inventoryItems->previousPageUrl() }}" class="page-btn">Previous</a>
            @endif

            <span class="page-number">
              Page {{ $inventoryItems->currentPage() }} of {{ $inventoryItems->lastPage() }}
            </span>

            @if ($inventoryItems->hasMorePages())
              <a href="{{ $inventoryItems->nextPageUrl() }}" class="page-btn">Next</a>
            @else
              <span class="page-btn disabled">Next</span>
            @endif
          </div>
        </div>

      </section>

    </main>

  </div>

  {{-- ADD MODAL --}}
  <div class="modal-overlay" id="addModal">
    <div class="modal-box wide-modal">

      <div class="modal-header">
        <h2>Add Inventory Item</h2>
        <button type="button" class="close-btn closeModal">&times;</button>
      </div>

      <form action="{{ route('inventory.store') }}" method="POST">
        @csrf

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

          <div class="form-group">
            <label>Quantity Available</label>
            <input type="number" name="quantity_available" min="0" required>
          </div>

          <div class="form-group">
            <label>Unit of Measurement</label>
            <input type="text" name="unit_of_measurement" required placeholder="Example: pcs, liter, box">
          </div>

          <div class="form-group">
            <label>Reorder Level</label>
            <input type="number" name="reorder_level" min="0" required>
          </div>

          <div class="form-group">
            <label>Supplier</label>
            <input type="text" name="supplier" placeholder="Supplier name">
          </div>

          <div class="form-group">
            <label>Storage Location</label>
            <input type="text" name="storage_location" placeholder="Example: A-02">
          </div>

        </div>

        <div class="modal-actions full-width">
          <button type="button" class="cancel-btn closeModal">Cancel</button>
          <button type="submit" class="primary-btn">Save Item</button>
        </div>

      </form>

    </div>
  </div>

  {{-- EDIT MODAL --}}
  <div class="modal-overlay" id="editModal">
    <div class="modal-box wide-modal">

      <div class="modal-header">
        <h2>Edit Inventory Item</h2>
        <button type="button" class="close-btn closeModal">&times;</button>
      </div>

      <form id="editForm" method="POST">
        @csrf
        @method('PUT')

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

          <div class="form-group">
            <label>Quantity Available</label>
            <input type="number" name="quantity_available" id="edit_quantity" min="0" required>
          </div>

          <div class="form-group">
            <label>Unit of Measurement</label>
            <input type="text" name="unit_of_measurement" id="edit_unit" required>
          </div>

          <div class="form-group">
            <label>Reorder Level</label>
            <input type="number" name="reorder_level" id="edit_reorder" min="0" required>
          </div>

          <div class="form-group">
            <label>Supplier</label>
            <input type="text" name="supplier" id="edit_supplier">
          </div>

          <div class="form-group">
            <label>Storage Location</label>
            <input type="text" name="storage_location" id="edit_location">
          </div>

        </div>

        <div class="modal-actions full-width">
          <button type="button" class="cancel-btn closeModal">Cancel</button>
          <button type="submit" class="primary-btn">Update Item</button>
        </div>

      </form>

    </div>
  </div>

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
        <button type="button" class="cancel-btn closeModal">Close</button>
      </div>

    </div>
  </div>

  {{-- IMPORT MODAL --}}
  <div class="modal-overlay" id="importModal">
    <div class="modal-box">

      <div class="modal-header">
        <h2>Import Inventory Data</h2>
        <button type="button" class="close-btn closeModal">&times;</button>
      </div>

      <form action="{{ route('inventory.import') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="form-group full-width">
          <label>Upload CSV File</label>
          <input type="file" name="import_file" accept=".csv,.txt" required>

          <small>
            CSV format: item_code, item_name, category, quantity_available, unit, reorder_level, supplier, storage_location
          </small>
        </div>

        <div class="modal-actions full-width">
          <button type="button" class="cancel-btn closeModal">Cancel</button>
          <button type="submit" class="primary-btn">Import Data</button>
        </div>

      </form>

    </div>
  </div>

</x-layout.app>