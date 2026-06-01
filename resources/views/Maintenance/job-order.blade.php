<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>FROMS - Job Orders</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  @vite([
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Maintenance/job-order.css',
    'resources/js/Maintenance/job-order.js'
  ])
</head>

<body>

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

    <!-- SIDEBAR -->
    <aside class="sidebar">

      <div class="brand">
        <div class="brand-icon">
          <i class="fa-solid fa-truck"></i>
        </div>

        <div>
          <h2>Maintenance</h2>
          <p>Department Module</p>
        </div>
      </div>

      <nav class="menu">
        <a href="{{ route('dashboard-maintenance') }}" class="menu-item">
          <i class="fa-solid fa-table-cells-large"></i>
          <span>Dashboard</span>
        </a>

        <a href="{{ route('job-orders') }}" class="menu-item active">
          <i class="fa-solid fa-clipboard-list"></i>
          <span>Job Orders</span>
        </a>

        <a href="{{ route('mechanic-list') }}" class="menu-item">
          <i class="fa-solid fa-bus"></i>
          <span>Mechanic List</span>
        </a>

        <a href="{{ route('PMS-Scheduling') }}" class="menu-item">
          <i class="fa-solid fa-calendar-check"></i>
          <span>PMS Scheduling</span>
        </a>

        <a href="{{ route('purchase-requests') }}" class="menu-item">
          <i class="fa-solid fa-file-invoice"></i>
          <span>Purchase Requests</span>
        </a>

        <a href="{{ route('fuel-reports') }}" class="menu-item">
          <i class="fa-solid fa-gas-pump"></i>
          <span>Fuel Reports</span>
        </a>

        <a href="{{ route('settings') }}" class="menu-item">
          <i class="fa-solid fa-gear"></i>
          <span>Settings</span>
        </a>
      </nav>

      <div class="user-box">
        <div class="avatar">
          <i class="fa-solid fa-user"></i>
        </div>

        <div>
          <h4>R. Lim</h4>
          <p>Maintenance Admin</p>
        </div>

        <i class="fa-solid fa-chevron-down"></i>
      </div>

    </aside>

    <!-- MAIN -->
    <main class="main">

      <!-- TOP BAR -->
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

      @if($errors->any())
        <div class="alert-error">
          <ul>
            @foreach($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <!-- SUMMARY CARDS -->
      <section class="stats-grid">

        <div class="stat-card">
          <div class="stat-icon yellow">
            <i class="fa-solid fa-pause"></i>
          </div>

          <div>
            <p>On Hold</p>
            <h2>{{ $onHold }}</h2>
            <small>Job Orders</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon blue">
            <i class="fa-solid fa-spinner"></i>
          </div>

          <div>
            <p>On Going</p>
            <h2>{{ $onGoing }}</h2>
            <small>Job Order</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon green">
            <i class="fa-solid fa-check"></i>
          </div>

          <div>
            <p>Completed</p>
            <h2>{{ $completed }}</h2>
            <small>Job Orders</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon red">
            <i class="fa-solid fa-triangle-exclamation"></i>
          </div>

          <div>
            <p>Urgent Repair</p>
            <h2>{{ $urgentRepair }}</h2>
            <small>Needs attention</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

      </section>

      <!-- JOB ORDERS TABLE -->
      <section class="table-card">

        <div class="section-header">
          <div>
            <h2>Job Orders</h2>
            <p>Track service requests, assigned mechanics, and job order status</p>
          </div>
        </div>

        <form method="GET" action="{{ route('job-orders') }}" class="toolbar job-toolbar">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input
              type="text"
              name="search"
              value="{{ request('search') }}"
              placeholder="Search service, JO no., bus, or mechanic..."
            >
          </div>

          <div class="filter-group">
            <label>Status</label>
            <select name="status" onchange="this.form.submit()">
              <option value="All Statuses" {{ request('status') == 'All Statuses' ? 'selected' : '' }}>
                All Statuses
              </option>
              <option value="On Hold" {{ request('status') == 'On Hold' ? 'selected' : '' }}>
                On Hold
              </option>
              <option value="On Going" {{ request('status') == 'On Going' ? 'selected' : '' }}>
                On Going
              </option>
              <option value="Completed" {{ request('status') == 'Completed' ? 'selected' : '' }}>
                Completed
              </option>
              <option value="Urgent Repair" {{ request('status') == 'Urgent Repair' ? 'selected' : '' }}>
                Urgent Repair
              </option>
            </select>
          </div>

          <div class="filter-group">
            <label>Type</label>
            <select name="type" onchange="this.form.submit()">
              <option value="All Types" {{ request('type') == 'All Types' ? 'selected' : '' }}>
                All Types
              </option>
              <option value="PMS" {{ request('type') == 'PMS' ? 'selected' : '' }}>
                PMS
              </option>
              <option value="Repair" {{ request('type') == 'Repair' ? 'selected' : '' }}>
                Repair
              </option>
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
                <th>Service</th>
                <th>Type</th>
                <th>Assigned Mechanic</th>
                <th>Status</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Date Reported</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              @forelse($jobOrders as $jobOrder)
                <tr>
                  <td>
                    <input type="checkbox">
                  </td>

                  <td>{{ $jobOrder->job_order_no }}</td>

                  <td>{{ $jobOrder->bus_no }}</td>

                  <td>{{ $jobOrder->service }}</td>

                  <td>{{ $jobOrder->type }}</td>

                  <td class="{{ $jobOrder->assigned_mechanic ? '' : 'empty' }}">
                    {{ $jobOrder->assigned_mechanic ?? '—' }}
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

                  <td class="{{ $jobOrder->start_time ? '' : 'empty' }}">
                    @if($jobOrder->start_time)
                      {{ date('g:i A', strtotime($jobOrder->start_time)) }}
                    @else
                      —
                    @endif
                  </td>

                  <td class="{{ $jobOrder->end_time ? '' : 'empty' }}">
                    @if($jobOrder->end_time)
                      {{ date('g:i A', strtotime($jobOrder->end_time)) }}
                    @else
                      —
                    @endif
                  </td>

                  <td>
                    {{ date('M d, Y h:i A', strtotime($jobOrder->date_reported)) }}
                  </td>

                  <td>
                    <div class="actions">
                      <button
                        type="button"
                        class="edit open-edit-modal"
                        data-id="{{ $jobOrder->id }}"
                        data-job-order-no="{{ $jobOrder->job_order_no }}"
                        data-bus-no="{{ $jobOrder->bus_no }}"
                        data-service="{{ $jobOrder->service }}"
                        data-type="{{ $jobOrder->type }}"
                        data-assigned-mechanic="{{ $jobOrder->assigned_mechanic }}"
                        data-status="{{ $jobOrder->status }}"
                        data-start-time="{{ $jobOrder->start_time }}"
                        data-end-time="{{ $jobOrder->end_time }}"
                        data-date-reported="{{ date('Y-m-d\TH:i', strtotime($jobOrder->date_reported)) }}"
                      >
                        <i class="fa-solid fa-pen"></i>
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
                        >
                          <i class="fa-solid fa-trash"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="11" style="text-align:center; padding: 30px;">
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

  <!-- NEW JOB ORDER MODAL -->
  <div id="jobModal" class="modal-overlay">
    <div class="modal-box wide-modal">
      <div class="modal-header">
        <h2>New Job Order</h2>
        <button type="button" id="closeJobModal" class="close-btn">&times;</button>
      </div>

      <form action="{{ route('job-orders.store') }}" method="POST" class="job-form wide-form">
        @csrf

        <div class="form-section-title full-width">
          <h3>Job Order Details</h3>
          <p>Enter the basic information of the service request.</p>
        </div>

        <div class="form-group">
          <label>JO No.</label>
          <input type="text" name="job_order_no" placeholder="Example: JO-26-0001" required>
        </div>

        <div class="form-group">
          <label>Bus #</label>
          <input type="text" name="bus_no" placeholder="Example: BUS-001" required>
        </div>

        <div class="form-group full-width">
          <label>Service</label>
          <input type="text" name="service" placeholder="Example: Engine Oil Filter" required>
        </div>

        <div class="form-group">
          <label>Type</label>
          <select name="type" required>
            <option value="">Select Type</option>
            <option value="PMS">PMS</option>
            <option value="Repair">Repair</option>
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
          <span>Work Time Details</span>
        </div>

        <div class="form-row time-section full-width">
          <div class="form-group">
            <label>Start Time</label>
            <input type="time" name="start_time">
          </div>

          <div class="form-group">
            <label>End Time</label>
            <input type="time" name="end_time">
          </div>
        </div>

        <div class="form-group full-width">
          <label>Date Reported</label>
          <input type="datetime-local" name="date_reported" required>
        </div>

        <div class="modal-actions full-width">
          <button type="button" id="cancelJobModal" class="cancel-btn">
            Cancel
          </button>

          <button type="submit" class="save-btn">
            Save Job Order
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- EDIT / DETAILS JOB ORDER MODAL -->
  <div id="editJobModal" class="modal-overlay">
    <div class="modal-box wide-modal">
      <div class="modal-header">
        <h2>Job Order Details</h2>
        <button type="button" id="closeEditJobModal" class="close-btn">&times;</button>
      </div>

      <form id="editJobForm" method="POST" class="job-form wide-form">
        @csrf
        @method('PUT')

        <div class="form-section-title full-width">
          <h3>Editable JO Information</h3>
          <p>Review and update the selected job order.</p>
        </div>

        <div class="form-group">
          <label>JO No.</label>
          <input type="text" name="job_order_no" id="edit_job_order_no" required>
        </div>

        <div class="form-group">
          <label>Bus #</label>
          <input type="text" name="bus_no" id="edit_bus_no" required>
        </div>

        <div class="form-group full-width">
          <label>Service</label>
          <input type="text" name="service" id="edit_service" required>
        </div>

        <div class="form-group">
          <label>Type</label>
          <select name="type" id="edit_type" required>
            <option value="PMS">PMS</option>
            <option value="Repair">Repair</option>
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
          <span>Work Time Details</span>
        </div>

        <div class="form-row time-section full-width">
          <div class="form-group">
            <label>Start Time</label>
            <input type="time" name="start_time" id="edit_start_time">
          </div>

          <div class="form-group">
            <label>End Time</label>
            <input type="time" name="end_time" id="edit_end_time">
          </div>
        </div>

        <div class="form-group full-width">
          <label>Date Reported</label>
          <input type="datetime-local" name="date_reported" id="edit_date_reported" required>
        </div>

        <div class="modal-actions full-width">
          <button type="button" id="cancelEditJobModal" class="cancel-btn">
            Cancel
          </button>

          <button type="submit" class="save-btn">
            Update Job Order
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- DELETE CONFIRMATION MODAL -->
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

</body>
</html>