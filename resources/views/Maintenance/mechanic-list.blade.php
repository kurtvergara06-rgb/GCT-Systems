<x-layout.app
  title="FROMS - Mechanic Availability"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Maintenance/mechanic-list.css',
    'resources/css/Maintenance/mechanic-availability-enhancement.css',
    'resources/js/Main-js/sidebar.js',
  ]"
>

  <div class="app">

    <x-layout.sidebar department="Maintenance" />

    <main class="main mechanic-page">

      <x-layout.topbar
        title="Mechanic Availability"
        subtitle="Monitor attendance, availability, and active Job Order assignments"
        notification-count="0"
      />

      <section data-ajax-region="summary" class="stats-grid mechanic-stats-grid">
        <x-ui.summary-card
          label="Total Mechanics"
          value="{{ $totalMechanics ?? 0 }}"
          small="Attendance records"
          icon="fa-users-gear"
          color="blue"
        />

        <x-ui.summary-card
          label="Available"
          value="{{ $availableMechanics ?? 0 }}"
          small="Present or late without active JO"
          icon="fa-user-check"
          color="green"
        />

        <x-ui.summary-card
          label="Not Available"
          value="{{ $notAvailableMechanics ?? 0 }}"
          small="Assigned, absent, or on leave"
          icon="fa-user-clock"
          color="red"
        />

        <x-ui.summary-card
          label="On Duty"
          value="{{ $onDutyMechanics ?? 0 }}"
          small="Mechanics with an active Job Order"
          icon="fa-screwdriver-wrench"
          color="blue"
        />
      </section>

      <section data-ajax-region="records" class="table-card mechanic-list-card mechanic-table-card">

        <div class="availability-header">
          <div class="availability-heading">
            <span class="availability-heading-icon">
              <i class="fa-solid fa-users-gear"></i>
            </span>

            <div>
              <h2>Mechanic Availability Board</h2>
              <p>Live attendance from Operation combined with active Maintenance Job Orders.</p>
            </div>
          </div>

          <span class="availability-live-badge">Live assignment status</span>
        </div>

        <x-ui.table-toolbar action="{{ route('mechanic-list') }}" search-label="Search Mechanic Availability" search-placeholder="Search mechanic, JO, bus, maintenance type, or issue..." :filter-count="(int) (request()->filled('date_filter') && request('date_filter') !== 'All Dates') + (int) (request()->filled('availability') && request('availability') !== 'All Types')" filter-title="Filter Mechanic Availability" :sort-options="['mechanic_name' => 'Mechanic Name', 'mechanic_id' => 'Mechanic ID', 'shift' => 'Shift', 'employment_status' => 'Employment Status']" default-sort="mechanic_name">
          <x-slot:filters>
            <x-ui.table-toolbar-field label="Date Range" name="date_filter" default="All Dates">@foreach(['All Dates','Today','This Week'] as $dateFilter)<option value="{{ $dateFilter }}" @selected(request('date_filter', 'All Dates') === $dateFilter)>{{ $dateFilter }}</option>@endforeach</x-ui.table-toolbar-field>
            <x-ui.table-toolbar-field label="Availability" name="availability" default="All Types"><option value="All Types">All Availability</option><option value="Available" @selected(request('availability') === 'Available')>Available</option><option value="Not Available" @selected(request('availability') === 'Not Available')>Not Available</option></x-ui.table-toolbar-field>
          </x-slot:filters>
        </x-ui.table-toolbar>

        <div class="table-wrap">
          <table class="mechanic-attendance-style-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Mechanic</th>
                <th>Assigned Job</th>
                <th>Date</th>
                <th>Time-In</th>
                <th>Time-Out</th>
                <th>Status</th>
              </tr>
            </thead>

            <tbody>
              @forelse($mechanics as $mechanic)
                @php
                  $activeJob = $mechanic->active_job;
                  $effectiveStatus = $mechanic->effective_status ?? $mechanic->status;
                  $statusClass = strtolower(str_replace([' ', '/'], ['-', '-'], $effectiveStatus));
                  $initials = collect(explode(' ', $mechanic->mechanic_name))
                    ->filter()
                    ->take(2)
                    ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
                    ->implode('');
                @endphp

                <tr class="{{ $activeJob ? 'row-on-duty' : '' }}">
                  <td>
                    <span class="mechanic-id-pill">{{ $mechanic->mechanic_id }}</span>
                  </td>

                  <td>
                    <div class="mechanic-profile-cell">
                      <span class="mechanic-avatar">{{ $initials }}</span>

                      <div class="mechanic-profile-meta">
                        <strong>{{ $mechanic->mechanic_name }}</strong>
                        <small>{{ $mechanic->shift ?: 'Mechanic' }}</small>
                      </div>
                    </div>
                  </td>

                  <td>
                    @if($activeJob)
                      <div class="assigned-job-card">
                        <div class="assigned-job-top">
                          <span class="assigned-job-number">
                            <i class="fa-solid fa-clipboard-list"></i>
                            {{ $activeJob->job_order_no }}
                          </span>

                          <x-ui.status-badge :status="$activeJob->status" class="assigned-job-state" />
                        </div>

                        <div class="assigned-job-main">
                          <span>{{ $activeJob->maintenance_type }}</span>
                          <span class="dot">•</span>
                          <span class="bus-badge-pill"><i class="fa-solid fa-bus"></i> {{ $activeJob->bus_no }}</span>
                        </div>

                        <span class="assigned-job-issue" title="{{ $activeJob->problem_issue }}">
                          {{ $activeJob->problem_issue ?: 'No issue description provided.' }}
                        </span>
                      </div>
                    @elseif($mechanic->assigned_job)
                      <div class="assigned-job-card">
                        <div class="assigned-job-main">
                          <i class="fa-solid fa-wrench"></i>
                          <span>{{ $mechanic->assigned_job }}</span>
                        </div>
                        <span class="assigned-job-issue">Recorded from attendance</span>
                      </div>
                    @else
                      <span class="no-assignment">
                        <i class="fa-solid fa-circle-check"></i>
                        Available for assignment
                      </span>
                    @endif
                  </td>

                  <td class="{{ $mechanic->attendance_date ? 'mechanic-date-cell' : 'empty' }}">
                    @if($mechanic->attendance_date)
                      <span class="mechanic-date-value">
                        {{ $mechanic->attendance_date->format('M d, Y') }}
                      </span>
                    @else
                      —
                    @endif
                  </td>

                  <td>
                    <div class="time-cell">
                      <strong>{{ $mechanic->time_in ? \Carbon\Carbon::parse($mechanic->time_in)->format('h:i A') : '—' }}</strong>
                      <small>Time in</small>
                    </div>
                  </td>

                  <td>
                    <div class="time-cell">
                      <strong>{{ $mechanic->time_out ? \Carbon\Carbon::parse($mechanic->time_out)->format('h:i A') : '—' }}</strong>
                      <small>Time out</small>
                    </div>
                  </td>

                  <td>
                    <x-ui.status-badge :status="$effectiveStatus" class="attendance-badge {{ $statusClass }}" />
                  </td>
                </tr>
              @empty
                <x-ui.empty-row
                  colspan="7"
                  icon="fa-wrench"
                  message="No Mechanic Records Found"
                  description="No mechanics match your search or availability filter."
                />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$mechanics" />
      </section>
    </main>
  </div>
</x-layout.app>
