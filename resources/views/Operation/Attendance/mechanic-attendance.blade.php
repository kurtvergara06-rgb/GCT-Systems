<x-layout.app
  title="FROMS - Mechanic Attendance"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Operation/Attendance/available-mechanics.css',
    'resources/js/Main-js/sidebar.js',
    'resources/js/Operation/Attendance/mechanic-attendance.js'
  ]"
>

  <style>
    .badge.leave {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 78px;
      padding: 6px 12px;
      border: 1px solid #c4b5fd;
      border-radius: 999px;
      background: #ede9fe;
      color: #7c3aed;
      font-weight: 700;
      line-height: 1;
    }
  </style>

  <div class="app">

  <x-layout.sidebar
    department="Operation"
    subtitle="Operation Module"
    icon="fa-bus"
    :items="[
        ['label' => 'Dashboard', 'route' => 'dashboard-operation', 'icon' => 'fa-table-cells-large'],
        ['label' => 'Routes', 'route' => 'operation.routes', 'icon' => 'fa-route'],
        [
            'label' => 'Scheduling',
            'icon' => 'fa-calendar-days',
            'children' => [
                ['label' => 'Trip Schedule', 'route' => 'trip-schedule', 'icon' => 'fa-calendar-days'],
                ['label' => 'Driver & Bus Assignment', 'route' => 'driver-bus-assignment', 'icon' => 'fa-user-tie'],
                ['label' => 'Auto Scheduling', 'route' => 'auto-scheduling', 'icon' => 'fa-wand-magic-sparkles'],
            ],
        ],
        [
            'label' => 'Personnel Management',
            'icon' => 'fa-address-book',
            'children' => [
                ['label' => 'Driver Master List', 'route' => 'operation.personnel.drivers', 'icon' => 'fa-id-card'],
                ['label' => 'Mechanic Master List', 'route' => 'operation.personnel.mechanics', 'icon' => 'fa-users-gear'],
            ],
        ],
        [
            'label' => 'Attendance',
            'icon' => 'fa-calendar-check',
            'children' => [
                ['label' => 'Driver Attendance', 'route' => 'driver-attendance', 'icon' => 'fa-user-check'],
                ['label' => 'Mechanic Attendance', 'route' => 'mechanic-attendance', 'icon' => 'fa-clipboard-user'],
            ],
        ],
        ['label' => 'Bus Master List', 'route' => 'bus-master-list', 'icon' => 'fa-bus'],
    ]"
