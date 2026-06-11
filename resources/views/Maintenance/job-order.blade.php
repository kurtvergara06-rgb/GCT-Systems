<x-layout.app
  title="FROMS - Job Orders"
  :assets="[
    'resources/css/Main-style/main.css',
    'resources/css/Main-style/sidebar.css',
    'resources/css/Maintenance/job-order.css',
    'resources/js/Maintenance/job-order.js'
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
        title="Job Orders"
        subtitle="Manage repair and preventive maintenance service requests"
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
          label="On Hold"
          value="{{ $onHold }}"
          small="Job Orders"
          icon="fa-pause"
          color="yellow"
        />

        <x-ui.summary-card
          label="On Going"
          value="{{ $onGoing }}"
          small="Job Orders"
          icon="fa-spinner"
          color="blue"
        />

        <x-ui.summary-card
          label="Completed"
          value="{{ $completed }}"
          small="Job Orders"
          icon="fa-check"
          color="green"
        />

        <x-ui.summary-card
          label="Urgent Repair"
          value="{{ $urgentRepair }}"
          small="Needs attention"
          icon="fa-triangle-exclamation"
          color="red"
        />

      </section>

      {{-- TABLE --}}
      <section class="table-card">

        <div class="section-header">
          <div>
            <h2>Job Orders</h2>
            <p>Track job order details, assigned mechanics, parts needed, and completion status</p>
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
            <label>Part Status</label>
            <select name="part_status" onchange="this.form.submit()">
              <option value="All Part Statuses" {{ request('part_status') == 'All Part Statuses' ? 'selected' : '' }}>
                All Part Statuses
              </option>

              <option value="Submitted" {{ request('part_status') == 'Submitted' ? 'selected' : '' }}>
                Submitted
              </option>

              <option value="Approved" {{ request('part_status') == 'Approved' ? 'selected' : '' }}>
                Approved
              </option>
<<<<<<< HEAD

              <option value="Rejected" {{ request('part_status') == 'Rejected' ? 'selected' : '' }}>
                Rejected
              </option>

              <option value="For Purchase" {{ request('part_status') == 'For Purchase' ? 'selected' : '' }}>
                For Purchase
              </option>

              <option value="Ordered" {{ request('part_status') == 'Ordered' ? 'selected' : '' }}>
                Ordered
              </option>

              <option value="For Pick-up" {{ request('part_status') == 'For Pick-up' ? 'selected' : '' }}>
                For Pick-up
              </option>

              <option value="For Delivery" {{ request('part_status') == 'For Delivery' ? 'selected' : '' }}>
                For Delivery
              </option>

              <option value="Delivered" {{ request('part_status') == 'Delivered' ? 'selected' : '' }}>
                Delivered
              </option>

              <option value="Picked Up" {{ request('part_status') == 'Picked Up' ? 'selected' : '' }}>
                Picked Up
              </option>

              <option value="Issued" {{ request('part_status') == 'Issued' ? 'selected' : '' }}>
                Issued
              </option>
