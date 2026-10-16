<x-layout.app
  title="FROMS - Driver Attendance"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Main-styles/form-components.css',
    'resources/css/Operation/Attendance/driver-attendance.css',
    'resources/css/Operation/Attendance/batch-attendance.css',
    'resources/js/Main-js/sidebar.js',
    'resources/js/Operation/Attendance/driver-attendance.js',
    'resources/js/Operation/Attendance/batch-attendance.js'
  ]"
>

  <div class="app">

    <x-layout.sidebar department="Operation" />

    <main class="main driver-attendance-page" data-attendance-business-date="{{ now(config('app.business_timezone', 'Asia/Manila'))->toDateString() }}" data-attendance-auto-today="{{ request()->filled('attendance_date') ? '0' : '1' }}">
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

      <section data-ajax-region="summary" class="stats-grid">
        <x-ui.summary-card label="Present" value="{{ $present }}" small="Drivers present" icon="fa-user-check" color="green" />
        <x-ui.summary-card label="Absent" value="{{ $absent }}" small="Drivers absent" icon="fa-user-xmark" color="red" />
        <x-ui.summary-card label="Late" value="{{ $late }}" small="Drivers who were late" icon="fa-clock" color="yellow" />
        <x-ui.summary-card label="On Duty" value="{{ $onDuty }}" small="Assigned drivers" icon="fa-bus" color="blue" />
      </section>

      <section data-ajax-region="records" class="table-card attendance-card">
        <div class="section-header">
          <div>
            <h2>Driver Attendance List</h2>
            <p>Track daily driver attendance, bus assignment, time-in, and attendance status</p>
          </div>
<button
            type="button"
            class="primary-btn batch-attendance-open"
            data-batch-attendance-open
          >
            <i class="fa-solid fa-clipboard-check"></i>
            Record Daily Attendance
          </button>

        </div>

        <x-ui.table-toolbar action="{{ route('driver-attendance', [], false) }}" data-server-filter="true" search-label="Search Driver Attendance" search-placeholder="Search driver name, ID, or bus..." :filter-count="1 + (int) (request()->filled('status') && request('status') !== 'All Status')"  :show-filter-count="false" filter-title="Filter Driver Attendance" :sort-options="['driver_name' => 'Driver Name', 'driver_id' => 'Driver ID', 'status' => 'Status', 'time_in' => 'Time In']" default-sort="driver_name">
          <x-slot:filters>
            <x-ui.table-toolbar-field label="Status" name="status" default="All Status"><option value="All Status" @selected(request('status', 'All Status') === 'All Status')>All Statuses</option>@foreach(['Present','Late','On Duty','Absent','On Leave'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</x-ui.table-toolbar-field>
            <x-ui.table-toolbar-field label="Attendance Date" name="attendance_date" type="date" :value="$summaryDate" />
          </x-slot:filters>
        </x-ui.table-toolbar>

        <div class="table-wrap" style="margin-top: 16px;">
          <table class="attendance-table">
            <colgroup><col style="width: 14%"><col style="width: 18%"><col style="width: 28%"><col style="width: 12%"><col style="width: 12%"><col style="width: 16%"></colgroup>
            <thead>
              <tr>
                <th>Driver ID</th>
                <th>Driver</th>
                <th>Current Assignment</th>
                <th>Time-in</th>
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
                      return $trip && ! in_array($trip->status, ['Cancelled', 'Completed'], true);
                    })
                    ->sortBy(fn ($assignment) => optional($assignment->tripSchedule)->departure_time)
                    ->values();

                  $primaryAssignment = $activeAssignments->first();
                  $primaryTrip = $primaryAssignment?->tripSchedule;
                  $primaryBus = $primaryAssignment?->bus;

                  $assignmentSummary = $primaryAssignment
                    ? trim(($primaryBus?->bus_no ?? 'Bus unavailable') . ' | ' . ($primaryTrip?->trip_code ?? 'Trip unavailable'))
                    : 'Unassigned';

                  if ($activeAssignments->count() > 1) {
                    $assignmentSummary .= ' | ' . $activeAssignments->count() . ' assigned trips';
                  }
                @endphp

                <tr>
                  <td><x-ui.id-badge :value="$attendance->driver_id" size="small" /></td>
                  <td><div class="attendance-driver-identity"><span class="attendance-driver-avatar"><i class="fa-solid fa-user" aria-hidden="true"></i></span><span class="attendance-driver-details"><strong title="{{ $attendance->driver_name }}">{{ $attendance->driver_name }}</strong><small>Driver profile</small></span></div></td>
                  <td>
                    @if($primaryAssignment)
                      <div class="current-assignment-cell">
                        <span class="current-assignment-bus">{{ $primaryBus?->bus_no ?? 'Bus unavailable' }}</span>
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
                      <span class="gct-pill gct-pill--unassigned">Unassigned</span>
                    @endif
                  </td>
                  <td>{{ $attendance->time_in ? date('h:i A', strtotime($attendance->time_in)) : '--:--' }}</td>
                  <td><x-ui.status-badge :status="$attendance->status" class="gct-pill gct-pill--attendance {{ $statusClass }}" /></td>
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
                        @if($attendance->tripAssignments->isNotEmpty()) disabled aria-disabled="true" title="Locked: linked trip or bus assignment" @else title="Edit" @endif
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
                          @if($attendance->tripAssignments->isNotEmpty()) disabled aria-disabled="true" title="Locked: linked trip assignment" @else title="Delete" @endif
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
                  colspan="6"
                  icon="fa-user-clock"
                  message="No Driver Attendance Records Found"
                  description="No driver attendance records match your selected filters."
                />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$driverAttendances" />
      </section>

        {{-- Modals are part of the navigable page so their handlers reinitialize on return. --}}


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
    <input type="hidden" name="_method" id="driverAttendanceFormMethod" value="PUT" disabled>

    <div class="ui-form-grid driver-attendance-form-grid">
      <x-ui.form-field label="Driver ID" name="driver_id_display" id="driverAttendanceDriverId" :value="$nextDriverId" icon="fa-hashtag" :readonly="true" />
      <x-ui.form-field label="Driver Name" name="driver_name" id="driverAttendanceDriverName" :value="old('driver_name')" placeholder="Example: Rowell Amano" icon="fa-user" :required="true" />
      <input type="hidden" name="shift" value="Morning">
      <x-ui.form-field label="Date" name="attendance_date" id="driverAttendanceDate" type="date" :value="old('attendance_date', now()->format('Y-m-d'))" icon="fa-calendar-day" :required="true" />
      <x-ui.form-field label="Time-in" name="time_in" id="driverAttendanceTimeIn" type="time" :value="old('time_in')" icon="fa-clock" />
      <x-ui.form-select
        label="Status"
        name="status"
        id="driverAttendanceStatus"
        :options="['Present' => 'Present', 'Absent' => 'Absent', 'On Leave' => 'On Leave', 'On Duty' => 'On Duty']"
        :selected="old('status', 'Present')"
        icon="fa-circle-check"
        :required="true"
      />
    </div>

    <div class="attendance-form-note">
      <i class="fa-solid fa-circle-info"></i>
      <div>
        <strong>Automatic assignment display</strong>
        <span>Bus and trip assignments are managed in Driver & Bus Assignment or Auto Scheduling and are displayed here automatically.</span>
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
      <button type="button" id="closeViewDriverAttendanceButton" class="ui-form-btn ui-form-btn-primary">
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

    </main>
  </div>
</x-layout.app>
