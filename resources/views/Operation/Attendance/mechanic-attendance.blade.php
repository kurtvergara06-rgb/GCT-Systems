<x-layout.app
  title="FROMS - Mechanic Attendance"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Operation/Attendance/available-mechanics.css',
    'resources/css/Operation/Attendance/batch-attendance.css',
    'resources/js/Main-js/sidebar.js',
    'resources/js/Operation/Attendance/mechanic-attendance.js',
    'resources/js/Operation/Attendance/batch-attendance.js'
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
    /* Edit modal: align native selectors with the custom 46px date/time pickers. */
    #editMechanicAttendanceModal .form-group > input:not([type="hidden"]),
    #editMechanicAttendanceModal .form-group > select,
    #editMechanicAttendanceModal .form-group > .gct-picker-trigger {
      box-sizing: border-box;
      width: 100%;
      height: 46px;
      min-height: 46px;
      border-radius: 10px;
    }
    #editMechanicAttendanceModal #edit_assigned_job[readonly] {
      background: #f3f6fa;
      color: #64748b;
      cursor: not-allowed;
    }
    #editMechanicAttendanceModal .mechanic-attendance-field-hint {
      display: block;
      margin-top: 5px;
      font-size: 11px;
      line-height: 1.4;
      color: #64748b;
    }
  </style>

  <div class="app">

  <x-layout.sidebar department="Operation" />

    <main class="main mechanic-attendance-page">
      <x-layout.topbar
        title="Mechanic Attendance"
        subtitle="Manage and track mechanic attendance and availability"
        notification-count="6"
      />

      @if(isset($errors) && $errors->any())
        <div class="alert-error">
          <ul>
            @foreach($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <section data-ajax-region="summary" class="stats-grid">
        <x-ui.summary-card label="Present" value="{{ $present }}" small="Mechanics today" icon="fa-user-check" color="green" />
        <x-ui.summary-card label="Absent" value="{{ $absent }}" small="Mechanics absent" icon="fa-user-xmark" color="red" />
        <x-ui.summary-card label="Late" value="{{ $late }}" small="Mechanics who were late" icon="fa-clock" color="yellow" />
        <x-ui.summary-card label="On Duty" value="{{ $onDuty }}" small="Assigned mechanics" icon="fa-screwdriver-wrench" color="blue" />
      </section>

      <section data-ajax-region="records" class="table-card attendance-card">
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
          data-server-filter="true"
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
            <select name="status" aria-label="Status">
              <option value="All Status" {{ request('status') == 'All Status' ? 'selected' : '' }}>All Status</option>
              <option value="Present" {{ request('status') == 'Present' ? 'selected' : '' }}>Present</option>
              <option value="Late" {{ request('status') == 'Late' ? 'selected' : '' }}>Late</option>
              <option value="On Duty" {{ request('status') == 'On Duty' ? 'selected' : '' }}>On Duty</option>
              <option value="Absent" {{ request('status') == 'Absent' ? 'selected' : '' }}>Absent</option>
              <option value="On Leave" {{ request('status') == 'On Leave' ? 'selected' : '' }}>On Leave</option>
            </select>
          </div>

          <div class="filter-group">
            <label class="sr-only" for="mechanicAttendanceFilterDate">Attendance Date</label>
            <input
              type="date"
              id="mechanicAttendanceFilterDate"
              name="attendance_date"
              value="{{ $summaryDate }}"
              aria-label="Filter attendance by date"
              title="Choose attendance date"
            >
          </div>

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
            <colgroup><col style="width: 12%"><col style="width: 19%"><col style="width: 10%"><col style="width: 19%"><col style="width: 10%"><col style="width: 10%"><col style="width: 10%"><col style="width: 10%"></colgroup>
            <thead>
              <tr>
                <th>ID</th>
                <th>Mechanic</th>
                <th>Shift</th>
                <th>Assigned Job</th>
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
                  <td><span class="system-id-badge system-id-badge--small">{{ $attendance->mechanic_id }}</span></td>
                  <td><span class="mechanic-attendance-name-chip">{{ $attendance->mechanic_name }}</span></td>
                  <td><span class="gct-pill gct-pill--shift-{{ strtolower($attendance->shift) }}">{{ $attendance->shift }}</span></td>
                  <td><span class="gct-pill {{ $attendance->display_assigned_job === 'Unassigned' ? 'gct-pill--unassigned' : 'gct-pill--scheduled' }}">{{ $attendance->display_assigned_job }}</span></td>
                  <td>{{ $attendance->time_in ? date('h:i A', strtotime($attendance->time_in)) : '--:--' }}</td>
                  <td>{{ $attendance->time_out ? date('h:i A', strtotime($attendance->time_out)) : '--:--' }}</td>
                  <td><span class="badge gct-pill gct-pill--attendance {{ $statusClass }}">{{ $attendance->status }}</span></td>
                  <td>
                    <div class="actions">
                      <button type="button" class="action-btn view open-view-mechanic-attendance-modal"
                        title="View" aria-label="View mechanic attendance"
                        data-mechanic-id="{{ $attendance->mechanic_id }}"
                        data-mechanic-name="{{ $attendance->mechanic_name }}"
                        data-shift="{{ $attendance->shift }}"
                        data-assigned-job="{{ $attendance->display_assigned_job }}"
                        data-attendance-date="{{ $attendance->attendance_date ? $attendance->attendance_date->format('M d, Y') : '—' }}"
                        data-time-in="{{ $attendance->time_in ? date('h:i A', strtotime($attendance->time_in)) : '--:--' }}"
                        data-time-out="{{ $attendance->time_out ? date('h:i A', strtotime($attendance->time_out)) : '--:--' }}"
                        data-status="{{ $attendance->status }}">
                        <i class="fa-solid fa-eye"></i>
                      </button>
                      <x-ui.action-buttom-modal
                        class="edit open-edit-attendance-modal"
                        title="Edit"
                        icon="fa-pen-to-square"
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
                <x-ui.empty-row colspan="8" message="No mechanic attendance records found." />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$mechanicAttendances" />
      </section>
    </main>
  </div>

  <div id="viewMechanicAttendanceModal" class="modal-overlay">
    <div class="modal-box wide-modal" role="dialog" aria-modal="true" aria-labelledby="viewMechanicAttendanceTitle">
      <div class="modal-header">
        <h2 id="viewMechanicAttendanceTitle">Mechanic Attendance Details</h2>
        <button type="button" id="closeViewMechanicAttendanceModal" class="close-btn" aria-label="Close">&times;</button>
      </div>
      <div class="attendance-details-grid" id="viewMechanicAttendanceContent"></div>
      <div class="modal-actions">
        <button type="button" id="dismissViewMechanicAttendanceModal" class="cancel-btn">Close</button>
      </div>
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
          <input type="text" id="edit_assigned_job" readonly aria-readonly="true" title="Assigned jobs are managed from Maintenance Job Orders">
          <small class="mechanic-attendance-field-hint">Assigned jobs are managed through Maintenance Job Orders.</small>
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
