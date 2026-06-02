<x-layout.app
  title="FROMS - Purchase Order"
  :assets="[
    'resources/css/Purchase/purchase-orders.css'
  ]"
>

  <div class="app">

    <x-layout.sidebar
      department="Purchase"
      subtitle="Department Module"
      icon="fa-truck"
      user-name="R. Lim"
      user-role="Purchase Admin"
      :items="[
        ['label' => 'Purchase Orders', 'route' => 'purchase-orders', 'icon' => 'fa-file-invoice'],
        ['label' => 'Requested Purchase', 'route' => 'requested-purchase', 'icon' => 'fa-clipboard-list'],
        ['label' => 'Scheduled Purchase', 'route' => 'scheduled-purchase', 'icon' => 'fa-calendar-check'],
      ]"
    />

    <!-- MAIN -->
    <main class="main">

      <!-- TOP BAR -->
      <header class="topbar">
        <div>
          <h1>Purchase Order</h1>
          <p>Manage procurement records for vehicle parts, equipment & operational materials</p>
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
    <section class="stats-grid">

  <x-ui.summary-card
    label="Total Orders"
    value="8"
    small="Purchase orders"
    icon="fa-file-invoice"
    color="gray"
  />

  <x-ui.summary-card
    label="Ordered"
    value="3"
    small="Waiting for delivery"
    icon="fa-cart-shopping"
    color="blue"
  />

  <x-ui.summary-card
    label="Shipped"
    value="2"
    small="In transit"
    icon="fa-truck-fast"
    color="yellow"  
  />

  <x-ui.summary-card
    label="Delivered"
    value="3"
    small="Completed orders"
    icon="fa-circle-check"
    color="green"
  />

