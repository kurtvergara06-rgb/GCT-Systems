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

    <x-layout.sidebar
      department="Maintenance"
      subtitle="Department Module"
      icon="fa-truck"
      :items="[
        ['label' => 'Dashboard', 'route' => 'maintenance-dashboard', 'icon' => 'fa-table-cells-large'],
        ['label' => 'Job Orders', 'route' => 'job-orders', 'icon' => 'fa-clipboard-list'],
        ['label' => 'Mechanic List', 'route' => 'mechanic-list', 'icon' => 'fa-bus'],
        ['label' => 'PMS Scheduling', 'route' => 'PMS-Scheduling', 'icon' => 'fa-calendar-check'],
        ['label' => 'Purchase Requests', 'route' => 'purchase-requests', 'icon' => 'fa-file-invoice'],
        ['label' => 'Fuel Reports', 'route' => 'fuel-reports', 'icon' => 'fa-gas-pump'],
      ]"
    />

    <main class="main mechanic-page">

      <x-layout.topbar
        title="Mechanic Availability"
        subtitle="Monitor attendance, availability, and active Job Order assignments"
        notification-count="0"
      />

      <section class="stats-grid mechanic-stats-grid">
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

      <section class="table-card mechanic-list-card mechanic-table-card">

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

        <form
          action="{{ route('mechanic-list') }}"
          method="GET"
          class="toolbar mechanic-toolbar"
        >
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>

            <input
              type="search"
              name="search"
              value="{{ request('search') }}"
              placeholder="Search mechanic, JO number, bus, maintenance type, or issue..."
            >
          </div>

          <div class="filter-group">
            <select
              name="date_filter"
              id="dateFilter"
              onchange="this.form.submit()"
            >
              <option value="All Dates" @selected(request('date_filter', 'All Dates') === 'All Dates')>All Dates</option>
              <option value="Today" @selected(request('date_filter') === 'Today')>Today</option>
              <option value="This Week" @selected(request('date_filter') === 'This Week')>This Week</option>
            </select>
          </div>

          <div class="filter-group">
            <select
              name="availability"
              id="availabilityFilter"
              onchange="this.form.submit()"
            >
              <option value="All Types" @selected(request('availability', 'All Types') === 'All Types')>All Availability</option>
              <option value="Available" @selected(request('availability') === 'Available')>Available</option>
              <option value="Not Available" @selected(request('availability') === 'Not Available')>Not Available</option>
            </select>
          </div>
        </form>

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

                          <span class="assigned-job-state">{{ $activeJob->status }}</span>
                        </div>

                        <div class="assigned-job-main">
                          <span>{{ $activeJob->maintenance_type }}</span>
                          <span class="dot">•</span>
                          <span>Bus {{ $activeJob->bus_no }}</span>
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
                    <span class="attendance-badge {{ $statusClass }}">
                      {{ $effectiveStatus }}
                    </span>
                  </td>
                </tr>
              @empty
                <x-ui.empty-row
                  colspan="7"
                  message="No mechanic availability records found."
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