/>

    <main class="main">
      <x-layout.topbar
        title="Mechanic Attendance"
        subtitle="Manage and track mechanic attendance and availability"
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

      <section class="stats-grid">
        <x-ui.summary-card label="Present" value="{{ $present }}" small="Mechanics today" icon="fa-user-check" color="green" />
        <x-ui.summary-card label="Absent" value="{{ $absent }}" small="Mechanics absent" icon="fa-user-xmark" color="red" />
        <x-ui.summary-card label="Late" value="{{ $late }}" small="Mechanics who were late" icon="fa-clock" color="yellow" />
        <x-ui.summary-card label="On Duty" value="{{ $onDuty }}" small="Assigned mechanics" icon="fa-screwdriver-wrench" color="blue" />
      </section>

      <section class="table-card attendance-card">
        <div class="section-header">
          <div>
            <h2>Mechanic Attendance List</h2>
            <p>Track time-in, time-out, assigned job, and attendance status</p>
          </div>
        </div>

        <form
          action="{{ route('mechanic-attendance', [], false) }}"
          method="GET"
          class="toolbar attendance-toolbar"
        >
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input
              type="text"
              name="search"
              value="{{ request('search') }}"
              placeholder="Search mechanic name, ID, or assigned job..."
            >
          </div>

          <div class="filter-group">
            <select name="status" onchange="this.form.submit()" aria-label="Status">
              <option value="All Status" {{ request('status') == 'All Status' ? 'selected' : '' }}>All Status</option>
              <option value="Present" {{ request('status') == 'Present' ? 'selected' : '' }}>Present</option>
              <option value="Late" {{ request('status') == 'Late' ? 'selected' : '' }}>Late</option>
              <option value="On Duty" {{ request('status') == 'On Duty' ? 'selected' : '' }}>On Duty</option>
              <option value="Absent" {{ request('status') == 'Absent' ? 'selected' : '' }}>Absent</option>
              <option value="On Leave" {{ request('status') == 'On Leave' ? 'selected' : '' }}>On Leave</option>
            </select>
          </div>

          <button
            type="button"
            id="openImportAttendanceModal"
            class="secondary-btn import-btn"
          >
            <i class="fa-solid fa-file-import"></i>
            Import Data
          </button>

          <button
            type="button"
            class="primary-btn batch-attendance-open"
            data-batch-attendance-open
          >
            <i class="fa-solid fa-clipboard-check"></i>
            Record Daily Attendance
          </button>
        </form>

        <div class="table-wrap">
          <table class="attendance-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Mechanic</th>
                <th>Shift</th>
                <th>Assigned Job</th>
                <th>Date</th>
                <th>Time-in</th>
                <th>Time-out</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              @forelse($mechanicAttendances as $attendance)
                @php
                  $statusClass = match($attendance->status) {
                    'Present' => 'present',
                    'Late' => 'late',
                    'Absent' => 'absent',
                    'On Leave' => 'leave',
                    'On Duty' => 'duty',
                    default => 'present',
                  };
                @endphp

                <tr>
                  <td>{{ $attendance->mechanic_id }}</td>
                  <td>{{ $attendance->mechanic_name }}</td>
                  <td>{{ $attendance->shift }}</td>
                  <td>{{ $attendance->assigned_job ?? 'Available' }}</td>
                  <td>{{ $attendance->attendance_date ? $attendance->attendance_date->format('m/d/y') : '—' }}</td>
                  <td>{{ $attendance->time_in ? date('h:i A', strtotime($attendance->time_in)) : '--:--' }}</td>
                  <td>{{ $attendance->time_out ? date('h:i A', strtotime($attendance->time_out)) : '--:--' }}</td>
                  <td><span class="badge {{ $statusClass }}">{{ $attendance->status }}</span></td>
                  <td>
                    <div class="actions">
                      <x-ui.action-buttom-modal
                        class="edit open-edit-attendance-modal"
                        title="Edit"
                        icon="fa-pen"
                        data-id="{{ $attendance->id }}"
                        data-mechanic-id="{{ $attendance->mechanic_id }}"
                        data-mechanic-name="{{ $attendance->mechanic_name }}"
                        data-shift="{{ $attendance->shift }}"
                        data-assigned-job="{{ $attendance->assigned_job }}"
                        data-attendance-date="{{ $attendance->attendance_date ? $attendance->attendance_date->format('Y-m-d') : '' }}"
                        data-time-in="{{ $attendance->time_in }}"
                        data-time-out="{{ $attendance->time_out }}"
                        data-status="{{ $attendance->status }}"
                        data-update-url="{{ route('mechanic-attendance.update', $attendance->id, false) }}"
                      />

                      <form
                        id="deleteAttendanceForm-{{ $attendance->id }}"
                        action="{{ route('mechanic-attendance.destroy', $attendance->id, false) }}"
                        method="POST"
                      >
                        @csrf
                        @method('DELETE')
                        <button
                          type="button"
                          class="action-btn delete open-delete-attendance-modal"
                          title="Delete"
                          data-id="{{ $attendance->id }}"
                          data-mechanic-id="{{ $attendance->mechanic_id }}"
                          data-mechanic-name="{{ $attendance->mechanic_name }}"
                        >
                          <i class="fa-solid fa-trash"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              @empty
                <x-ui.empty-row colspan="9" message="No mechanic attendance records found." />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$mechanicAttendances" />
      </section>
    </main>
  </div>

  <div id="importAttendanceModal" class="modal-overlay">
    <div class="modal-box">
      <div class="modal-header">
        <h2>Import Mechanic Attendance Data</h2>
        <button type="button" id="closeImportAttendanceModal" class="close-btn">&times;</button>
      </div>

      <form
        id="importAttendanceForm"
        action="{{ route('mechanic-attendance.import', [], false) }}"
        method="POST"
        enctype="multipart/form-data"
        class="job-form"
        data-confirm-form
        data-confirm-title="Import Mechanic Attendance?"
        data-confirm-message="Are you sure you want to import these mechanic attendance records?"
        data-confirm-button="Yes, Import Data"
        data-confirm-type="warning"
      >
        @csrf
        <div class="form-section-title full-width">
          <h3>Upload CSV File</h3>
          <p>Upload mechanic attendance records using a CSV file.</p>
        </div>
        <div class="form-group full-width">
          <label>CSV File</label>
          <input type="file" name="import_file" accept=".csv,.txt" required>
        </div>
        <div class="form-group full-width">
          <small>Required columns: mechanic_name, shift, assigned_job, attendance_date, time_in, time_out, status</small>
        </div>
        <div class="modal-actions full-width">
          <button type="button" id="cancelImportAttendanceModal" class="cancel-btn">Cancel</button>
          <button type="submit" class="save-btn">Import Data</button>
        </div>
      </form>
    </div>
  </div>

  <div id="editMechanicAttendanceModal" class="modal-overlay">
    <div class="modal-box wide-modal">
      <div class="modal-header">
        <h2>Edit Mechanic Attendance</h2>
        <button type="button" id="closeEditMechanicAttendanceModal" class="close-btn">&times;</button>
      </div>

      <form
        id="editMechanicAttendanceForm"
        method="POST"
        class="job-form wide-form"
        data-confirm-form
        data-confirm-title="Update Mechanic Attendance?"
        data-confirm-message="Are you sure you want to update this mechanic attendance record?"
        data-confirm-button="Yes, Update Record"
        data-confirm-type="update"
      >
        @csrf
        @method('PUT')

        <div class="form-section-title full-width">
          <h3>Attendance Details</h3>
          <p>Update mechanic attendance information.</p>
        </div>

        <div class="form-group">
          <label>Mechanic ID</label>
          <input type="text" id="edit_mechanic_id" readonly>
        </div>
        <div class="form-group">
          <label>Mechanic Name</label>
          <input type="text" name="mechanic_name" id="edit_mechanic_name" required>
        </div>
        <div class="form-group">
          <label>Shift</label>
          <select name="shift" id="edit_shift" required>
            <option value="Morning">Morning</option>
            <option value="Afternoon">Afternoon</option>
            <option value="Night">Night</option>
          </select>
        </div>
        <div class="form-group">
          <label>Assigned Job</label>
          <input type="text" name="assigned_job" id="edit_assigned_job">
        </div>
        <div class="form-group">
          <label>Date</label>
          <input type="date" name="attendance_date" id="edit_attendance_date" required>
        </div>
        <div class="form-group">
          <label>Time-in</label>
          <input type="time" name="time_in" id="edit_time_in">
        </div>
        <div class="form-group">
          <label>Time-out</label>
          <input type="time" name="time_out" id="edit_time_out">
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" id="edit_status" required>
            <option value="Present">Present</option>
            <option value="Late">Late</option>
            <option value="Absent">Absent</option>
            <option value="On Leave">On Leave</option>
            <option value="On Duty">On Duty</option>
          </select>
        </div>

        <div class="modal-actions full-width">
          <button type="button" id="cancelEditMechanicAttendanceModal" class="cancel-btn">Cancel</button>
          <button type="submit" class="save-btn">Update Record</button>
        </div>
      </form>
    </div>
  </div>

  <x-ui.action-buttom-modal
    mode="delete"
    id="deleteAttendanceModal"
    delete-title="Delete Attendance Record?"
    delete-message="Are you sure you want to delete"
    name-id="deleteAttendanceName"
    cancel-id="cancelDeleteAttendance"
    confirm-id="confirmDeleteAttendance"
  />

</x-layout.app>
