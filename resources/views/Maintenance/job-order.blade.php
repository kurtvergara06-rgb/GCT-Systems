<x-layout.app
  title="FROMS - Job Orders"
  :assets="[
    'resources/css/Maintenance/job-order.css',
    'resources/css/Main-style/main.css',
    'resources/js/Maintenance/job-order.js'
  ]"
>

  {{-- SUCCESS POPUP --}}
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

      {{-- TOP BAR --}}
      <header class="topbar">
        <div>
          <h1>Job Orders</h1>
          <p>Manage repair and preventive maintenance service requests</p>
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

      {{-- ERROR ALERT --}}
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
            <p>Track job order details, assigned mechanics, and status</p>
          </div>
        </div>

        <form method="GET" action="{{ route('job-orders') }}" class="toolbar job-toolbar">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input
              type="text"
              name="search"
              value="{{ request('search') }}"
              placeholder="Search JO no., bus, problem, or mechanic..."
            >
          </div>

          <div class="filter-group">
            <label>Status</label>
            <select name="status" onchange="this.form.submit()">
              <option value="All Statuses" {{ request('status') == 'All Statuses' ? 'selected' : '' }}>All Statuses</option>
              <option value="On Hold" {{ request('status') == 'On Hold' ? 'selected' : '' }}>On Hold</option>
              <option value="On Going" {{ request('status') == 'On Going' ? 'selected' : '' }}>On Going</option>
              <option value="Completed" {{ request('status') == 'Completed' ? 'selected' : '' }}>Completed</option>
              <option value="Urgent Repair" {{ request('status') == 'Urgent Repair' ? 'selected' : '' }}>Urgent Repair</option>
            </select>
          </div>

          <div class="filter-group">
            <label>Maintenance Type</label>
            <select name="maintenance_type" onchange="this.form.submit()">
              <option value="All Types" {{ request('maintenance_type') == 'All Types' ? 'selected' : '' }}>All Types</option>
              <option value="PMS" {{ request('maintenance_type') == 'PMS' ? 'selected' : '' }}>PMS</option>
              <option value="Repair" {{ request('maintenance_type') == 'Repair' ? 'selected' : '' }}>Repair</option>
              <option value="Urgent Repair" {{ request('maintenance_type') == 'Urgent Repair' ? 'selected' : '' }}>Urgent Repair</option>
            </select>
          </div>

          <button type="button" class="primary-btn" id="openJobModal">
            <i class="fa-solid fa-plus"></i>
            New JO
          </button>
        </form>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th><input type="checkbox"></th>
                <th>JO No.</th>
                <th>Bus #</th>
                <th>Problem / Issue</th>
                <th>Maintenance Type</th>
                <th>Assigned Mechanic</th>
                <th>Start Date</th>
                <th>Completion Date</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              @forelse($jobOrders as $jobOrder)
                <tr>
                  <td><input type="checkbox"></td>

                  <td>{{ $jobOrder->job_order_no }}</td>
                  <td>{{ $jobOrder->bus_no }}</td>

                  <td class="problem-cell">
                    <span title="{{ $jobOrder->problem_issue }}">
                      {{ \Illuminate\Support\Str::limit($jobOrder->problem_issue, 35, '...') }}
                    </span>
                  </td>

                  <td>{{ $jobOrder->maintenance_type }}</td>

                  <td class="{{ $jobOrder->assigned_mechanic ? '' : 'empty' }}">
                    {{ $jobOrder->assigned_mechanic ?? '—' }}
                  </td>

                  <td class="{{ $jobOrder->start_date ? '' : 'empty' }}">
                    {{ $jobOrder->start_date ? date('M d, Y', strtotime($jobOrder->start_date)) : '—' }}
                  </td>

                  <td class="{{ $jobOrder->completion_date ? '' : 'empty' }}">
                    {{ $jobOrder->completion_date ? date('M d, Y', strtotime($jobOrder->completion_date)) : '—' }}
                  </td>

                  <td>
                    @php
                      $badgeClass = match($jobOrder->status) {
                        'On Hold' => 'hold',
                        'On Going' => 'ongoing',
                        'Completed' => 'completed',
                        'Urgent Repair' => 'urgent',
                        default => 'hold'
                      };
                    @endphp

                    <span class="badge {{ $badgeClass }}">
                      {{ $jobOrder->status }}
                    </span>
                  </td>

                  <td>
                    <div class="actions">
                      <button
                        type="button"
                        class="edit open-edit-modal"
                        data-id="{{ $jobOrder->id }}"
                        data-job-order-no="{{ $jobOrder->job_order_no }}"
                        data-bus-no="{{ $jobOrder->bus_no }}"
                        data-problem-issue="{{ $jobOrder->problem_issue }}"
                        data-maintenance-type="{{ $jobOrder->maintenance_type }}"
                        data-assigned-mechanic="{{ $jobOrder->assigned_mechanic }}"
                        data-start-date="{{ $jobOrder->start_date }}"
                        data-completion-date="{{ $jobOrder->completion_date }}"
                        data-status="{{ $jobOrder->status }}"
                        title="View / Edit"
                      >
                        <i class="fa-solid fa-pen-to-square"></i>
                      </button>

                      <form
                        id="deleteForm-{{ $jobOrder->id }}"
                        action="{{ route('job-orders.destroy', $jobOrder->id) }}"
                        method="POST"
                      >
                        @csrf
                        @method('DELETE')

                        <button
                          type="button"
                          class="delete open-delete-modal"
                          data-id="{{ $jobOrder->id }}"
                          data-jo-no="{{ $jobOrder->job_order_no }}"
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
                  <td colspan="10" style="text-align:center; padding: 30px;">
                    No job orders found.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="table-footer">
          <p>
            Showing {{ $jobOrders->firstItem() ?? 0 }} to {{ $jobOrders->lastItem() ?? 0 }} of {{ $jobOrders->total() }} entries
          </p>

          <div class="pagination">
            {{ $jobOrders->links() }}
          </div>
        </div>

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
      <input type="text" name="job_order_no" placeholder="Example: JO-26-0001" required>
    </div>

    <div class="form-group">
      <label>Bus #</label>
      <input type="text" name="bus_no" placeholder="Example: BUS-001" required>
    </div>

    <div class="form-group full-width">
      <label>Problem / Issue</label>
      <textarea name="problem_issue" placeholder="Describe the problem or issue..." required></textarea>
    </div>

    <div class="form-group">
      <label>Maintenance Type</label>
      <select name="maintenance_type" required>
        <option value="">Select Maintenance Type</option>
        <option value="PMS">PMS</option>
        <option value="Repair">Repair</option>
        <option value="Urgent Repair">Urgent Repair</option>
      </select>
    </div>

    <div class="form-group">
      <label>Status</label>
      <select name="status" required>
        <option value="On Hold">On Hold</option>
        <option value="On Going">On Going</option>
        <option value="Completed">Completed</option>
        <option value="Urgent Repair">Urgent Repair</option>
      </select>
    </div>

    <div class="form-group full-width">
      <label>Assigned Mechanic</label>
      <input type="text" name="assigned_mechanic" placeholder="Optional">
    </div>

    <div class="form-section-divider full-width">
      <span>Schedule Details</span>
    </div>

    <div class="form-row time-section full-width">
      <div class="form-group">
        <label>Start Date</label>
        <input type="date" name="start_date">
      </div>

      <div class="form-group">
        <label>Completion Date</label>
        <input type="date" name="completion_date">
      </div>
    </div>
  </x-ui.form-modal>

  {{-- EDIT JO MODAL --}}
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
      <input type="text" name="job_order_no" id="edit_job_order_no" required>
    </div>

    <div class="form-group">
      <label>Bus #</label>
      <input type="text" name="bus_no" id="edit_bus_no" required>
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
        <option value="Urgent Repair">Urgent Repair</option>
      </select>
    </div>

    <div class="form-group">
      <label>Status</label>
      <select name="status" id="edit_status" required>
        <option value="On Hold">On Hold</option>
        <option value="On Going">On Going</option>
        <option value="Completed">Completed</option>
        <option value="Urgent Repair">Urgent Repair</option>
      </select>
    </div>

    <div class="form-group full-width">
      <label>Assigned Mechanic</label>
      <input type="text" name="assigned_mechanic" id="edit_assigned_mechanic">
    </div>

    <div class="form-section-divider full-width">
      <span>Schedule Details</span>
    </div>

    <div class="form-row time-section full-width">
      <div class="form-group">
        <label>Start Date</label>
        <input type="date" name="start_date" id="edit_start_date">
      </div>

      <div class="form-group">
        <label>Completion Date</label>
        <input type="date" name="completion_date" id="edit_completion_date">
      </div>
    </div>
  </x-ui.form-modal>

  {{-- DELETE MODAL --}}
  <div id="deleteJobModal" class="delete-modal-overlay">
    <div class="delete-modal-box">
      <div class="delete-icon">
        <i class="fa-solid fa-triangle-exclamation"></i>
      </div>

      <h2>Delete Job Order?</h2>

      <p>
        Are you sure you want to delete
        <strong id="deleteJoNo">this job order</strong>?
        This action can’t be undone.
      </p>

      <div class="delete-modal-actions">
        <button type="button" id="cancelDeleteJob" class="cancel-btn">
          Cancel
        </button>

        <button type="button" id="confirmDeleteJob" class="danger-btn">
          Yes, Delete
        </button>
      </div>
    </div>
  </div>

</x-layout.app>