=======
>>>>>>> 261af0e33d572cd870c9ef98898f871a0e6e07fb
            </select>
          </div>

          <div class="filter-group">
            <label>Maintenance Type</label>
            <select name="maintenance_type" onchange="this.form.submit()">
              <option value="All Types" {{ request('maintenance_type') == 'All Types' ? 'selected' : '' }}>
                All Types
              </option>

              <option value="PMS" {{ request('maintenance_type') == 'PMS' ? 'selected' : '' }}>
                PMS
              </option>

              <option value="Repair" {{ request('maintenance_type') == 'Repair' ? 'selected' : '' }}>
                Repair
              </option>
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
                <th>Part Needed</th>
                <th>Quantity</th>
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
                  $firstPartName = '—';
                  $firstPartQuantity = '—';
                  $isCompleted = $jobOrder->status === 'Completed';

                  $joStatus = $jobOrder->status ?: 'On Going';
                  $partStatus = $jobOrder->part_status;

                  if (!$jobOrder->part_needed) {
                    $partStatus = '----';
                  } elseif (!$partStatus || $partStatus === 'Unknown') {
                    $partStatus = 'Not Requested';
                  }

                  $hasNeededParts = !empty($jobOrder->part_needed);
                  $canFinish = !$hasNeededParts || $jobOrder->part_status === 'Issued';

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
                    !$hasNeededParts || in_array($jobOrder->part_status, ['Issued', 'Rejected'], true)
                  );

                  if ($jobOrder->part_needed) {
                    $parts = explode(',', $jobOrder->part_needed);
                    $firstPart = trim($parts[0] ?? '');

                    if (str_contains($firstPart, ' - Qty:')) {
                      [$name, $qty] = explode(' - Qty:', $firstPart);

                      $firstPartName = trim($name);
                      $firstPartQuantity = trim($qty);
                    } else {
                      $firstPartName = $firstPart ?: '—';
                      $firstPartQuantity = '—';
                    }
                  }
                @endphp

                <tr>
                  <td>{{ $jobOrder->bus_no }}</td>

                  <td>{{ $jobOrder->maintenance_type }}</td>

                  <td>{{ $jobOrder->assigned_mechanic }}</td>

                  <td class="{{ $jobOrder->part_needed ? '' : 'empty' }}">
                    {{ $firstPartName }}
                  </td>

                  <td class="{{ $jobOrder->part_needed ? '' : 'empty' }}">
                    {{ $firstPartQuantity }}
                  </td>

                  <td class="{{ $jobOrder->start_date ? '' : 'empty' }}">
                    {{ $jobOrder->start_date ? date('m/d/y | h:i A', strtotime($jobOrder->start_date)) : '—' }}
                  </td>

                  <td class="{{ $jobOrder->completion_date ? '' : 'empty' }}">
                    @if($jobOrder->completion_date)
                      {{ date('m/d/y | h:i A', strtotime($jobOrder->completion_date)) }}
                    @else
<<<<<<< HEAD
                      @if($canFinish)
                        <form
                          id="finishForm-{{ $jobOrder->id }}"
                          action="{{ route('job-orders.finish', $jobOrder->id) }}"
                          method="POST"
                        >
                          @csrf

=======

                      @if($canFinish)
                        <form
                          id="finishForm-{{ $jobOrder->id }}"
                          action="{{ route('job-orders.finish', $jobOrder->id) }}"
                          method="POST"
                        >
                          @csrf

>>>>>>> 261af0e33d572cd870c9ef98898f871a0e6e07fb
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
<<<<<<< HEAD
                          title="{{ $isOnHold ? 'Cannot finish yet. This job order is on hold.' : 'Cannot finish yet. The part status must be Issued or Rejected first.' }}"
=======
                          title="Cannot finish yet. The requested part must be issued first."
>>>>>>> 261af0e33d572cd870c9ef98898f871a0e6e07fb
                          disabled
                        >
                          <i class="fa-solid fa-lock"></i>
                          Locked
                        </button>
                      @endif
<<<<<<< HEAD
=======

>>>>>>> 261af0e33d572cd870c9ef98898f871a0e6e07fb
                    @endif
                  </td>

                  <td class="status-col">
                    <x-ui.status-badge :status="$joStatus" />
                  </td>

                  <td class="status-col">
<<<<<<< HEAD
                    @if(!$jobOrder->part_needed || $partStatus === '----')
=======
                    @if($partStatus === '----')
