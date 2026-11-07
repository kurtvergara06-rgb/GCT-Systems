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
          <button type="button" id="openPhysicalFuelForm" class="fuel-new-report-btn"><i class="fa-solid fa-plus" aria-hidden="true"></i> New Fuel Report</button>
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

        <div class="table-wrap gct-responsive-records gct-sticky-actions">

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
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>

              @forelse($recentFuelRecords as $record)

                <tr>
                  <td><span class="fuel-record-reference">#{{ $record->id }}</span></td>
                  <td>{{ $record->report_date?->format('M d, Y') }}</td>
                  <td><span class="bus-badge-pill"><i class="fa-solid fa-bus" aria-hidden="true"></i> {{ $record->bus_no }}</span></td>
                  <td>{{ $record->driver_name ?: '—' }}</td>
                  <td>{{ $record->gas_station ?: '—' }}</td>
                  <td>{{ number_format((float) $record->fuel_liters, 2) }}</td>
                  <td>{{ number_format((float) $record->distance_km, 2) }}</td>

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

                      @if($record->entry_type !== 'Physical Form')
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
                      @endif

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
                  colspan="8"
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

  <div class="fuel-paper-overlay" id="physicalFuelModal" hidden>
    <div class="fuel-paper-modal" role="dialog" aria-modal="true" aria-labelledby="physicalFuelTitle">
      <div class="fuel-paper-header">
        <span class="fuel-paper-heading-icon"><i class="fa-solid fa-gas-pump" aria-hidden="true"></i></span>
        <div><h2 id="physicalFuelTitle">New Fuel Report</h2><p>Encode the driver's Fuel Efficiency Monitoring Form</p></div>
        <button type="button" id="closePhysicalFuelForm" class="fuel-paper-close" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form method="POST" action="{{ route('fuel-reports.store') }}" id="physicalFuelForm"
        data-confirm-form
        data-confirm-title="Save Fuel Report?"
        data-confirm-message="Confirm the fuel issuance and trip details are accurate before saving."
        data-confirm-button="Yes, Save Fuel Report"
        data-confirm-type="create">
        @csrf
        <input type="hidden" name="physical_fuel_form" value="1">
        <div class="fuel-paper-content">
          @if($errors->any())<div class="fuel-paper-errors" role="alert">{{ $errors->first() }}</div>@endif
          <details class="fuel-paper-section" open>
            <summary><span class="fuel-paper-step">01</span> Fuel Issuance Details <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
            <div class="fuel-paper-grid">
              <div class="fuel-paper-field"><label for="physical-date">Report Date *</label><input type="date" id="physical-date" name="report_date" value="{{ old('report_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
              <div class="fuel-paper-field"><label for="physical-station">Gas Station *</label><input id="physical-station" name="gas_station" value="{{ old('gas_station') }}" placeholder="Enter gas station" maxlength="150" required></div>
              <div class="fuel-paper-field fuel-paper-search-picker" data-picker="bus"><label for="physical-bus-search">Bus *</label>
                <input type="hidden" id="physical-bus" name="bus_no" value="{{ old('bus_no') }}">
                <div class="fuel-paper-search-shell"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input id="physical-bus-search" type="search" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="physical-bus-options" placeholder="Search bus number or plate..." autocomplete="off" required></div>
                <div class="fuel-paper-search-options" id="physical-bus-options" role="listbox" hidden>@foreach($buses as $bus)<button type="button" role="option" class="fuel-paper-search-option" data-value="{{ $bus->bus_no }}" data-name="{{ $bus->bus_no }}" data-search="{{ strtolower($bus->bus_no.' '.$bus->plate_no) }}" data-plate="{{ $bus->plate_no }}">{{ $bus->bus_no }}</button>@endforeach<p class="fuel-paper-search-empty" hidden>No matching buses</p></div>
              </div>
              <div class="fuel-paper-field fuel-paper-search-picker" data-picker="driver"><label for="physical-driver-search">Driver / Employee *</label>
                <input type="hidden" id="physical-driver" name="driver_id" value="{{ old('driver_id') }}">
                <div class="fuel-paper-search-shell"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input id="physical-driver-search" type="search" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="physical-driver-options" placeholder="Search driver name or ID..." autocomplete="off" required></div>
                <div class="fuel-paper-search-options" id="physical-driver-options" role="listbox" hidden>@foreach($drivers as $driver)<button type="button" role="option" class="fuel-paper-search-option" data-value="{{ $driver->driver_id }}" data-name="{{ $driver->driver_name }}" data-search="{{ strtolower($driver->driver_name.' '.$driver->driver_id) }}">{{ $driver->driver_name }}</button>@endforeach<p class="fuel-paper-search-empty" hidden>No matching drivers</p></div>
              </div>
              <div class="fuel-paper-field"><label for="physical-plate">Plate Number</label><input id="physical-plate" readonly placeholder="Selected bus plate"></div>
              <div class="fuel-paper-field"><label for="physical-time">Issuance Time</label><input id="physical-time" type="time" name="issued_at" value="{{ old('issued_at') }}"></div>
              <div class="fuel-paper-field"><label for="physical-po">PO Number</label><input id="physical-po" name="po_reference" value="{{ old('po_reference') }}" maxlength="80" placeholder="Optional reference"></div>
              <div class="fuel-paper-field"><label for="physical-liters">Diesel Issued (L) *</label><input id="physical-liters" type="number" step="0.01" min="0.01" name="fuel_liters" value="{{ old('fuel_liters') }}" placeholder="0.00" required></div>
            </div>
          </details>
          <details class="fuel-paper-section" open>
            <summary><span class="fuel-paper-step">02</span> Trip Details <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
            <div class="fuel-paper-trip-content">
              <p class="fuel-paper-hint">Enter the trips on the paper form. Do not double-count DDR distances.</p>
              <div id="physicalTripRows" class="fuel-paper-trip-rows"></div>
              <div class="fuel-paper-trip-bottom"><button type="button" id="addPhysicalTrip" class="fuel-paper-add-trip"><i class="fa-solid fa-plus"></i> Add Trip Row</button><strong>Total KM: <span id="physicalTripTotal">0.00</span> km</strong></div>
            </div>
          </details>
          <details class="fuel-paper-section" open>
            <summary><span class="fuel-paper-step">03</span> Remarks <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
            <div class="fuel-paper-remarks"><label for="physical-remarks">Additional notes (optional)</label><textarea id="physical-remarks" name="remarks" rows="3" maxlength="2000" placeholder="Full tank, special trip, observations...">{{ old('remarks') }}</textarea></div>
          </details>
        </div>
        <div class="fuel-paper-footer"><button type="button" id="cancelPhysicalFuelForm" class="fuel-paper-cancel">Cancel</button><button type="submit" class="fuel-paper-save"><i class="fa-solid fa-floppy-disk"></i> Save Fuel Report</button></div>
      </form>
    </div>
  </div>
  @if(old('physical_fuel_form'))
    <span id="physicalFuelReopen" hidden></span>
  @endif
  <script type="application/json" id="physicalFuelOldTrips">@json(old('trips', []))</script>
  <template id="physicalTripTemplate">
    <div class="fuel-paper-trip">
      <div class="fuel-paper-trip-title"><strong>Trip <span class="fuel-paper-trip-number"></span></strong><button type="button" class="fuel-paper-remove-trip" aria-label="Remove trip"><i class="fa-solid fa-trash"></i></button></div>
      <div class="fuel-paper-trip-grid">
        <div class="fuel-paper-field"><label>Trip Date *</label><input type="date" data-field="trip_date" required></div>
        <div class="fuel-paper-field"><label>Trip Ticket No.</label><input data-field="ticket_number" maxlength="80"></div>
        <div class="fuel-paper-field"><label>Origin *</label><input data-field="origin" maxlength="150" required></div>
        <div class="fuel-paper-field"><label>Destination *</label><input data-field="destination" maxlength="150" required></div>
        <div class="fuel-paper-field"><label>Distance (km) *</label><input type="number" data-field="distance_km" step="0.01" min="0.01" required></div>
      </div>
    </div>
  </template>

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
