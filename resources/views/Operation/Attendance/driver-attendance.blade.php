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

    <main class="main driver-attendance-page" >
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
        <x-ui.summary-card label="Verified Drivers" value="{{ $verifiedDrivers }}" small="Unique drivers with DDR-confirmed attendance" icon="fa-user-check" color="green" />
        <x-ui.summary-card label="Verified Attendance Records" value="{{ $attendanceRecords }}" small="Driver service dates confirmed by DDR" icon="fa-clipboard-list" color="blue" />
        <x-ui.summary-card label="Verified Service Dates" value="{{ $verifiedTripDays }}" small="Distinct service dates with confirmed attendance" icon="fa-calendar-check" color="yellow" />
        <x-ui.summary-card label="Latest Verified Date" value="{{ $lastVerifiedDate ? \Illuminate\Support\Carbon::parse($lastVerifiedDate)->format('M d, Y') : '—' }}" small="Latest service date with a DDR record" icon="fa-clock-rotate-left" color="purple" />
      </section>

      <section data-ajax-region="records" class="table-card attendance-card">
        <div class="section-header">
          <div>
            <h2>DDR-Verified Attendance History</h2>
            <p>Attendance is confirmed by submitted DDR records for each service date.</p>
          </div>
          <span class="gct-pill gct-pill--unassigned">Automatically verified from DDR</span>

        </div>

        <div class="attendance-record-controls">
        <x-ui.table-toolbar action="{{ route('driver-attendance', [], false) }}" data-server-filter="true" search-label="Search Driver Attendance" search-placeholder="Search driver name, ID, or bus..." :filter-count="(int) (request()->filled('status') && request('status') !== 'All Status') + (int) request()->filled('attendance_date')"  :show-filter-count="false" filter-title="Filter Driver Attendance" :sort-options="['driver_name' => 'Driver Name', 'driver_id' => 'Driver ID', 'status' => 'Status', 'time_in' => 'Time In', 'attendance_date' => 'Attendance Date']" default-sort="attendance_date">

          <x-slot:filters>
            <x-ui.table-toolbar-field label="Status" name="status" default="All Status"><option value="All Status" @selected(request('status', 'All Status') === 'All Status')>All Statuses</option>@foreach(['Present','Late','On Duty','Absent','On Leave'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</x-ui.table-toolbar-field>
            <x-ui.table-toolbar-field label="Service Date (optional)" name="attendance_date" type="date" :value="$summaryDate" />
          </x-slot:filters>
        </x-ui.table-toolbar>

        </div>

        <div class="table-wrap attendance-record-table-wrap gct-responsive-records gct-sticky-actions">
          <table class="attendance-table">
            <colgroup><col style="width: 15%"><col style="width: 18%"><col style="width: 32%"><col style="width: 12%"><col style="width: 11%"><col style="width: 12%"></colgroup>
            <thead>
              <tr>
                <th>Driver ID</th>
                <th>Driver</th>
                <th>Trip Assignment</th>
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

                  $historicalAssignments = $attendance
                    ->displayTripAssignments
                    ->filter(function ($assignment) {
                      $trip = $assignment->tripSchedule;
                      return $trip && $trip->status !== 'Cancelled';
                    })
                    ->sortBy(fn ($assignment) => optional($assignment->tripSchedule)->departure_time)
                    ->values();

                  $primaryAssignment = $historicalAssignments->first();
                  $primaryTrip = $primaryAssignment?->tripSchedule;
                  $primaryBus = $primaryAssignment?->bus;

                  $assignmentSummary = $primaryAssignment
                    ? trim(($primaryBus?->bus_no ?? 'Bus unavailable') . ' | ' . ($primaryTrip?->trip_code ?? 'Trip unavailable'))
                    : 'Unassigned';

                  if ($historicalAssignments->count() > 1) {
                    $assignmentSummary .= ' | ' . $historicalAssignments->count() . ' assigned trips';
                  }
                @endphp

                <tr>
                  <td><x-ui.id-badge :value="$attendance->driver_id" size="small" /></td>
                  <td><div class="attendance-driver-identity"><span class="attendance-driver-avatar"><i class="fa-solid fa-user" aria-hidden="true"></i></span><span class="attendance-driver-details"><strong title="{{ $attendance->driver_name }}">{{ $attendance->driver_name }}</strong></span></div></td>
                  <td>
                    @if($primaryAssignment)
                      <div class="current-assignment-cell">
                        <span class="current-assignment-bus">{{ $primaryBus?->bus_no ?? 'Bus unavailable' }}</span>
                        <span class="current-assignment-trip" title="{{ $primaryTrip?->trip_code ?? 'Trip unavailable' }}">{{ $primaryTrip?->trip_code ?? 'Trip unavailable' }}</span>
                        <span class="attendance-assignment-schedule">
                          <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                          {{ $attendance->attendance_date?->format('M d, Y') ?? 'Date unavailable' }}
                          @if($primaryTrip)
                            <span class="attendance-assignment-separator" aria-hidden="true">•</span>
                            <i class="fa-regular fa-clock" aria-hidden="true"></i>
                            {{ $primaryTrip->departureDateTime()->format('g:i A') }} – {{ $primaryTrip->estimatedArrivalDateTime()->format('g:i A') }}
                          @endif
                        </span>
                        @if($historicalAssignments->count() > 1)
                          <small class="current-assignment-more">
                            +{{ $historicalAssignments->count() - 1 }} more assignment(s)
                          </small>
                        @endif
                      </div>
                    @else
                      <div class="attendance-assignment-unassigned"><span class="gct-pill gct-pill--unassigned">Unassigned</span><small><i class="fa-regular fa-calendar" aria-hidden="true"></i> {{ $attendance->attendance_date?->format('M d, Y') ?? 'Date unavailable' }}</small></div>
                    @endif
                  </td>
                  <td>{{ $attendance->time_in ? date('h:i A', strtotime($attendance->time_in)) : '--:--' }}</td>
                  <td><div class="attendance-status-cell"><x-ui.status-badge :status="$attendance->status" class="gct-pill gct-pill--attendance {{ $statusClass }}" /></div></td>
                  <td>
                    <div class="actions">
                      @if($canEditOperation)
                      <button
                        type="button"
                        class="action-btn view attendance-view-action open-view-driver-attendance-modal"
                        title="View attendance details"
                        aria-label="View attendance for {{ $attendance->driver_name }} on {{ $attendance->attendance_date?->format('M d, Y') }}"
                        data-driver-id="{{ $attendance->driver_id }}"
                        data-driver-name="{{ $attendance->driver_name }}"
                        data-shift="{{ $attendance->shift }}"
                        data-bus-assignment="{{ $assignmentSummary }}"
                        data-attendance-date="{{ $attendance->attendance_date ? $attendance->attendance_date->format('M d, Y') : '—' }}"
                        data-time-in="{{ $attendance->time_in ? date('h:i A', strtotime($attendance->time_in)) : '--:--' }}"
                        data-time-out="{{ $attendance->time_out ? date('h:i A', strtotime($attendance->time_out)) : '--:--' }}"
                        data-status="{{ $attendance->status }}"
                      >
                        <i class="fa-solid fa-eye" aria-hidden="true"></i><span>View</span>
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
