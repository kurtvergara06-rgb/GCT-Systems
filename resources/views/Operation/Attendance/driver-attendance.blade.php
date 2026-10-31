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
  @php
    $canEditOperation = auth()->user()?->hasSystemPermission('operation', 'edit') ?? false;
  @endphp

  <div class="app">

    <x-layout.sidebar department="Operation" />

    <main class="main driver-attendance-page" data-attendance-business-date="{{ now(config('app.business_timezone', 'Asia/Manila'))->toDateString() }}" data-attendance-auto-today="{{ $activeTab === 'today' ? '1' : '0' }}">
      <x-layout.topbar
        title="Driver Attendance"
        subtitle="DDR-verified driver attendance history"
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
        <x-ui.summary-card label="Present" value="{{ $present }}" small="Confirmed Present for selected date" icon="fa-user-check" color="green" />
        <x-ui.summary-card label="Assigned Present" value="{{ $assignedPresent }}" small="Present drivers assigned to active trips" icon="fa-bus" color="blue" />
        <x-ui.summary-card label="Unassigned Present" value="{{ $unassignedPresent }}" small="Present drivers without active trip assignments" icon="fa-user-clock" color="yellow" />
        <x-ui.summary-card label="Attendance Records" value="{{ $attendanceRecords }}" small="All saved statuses for selected date" icon="fa-clipboard-list" color="purple" />
      </section>

      <section data-ajax-region="records" class="table-card attendance-card">
        <div class="section-header">
          <div>
            <h2>Driver Attendance List</h2>
            <p>Attendance is automatically verified by a valid DDR.</p>
          </div>
          <span class="gct-pill gct-pill--unassigned">Automatically verified from DDR</span>

        </div>

        <div class="attendance-record-controls">
          <nav class="attendance-record-tabs" aria-label="Driver attendance record groups">
            <a class="attendance-record-tab {{ $activeTab === 'today' ? 'is-active' : '' }}"
               href="{{ route('driver-attendance', [], false) }}?{{ http_build_query(array_merge(request()->except(['tab', 'attendance_date', 'page']), ['tab' => 'today'])) }}"
               @if($activeTab === 'today') aria-current="page" @endif>
              <i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Today's Attendance
            </a>
            <a class="attendance-record-tab {{ $activeTab === 'history' ? 'is-active' : '' }}"
               href="{{ route('driver-attendance', [], false) }}?{{ http_build_query(array_merge(request()->except(['tab', 'page']), ['tab' => 'history'])) }}"
               @if($activeTab === 'history') aria-current="page" @endif>
              <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Attendance History
            </a>
          </nav>
        <x-ui.table-toolbar action="{{ route('driver-attendance', [], false) }}" data-server-filter="true" search-label="Search Driver Attendance" search-placeholder="Search driver name, ID, or bus..." :filter-count="1 + (int) (request()->filled('status') && request('status') !== 'All Status')"  :show-filter-count="false" filter-title="Filter Driver Attendance" :sort-options="['driver_name' => 'Driver Name', 'driver_id' => 'Driver ID', 'status' => 'Status', 'time_in' => 'Time In']" default-sort="driver_name">
          <x-slot:hidden><input type="hidden" name="tab" value="{{ $activeTab }}"></x-slot:hidden>
          <x-slot:filters>
            <x-ui.table-toolbar-field label="Status" name="status" default="All Status"><option value="All Status" @selected(request('status', 'All Status') === 'All Status')>All Statuses</option>@foreach(['Present','Late','On Duty','Absent','On Leave'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</x-ui.table-toolbar-field>
            @if($activeTab === 'history')
              <x-ui.table-toolbar-field label="Attendance Date" name="attendance_date" type="date" :value="$summaryDate" />
            @endif
          </x-slot:filters>
        </x-ui.table-toolbar>

        </div>

        <div class="table-wrap attendance-record-table-wrap">
          <table class="attendance-table">
            <colgroup><col style="width: 16%"><col style="width: 20%"><col style="width: 23%"><col style="width: 13%"><col style="width: 13%"><col style="width: 15%"></colgroup>
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
                    ->displayTripAssignments
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
                      @if($canEditOperation)
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


                      @endif
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



    </main>
  </div>
</x-layout.app>
