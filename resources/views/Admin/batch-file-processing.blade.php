<x-layout.app
    title="FROMS - Batch File Processing"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/batch-file-processing.css',
        'resources/js/Admin/batch-file-processing.js'
    ]"
>
    @php
        $authUser = auth()->user();

        $sidebarName = $authUser?->name ?? 'System Admin';

        $department = trim($authUser?->department ?? 'Admin');
        $role = strtolower(trim($authUser?->role ?? 'head'));

        if (strtolower($department) === 'admin') {
            $sidebarRole = $role === 'head'
                ? 'System Admin'
                : 'Admin Staff';
        } else {
            $sidebarRole = $department . ' ' . ucfirst($role ?: 'Staff');
        }
    @endphp

    <div class="app">

        <x-layout.sidebar
            department="Admin"
            subtitle="System Management"
            icon="fa-shield-halved"
            :user-name="$sidebarName"
            :user-role="$sidebarRole"
            :items="[
                [
                    'label' => 'Dashboard',
                    'route' => 'admin.dashboard',
                    'icon' => 'fa-table-cells-large'
                ],
                [
                    'label' => 'User Management',
                    'route' => 'admin.users',
                    'icon' => 'fa-users-gear'
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
            ]"
        />

        <main class="main batch-main">

            <x-layout.topbar
                title="Batch File Processing"
                subtitle="Upload GPS files and convert them into structured trip records using NLP."
                notification-count="6"
            />

            <section class="batch-top-grid">

                <div class="upload-card">
                    <div class="upload-card-header">
                        <div>
                            <h2>
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                Upload GPS Files
                            </h2>
                            <p>Upload PDF, TXT, or CSV files for extraction and processing.</p>
                        </div>
                    </div>

                    <label for="gpsFileInput" class="compact-dropzone">
                        <input
                            type="file"
                            id="gpsFileInput"
                            accept=".pdf,.txt,.csv"
                            multiple
                            hidden
                        >

                        <div class="dropzone-icon">
                            <i class="fa-solid fa-file-arrow-up"></i>
                        </div>

                        <div class="dropzone-content">
                            <strong>Drag and drop GPS files here</strong>
                            <span>or choose files from your device</span>
                        </div>

                        <span class="browse-btn">
                            <i class="fa-solid fa-folder-open"></i>
                            Choose Files
                        </span>
                    </label>

                    <div class="upload-details">
                        <span>
                            <i class="fa-solid fa-file-lines"></i>
                            PDF, TXT, CSV supported
                        </span>

                        <span>
                            <i class="fa-solid fa-hard-drive"></i>
                            Maximum file size: 50 MB
                        </span>
                    </div>
                </div>

                <div class="batch-summary-card">
                    <div class="batch-summary-header">
                        <div>
                            <h2>
                                <i class="fa-solid fa-layer-group"></i>
                                Current Batch Summary
                            </h2>
                            <p>Overview of your latest uploaded GPS files.</p>
                        </div>

                        <button type="button" class="secondary-btn processing-history-btn">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            Processing History
                        </button>
                    </div>

                    <div class="batch-summary-stats">
                        <div class="batch-stat">
                            <div class="batch-stat-icon blue">
                                <i class="fa-solid fa-file-arrow-up"></i>
                            </div>
                            <div>
                                <span>Files Uploaded</span>
                                <strong>3</strong>
                            </div>
                        </div>

                        <div class="batch-stat">
                            <div class="batch-stat-icon green">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                            <div>
                                <span>Processed</span>
                                <strong>2</strong>
                            </div>
                        </div>

                        <div class="batch-stat">
                            <div class="batch-stat-icon yellow">
                                <i class="fa-solid fa-clock"></i>
                            </div>
                            <div>
                                <span>In Review</span>
                                <strong>1</strong>
                            </div>
                        </div>

                        <div class="batch-stat">
                            <div class="batch-stat-icon navy">
                                <i class="fa-solid fa-database"></i>
                            </div>
                            <div>
                                <span>Records Extracted</span>
                                <strong>1,220</strong>
                            </div>
                        </div>
                    </div>

                    <div class="batch-last-update">
                        <i class="fa-solid fa-arrows-rotate"></i>
                        Last processed: May 8, 2026 · 06:20 AM
                    </div>
                </div>

            </section>

            <section class="processing-grid">

                <div class="panel-card uploaded-files-card">
                    <div class="panel-card-header">
                        <div>
                            <h3>
                                <i class="fa-solid fa-folder-open"></i>
                                Uploaded Files
                            </h3>
                            <p>Select a processed file to review extracted records.</p>
                        </div>
                    </div>

                    <div class="uploaded-file-list">

                        <button type="button" class="uploaded-file active-file">
                            <div class="file-icon pdf">
                                <i class="fa-solid fa-file-pdf"></i>
                            </div>

                            <div class="file-info">
                                <strong>GPS_Daily_Report_May08.pdf</strong>
                                <span>1.24 MB · May 8, 2026 06:02 AM</span>
                            </div>

                            <span class="processed-badge">Processed</span>
                        </button>

                        <button type="button" class="uploaded-file">
                            <div class="file-icon txt">
                                <i class="fa-solid fa-file-lines"></i>
                            </div>

                            <div class="file-info">
                                <strong>Route_Log_May08.txt</strong>
                                <span>512 KB · May 8, 2026 06:03 AM</span>
                            </div>

                            <span class="processed-badge">Processed</span>
                        </button>

                        <button type="button" class="uploaded-file">
                            <div class="file-icon csv">
                                <i class="fa-solid fa-file-csv"></i>
                            </div>

                            <div class="file-info">
                                <strong>Bus_Trips_May08.csv</strong>
                                <span>284 KB · May 8, 2026 06:05 AM</span>
                            </div>

                            <span class="review-badge">In Review</span>
                        </button>

                    </div>

                    <button type="button" class="view-files-btn">
                        View All Uploads
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>

                <div class="panel-card extracted-preview-card">
                    <div class="panel-card-header">
                        <div>
                            <h3>
                                <i class="fa-solid fa-file-waveform"></i>
                                Extracted Text Preview
                            </h3>
                            <p>Raw text recognized from the selected uploaded file.</p>
                        </div>

                        <div class="record-navigation">
                            <button type="button" class="record-btn">
                                <i class="fa-solid fa-chevron-left"></i>
                            </button>

                            <span>Record 1 of 1,220</span>

                            <button type="button" class="record-btn">
                                <i class="fa-solid fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>

                    <div class="text-preview">
