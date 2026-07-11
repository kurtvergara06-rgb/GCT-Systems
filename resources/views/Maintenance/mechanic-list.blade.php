<x-layout.app
  title="FROMS - Mechanic List"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Maintenance/mechanic-list.css',
    'resources/js/Main-js/sidebar.js',
    'resources/js/Maintenance/mechanic-list.js'
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
        ['label' => 'Settings', 'route' => 'settings', 'icon' => 'fa-gear'],
      ]"
    />

    <main class="main mechanic-page">

      <x-layout.topbar
        title="Mechanic List"
        subtitle="Monitor mechanic attendance and assignment status from Operation"
        notification-count="6"
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
          small="Present or late"
          icon="fa-user-check"
          color="green"
        />

        <x-ui.summary-card
          label="Not Available"
          value="{{ $notAvailableMechanics ?? 0 }}"
          small="On duty, absent, or on leave"
          icon="fa-user-clock"
          color="red"
        />

        <x-ui.summary-card
          label="On Duty"
          value="{{ $onDutyMechanics ?? 0 }}"
          small="Currently assigned mechanics"
          icon="fa-screwdriver-wrench"
          color="blue"
        />

      </section>

      <section class="table-card mechanic-list-card mechanic-table-card">

        <div class="section-header">
          <div>
            <h2>Mechanic List</h2>
            <p>Live attendance information from the Operation Department.</p>
          </div>
        </div>

        <form
          action="{{ route('mechanic-list') }}"
          method="GET"
          class="toolbar mechanic-toolbar"
        >
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>

            <input
              type="text"
              name="search"
              value="{{ request('search') }}"
              placeholder="Search mechanic name, ID, assigned job..."
            >
          </div>

          <div class="filter-group">

            <select
              name="date_filter"
              id="dateFilter"
              onchange="this.form.submit()"
            >
              <option
                value="Month Dates"
                {{ request('date_filter', 'Month Dates') === 'Month Dates' ? 'selected' : '' }}
              >
                Month Dates
              </option>

              <option
                value="Today"
                {{ request('date_filter') === 'Today' ? 'selected' : '' }}
              >
                Today
              </option>

              <option
                value="This Week"
                {{ request('date_filter') === 'This Week' ? 'selected' : '' }}
              >
                This Week
              </option>
            </select>
          </div>

          <div class="filter-group">
            <label for="availabilityFilter"></label>

            <select
              name="availability"
              id="availabilityFilter"
              onchange="this.form.submit()"
            >
              <option
                value="All Types"
                {{ request('availability', 'All Types') === 'All Types' ? 'selected' : '' }}
              >
                All Types
              </option>

              <option
                value="Available"
                {{ request('availability') === 'Available' ? 'selected' : '' }}
              >
                Available
              </option>

              <option
                value="Not Available"
                {{ request('availability') === 'Not Available' ? 'selected' : '' }}
              >
                Not Available
              </option>
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
                  $timeIn = $mechanic->time_in
                    ? \Carbon\Carbon::parse($mechanic->time_in)->format('h:i A')
                    : '--:--';

                  $timeOut = $mechanic->time_out
                    ? \Carbon\Carbon::parse($mechanic->time_out)->format('h:i A')
                    : '--:--';

                  $statusClass = strtolower(
                    str_replace([' ', '/'], ['-', '-'], $mechanic->status)
                  );
                @endphp

                <tr>
                  <td>{{ $mechanic->mechanic_id }}</td>

                  <td>
                    {{ $mechanic->mechanic_name }}
                  </td>

                  <td>
                    {{ $mechanic->assigned_job ?: '--:--' }}
                  </td>

                  <td class="{{ $mechanic->attendance_date ? 'mechanic-date-cell' : 'empty' }}">
                    @if($mechanic->attendance_date)
                      <span class="mechanic-date-value">
                        {{ $mechanic->attendance_date->format('M d, Y') }}
                      </span>
                    @else
                      --:--
                    @endif
                  </td>

                  <td>{{ $timeIn }}</td>

                  <td>{{ $timeOut }}</td>

                  <td>
                    <span class="attendance-badge {{ $statusClass }}">
                      {{ $mechanic->status }}
                    </span>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="empty-mechanics">
                    No mechanic attendance records found.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$mechanics" />

      </section>

    </main>

  </div>

</x-layout.app>