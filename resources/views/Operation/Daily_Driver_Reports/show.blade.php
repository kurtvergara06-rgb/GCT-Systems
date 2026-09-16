<x-layout.app
    title="FROMS - Daily Driver Report Details"
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
                title="Daily Driver Report Details"
                subtitle="Encoded trip execution and scheduled comparison"
            />

            @if (session('success'))
                <div class="ddr-alert ddr-alert-success" role="alert">
                    <i class="fa-solid fa-circle-check"></i>
                    <div>
                        <strong>{{ session('success') }}</strong>
                    </div>
                </div>
            @endif

            <div class="ddr-show-layout">
                <!-- Report Details -->
                <section class="ddr-card">
                    <div class="ddr-card-header">
                        <div>
                            <h2>Report Details</h2>
                            <p>Actual values taken straight from the physical DDR.</p>
                        </div>

                        <x-ui.id-badge :value="$report->ddr_no" size="large" />
                    </div>

                    <div class="ddr-detail-grid">
                        <div class="ddr-detail-item">
                            <span class="ddr-detail-label">Report Date</span>
                            <span class="ddr-detail-value">{{ $report->report_date ? $report->report_date->format('M d, Y') : '—' }}</span>
                        </div>

                        <div class="ddr-detail-item">
                            <span class="ddr-detail-label">Trip Ticket No.</span>
                            <span class="ddr-detail-value">{{ $report->trip_ticket }}</span>
                        </div>

                        <div class="ddr-detail-item">
                            <span class="ddr-detail-label">Driver</span>
                            <span class="ddr-detail-value">{{ $report->driver_name }}</span>
                            @if($report->driver_id)
                                <small class="ddr-detail-sub">{{ $report->driver_id }}</small>
                            @endif
                        </div>

                        <div class="ddr-detail-item">
                            <span class="ddr-detail-label">Bus</span>
                            <span class="ddr-detail-value">{{ $report->bus?->bus_no ?: '—' }}</span>
                            @if($report->bus)
                                <small class="ddr-detail-sub">{{ $report->bus->plate_no }}</small>
                            @endif
                        </div>

                        <div class="ddr-detail-item">
                            <span class="ddr-detail-label">From</span>
                            <span class="ddr-detail-value">{{ $report->from_location }}</span>
                        </div>

                        <div class="ddr-detail-item">
                            <span class="ddr-detail-label">To</span>
                            <span class="ddr-detail-value">{{ $report->to_location }}</span>
                        </div>

                        <div class="ddr-detail-item">
                            <span class="ddr-detail-label">Departure Time</span>
                            <span class="ddr-detail-value">{{ $report->departure_time ? $report->departure_time->format('H:i') : '—' }}</span>
                        </div>

                        <div class="ddr-detail-item">
                            <span class="ddr-detail-label">Arrival Time</span>
                            <span class="ddr-detail-value">{{ $report->arrival_time ? $report->arrival_time->format('H:i') : '—' }}</span>
                        </div>

                        <div class="ddr-detail-item full">
                            <span class="ddr-detail-label">Passengers</span>
                            <span class="ddr-detail-value">{{ number_format($report->passengers) }} pax</span>
                        </div>

                        <div class="ddr-detail-item full">
                            <span class="ddr-detail-label">Encoded By</span>
                            <span class="ddr-detail-value">{{ $report->encoder?->name ?: '—' }}</span>
                            @if($report->created_at)
                                <small class="ddr-detail-sub">{{ $report->created_at->format('M d, Y g:i A') }}</small>
                            @endif
                        </div>
                    </div>
                </section>

                <!-- Schedule Comparison -->
                <section class="ddr-card">
                    <div class="ddr-card-header">
                        <div>
                            <h2>Schedule Comparison</h2>
                            <p>Derived from the DDR's actual times against the matched scheduled trip.</p>
                        </div>

                        @if($comparison['matched'])
                            @php
                                $statusClass = $comparison['status'] === 'Delayed'
                                    ? 'delayed'
                                    : 'on-time';
                            @endphp
                            <span class="ddr-status {{ $statusClass }}">
                                {{ $comparison['status'] }}
                            </span>
                        @else
                            <span class="ddr-status unavailable">
                                Unavailable
                            </span>
                        @endif
                    </div>

                    @if($comparison['matched'])
                        <div class="ddr-sched-banner">
                            <i class="fa-solid fa-link"></i>
                            <div>
                                <strong><x-ui.id-badge :value="$comparison['trip_code']" /></strong>
                                <span>{{ $comparison['route_label'] }} &bull; {{ $comparison['shift'] }} shift</span>
                            </div>
                        </div>

                        <div class="ddr-compare-grid">
                            <div class="ddr-detail-item">
                                <span class="ddr-detail-label">Scheduled Departure</span>
                                <span class="ddr-detail-value">{{ $comparison['scheduled_departure'] }}</span>
                            </div>

                            <div class="ddr-detail-item">
                                <span class="ddr-detail-label">Actual Departure</span>
                                <span class="ddr-detail-value">{{ $comparison['actual_departure'] }}</span>
                                @if($comparison['departure_delay_minutes'] > 0)
                                    <small class="ddr-delay-negative">
                                        Late by {{ $comparison['departure_delay_minutes'] }} min
                                    </small>
                                @elseif($comparison['matched'])
                                    <small class="ddr-delay-clean">On schedule</small>
                                @endif
                            </div>

                            <div class="ddr-detail-item">
                                <span class="ddr-detail-label">Scheduled Arrival (ETA)</span>
                                <span class="ddr-detail-value">{{ $comparison['scheduled_arrival'] }}</span>
                            </div>

                            <div class="ddr-detail-item">
                                <span class="ddr-detail-label">Actual Arrival</span>
                                <span class="ddr-detail-value">{{ $comparison['actual_arrival'] }}</span>
                                @if($comparison['arrival_delay_minutes'] > 0)
                                    <small class="ddr-delay-negative">
                                        Late by {{ $comparison['arrival_delay_minutes'] }} min
                                    </small>
                                @elseif($comparison['matched'])
                                    <small class="ddr-delay-clean">On schedule</small>
                                @endif
                            </div>
                        </div>

                        <div class="ddr-form-note ddr-compute-note">
                            <i class="fa-solid fa-square-root-variable"></i>
                            <div>
                                <strong>Computed delay</strong>
                                <span>The status above compares the DDR's actual times against the real scheduled trip. It is a derivation for review only and does not change any stored report values.</span>
                            </div>
                        </div>
                    @else
                        <div class="ddr-no-match">
                            <i class="fa-solid fa-file-circle-question"></i>
                            <div>
                                <strong>Schedule match unavailable</strong>
                                <span>No trip is scheduled for this driver and bus on {{ $report->report_date ? $report->report_date->format('M d, Y') : 'the report date' }}. No delay can be computed without a real scheduled trip.</span>
                            </div>
                        </div>
                    @endif
                </section>
            </div>

            <div class="ddr-form-actions ddr-back-actions">
                <a href="{{ route('daily-driver-reports') }}" class="ddr-secondary-btn">
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to List
                </a>
                <a href="{{ route('daily-driver-reports.create') }}" class="ddr-primary-btn">
                    <i class="fa-solid fa-plus"></i>
                    Encode New Report
                </a>
            </div>
        </main>
    </div>
</x-layout.app>