<pre>Bus No:        BUS-001
Route:         R-01
Date:          May 8, 2026
Start Time:    06:10 AM
End Time:      07:10 AM
Distance:      12.8 km
Idle Time:     13 mins
Average Speed: 22 km/h
Notes:         Normal traffic, clear weather</pre>
                    </div>

                    <div class="confidence-row">
                        <span>
                            <i class="fa-solid fa-circle-check"></i>
                            NLP Confidence Score: <strong>98%</strong>
                        </span>

                        <small>Source: GPS_Daily_Report_May08.pdf</small>
                    </div>
                </div>

                <div class="panel-card parsed-fields-card">
                    <div class="panel-card-header">
                        <div>
                            <h3>
                                <i class="fa-solid fa-code"></i>
                                Parsed Fields
                            </h3>
                            <p>Structured data extracted from the selected record.</p>
                        </div>
                    </div>

                    <div class="parsed-fields-list">
                        <div class="parsed-field">
                            <span>Bus No.</span>
                            <strong>BUS-001</strong>
                        </div>

                        <div class="parsed-field">
                            <span>Route</span>
                            <strong>R-01</strong>
                        </div>

                        <div class="parsed-field">
                            <span>Date</span>
                            <strong>May 8, 2026</strong>
                        </div>

                        <div class="parsed-field">
                            <span>Start Time</span>
                            <strong>06:10 AM</strong>
                        </div>

                        <div class="parsed-field">
                            <span>End Time</span>
                            <strong>07:10 AM</strong>
                        </div>

                        <div class="parsed-field">
                            <span>Distance</span>
                            <strong>12.8 km</strong>
                        </div>

                        <div class="parsed-field">
                            <span>Idle Time</span>
                            <strong>13 mins</strong>
                        </div>

                        <div class="parsed-field">
                            <span>Average Speed</span>
                            <strong>22 km/h</strong>
                        </div>
                    </div>
                </div>

            </section>

            <section class="table-card structured-records-card">
                <div class="section-header">
                    <div>
                        <h2>Structured Trip Records</h2>
                        <p>Processed GPS trip records ready for analytics, reporting, and export.</p>
                    </div>

                    <div class="table-header-actions">
                        <div class="mini-search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" placeholder="Search bus, route, or date...">
                        </div>

                        <button type="button" class="secondary-btn">
                            <i class="fa-solid fa-filter"></i>
                            Filters
                        </button>

                        <button type="button" class="primary-btn export-btn">
                            <i class="fa-solid fa-file-export"></i>
                            Export CSV
                        </button>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="batch-records-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Bus No.</th>
                                <th>Route</th>
                                <th>Date</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Distance</th>
                                <th>Avg. Speed</th>
                                <th>Idle Time</th>
                                <th>Trip Duration</th>
                                <th>Severity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td>1</td>
                                <td><strong>BUS-001</strong></td>
                                <td>R-01</td>
                                <td>May 8, 2026</td>
                                <td>06:10 AM</td>
                                <td>07:10 AM</td>
                                <td>12.8 km</td>
                                <td>22 km/h</td>
                                <td>13 mins</td>
                                <td>48 mins</td>
                                <td><span class="severity-high">High</span></td>
                                <td>
                                    <button type="button" class="table-action" title="View record">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </td>
                            </tr>

                            <tr>
                                <td>2</td>
                                <td><strong>BUS-002</strong></td>
                                <td>R-02</td>
                                <td>May 8, 2026</td>
                                <td>06:10 AM</td>
                                <td>07:25 AM</td>
                                <td>28 km</td>
                                <td>35 km/h</td>
                                <td>23 mins</td>
                                <td>1 hr 11 mins</td>
                                <td><span class="severity-medium">Medium</span></td>
                                <td>
                                    <button type="button" class="table-action" title="View record">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </td>
                            </tr>

                            <tr>
                                <td>3</td>
                                <td><strong>BUS-003</strong></td>
                                <td>R-03</td>
                                <td>May 8, 2026</td>
                                <td>06:10 AM</td>
                                <td>06:55 AM</td>
                                <td>8.3 km</td>
                                <td>22 km/h</td>
                                <td>2 mins</td>
                                <td>25 mins</td>
                                <td><span class="severity-low">Low</span></td>
                                <td>
                                    <button type="button" class="table-action" title="View record">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </td>
                            </tr>

                            <tr>
                                <td>4</td>
                                <td><strong>BUS-004</strong></td>
                                <td>R-04</td>
                                <td>May 8, 2026</td>
                                <td>06:10 AM</td>
                                <td>06:50 AM</td>
                                <td>18.7 km</td>
                                <td>28 km/h</td>
                                <td>3 mins</td>
                                <td>43 mins</td>
                                <td><span class="severity-normal">Normal</span></td>
                                <td>
                                    <button type="button" class="table-action" title="View record">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="batch-table-footer">
                    <p>Showing 1 to 4 of 1,220 records</p>

                    <div class="custom-pagination">
                        <button class="page-btn disabled" type="button">Previous</button>
                        <button class="page-btn active" type="button">1</button>
                        <button class="page-btn" type="button">2</button>
                        <button class="page-btn" type="button">3</button>
                        <span class="pagination-dots">...</span>
                        <button class="page-btn" type="button">61</button>
                        <button class="page-btn" type="button">Next</button>
                    </div>
                </div>
            </section>

        </main>
    </div>
</x-layout.app>