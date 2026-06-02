<x-layout.app
  title="FROMS - Warehouse Inventory"
  :assets="[
    'resources/css/Warehouse/inventory.css'
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

    <!-- MAIN -->
    <main class="main">

      <!-- TOP BAR -->
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

      <!-- SUMMARY CARDS -->
      <section class="stats-grid inventory-stats">

        <div class="stat-card">
          <div class="stat-icon green">
            <i class="fa-solid fa-boxes-stacked"></i>
          </div>

          <div>
            <p>Total Items in Stock</p>
            <h2>3,414</h2>
            <small>Across all categories</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon yellow">
            <i class="fa-solid fa-bell"></i>
          </div>

          <div>
            <p>Low Stock Alerts</p>
            <h2>4</h2>
            <small>Below threshold</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon red">
            <i class="fa-solid fa-triangle-exclamation"></i>
          </div>

          <div>
            <p>Critical Items</p>
            <h2>2</h2>
            <small>Need immediate restock</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon blue">
            <i class="fa-solid fa-chart-line"></i>
          </div>

          <div>
            <p>Forecasted Stockouts</p>
            <h2>6</h2>
            <small>Within 30 to 40 days</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

      </section>

      <!-- INVENTORY TABLE -->
      <section class="table-card inventory-card">

        <div class="section-header">
          <div>
            <h2>Inventory Stock Records</h2>
            <p>Track warehouse stock levels, item thresholds, and item availability</p>
          </div>

          <div class="inventory-summary">
            <span>15 items shown</span>
            <span class="critical-text">2 critical</span>
            <span class="low-text">2 low</span>
            <span class="stock-text">11 in stock</span>
          </div>
        </div>

        <div class="toolbar inventory-toolbar">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" placeholder="Search by item, order number, or supplier...">
          </div>

          <div class="filter-group">
            <label>Category</label>
            <select>
              <option>All Categories</option>
              <option>Engine Parts</option>
              <option>Cooling System</option>
              <option>Brake System</option>
              <option>Fuel System</option>
            </select>
          </div>

          <div class="filter-group">
            <label>Status</label>
            <select>
              <option>All States</option>
              <option>In Stock</option>
              <option>Low Stock</option>
              <option>Critical</option>
            </select>
          </div>

          <button class="primary-btn">
            <i class="fa-solid fa-plus"></i>
            Add Item
          </button>
        </div>

        <div class="table-wrap">
          <table class="inventory-table">
            <thead>
              <tr>
                <th>Item ID</th>
                <th>Parts Name</th>
                <th>Category</th>
                <th>On Hand</th>
                <th>Min. Threshold</th>
                <th>Status</th>
                <th>Location</th>
                <th>Last Updated</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              <tr>
                <td>PART-007</td>
                <td>Air Filter</td>
                <td>Engine Parts</td>
                <td><strong>18</strong></td>
                <td><strong>10</strong></td>
                <td><span class="badge in-stock">In Stock</span></td>
                <td>C-02</td>
                <td>Apr 1, 2026</td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr class="warning-row">
                <td>PART-008</td>
                <td>Coolant Antifreeze 4L</td>
                <td>Cooling System</td>
                <td><strong>13</strong></td>
                <td><strong>15</strong></td>
                <td><span class="badge low-stock">Low Stock</span></td>
                <td>A-02</td>
                <td>Jan 1, 2026</td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>PART-009</td>
                <td>Brake Pads</td>
                <td>Brake System</td>
                <td><strong>20</strong></td>
                <td><strong>8</strong></td>
                <td><span class="badge in-stock">In Stock</span></td>
                <td>B-01</td>
                <td>Apr 2, 2026</td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr class="danger-row">
                <td>PART-010</td>
                <td>Engine Oil</td>
                <td>Engine Parts</td>
                <td><strong>4</strong></td>
                <td><strong>10</strong></td>
                <td><span class="badge critical">Critical</span></td>
                <td>D-03</td>
                <td>Apr 3, 2026</td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>PART-011</td>
                <td>Fuel Filter</td>
                <td>Fuel System</td>
                <td><strong>16</strong></td>
                <td><strong>10</strong></td>
                <td><span class="badge in-stock">In Stock</span></td>
                <td>C-04</td>
                <td>Apr 4, 2026</td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr class="warning-row">
                <td>PART-012</td>
                <td>Tire Valve</td>
                <td>Tire Parts</td>
                <td><strong>7</strong></td>
                <td><strong>10</strong></td>
                <td><span class="badge low-stock">Low Stock</span></td>
                <td>A-04</td>
                <td>Apr 4, 2026</td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>PART-013</td>
                <td>Suspension Bushing</td>
                <td>Suspension</td>
                <td><strong>22</strong></td>
                <td><strong>12</strong></td>
                <td><span class="badge in-stock">In Stock</span></td>
                <td>E-01</td>
                <td>Apr 5, 2026</td>
                <td>
                  <div class="actions">
                    <button class="edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="delete"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

      </section>

      <!-- FORECAST TABLE -->
      <section class="table-card inventory-card">

        <div class="section-header">
          <div>
            <h2>Inventory Forecast and Restocking Recommendation</h2>
            <p>Predicted demand against current stock for at-risk items</p>
          </div>
        </div>

        <div class="toolbar inventory-toolbar">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" placeholder="Search forecasted item...">
          </div>

          <div class="filter-group">
            <label>Category</label>
            <select>
              <option>All Categories</option>
              <option>Engine Parts</option>
              <option>Brake System</option>
              <option>Cooling System</option>
            </select>
          </div>

          <div class="filter-group">
            <label>Status</label>
            <select>
              <option>All States</option>
              <option>Restock Soon</option>
              <option>Monitor Stock</option>
              <option>Enough Stock</option>
            </select>
          </div>
        </div>

        <div class="table-wrap">
          <table class="inventory-table">
            <thead>
              <tr>
                <th>Part</th>
                <th>Current Stock</th>
                <th>Monthly Usage</th>
                <th>Forecast Demand</th>
                <th>Days Until Stockout</th>
                <th>Recommended Action</th>
              </tr>
            </thead>

            <tbody>
              <tr>
                <td>
                  <strong>Air Filter</strong>
                  <span class="sub-text">Filters</span>
                </td>
                <td>18 Pieces</td>
                <td>6/mo</td>
                <td>7 Pieces</td>
                <td>40 days</td>
                <td><span class="badge enough">Enough Stock</span></td>
              </tr>

              <tr>
                <td>
                  <strong>Brake Pads</strong>
                  <span class="sub-text">Brake</span>
                </td>
                <td>8 Pieces</td>
                <td>6/mo</td>
                <td>9 Pieces</td>
                <td>40 days</td>
                <td><span class="badge monitor">Monitor Stock</span></td>
              </tr>

              <tr>
                <td>
                  <strong>Suspension Bushing</strong>
                  <span class="sub-text">Suspension</span>
                </td>
                <td>8 Pieces</td>
                <td>6/mo</td>
                <td>18 Pieces</td>
                <td>40 days</td>
                <td><span class="badge restock">Restock Soon</span></td>
              </tr>

              <tr>
                <td>
                  <strong>Tire Valve</strong>
                  <span class="sub-text">Tires</span>
                </td>
                <td>7 Pieces</td>
                <td>6/mo</td>
                <td>22 Pieces</td>
                <td>40 days</td>
                <td><span class="badge restock">Restock Soon</span></td>
              </tr>

              <tr>
                <td>
                  <strong>Engine Oil</strong>
                  <span class="sub-text">Engine Parts</span>
                </td>
                <td>4 Pieces</td>
                <td>6/mo</td>
                <td>15 Pieces</td>
                <td>30 days</td>
                <td><span class="badge restock">Restock Soon</span></td>
              </tr>
            </tbody>
          </table>
        </div>

      </section>

    </main>

  </div>

</x-layout.app>