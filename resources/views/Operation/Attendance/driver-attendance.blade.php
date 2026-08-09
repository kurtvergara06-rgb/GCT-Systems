<x-layout.app
  title="FROMS - Driver Attendance"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Main-styles/form-components.css',
    'resources/css/Operation/Attendance/driver-attendance.css',
    'resources/js/Main-js/sidebar.js',
    'resources/js/Operation/Attendance/driver-attendance.js'
  ]"
>

  <div class="app">

    <x-layout.sidebar
      department="Operation"
      subtitle="Operation Module"
      icon="fa-bus"
      :items="[
          [
              'label' => 'Dashboard',
              'route' => 'dashboard-operation',
              'icon' => 'fa-table-cells-large',
          ],
          [
              'label' => 'Routes',
              'route' => 'operation.routes',
              'icon' => 'fa-route',
          ],
          [
              'label' => 'Scheduling',
              'icon' => 'fa-calendar-days',
              'children' => [
                  [
                      'label' => 'Trip Schedule',
                      'route' => 'trip-schedule',
                      'icon' => 'fa-calendar-days',
                  ],
                  [
                      'label' => 'Driver & Bus Assignment',
                      'route' => 'driver-bus-assignment',
                      'icon' => 'fa-user-tie',
                  ],
                  [
                      'label' => 'Auto Scheduling',
                      'route' => 'auto-scheduling',
                      'icon' => 'fa-wand-magic-sparkles',
                  ],
              ],
          ],
          [
              'label' => 'Personnel Management',
              'icon' => 'fa-address-book',
              'children' => [
                  [
                      'label' => 'Driver Master List',
                      'route' => 'operation.personnel.drivers',
                      'icon' => 'fa-id-card',
                  ],
                  [
                      'label' => 'Mechanic Master List',
                      'route' => 'operation.personnel.mechanics',
                      'icon' => 'fa-users-gear',
                  ],
              ],
          ],
          [
              'label' => 'Attendance',
              'icon' => 'fa-calendar-check',
              'children' => [
                  [
                      'label' => 'Driver Attendance',
                      'route' => 'driver-attendance',
                      'icon' => 'fa-user-check',
                  ],
                  [
                      'label' => 'Mechanic Attendance',
                      'route' => 'mechanic-attendance',
                      'icon' => 'fa-clipboard-user',
                  ],
              ],
          ],
          [
              'label' => 'Bus Master List',
              'route' => 'bus-master-list',
              'icon' => 'fa-bus',
          ],
      ]"
    />

    <main class="main">

      <x-layout.topbar
        title="Driver Attendance"
        subtitle="Manage and track driver attendance and availability"
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
        <x-ui.summary-card
          label="Present"
          value="{{ $present }}"
          small="Drivers present"
          icon="fa-user-check"
          color="green"
        />
        <x-ui.summary-card
          label="Absent"
          value="{{ $absent }}"
          small="Drivers absent"
          icon="fa-user-xmark"
          color="red"
        />
        <x-ui.summary-card
          label="Late"
          value="{{ $late }}"
          small="Drivers who were late"
          icon="fa-clock"
          color="yellow"
        />
        <x-ui.summary-card
          label="On Duty"
          value="{{ $onDuty }}"
          small="Assigned drivers"
          icon="fa-bus"
          color="blue"
        />
      </section>

      <section class="table-card attendance-card">
        <div class="section-header">
          <div>
            <h2>Driver Attendance List</h2>
            <p>Track driver attendance, bus assignment, time-in, time-out, and attendance status</p>
          </div>
        </div>

        <form
          action="{{ route('driver-attendance', [], false) }}"
          method="GET"
          class="toolbar attendance-toolbar"
        >
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input
              type="text"
              name="search"
              value="{{ request('search') }}"
              placeholder="Search driver name, ID, bus, or shift..."
            >
          </div>

          <div class="filter-group">
            <label>Status</label>
            <select name="status" onchange="this.form.submit()">
              <option value="All Status" {{ request('status', 'All Status') === 'All Status' ? 'selected' : '' }}>All Status</option>
              <option value="Present" {{ request('status') === 'Present' ? 'selected' : '' }}>Present</option>
              <option value="Late" {{ request('status') === 'Late' ? 'selected' : '' }}>Late</option>
              <option value="On Duty" {{ request('status') === 'On Duty' ? 'selected' : '' }}>On Duty</option>
              <option value="Absent" {{ request('status') === 'Absent' ? 'selected' : '' }}>Absent</option>
              <option value="On Leave" {{ request('status') === 'On Leave' ? 'selected' : '' }}>On Leave</option>
            </select>
          </div>

          <button
            type="button"
            id="openImportDriverAttendanceModal"
            class="secondary-btn import-btn"
          >
            <i class="fa-solid fa-file-import"></i>
            Import Data
          </button>
        </form>

        <div class="table-wrap">
          <table class="attendance-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Driver</th>
                <th>Role</th>
                <th>Shift</th>
                <th>Current Assignment</th>
                <th>Date</th>
                <th>Time-in</th>
                <th>Time-out</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              @forelse($driverAttendances as $attendance)
                @php
                  $statusClass = match($attendance->status) {
                    'Present' => 'present',
                    'Late' => 'late',
                    'Absent' => 'absent',
                    'On Leave' => 'leave',
                    'On Duty' => 'duty',
                    default => 'present',
                  };

                  $activeAssignments = $attendance
                    ->tripAssignments
                    ->filter(function ($assignment) {
                      $trip = $assignment->tripSchedule;

                      return $trip
                        && ! in_array(
                          $trip->status,
                          ['Cancelled', 'Completed'],
                          true
                        );
                    })
                    ->sortBy(function ($assignment) {
                      return optional($assignment->tripSchedule)->departure_time;
                    })
                    ->values();

                  $primaryAssignment = $activeAssignments->first();
                  $primaryTrip = $primaryAssignment?->tripSchedule;
                  $primaryBus = $primaryAssignment?->bus;

                  $assignmentSummary = $primaryAssignment
                    ? trim(
                        ($primaryBus?->bus_no ?? 'Bus unavailable')
                        . ' | '
                        . ($primaryTrip?->trip_code ?? 'Trip unavailable')
                      )
                    : 'Unassigned';

                  if ($activeAssignments->count() > 1) {
                    $assignmentSummary .= ' | ' . $activeAssignments->count() . ' assigned trips';
                  }
                @endphp

                <tr>
                  <td>{{ $attendance->driver_id }}</td>
                  <td>{{ $attendance->driver_name }}</td>
                  <td>Driver</td>
                  <td>{{ $attendance->shift }}</td>
                  <td>
                    @if($primaryAssignment)
                      <div class="current-assignment-cell">
                        <span class="current-assignment-bus">
                          {{ $primaryBus?->bus_no ?? 'Bus unavailable' }}
                        </span>
                        <span class="current-assignment-trip">
                          {{ $primaryTrip?->trip_code ?? 'Trip unavailable' }}
                          @if($primaryTrip)
                            • {{ date('h:i A', strtotime($primaryTrip->departure_time)) }}
                            – {{ date('h:i A', strtotime($primaryTrip->estimated_arrival_time)) }}
                          @endif
                        </span>
                        @if($activeAssignments->count() > 1)
                          <small class="current-assignment-more">
                            +{{ $activeAssignments->count() - 1 }} more assignment(s)
                          </small>
                        @endif
                      </div>
                    @else
                      <span class="current-assignment-empty">Unassigned</span>
                    @endif
                  </td>
                  <td>{{ $attendance->attendance_date ? $attendance->attendance_date->format('m/d/y') : '—' }}</td>
                  <td>{{ $attendance->time_in ? date('h:i A', strtotime($attendance->time_in)) : '--:--' }}</td>
                  <td>{{ $attendance->time_out ? date('h:i A', strtotime($attendance->time_out)) : '--:--' }}</td>
                  <td>
                    <span class="badge {{ $statusClass }}">{{ $attendance->status }}</span>
                  </td>
                  <td>
                    <div class="actions">
                      <button
                        type="button"
                        class="action-btn view open-view-driver-attendance-modal"
                        title="View"
                        data-driver-id="{{ $attendance->driver_id }}"
                        data-driver-name="{{ $attendance->driver_name }}"
                        data-shift="{{ $attendance->shift }}"
                        data-bus-assignment="{{ $assignmentSummary }}"
                        data-attendance-date="{{ $attendance->attendance_date ? $attendance->attendance_date->format('M d, Y') : '—' }}"
                        data-time-in="{{ $attendance->time_in ? date('h:i A', strtotime($attendance->time_in)) : '--:--' }}"
                        data-time-out="{{ $attendance->time_out ? date('h:i A', strtotime($attendance->time_out)) : '--:--' }}"
                        data-status="{{ $attendance->status }}"
                      >
                        <i class="fa-solid fa-eye"></i>
                      </button>

                      <button
                        type="button"
                        class="action-btn edit open-edit-driver-attendance-modal"
                        title="Edit"
                        data-id="{{ $attendance->id }}"
                        data-driver-id="{{ $attendance->driver_id }}"
                        data-driver-name="{{ $attendance->driver_name }}"
                        data-shift="{{ $attendance->shift }}"
                        data-attendance-date="{{ $attendance->attendance_date ? $attendance->attendance_date->format('Y-m-d') : '' }}"
                        data-time-in="{{ $attendance->time_in }}"
                        data-time-out="{{ $attendance->time_out }}"
                        data-status="{{ $attendance->status }}"
                        data-update-url="{{ route('driver-attendance.update', $attendance->id, false) }}"
                      >
                        <i class="fa-solid fa-pen-to-square"></i>
                      </button>

                      <form
                        id="deleteDriverAttendanceForm-{{ $attendance->id }}"
                        action="{{ route('driver-attendance.destroy', $attendance->id, false) }}"
                        method="POST"
                      >
                        @csrf
                        @method('DELETE')
                        <button
                          type="button"
                          class="action-btn delete open-delete-driver-attendance-modal"
                          title="Delete"
                          data-id="{{ $attendance->id }}"
                          data-driver-id="{{ $attendance->driver_id }}"
                          data-driver-name="{{ $attendance->driver_name }}"
                        >
                          <i class="fa-solid fa-trash"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              @empty
                <x-ui.empty-row colspan="10" message="No driver attendance records found." />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$driverAttendances" />
      </section>
    </main>
  </div>

  {{-- IMPORT DRIVER ATTENDANCE MODAL --}}
  <div id="importDriverAttendanceModal" class="modal-overlay">
    <div class="modal-box">
      <div class="modal-header">
        <h2>Import Driver Attendance Data</h2>
        <button type="button" id="closeImportDriverAttendanceModal" class="close-btn">&times;</button>
      </div>

      <form
        id="importDriverAttendanceForm"
        action="{{ route('driver-attendance.import', [], false) }}"
        method="POST"
        enctype="multipart/form-data"
        class="job-form"
        data-confirm-form
        data-confirm-title="Import Driver Attendance?"
        data-confirm-message="Are you sure you want to import these driver attendance records?"
        data-confirm-button="Yes, Import Data"
        data-confirm-type="warning"
      >
        @csrf
        <div class="form-section-title full-width">
          <h3>Upload CSV File</h3>
          <p>Upload driver attendance records using a CSV file.</p>
        </div>
        <div class="form-group full-width">
          <label>CSV File</label>
          <input type="file" name="import_file" accept=".csv,.txt" required>
        </div>
        <div class="form-group full-width">
          <small>
            Required columns: driver_name, shift, attendance_date, time_in, time_out, status
          </small>
        </div>
        <div class="modal-actions full-width">
          <button type="button" id="cancelImportDriverAttendanceModal" class="cancel-btn">Cancel</button>
          <button type="submit" class="save-btn">
            <i class="fa-solid fa-file-import"></i>
            Import Data
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- EDIT DRIVER ATTENDANCE MODAL --}}
  <x-ui.form-modal
    id="driverAttendanceModal"
    title="Driver Attendance Details"
    title-id="driverAttendanceModalTitle"
    description="Review and update driver attendance information."
    icon="fa-id-card"
    size="large"
    form-id="driverAttendanceForm"
    :action="route('driver-attendance.store', [], false)"
    method="POST"
    submit-text="Save Record"
    submit-text-id="driverAttendanceSubmitText"
    submit-icon="fa-floppy-disk"
    cancel-text="Cancel"
    cancel-id="cancelDriverAttendanceModal"
    close-id="closeDriverAttendanceModal"
    :confirm="true"
    confirm-title="Save Driver Attendance?"
    confirm-message="Are you sure you want to save this driver attendance record?"
    confirm-button="Yes, Save Record"
    confirm-type="create"
  >
    <input
      type="hidden"
      name="_method"
      id="driverAttendanceFormMethod"
      value="PUT"
      disabled
    >

    <div class="ui-form-grid driver-attendance-form-grid">
      <x-ui.form-field
        label="Driver ID"
        name="driver_id_display"
        id="driverAttendanceDriverId"
        :value="$nextDriverId"
        icon="fa-hashtag"
        :readonly="true"
      />
      <x-ui.form-field
        label="Driver Name"
        name="driver_name"
        id="driverAttendanceDriverName"
        :value="old('driver_name')"
        placeholder="Example: Rowell Amano"
        icon="fa-user"
        :required="true"
      />
      <x-ui.form-select
        label="Shift"
        name="shift"
        id="driverAttendanceShift"
        :options="[
          'Morning' => 'Morning',
          'Afternoon' => 'Afternoon',
          'Night' => 'Night',
        ]"
        :selected="old('shift', 'Morning')"
        icon="fa-business-time"
        :required="true"
      />
      <x-ui.form-field
        label="Date"
        name="attendance_date"
        id="driverAttendanceDate"
        type="date"
        :value="old('attendance_date', now()->format('Y-m-d'))"
        icon="fa-calendar-day"
        :required="true"
      />
      <x-ui.form-field
        label="Time-in"
        name="time_in"
        id="driverAttendanceTimeIn"
        type="time"
        :value="old('time_in')"
        icon="fa-clock"
      />
      <x-ui.form-field
        label="Time-out"
        name="time_out"
        id="driverAttendanceTimeOut"
        type="time"
        :value="old('time_out')"
        icon="fa-clock-rotate-left"
      />
      <x-ui.form-select
        label="Status"
        name="status"
        id="driverAttendanceStatus"
        :options="[
          'Present' => 'Present',
          'Late' => 'Late',
          'Absent' => 'Absent',
          'On Leave' => 'On Leave',
          'On Duty' => 'On Duty',
        ]"
        :selected="old('status', 'Present')"
        icon="fa-circle-check"
        :required="true"
      />
    </div>

    <div class="attendance-form-note">
      <i class="fa-solid fa-circle-info"></i>
      <div>
        <strong>Automatic assignment display</strong>
        <span>
          Bus and trip assignments are managed in Driver & Bus Assignment
          or Auto Scheduling and are displayed here automatically.
        </span>
      </div>
    </div>
  </x-ui.form-modal>

  <x-ui.form-modal
    id="viewDriverAttendanceModal"
    title="Driver Attendance Details"
    description="Complete driver attendance information."
    icon="fa-id-card"
    size="large"
    form-id="viewDriverAttendanceForm"
    action="#"
    method="POST"
    :show-actions="false"
    close-id="closeViewDriverAttendanceModal"
  >
    <div class="attendance-details-grid" id="viewDriverAttendanceContent"></div>
    <div class="ui-form-actions">
      <button
        type="button"
        id="closeViewDriverAttendanceButton"
        class="ui-form-btn ui-form-btn-primary"
      >
        <i class="fa-solid fa-check"></i>
        <span>Close</span>
      </button>
    </div>
  </x-ui.form-modal>

  <x-ui.action-buttom-modal
    mode="delete"
    id="deleteDriverAttendanceModal"
    delete-title="Delete Driver Attendance Record?"
    delete-message="Are you sure you want to delete"
    name-id="deleteDriverAttendanceName"
    cancel-id="cancelDeleteDriverAttendance"
    confirm-id="confirmDeleteDriverAttendance"
  />

</x-layout.app>
