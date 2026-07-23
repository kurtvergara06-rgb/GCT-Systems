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

    <main
      class="main fuel-page"
      data-gps-url="{{ route('fuel-reports.gps-distance') }}"
      data-store-url="{{ route('fuel-reports.store') }}"
    >

      <x-layout.topbar
        title="Fuel Reports"
        subtitle="Track GPS-based fuel efficiency and fuel usage for every bus"
      />

      {{-- SUCCESS MESSAGE --}}
      @if(session('success'))
        <div class="fuel-alert success">
          <i class="fa-solid fa-circle-check"></i>
          <span>{{ session('success') }}</span>
        </div>
      @endif

      {{-- ERROR MESSAGE --}}
      @if(session('error'))
        <div class="fuel-alert error">
          <i class="fa-solid fa-triangle-exclamation"></i>
          <span>{{ session('error') }}</span>
        </div>
      @endif

      {{-- VALIDATION ERRORS --}}
      @if($errors->any())
        <div class="fuel-alert error">
          <i class="fa-solid fa-triangle-exclamation"></i>

          <div>
            <strong>Please correct the following:</strong>

            <ul>
              @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

      {{-- SUMMARY CARDS --}}
      <section class="stats-grid fuel-stats-grid">

        <x-ui.summary-card
          label="Total Fuel Used"
          value="{{ number_format($totalFuelUsed, 2) }} L"
          small="Recorded fuel consumption"
          icon="fa-gas-pump"
          color="blue"
        />

        <x-ui.summary-card
          label="Total Distance"
          value="{{ number_format($totalDistance, 2) }} km"
          small="GPS and manual distance"
          icon="fa-road"
          color="green"
        />

        <x-ui.summary-card
          label="Fleet Average"
          value="{{ number_format($fleetAverage, 2) }} km/L"
          small="Overall fuel efficiency"
          icon="fa-chart-line"
          color="yellow"
        />

        <x-ui.summary-card
          label="Inefficient Vehicles"
          value="{{ $inefficientVehicles }}"
          small="Needs inspection"
          icon="fa-triangle-exclamation"
          color="red"
        />

      </section>

      {{-- ANALYTICS CHARTS --}}
      <section class="fuel-analytics-grid">

        <x-ui.chart-card
          title="Fuel Efficiency by Vehicle"
          description="Average kilometers travelled per liter for each vehicle."
          chart-id="fuelEfficiencyChart"
          icon="fa-chart-column"
          icon-color="blue"
        />

        <x-ui.chart-card
          title="Distance and Fuel Usage"
          description="Comparison between total distance and total fuel consumption."
          chart-id="fuelUsageChart"
          icon="fa-gas-pump"
          icon-color="yellow"
        />

      </section>

      {{-- ANALYTICS INSIGHTS --}}
      <section class="fuel-insights-grid">

        <x-ui.analytics-insight
          label="Most Efficient Vehicle"
          :value="$mostEfficientVehicle?->bus_no ?? 'No data'"
          :description="$mostEfficientVehicle
            ? number_format($mostEfficientVehicle->km_per_liter, 2).' km/L'
            : 'No fuel records available.'"
          icon="fa-arrow-trend-up"
          type="efficient"
        />

        <x-ui.analytics-insight
          label="Least Efficient Vehicle"
          :value="$leastEfficientVehicle?->bus_no ?? 'No data'"
          :description="$leastEfficientVehicle
            ? number_format($leastEfficientVehicle->km_per_liter, 2).' km/L'
            : 'No fuel records available.'"
          icon="fa-arrow-trend-down"
          type="inefficient"
        />

        <x-ui.analytics-insight
          label="Fleet Average"
          :value="number_format($fleetAverage, 2).' km/L'"
          description="Average efficiency for the selected reporting period."
          icon="fa-chart-line"
          type="average"
        />

      </section>

      {{-- EFFICIENCY BY VEHICLE --}}
      <section class="table-card fuel-card fuel-efficiency-card">

        <div class="section-header">
          <div>
            <h2>Efficiency by Vehicle</h2>

            <p>
              Fuel efficiency summary based on the selected reporting period.
            </p>
          </div>
        </div>

        <x-ui.table-toolbar
          :action="route('fuel-reports')"
          class="toolbar fuel-toolbar"
          search-placeholder="Search bus, driver, status, or source"
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
                <th>Average KM/L</th>
                <th>VS Fleet Avg</th>
                <th>Entries</th>
                <th>Status</th>
              </tr>
            </thead>

            <tbody>
              @forelse($vehicleSummaries as $vehicle)
                @php
                  $statusClass = strtolower(
                    str_replace(' ', '-', $vehicle->status)
                  );

                  $vsSign = $vehicle->vs_fleet_avg >= 0 ? '+' : '';
                @endphp

                <tr class="{{ $vehicle->status === 'Inefficient' ? 'danger-row' : '' }}">
                  <td>{{ $vehicle->bus_no }}</td>

                  <td>
                    {{ number_format($vehicle->total_km, 2) }} km
                  </td>

                  <td>
                    {{ number_format($vehicle->total_liters, 2) }} L
                  </td>

                  <td>
                    {{ number_format($vehicle->km_per_liter, 2) }} km/L
                  </td>

                  <td>
                    {{ $vsSign }}{{ number_format($vehicle->vs_fleet_avg, 1) }}%
                  </td>

                  <td>{{ $vehicle->entries }}</td>

                  <td>
                    <span class="badge {{ $statusClass }}">
                      @if($vehicle->status === 'Inefficient')
                        <i class="fa-solid fa-triangle-exclamation"></i>
                      @elseif($vehicle->status === 'Efficient')
                        <i class="fa-solid fa-circle-check"></i>
                      @endif

                      {{ $vehicle->status }}
                    </span>
                  </td>
                </tr>
              @empty
                <tr>
                  <x-ui.empty-row
                    colspan="7"
                    message="No fuel records found for the selected period."
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

            <p>
              Latest fuel records with GPS or manual distance source.
            </p>
          </div>
        </div>

        <div class="table-wrap">
          <table class="fuel-table recent-records-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Vehicle</th>
                <th>Distance</th>
                <th>Fuel</th>
                <th>KM/L</th>
                <th>Driver</th>
                <th>Source</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              @forelse($recentFuelRecords as $record)
                @php
                  $recordStatusClass = strtolower(
                    str_replace(' ', '-', $record->status)
                  );
                @endphp

                <tr>
                  <td>
                    {{ $record->report_date?->format('M d, Y') }}
                  </td>

                  <td>{{ $record->bus_no }}</td>

                  <td>
                    {{ number_format((float) $record->distance_km, 2) }} km
                  </td>

                  <td>
                    {{ number_format((float) $record->fuel_liters, 2) }} L
                  </td>

                  <td>
                    {{ number_format((float) $record->km_per_liter, 2) }}
                  </td>

                  <td>
                    {{ $record->driver_name ?: '—' }}
                  </td>

                  <td>
                    <span class="source-badge {{ strtolower($record->distance_source) }}">
                      <i
                        class="fa-solid {{
                          $record->distance_source === 'GPS'
                            ? 'fa-location-dot'
                            : 'fa-pen'
                        }}"
                      ></i>

                      {{ $record->distance_source }}
                    </span>
                  </td>

                  <td>
                    <span class="badge {{ $recordStatusClass }}">
                      {{ $record->status }}
                    </span>
                  </td>

                  <td>
                    <div class="fuel-actions">

                      <button
                        type="button"
                        class="fuel-action-btn view"
                        title="View Fuel Record"
                        aria-label="View Fuel Record"
                        data-view-fuel
                        data-id="{{ $record->id }}"
                        data-report-date="{{ $record->report_date?->format('Y-m-d') }}"
                        data-bus-no="{{ $record->bus_no }}"
                        data-driver-name="{{ $record->driver_name }}"
                        data-distance-km="{{ $record->distance_km }}"
                        data-fuel-liters="{{ $record->fuel_liters }}"
                        data-km-per-liter="{{ $record->km_per_liter }}"
                        data-distance-source="{{ $record->distance_source }}"
                        data-status="{{ $record->status }}"
                        data-remarks="{{ $record->remarks }}"
                        data-manual-distance-reason="{{ $record->manual_distance_reason }}"
                        data-gps-date="{{ $record->gpsTripRecord?->beginning_at?->format('M d, Y h:i A') }}"
                        data-idling-minutes="{{ $record->gpsTripRecord?->idling_minutes }}"
                      >
                        <i class="fa-solid fa-eye"></i>
                      </button>

                      <button
                        type="button"
                        class="fuel-action-btn edit"
                        title="Edit Fuel Record"
                        aria-label="Edit Fuel Record"
                        data-edit-fuel
                        data-id="{{ $record->id }}"
                        data-update-url="{{ route('fuel-reports.update', $record) }}"
                        data-report-date="{{ $record->report_date?->format('Y-m-d') }}"
                        data-bus-no="{{ $record->bus_no }}"
                        data-driver-name="{{ $record->driver_name }}"
                        data-distance-km="{{ $record->distance_km }}"
                        data-fuel-liters="{{ $record->fuel_liters }}"
                        data-distance-source="{{ $record->distance_source }}"
                        data-remarks="{{ $record->remarks }}"
                        data-manual-distance-reason="{{ $record->manual_distance_reason }}"
                      >
                        <i class="fa-solid fa-pen-to-square"></i>
                      </button>

                      <form
                        action="{{ route('fuel-reports.destroy', $record) }}"
                        method="POST"
                        data-confirm-form
                        data-confirm-title="Delete Fuel Record?"
                        data-confirm-message="This fuel record will be permanently removed."
                        data-confirm-button="Yes, Delete"
                        data-confirm-type="delete"
                      >
                        @csrf
                        @method('DELETE')

                        <button
                          type="submit"
                          class="fuel-action-btn delete"
                          title="Delete Fuel Record"
                          aria-label="Delete Fuel Record"
                        >
                          <i class="fa-solid fa-trash"></i>
                        </button>
                      </form>

                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <x-ui.empty-row
                    colspan="9"
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

  {{-- ADD / EDIT FUEL RECORD MODAL --}}
  <div class="fuel-modal-overlay" id="fuelModal">
    <div class="fuel-modal">

      <div class="fuel-modal-header">
        <div>
          <h2 id="fuelModalTitle">Add Fuel Record</h2>

          <p>
            Select a bus and date. The system will automatically find the
            matching processed GPS mileage.
          </p>
        </div>

        <button
          type="button"
          class="fuel-modal-close"
          id="closeFuelModal"
          aria-label="Close modal"
        >
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <form
        id="fuelForm"
        action="{{ route('fuel-reports.store') }}"
        method="POST"
        data-store-url="{{ route('fuel-reports.store') }}"
        data-confirm-form
        data-confirm-title="Save Fuel Record?"
        data-confirm-message="The system will calculate the fuel efficiency using the selected distance."
        data-confirm-button="Yes, Save Record"
        data-confirm-type="create"
      >
        @csrf

        <input
          type="hidden"
          name="_method"
          id="fuelFormMethod"
          value="POST"
        >

        <div class="fuel-form-grid">

          <div class="form-group">
            <label for="fuelReportDate">Date</label>

            <input
              type="date"
              id="fuelReportDate"
              name="report_date"
              value="{{ old('report_date', now()->toDateString()) }}"
              required
            >
          </div>

          <div class="form-group">
            <label for="fuelBusNo">Vehicle</label>

            <select
              id="fuelBusNo"
              name="bus_no"
              required
            >
              <option value="">Select bus</option>

              @foreach($buses as $bus)
                @php
                  $busNumber = trim((string) $bus->bus_no);
                  $plateNumber = trim((string) $bus->plate_no);

                  $showPlateNumber =
                    $plateNumber !== '' &&
                    strtoupper($plateNumber) !== strtoupper($busNumber);
                @endphp

                <option
                  value="{{ $busNumber }}"
                  @selected(old('bus_no') === $busNumber)
                >
                  {{ $busNumber }}

                  @if($showPlateNumber)
                    — {{ $plateNumber }}
                  @endif
                </option>
              @endforeach
            </select>
          </div>

          <div class="form-group">
            <label for="fuelDriverName">Driver Name</label>

            <input
              type="text"
              id="fuelDriverName"
              name="driver_name"
              value="{{ old('driver_name') }}"
              placeholder="Optional"
            >
          </div>

          <div class="form-group">
            <label for="fuelLiters">Fuel Added</label>

            <div class="fuel-input-with-unit">
              <input
                type="number"
                id="fuelLiters"
                name="fuel_liters"
                step="0.01"
                min="0.01"
                value="{{ old('fuel_liters') }}"
                placeholder="0.00"
                required
              >

              <span>L</span>
            </div>
          </div>

          <div class="form-group full-width">
            <label>GPS Mileage Lookup</label>

            <div
              class="gps-status-card idle"
              id="gpsStatusCard"
            >
              <div class="gps-status-icon">
                <i class="fa-solid fa-location-dot"></i>
              </div>

              <div class="gps-status-content">
                <strong id="gpsStatusTitle">
                  Select a bus and date
                </strong>

                <p id="gpsStatusMessage">
                  The system will search for a processed GPS record.
                </p>

                <div
                  class="gps-status-details"
                  id="gpsStatusDetails"
                  hidden
                >
                  <span>
                    Distance:
                    <strong id="gpsDistanceValue">0.00 km</strong>
                  </span>

                  <span>
                    Idling:
                    <strong id="gpsIdlingValue">0 min</strong>
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div class="form-group full-width manual-toggle-group">
            <label class="manual-toggle">
              <input
                type="checkbox"
                id="useManualDistance"
                name="use_manual_distance"
                value="1"
                @checked(old('use_manual_distance'))
              >

              <span>Use manual distance instead</span>
            </label>

            <small>
              Use this only when no valid processed GPS record is available.
            </small>
          </div>

          <div
            class="manual-distance-fields full-width"
            id="manualDistanceFields"
            hidden
          >
            <div class="fuel-form-grid nested-grid">

              <div class="form-group">
                <label for="fuelDistanceKm">
                  Manual Distance
                </label>

                <div class="fuel-input-with-unit">
                  <input
                    type="number"
                    id="fuelDistanceKm"
                    name="distance_km"
                    step="0.01"
                    min="0.01"
                    value="{{ old('distance_km') }}"
                    placeholder="0.00"
                  >

                  <span>km</span>
                </div>
              </div>

              <div class="form-group">
                <label for="manualDistanceReason">
                  Reason
                </label>

                <input
                  type="text"
                  id="manualDistanceReason"
                  name="manual_distance_reason"
                  value="{{ old('manual_distance_reason') }}"
                  placeholder="Example: GPS device unavailable"
                >
              </div>

            </div>
          </div>

          <div class="form-group full-width">
            <label>Fuel Efficiency Preview</label>

            <div
              class="efficiency-preview"
              id="efficiencyPreview"
            >
              <div>
                <span>Calculated KM/L</span>
                <strong id="efficiencyValue">0.00</strong>
              </div>

              <span
                class="badge no-data"
                id="efficiencyStatus"
              >
                No Data
              </span>
            </div>
          </div>

          <div class="form-group full-width">
            <label for="fuelRemarks">Remarks</label>

            <textarea
              id="fuelRemarks"
              name="remarks"
              rows="3"
              placeholder="Optional remarks"
            >{{ old('remarks') }}</textarea>
          </div>

        </div>

        <div class="fuel-modal-actions">
          <button
            type="button"
            class="secondary-btn fuel-cancel-btn"
            id="cancelFuelModal"
          >
            Cancel
          </button>

          <button
            type="submit"
            class="primary-btn"
            id="saveFuelRecord"
          >
            <i class="fa-solid fa-floppy-disk"></i>
            <span id="saveFuelText">Save Fuel Record</span>
          </button>
        </div>

      </form>

    </div>
  </div>

  {{-- VIEW FUEL RECORD MODAL --}}
  <div class="fuel-modal-overlay" id="fuelViewModal">
    <div class="fuel-modal fuel-view-modal">

      <div class="fuel-modal-header">
        <div>
          <h2>Fuel Record Details</h2>
          <p>Complete fuel efficiency and distance information.</p>
        </div>

        <button
          type="button"
          class="fuel-modal-close"
          id="closeFuelViewModal"
          aria-label="Close modal"
        >
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <div class="fuel-view-grid">

        <div class="fuel-view-item">
          <span>Date</span>
          <strong id="viewFuelDate">—</strong>
        </div>

        <div class="fuel-view-item">
          <span>Vehicle</span>
          <strong id="viewFuelBus">—</strong>
        </div>

        <div class="fuel-view-item">
          <span>Driver</span>
          <strong id="viewFuelDriver">—</strong>
        </div>

        <div class="fuel-view-item">
          <span>Distance</span>
          <strong id="viewFuelDistance">—</strong>
        </div>

        <div class="fuel-view-item">
          <span>Fuel Added</span>
          <strong id="viewFuelLiters">—</strong>
        </div>

        <div class="fuel-view-item">
          <span>Fuel Efficiency</span>
          <strong id="viewFuelEfficiency">—</strong>
        </div>

        <div class="fuel-view-item">
          <span>Distance Source</span>
          <strong id="viewFuelSource">—</strong>
        </div>

        <div class="fuel-view-item">
          <span>Status</span>
          <strong id="viewFuelStatus">—</strong>
        </div>

        <div class="fuel-view-item">
          <span>GPS Record Date</span>
          <strong id="viewGpsDate">—</strong>
        </div>

        <div class="fuel-view-item">
          <span>GPS Idling</span>
          <strong id="viewIdlingMinutes">—</strong>
        </div>

        <div class="fuel-view-item full-width">
          <span>Manual Distance Reason</span>
          <strong id="viewManualReason">—</strong>
        </div>

        <div class="fuel-view-item full-width">
          <span>Remarks</span>
          <strong id="viewFuelRemarks">—</strong>
        </div>

      </div>

      <div class="fuel-modal-actions">
        <button
          type="button"
          class="primary-btn"
          id="closeFuelViewButton"
        >
          Close
        </button>
      </div>

    </div>
  </div>

  {{-- CHART DATA --}}
  <x-ui.chart-data
    id="fuelAnalyticsData"
    :data="[
      'labels' => $vehicleSummaries
        ->pluck('bus_no')
        ->values(),

      'efficiency' => $vehicleSummaries
        ->pluck('km_per_liter')
        ->map(fn ($value) => round((float) $value, 2))
        ->values(),

      'distance' => $vehicleSummaries
        ->pluck('total_km')
        ->map(fn ($value) => round((float) $value, 2))
        ->values(),

      'fuel' => $vehicleSummaries
        ->pluck('total_liters')
        ->map(fn ($value) => round((float) $value, 2))
        ->values(),

      'fleetAverage' => round((float) $fleetAverage, 2),
    ]"
  />

</x-layout.app>