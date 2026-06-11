<x-layout.app
  title="FROMS - Requested Purchase"
  :assets="[
    'resources/css/Main-style/main.css',
    'resources/css/Main-style/sidebar.css',
    'resources/css/Purchase/requested-purchase.css',
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
        subtitle="View maintenance purchase requests sent for purchasing process"
        notification-count="6"
      />

      @if($errors->any())
        <div id="validationErrorModal" class="delete-modal-overlay show">
          <div class="delete-modal-box">

            <div class="delete-icon">
              <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <h2>Form Error</h2>

            <p>Please check the form. Some required information is missing.</p>

            <ul style="text-align: left; margin: 12px 0 0; color: #dc2626; font-size: 13px;">
              @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>

            <div class="delete-modal-actions">
              <button type="button" id="closeValidationErrorModal" class="cancel-delete-btn">
                Okay
              </button>
            </div>

          </div>
        </div>
      @endif

      {{-- SUMMARY CARDS --}}
      <section class="stats-grid">

        <x-ui.summary-card
          label="Total Requests"
          value="{{ $totalRequests ?? 0 }}"
          small="Purchase requests"
          icon="fa-file"
          color="gray"
        />

        <x-ui.summary-card
          label="For Purchase"
          value="{{ $forPurchase ?? 0 }}"
          small="Parts unavailable"
          icon="fa-cart-shopping"
          color="blue"
        />

        <x-ui.summary-card
          label="For Delivery"
          value="{{ $forDelivery ?? 0 }}"
          small="Waiting delivery"
          icon="fa-truck-fast"
          color="yellow"
        />

        <x-ui.summary-card
          label="Delivered"
          value="{{ $delivered ?? 0 }}"
          small="Supplier delivered"
          icon="fa-box"
          color="green"
        />

      </section>

      {{-- TABLE --}}
      <section class="table-card purchase-card requested-purchase-card">

        <div class="section-header">
          <div>
            <h2>Requested Purchase Records</h2>
            <p>Track purchase requests from Warehouse to Purchase Department</p>
          </div>
        </div>

        <form action="{{ route('requested-purchase') }}" method="GET" class="toolbar purchase-toolbar requested-purchase-toolbar">

          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input
              type="text"
              name="search"
              value="{{ request('search') }}"
              placeholder="Search PR no., JO no., bus, or item..."
            >
          </div>

          <div class="filter-group status-filter">
            <label>Status</label>

            <select name="status" onchange="this.form.submit()" class="status-select">
              <option value="All States" {{ request('status', 'All States') == 'All States' ? 'selected' : '' }}>
                All States
              </option>

              <option value="For Purchase" {{ request('status') == 'For Purchase' ? 'selected' : '' }}>
                For Purchase
              </option>

              <option value="Ordered" {{ request('status') == 'Ordered' ? 'selected' : '' }}>
                Ordered
              </option>

              <option value="For Pick-up" {{ request('status') == 'For Pick-up' ? 'selected' : '' }}>
                For Pick-up
              </option>

              <option value="For Delivery" {{ request('status') == 'For Delivery' ? 'selected' : '' }}>
                For Delivery
              </option>

              <option value="Delivered" {{ request('status') == 'Delivered' ? 'selected' : '' }}>
                Delivered
              </option>

              <option value="Picked Up" {{ request('status') == 'Picked Up' ? 'selected' : '' }}>
                Picked Up
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

                      @if($purchaseRequest->status === 'For Purchase')
                        <form
                          id="orderedForm-{{ $purchaseRequest->id }}"
                          action="{{ route('requested-purchase.ordered', $purchaseRequest->id) }}"
                          method="POST"
                        >
                          @csrf

                          <button
                            type="button"
                            class="edit open-status-confirm"
                            title="Mark as Ordered"
                            data-title="Mark as Ordered?"
                            data-message="This will mark {{ $purchaseRequest->pr_no }} as Ordered."
                            data-confirm-text="Yes, Mark Ordered"
                            data-form-id="orderedForm-{{ $purchaseRequest->id }}"
                          >
                            <i class="fa-solid fa-clock"></i>
                          </button>
                        </form>

                      @elseif($purchaseRequest->status === 'Ordered')
                        <form
                          id="forPickupForm-{{ $purchaseRequest->id }}"
                          action="{{ route('requested-purchase.for-pickup', $purchaseRequest->id) }}"
                          method="POST"
                        >
                          @csrf

                          <button
                            type="button"
                            class="edit open-status-confirm"
                            title="Mark as For Pick-up"
                            data-title="Mark as For Pick-up?"
                            data-message="This will mark {{ $purchaseRequest->pr_no }} as For Pick-up."
                            data-confirm-text="Yes, Continue"
                            data-form-id="forPickupForm-{{ $purchaseRequest->id }}"
                          >
                            <i class="fa-solid fa-box"></i>
                          </button>
                        </form>

                        <form
                          id="forDeliveryForm-{{ $purchaseRequest->id }}"
                          action="{{ route('requested-purchase.for-delivery', $purchaseRequest->id) }}"
                          method="POST"
                        >
                          @csrf

                          <button
                            type="button"
                            class="edit open-status-confirm"
                            title="Mark as For Delivery"
                            data-title="Mark as For Delivery?"
                            data-message="This will mark {{ $purchaseRequest->pr_no }} as For Delivery."
                            data-confirm-text="Yes, Continue"
                            data-form-id="forDeliveryForm-{{ $purchaseRequest->id }}"
                          >
                            <i class="fa-solid fa-truck-fast"></i>
                          </button>
                        </form>

                      @elseif($purchaseRequest->status === 'For Delivery')
                        <form
                          id="deliveredForm-{{ $purchaseRequest->id }}"
                          action="{{ route('requested-purchase.delivered', $purchaseRequest->id) }}"
                          method="POST"
                        >
                          @csrf

                          <button
                            type="button"
                            class="edit open-status-confirm"
                            title="Mark as Delivered"
                            data-title="Mark as Delivered?"
                            data-message="This will mark {{ $purchaseRequest->pr_no }} as Delivered."
                            data-confirm-text="Yes, Delivered"
                            data-form-id="deliveredForm-{{ $purchaseRequest->id }}"
                          >
                            <i class="fa-solid fa-box-open"></i>
                          </button>
                        </form>

                      @elseif($purchaseRequest->status === 'For Pick-up')
                        <form
                          id="pickedUpForm-{{ $purchaseRequest->id }}"
                          action="{{ route('requested-purchase.picked-up', $purchaseRequest->id) }}"
                          method="POST"
                        >
                          @csrf

                          <button
                            type="button"
                            class="edit open-status-confirm"
                            title="Mark as Picked Up"
                            data-title="Mark as Picked Up?"
                            data-message="This will mark {{ $purchaseRequest->pr_no }} as Picked Up."
                            data-confirm-text="Yes, Picked Up"
                            data-form-id="pickedUpForm-{{ $purchaseRequest->id }}"
                          >
                            <i class="fa-solid fa-boxes-packing"></i>
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

  {{-- STATUS CONFIRMATION MODAL --}}
  <div id="statusConfirmModal" class="delete-modal-overlay">
    <div class="delete-modal-box">

      <div class="delete-icon finish-icon">
        <i class="fa-solid fa-triangle-exclamation"></i>
      </div>

      <h2 id="statusConfirmTitle">Confirm Action</h2>

      <p id="statusConfirmMessage">
        Are you sure you want to continue?
      </p>

      <div class="delete-modal-actions">
        <button type="button" id="statusCancel" class="cancel-delete-btn">
          Cancel
        </button>

        <button type="button" id="statusConfirm" class="confirm-finish-btn">
          Yes, Continue
        </button>
      </div>

    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const validationErrorModal = document.getElementById('validationErrorModal');
      const closeValidationErrorModal = document.getElementById('closeValidationErrorModal');

      if (closeValidationErrorModal) {
        closeValidationErrorModal.addEventListener('click', () => {
          validationErrorModal.classList.remove('show');
        });
      }

      if (validationErrorModal) {
        validationErrorModal.addEventListener('click', (event) => {
          if (event.target === validationErrorModal) {
            validationErrorModal.classList.remove('show');
          }
        });
      }

      const statusModal = document.getElementById('statusConfirmModal');
      const statusTitle = document.getElementById('statusConfirmTitle');
      const statusMessage = document.getElementById('statusConfirmMessage');
      const statusCancel = document.getElementById('statusCancel');
      const statusConfirm = document.getElementById('statusConfirm');

      let selectedForm = null;

      document.querySelectorAll('.open-status-confirm').forEach((btn) => {
        btn.addEventListener('click', () => {
          const title = btn.dataset.title || 'Confirm Action';
          const message = btn.dataset.message || 'Are you sure you want to continue?';
          const confirmText = btn.dataset.confirmText || 'Yes, Continue';
          const formId = btn.dataset.formId;

          selectedForm = formId ? document.getElementById(formId) : null;

          if (statusTitle) statusTitle.textContent = title;
          if (statusMessage) statusMessage.textContent = message;
          if (statusConfirm) statusConfirm.textContent = confirmText;

          if (statusModal) statusModal.classList.add('show');
        });
      });

      if (statusCancel) {
        statusCancel.addEventListener('click', () => {
          selectedForm = null;
          if (statusModal) statusModal.classList.remove('show');
        });
      }

      if (statusConfirm) {
        statusConfirm.addEventListener('click', () => {
          if (selectedForm) {
            selectedForm.submit();
          }

          if (statusModal) statusModal.classList.remove('show');
        });
      }

      document.querySelectorAll('.delete-modal-overlay').forEach((modal) => {
        modal.addEventListener('click', (event) => {
          if (event.target === modal) {
            modal.classList.remove('show');
          }
        });
      });
    });
  </script>

</x-layout.app>