>>>>>>> 261af0e33d572cd870c9ef98898f871a0e6e07fb
                      <span class="empty">----</span>
                    @else
                      <x-ui.status-badge :status="$partStatus" />
                    @endif
                  </td>

                  <td>
                    <div class="actions">

                      <x-ui.action-buttom-modal
                        class="{{ $isCompleted ? 'view open-edit-modal' : 'edit open-edit-modal' }}"
                        title="{{ $isCompleted ? 'View' : 'View / Edit' }}"
                        icon="{{ $isCompleted ? 'fa-eye' : 'fa-pen-to-square' }}"
                        data-id="{{ $jobOrder->id }}"
                        data-job-order-no="{{ $jobOrder->job_order_no }}"
                        data-bus-no="{{ $jobOrder->bus_no }}"
                        data-problem-issue="{{ $jobOrder->problem_issue }}"
                        data-maintenance-type="{{ $jobOrder->maintenance_type }}"
                        data-assigned-mechanic="{{ $jobOrder->assigned_mechanic }}"
                        data-part-needed="{{ $jobOrder->part_needed }}"
                        data-status="{{ $jobOrder->status }}"
                      />

                      <form
                        id="deleteForm-{{ $jobOrder->id }}"
                        action="{{ route('job-orders.destroy', $jobOrder->id) }}"
                        method="POST"
                      >
                        @csrf
                        @method('DELETE')

                        <x-ui.action-buttom-modal
                          class="delete open-delete-modal"
                          title="Delete"
                          icon="fa-trash"
                          data-id="{{ $jobOrder->id }}"
                          data-jo-no="{{ $jobOrder->job_order_no }}"
                        />
                      </form>

                    </div>
                  </td>
                </tr>
              @empty
                <x-ui.empty-row
                  colspan="10"
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

  {{-- NEW JO MODAL --}}
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
      <input
        type="text"
        value="{{ $nextJobOrderNo }}"
        readonly
      >
    </div>

    <div class="form-group">
      <label>Bus #</label>
      <input
        type="text"
        name="bus_no"
        placeholder="Example: BUS-2026-0001"
        required
      >
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
      <select name="assigned_mechanic" required>
        <option value="">Select Available Mechanic</option>

        @foreach($availableMechanics as $mechanic)
          <option value="{{ $mechanic->mechanic_name }}">
<<<<<<< HEAD
            {{ $mechanic->mechanic_name }}
=======
            {{ $mechanic->mechanic_name }} - {{ $mechanic->mechanic_id }}
>>>>>>> 261af0e33d572cd870c9ef98898f871a0e6e07fb
          </option>
        @endforeach
      </select>
    </div>

    <div class="form-group full-width">
      <label>Parts Needed</label>

      <div id="partsNeededWrapper" class="parts-needed-wrapper">
        <div class="part-needed-row">
          <input
            type="text"
            name="parts[0][name]"
            placeholder="Part name"
          >

          <input
            type="number"
            name="parts[0][quantity]"
            min="1"
            placeholder="Quantity"
          >

          <button type="button" class="remove-part-btn" style="display: none;">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
      </div>

      <button type="button" id="addPartBtn" class="add-part-btn">
        <i class="fa-solid fa-plus"></i>
        Add Other Part
      </button>
    </div>
  </x-ui.form-modal>

  {{-- EDIT / VIEW JO MODAL --}}
  <x-ui.form-modal
    id="editJobModal"
    title="Job Order Details"
    subtitle="Editable JO Information"
    description="Review and update the selected job order."
    form-id="editJobForm"
    action="#"
    method="PUT"
    submit-text="Update Job Order"
    close-id="closeEditJobModal"
    cancel-id="cancelEditJobModal"
  >
    <div class="form-group">
      <label>JO No.</label>
      <input
        type="text"
        name="job_order_no"
        id="edit_job_order_no"
        readonly
        required
      >
    </div>

    <div class="form-group">
      <label>Bus #</label>
      <input
        type="text"
        name="bus_no"
        id="edit_bus_no"
        required
      >
    </div>

    <div class="form-group full-width">
      <label>Problem / Issue</label>
      <textarea
        name="problem_issue"
        id="edit_problem_issue"
        required
      ></textarea>
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
      <select name="status" id="edit_status" required>
        <option value="On Hold">On Hold</option>
        <option value="On Going">On Going</option>
      </select>
    </div>

    <div class="form-group">
      <label>Assigned Mechanic</label>
      <select name="assigned_mechanic" id="edit_assigned_mechanic" required>
        <option value="">Select Mechanic</option>

        @foreach($allMechanics as $mechanic)
          <option value="{{ $mechanic->mechanic_name }}">
            {{ $mechanic->mechanic_name }}
          </option>
        @endforeach
      </select>
    </div>

    <div class="form-group full-width">
      <label>Parts Needed</label>

      <div id="editPartsNeededWrapper" class="parts-needed-wrapper">
        {{-- JS will insert existing parts here --}}
      </div>

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

    <div class="modal-actions full-width" id="viewOnlyJobActions" style="display: none;">
      <button type="button" id="closeViewOnlyJob" class="cancel-btn">
        Close
      </button>
    </div>
  </x-ui.form-modal>

  {{-- FINISH MODAL --}}
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

  {{-- DELETE MODAL --}}
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
