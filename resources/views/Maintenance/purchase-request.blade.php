<x-layout.app
  title="FROMS - Purchase Requests"
  :assets="[
    'resources/css/Maintenance/purchase-request.css',
    'resources/js/Maintenance/purchase-request.js'
  ]"
>

  @php
    $role = 'sub_admin';
  @endphp

  @if(session('success'))
    <div id="successModal" class="success-modal-overlay show">
      <div class="success-modal-box">
        <div class="success-icon">
          <i class="fa-solid fa-check"></i>
        </div>

        <h2>Success</h2>
        <p>{{ session('success') }}</p>

        <button type="button" id="closeSuccessModal" class="save-btn">
          Okay
        </button>
      </div>
    </div>
  @endif

  @if(session('error'))
    <div id="successModal" class="success-modal-overlay show">
      <div class="success-modal-box">
        <div class="delete-icon">
          <i class="fa-solid fa-triangle-exclamation"></i>
        </div>

        <h2>Error</h2>
        <p>{{ session('error') }}</p>

        <button type="button" id="closeSuccessModal" class="save-btn">
          Okay
        </button>
      </div>
    </div>
  @endif

  <div class="app">

    <x-layout.sidebar
      department="Maintenance"
      subtitle="Department Module"
      icon="fa-truck"
      user-name="R. Lim"
      user-role="Maintenance Admin"
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

      <header class="topbar">
        <div>
          <h1>Purchase Requests</h1>
          <p>Manage requested parts and maintenance purchasing records</p>
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
            label="Draft"
            value="{{ $draft }}"
            small="Unfinished requests"
            icon="fa-file"
            color="gray"
          />

          <x-ui.summary-card
            label="Submitted"
            value="{{ $submitted }}"
            small="Waiting approval"
            icon="fa-paper-plane"
            color="yellow"
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

        <form method="GET" action="{{ route('purchase-requests') }}" class="toolbar purchase-toolbar">
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
              <option value="All Statuses" {{ request('status') == 'All Statuses' ? 'selected' : '' }}>All Statuses</option>
              <option value="Draft" {{ request('status') == 'Draft' ? 'selected' : '' }}>Draft</option>
              <option value="Submitted" {{ request('status') == 'Submitted' ? 'selected' : '' }}>Submitted</option>
              <option value="Approved" {{ request('status') == 'Approved' ? 'selected' : '' }}>Approved</option>
              <option value="Rejected" {{ request('status') == 'Rejected' ? 'selected' : '' }}>Rejected</option>
              <option value="For Purchase" {{ request('status') == 'For Purchase' ? 'selected' : '' }}>For Purchase</option>
              <option value="Pending Purchase" {{ request('status') == 'Pending Purchase' ? 'selected' : '' }}>Pending Purchase</option>
              <option value="Delivering" {{ request('status') == 'Delivering' ? 'selected' : '' }}>Delivering</option>
              <option value="Delivered" {{ request('status') == 'Delivered' ? 'selected' : '' }}>Delivered</option>
              <option value="Issued" {{ request('status') == 'Issued' ? 'selected' : '' }}>Issued</option>
            </select>
          </div>

          <button type="button" class="primary-btn" id="openPrModal">
            <i class="fa-solid fa-plus"></i>
            New PR
          </button>
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
                    @php
                      $badgeClass = match($pr->status) {
                        'Draft' => 'draft',
                        'Submitted' => 'submitted',
                        'Approved' => 'approved',
                        'Rejected' => 'rejected',
                        'For Purchase' => 'for-purchase',
                        'Pending Purchase' => 'pending-purchase',
                        'Delivering' => 'delivering',
                        'Delivered' => 'delivered',
                        'Issued' => 'issued',
                        default => 'draft'
                      };
                    @endphp

                    <span class="badge {{ $badgeClass }}">
                      {{ $pr->status }}
                    </span>
                  </td>

                  <td>{{ $pr->created_at->format('M d, Y') }}</td>

                  <td>
                    <div class="actions">

                      <button
                        type="button"
                        class="edit open-edit-pr-modal"
                        title="View / Edit"
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
                        data-for-purchase-url="{{ route('purchase-requests.for-purchase', $pr->id) }}"
                        data-pending-purchase-url="{{ route('purchase-requests.pending-purchase', $pr->id) }}"
                        data-delivering-url="{{ route('purchase-requests.delivering', $pr->id) }}"
                        data-delivered-url="{{ route('purchase-requests.delivered', $pr->id) }}"
                        data-issue-url="{{ route('purchase-requests.issue', $pr->id) }}"
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
                          class="delete open-delete-pr-modal"
                          data-id="{{ $pr->id }}"
                          data-pr-no="{{ $pr->pr_no }}"
                          title="Delete"
                        >
                          <i class="fa-solid fa-trash"></i>
                        </button>
                      </form>

                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="8" style="text-align:center; padding: 30px;">
                    No purchase requests found.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="table-footer">
          <p>
            Showing {{ $purchaseRequests->firstItem() ?? 0 }} to {{ $purchaseRequests->lastItem() ?? 0 }} of {{ $purchaseRequests->total() }} entries
          </p>

          <div class="pagination">
            {{ $purchaseRequests->links() }}
          </div>
        </div>

      </section>

    </main>
  </div>

  {{-- NEW PR MODAL --}}
  <div id="prModal" class="modal-overlay">
    <div class="modal-box wide-modal">

      <div class="modal-header">
        <h2>New Purchase Request</h2>

        <button type="button" id="closePrModal" class="close-btn">
          &times;
        </button>
      </div>

      <form action="{{ route('purchase-requests.store') }}" method="POST" class="job-form wide-form">
        @csrf

        <div class="form-section-title full-width">
          <h3>Purchase Request Details</h3>
          <p>Enter the requested parts information for a job order.</p>
        </div>

        <div class="form-group">
          <label>PR No.</label>
          <input type="text" name="pr_no" placeholder="Example: PR-2026-0001" required>
        </div>

        <div class="form-group">
          <label>JO No.</label>
          <select name="job_order_no" id="jobOrderSelect" required>
            <option value="">Select Job Order</option>

            @foreach($jobOrders as $jobOrder)
              <option value="{{ $jobOrder->job_order_no }}" data-bus="{{ $jobOrder->bus_no }}">
                {{ $jobOrder->job_order_no }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="form-group">
          <label>Bus #</label>
          <input
            type="text"
            name="bus_no"
            id="busNoInput"
            placeholder="Auto-filled from Job Order"
            readonly
            required
          >
        </div>

        <div class="form-group">
          <label>Status</label>
          <input type="text" value="Automatic: Draft or Submitted" readonly>
        </div>

        <div class="form-group full-width">
          <label>Requested Item / Part</label>
          <input type="text" name="item" placeholder="Example: Engine Oil Filter" required>
        </div>

        <div class="form-section-divider full-width">
          <span>Request Details</span>
        </div>

        <div class="form-row time-section full-width">
          <div class="form-group">
            <label>Quantity</label>
            <input type="number" name="quantity" min="1" placeholder="Example: 2" required>
          </div>

          <div class="form-group">
            <label>Remarks</label>
            <input type="text" name="remarks" placeholder="Optional remarks...">
          </div>
        </div>

        <div class="modal-actions full-width">
          <button type="button" id="cancelPrModal" class="cancel-btn">
            Cancel
          </button>

          <button type="submit" name="submit_action" value="draft" class="cancel-btn">
            Save as Draft
          </button>

          <button type="submit" name="submit_action" value="submit" class="save-btn">
            Submit PR
          </button>
        </div>
      </form>

    </div>
  </div>

  {{-- EDIT PR MODAL --}}
  <div id="editPrModal" class="modal-overlay">
    <div class="modal-box wide-modal">

      <div class="modal-header">
        <h2>Purchase Request Details</h2>

        <button type="button" id="closeEditPrModal" class="close-btn">
          &times;
        </button>
      </div>

      <form id="editPrForm" method="POST" class="job-form wide-form">
        @csrf
        @method('PUT')

        <div class="form-section-title full-width">
          <h3>Editable PR Information</h3>
          <p>Review and update the selected purchase request.</p>
        </div>

        <div class="form-group">
          <label>PR No.</label>
          <input type="text" name="pr_no" id="edit_pr_no" required>
        </div>

        <div class="form-group">
          <label>JO No.</label>
          <select name="job_order_no" id="edit_job_order_no" required>
            <option value="">Select Job Order</option>

            @foreach($jobOrders as $jobOrder)
              <option value="{{ $jobOrder->job_order_no }}" data-bus="{{ $jobOrder->bus_no }}">
                {{ $jobOrder->job_order_no }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="form-group">
          <label>Bus #</label>
          <input
            type="text"
            name="bus_no"
            id="edit_bus_no"
            placeholder="Auto-filled from Job Order"
            readonly
            required
          >
        </div>

        <div class="form-group">
          <label>Status</label>
          <input type="text" id="edit_status_display" readonly>
        </div>

        <div class="form-group full-width">
          <label>Requested Item / Part</label>
          <input type="text" name="item" id="edit_item" required>
        </div>

        <div class="form-section-divider full-width">
          <span>Request Details</span>
        </div>

        <div class="form-row time-section full-width">
          <div class="form-group">
            <label>Quantity</label>
            <input type="number" name="quantity" id="edit_quantity" min="1" required>
          </div>

          <div class="form-group">
            <label>Remarks</label>
            <input type="text" name="remarks" id="edit_remarks">
          </div>
        </div>

        <div class="modal-actions full-width">
          <button type="button" id="cancelEditPrModal" class="cancel-btn">
            Cancel
          </button>

          <button type="submit" name="submit_action" value="draft" class="cancel-btn" id="saveDraftEditBtn">
            Save Changes
          </button>

          <button type="submit" name="submit_action" value="submit" class="save-btn" id="submitEditBtn">
            Submit PR
          </button>
        </div>
      </form>

      <div class="modal-actions full-width pr-approval-actions" id="prApprovalActions" style="display: none;">
        <form id="approvePrForm" method="POST">
          @csrf
          <button type="submit" class="approve-action-btn">
            <i class="fa-solid fa-check"></i>
            Approve
          </button>
        </form>

        <form id="rejectPrForm" method="POST">
          @csrf
          <input type="hidden" name="remarks" value="Rejected by sub admin">
          <button type="submit" class="reject-action-btn">
            <i class="fa-solid fa-xmark"></i>
            Reject
          </button>
        </form>
      </div>

      <div class="modal-actions full-width warehouse-actions" id="warehouseActions" style="display: none;">
        <form id="issuePrForm" method="POST">
          @csrf
          <button type="submit" class="approve-action-btn">
            <i class="fa-solid fa-box-open"></i>
            Issue Parts
          </button>
        </form>

        <form id="forPurchasePrForm" method="POST">
          @csrf
          <button type="submit" class="purchase-action-btn">
            <i class="fa-solid fa-cart-shopping"></i>
            For Purchase
          </button>
        </form>
      </div>

      <div class="modal-actions full-width purchase-actions" id="purchaseActions" style="display: none;">
        <form id="pendingPurchasePrForm" method="POST">
          @csrf
          <button type="submit" class="purchase-action-btn">
            <i class="fa-solid fa-clock"></i>
            Pending Purchase
          </button>
        </form>

        <form id="deliveringPrForm" method="POST">
          @csrf
          <button type="submit" class="purchase-action-btn">
            <i class="fa-solid fa-truck-fast"></i>
            Delivering
          </button>
        </form>

        <form id="deliveredPrForm" method="POST">
          @csrf
          <button type="submit" class="approve-action-btn">
            <i class="fa-solid fa-box"></i>
            Delivered
          </button>
        </form>
      </div>

    </div>
  </div>

  {{-- DELETE MODAL --}}
  <div id="deletePrModal" class="delete-modal-overlay">
    <div class="delete-modal-box">
      <div class="delete-icon">
        <i class="fa-solid fa-triangle-exclamation"></i>
      </div>

      <h2>Delete Purchase Request?</h2>

      <p>
        Are you sure you want to delete
        <strong id="deletePrNo">this purchase request</strong>?
        This action can’t be undone.
      </p>

      <div class="delete-modal-actions">
        <button type="button" id="cancelDeletePr" class="cancel-btn">
          Cancel
        </button>

        <button type="button" id="confirmDeletePr" class="danger-btn">
          Yes, Delete
        </button>
      </div>
    </div>
  </div>

</x-layout.app>