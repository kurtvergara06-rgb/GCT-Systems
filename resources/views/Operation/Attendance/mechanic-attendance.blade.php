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
  @php
    $isMaintenanceUser = strtolower(trim((string) auth()->user()?->department)) === 'maintenance';
    $attendanceDepartment = strtolower(trim((string) auth()->user()?->department));
    $attendancePermissionModule = in_array($attendanceDepartment, ['operation', 'operations'], true) ? 'operation' : 'maintenance';
    $canEditMechanicAttendance = in_array($attendanceDepartment, ['maintenance', 'admin'], true)
        && (auth()->user()?->hasSystemPermission('maintenance', 'edit') ?? false);
    $voidDepartment = strtolower(trim((string) auth()->user()?->department));
    $canVoidMechanicAttendance = in_array($voidDepartment, ['maintenance', 'admin'], true)
        && strtolower(trim((string) auth()->user()?->role)) === 'head'
        && (auth()->user()?->hasSystemPermission('maintenance', 'approve') ?? false);
  @endphp

  <style>
    /* Mechanic Attendance table: compact rectangular status boxes. */
    .mechanic-attendance-page .mechanic-attendance-status-box {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      min-width: 96px;
      min-height: 32px;
      box-sizing: border-box;
      padding: 5px 10px;
      border: 1px solid transparent;
      border-radius: 7px;
      white-space: nowrap;
      font-size: 11px;
      font-weight: 800;
      line-height: 1.2;
    }
    .mechanic-attendance-page .mechanic-attendance-status-box i { font-size: 11px; }
    .mechanic-attendance-page .mechanic-attendance-status-box.present { background: #dcfce7; border-color: #86efac; color: #166534; }
    .mechanic-attendance-page .mechanic-attendance-status-box.late { background: #fef3c7; border-color: #fcd34d; color: #92400e; }
    .mechanic-attendance-page .mechanic-attendance-status-box.absent { background: #fee2e2; border-color: #fca5a5; color: #991b1b; }
    .mechanic-attendance-page .mechanic-attendance-status-box.leave { background: #ede9fe; border-color: #c4b5fd; color: #6d28d9; }
    .mechanic-attendance-page .mechanic-attendance-status-box.duty { background: #dbeafe; border-color: #93c5fd; color: #1e40af; }
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
    #editMechanicAttendanceModal .mechanic-attendance-field-hint {
      display: block;
      margin-top: 5px;
      font-size: 11px;
      line-height: 1.4;
      color: #64748b;
    }
    /* Shared attendance-details presentation with driver records. */
    #viewMechanicAttendanceModal .modal-box { width: min(650px, calc(100vw - 32px)); max-width: calc(100vw - 32px); padding: 0; overflow: hidden; }
    #viewMechanicAttendanceModal .modal-header { display: flex; align-items: center; gap: 12px; padding: 22px 24px; border-bottom: 1px solid #e8eef5; }
    #viewMechanicAttendanceModal .modal-header h2 { margin: 0; font-size: 19px; }
    #viewMechanicAttendanceModal .attendance-view-icon { width: 46px; height: 46px; flex-shrink: 0; display: grid; place-items: center; border-radius: 12px; background: #fff1c7; color: #bc8400; }
    #viewMechanicAttendanceModal .attendance-view-intro { margin: 5px 0 0; font-size: 12px; color: #64748b; }
    #viewMechanicAttendanceModal .attendance-details-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; padding: 20px 24px; max-height: 55vh; overflow-y: auto; }
    #viewMechanicAttendanceModal .mechanic-attendance-detail-card { min-width: 0; padding: 13px 14px; border: 1px solid #dbe5f1; background: #f8fafc; border-radius: 10px; }
    #viewMechanicAttendanceModal .mechanic-attendance-detail-card strong { display: block; margin-bottom: 7px; color: #64748b; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; }
    #viewMechanicAttendanceModal .mechanic-attendance-detail-card span { display: block; color: #10213e; font-size: 13px; font-weight: 700; overflow-wrap: anywhere; }
    #viewMechanicAttendanceModal .mechanic-attendance-detail-card .attendance-status-pill { display: inline-flex; width: auto; padding: 6px 12px; border-radius: 999px; background: #dff8e7; color: #166534; }
    #viewMechanicAttendanceModal .mechanic-attendance-detail-card .attendance-status-pill[data-status="absent"] { background: #fee2e2; color: #991b1b; }
    #viewMechanicAttendanceModal .mechanic-attendance-detail-card .attendance-status-pill[data-status="late"] { background: #fef3c7; color: #92400e; }
    #viewMechanicAttendanceModal .mechanic-attendance-detail-card .attendance-status-pill[data-status="on leave"] { background: #ede9fe; color: #6d28d9; }
    #viewMechanicAttendanceModal .mechanic-attendance-detail-card .attendance-status-pill[data-status="on duty"] { background: #dbeafe; color: #1e40af; }
    #viewMechanicAttendanceModal .modal-actions { margin: 0 24px; padding: 16px 0 22px; border-top: 1px solid #e8eef5; justify-content: flex-end; }
    #viewMechanicAttendanceModal .modal-actions .cancel-btn { background: #ffb900; color: #111827; border-color: #ffb900; font-weight: 800; }
    @media (max-width: 580px) { #viewMechanicAttendanceModal .attendance-details-grid { grid-template-columns: 1fr; } #viewMechanicAttendanceModal .modal-header { padding: 18px; } }
  

.mechanic-attendance-voided-badge { display:inline-flex; padding:6px 11px; border-radius:7px; background:#fee2e2; color:#991b1b; font-size:11px; font-weight:800; }
.mechanic-void-modal { width:min(510px,calc(100vw - 32px)); }
.mechanic-void-intro { margin:16px 22px; line-height:1.6; }
.mechanic-void-modal > label { display:block; margin:0 22px 8px; font-size:12px; font-weight:800; }
.mechanic-void-modal > textarea { display:block; width:calc(100% - 44px); margin:0 22px; box-sizing:border-box; min-height:100px; padding:11px; border:1px solid #9eb6d3; border-radius:8px; color:#142b48; background:#fff; }
.mechanic-void-hint { margin:10px 22px; font-size:11px; }
.mechanic-void-modal .modal-actions { padding:14px 22px; }
html[data-gct-theme="dark"] .mechanic-void-modal { background:var(--gct-theme-surface,#162235); color:var(--gct-theme-fg,#edf3fc); border-color:var(--gct-theme-border,#35465e); }
html[data-gct-theme="dark"] .mechanic-void-modal :is(h2, label, .mechanic-void-intro) { color:var(--gct-theme-fg,#edf3fc); }
html[data-gct-theme="dark"] .mechanic-void-modal textarea { background:var(--gct-theme-input,#101b2b); color:var(--gct-theme-fg,#edf3fc); border-color:var(--gct-theme-control-border,#465a76); }
html[data-gct-theme="dark"] .mechanic-void-hint { color:var(--gct-theme-muted,#b1bfd2); }
html[data-gct-theme="dark"] .mechanic-void-modal .cancel-btn { background:#fff; color:#1d4ed8; }

</style>

  <div class="app">

  <x-layout.sidebar :department="$isMaintenanceUser ? 'Maintenance' : 'Operation'" />

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
            <h2>{{ $showVoided ? 'Voided Attendance History' : 'Mechanic Attendance List' }}</h2>
            <p>{{ $showVoided ? 'Original attendance records retained for audit, with void reasons.' : 'Track time-in, time-out, assigned job, and attendance status' }}</p>
          </div>
          @if($canEditMechanicAttendance && ! $showVoided)
          <button
            type="button"
            class="primary-btn batch-attendance-open"
            data-batch-attendance-open
          >
            <i class="fa-solid fa-clipboard-check"></i>
            Record Daily Attendance
          </button>
          @endif

        </div>

        <div class="mechanic-attendance-controls gct-table-toolbar-row">
          <nav class="mechanic-attendance-record-tabs" aria-label="Attendance records">
            <a href="{{ route('mechanic-attendance', [], false) }}?{{ http_build_query(array_merge(request()->except(['records', 'page']), ['attendance_date' => $summaryDate])) }}" class="mechanic-attendance-record-tab {{ $showVoided ? '' : 'is-active' }}" @if(! $showVoided) aria-current="page" @endif><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Active Attendance</a>
            <a href="{{ route('mechanic-attendance', [], false) }}?{{ http_build_query(array_merge(request()->except(['records', 'page']), ['records' => 'voided', 'attendance_date' => $summaryDate])) }}" class="mechanic-attendance-record-tab {{ $showVoided ? 'is-active' : '' }}" @if($showVoided) aria-current="page" @endif><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Voided History</a>
          </nav>
          <x-ui.table-toolbar action="{{ route('mechanic-attendance', [], false) }}" data-server-filter="true" search-label="Search Mechanic Attendance" search-placeholder="Search mechanic name, ID, or assigned job..." :filter-count="1 + (int) (request()->filled('status') && request('status') !== 'All Status')" :show-filter-count="false" filter-title="Filter Mechanic Attendance" :sort-options="['mechanic_name' => 'Mechanic Name', 'mechanic_id' => 'Mechanic ID', 'status' => 'Status', 'time_in' => 'Time In']" default-sort="mechanic_name">
          <x-slot:hidden>
            @if($showVoided)<input type="hidden" name="records" value="voided">@endif
          </x-slot:hidden>
          <x-slot:filters>
            <x-ui.table-toolbar-field label="Status" name="status" default="All Status"><option value="All Status" @selected(request('status', 'All Status') === 'All Status')>All Statuses</option>@foreach(['Present','Late','On Duty','Absent','On Leave'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</x-ui.table-toolbar-field>
            <x-ui.table-toolbar-field label="Attendance Date" name="attendance_date" type="date" :value="$summaryDate" />
          </x-slot:filters>
          </x-ui.table-toolbar>
        </div>

        <div class="table-wrap mechanic-attendance-table-wrap gct-master-scroll-10" role="region" tabindex="0" aria-label="Mechanic Attendance records, scrollable">
          <table class="attendance-table">
            <colgroup><col style="width: 14%"><col style="width: 22%"><col style="width: 22%"><col style="width: 11%"><col style="width: 11%"><col style="width: 10%"><col style="width: 10%"></colgroup>
            <thead>
              <tr>
                <th>ID</th>
                <th>Mechanic</th>
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
                  <td><span class="gct-pill {{ $attendance->display_assigned_job === 'Unassigned' ? 'gct-pill--unassigned' : 'gct-pill--scheduled' }}">{{ $attendance->display_assigned_job }}</span></td>
                  <td>{{ $attendance->time_in ? date('h:i A', strtotime($attendance->time_in)) : '--:--' }}</td>
                  <td>{{ $attendance->time_out ? date('h:i A', strtotime($attendance->time_out)) : '--:--' }}</td>
                  <td>
                    @if($showVoided)
                      <span class="mechanic-attendance-voided-badge">Voided</span>
                    @else
                      <span class="mechanic-attendance-status-box {{ $statusClass }}">
                        <i class="fa-solid {{ match($attendance->status) {
                          'Present' => 'fa-circle-check',
                          'Late' => 'fa-clock',
                          'Absent' => 'fa-circle-xmark',
                          'On Leave' => 'fa-calendar-minus',
                          'On Duty' => 'fa-screwdriver-wrench',
                          default => 'fa-circle-info',
                        } }}" aria-hidden="true"></i>
                        {{ $attendance->status }}
                      </span>
                    @endif
                  </td>
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
                        data-status="{{ $attendance->status }}"
                        data-voided="{{ $showVoided ? '1' : '0' }}"
                        data-void-reason="{{ $attendance->void_reason }}"
                        data-voided-by="{{ $attendance->voidedBy?->name ?? 'Not recorded' }}"
                        data-voided-at="{{ $attendance->deleted_at?->timezone(config('app.business_timezone', 'Asia/Manila'))->format('M d, Y h:i A') }}">
                        <i class="fa-solid fa-eye"></i>
                      </button>
                      @if($canEditMechanicAttendance && ! $showVoided)
                      <button type="button" class="action-btn edit open-edit-attendance-modal"
                        @if($attendance->has_active_job) disabled aria-disabled="true" title="Locked: active Maintenance Job Order" @else title="Edit" @endif
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
                      >
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                      </button>
                      @endif
                      @if($canVoidMechanicAttendance && ! $showVoided)
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
                          @if($attendance->has_active_job) disabled aria-disabled="true" title="Locked: active Maintenance Job Order" @else title="Void Attendance" aria-label="Void attendance" @endif
                          data-id="{{ $attendance->id }}"
                          data-mechanic-id="{{ $attendance->mechanic_id }}"
                          data-mechanic-name="{{ $attendance->mechanic_name }}"
                        >
                          <i class="fa-solid fa-ban"></i>
                        </button>
                      </form>
                      @endif
                    </div>
                  </td>
                </tr>
              @empty
                <x-ui.empty-row
                  colspan="8"
                  icon="fa-user-gear"
                  message="{{ $showVoided ? 'No Voided Attendance Records Found' : 'No Mechanic Attendance Records Found' }}"
                  description="{{ $showVoided ? 'No voided records match your selected filters.' : 'No mechanic attendance records match your selected filters.' }}"
                />
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
        <span class="attendance-view-icon" aria-hidden="true"><i class="fa-solid fa-id-card"></i></span>
        <div class="attendance-view-heading">
          <h2 id="viewMechanicAttendanceTitle">Mechanic Attendance Details</h2>
          <p class="attendance-view-intro">Complete mechanic attendance information.</p>
        </div>
        <button type="button" id="closeViewMechanicAttendanceModal" class="close-btn" aria-label="Close" style="margin-left:auto">&times;</button>
      </div>
      <div class="attendance-details-grid" id="viewMechanicAttendanceContent"></div>
      <div class="modal-actions">
        <button type="button" id="dismissViewMechanicAttendanceModal" class="cancel-btn">Close</button>
      </div>
    </div>
  </div>

  @if($canEditMechanicAttendance)
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
          <input type="text" id="edit_mechanic_name" readonly aria-readonly="true" title="Mechanic identity is managed in the Master List">
        </div>
        <div class="form-group">
          <input type="hidden" name="shift" id="edit_shift" value="Morning">
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

  @endif

  @if($canVoidMechanicAttendance)
  <div id="deleteAttendanceModal" class="modal-overlay">
    <div class="modal-box mechanic-void-modal" role="dialog" aria-modal="true" aria-labelledby="voidAttendanceTitle">
      <div class="modal-header">
        <h2 id="voidAttendanceTitle"><i class="fa-solid fa-ban" aria-hidden="true"></i> Void Attendance?</h2>
      </div>
      <p class="mechanic-void-intro">Void the attendance record for <strong id="deleteAttendanceName"></strong>. The original record remains in Voided History and cannot be edited. This does not delete the original data.</p>
      <label for="voidAttendanceReason">Reason for voiding <span aria-hidden="true">*</span></label>
      <textarea id="voidAttendanceReason" required minlength="10" maxlength="1000" rows="3" placeholder="Enter a specific reason (at least 10 characters)..."></textarea>
      <p class="mechanic-void-hint">Only authorized approvers can void attendance. Active Job Order assignments remain protected.</p>
      <div class="modal-actions">
        <button type="button" class="cancel-btn" id="cancelDeleteAttendance">Cancel</button>
        <button type="button" class="save-btn" id="confirmDeleteAttendance">Yes, Void Attendance</button>
      </div>
    </div>
  </div>
  @endif

</x-layout.app>
