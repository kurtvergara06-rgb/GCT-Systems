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
        subtitle="Purchase Department request inbox from Warehouse"
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

      <section class="stats-grid">

        <x-ui.summary-card
          label="Total Requests"
          value="{{ $totalRequests ?? 0 }}"
          small="Purchase inbox"
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
          value="{{ ($delivered ?? 0) + ($pickedUp ?? 0) }}"
          small="Ready for warehouse issue"
          icon="fa-box"
          color="green"
        />

      </section>

      <section class="table-card purchase-card requested-purchase-card">

        <div class="section-header">
          <div>
            <h2>Requested Purchase Records</h2>
            <p>Only purchase-process PRs are shown here. Issued requests are hidden.</p>
          </div>
        </div>

        <form action="{{ route('requested-purchase') }}" method="GET" class="toolbar purchase-toolbar requested-purchase-toolbar">

          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input
              type="text"
              name="search"
              value="{{ request('search') }}"
              placeholder="Search PR no., JO no., bus, item, or status..."
            >
          </div>

          <div class="filter-group status-filter">
            <label>Status</label>

            <select name="status" onchange="this.form.submit()" class="status-select">
              <option value="All States" {{ request('status', 'All States') == 'All States' ? 'selected' : '' }}>
                All States
              </option>

              @foreach($statuses as $status)
                <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                  {{ $status }}
                </option>
              @endforeach
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
                          id="createPoForm-{{ $purchaseRequest->id }}"
                          action="{{ route('requested-purchase.create-po', $purchaseRequest->id) }}"
                          method="POST"
                        >
                          @csrf

                          <button
                            type="button"
                            class="edit open-status-confirm"
                            title="Create PO"
                            data-title="Create Purchase Order?"
                            data-message="This will open the PO form for {{ $purchaseRequest->pr_no }}. You can fill up the supplier and cost details before saving."
                            data-confirm-text="Continue"
                            data-form-id="createPoForm-{{ $purchaseRequest->id }}"
                          >
                            <i class="fa-solid fa-file-circle-plus"></i>
                          </button>
                        </form>
                      @else
                        <span class="no-action">Manage in PO</span>
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
      function closeModal(modal) {
        if (!modal) return;

        modal.classList.remove('show');
        modal.style.display = 'none';
      }

      function openModal(modal) {
        if (!modal) return;

        modal.classList.add('show');
        modal.style.display = 'flex';
      }

      function closeFeedbackModal(button) {
        const modal =
          button.closest('.success-modal-overlay') ||
          button.closest('.delete-modal-overlay') ||
          button.closest('.modal-overlay') ||
          button.closest('[class*="modal-overlay"]');

        closeModal(modal);
      }

      document
        .querySelectorAll(
          '.close-feedback-modal, .success-ok-btn, .btn-ok, [data-close-feedback], .success-modal-overlay button'
        )
        .forEach(function (button) {
          button.addEventListener('click', function () {
            closeFeedbackModal(button);
          });
        });

      document
        .querySelectorAll('.success-modal-overlay, .modal-overlay')
        .forEach(function (modal) {
          modal.addEventListener('click', function (event) {
            if (event.target === modal) {
              closeModal(modal);
            }
          });
        });

      const validationErrorModal = document.getElementById('validationErrorModal');
      const closeValidationErrorModal = document.getElementById('closeValidationErrorModal');

      if (closeValidationErrorModal && validationErrorModal) {
        closeValidationErrorModal.addEventListener('click', function () {
          closeModal(validationErrorModal);
        });
      }

      if (validationErrorModal) {
        validationErrorModal.addEventListener('click', function (event) {
          if (event.target === validationErrorModal) {
            closeModal(validationErrorModal);
          }
        });
      }

      const statusModal = document.getElementById('statusConfirmModal');
      const statusTitle = document.getElementById('statusConfirmTitle');
      const statusMessage = document.getElementById('statusConfirmMessage');
      const statusCancel = document.getElementById('statusCancel');
      const statusConfirm = document.getElementById('statusConfirm');

      let selectedForm = null;

      document.querySelectorAll('.open-status-confirm').forEach(function (btn) {
        btn.addEventListener('click', function () {
          const title = btn.dataset.title || 'Confirm Action';
          const message = btn.dataset.message || 'Are you sure you want to continue?';
          const confirmText = btn.dataset.confirmText || 'Yes, Continue';
          const formId = btn.dataset.formId;

          selectedForm = formId ? document.getElementById(formId) : null;

          if (statusTitle) {
            statusTitle.textContent = title;
          }

          if (statusMessage) {
            statusMessage.textContent = message;
          }

          if (statusConfirm) {
            statusConfirm.textContent = confirmText;
          }

          openModal(statusModal);
        });
      });

      if (statusCancel) {
        statusCancel.addEventListener('click', function () {
          selectedForm = null;
          closeModal(statusModal);
        });
      }

      if (statusConfirm) {
        statusConfirm.addEventListener('click', function () {
          if (selectedForm) {
            selectedForm.submit();
          }

          closeModal(statusModal);
        });
      }

      document.querySelectorAll('.delete-modal-overlay').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
          if (event.target === modal) {
            closeModal(modal);
          }
        });
      });

      document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
          return;
        }

        document
          .querySelectorAll('.success-modal-overlay, .delete-modal-overlay, .modal-overlay')
          .forEach(function (modal) {
            closeModal(modal);
          });
      });
    });
  </script>

</x-layout.app>