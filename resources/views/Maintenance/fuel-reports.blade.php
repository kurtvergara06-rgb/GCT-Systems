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

    <x-layout.sidebar department="Maintenance" />

    <main
      class="main fuel-page"
      data-gps-url="{{ route('fuel-reports.gps-distance') }}"
      data-store-url="{{ route('fuel-reports.store') }}"
    >

      <x-layout.topbar
        title="Fuel Reports"
        subtitle="Manage recorded fuel issuance, trip mileage, and fuel report history"
      />


      {{-- Fuel issuance KPIs use the same shared cards as Job Orders.
           These metrics reflect the selected reporting period and do not
           imply that issued diesel has already been consumed. --}}
      <section data-ajax-region="summary" class="stats-grid fuel-stats-grid">
        <x-ui.summary-card label="Fuel Records" value="{{ $fuelReports->count() }}" small="Recorded fuel entries" icon="fa-file-lines" color="blue" />
        <x-ui.summary-card label="Diesel Issued" value="{{ number_format($totalFuelUsed, 2) }} L" small="Recorded issuance volume" icon="fa-gas-pump" color="yellow" />
        <x-ui.summary-card label="Recorded Distance" value="{{ number_format($totalDistance, 2) }} km" small="Recorded GPS or manual distance" icon="fa-road" color="green" />
        <x-ui.summary-card label="Vehicles Recorded" value="{{ $fuelReports->pluck('bus_no')->filter()->unique()->count() }}" small="Distinct buses with fuel records" icon="fa-bus" color="red" />
      </section>

      {{-- RECENT FUEL ENTRIES --}}
      <section data-ajax-region="fuel-records" class="table-card fuel-card recent-fuel-card">

        <div class="section-header">
          <div>
            <h2>Fuel Records</h2>
            <p>Fuel issuance entries and their recorded trip distance.</p>
          </div>
          <button type="button" id="openFuelModal" class="fuel-new-report-btn"><i class="fa-solid fa-plus" aria-hidden="true"></i> New Fuel Report</button>
        </div>

        <div class="fuel-record-controls recent-fuel-controls">
          <x-ui.table-toolbar
            :action="route('fuel-reports')"
            form-id="recentFuelEntriesToolbar"
            search-label="Search Fuel Records"
            search-placeholder="Search bus, driver, or source..."
            :filter-count="(int) (request('date_filter', 'This Month') !== 'This Month')"
            filter-title="Filter Fuel Entries"
            :sort-options="[
              'report_date' => 'Report Date',
              'bus_no' => 'Bus ID',
              'driver_name' => 'Driver',
              'distance_km' => 'Distance',
              'fuel_liters' => 'Fuel Used',
              'km_per_liter' => 'Efficiency',
              'status' => 'Status',
            ]"
            default-sort="report_date"
            default-direction="desc"
            data-server-filter="true"
          >
            <x-slot:hidden>
              <input type="hidden" name="monitor_date" value="{{ $monitorDate }}">
              <input type="hidden" name="monitor_filter" value="{{ $monitorFilter }}">
            </x-slot:hidden>
            <x-slot:filters>
              <x-ui.table-toolbar-field label="Date Range" name="date_filter" default="This Month">
                @foreach(['This Month', 'Today', 'This Week', 'All Dates'] as $dateFilter)
                  <option value="{{ $dateFilter }}" @selected(request('date_filter', 'This Month') === $dateFilter)>{{ $dateFilter }}</option>
                @endforeach
              </x-ui.table-toolbar-field>
            </x-slot:filters>
          </x-ui.table-toolbar>
        </div>

        <div class="table-wrap">

          <table class="fuel-table recent-records-table">

            <thead>
              <tr>
                <th>Record No.</th>
                <th>Date</th>
                <th>Bus</th>
                <th>Driver</th>
                <th>Gas Station</th>
                <th>Liters (L)</th>
                <th>Total KM</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>

              @forelse($recentFuelRecords as $record)

                @php
                  $recordStatusClass =
                    strtolower(
                      str_replace(
                        ' ',
                        '-',
                        $record->status
                      )
                    );
                @endphp

                <tr>
                  <td><span class="fuel-record-reference">#{{ $record->id }}</span></td>
                  <td>{{ $record->report_date?->format('M d, Y') }}</td>
                  <td><span class="bus-badge-pill"><i class="fa-solid fa-bus" aria-hidden="true"></i> {{ $record->bus_no }}</span></td>
                  <td>{{ $record->driver_name ?: '—' }}</td>
                  <td><span title="Gas station is not yet recorded in the existing fuel report schema">—</span></td>
                  <td>{{ number_format((float) $record->fuel_liters, 2) }}</td>
                  <td>{{ number_format((float) $record->distance_km, 2) }}</td>
                  <td><x-ui.status-badge :status="$record->status" class="{{ $recordStatusClass }}" /></td>

                  <td>

                    <div class="fuel-actions">

                      <button
                        type="button"
                        class="fuel-action-btn view"
                        title="View Fuel Record"
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
                        >
                          <i class="fa-solid fa-trash"></i>
                        </button>

                      </form>

                    </div>

                  </td>

                </tr>

              @empty

                <x-ui.empty-row
                  colspan="9"
                  icon="fa-gas-pump"
                  message="No Fuel Records Found"
                  description="No fuel dispensing or consumption logs match your search or filter criteria."
                />

              @endforelse

            </tbody>

          </table>

        </div>

      </section>

    </main>

  </div>

  {{-- ADD / EDIT FUEL RECORD --}}
  <div class="fuel-modal-overlay" id="fuelModal">

    <div class="fuel-modal fuel-record-redesign-modal">

      <div class="fuel-modal-header fuel-record-modal-header">
        <div class="fuel-record-modal-heading">
          <span class="fuel-record-title-icon" aria-hidden="true">
            <i class="fa-solid fa-gas-pump"></i>
          </span>

          <div>
            <h2 id="fuelModalTitle">Add Fuel Record</h2>
            <p id="fuelModalDescription">
              Select a bus and date. The system will automatically find the matching processed GPS mileage.
            </p>
          </div>
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

        <input type="hidden" name="_method" id="fuelFormMethod" value="POST">

        <div class="fuel-record-modal-body">

          <section class="fuel-record-section fuel-record-basic-section">
            <div class="fuel-record-top-grid">

              <div class="form-group">
                <label for="fuelReportDate">
                  Date
                  <span class="fuel-field-required">*</span>
                </label>

                <div class="fuel-field-control has-leading-icon">
                  <span class="fuel-field-icon">
                    <i class="fa-solid fa-calendar-days"></i>
                  </span>

                  <input
                    type="date"
                    id="fuelReportDate"
                    name="report_date"
                    value="{{ old('report_date', now()->toDateString()) }}"
                    required
                  >
                </div>

                <small class="fuel-field-help">Select the date of the fuel entry.</small>
              </div>

              <div class="form-group">
                <label for="fuelBusNo">
                  Bus ID
                  <span class="fuel-field-required">*</span>
                </label>

                <div class="fuel-field-control has-leading-icon">
                  <span class="fuel-field-icon">
                    <i class="fa-solid fa-bus"></i>
                  </span>

                  <select id="fuelBusNo" name="bus_no" required>
                    <option value="">Select Bus ID or plate number</option>

                    @foreach($buses as $bus)
                      @php
                        $busNumber = trim((string) $bus->bus_no);
                        $plateNumber = trim((string) $bus->plate_no);
                        $showPlateNumber = $plateNumber !== '' && strtoupper($plateNumber) !== strtoupper($busNumber);
                      @endphp

                      <option value="{{ $busNumber }}" @selected(old('bus_no') === $busNumber)>
                        {{ $busNumber }}
                        @if($showPlateNumber)
                          — {{ $plateNumber }}
                        @endif
                      </option>
                    @endforeach
                  </select>
                </div>

                <small class="fuel-field-help">Select the bus to load its processed GPS data.</small>
              </div>

              <div class="fuel-record-field-card driver-card">
                <div class="fuel-record-field-card-heading">
                  <span class="fuel-record-field-card-icon blue">
                    <i class="fa-solid fa-user"></i>
                  </span>
                  <label for="fuelDriverName">Driver Name</label>
                </div>

                <input
                  type="text"
                  id="fuelDriverName"
                  name="driver_name"
                  value="{{ old('driver_name') }}"
                  placeholder="Driver name (optional)"
                  maxlength="255"
                >

                <small>Select or enter the driver who refueled the bus.</small>
              </div>

              <div class="fuel-record-field-card fuel-added-card">
                <div class="fuel-record-field-card-heading">
                  <span class="fuel-record-field-card-icon yellow">
                    <i class="fa-solid fa-droplet"></i>
                  </span>
                  <label for="fuelLiters">Fuel Added</label>
                </div>

                <div class="fuel-input-with-unit fuel-added-control">
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
                  <span>Liters</span>
                </div>

                <small>Enter the actual liters refueled.</small>
              </div>

            </div>
          </section>

          <section class="fuel-record-section fuel-gps-lookup-section">
            <div class="fuel-record-section-heading">
              <div class="fuel-record-section-title">
                <span class="fuel-record-section-icon blue">
                  <i class="fa-solid fa-location-dot"></i>
                </span>

                <div>
                  <h3>GPS Mileage Lookup</h3>
                  <p>The system searches for a processed GPS record for the selected bus and date.</p>
                </div>
              </div>

              <button
                type="button"
                class="fuel-gps-refresh-btn"
                id="fuelGpsLookupButton"
              >
                <i class="fa-solid fa-rotate"></i>
                Search GPS Data
              </button>
            </div>

            <div class="gps-status-card idle fuel-gps-status-card" id="gpsStatusCard">
              <div class="gps-status-icon">
                <i class="fa-solid fa-location-dot"></i>
              </div>

              <div class="gps-status-content">
                <strong id="gpsStatusTitle">Select a bus and date</strong>
                <p id="gpsStatusMessage">The system will search for a processed GPS record.</p>

                <div class="gps-status-details" id="gpsStatusDetails" hidden>
                  <span>Distance: <strong id="gpsDistanceValue">0.00 km</strong></span>
                  <span>Idling: <strong id="gpsIdlingValue">0 min</strong></span>
                </div>
              </div>
            </div>

            <div class="fuel-gps-metric-grid">
              <div class="fuel-gps-metric-card">
                <span class="fuel-gps-metric-icon">
                  <i class="fa-solid fa-road"></i>
                </span>

                <div>
                  <span>GPS Distance</span>
                  <strong id="fuelPreviewDistance">0.00 km</strong>
                  <small>Processed distance for this entry.</small>
                </div>
              </div>

              <div class="fuel-gps-metric-card">
                <span class="fuel-gps-metric-icon">
                  <i class="fa-solid fa-clock"></i>
                </span>

                <div>
                  <span>Idling Time</span>
                  <strong id="fuelPreviewIdling">0 min</strong>
                  <small>Processed GPS idling time.</small>
                </div>
              </div>
            </div>

            <div class="manual-toggle-group fuel-manual-toggle-card">
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
              <small>Use this only when no valid processed GPS record is available.</small>
            </div>

            <div class="manual-distance-fields" id="manualDistanceFields" hidden>
              <div class="fuel-form-grid nested-grid">
                <div class="form-group">
                  <label for="fuelDistanceKm">Manual Distance</label>
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
                  <label for="manualDistanceReason">Reason</label>
                  <input
                    type="text"
                    id="manualDistanceReason"
                    name="manual_distance_reason"
                    value="{{ old('manual_distance_reason') }}"
                    placeholder="Example: GPS device unavailable"
                    maxlength="1000"
                  >
                </div>
              </div>
            </div>
          </section>

          <section class="fuel-record-section fuel-efficiency-section">
            <div class="fuel-record-section-title">
              <span class="fuel-record-section-icon green">
                <i class="fa-solid fa-chart-column"></i>
              </span>

              <div>
                <h3>Fuel Efficiency Preview</h3>
                <p>Calculated automatically using the selected distance and fuel added.</p>
              </div>
            </div>

            <div class="efficiency-preview fuel-efficiency-redesign" id="efficiencyPreview">
              <div class="fuel-efficiency-metric">
                <span>Distance</span>
                <strong id="fuelEfficiencyDistance">0.00 km</strong>
              </div>

              <div class="fuel-efficiency-metric">
                <span>Fuel Added</span>
                <strong id="fuelEfficiencyLiters">0.00 L</strong>
              </div>

              <div class="fuel-efficiency-metric emphasis">
                <span>Estimated Efficiency</span>
                <strong><span id="efficiencyValue">0.00</span> km/L</strong>
              </div>

              <span class="badge no-data" id="efficiencyStatus">No Data</span>
            </div>
          </section>

          <section class="fuel-record-section fuel-remarks-section">
            <div class="fuel-record-section-title compact">
              <span class="fuel-record-section-icon slate">
                <i class="fa-solid fa-note-sticky"></i>
              </span>

              <div>
                <h3>Remarks <span>(Optional)</span></h3>
              </div>
            </div>

            <textarea
              id="fuelRemarks"
              name="remarks"
              rows="3"
              maxlength="2000"
              placeholder="Enter additional notes here..."
            >{{ old('remarks') }}</textarea>

            <div class="fuel-remarks-counter">
              <span id="fuelRemarksCount">0</span>/2000
            </div>
          </section>

        </div>

        <div class="fuel-modal-actions fuel-record-modal-actions">
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

  {{-- VIEW FUEL RECORD --}}
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
        >
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <div class="fuel-view-grid">
        <div class="fuel-view-item"><span>Date</span><strong id="viewFuelDate">—</strong></div>
        <div class="fuel-view-item"><span>Vehicle</span><strong id="viewFuelBus">—</strong></div>
        <div class="fuel-view-item"><span>Driver</span><strong id="viewFuelDriver">—</strong></div>
        <div class="fuel-view-item"><span>Distance</span><strong id="viewFuelDistance">—</strong></div>
        <div class="fuel-view-item"><span>Fuel Added</span><strong id="viewFuelLiters">—</strong></div>
        <div class="fuel-view-item"><span>Fuel Efficiency</span><strong id="viewFuelEfficiency">—</strong></div>
        <div class="fuel-view-item"><span>Distance Source</span><strong id="viewFuelSource">—</strong></div>
        <div class="fuel-view-item"><span>Status</span><strong id="viewFuelStatus">—</strong></div>
        <div class="fuel-view-item"><span>GPS Record Date</span><strong id="viewGpsDate">—</strong></div>
        <div class="fuel-view-item"><span>GPS Idling</span><strong id="viewIdlingMinutes">—</strong></div>
        <div class="fuel-view-item full-width"><span>Manual Distance Reason</span><strong id="viewManualReason">—</strong></div>
        <div class="fuel-view-item full-width"><span>Remarks</span><strong id="viewFuelRemarks">—</strong></div>
      </div>

      <div class="fuel-modal-actions">
        <button type="button" class="primary-btn" id="closeFuelViewButton">Close</button>
      </div>

    </div>

  </div>

  {{-- CHART DATA --}}
  <x-ui.chart-data
    id="fuelAnalyticsData"
    :data="[
      'labels' => $vehicleSummaries->pluck('bus_no')->values(),
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
