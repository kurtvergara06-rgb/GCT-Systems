<x-layout.app
    title="FROMS - Daily Drivers Report"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Main-styles/form-components.css',
        'resources/css/Operation/Daily_Driver_Reports/daily-driver-reports.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Operation/Daily_Driver_Reports/daily-driver-reports.js',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Operation" />

        <main class="main ddr-page">
            <x-layout.topbar
                title="Daily Drivers Report"
                subtitle="Encoded actual trip execution from physical daily driver reports"
            />

            <!-- Summary KPI Cards -->
            <section class="ddr-summary-grid">
                <article class="ddr-summary-card">
                    <div class="ddr-summary-icon blue">
                        <i class="fa-solid fa-file-lines"></i>
                    </div>
                    <div>
                        <p>Total Reports Encoded</p>
                        <h2>{{ number_format($totalReports) }}</h2>
                        <small>All daily driver reports</small>
                    </div>
                </article>

                <article class="ddr-summary-card">
                    <div class="ddr-summary-icon green">
                        <i class="fa-solid fa-calendar-day"></i>
                    </div>
                    <div>
                        <p>Reports Today</p>
                        <h2>{{ number_format($reportsToday) }}</h2>
                        <small>Encoded for today</small>
                    </div>
                </article>

                <article class="ddr-summary-card">
                    <div class="ddr-summary-icon yellow">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <p>Passengers Today</p>
                        <h2>{{ number_format($passengersToday) }}</h2>
                        <small>Total passengers carried today</small>
                    </div>
                </article>

                <article class="ddr-summary-card">
                    <div class="ddr-summary-icon purple">
                        <i class="fa-solid fa-chart-simple"></i>
                    </div>
                    <div>
                        <p>Avg Passengers / Trip</p>
                        <h2>{{ number_format($passengerCount, 1) }}</h2>
                        <small>Load average for today</small>
                    </div>
                </article>
            </section>

            <!-- Main Records Card -->
            <section class="ddr-card">
                <div class="ddr-card-header">
                    <div>
                        <h2>Daily Driver Report Records</h2>
                        <p>Actual shuttle trip execution encoded from physical daily driver reports.</p>
                    </div>

                    <a href="{{ route('daily-driver-reports.create') }}" class="ddr-new-btn">
                        <i class="fa-solid fa-plus"></i>
                        Encode New Report
                    </a>
                </div>

                <!-- Filter & Search Toolbar -->
                <form method="GET" action="{{ route('daily-driver-reports') }}" class="ddr-toolbar">
                    <div class="ddr-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Search by DDR no, driver, trip ticket, location..."
                        />
                    </div>

                    <div class="ddr-filter date-filter">
                        <label for="filterDate">Report Date</label>
                        <input
                            type="date"
                            id="filterDate"
                            name="report_date"
                            value="{{ request('report_date') }}"
                            onchange="this.form.submit()"
                        />
                    </div>

                    <div class="ddr-filter">
                        <label for="filterDriver">Driver</label>
                        <select id="filterDriver" name="driver" onchange="this.form.submit()">
                            <option value="all">All Drivers</option>
                            @foreach($drivers as $driver)
                                <option value="{{ $driver->driver_id }}" @selected(request('driver') == $driver->driver_id)>
                                    {{ $driver->driver_name }} ({{ $driver->driver_id }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="ddr-filter">
                        <label for="filterBus">Bus</label>
                        <select id="filterBus" name="bus" onchange="this.form.submit()">
                            <option value="all">All Buses</option>
                            @foreach($buses as $bus)
                                <option value="{{ $bus->id }}" @selected(request('bus') == $bus->id)>
                                    {{ $bus->bus_no }} - {{ $bus->plate_no }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if(request()->anyFilled(['search', 'report_date', 'driver', 'bus']))
                        <a href="{{ route('daily-driver-reports') }}" class="ddr-clear-btn" title="Reset all filters">
                            <i class="fa-solid fa-rotate-left"></i> Reset
                        </a>
                    @endif
                </form>

                <!-- DDR Table Container with Contained Scroll -->
                <div class="ddr-table-wrap">
                    <table class="ddr-table">
                        <thead>
                            <tr>
                                <th>DDR No.</th>
                                <th>Report Date</th>
                                <th>Driver</th>
                                <th>Bus</th>
                                <th>Trip Ticket</th>
                                <th>Route</th>
                                <th>Actual Times</th>
                                <th>Passengers</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($reports as $report)
                                @php
                                    $dateFormatted = $report->report_date
                                        ? $report->report_date->format('M d, Y')
                                        : '—';

                                    $departureFmt = $report->departure_time
                                        ? $report->departure_time->format('H:i')
                                        : '—';

                                    $arrivalFmt = $report->arrival_time
                                        ? $report->arrival_time->format('H:i')
                                        : '—';

                                    $routeFmt = trim(e($report->from_location))
                                        . ' → '
                                        . trim(e($report->to_location));
                                @endphp

                                <tr>
                                    <td>
                                        <x-ui.id-badge :value="$report->ddr_no" />
                                    </td>

                                    <td>
                                        <strong style="font-size: 12px; color: var(--ddr-navy);">{{ $dateFormatted }}</strong>
                                    </td>

                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 2px;">
                                            <span style="font-weight: 700; color: var(--ddr-navy);">{{ $report->driver_name }}</span>
                                            @if($report->driver_id)
                                                <small style="color: var(--ddr-muted); font-size: 11px;">{{ $report->driver_id }}</small>
                                            @endif
                                        </div>
                                    </td>

                                    <td>
                                        @if($report->bus)
                                            <x-ui.id-badge :value="$report->bus->bus_no" />
                                        @else
                                            <span style="color: #94a3b8; font-style: italic;">—</span>
                                        @endif
                                    </td>

                                    <td>
                                        <x-ui.id-badge :value="$report->trip_ticket" tone="neutral" />
                                    </td>

                                    <td>
                                        <div class="ddr-route-cell">
                                            <span>{!! $routeFmt !!}</span>
                                        </div>
                                    </td>

                                    <td>
                                        <span style="font-weight: 700; color: var(--ddr-navy); font-size: 12px;">
                                            {{ $departureFmt }} <span style="color: #94a3b8;">→</span> {{ $arrivalFmt }}
                                        </span>
                                        <br>
                                        <small style="color: var(--ddr-muted); font-size: 11px;">
                                            {{ number_format($report->passengers) }} pax
                                        </small>
                                    </td>

                                    <td>
                                        <span class="ddr-passenger-pill">
                                            {{ number_format($report->passengers) }}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="ddr-actions">
                                            <a
                                                href="{{ route('daily-driver-reports.show', ['dailyDriverReport' => $report->ddr_no]) }}"
                                                class="ddr-action view"
                                                title="View Report Details"
                                            >
                                                <i class="fa-regular fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 48px 20px;">
                                        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; color: var(--ddr-muted);">
                                            <i class="fa-solid fa-file-lines" style="font-size: 32px; color: #cbd5e1;"></i>
                                            <strong style="font-size: 15px; color: var(--ddr-navy);">No Daily Driver Reports Found</strong>
                                            <p style="font-size: 13px; margin: 0; max-width: 420px;">
                                                No encoded reports matched your search or filter criteria. Try adjusting the date, driver, or bus filters.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div class="ddr-table-footer">
                    <span>
                        Showing {{ $reports->firstItem() ?? 0 }} to {{ $reports->lastItem() ?? 0 }} of {{ $reports->total() }} daily driver reports
                    </span>

                    <div class="ddr-pagination">
                        @if ($reports->onFirstPage())
                            <button type="button" class="disabled" disabled>
                                <i class="fa-solid fa-chevron-left"></i> Previous
                            </button>
                        @else
                            <a href="{{ $reports->previousPageUrl() }}">
                                <i class="fa-solid fa-chevron-left"></i> Previous
                            </a>
                        @endif

                        <span>Page {{ $reports->currentPage() }} of {{ $reports->lastPage() }}</span>

                        @if ($reports->hasMorePages())
                            <a href="{{ $reports->nextPageUrl() }}">
                                Next <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        @else
                            <button type="button" class="disabled" disabled>
                                Next <i class="fa-solid fa-chevron-right"></i>
                            </button>
                        @endif
                    </div>
                </div>
            </section>
        </main>
    </div>
</x-layout.app>