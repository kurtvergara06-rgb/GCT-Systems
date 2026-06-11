<x-layout.app
  title="FROMS - Purchase Requests"
  :assets="[
    'resources/css/Main-style/main.css',
    'resources/css/Main-style/sidebar.css',
    'resources/css/Maintenance/purchase-request.css',
    'resources/js/Maintenance/purchase-request.js'
  ]"
>

  {{-- FEEDBACK MODALS --}}
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
    <div id="validationErrorModal" class="delete-modal-overlay show">
      <div class="delete-modal-box">

        <div class="delete-icon">
          <i class="fa-solid fa-triangle-exclamation"></i>
        </div>

        <h2>Form Error</h2>

        <p>
          Please check the form. Some required information is missing.
        </p>

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

        <x-ui.summary-card
          label="Issued"
          value="{{ $issued }}"
          small="Released parts"
          icon="fa-box-open"
          color="blue"
        />

      </section>

      <section class="table-card purchase-card">

        <div class="section-header">
          <div>
            <h2>Purchase Request Records</h2>
            <p>Track requested parts, approval status, warehouse issuance, and purchasing progress</p>
          </div>
        </div>

        <x-ui.table-toolbar
          :action="route('purchase-requests')"
          class="toolbar purchase-toolbar"
          search-placeholder="Search PR no., JO no., bus, or item..."
          button-id="openPrModal"
          button-label="New PR"
        >
          <div class="filter-group">
            <label>Status</label>

            <select name="status" onchange="this.form.submit()">
              <option value="All Statuses" {{ request('status') == 'All Statuses' ? 'selected' : '' }}>
                All Statuses
              </option>

              <option value="Submitted" {{ request('status') == 'Submitted' ? 'selected' : '' }}>
                Submitted
              </option>

              <option value="Approved" {{ request('status') == 'Approved' ? 'selected' : '' }}>
                Approved
              </option>

              <option value="Rejected" {{ request('status') == 'Rejected' ? 'selected' : '' }}>
                Rejected
              </option>

              <option value="For Purchase" {{ request('status') == 'For Purchase' ? 'selected' : '' }}>
                For Purchase
              </option>

              <option value="Delivered" {{ request('status') == 'Delivered' ? 'selected' : '' }}>
                Delivered
              </option>

              <option value="Issued" {{ request('status') == 'Issued' ? 'selected' : '' }}>
                Issued
              </option>
            </select>
          </div>
        </x-ui.table-toolbar>

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
                @php
                  $viewOnlyStatuses = [
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

                  $isViewOnly = in_array($pr->status, $viewOnlyStatuses);
                @endphp

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

                      <x-ui.action-buttom-modal
                        class="{{ $isViewOnly ? 'view open-edit-pr-modal' : 'edit open-edit-pr-modal' }}"
                        title="{{ $isViewOnly ? 'View' : 'View / Edit' }}"
                        icon="{{ $isViewOnly ? 'fa-eye' : 'fa-pen-to-square' }}"
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
                        data-delivered-url="{{ route('purchase-requests.delivered', $pr->id) }}"
                        data-issue-url="{{ route('purchase-requests.issue', $pr->id) }}"
                      />

                      <form
                        id="deletePrForm-{{ $pr->id }}"
                        action="{{ route('purchase-requests.destroy', $pr->id) }}"
                        method="POST"
                      >
                        @csrf
                        @method('DELETE')

                        <x-ui.action-buttom-modal
                          class="delete open-delete-pr-modal"
                          title="Delete"
                          icon="fa-trash"
                          data-id="{{ $pr->id }}"
                          data-pr-no="{{ $pr->pr_no }}"
                        />
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
          <p>Select a job order. The bus number, part, and quantity will be filled automatically.</p>
        </div>

        <div class="form-group">
          <label>PR No.</label>
          <input
            type="text"
            name="pr_no_display"
            value="{{ $nextPrNo }}"
            readonly
          >
        </div>

        <div class="form-group">
          <label>JO No.</label>
          <select name="job_order_no" id="jobOrderSelect" required>
            <option value="">Select Job Order</option>

            @foreach($jobOrders as $jobOrder)
              <option
                value="{{ $jobOrder->job_order_no }}"
                data-bus="{{ $jobOrder->bus_no }}"
                data-parts="{{ $jobOrder->part_needed }}"
              >
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
          <input
            type="text"
            value="Automatic: Submitted"
            readonly
          >
        </div>

        <div class="form-group full-width">
          <label>Requested Item / Part</label>
          <input
            type="text"
            name="item"
            id="partInput"
            placeholder="Auto-filled from selected Job Order"
            readonly
            required
          >
        </div>

        <div class="form-group">
          <label>Quantity</label>
          <input
            type="number"
            name="quantity"
            id="quantityInput"
            min="1"
            placeholder="Auto-filled"
            readonly
            required
          >
        </div>

        <div class="form-group">
          <label>Remarks</label>
          <input
            type="text"
            name="remarks"
            placeholder="Optional remarks..."
          >
        </div>

        <div class="modal-actions full-width">
          <button type="button" id="cancelPrModal" class="cancel-btn">
            Cancel
          </button>

          <button type="submit" name="submit_action" value="submit" class="save-btn">
            Create PR
          </button>
        </div>
      </form>

    </div>
  </div>

  {{-- EDIT / VIEW PR MODAL --}}
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
          <p id="editPrDescription">Review and update the selected purchase request.</p>
        </div>

        <div class="form-group">
          <label>PR No.</label>
          <input
            type="text"
            name="pr_no"
            id="edit_pr_no"
            readonly
            required
          >
        </div>

        <div class="form-group">
          <label>JO No.</label>
          <select name="job_order_no" id="edit_job_order_no" required>
            <option value="">Select Job Order</option>

            @foreach($jobOrders as $jobOrder)
              <option
                value="{{ $jobOrder->job_order_no }}"
                data-bus="{{ $jobOrder->bus_no }}"
                data-parts="{{ $jobOrder->part_needed }}"
              >
                {{ $jobOrder->job_order_no }}
              </option>
            @endforeach

            @foreach($purchaseRequests as $prOption)
              <option
                value="{{ $prOption->job_order_no }}"
                data-bus="{{ $prOption->bus_no }}"
                data-parts="{{ $prOption->item }} - Qty: {{ $prOption->quantity }}"
                class="existing-pr-option"
              >
                {{ $prOption->job_order_no }}
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
            readonly
            required
          >
        </div>

        <div class="form-group">
          <label>Status</label>
          <input
            type="text"
            id="edit_status_display"
            readonly
          >
        </div>

        <div class="form-group full-width">
          <label>Requested Item / Part</label>
          <input
            type="text"
            name="item"
            id="edit_item"
            readonly
            required
          >
        </div>

        <div class="form-group">
          <label>Quantity</label>
          <input
            type="number"
            name="quantity"
            id="edit_quantity"
            min="1"
            readonly
            required
          >
        </div>

        <div class="form-group">
          <label>Remarks</label>
          <input
            type="text"
            name="remarks"
            id="edit_remarks"
          >
        </div>

        <div class="modal-actions full-width" id="editPrMainActions">
          <button type="button" id="cancelEditPrModal" class="cancel-btn">
            Cancel
          </button>

          <button type="submit" name="submit_action" value="submit" class="save-btn" id="submitEditBtn">
            Save Changes
          </button>
        </div>

        <div class="modal-actions full-width" id="viewOnlyActions" style="display: none;">
          <button type="button" id="closeViewOnlyPr" class="cancel-btn">
            Close
          </button>
        </div>
      </form>

      {{-- SUB ADMIN APPROVAL ACTIONS - NO CONFIRM MODAL --}}
      <div class="modal-actions full-width pr-approval-actions" id="prApprovalActions" style="display: none;">
        <form id="approvePrForm" method="POST" action="#">
          @csrf

          <button type="submit" class="approve-action-btn">
            <i class="fa-solid fa-check"></i>
            Approve
          </button>
        </form>

        <form id="rejectPrForm" method="POST" action="#">
          @csrf

          <input type="hidden" name="remarks" value="Rejected by sub admin">

          <button type="submit" class="reject-action-btn">
            <i class="fa-solid fa-xmark"></i>
            Reject
          </button>
        </form>
      </div>

      {{-- WAREHOUSE ACTIONS --}}
      <div class="modal-actions full-width warehouse-actions" id="warehouseActions" style="display: none;">
        <form id="issuePrForm" method="POST" action="#">
          @csrf

          <button type="submit" class="approve-action-btn">
            <i class="fa-solid fa-box-open"></i>
            Issue Parts
          </button>
        </form>

        <form id="forPurchasePrForm" method="POST" action="#">
          @csrf

          <button type="submit" class="purchase-action-btn">
            <i class="fa-solid fa-cart-shopping"></i>
            For Purchase
          </button>
        </form>
      </div>

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

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      function openModal(modal) {
        if (modal) {
          modal.classList.add('show');
        }
      }

      function closeModal(modal) {
        if (modal) {
          modal.classList.remove('show');
        }
      }

      function parsePartNeeded(partNeeded) {
        if (!partNeeded) {
          return {
            item: '',
            quantity: ''
          };
        }

        const firstPart = partNeeded.split(',')[0].trim();

        if (firstPart.includes(' - Qty:')) {
          const pieces = firstPart.split(' - Qty:');

          return {
            item: pieces[0] ? pieces[0].trim() : '',
            quantity: pieces[1] ? pieces[1].trim() : 1
          };
        }

        return {
          item: firstPart,
          quantity: 1
        };
      }

      const validationErrorModal = document.getElementById('validationErrorModal');
      const closeValidationErrorModal = document.getElementById('closeValidationErrorModal');

      if (closeValidationErrorModal && validationErrorModal) {
        closeValidationErrorModal.addEventListener('click', () => {
          closeModal(validationErrorModal);
        });
      }

      const prModal = document.getElementById('prModal');
      const openPrModal = document.getElementById('openPrModal');
      const closePrModal = document.getElementById('closePrModal');
      const cancelPrModal = document.getElementById('cancelPrModal');

      if (openPrModal) {
        openPrModal.addEventListener('click', () => {
          openModal(prModal);
        });
      }

      if (closePrModal) {
        closePrModal.addEventListener('click', () => {
          closeModal(prModal);
        });
      }

      if (cancelPrModal) {
        cancelPrModal.addEventListener('click', () => {
          closeModal(prModal);
        });
      }

      const jobOrderSelect = document.getElementById('jobOrderSelect');
      const busNoInput = document.getElementById('busNoInput');
      const partInput = document.getElementById('partInput');
      const quantityInput = document.getElementById('quantityInput');

      if (jobOrderSelect) {
        jobOrderSelect.addEventListener('change', () => {
          const selected = jobOrderSelect.options[jobOrderSelect.selectedIndex];
          const busNo = selected.dataset.bus || '';
          const parts = selected.dataset.parts || '';
          const parsed = parsePartNeeded(parts);

          if (busNoInput) busNoInput.value = busNo;
          if (partInput) partInput.value = parsed.item;
          if (quantityInput) quantityInput.value = parsed.quantity || 1;
        });
      }

      const editPrModal = document.getElementById('editPrModal');
      const closeEditPrModal = document.getElementById('closeEditPrModal');
      const cancelEditPrModal = document.getElementById('cancelEditPrModal');
      const closeViewOnlyPr = document.getElementById('closeViewOnlyPr');

      const editPrForm = document.getElementById('editPrForm');
      const editPrNo = document.getElementById('edit_pr_no');
      const editJobOrderNo = document.getElementById('edit_job_order_no');
      const editBusNo = document.getElementById('edit_bus_no');
      const editStatusDisplay = document.getElementById('edit_status_display');
      const editItem = document.getElementById('edit_item');
      const editQuantity = document.getElementById('edit_quantity');
      const editRemarks = document.getElementById('edit_remarks');

      const editPrMainActions = document.getElementById('editPrMainActions');
      const viewOnlyActions = document.getElementById('viewOnlyActions');
      const prApprovalActions = document.getElementById('prApprovalActions');
      const warehouseActions = document.getElementById('warehouseActions');

      const approvePrForm = document.getElementById('approvePrForm');
      const rejectPrForm = document.getElementById('rejectPrForm');
      const issuePrForm = document.getElementById('issuePrForm');
      const forPurchasePrForm = document.getElementById('forPurchasePrForm');

      document.querySelectorAll('.open-edit-pr-modal').forEach((button) => {
        button.addEventListener('click', () => {
          const status = button.dataset.status || '';

          if (editPrForm) editPrForm.action = button.dataset.updateUrl || '#';
          if (approvePrForm) approvePrForm.action = button.dataset.approveUrl || '#';
          if (rejectPrForm) rejectPrForm.action = button.dataset.rejectUrl || '#';
          if (issuePrForm) issuePrForm.action = button.dataset.issueUrl || '#';
          if (forPurchasePrForm) forPurchasePrForm.action = button.dataset.forPurchaseUrl || '#';

          if (editPrNo) editPrNo.value = button.dataset.prNo || '';
          if (editBusNo) editBusNo.value = button.dataset.busNo || '';
          if (editStatusDisplay) editStatusDisplay.value = status;
          if (editItem) editItem.value = button.dataset.item || '';
          if (editQuantity) editQuantity.value = button.dataset.quantity || '';
          if (editRemarks) editRemarks.value = button.dataset.remarks || '';

          if (editJobOrderNo) {
            let optionExists = false;

            Array.from(editJobOrderNo.options).forEach((option) => {
              if (option.value === button.dataset.jobOrderNo) {
                optionExists = true;
              }
            });

            if (!optionExists && button.dataset.jobOrderNo) {
              const option = document.createElement('option');
              option.value = button.dataset.jobOrderNo;
              option.textContent = button.dataset.jobOrderNo;
              option.dataset.bus = button.dataset.busNo || '';
              option.dataset.parts = `${button.dataset.item || ''} - Qty: ${button.dataset.quantity || 1}`;
              editJobOrderNo.appendChild(option);
            }

            editJobOrderNo.value = button.dataset.jobOrderNo || '';
          }

          const canEdit = status === 'Submitted';
          const canApproveReject = status === 'Submitted';
          const canWarehouseAct = status === 'Approved';

          if (editPrMainActions) {
            editPrMainActions.style.display = canEdit ? 'flex' : 'none';
          }

          if (viewOnlyActions) {
            viewOnlyActions.style.display = canEdit ? 'none' : 'flex';
          }

          if (prApprovalActions) {
            prApprovalActions.style.display = canApproveReject ? 'flex' : 'none';
          }

          if (warehouseActions) {
            warehouseActions.style.display = canWarehouseAct ? 'flex' : 'none';
          }

          if (editRemarks) {
            editRemarks.readOnly = !canEdit;
          }

          if (editJobOrderNo) {
            editJobOrderNo.disabled = !canEdit;
          }

          openModal(editPrModal);
        });
      });

      if (closeEditPrModal) {
        closeEditPrModal.addEventListener('click', () => {
          closeModal(editPrModal);
        });
      }

      if (cancelEditPrModal) {
        cancelEditPrModal.addEventListener('click', () => {
          closeModal(editPrModal);
        });
      }

      if (closeViewOnlyPr) {
        closeViewOnlyPr.addEventListener('click', () => {
          closeModal(editPrModal);
        });
      }

      if (editJobOrderNo) {
        editJobOrderNo.addEventListener('change', () => {
          const selected = editJobOrderNo.options[editJobOrderNo.selectedIndex];
          const busNo = selected.dataset.bus || '';
          const parts = selected.dataset.parts || '';
          const parsed = parsePartNeeded(parts);

          if (editBusNo) editBusNo.value = busNo;
          if (editItem) editItem.value = parsed.item;
          if (editQuantity) editQuantity.value = parsed.quantity || 1;
        });
      }

      const deletePrModal = document.getElementById('deletePrModal');
      const deletePrNo = document.getElementById('deletePrNo');
      const cancelDeletePr = document.getElementById('cancelDeletePr');
      const confirmDeletePr = document.getElementById('confirmDeletePr');

      let selectedDeletePrForm = null;

      document.querySelectorAll('.open-delete-pr-modal').forEach((button) => {
        button.addEventListener('click', () => {
          const id = button.dataset.id;
          const prNo = button.dataset.prNo;

          selectedDeletePrForm = document.getElementById(`deletePrForm-${id}`);

          if (deletePrNo) {
            deletePrNo.textContent = prNo || 'this purchase request';
          }

          openModal(deletePrModal);
        });
      });

      if (cancelDeletePr) {
        cancelDeletePr.addEventListener('click', () => {
          selectedDeletePrForm = null;
          closeModal(deletePrModal);
        });
      }

      if (confirmDeletePr) {
        confirmDeletePr.addEventListener('click', () => {
          if (selectedDeletePrForm) {
            selectedDeletePrForm.submit();
          }
        });
      }

      document.querySelectorAll('.modal-overlay, .delete-modal-overlay, .success-modal-overlay').forEach((overlay) => {
        overlay.addEventListener('click', (event) => {
          if (event.target === overlay) {
            overlay.classList.remove('show');
          }
        });
      });
    });
  </script>

</x-layout.app>