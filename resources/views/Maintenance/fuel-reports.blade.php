<x-layout.app
  title="FROMS - Fuel Reports"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Maintenance/fuel-reports.css',
    'resources/js/Main-js/sidebar.js',
    'resources/js/Maintenance/fuel-reports.js'
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

    <main class="main fuel-page">

      <x-layout.topbar
        title="Fuel Reports"
        subtitle="Track vehicle fuel efficiency, fuel usage, and inefficient trips"
        notification-count="6"
      />

      {{-- SUMMARY CARDS --}}
      <section class="stats-grid fuel-stats-grid">

        <x-ui.summary-card
          label="Total Fuel Used"
          value="{{ number_format($totalFuelUsed, 0) }} L"
          small="Recorded fuel consumption"
          icon="fa-gas-pump"
          color="blue"
        />

        <x-ui.summary-card
          label="Total Distance"
          value="{{ number_format($totalDistance, 0) }} km"
          small="Distance travelled"
          icon="fa-road"
          color="green"
        />

        <x-ui.summary-card
          label="Fleet Average"
          value="{{ number_format($fleetAverage, 2) }}"
          small="Average km/L"
          icon="fa-chart-line"
          color="yellow"
        />

        <x-ui.summary-card
          label="Inefficient Vehicles"
          value="{{ $inefficientVehicles }}"
          small="Needs checking"
          icon="fa-triangle-exclamation"
          color="red"
        />

      </section>

      {{-- EFFICIENCY BY VEHICLE --}}
      <section class="table-card fuel-card fuel-efficiency-card">

        <div class="section-header">
          <div>
            <h2>Efficiency by Vehicle</h2>
            <p>Summary of fuel efficiency per vehicle based on recorded fuel data</p>
          </div>
        </div>

        <x-ui.table-toolbar
          :action="route('fuel-reports')"
          class="toolbar fuel-toolbar"
          search-placeholder="Search vehicle or driver"
          button-id="openFuelModal"
          button-label="Add Fuel Record"
        >
          <div class="filter-group">
            <label for="fuelDateFilter">Date</label>

            <select
              name="date_filter"
              id="fuelDateFilter"
              onchange="this.form.submit()"
            >
              <option
                value="This Month"
                @selected(request('date_filter', 'This Month') === 'This Month')
              >
                This Month
              </option>

              <option
                value="This Week"
                @selected(request('date_filter') === 'This Week')
              >
                This Week
              </option>

              <option
                value="Today"
                @selected(request('date_filter') === 'Today')
              >
                Today
              </option>
            </select>
          </div>
        </x-ui.table-toolbar>

        <div class="table-wrap">
          <table class="fuel-table">
            <thead>
              <tr>
                <th>Vehicle</th>
                <th>Total KM</th>
                <th>Total L</th>
                <th>Total KM/L</th>
                <th>VS Fleet Avg</th>
                <th>Entries</th>
                <th>Status</th>
              </tr>
            </thead>

            <tbody>
              @forelse($vehicleSummaries as $vehicle)
                @php
                  $statusClass = strtolower($vehicle->status);
                  $vsSign = $vehicle->vs_fleet_avg >= 0 ? '+' : '';
                @endphp

                <tr class="{{ $vehicle->status === 'Inefficient' ? 'danger-row' : '' }}">
                  <td>{{ $vehicle->bus_no }}</td>
                  <td>{{ number_format($vehicle->total_km, 2) }}</td>
                  <td>{{ number_format($vehicle->total_liters, 2) }}</td>
                  <td>{{ number_format($vehicle->km_per_liter, 2) }}</td>
                  <td>{{ $vsSign }}{{ number_format($vehicle->vs_fleet_average, 1) }}%</td>
                  <td>{{ $vehicle->entries }}</td>
                  <td>
                    <span class="badge {{ $statusClass }}">
                      @if($vehicle->status === 'Inefficient')
                        <i class="fa-solid fa-triangle-exclamation"></i>
                      @endif
                      {{ $vehicle->status }}
                    </span>
                  </td>
                </tr>
              @empty
                <tr>
                  <x-ui.empty-row
                    colspan="7"
                    message="No fuel records found."
                  />
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

      </section>

      {{-- RECENT FUEL RECORDS --}}
      <section class="table-card fuel-card recent-fuel-card">

        <div class="section-header">
          <div>
            <h2>Recent Fuel Records</h2>
            <p>Latest fuel entries recorded for each vehicle</p>
          </div>
        </div>

        <div class="table-wrap">
          <table class="fuel-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Vehicle</th>
                <th>Distance</th>
                <th>Fuel</th>
                <th>KM/L</th>
                <th>Driver</th>
              </tr>
            </thead>

            <tbody>
              @forelse($recentFuelRecords as $record)
                <tr>
                  <td>{{ $record->report_date?->format('M d, Y') }}</td>
                  <td>{{ $record->bus_no }}</td>
                  <td>{{ number_format((float) $record->distance_km, 2) }} km</td>
                  <td>{{ number_format((float) $record->fuel_liters, 2) }} L</td>
                  <td>{{ number_format((float) $record->km_per_liter, 2) }}</td>
                  <td>{{ $record->driver_name ?: '—' }}</td>
                </tr>
              @empty
                <tr>
                  <x-ui.empty-row
                    colspan="6"
                    message="No recent fuel records found."
                  />
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

      </section>

    </main>

  </div>

  {{-- ADD FUEL RECORD MODAL --}}
  <div class="fuel-modal-overlay" id="fuelModal">
    <div class="fuel-modal">
      <div class="fuel-modal-header">
        <div>
          <h2>Add Fuel Record</h2>
          <p>Fuel liters are encoded manually. Distance can be auto-filled from GPS Mileage Report.</p>
        </div>

        <button type="button" class="fuel-modal-close" id="closeFuelModal">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <form action="{{ route('fuel-reports.store') }}" method="POST">
        @csrf

        <div class="fuel-form-grid">
          <div class="form-group">
            <label>Date</label>
            <input type="date" name="report_date" value="{{ old('report_date', now()->toDateString()) }}" required>
          </div>

          <div class="form-group">
            <label>Vehicle</label>
            <select name="bus_no" required>
              <option value="">Select bus</option>
              @foreach($buses as $bus)
                <option value="{{ $bus->bus_no }}" @selected(old('bus_no') === $bus->bus_no)>
                  {{ $bus->bus_no }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="form-group">
            <label>Driver Name</label>
            <input type="text" name="driver_name" value="{{ old('driver_name') }}" placeholder="Optional">
          </div>

          <div class="form-group">
            <label>Fuel Liters</label>
            <input type="number" step="0.01" min="0.01" name="fuel_liters" value="{{ old('fuel_liters') }}" required>
          </div>

          <div class="form-group full-width">
            <label>Distance KM</label>
            <input type="number" step="0.01" min="0" name="distance_km" value="{{ old('distance_km') }}" placeholder="Leave blank to use GPS Mileage Report for selected date">
          </div>

          <div class="form-group full-width">
            <label>Remarks</label>
            <textarea name="remarks" rows="3" placeholder="Optional remarks">{{ old('remarks') }}</textarea>
          </div>
        </div>

        <div class="fuel-modal-actions">
          <button type="button" class="secondary-btn fuel-cancel-btn" id="cancelFuelModal">Cancel</button>
          <button type="submit" class="primary-btn">
            <i class="fa-solid fa-floppy-disk"></i>
            Save Fuel Record
          </button>
        </div>
      </form>
    </div>
  </div>

</x-layout.app>