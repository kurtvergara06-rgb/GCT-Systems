<x-layout.app
    title="FROMS - Analytics"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/analytics.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Admin/analytics.js'
    ]"
>

<div class="app">

    {{-- =========================================================
         SIDEBAR
    ========================================================== --}}
    <x-layout.sidebar
        department="Admin"
        subtitle="Admin Module"
        icon="fa-bus"
        :items="[
            [
                'label' => 'Dashboard',
                'route' => 'admin.dashboard',
                'icon' => 'fa-table-cells-large'
            ],
            [
                'label' => 'User Management',
                'route' => 'admin.users',
                'icon' => 'fa-users'
            ],
            [
                'label' => 'Permissions',
                'route' => 'admin.permissions',
                'icon' => 'fa-lock'
            ],
            [
                'label' => 'Batch File Processing',
                'route' => 'batch-file-processing',
                'icon' => 'fa-file-arrow-up'
            ],
            [
                'label' => 'Analytics',
                'route' => 'analytics',
                'icon' => 'fa-chart-column'
            ],
        ]"
    />

    <main class="main analytics-page">

        {{-- =====================================================
             TOPBAR
        ====================================================== --}}
        <x-layout.topbar
            title="Analytics"
            subtitle="Fleet and maintenance insights, forecasts, and operational recommendations"
            notification-count="4"
        />


        {{-- =====================================================
             TOP DATA HEALTH
        ====================================================== --}}
        <section class="analytics-health-grid">

            <article class="health-card">
                <div class="health-icon blue">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>

                <div>
                    <span>Latest GPS Data</span>
                    <h3>Jul 20, 2026</h3>
                    <small>02:09 AM</small>
                    <p>Latest processed record</p>
                </div>
            </article>


            <article class="health-card">
                <div class="health-icon purple">
                    <i class="fa-solid fa-database"></i>
                </div>

                <div>
                    <span>Historical Records</span>
                    <h3>2,420</h3>
                    <p>Structured GPS records</p>
                </div>
            </article>


            <article class="health-card">
                <div class="health-icon green">
                    <i class="fa-solid fa-bus"></i>
                </div>

                <div>
                    <span>Bus Coverage</span>
                    <h3>16 / 18</h3>
                    <p>Vehicles with recent data</p>
                </div>
            </article>


            <article class="health-card">
                <div class="health-icon green">
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <div>
                    <span>Data Health</span>
                    <h3 class="text-green">FRESH</h3>
                    <p>Updated 3 days ago</p>
                </div>
            </article>


            <article class="health-card health-wide">
                <div class="health-icon blue">
                    <i class="fa-solid fa-circle-info"></i>
                </div>

                <div class="health-wide-content">
                    <span>GPS Dataset is Up to Date</span>

                    <p>
                        Latest fleet records are 3 days old and suitable
                        for short-term forecasting.
                    </p>
                </div>

                <span class="fresh-badge">Fresh</span>
            </article>

        </section>


        {{-- =====================================================
             ANALYTICS TYPE
        ====================================================== --}}
        <nav class="analytics-tabs">

            <button
                type="button"
                class="analytics-tab"
                data-analytics-tab="descriptive"
            >
                Descriptive
            </button>

            <button
                type="button"
                class="analytics-tab"
                data-analytics-tab="diagnostic"
            >
                Diagnostic
            </button>

            <button
                type="button"
                class="analytics-tab active"
                data-analytics-tab="predictive"
            >
                Predictive
            </button>

            <button
                type="button"
                class="analytics-tab"
                data-analytics-tab="prescriptive"
            >
                Prescriptive
            </button>

        </nav>


        {{-- =====================================================
             SYSTEM FORECAST OVERVIEW
        ====================================================== --}}
        <section class="analytics-section-card">

            <div class="analytics-section-heading">
                <div>
                    <span class="eyebrow">SYSTEM FORECAST OVERVIEW</span>

                    <h2>Predictive Analytics</h2>

                    <p>
                        Forecasts generated from GPS, maintenance, fuel,
                        inventory, purchase, and operational records.
                    </p>
                </div>
            </div>


            <div class="system-forecast-grid">

                <article class="forecast-kpi blue">
                    <div class="forecast-kpi-icon">
                        <i class="fa-solid fa-route"></i>
                    </div>

                    <div>
                        <span>Predicted Fleet Distance</span>
                        <h3 id="predictedDistance">8,720 KM</h3>
                        <small>Next 7 days</small>

                        <p class="positive">
                            <i class="fa-solid fa-arrow-up"></i>
                            4.2% vs recent average
                        </p>
                    </div>
                </article>


                <article class="forecast-kpi green">
                    <div class="forecast-kpi-icon">
                        <i class="fa-solid fa-gas-pump"></i>
                    </div>

                    <div>
                        <span>Predicted Fuel Need</span>
                        <h3>1,240 L / day</h3>
                        <small>8,720 L / week</small>
                        <p>35,400 L / month</p>
                    </div>
                </article>


                <article class="forecast-kpi purple">
                    <div class="forecast-kpi-icon">
                        <i class="fa-solid fa-wrench"></i>
                    </div>

                    <div>
                        <span>PMS Due Soon</span>
                        <h3>3 Buses</h3>
                        <small>Within next 30 days</small>
                    </div>
                </article>


                <article class="forecast-kpi yellow">
                    <div class="forecast-kpi-icon">
                        <i class="fa-solid fa-box"></i>
                    </div>

                    <div>
                        <span>Parts Stockout Risk</span>
                        <h3>5 Items</h3>
                        <small>High risk of shortage</small>
                    </div>
                </article>


                <article class="forecast-kpi red">
                    <div class="forecast-kpi-icon">
                        <i class="fa-solid fa-clipboard-list"></i>
                    </div>

                    <div>
                        <span>Predicted Job Orders</span>
                        <h3>12</h3>
                        <small>Next 7 days</small>
                    </div>
                </article>

            </div>

        </section>


        {{-- =====================================================
             FORECAST CONTROLS
        ====================================================== --}}
        <section class="forecast-controls-card">

            <div>
                <h3>Forecast Controls</h3>

                <p>
                    Adjust forecast period and vehicle scope.
                </p>
            </div>


            <div class="forecast-control-actions">

                <div class="period-selector">
                    <button
                        type="button"
                        class="period-btn"
                        data-period="daily"
                    >
                        Daily
                    </button>

                    <button
                        type="button"
                        class="period-btn active"
                        data-period="weekly"
                    >
                        Weekly
                    </button>

                    <button
                        type="button"
                        class="period-btn"
                        data-period="monthly"
                    >
                        Monthly
                    </button>
                </div>


                <select class="analytics-select">
                    <option>All Vehicles</option>
                    <option>ABC-1234</option>
                    <option>DEF-5678</option>
                    <option>GHI-9012</option>
                    <option>MNO-7890</option>
                </select>


                <button type="button" class="analytics-outline-btn">
                    <i class="fa-solid fa-download"></i>
                    Export Report
                </button>

            </div>

        </section>


        {{-- =====================================================
             PRIMARY FORECAST CHARTS
        ====================================================== --}}
        <section class="three-chart-grid">

            <article class="analytics-chart-card">
                <div class="card-heading">
                    <h3>Fleet Mileage Forecast</h3>
                    <p>Historical vs forecast fleet distance</p>
                </div>

                <div class="chart-container">
                    <canvas id="fleetMileageChart"></canvas>
                </div>
            </article>


            <article class="analytics-chart-card">
                <div class="card-heading">
                    <h3>Fuel Consumption Forecast</h3>
                    <p>Expected fuel usage</p>
                </div>

                <div class="static-line-chart">
                    <svg
                        viewBox="0 0 600 250"
                        preserveAspectRatio="none"
                    >
                        <polyline
                            class="chart-grid-line"
                            points="0,210 600,210"
                        />

                        <polyline
                            class="chart-grid-line"
                            points="0,160 600,160"
                        />

                        <polyline
                            class="chart-grid-line"
                            points="0,110 600,110"
                        />

                        <polyline
                            class="chart-grid-line"
                            points="0,60 600,60"
                        />

                        <polyline
                            class="history-line"
                            points="
                                10,190
                                70,150
                                130,110
                                190,160
                                250,135
                                310,70
                                370,115
                                430,90
                            "
                        />

                        <polyline
                            class="forecast-line"
                            points="
                                430,90
                                490,78
                                540,58
                                590,30
                            "
                        />
                    </svg>

                    <div class="chart-legend">
                        <span>
                            <i class="legend-blue"></i>
                            Historical
                        </span>

                        <span>
                            <i class="legend-red"></i>
                            Forecast
                        </span>
                    </div>
                </div>
            </article>


            <article class="analytics-chart-card">
                <div class="card-heading">
                    <h3>Predicted Job Orders</h3>
                    <p>Expected maintenance workload</p>
                </div>

                <div class="static-line-chart">
                    <svg
                        viewBox="0 0 600 250"
                        preserveAspectRatio="none"
                    >
                        <polyline
                            class="chart-grid-line"
                            points="0,210 600,210"
                        />

                        <polyline
                            class="chart-grid-line"
                            points="0,160 600,160"
                        />

                        <polyline
                            class="chart-grid-line"
                            points="0,110 600,110"
                        />

                        <polyline
                            class="chart-grid-line"
                            points="0,60 600,60"
                        />

                        <polyline
                            class="history-line"
                            points="
                                10,185
                                70,135
                                130,105
                                190,145
                                250,100
                                310,75
                                370,110
                                430,70
                            "
                        />

                        <polyline
                            class="forecast-line"
                            points="
                                430,70
                                490,70
                                540,55
                                590,30
                            "
                        />
                    </svg>

                    <div class="chart-legend">
                        <span>
                            <i class="legend-blue"></i>
                            Historical
                        </span>

                        <span>
                            <i class="legend-red"></i>
                            Forecast
                        </span>
                    </div>
                </div>
            </article>

        </section>


        {{-- =====================================================
             FORECAST DETAIL TABLES
        ====================================================== --}}
        <section class="three-table-grid">

            {{-- VEHICLE FORECAST --}}
            <article class="analytics-table-card">

                <div class="card-heading">
                    <h3>Vehicle Forecast Details</h3>
                    <p>Next 7 days</p>
                </div>

                <div class="table-scroll">
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>Vehicle</th>
                                <th>Current Avg KM/Day</th>
                                <th>Predicted Daily KM</th>
                                <th>Predicted Weekly KM</th>
                                <th>Predicted Monthly KM</th>
                                <th>Trend</th>
                                <th>Confidence</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td>ABC-1234</td>
                                <td>122</td>
                                <td>135</td>
                                <td>890</td>
                                <td>3,650</td>
                                <td>
                                    <span class="trend-up">
                                        ↑ Increasing
                                    </span>
                                </td>
                                <td>
                                    <span class="status-pill green">
                                        High
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <td>DEF-5678</td>
                                <td>105</td>
                                <td>108</td>
                                <td>740</td>
                                <td>3,050</td>
                                <td>
                                    <span class="trend-stable">
                                        → Stable
                                    </span>
                                </td>
                                <td>
                                    <span class="status-pill yellow">
                                        Medium
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <td>GHI-9012</td>
                                <td>89</td>
                                <td>92</td>
                                <td>620</td>
                                <td>2,480</td>
                                <td>
                                    <span class="trend-down">
                                        ↓ Slight decrease
                                    </span>
                                </td>
                                <td>
                                    <span class="status-pill green">
                                        High
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <td>MNO-7890</td>
                                <td>77</td>
                                <td>85</td>
                                <td>590</td>
                                <td>2,390</td>
                                <td>
                                    <span class="trend-up">
                                        ↑ Increasing
                                    </span>
                                </td>
                                <td>
                                    <span class="status-pill yellow">
                                        Medium
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <button class="table-link">
                    View all vehicles
                    <i class="fa-solid fa-arrow-right"></i>
                </button>

            </article>


            {{-- PMS --}}
            <article class="analytics-table-card">

                <div class="card-heading">
                    <h3>PMS Due Soon</h3>
                    <p>Predicted preventive maintenance dates</p>
                </div>

                <div class="table-scroll">
                    <table class="analytics-table">

                        <thead>
                            <tr>
                                <th>Vehicle</th>
                                <th>Current Mileage</th>
                                <th>PMS Threshold</th>
                                <th>Predicted Due</th>
                                <th>Days Left</th>
                                <th>Priority</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td>ABC-1234</td>
                                <td>18,450</td>
                                <td>20,000</td>
                                <td>Aug 05, 2026</td>
                                <td>16</td>
                                <td>
                                    <span class="status-pill red">
                                        High
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <td>DEF-5678</td>
                                <td>27,600</td>
                                <td>30,000</td>
                                <td>Aug 15, 2026</td>
                                <td>26</td>
                                <td>
                                    <span class="status-pill yellow">
                                        Medium
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <td>GHI-9012</td>
                                <td>14,200</td>
                                <td>20,000</td>
                                <td>Aug 28, 2026</td>
                                <td>39</td>
                                <td>
                                    <span class="status-pill yellow">
                                        Medium
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <td>MNO-7890</td>
                                <td>19,100</td>
                                <td>25,000</td>
                                <td>Sep 05, 2026</td>
                                <td>47</td>
                                <td>
                                    <span class="status-pill green">
                                        Low
                                    </span>
                                </td>
                            </tr>
                        </tbody>

                    </table>
                </div>

                <button class="table-link">
                    View all PMS schedules
                    <i class="fa-solid fa-arrow-right"></i>
                </button>

            </article>


            {{-- INVENTORY --}}
            <article class="analytics-table-card">

                <div class="card-heading">
                    <h3>Parts Stockout Risk</h3>
                    <p>Predicted inventory demand</p>
                </div>

                <div class="table-scroll">
                    <table class="analytics-table">

                        <thead>
                            <tr>
                                <th>Part</th>
                                <th>Current Stock</th>
                                <th>Predicted Demand</th>
                                <th>Estimated Shortage</th>
                                <th>Risk</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td>Brake Pads</td>
                                <td>18 pcs</td>
                                <td>30 pcs</td>
                                <td>12 pcs</td>
                                <td>
                                    <span class="status-pill red">
                                        High
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <td>Engine Oil</td>
                                <td>42 L</td>
                                <td>35 L</td>
                                <td>—</td>
                                <td>
                                    <span class="status-pill green">
                                        Low
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <td>Oil Filter</td>
                                <td>24 pcs</td>
                                <td>36 pcs</td>
                                <td>12 pcs</td>
                                <td>
                                    <span class="status-pill red">
                                        High
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <td>Coolant</td>
                                <td>15 L</td>
                                <td>22 L</td>
                                <td>7 L</td>
                                <td>
                                    <span class="status-pill yellow">
                                        Medium
                                    </span>
                                </td>
                            </tr>
                        </tbody>

                    </table>
                </div>

                <button class="table-link">
                    View inventory forecast
                    <i class="fa-solid fa-arrow-right"></i>
                </button>

            </article>

        </section>


        {{-- =====================================================
             FUEL ANALYTICS
        ====================================================== --}}
        <section class="analytics-split-grid">

            <article class="analytics-table-card">

                <div class="card-heading">
                    <h3>Fuel Requirement Forecast</h3>
                    <p>Expected fleet fuel requirement</p>
                </div>

                <div class="table-scroll">
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Total Fleet</th>
                                <th>Change vs Previous</th>
                                <th>Notes</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td>Daily</td>
                                <td>1,240 L</td>
                                <td>
                                    <span class="trend-up">
                                        ↑ 4.1%
                                    </span>
                                </td>
                                <td>Based on 7-day forecast</td>
                            </tr>

                            <tr>
                                <td>Weekly</td>
                                <td>8,720 L</td>
                                <td>
                                    <span class="trend-up">
                                        ↑ 4.2%
                                    </span>
                                </td>
                                <td>Based on 7-day forecast</td>
                            </tr>

                            <tr>
                                <td>Monthly</td>
                                <td>35,400 L</td>
                                <td>
                                    <span class="trend-up">
                                        ↑ 3.9%
                                    </span>
                                </td>
                                <td>Based on 30-day forecast</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </article>


            <article class="analytics-chart-card">

                <div class="card-heading">
                    <h3>Fuel Consumption by Vehicle</h3>
                    <p>Predicted daily fuel usage</p>
                </div>

                <div class="vehicle-bars">

                    @php
                        $fuelVehicles = [
                            ['bus' => 'ABC-1234', 'value' => 135],
                            ['bus' => 'DEF-5678', 'value' => 108],
                            ['bus' => 'GHI-9012', 'value' => 92],
                            ['bus' => 'MNO-7890', 'value' => 85],
                            ['bus' => 'XYZ-3456', 'value' => 96],
                            ['bus' => 'AAA-1001', 'value' => 110],
                            ['bus' => 'BBB-2002', 'value' => 75],
                            ['bus' => 'CCC-3003', 'value' => 82],
                            ['bus' => 'DDD-4044', 'value' => 68],
                            ['bus' => 'EEE-5005', 'value' => 120],
                            ['bus' => 'FFF-6006', 'value' => 88],
                            ['bus' => 'GGG-7007', 'value' => 95],
                        ];
                    @endphp

                    @foreach($fuelVehicles as $vehicle)
                        <div class="vehicle-bar-item">

                            <span class="vehicle-bar-value">
                                {{ $vehicle['value'] }}
                            </span>

                            <div
                                class="vehicle-bar"
                                style="height: {{ max(35, $vehicle['value']) }}px;"
                            ></div>

                            <span class="vehicle-bar-label">
                                {{ $vehicle['bus'] }}
                            </span>

                        </div>
                    @endforeach

                </div>

            </article>

        </section>


        {{-- =====================================================
             MAINTENANCE WORKLOAD
        ====================================================== --}}
        <section class="three-panel-grid">

            <article class="analytics-panel">

                <div class="card-heading">
                    <h3>Predicted Job Orders</h3>
                    <p>Expected number of jobs in the next 7 days</p>
                </div>

                <div class="donut-layout">

                    <div class="donut-chart">
                        <div class="donut-center">
                            <strong>12</strong>
                            <span>Total</span>
                        </div>
                    </div>

                    <div class="donut-legend">

                        <div>
                            <i class="dot blue"></i>
                            <span>Repair</span>
                            <strong>7</strong>
                        </div>

                        <div>
                            <i class="dot purple"></i>
                            <span>PMS</span>
                            <strong>4</strong>
                        </div>

                        <div>
                            <i class="dot red"></i>
                            <span>Urgent Repair</span>
                            <strong>1</strong>
                        </div>

                    </div>

                </div>

            </article>


            <article class="analytics-panel">

                <div class="card-heading">
                    <h3>Maintenance Workload Forecast</h3>
                    <p>Predicted workload vs mechanic capacity</p>
                </div>

                <div class="workload-chart">

                    <div class="workload-line predicted"></div>
                    <div class="workload-line capacity"></div>

                    <div class="workload-labels">
                        <span>Jul 21</span>
                        <span>Jul 22</span>
                        <span>Jul 23</span>
                        <span>Jul 24</span>
                        <span>Jul 25</span>
                        <span>Jul 26</span>
                        <span>Jul 27</span>
                    </div>

                </div>

                <div class="mini-chart-legend">
                    <span>
                        <i class="legend-blue"></i>
                        Predicted Job Orders
                    </span>

                    <span>
                        <i class="legend-green"></i>
                        Available Capacity
                    </span>
                </div>

            </article>


            <article class="analytics-panel">

                <div class="card-heading">
                    <h3>Mechanic Capacity Forecast</h3>
                    <p>Next 7 days</p>
                </div>

                <div class="capacity-list">

                    <div>
                        <span>Expected Active Jobs</span>
                        <strong>12</strong>
                    </div>

                    <div>
                        <span>Available Mechanics</span>
                        <strong>9</strong>
                    </div>

                    <div>
                        <span>Capacity Utilization</span>
                        <strong>83%</strong>
                    </div>

                    <div>
                        <span>Potential Shortage</span>
                        <strong>3 jobs</strong>
                    </div>

                </div>

                <div class="capacity-progress">
                    <div style="width: 83%;"></div>
                </div>

                <div class="capacity-warning">
                    <i class="fa-solid fa-triangle-exclamation"></i>

                    Consider overtime or reassigning mechanic workload.
                </div>

            </article>

        </section>


        {{-- =====================================================
             BATCH / PERIOD COMPARISON
        ====================================================== --}}
        <section class="three-table-grid">

            <article class="analytics-table-card">

                <div class="card-heading">
                    <h3>Recent GPS Data Uploads</h3>
                    <p>Historical Batch File Processing activity</p>
                </div>

                <div class="table-scroll">
                    <table class="analytics-table">

                        <thead>
                            <tr>
                                <th>Upload Date</th>
                                <th>File Name</th>
                                <th>Records</th>
                                <th>Vehicles Covered</th>
                                <th>Latest Record</th>
                                <th>Quality</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td>Jul 20, 2026</td>
                                <td>gps_july20.csv</td>
                                <td>320</td>
                                <td>16 / 18</td>
                                <td>Jul 20</td>
                                <td>89%</td>
                                <td>
                                    <span class="status-pill green">
                                        Processed
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <td>Jul 13, 2026</td>
                                <td>gps_july13.csv</td>
                                <td>298</td>
                                <td>18 / 18</td>
                                <td>Jul 13</td>
                                <td>96%</td>
                                <td>
                                    <span class="status-pill green">
                                        Processed
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <td>Jul 06, 2026</td>
                                <td>gps_july06.csv</td>
                                <td>301</td>
                                <td>17 / 18</td>
                                <td>Jul 06</td>
                                <td>92%</td>
                                <td>
                                    <span class="status-pill green">
                                        Processed
                                    </span>
                                </td>
                            </tr>
                        </tbody>

                    </table>
                </div>

            </article>


            <article class="analytics-table-card">

                <div class="card-heading">
                    <h3>Vehicles Requiring Updated GPS Data</h3>
                    <p>Data freshness monitoring</p>
                </div>

                <div class="table-scroll">
                    <table class="analytics-table">

                        <thead>
                            <tr>
                                <th>Vehicle</th>
                                <th>Latest GPS Record</th>
                                <th>Data Age</th>
                                <th>Coverage Status</th>
                                <th>Recommended Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td>MNO-7890</td>
                                <td>Jul 10, 2026</td>
                                <td>13 days</td>
                                <td>
                                    <span class="status-pill red">
                                        Outdated
                                    </span>
                                </td>
                                <td>Include in next upload</td>
                            </tr>

                            <tr>
                                <td>AAA-1001</td>
                                <td>Jul 11, 2026</td>
                                <td>12 days</td>
                                <td>
                                    <span class="status-pill red">
                                        Outdated
                                    </span>
                                </td>
                                <td>Upload newer data</td>
                            </tr>

                            <tr>
                                <td>EEE-5005</td>
                                <td>Jul 12, 2026</td>
                                <td>11 days</td>
                                <td>
                                    <span class="status-pill yellow">
                                        Update Recommended
                                    </span>
                                </td>
                                <td>Upload newer data</td>
                            </tr>
                        </tbody>

                    </table>
                </div>

            </article>


            <article class="analytics-table-card">

                <div class="card-heading">
                    <h3>Period Comparison</h3>
                    <p>Current vs previous period</p>
                </div>

                <div class="table-scroll">
                    <table class="analytics-table">

                        <thead>
                            <tr>
                                <th>Metric</th>
                                <th>Previous</th>
                                <th>Current</th>
                                <th>Change</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td>Fleet Distance</td>
                                <td>7,820 KM</td>
                                <td>8,320 KM</td>
                                <td>
                                    <span class="trend-up">
                                        ↑ 6.4%
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <td>Average / Bus</td>
                                <td>489 KM</td>
                                <td>520 KM</td>
                                <td>
                                    <span class="trend-up">
                                        ↑ 6.3%
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <td>Fuel Consumption</td>
                                <td>1,050 L</td>
                                <td>1,180 L</td>
                                <td>
                                    <span class="trend-up">
                                        ↑ 12.4%
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <td>Job Orders</td>
                                <td>18</td>
                                <td>23</td>
                                <td>
                                    <span class="trend-up">
                                        ↑ 27.8%
                                    </span>
                                </td>
                            </tr>
                        </tbody>

                    </table>
                </div>

            </article>

        </section>


        {{-- =====================================================
             VEHICLE HEALTH
        ====================================================== --}}
        <section class="analytics-table-card full-width-table">

            <div class="card-heading">
                <h3>Vehicle Health & Maintenance Risk</h3>

                <p>
                    Health score calculated from mileage trend,
                    fuel efficiency, repair history, and PMS status.
                </p>
            </div>

            <div class="table-scroll">
                <table class="analytics-table">

                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Health Score</th>
                            <th>Mileage Trend</th>
                            <th>Fuel Efficiency</th>
                            <th>Recent Repairs</th>
                            <th>PMS Status</th>
                            <th>Risk Score</th>
                            <th>Risk Level</th>
                            <th>Expected Issue Window</th>
                            <th>Recommended Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td>ABC-1234</td>
                            <td><strong>72%</strong></td>
                            <td><span class="trend-up">↑ High</span></td>
                            <td><span class="trend-down">↓ Declining</span></td>
                            <td>3</td>
                            <td>
                                <span class="status-pill yellow">
                                    Due Soon
                                </span>
                            </td>
                            <td>82 / 100</td>
                            <td>
                                <span class="status-pill red">
                                    High
                                </span>
                            </td>
                            <td>Within 14 days</td>
                            <td>Schedule PMS</td>
                        </tr>

                        <tr>
                            <td>DEF-5678</td>
                            <td><strong>58%</strong></td>
                            <td><span class="trend-up">↑ Medium</span></td>
                            <td><span class="trend-stable">→ Stable</span></td>
                            <td>1</td>
                            <td>
                                <span class="status-pill green">
                                    Normal
                                </span>
                            </td>
                            <td>48 / 100</td>
                            <td>
                                <span class="status-pill yellow">
                                    Medium
                                </span>
                            </td>
                            <td>Within 30 days</td>
                            <td>Monitor</td>
                        </tr>

                        <tr>
                            <td>GHI-9012</td>
                            <td><strong>78%</strong></td>
                            <td><span class="trend-stable">→ Low</span></td>
                            <td><span class="trend-up">↑ Improving</span></td>
                            <td>0</td>
                            <td>
                                <span class="status-pill green">
                                    Normal
                                </span>
                            </td>
                            <td>28 / 100</td>
                            <td>
                                <span class="status-pill green">
                                    Low
                                </span>
                            </td>
                            <td>Within 45 days</td>
                            <td>Continue Monitoring</td>
                        </tr>

                        <tr>
                            <td>MNO-7890</td>
                            <td><strong>45%</strong></td>
                            <td><span class="trend-up">↑ High</span></td>
                            <td><span class="trend-down">↓ Declining</span></td>
                            <td>4</td>
                            <td>
                                <span class="status-pill red">
                                    Overdue
                                </span>
                            </td>
                            <td>91 / 100</td>
                            <td>
                                <span class="status-pill red">
                                    Critical
                                </span>
                            </td>
                            <td>Within 7 days</td>
                            <td>Immediate Inspection</td>
                        </tr>

                    </tbody>

                </table>
            </div>

        </section>


        {{-- =====================================================
             PURCHASE + RECOMMENDATION HISTORY
        ====================================================== --}}
        <section class="analytics-split-grid">

            <article class="analytics-table-card">

                <div class="card-heading">
                    <h3>Expected Purchase Requirements</h3>
                    <p>Next 30 days</p>
                </div>

                <div class="table-scroll">
                    <table class="analytics-table">

                        <thead>
                            <tr>
                                <th>Item Category</th>
                                <th>Predicted Demand</th>
                                <th>Current Stock</th>
                                <th>Estimated Shortage</th>
                                <th>Priority</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td>Brake Pads</td>
                                <td>30 pcs</td>
                                <td>18 pcs</td>
                                <td>12 pcs</td>
                                <td>
                                    <span class="status-pill red">
                                        High
                                    </span>
                                </td>
                                <td>Create PR</td>
                            </tr>

                            <tr>
                                <td>Engine Oil</td>
                                <td>35 L</td>
                                <td>42 L</td>
                                <td>—</td>
                                <td>
                                    <span class="status-pill green">
                                        Low
                                    </span>
                                </td>
                                <td>Monitor</td>
                            </tr>

                            <tr>
                                <td>Oil Filters</td>
                                <td>36 pcs</td>
                                <td>24 pcs</td>
                                <td>12 pcs</td>
                                <td>
                                    <span class="status-pill red">
                                        High
                                    </span>
                                </td>
                                <td>Create PR</td>
                            </tr>

                            <tr>
                                <td>Coolant</td>
                                <td>22 L</td>
                                <td>15 L</td>
                                <td>7 L</td>
                                <td>
                                    <span class="status-pill yellow">
                                        Medium
                                    </span>
                                </td>
                                <td>Prepare PR</td>
                            </tr>
                        </tbody>

                    </table>
                </div>

            </article>


            <article class="analytics-table-card">

                <div class="card-heading">
                    <h3>Recommendation History</h3>
                    <p>System-generated operational recommendations</p>
                </div>

                <div class="table-scroll">
                    <table class="analytics-table">

                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Recommendation</th>
                                <th>Related Module</th>
                                <th>Priority</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td>Jul 20, 2026</td>
                                <td>Schedule PMS for ABC-1234 within 14 days</td>
                                <td>Maintenance</td>
                                <td>
                                    <span class="status-pill red">
                                        High
                                    </span>
                                </td>
                                <td>
                                    <span class="status-pill yellow">
                                        Pending
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <td>Jul 19, 2026</td>
                                <td>Upload missing GPS data for 2 buses</td>
                                <td>Batch File Processing</td>
                                <td>
                                    <span class="status-pill yellow">
                                        Medium
                                    </span>
                                </td>
                                <td>
                                    <span class="status-pill yellow">
                                        Pending
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <td>Jul 18, 2026</td>
                                <td>Restock Brake Pads</td>
                                <td>Inventory</td>
                                <td>
                                    <span class="status-pill red">
                                        High
                                    </span>
                                </td>
                                <td>
                                    <span class="status-pill blue">
                                        Acknowledged
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <td>Jul 17, 2026</td>
                                <td>Monitor high fuel consumption vehicles</td>
                                <td>Fuel</td>
                                <td>
                                    <span class="status-pill yellow">
                                        Medium
                                    </span>
                                </td>
                                <td>
                                    <span class="status-pill blue">
                                        In Progress
                                    </span>
                                </td>
                            </tr>
                        </tbody>

                    </table>
                </div>

            </article>

        </section>


        {{-- =====================================================
             PREDICTION RELIABILITY
        ====================================================== --}}
        <section class="reliability-section">

            <div class="reliability-heading">

                <div class="reliability-icon">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>

                <div>
                    <h3>Prediction Reliability</h3>

                    <p>
                        Confidence of current predictions based on
                        historical records and source-data freshness.
                    </p>
                </div>

            </div>


            <div class="reliability-grid">

                <div>
                    <span>Forecast Confidence</span>
                    <strong class="text-green">HIGH</strong>
                </div>

                <div>
                    <span>Historical Records</span>
                    <strong>2,420</strong>
                </div>

                <div>
                    <span>Latest GPS Data</span>
                    <strong>3 days ago</strong>
                </div>

                <div>
                    <span>Bus Coverage</span>
                    <strong>89%</strong>
                </div>

                <div>
                    <span>Missing Buses</span>
                    <strong>2</strong>
                </div>

            </div>


            <div class="readiness-row">

                <span>Data Readiness</span>
                <strong>89%</strong>

            </div>

            <div class="readiness-track">
                <div style="width: 89%;"></div>
            </div>


            <p class="reliability-note">
                <i class="fa-solid fa-circle-check"></i>
                Current dataset is suitable for short-term forecasting.
            </p>

        </section>

    </main>

</div>

</x-layout.app>