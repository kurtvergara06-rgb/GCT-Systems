<x-layout.app
  title="FROMS - Fuel Reports"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Maintenance/fuel-reports.css',
    'resources/js/Main-js/sidebar.js'
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

    <main class="main">

      <x-layout.topbar
        title="Fuel Reports"
        subtitle="Track vehicle fuel efficiency, fuel usage, and inefficient trips"
        notification-count="6"
      />

      {{-- SUMMARY CARDS --}}
      <section class="stats-grid">

        <div class="stat-card">
          <div class="stat-icon blue">
            <i class="fa-solid fa-gas-pump"></i>
          </div>

          <div>
            <p>Total Fuel Used</p>
            <h2>{{ number_format($totalFuelUsed, 0) }} L</h2>
            <small>Recorded fuel consumption</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon green">
            <i class="fa-solid fa-road"></i>
          </div>

          <div>
            <p>Total Distance</p>
            <h2>{{ number_format($totalDistance, 0) }} km</h2>
            <small>Distance travelled</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon yellow">
            <i class="fa-solid fa-chart-line"></i>
          </div>

          <div>
            <p>Fleet Average</p>
            <h2>{{ number_format($fleetAverage, 2) }}</h2>
            <small>Average km/L</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

        <div class="stat-card">
          <div class="stat-icon red">
            <i class="fa-solid fa-triangle-exclamation"></i>
          </div>

          <div>
            <p>Inefficient Vehicles</p>
            <h2>{{ $inefficientVehicles }}</h2>
            <small>Needs checking</small>
          </div>

          <i class="fa-solid fa-chevron-right arrow"></i>
        </div>

      </section>

      {{-- EFFICIENCY BY VEHICLE --}}
      <section class="table-card fuel-card">

        <div class="section-header">
          <div>
            <h2>Efficiency by Vehicle</h2>
            <p>Summary of fuel efficiency per vehicle based on recorded fuel data</p>
          </div>
        </div>

        <form method="GET" action="{{ route('fuel-reports') }}">
          <div class="toolbar fuel-toolbar">
            <div class="search-box">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search vehicle or driver"
              >
            </div>

            <div class="filter-group">
              <label>Date</label>
              <select name="date_filter" onchange="this.form.submit()">
                <option value="This Month" @selected(request('date_filter', 'This Month') === 'This Month')>This Month</option>
                <option value="This Week" @selected(request('date_filter') === 'This Week')>This Week</option>
                <option value="Today" @selected(request('date_filter') === 'Today')>Today</option>
              </select>
            </div>

            <button class="primary-btn" type="button" id="openFuelModal">
              <i class="fa-solid fa-plus"></i>
              Add Fuel Record
            </button>
          </div>
        </form>

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
      <section class="table-card fuel-card">

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

  <style>
    .fuel-modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.55);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      padding: 24px;
    }

    .fuel-modal-overlay.show {
      display: flex;
    }

    .fuel-modal {
      width: min(760px, 100%);
      background: #ffffff;
      border-radius: 18px;
      padding: 24px;
      box-shadow: 0 24px 80px rgba(15, 23, 42, 0.25);
    }

    .fuel-modal-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 18px;
    }

    .fuel-modal-header h2 {
      margin: 0;
      color: #0f172a;
      font-size: 20px;
      font-weight: 900;
    }

    .fuel-modal-header p {
      margin: 5px 0 0;
      color: #64748b;
      font-size: 13px;
    }

    .fuel-modal-close {
      width: 36px;
      height: 36px;
      border: none;
      border-radius: 10px;
      background: #f1f5f9;
      color: #0f172a;
      cursor: pointer;
    }

    .fuel-form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 16px;
    }

    .fuel-form-grid .full-width {
      grid-column: 1 / -1;
    }

    .fuel-form-grid label {
      display: block;
      margin-bottom: 7px;
      font-size: 13px;
      font-weight: 800;
      color: #0f172a;
    }

    .fuel-form-grid input,
    .fuel-form-grid select,
    .fuel-form-grid textarea {
      width: 100%;
      border: 1px solid #dbe4f0;
      border-radius: 12px;
      padding: 12px 14px;
      font: inherit;
      color: #0f172a;
      outline: none;
    }

    .fuel-form-grid textarea {
      resize: vertical;
    }

    .fuel-modal-actions {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 20px;
    }

    .fuel-cancel-btn {
      border: none;
      border-radius: 12px;
      padding: 0 18px;
      min-height: 44px;
      background: #e2e8f0;
      color: #0f172a;
      font-weight: 800;
      cursor: pointer;
    }

    @media (max-width: 700px) {
      .fuel-form-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const modal = document.getElementById('fuelModal');
      const openBtn = document.getElementById('openFuelModal');
      const closeBtn = document.getElementById('closeFuelModal');
      const cancelBtn = document.getElementById('cancelFuelModal');

      function openModal() {
        if (modal) modal.classList.add('show');
      }

      function closeModal() {
        if (modal) modal.classList.remove('show');
      }

      if (openBtn) openBtn.addEventListener('click', openModal);
      if (closeBtn) closeBtn.addEventListener('click', closeModal);
      if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

      if (modal) {
        modal.addEventListener('click', function (event) {
          if (event.target === modal) closeModal();
        });
      }

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeModal();
      });
    });
  </script>

</x-layout.app>