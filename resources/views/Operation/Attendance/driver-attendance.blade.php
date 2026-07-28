<x-layout.app
  title="FROMS - Driver Attendance"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Operation/Attendance/driver-attendance.css',
    'resources/js/Main-js/sidebar.js',
    'resources/js/Operation/Attendance/driver-attendance.js'
  ]"
>

  <div class="app">

    {{-- =====================================================
        SIDEBAR
    ====================================================== --}}
   <x-layout.sidebar
    department="Operation"
    subtitle="Operation Module"
    icon="fa-bus"
    :items="[
        [
            'label' => 'Dashboard',
            'route' => 'dashboard-operation',
            'icon' => 'fa-table-cells-large'
        ],

        [
            'label' => 'Routes',
            'route' => 'operation.routes',
            'icon' => 'fa-route'
        ],

        [
            'label' => 'Scheduling',
            'icon' => 'fa-calendar-days',
            'children' => [
                [
                    'label' => 'Trip Schedule',
                    'route' => 'trip-schedule',
                    'icon' => 'fa-calendar-days'
                ],
                [
                    'label' => 'Driver & Bus Assignment',
                    'route' => 'driver-bus-assignment',
                    'icon' => 'fa-user-tie'
                ],
                [
                    'label' => 'Auto Scheduling',
                    'route' => 'auto-scheduling',
                    'icon' => 'fa-wand-magic-sparkles'
                ],
            ]
        ],

        [
            'label' => 'Attendance',
            'icon' => 'fa-calendar-check',
            'children' => [
                [
                    'label' => 'Driver Attendance',
                    'route' => 'driver-attendance',
                    'icon' => 'fa-id-card'
                ],
                [
                    'label' => 'Mechanic Attendance',
                    'route' => 'mechanic-attendance',
                    'icon' => 'fa-users-gear'
                ],
            ]
        ],

        [
            'label' => 'Fleet Management',
            'icon' => 'fa-bus',
            'children' => [
                [
                    'label' => 'Bus Master List',
                    'route' => 'bus-master-list',
                    'icon' => 'fa-bus'
                ],
                [
                    'label' => 'Fuel Efficiency',
                    'route' => 'fuel-efficiency',
                    'icon' => 'fa-gas-pump'
                ],
            ]
        ],
    ]"
