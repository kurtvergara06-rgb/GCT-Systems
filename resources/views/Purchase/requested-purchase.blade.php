<x-layout.app
  title="FROMS - Requested Purchase"
  :assets="[
    'resources/css/Main-style/main.css',
    'resources/css/Main-style/sidebar.css',
    'resources/css/Purchase/purchase-orders.css'
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
        ['label' => 'Purchase Orders', 'route' => 'purchase-orders', 'icon' => 'fa-file-invoice'],
        ['label' => 'Requested Purchase', 'route' => 'requested-purchase', 'icon' => 'fa-clipboard-list'],
        ['label' => 'Scheduled Purchase', 'route' => 'scheduled-purchase', 'icon' => 'fa-calendar-check'],
      ]"
    />

    <main class="main">

      <x-layout.topbar
        title="Requested Purchases"
        subtitle="View approved maintenance purchase requests for purchasing process"
        notification-count="6"
      />

      @if($errors->any())
        <div class="alert-error">
          <ul>
            @foreach($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

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

      {{-- TABLE --}}
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
              @forelse($purchaseRequests as $purchaseRequest)
                @php
                  $statusClass = strtolower(str_replace(' ', '-', $purchaseRequest->status));
                @endphp

                <tr>
                  <td>{{ $purchaseRequest->pr_no }}</td>
                  <td>{{ $purchaseRequest->job_order_no }}</td>
                  <td>{{ $purchaseRequest->bus_no }}</td>
                  <td>{{ $purchaseRequest->item }}</td>
                  <td>{{ $purchaseRequest->quantity }}</td>

                  <td>
                    <span class="badge {{ $statusClass }}">
                      {{ $purchaseRequest->status }}
                    </span>
                  </td>

                  <td>
                    {{ $purchaseRequest->created_at ? $purchaseRequest->created_at->format('m/d/y | h:i A') : '—' }}
                  </td>

                  <td>
                    <div class="actions">

                      @if($purchaseRequest->status === 'Approved')
                        <form
                          action="{{ route('requested-purchase.for-purchase', $purchaseRequest->id) }}"
                          method="POST"
                        >
                          @csrf

                          <button
                            type="submit"
                            class="edit"
                            title="Mark as For Purchase"
                          >
                            <i class="fa-solid fa-cart-shopping"></i>
                          </button>
                        </form>

                      @elseif($purchaseRequest->status === 'For Purchase')
                        <form
                          action="{{ route('requested-purchase.pending-purchase', $purchaseRequest->id) }}"
                          method="POST"
                        >
                          @csrf

                          <button
                            type="submit"
                            class="edit"
                            title="Mark as Pending Purchase"
                          >
                            <i class="fa-solid fa-clock"></i>
                          </button>
                        </form>

                      @elseif($purchaseRequest->status === 'Pending Purchase')
                        <form
                          action="{{ route('requested-purchase.delivering', $purchaseRequest->id) }}"
                          method="POST"
                        >
                          @csrf

                          <button
                            type="submit"
                            class="edit"
                            title="Mark as Delivering"
                          >
                            <i class="fa-solid fa-truck-fast"></i>
                          </button>
                        </form>

                      @elseif($purchaseRequest->status === 'Delivering')
                        <form
                          action="{{ route('requested-purchase.delivered', $purchaseRequest->id) }}"
                          method="POST"
                        >
                          @csrf

                          <button
                            type="submit"
                            class="edit"
                            title="Mark as Delivered"
                          >
                            <i class="fa-solid fa-box"></i>
                          </button>
                        </form>

                      @else
                        <span class="no-action">No Action</span>
                      @endif

                    </div>
                  </td>
                </tr>
              @empty
                <x-ui.empty-row
                  colspan="8"
                  message="No requested purchases found."
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