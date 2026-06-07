<x-layout.app
  title="FROMS - Requested Purchase"
  :assets="[
    'resources/css/Main-style/main.css',
    'resources/css/Main-style/sidebar.css',
    'resources/css/Purchase/purchase-orders.css'
  ]"
>

  <div class="app">

    <x-layout.sidebar
      department="Purchase"
      subtitle="Department Module"
      icon="fa-cart-shopping"
      user-name="P. Admin"
      user-role="Purchase Admin"
      :items="[
        ['label' => 'Purchase Orders', 'route' => 'purchase-orders', 'icon' => 'fa-file-invoice'],
        ['label' => 'Requested Purchase', 'route' => 'requested-purchase', 'icon' => 'fa-clipboard-list'],
        ['label' => 'Scheduled Purchase', 'route' => 'scheduled-purchase', 'icon' => 'fa-calendar-check'],
      ]"
    />

    <main class="main">

      {{-- TOP BAR --}}
      <x-layout.topbar
        title="Requested Purchases"
        subtitle="View approved maintenance purchase requests for purchasing process"
        notification-count="6"
      />

      {{-- SUMMARY CARDS --}}
      <section class="stats-grid">

        <x-ui.summary-card
          label="Total Requests"
          value="{{ $totalRequests }}"
          small="Purchase requests"
          icon="fa-file"
          color="gray"
        />

        <x-ui.summary-card
          label="Approved"
          value="{{ $approved }}"
          small="Ready for purchase"
          icon="fa-check"
          color="green"
        />

        <x-ui.summary-card
          label="For Purchase"
          value="{{ $forPurchase }}"
          small="Parts unavailable"
          icon="fa-cart-shopping"
          color="blue"
        />

        <x-ui.summary-card
          label="Delivered"
          value="{{ $delivered }}"
          small="Supplier delivered"
          icon="fa-box"
          color="yellow"
        />

      </section>

      {{-- PURCHASE REQUEST TABLE --}}
      <section class="table-card purchase-card">

        <div class="section-header">
          <div>
            <h2>Requested Purchase Records</h2>
            <p>Track approved purchase requests from Maintenance for purchasing process</p>
          </div>
        </div>

        <form action="{{ route('requested-purchase') }}" method="GET" class="toolbar purchase-toolbar">

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
            <label>Status</label>
            <select name="status" onchange="this.form.submit()">
              <option value="All Statuses" {{ request('status') == 'All Statuses' ? 'selected' : '' }}>
                All Statuses
              </option>

              <option value="Approved" {{ request('status') == 'Approved' ? 'selected' : '' }}>
                Approved
              </option>

              <option value="For Purchase" {{ request('status') == 'For Purchase' ? 'selected' : '' }}>
                For Purchase
              </option>

              <option value="Pending Purchase" {{ request('status') == 'Pending Purchase' ? 'selected' : '' }}>
                Pending Purchase
              </option>

              <option value="Delivering" {{ request('status') == 'Delivering' ? 'selected' : '' }}>
                Delivering
              </option>

              <option value="Delivered" {{ request('status') == 'Delivered' ? 'selected' : '' }}>
                Delivered
              </option>

              <option value="Issued" {{ request('status') == 'Issued' ? 'selected' : '' }}>
                Issued
              </option>
            </select>
          </div>

        </form>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>PR #</th>
                <th>JO No.</th>
                <th>Bus #</th>
                <th>Item</th>
                <th>Quantity</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              @forelse($purchaseRequests as $pr)
                <tr>
                  <td>{{ $pr->pr_no }}</td>
                  <td>{{ $pr->job_order_no }}</td>
                  <td>{{ $pr->bus_no }}</td>
                  <td>{{ $pr->item }}</td>
                  <td>{{ $pr->quantity }}</td>

                  <td>
                    <x-ui.status-badge :status="$pr->status" />
                  </td>

                  <td>
                    {{ $pr->created_at ? $pr->created_at->format('m/d/y | h:i A') : '—' }}
                  </td>

                  <td>
                    <div class="actions">

                      <button type="button" class="view" title="View">
                        <i class="fa-solid fa-eye"></i>
                      </button>

                      @if($pr->status === 'Approved')
                        <form action="{{ route('purchase-requests.for-purchase', $pr->id) }}" method="POST">
                          @csrf

                          <button type="submit" class="edit" title="Mark as For Purchase">
                            <i class="fa-solid fa-cart-shopping"></i>
                          </button>
                        </form>
                      @else
                        <button type="button" class="edit" title="No Action" disabled>
                          <i class="fa-solid fa-lock"></i>
                        </button>
                      @endif

                    </div>
                  </td>
                </tr>
              @empty
                <x-ui.empty-row
                  colspan="8"
                  message="No requested purchase records found."
                />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$purchaseRequests" />

      </section>

    </main>

  </div>

</x-layout.app>