/>


    <main class="main">

      {{-- =====================================================
          TOP BAR
      ====================================================== --}}
      <x-layout.topbar
        title="Driver Attendance"
        subtitle="Manage and track driver attendance and availability"
        notification-count="6"
      />


      {{-- =====================================================
          VALIDATION ERRORS
      ====================================================== --}}
      @if($errors->any())

        <div class="alert-error">

          <ul>

            @foreach($errors->all() as $error)

              <li>
                {{ $error }}
              </li>

            @endforeach

          </ul>

        </div>

      @endif


      {{-- =====================================================
          SUMMARY CARDS
      ====================================================== --}}
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


      {{-- =====================================================
          DRIVER ATTENDANCE TABLE
      ====================================================== --}}
      <section class="table-card attendance-card">

        <div class="section-header">

          <div>

            <h2>
              Driver Attendance List
            </h2>

            <p>
              Track driver attendance, bus assignment, time-in,
              time-out, and attendance status
            </p>

          </div>

        </div>


        {{-- =================================================
            SEARCH / FILTER / BUTTONS
        ================================================== --}}
        <form
          action="{{ route('driver-attendance') }}"
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

            <label>
              Status
            </label>

            <select
              name="status"
              onchange="this.form.submit()"
            >

              <option
                value="All Status"
                {{ request('status', 'All Status') === 'All Status' ? 'selected' : '' }}
              >
                All Status
              </option>

              <option
                value="Present"
                {{ request('status') === 'Present' ? 'selected' : '' }}
              >
                Present
              </option>

              <option
                value="Late"
                {{ request('status') === 'Late' ? 'selected' : '' }}
              >
                Late
              </option>

              <option
                value="On Duty"
                {{ request('status') === 'On Duty' ? 'selected' : '' }}
              >
                On Duty
              </option>

              <option
                value="Absent"
                {{ request('status') === 'Absent' ? 'selected' : '' }}
              >
                Absent
              </option>

              <option
                value="On Leave"
                {{ request('status') === 'On Leave' ? 'selected' : '' }}
              >
                On Leave
              </option>

            </select>

          </div>


          {{-- IMPORT CSV --}}
          <button
            type="button"
            id="openImportDriverAttendanceModal"
            class="secondary-btn import-btn"
          >

            <i class="fa-solid fa-file-import"></i>

            Import Data

          </button>


          {{-- ADD NEW --}}
          <button
            type="button"
            id="openDriverAttendanceModal"
            class="primary-btn"
          >

            <i class="fa-solid fa-plus"></i>

            New Record

          </button>

        </form>


        {{-- =================================================
            TABLE
        ================================================== --}}
        <div class="table-wrap">

          <table class="attendance-table">

            <thead>

              <tr>

                <th>ID</th>

                <th>Driver</th>

                <th>Role</th>

                <th>Shift</th>

                <th>Bus / Assignment</th>

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

                @endphp


                <tr>

                  <td>
                    {{ $attendance->driver_id }}
                  </td>


                  <td>
                    {{ $attendance->driver_name }}
                  </td>


                  <td>
                    Driver
                  </td>


                  <td>
                    {{ $attendance->shift }}
                  </td>


                  <td>
                    {{ $attendance->bus_assignment ?: 'Unassigned' }}
                  </td>


                  <td>

                    {{
                      $attendance->attendance_date
                        ? $attendance->attendance_date->format('m/d/y')
                        : '—'
                    }}

                  </td>


                  <td>

                    {{
                      $attendance->time_in
                        ? date(
                            'h:i A',
                            strtotime($attendance->time_in)
                          )
                        : '--:--'
                    }}

                  </td>


                  <td>

                    {{
                      $attendance->time_out
                        ? date(
                            'h:i A',
                            strtotime($attendance->time_out)
                          )
                        : '--:--'
                    }}

                  </td>


                  <td>

                    <span
                      class="badge {{ $statusClass }}"
                    >

                      {{ $attendance->status }}

                    </span>

                  </td>


                  <td>

                    <div class="actions">

                      {{-- EDIT --}}
                      <button
                        type="button"
                        class="action-btn edit open-edit-driver-attendance-modal"
                        title="Edit"
                        data-id="{{ $attendance->id }}"
                        data-driver-id="{{ $attendance->driver_id }}"
                        data-driver-name="{{ $attendance->driver_name }}"
                        data-shift="{{ $attendance->shift }}"
                        data-bus-assignment="{{ $attendance->bus_assignment }}"
                        data-attendance-date="{{ $attendance->attendance_date ? $attendance->attendance_date->format('Y-m-d') : '' }}"
                        data-time-in="{{ $attendance->time_in }}"
                        data-time-out="{{ $attendance->time_out }}"
                        data-status="{{ $attendance->status }}"
                        data-update-url="{{ route('driver-attendance.update', $attendance->id) }}"
                      >

                        <i class="fa-solid fa-pen"></i>

                      </button>


                      {{-- DELETE FORM --}}
                      <form
                        id="deleteDriverAttendanceForm-{{ $attendance->id }}"
                        action="{{ route('driver-attendance.destroy', $attendance->id) }}"
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

                <x-ui.empty-row
                  colspan="10"
                  message="No driver attendance records found."
                />

              @endforelse

            </tbody>

          </table>

        </div>


        {{-- =================================================
            PAGINATION
        ================================================== --}}
        <x-ui.table-footer
          :items="$driverAttendances"
        />

      </section>

    </main>

  </div>



  {{-- =========================================================
      IMPORT DRIVER ATTENDANCE MODAL
  ========================================================== --}}
  <div
    id="importDriverAttendanceModal"
    class="modal-overlay"
  >

    <div class="modal-box">

      <div class="modal-header">

        <h2>
          Import Driver Attendance Data
        </h2>


        <button
          type="button"
          id="closeImportDriverAttendanceModal"
          class="close-btn"
        >

          &times;

        </button>

      </div>


      <form
        id="importDriverAttendanceForm"
        action="{{ route('driver-attendance.import') }}"
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

          <h3>
            Upload CSV File
          </h3>

          <p>
            Upload driver attendance records using a CSV file.
          </p>

        </div>


        <div class="form-group full-width">

          <label>
            CSV File
          </label>

          <input
            type="file"
            name="import_file"
            accept=".csv,.txt"
            required
          >

        </div>


        <div class="form-group full-width">

          <small>

            Required columns:
            driver_name,
            shift,
            bus_assignment,
            attendance_date,
            time_in,
            time_out,
            status

          </small>

        </div>


        <div class="modal-actions full-width">

          <button
            type="button"
            id="cancelImportDriverAttendanceModal"
            class="cancel-btn"
          >

            Cancel

          </button>


          <button
            type="submit"
            class="save-btn"
          >

            <i class="fa-solid fa-file-import"></i>

            Import Data

          </button>

        </div>

      </form>

    </div>

  </div>



  {{-- =========================================================
      NEW DRIVER ATTENDANCE MODAL
  ========================================================== --}}
  <div
    id="driverAttendanceModal"
    class="modal-overlay"
  >

    <div class="modal-box wide-modal">

      <div class="modal-header">

        <h2>
          Add New Driver Attendance
        </h2>


        <button
          type="button"
          id="closeDriverAttendanceModal"
          class="close-btn"
        >

          &times;

        </button>

      </div>


      <form
        action="{{ route('driver-attendance.store') }}"
        method="POST"
        class="job-form wide-form"
        data-confirm-form
        data-confirm-title="Save Driver Attendance?"
        data-confirm-message="Are you sure you want to save this driver attendance record?"
        data-confirm-button="Yes, Save Record"
        data-confirm-type="create"
      >

        @csrf


        <div class="form-section-title full-width">

          <h3>
            Attendance Details
          </h3>

          <p>
            Enter driver attendance information.
          </p>

        </div>


        {{-- DRIVER ID --}}
        <div class="form-group">

          <label>
            Driver ID
          </label>

          <input
            type="text"
            value="{{ $nextDriverId }}"
            readonly
          >

        </div>


        {{-- DRIVER NAME --}}
        <div class="form-group">

          <label>
            Driver Name
          </label>

          <input
            type="text"
            name="driver_name"
            value="{{ old('driver_name') }}"
            placeholder="Example: Rowell Amano"
            required
          >

        </div>


        {{-- SHIFT --}}
        <div class="form-group">

          <label>
            Shift
          </label>

          <select
            name="shift"
            required
          >

            <option
              value="Morning"
              {{ old('shift') === 'Morning' ? 'selected' : '' }}
            >
              Morning
            </option>

            <option
              value="Afternoon"
              {{ old('shift') === 'Afternoon' ? 'selected' : '' }}
            >
              Afternoon
            </option>

            <option
              value="Night"
              {{ old('shift') === 'Night' ? 'selected' : '' }}
            >
              Night
            </option>

          </select>

        </div>


        {{-- BUS ASSIGNMENT --}}
        <div class="form-group">

          <label>
            Bus / Assignment
          </label>

          <input
            type="text"
            name="bus_assignment"
            value="{{ old('bus_assignment') }}"
            placeholder="Example: BUS-001"
          >

        </div>


        {{-- ATTENDANCE DATE --}}
        <div class="form-group">

          <label>
            Date
          </label>

          <input
            type="date"
            name="attendance_date"
            value="{{ old('attendance_date', now()->format('Y-m-d')) }}"
            required
          >

        </div>


        {{-- TIME IN --}}
        <div class="form-group">

          <label>
            Time-in
          </label>

          <input
            type="time"
            name="time_in"
            value="{{ old('time_in') }}"
          >

        </div>


        {{-- TIME OUT --}}
        <div class="form-group">

          <label>
            Time-out
          </label>

          <input
            type="time"
            name="time_out"
            value="{{ old('time_out') }}"
          >

        </div>


        {{-- STATUS --}}
        <div class="form-group">

          <label>
            Status
          </label>

          <select
            name="status"
            required
          >

            <option
              value="Present"
              {{ old('status') === 'Present' ? 'selected' : '' }}
            >
              Present
            </option>

            <option
              value="Late"
              {{ old('status') === 'Late' ? 'selected' : '' }}
            >
              Late
            </option>

            <option
              value="Absent"
              {{ old('status') === 'Absent' ? 'selected' : '' }}
            >
              Absent
            </option>

            <option
              value="On Leave"
              {{ old('status') === 'On Leave' ? 'selected' : '' }}
            >
              On Leave
            </option>

            <option
              value="On Duty"
              {{ old('status') === 'On Duty' ? 'selected' : '' }}
            >
              On Duty
            </option>

          </select>

        </div>


        <div class="modal-actions full-width">

          <button
            type="button"
            id="cancelDriverAttendanceModal"
            class="cancel-btn"
          >

            Cancel

          </button>


          <button
            type="submit"
            class="save-btn"
          >

            Save Record

          </button>

        </div>

      </form>

    </div>

  </div>



  {{-- =========================================================
      EDIT DRIVER ATTENDANCE MODAL
  ========================================================== --}}
  <div
    id="editDriverAttendanceModal"
    class="modal-overlay"
  >

    <div class="modal-box wide-modal">

      <div class="modal-header">

        <h2>
          Edit Driver Attendance
        </h2>


        <button
          type="button"
          id="closeEditDriverAttendanceModal"
          class="close-btn"
        >

          &times;

        </button>

      </div>


      <form
        id="editDriverAttendanceForm"
        method="POST"
        class="job-form wide-form"
        data-confirm-form
        data-confirm-title="Update Driver Attendance?"
        data-confirm-message="Are you sure you want to update this driver attendance record?"
        data-confirm-button="Yes, Update Record"
        data-confirm-type="update"
      >

        @csrf

        @method('PUT')


        <div class="form-section-title full-width">

          <h3>
            Attendance Details
          </h3>

          <p>
            Update driver attendance information.
          </p>

        </div>


        {{-- DRIVER ID --}}
        <div class="form-group">

          <label>
            Driver ID
          </label>

          <input
            type="text"
            id="edit_driver_id"
            readonly
          >

        </div>


        {{-- DRIVER NAME --}}
        <div class="form-group">

          <label>
            Driver Name
          </label>

          <input
            type="text"
            name="driver_name"
            id="edit_driver_name"
            required
          >

        </div>


        {{-- SHIFT --}}
        <div class="form-group">

          <label>
            Shift
          </label>

          <select
            name="shift"
            id="edit_shift"
            required
          >

            <option value="Morning">
              Morning
            </option>

            <option value="Afternoon">
              Afternoon
            </option>

            <option value="Night">
              Night
            </option>

          </select>

        </div>


        {{-- BUS ASSIGNMENT --}}
        <div class="form-group">

          <label>
            Bus / Assignment
          </label>

          <input
            type="text"
            name="bus_assignment"
            id="edit_bus_assignment"
          >

        </div>


        {{-- DATE --}}
        <div class="form-group">

          <label>
            Date
          </label>

          <input
            type="date"
            name="attendance_date"
            id="edit_attendance_date"
            required
          >

        </div>


        {{-- TIME-IN --}}
        <div class="form-group">

          <label>
            Time-in
          </label>

          <input
            type="time"
            name="time_in"
            id="edit_time_in"
          >

        </div>


        {{-- TIME-OUT --}}
        <div class="form-group">

          <label>
            Time-out
          </label>

          <input
            type="time"
            name="time_out"
            id="edit_time_out"
          >

        </div>


        {{-- STATUS --}}
        <div class="form-group">

          <label>
            Status
          </label>

          <select
            name="status"
            id="edit_status"
            required
          >

            <option value="Present">
              Present
            </option>

            <option value="Late">
              Late
            </option>

            <option value="Absent">
              Absent
            </option>

            <option value="On Leave">
              On Leave
            </option>

            <option value="On Duty">
              On Duty
            </option>

          </select>

        </div>


        <div class="modal-actions full-width">

          <button
            type="button"
            id="cancelEditDriverAttendanceModal"
            class="cancel-btn"
          >

            Cancel

          </button>


          <button
            type="submit"
            class="save-btn"
          >

            Update Record

          </button>

        </div>

      </form>

    </div>

  </div>



  {{-- =========================================================
      DELETE DRIVER ATTENDANCE MODAL
  ========================================================== --}}
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