</section>

      <!-- PURCHASE ORDER TABLE -->
      <section class="table-card po-card">

        <div class="section-header">
          <div>
            <h2>Purchase Order Records</h2>
            <p>Track purchase orders, supplier status, and scheduled delivery dates</p>
          </div>
        </div>

        <!-- TOOLBAR INSIDE CARD -->
        <div class="toolbar po-toolbar">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" placeholder="Search by item, order number, or supplier...">
          </div>

          <select>
            <option>All Categories</option>
            <option>Vehicle Parts</option>
            <option>Equipment</option>
            <option>Operational Materials</option>
          </select>

          <select>
            <option>All States</option>
            <option>Pending</option>
            <option>Ordered</option>
            <option>Approved</option>
            <option>Shipped</option>
            <option>Delivered</option>
          </select>

          <button class="primary-btn">
            <i class="fa-solid fa-plus"></i>
            New PO
          </button>
        </div>

        <div class="table-wrap">
          <table class="po-table">
            <thead>
              <tr>
                <th>Order #</th>
                <th>Item / Category</th>
                <th>Quantity</th>
                <th>Supplier</th>
                <th>Total Price</th>
                <th>Status</th>
                <th>Scheduled Date</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              <tr>
                <td>PO-2026-0001</td>
                <td>
                  <strong>Engine Oil Filter</strong>
                  <span class="sub-text">Vehicle Parts</span>
                </td>
                <td>50 pcs</td>
                <td>AutoParts Philippines Corp...</td>
                <td><strong>₱17,500.00</strong></td>
                <td><span class="badge delivered">Delivered</span></td>
                <td>Apr 1, 2026</td>
                <td>
                  <div class="po-actions">
                    <button type="button"><i class="fa-regular fa-eye"></i></button>
                    <button type="button"><i class="fa-solid fa-pen"></i></button>
                    <button type="button"><i class="fa-solid fa-trash-can"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>PO-2026-0002</td>
                <td>
                  <strong>Brake Pads</strong>
                  <span class="sub-text">Vehicle Parts</span>
                </td>
                <td>30 pcs</td>
                <td>Metro Supplies</td>
                <td><strong>₱21,300.00</strong></td>
                <td><span class="badge approved">Approved</span></td>
                <td>Apr 3, 2026</td>
                <td>
                  <div class="po-actions">
                    <button type="button"><i class="fa-regular fa-eye"></i></button>
                    <button type="button"><i class="fa-solid fa-pen"></i></button>
                    <button type="button"><i class="fa-solid fa-trash-can"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>PO-2026-0003</td>
                <td>
                  <strong>Coolant</strong>
                  <span class="sub-text">Operational Materials</span>
                </td>
                <td>40 pcs</td>
                <td>Fleet Warehouse</td>
                <td><strong>₱12,000.00</strong></td>
                <td><span class="badge ordered">Ordered</span></td>
                <td>Apr 5, 2026</td>
                <td>
                  <div class="po-actions">
                    <button type="button"><i class="fa-regular fa-eye"></i></button>
                    <button type="button"><i class="fa-solid fa-pen"></i></button>
                    <button type="button"><i class="fa-solid fa-trash-can"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>PO-2026-0004</td>
                <td>
                  <strong>Fuel Filter</strong>
                  <span class="sub-text">Vehicle Parts</span>
                </td>
                <td>25 pcs</td>
                <td>AutoParts Philippines Corp...</td>
                <td><strong>₱9,750.00</strong></td>
                <td><span class="badge shipped">Shipped</span></td>
                <td>Apr 8, 2026</td>
                <td>
                  <div class="po-actions">
                    <button type="button"><i class="fa-regular fa-eye"></i></button>
                    <button type="button"><i class="fa-solid fa-pen"></i></button>
                    <button type="button"><i class="fa-solid fa-trash-can"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>PO-2026-0005</td>
                <td>
                  <strong>Tire Valve</strong>
                  <span class="sub-text">Vehicle Parts</span>
                </td>
                <td>100 pcs</td>
                <td>Metro Supplies</td>
                <td><strong>₱8,500.00</strong></td>
                <td><span class="badge pending">Pending</span></td>
                <td>Apr 10, 2026</td>
                <td>
                  <div class="po-actions">
                    <button type="button"><i class="fa-regular fa-eye"></i></button>
                    <button type="button"><i class="fa-solid fa-pen"></i></button>
                    <button type="button"><i class="fa-solid fa-trash-can"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>PO-2026-0006</td>
                <td>
                  <strong>Air Filter</strong>
                  <span class="sub-text">Vehicle Parts</span>
                </td>
                <td>45 pcs</td>
                <td>Fleet Warehouse</td>
                <td><strong>₱13,950.00</strong></td>
                <td><span class="badge delivered">Delivered</span></td>
                <td>Apr 12, 2026</td>
                <td>
                  <div class="po-actions">
                    <button type="button"><i class="fa-regular fa-eye"></i></button>
                    <button type="button"><i class="fa-solid fa-pen"></i></button>
                    <button type="button"><i class="fa-solid fa-trash-can"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>PO-2026-0007</td>
                <td>
                  <strong>Engine Oil</strong>
                  <span class="sub-text">Lubricants</span>
                </td>
                <td>60 pcs</td>
                <td>Diesel Masters</td>
                <td><strong>₱36,000.00</strong></td>
                <td><span class="badge approved">Approved</span></td>
                <td>Apr 15, 2026</td>
                <td>
                  <div class="po-actions">
                    <button type="button"><i class="fa-regular fa-eye"></i></button>
                    <button type="button"><i class="fa-solid fa-pen"></i></button>
                    <button type="button"><i class="fa-solid fa-trash-can"></i></button>
                  </div>
                </td>
              </tr>

              <tr>
                <td>PO-2026-0008</td>
                <td>
                  <strong>AC Belt</strong>
                  <span class="sub-text">Vehicle Parts</span>
                </td>
                <td>20 pcs</td>
                <td>AutoParts Philippines Corp...</td>
                <td><strong>₱18,400.00</strong></td>
                <td><span class="badge ordered">Ordered</span></td>
                <td>Apr 18, 2026</td>
                <td>
                  <div class="po-actions">
                    <button type="button"><i class="fa-regular fa-eye"></i></button>
                    <button type="button"><i class="fa-solid fa-pen"></i></button>
                    <button type="button"><i class="fa-solid fa-trash-can"></i></button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

      </section>

    </main>

  </div>

</x-layout.app>