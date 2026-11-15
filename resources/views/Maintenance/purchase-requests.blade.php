<x-layout.app
  title="FROMS - Purchase Requests"
  :assets="[
    'resources/css/Main-style/main.css',
    'resources/css/Main-style/sidebar.css',
    'resources/css/Maintenance/purchase-request.css',
    'resources/js/Main-style/sidebar.js',
    'resources/js/Maintenance/purchase-request.js'
  ]"
>

  @php
    $statuses = $statuses ?? [
      'Submitted',
      'Approved',
      'Rejected',
      'For Purchase',
      'Ordered',
      'For Pick-up',
      'For Delivery',
      'Delivered',
      'Picked Up',
      'Issued',
    ];

    $submitted = $submitted ?? 0;
    $rejected = $rejected ?? 0;
    $approved = $approved ?? 0;
    $issued = $issued ?? 0;

    $isMaintenanceAdmin = $isMaintenanceAdmin ?? false;
  @endphp

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

  @if($errors->any())
    <div id="validationErrorModal" class="delete-modal-overlay show active" style="display: flex;">
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

  <div class="app">

    <x-layout.sidebar
      department="Maintenance"
      subtitle="Department Module"
      icon="fa-truck"
      :items="[
        ['label' => 'Dashboard', 'route' => 'maintenance-dashboard', 'icon' => 'fa-table-cells-large'],
        ['label' => 'Job Orders', 'route' => 'job-orders', 'icon' => 'fa-clipboard-list'],
        ['label' => 'Mechanic List', 'route' => 'mechanic-list', 'icon' => 'fa-bus'],
        ['label' => 'PMS Scheduling', 'route' => 'PMS-Scheduling', 'icon' => 'fa-calendar-check'],
        ['label' => 'Purchase Requests', 'route' => 'purchase-requests', 'icon' => 'fa-file-invoice'],
        ['label' => 'Fuel Reports', 'route' => 'fuel-reports', 'icon' => 'fa-gas-pump'],
        ['label' => 'Settings', 'route' => 'settings', 'icon' => 'fa-gear'],
      ]"
    />

    <main class="main">

      <x-layout.topbar
        title="Purchase Requests"
        subtitle="Manage requested parts and maintenance purchasing records"
        notification-count="6"
      />

      <section class="stats-grid">

        <x-ui.summary-card
          label="Submitted"
          value="{{ $submitted }}"
          small="Waiting approval"
          icon="fa-paper-plane"
          color="blue"
        />

        <x-ui.summary-card
          label="Rejected"
          value="{{ $rejected }}"
          small="Rejected requests"
          icon="fa-xmark"
          color="red"
        />

        <x-ui.summary-card
          label="Approved"
          value="{{ $approved }}"
          small="Approved requests"
          icon="fa-check"
          color="purple"
        />

        <x-ui.summary-card
          label="Issued"
          value="{{ $issued }}"
          small="Released parts"
          icon="fa-box-open"
          color="green"
        />

      </section>

      <section class="table-card purchase-card">

        <div class="section-header">
          <div>
            <h2>Purchase Request Records</h2>
            <p>Track requested parts, approval status, warehouse issuance, and purchasing progress</p>
          </div>
        </div>

        <form action="{{ route('purchase-requests') }}" method="GET" class="toolbar purchase-toolbar fixed-purchase-toolbar">

          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>

            <input
              type="text"
              name="search"
              value="{{ request('search') }}"
              placeholder="Search PR no., JO no., bus no., item..."
            >
          </div>

          <div class="filter-group">
            <label for="prStatusFilter">Status</label>

            <select
              name="status"
              id="prStatusFilter"
              class="pr-status-select"
              onchange="this.form.submit()"
            >
              <option value="All Statuses" {{ request('status', 'All Statuses') === 'All Statuses' ? 'selected' : '' }}>
                All Statuses
              </option>

              @foreach($statuses as $status)
                <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                  {{ $status }}
                </option>
              @endforeach
            </select>
          </div>

          <button type="button" id="openPrModal" class="primary-btn compact-new-pr-btn">
            <i class="fa-solid fa-plus"></i>
            New PR
          </button>

        </form>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>PR #</th>
                <th>JO #</th>
                <th>Bus #</th>
                <th>Requested Item / Part</th>
                <th>Qty</th>
                <th class="status-col">Status</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              @forelse($purchaseRequests as $pr)
                @php
                  $statusClass = strtolower(str_replace([' ', '/'], ['-', '-'], $pr->status));

                  $firstRequestedItem = trim(explode(',', $pr->item ?? '')[0] ?? '');

                  if (str_contains($firstRequestedItem, ' - Qty:')) {
                    $firstRequestedItem = trim(explode(' - Qty:', $firstRequestedItem)[0] ?? $firstRequestedItem);
                  }
                @endphp

                <tr>
                  <td>{{ $pr->pr_no }}</td>
                  <td>{{ $pr->job_order_no }}</td>
                  <td>{{ $pr->bus_no }}</td>
                  <td>{{ $firstRequestedItem ?: '—' }}</td>
                  <td>{{ $pr->quantity }}</td>

                  <td class="status-col">
                    <span class="pr-status-badge {{ $statusClass }}">
                      {{ $pr->status }}
                    </span>
                  </td>

                  <td>
                    {{ $pr->created_at ? $pr->created_at->format('m/d/y | h:i A') : '—' }}
                  </td>

                  <td>
                    <div class="actions">

                      <button
                        type="button"
                        class="action-btn view open-view-pr-modal"
                        title="View"
                        data-id="{{ $pr->id }}"
                        data-pr-no="{{ $pr->pr_no }}"
                        data-job-order-no="{{ $pr->job_order_no }}"
                        data-bus-no="{{ $pr->bus_no }}"
                        data-item="{{ $pr->item }}"
                        data-quantity="{{ $pr->quantity }}"
                        data-status="{{ $pr->status }}"
                        data-remarks="{{ $pr->remarks }}"
                        data-update-url="{{ route('purchase-requests.update', $pr->id) }}"
                        data-approve-url="{{ route('purchase-requests.approve', $pr->id) }}"
                        data-reject-url="{{ route('purchase-requests.reject', $pr->id) }}"
                        data-can-approve="{{ $isMaintenanceAdmin ? '1' : '0' }}"
                      >
                        <i class="fa-solid fa-eye"></i>
                      </button>

                      <button
                        type="button"
                        class="action-btn edit open-edit-pr-modal"
                        title="Edit"
                        data-id="{{ $pr->id }}"
                        data-pr-no="{{ $pr->pr_no }}"
                        data-job-order-no="{{ $pr->job_order_no }}"
                        data-bus-no="{{ $pr->bus_no }}"
                        data-item="{{ $pr->item }}"
                        data-quantity="{{ $pr->quantity }}"
                        data-status="{{ $pr->status }}"
                        data-remarks="{{ $pr->remarks }}"
                        data-update-url="{{ route('purchase-requests.update', $pr->id) }}"
                        data-approve-url="{{ route('purchase-requests.approve', $pr->id) }}"
                        data-reject-url="{{ route('purchase-requests.reject', $pr->id) }}"
                        data-can-approve="{{ $isMaintenanceAdmin ? '1' : '0' }}"
                      >
                        <i class="fa-solid fa-pen-to-square"></i>
                      </button>

                      <form
                        id="deletePrForm-{{ $pr->id }}"
                        action="{{ route('purchase-requests.destroy', $pr->id) }}"
                        method="POST"
                      >
                        @csrf
                        @method('DELETE')

                        <button
                          type="button"
                          class="action-btn delete open-delete-pr-modal"
                          title="Delete"
                          data-id="{{ $pr->id }}"
                          data-pr-no="{{ $pr->pr_no }}"
                        >
                          <i class="fa-solid fa-trash"></i>
                        </button>
                      </form>

                    </div>
                  </td>
                </tr>
              @empty
                <x-ui.empty-row
                  colspan="8"
                  message="No purchase requests found."
                />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$purchaseRequests" />

      </section>

    </main>
  </div>

  {{-- NEW PR MODAL --}}
  <div
    id="prModal"
    class="modal-overlay {{ isset($selectedJobOrder) && $selectedJobOrder ? 'show active' : '' }}"
    style="{{ isset($selectedJobOrder) && $selectedJobOrder ? 'display: flex;' : '' }}"
  >
    <div class="modal-box pr-jo-style-modal">

      <div class="pr-jo-modal-header">
        <div>
          <h2>New Purchase Request</h2>
          <p>Create a purchase request from a selected job order.</p>
        </div>

        <button type="button" id="closePrModal" class="pr-jo-close-btn">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <form action="{{ route('purchase-requests.store') }}" method="POST" class="pr-jo-form">
        @csrf

        <div class="pr-jo-section-title full-width">
          <h3>Purchase Request Information</h3>
          <p>Select a job order and review the requested parts.</p>
        </div>

        <div class="pr-jo-form-group">
          <label>PR No.</label>
          <input
            type="text"
            name="pr_no_display"
            value="{{ $nextPrNo ?? '' }}"
            readonly
          >
        </div>

        <div class="pr-jo-form-group">
          <label>JO No.</label>
          <select name="job_order_no" id="jobOrderSelect" required>
            <option value="">Select Job Order</option>

            @foreach($jobOrders ?? [] as $jobOrder)
              <option
                value="{{ $jobOrder->job_order_no }}"
                data-bus="{{ $jobOrder->bus_no }}"
                data-parts="{{ $jobOrder->part_needed }}"
                {{ isset($selectedJobOrder) && $selectedJobOrder && $selectedJobOrder->id === $jobOrder->id ? 'selected' : '' }}
              >
                {{ $jobOrder->job_order_no }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="pr-jo-form-group">
          <label>Bus #</label>
          <input
            type="text"
            name="bus_no"
            id="busNoInput"
            value="{{ isset($selectedJobOrder) && $selectedJobOrder ? $selectedJobOrder->bus_no : '' }}"
            placeholder="Auto-filled from Job Order"
            required
          >
        </div>

        <div class="pr-jo-form-group">
          <label>Status</label>
          <input type="text" value="Submitted" readonly>
        </div>

        <div class="pr-jo-form-group full-width">
          <label>Requested Parts</label>

          <div
            id="newPrPartsContainer"
            class="pr-parts-container"
            data-initial-parts="{{ isset($selectedJobOrder) && $selectedJobOrder ? $selectedJobOrder->part_needed : '' }}"
          >
            {{-- JS creates rows here --}}
          </div>

          <small>Add each part separately so Warehouse can check inventory correctly.</small>
        </div>

        <div class="pr-jo-form-group full-width">
          <label>Remarks</label>
          <input
            type="text"
            name="remarks"
            value="{{ isset($selectedJobOrder) && $selectedJobOrder ? 'Created from Job Order ' . $selectedJobOrder->job_order_no : '' }}"
            placeholder="Optional remarks..."
          >
        </div>

        <div class="pr-jo-actions full-width">
          <button type="button" id="cancelPrModal" class="pr-jo-cancel-btn">
            Cancel
          </button>

          <button type="submit" class="pr-jo-save-btn">
            Create PR
          </button>
        </div>

      </form>
    </div>
  </div>

  {{-- EDIT / VIEW PR MODAL --}}
  <div id="editPrModal" class="modal-overlay">
    <div class="modal-box pr-jo-style-modal">

      <div class="pr-jo-modal-header">
        <div>
          <h2>Purchase Request Details</h2>
          <p>Review and update the selected purchase request.</p>
        </div>

        <button type="button" id="closeEditPrModal" class="pr-jo-close-btn">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <form id="editPrForm" method="POST" action="#" class="pr-jo-form">
        @csrf
        @method('PUT')

        <div class="pr-jo-section-title full-width">
          <h3>Editable PR Information</h3>
          <p id="editPrDescription">You can edit this purchase request information.</p>
        </div>

        <div class="pr-jo-form-group">
          <label>PR No.</label>
          <input type="text" name="pr_no" id="edit_pr_no" readonly>
        </div>

        <div class="pr-jo-form-group">
          <label>JO No.</label>
          <input type="text" name="job_order_no" id="edit_job_order_no" readonly>
        </div>

        <div class="pr-jo-form-group">
          <label>Bus #</label>
          <input type="text" name="bus_no" id="edit_bus_no" readonly>
        </div>

        <div class="pr-jo-form-group">
          <label>Status</label>
          <input type="text" id="edit_status_display" readonly>
        </div>

        <div class="pr-jo-form-group full-width">
          <label>Requested Parts</label>

          <div id="editPrPartsContainer" class="pr-parts-container">
            {{-- JS creates rows here --}}
          </div>
        </div>

        <div class="pr-jo-form-group full-width">
          <label>Remarks</label>
          <input
            type="text"
            name="remarks"
            id="edit_remarks"
            placeholder="Optional remarks..."
          >
        </div>

        <div class="pr-modal-footer full-width" id="editPrMainActions">
          <div class="pr-modal-left-actions">
            <button type="button" id="cancelEditPrModal" class="pr-jo-cancel-btn">
              Cancel
            </button>

            <button type="submit" class="pr-jo-save-btn" id="submitEditBtn">
              Save Changes
            </button>
          </div>

          <div
            class="pr-approval-actions"
            id="prApprovalActions"
            style="display: none;"
            data-can-approve="{{ $isMaintenanceAdmin ? '1' : '0' }}"
          >
            @if($isMaintenanceAdmin)
              <form id="approvePrForm" method="POST" action="#">
                @csrf

                <button type="submit" class="approve-action-btn">
                  <i class="fa-solid fa-check"></i>
                  Approve
                </button>
              </form>

              <form id="rejectPrForm" method="POST" action="#">
                @csrf

                <input type="hidden" name="remarks" value="Rejected by Maintenance Head">

                <button type="submit" class="reject-action-btn">
                  <i class="fa-solid fa-xmark"></i>
                  Reject
                </button>
              </form>
            @endif
          </div>
        </div>

        <div class="pr-jo-actions full-width" id="viewOnlyActions" style="display: none;">
          <button type="button" id="closeViewOnlyPr" class="pr-jo-cancel-btn">
            Close
          </button>
        </div>

      </form>

    </div>
  </div>

  {{-- DELETE MODAL --}}
  <x-ui.action-buttom-modal
    mode="delete"
    id="deletePrModal"
    delete-title="Delete Purchase Request?"
    delete-message="Are you sure you want to delete"
    name-id="deletePrNo"
    cancel-id="cancelDeletePr"
    confirm-id="confirmDeletePr"
  />

</x-layout.app>