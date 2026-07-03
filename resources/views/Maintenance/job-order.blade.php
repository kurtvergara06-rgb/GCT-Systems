<x-layout.app
  title="FROMS - Job Orders"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Maintenance/job-order.css',
    'resources/js/Main-js/sidebar.js',
    'resources/js/Maintenance/job-order.js'
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

  @if($errors->any())
    <div
      id="validationErrorModal"
      class="delete-modal-overlay show active"
      style="display: flex;"
    >
      <div class="delete-modal-box">
        <div class="delete-icon">
          <i class="fa-solid fa-triangle-exclamation"></i>
        </div>

        <h2>Form Error</h2>
        <p>Please check the form. Some required information is missing.</p>

        <ul style="text-align:left; margin:12px 0 0; color:#dc2626; font-size:13px;">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>

        <div class="delete-modal-actions">
          <button
            type="button"
            id="closeValidationErrorModal"
            class="cancel-delete-btn"
          >
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
        title="Job Orders"
        subtitle="Manage repair and preventive maintenance service requests"
        notification-count="6"
      />

      <section class="stats-grid">

        <x-ui.summary-card
          label="On Hold"
          value="{{ $onHold ?? 0 }}"
          small="Job Orders"
          icon="fa-pause"
          color="yellow"
        />

        <x-ui.summary-card
          label="On Going"
          value="{{ $onGoing ?? 0 }}"
          small="Job Orders"
          icon="fa-spinner"
          color="blue"
        />

        <x-ui.summary-card
          label="Completed"
          value="{{ $completed ?? 0 }}"
          small="Job Orders"
          icon="fa-check"
          color="green"
        />

        <x-ui.summary-card
          label="Needs Parts"
          value="{{ $needParts ?? 0 }}"
          small="Pending parts"
          icon="fa-screwdriver-wrench"
          color="red"
        />

      </section>

      <section class="table-card">

        <div class="section-header">
          <div>
            <h2>Job Orders</h2>
            <p>Track job order details, assigned mechanics, completion status, and parts progress</p>
          </div>
        </div>

        <x-ui.table-toolbar
          :action="route('job-orders')"
          class="toolbar job-toolbar"
          search-placeholder="Search bus, mechanic, maintenance type, or part..."
          button-id="openJobModal"
          button-label="New JO"
        >
          <div class="filter-group">
            <label for="partStatusFilter">Part Status</label>

            <select
              name="part_status"
              id="partStatusFilter"
              class="part-status-select"
              onchange="this.form.submit()"
            >
              <option value="All Part Statuses" {{ request('part_status', 'All Part Statuses') == 'All Part Statuses' ? 'selected' : '' }}>
                All Part Statuses
              </option>
              <option value="Not Requested" {{ request('part_status') == 'Not Requested' ? 'selected' : '' }}>Not Requested</option>
              <option value="Submitted" {{ request('part_status') == 'Submitted' ? 'selected' : '' }}>Submitted</option>
              <option value="Approved" {{ request('part_status') == 'Approved' ? 'selected' : '' }}>Approved</option>
              <option value="Rejected" {{ request('part_status') == 'Rejected' ? 'selected' : '' }}>Rejected</option>
              <option value="For Purchase" {{ request('part_status') == 'For Purchase' ? 'selected' : '' }}>For Purchase</option>
              <option value="Ordered" {{ request('part_status') == 'Ordered' ? 'selected' : '' }}>Ordered</option>
              <option value="For Pick-up" {{ request('part_status') == 'For Pick-up' ? 'selected' : '' }}>For Pick-up</option>
              <option value="For Delivery" {{ request('part_status') == 'For Delivery' ? 'selected' : '' }}>For Delivery</option>
              <option value="Delivered" {{ request('part_status') == 'Delivered' ? 'selected' : '' }}>Delivered</option>
              <option value="Picked Up" {{ request('part_status') == 'Picked Up' ? 'selected' : '' }}>Picked Up</option>
              <option value="Issued" {{ request('part_status') == 'Issued' ? 'selected' : '' }}>Issued</option>
              <option value="No Parts Needed" {{ request('part_status') == 'No Parts Needed' ? 'selected' : '' }}>No Parts Needed</option>
            </select>
          </div>

          <div class="filter-group">
            <label>Maintenance Type</label>

            <select name="maintenance_type" onchange="this.form.submit()">
              <option value="All Types" {{ request('maintenance_type', 'All Types') == 'All Types' ? 'selected' : '' }}>
                All Types
              </option>
              <option value="PMS" {{ request('maintenance_type') == 'PMS' ? 'selected' : '' }}>PMS</option>
              <option value="Repair" {{ request('maintenance_type') == 'Repair' ? 'selected' : '' }}>Repair</option>
            </select>
          </div>
        </x-ui.table-toolbar>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Bus #</th>
                <th>Maintenance Type</th>
                <th>Assigned Mechanic</th>
                <th>Start Date & Time</th>
                <th>Completion Date & Time</th>
                <th class="status-col">JO Status</th>
                <th class="status-col">Part Status</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              @forelse($jobOrders as $jobOrder)
                @php
                  $isCompleted = $jobOrder->status === 'Completed';
                  $isOnHold = $jobOrder->status === 'On Hold';

                  $joStatus = $jobOrder->status ?: 'On Going';
                  $partStatus = $jobOrder->part_status;

                  if (!$jobOrder->part_needed) {
                    $partStatus = '----';
                  } elseif (!$partStatus || $partStatus === 'Unknown' || $partStatus === 'No Parts Needed') {
                    $partStatus = 'Not Requested';
                  }

                  $hasNeededParts = !empty($jobOrder->part_needed);

                  $canFinish = !$isOnHold && (
                    !$hasNeededParts ||
                    in_array($jobOrder->part_status, ['Issued', 'Rejected'], true)
                  );

                  $canCreatePr = $hasNeededParts
                    && !$isCompleted
                    && in_array($jobOrder->part_status, [null, 'Not Requested', 'Rejected'], true);

                  $isLockedByPurchaseRequest = in_array($jobOrder->part_status, [
                    'Approved',
                    'For Purchase',
                    'Ordered',
                    'For Pick-up',
                    'For Delivery',
                    'Delivered',
                    'Picked Up',
                    'Issued',
                  ], true);

                  $isViewOnly = $isCompleted || $isLockedByPurchaseRequest;

                  $hasActivePr = $hasNeededParts
                    && !$isCompleted
                    && !$canCreatePr
                    && $jobOrder->part_status !== 'Issued';

                  $partStatusClass = match($partStatus) {
                    'Not Requested' => 'not-requested',
                    'Submitted' => 'submitted',
                    'Approved' => 'approved',
                    'Rejected' => 'rejected',
                    'For Purchase' => 'for-purchase',
                    'Ordered' => 'ordered',
                    'For Pick-up' => 'for-pick-up',
                    'For Delivery' => 'for-delivery',
                    'Delivered' => 'delivered',
                    'Picked Up' => 'picked-up',
                    'Issued' => 'issued',
                    'No Parts Needed' => 'no-parts-needed',
                    default => 'not-requested',
                  };
                @endphp

                <tr>
                  <td>{{ $jobOrder->bus_no }}</td>
                  <td>{{ $jobOrder->maintenance_type }}</td>

                  <td>
                    {{ $jobOrder->assigned_mechanic ?: 'No mechanic assigned' }}
                  </td>

                  <td class="{{ $jobOrder->start_date ? '' : 'empty' }}">
                    {{ $jobOrder->start_date ? date('m/d/y | h:i A', strtotime($jobOrder->start_date)) : '—' }}
                  </td>

                  <td class="{{ $jobOrder->completion_date ? '' : 'empty' }}">
                    @if($jobOrder->completion_date)
                      {{ date('m/d/y | h:i A', strtotime($jobOrder->completion_date)) }}
                    @else
                      @if($canFinish)
                        <form
                          id="finishForm-{{ $jobOrder->id }}"
                          action="{{ route('job-orders.finish', $jobOrder->id) }}"
                          method="POST"
                        >
                          @csrf

                          <button
                            type="button"
                            class="finish-btn open-finish-modal"
                            data-id="{{ $jobOrder->id }}"
                            data-jo-no="{{ $jobOrder->job_order_no }}"
                          >
                            <i class="fa-solid fa-check"></i>
                            Finish
                          </button>
                        </form>
                      @else
                        <button
                          type="button"
                          class="finish-btn locked-finish-btn"
                          disabled
                        >
                          <i class="fa-solid fa-lock"></i>
                          Locked
                        </button>
                      @endif
                    @endif
                  </td>

                  <td class="status-col">
                    <x-ui.status-badge :status="$joStatus" />
                  </td>

                  <td class="status-col part-status-cell">
                    @if(!$jobOrder->part_needed || $partStatus === '----')
                      <span class="empty">----</span>
                    @else
                      <span class="part-status-badge {{ $partStatusClass }}">
                        {{ $partStatus }}
                      </span>
                    @endif
                  </td>

                  <td>
                    <div class="actions">

                      <x-ui.action-buttom-modal
                        class="{{ $isViewOnly ? 'view open-edit-modal' : 'edit open-edit-modal' }}"
                        title="{{ $isViewOnly ? 'View Job Order' : 'Edit Job Order' }}"
                        icon="{{ $isViewOnly ? 'fa-eye' : 'fa-pen-to-square' }}"
                        data-id="{{ $jobOrder->id }}"
                        data-job-order-no="{{ $jobOrder->job_order_no }}"
                        data-bus-no="{{ $jobOrder->bus_no }}"
                        data-problem-issue="{{ $jobOrder->problem_issue }}"
                        data-maintenance-type="{{ $jobOrder->maintenance_type }}"
                        data-assigned-mechanic="{{ $jobOrder->assigned_mechanic }}"
                        data-part-needed="{{ $jobOrder->part_needed }}"
                        data-status="{{ $jobOrder->status }}"
                        data-view-only="{{ $isViewOnly ? '1' : '0' }}"
                      />

                      @if($canCreatePr)
                        <form
                          action="{{ route('job-orders.create-pr', $jobOrder->id) }}"
                          method="POST"
                          class="create-pr-form"
                        >
                          @csrf

                          <button
                            type="submit"
                            class="action-btn create-pr-btn"
                            title="Create Purchase Request"
                          >
                            <i class="fa-solid fa-file-circle-plus"></i>
                          </button>
                        </form>
                      @elseif($hasActivePr)
                        <button
                          type="button"
                          class="action-btn"
                          title="Purchase Request already created"
                          disabled
                          style="opacity:0.55; cursor:not-allowed;"
                        >
                          <i class="fa-solid fa-file-circle-check"></i>
                        </button>
                      @endif

                      <form
                        id="deleteForm-{{ $jobOrder->id }}"
                        action="{{ route('job-orders.destroy', $jobOrder->id) }}"
                        method="POST"
                      >
                        @csrf
                        @method('DELETE')

                        <button
                          type="button"
                          class="action-btn delete open-delete-modal"
                          title="Delete Job Order"
                          data-id="{{ $jobOrder->id }}"
                          data-jo-no="{{ $jobOrder->job_order_no }}"
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
                  message="No job orders found."
                />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$jobOrders" />

      </section>

    </main>
  </div>

  <x-ui.form-modal
    id="jobModal"
    title="New Job Order"
    subtitle="Job Order Details"
    description="Enter the client-required job order information."
    action="{{ route('job-orders.store') }}"
    method="POST"
    submit-text="Save Job Order"
    close-id="closeJobModal"
    cancel-id="cancelJobModal"
  >
    <div class="form-group">
      <label>JO No.</label>
      <input type="text" value="{{ $nextJobOrderNo }}" readonly>
    </div>

    <div class="form-group">
      <label>Bus #</label>

      <select name="bus_no" required>
        <option value="">Select Bus</option>

        @forelse($buses as $bus)
          <option value="{{ $bus->bus_no }}">
            {{ $bus->bus_no }}
            {{ $bus->plate_no ? ' - ' . $bus->plate_no : '' }}
          </option>
        @empty
          <option value="" disabled>
            No active buses found. Add a bus in Bus Master List first.
          </option>
        @endforelse
      </select>
    </div>

    <div class="form-group full-width">
      <label>Problem / Issue</label>
      <textarea
        name="problem_issue"
        placeholder="Describe the problem or issue..."
        required
      ></textarea>
    </div>

    <div class="form-group">
      <label>Maintenance Type</label>
      <select name="maintenance_type" required>
        <option value="">Select Maintenance Type</option>
        <option value="PMS">PMS</option>
        <option value="Repair">Repair</option>
      </select>
    </div>

    <div class="form-group">
      <label>Assigned Mechanic</label>

      <select name="assigned_mechanic">
        <option value="">
          {{ $availableMechanics->count() ? 'Select Available Mechanic' : 'No available mechanic - JO will be On Hold' }}
        </option>

        @foreach($availableMechanics as $mechanic)
          <option value="{{ $mechanic->mechanic_name }}">
            {{ $mechanic->mechanic_name }}
          </option>
        @endforeach
      </select>
    </div>

    <div class="form-group full-width">
      <label>Requested Parts</label>

      <div id="partsNeededWrapper" class="parts-needed-wrapper">
        <div class="part-needed-row">
          <input type="text" name="parts[0][name]" placeholder="Part name">

          <input
            type="number"
            name="parts[0][quantity]"
            min="1"
            placeholder="Qty"
          >

          <select name="parts[0][unit]">
            <option value="">Unit</option>
            <option value="pcs">pcs</option>
            <option value="set">set</option>
            <option value="liter">liter</option>
            <option value="gallon">gallon</option>
            <option value="bottle">bottle</option>
            <option value="box">box</option>
            <option value="meter">meter</option>
            <option value="kg">kg</option>
            <option value="pack">pack</option>
            <option value="pair">pair</option>
            <option value="roll">roll</option>
            <option value="tube">tube</option>
          </select>

          <button type="button" class="remove-part-btn">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
      </div>

      <small>Add each part separately so Warehouse can check inventory correctly.</small>

      <button type="button" id="addPartBtn" class="add-part-btn">
        <i class="fa-solid fa-plus"></i>
        Add Other Part
      </button>
    </div>
  </x-ui.form-modal>

  <div id="editJobModal" class="modal-overlay">
    <div class="modal-box wide-modal">

      <div class="modal-header">
        <div>
          <h2>Job Order Details</h2>
          <p id="editModalSubtitle">Review and update the selected job order.</p>
        </div>

        <button type="button" id="closeEditJobModal" class="close-btn">
          &times;
        </button>
      </div>

      <form id="editJobForm" method="POST" action="#" class="job-form wide-form">
        @csrf
        @method('PUT')

        <div class="form-section-title full-width">
          <h3>Editable JO Information</h3>
          <p id="editModeDescription">Review and update the selected job order.</p>
        </div>

        <div class="form-group">
          <label>JO No.</label>
          <input type="text" name="job_order_no" id="edit_job_order_no" readonly required>
        </div>

        <div class="form-group">
          <label>Bus #</label>

          <select name="bus_no" id="edit_bus_no" required>
            <option value="">Select Bus</option>

            @foreach($buses as $bus)
              <option value="{{ $bus->bus_no }}">
                {{ $bus->bus_no }}
                {{ $bus->plate_no ? ' - ' . $bus->plate_no : '' }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="form-group full-width">
          <label>Problem / Issue</label>
          <textarea name="problem_issue" id="edit_problem_issue" required></textarea>
        </div>

        <div class="form-group">
          <label>Maintenance Type</label>
          <select name="maintenance_type" id="edit_maintenance_type" required>
            <option value="PMS">PMS</option>
            <option value="Repair">Repair</option>
          </select>
        </div>

        <div class="form-group">
          <label>Status</label>
          <select name="status" id="edit_status">
            <option value="On Hold">On Hold</option>
            <option value="On Going">On Going</option>
          </select>
        </div>

        <div class="form-group">
          <label>Assigned Mechanic</label>

          <select name="assigned_mechanic" id="edit_assigned_mechanic">
            <option value="">No mechanic assigned</option>

            @foreach($allMechanics as $mechanic)
              <option value="{{ $mechanic->mechanic_name }}">
                {{ $mechanic->mechanic_name }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="form-group full-width">
          <label>Requested Parts</label>
          <div id="editPartsNeededWrapper" class="parts-needed-wrapper"></div>

          <button type="button" id="editAddPartBtn" class="add-part-btn">
            <i class="fa-solid fa-plus"></i>
            Add Other Part
          </button>
        </div>

        <div class="modal-actions full-width" id="editJobMainActions">
          <button type="button" id="cancelEditJobModal" class="cancel-btn">
            Cancel
          </button>

          <button type="submit" class="save-btn">
            Update Job Order
          </button>
        </div>

        <div
          class="modal-actions full-width"
          id="viewOnlyJobActions"
          style="display:none;"
        >
          <button type="button" id="closeViewOnlyJob" class="cancel-btn">
            Close
          </button>
        </div>
      </form>

    </div>
  </div>

  <div id="finishJobModal" class="delete-modal-overlay">
    <div class="delete-modal-box">
      <div class="delete-icon finish-icon">
        <i class="fa-solid fa-check"></i>
      </div>

      <h2>Finish Job Order?</h2>

      <p>
        Are you sure you want to finish
        <strong id="finishJoNo">this job order</strong>?
        This record will be marked as completed.
      </p>

      <div class="delete-modal-actions">
        <button type="button" id="cancelFinishJob" class="cancel-delete-btn">
          Cancel
        </button>

        <button type="button" id="confirmFinishJob" class="confirm-finish-btn">
          Yes, Finish
        </button>
      </div>
    </div>
  </div>

  <x-ui.action-buttom-modal
    mode="delete"
    id="deleteJobModal"
    delete-title="Delete Job Order?"
    delete-message="Are you sure you want to delete"
    name-id="deleteJoNo"
    cancel-id="cancelDeleteJob"
    confirm-id="confirmDeleteJob"
  />

</x-layout.app>