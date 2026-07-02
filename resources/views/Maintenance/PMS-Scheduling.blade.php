<x-layout.app
    title="FROMS - PMS Scheduling"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Maintenance/pms-scheduling.css',
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
                title="PMS Scheduling"
                subtitle="Monitor preventive maintenance schedules based on processed GPS vehicle mileage data."
                notification-count="6"
            />

            @if(session('success'))
                <div class="pms-alert success">
                    <i class="fa-solid fa-circle-check"></i>
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="pms-alert error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div class="pms-alert error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fa-solid fa-file-lines"></i>
                    </div>

                    <div>
                        <p>GPS Records Today</p>
                        <h2>{{ $gpsRecordsToday }}</h2>
                        <small>Processed mileage reports</small>
                    </div>

                    <i class="fa-solid fa-chevron-right arrow"></i>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>

                    <div>
                        <p>Upcoming PMS</p>
                        <h2>{{ $upcomingCount }}</h2>
                        <small>Scheduled maintenance</small>
                    </div>

                    <i class="fa-solid fa-chevron-right arrow"></i>
                </div>

                <div class="stat-card">
                    <div class="stat-icon yellow">
                        <i class="fa-solid fa-clock"></i>
                    </div>

                    <div>
                        <p>Due Soon</p>
                        <h2>{{ $dueSoonCount }}</h2>
                        <small>Within 500 KM of PMS</small>
                    </div>

                    <i class="fa-solid fa-chevron-right arrow"></i>
                </div>

                <div class="stat-card">
                    <div class="stat-icon red">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>

                    <div>
                        <p>Overdue</p>
                        <h2>{{ $overdueCount }}</h2>
                        <small>Needs immediate action</small>
                    </div>

                    <i class="fa-solid fa-chevron-right arrow"></i>
                </div>
            </section>

            <section class="table-card pms-card">
                <div class="section-header pms-header">
                    <div>
                        <h2>Automated PMS Record</h2>
                        <p>
                            The system reads only Processed GPS mileage reports and compares Current KM against Next PMS KM.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="pms-add-btn"
                        onclick="document.getElementById('addPmsModal').classList.add('show')"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Add PMS Schedule
                    </button>
                </div>

                <form method="GET" action="{{ route('PMS-Scheduling') }}">
                    <div class="toolbar pms-toolbar">
                        <div class="search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>

                            <input
                                type="text"
                                name="search"
                                value="{{ request('search') }}"
                                placeholder="Search vehicle or status..."
                            >
                        </div>

                        <div class="filter-group">
                            <label>Status</label>

                            <select name="status" onchange="this.form.submit()">
                                <option value="All Status">All Status</option>
                                <option value="Upcoming" @selected(request('status') === 'Upcoming')>
                                    Upcoming
                                </option>
                                <option value="Due Soon" @selected(request('status') === 'Due Soon')>
                                    Due Soon
                                </option>
                                <option value="Overdue" @selected(request('status') === 'Overdue')>
                                    Overdue
                                </option>
                            </select>
                        </div>

                        <button type="submit" class="pms-filter-btn">
                            <i class="fa-solid fa-filter"></i>
                            Filter
                        </button>
                    </div>
                </form>

                <div class="status-legend">
                    <div><span class="dot green"></span>Upcoming</div>
                    <div><span class="dot yellow"></span>Due Soon</div>
                    <div><span class="dot red"></span>Overdue</div>
                </div>

                <div class="table-wrap">
                    <table class="pms-table">
                        <thead>
                            <tr>
                                <th>Vehicle ID</th>
                                <th>GPS Report Date</th>
                                <th>Current KM</th>
                                <th>KM Traveled</th>
                                <th>Last PMS KM</th>
                                <th>Next PMS KM</th>
                                <th>Maintenance Type</th>
                                <th>Recommended Date</th>
                                <th class="status-col">Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($rows as $row)
                                <tr>
                                    <td><strong>{{ $row->bus_no }}</strong></td>

                                    <td>
                                        {{ $row->gps_report_date
                                            ? \Carbon\Carbon::parse($row->gps_report_date)->format('M d, Y')
                                            : 'No processed GPS report'
                                        }}
                                    </td>

                                    <td>
                                        {{ $row->current_km !== null
                                            ? number_format($row->current_km, 2) . ' km'
                                            : '—'
                                        }}
                                    </td>

                                    <td>
                                        {{ $row->km_traveled !== null
                                            ? number_format($row->km_traveled, 2) . ' km'
                                            : '—'
                                        }}
                                    </td>

                                    <td>{{ number_format($row->last_pms_km, 2) }} km</td>
                                    <td>{{ number_format($row->next_pms_km, 2) }} km</td>
                                    <td>{{ $row->maintenance_type }}</td>

                                    <td>
                                        {{ $row->status === 'Overdue'
                                            ? 'Immediate'
                                            : ($row->recommended_date
                                                ? \Carbon\Carbon::parse($row->recommended_date)->format('M d, Y')
                                                : '—'
                                            )
                                        }}
                                    </td>

                                    <td class="status-col">
                                        <span class="badge {{ strtolower(str_replace(' ', '-', $row->status)) }}">
                                            {{ $row->status }}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="actions">
                                            @if(in_array($row->status, ['Due Soon', 'Overdue']))
                                                <a
                                                    href="{{ route('pms-schedules.create-job-order', $row->schedule) }}"
                                                    class="create-pms-jo-btn"
                                                    title="Create PMS Job Order"
                                                >
                                                    <i class="fa-solid fa-clipboard-plus"></i>
                                                </a>
                                            @endif

                                            <button
                                                type="button"
                                                class="edit"
                                                title="Edit PMS Schedule"
                                                onclick="
                                                    document.getElementById('editPmsForm').action='{{ route('pms-schedules.update', $row->schedule) }}';
                                                    document.getElementById('edit_bus_no').value='{{ $row->bus_no }}';
                                                    document.getElementById('edit_last_pms_km').value='{{ $row->last_pms_km }}';
                                                    document.getElementById('edit_pms_interval_km').value='{{ $row->pms_interval_km }}';
                                                    document.getElementById('edit_maintenance_type').value='{{ $row->maintenance_type }}';
                                                    document.getElementById('edit_recommended_date').value='{{ optional($row->recommended_date)->format('Y-m-d') }}';
                                                    document.getElementById('editPmsModal').classList.add('show');
                                                "
                                            >
                                                <i class="fa-solid fa-pen"></i>
                                            </button>

                                            <form
                                                action="{{ route('pms-schedules.destroy', $row->schedule) }}"
                                                method="POST"
                                                onsubmit="return confirm('Delete this PMS schedule?');"
                                            >
                                                @csrf
                                                @method('DELETE')

                                                <button type="submit" class="delete" title="Delete PMS Schedule">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="pms-empty-row">
                                        No PMS schedule records found. Add a bus PMS schedule first.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="pms-modal-overlay" id="addPmsModal">
                <div class="pms-modal">
                    <div class="pms-modal-header">
                        <div>
                            <h2>Add PMS Schedule</h2>
                            <p>Create a maintenance interval for a bus.</p>
                        </div>

                        <button
                            type="button"
                            class="pms-close-btn"
                            onclick="document.getElementById('addPmsModal').classList.remove('show')"
                        >
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <form action="{{ route('pms-schedules.store') }}" method="POST">
                        @csrf

                        <div class="pms-form-grid">
                            <div class="form-group">
                                <label>Vehicle ID / Bus No.</label>
                                <input type="text" name="bus_no" placeholder="Example: BUS 0001" required>
                            </div>

                            <div class="form-group">
                                <label>Last PMS KM</label>
                                <input type="number" step="0.01" min="0" name="last_pms_km" required>
                            </div>

                            <div class="form-group">
                                <label>PMS Interval KM</label>
                                <input type="number" step="0.01" min="1" name="pms_interval_km" value="5000" required>
                            </div>

                            <div class="form-group">
                                <label>Maintenance Type</label>
                                <select name="maintenance_type" required>
                                    <option value="Preventive Maintenance">Preventive Maintenance</option>
                                    <option value="Oil Change">Oil Change</option>
                                    <option value="Brake Inspection">Brake Inspection</option>
                                    <option value="Regular Check-up">Regular Check-up</option>
                                </select>
                            </div>

                            <div class="form-group full-width">
                                <label>Recommended Date</label>
                                <input type="date" name="recommended_date">
                            </div>
                        </div>

                        <div class="pms-modal-actions">
                            <button
                                type="button"
                                class="pms-cancel-btn"
                                onclick="document.getElementById('addPmsModal').classList.remove('show')"
                            >
                                Cancel
                            </button>

                            <button type="submit" class="pms-save-btn">
                                <i class="fa-solid fa-floppy-disk"></i>
                                Save PMS Schedule
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="pms-modal-overlay" id="editPmsModal">
                <div class="pms-modal">
                    <div class="pms-modal-header">
                        <div>
                            <h2>Edit PMS Schedule</h2>
                            <p>Update the maintenance interval details.</p>
                        </div>

                        <button
                            type="button"
                            class="pms-close-btn"
                            onclick="document.getElementById('editPmsModal').classList.remove('show')"
                        >
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <form id="editPmsForm" method="POST" action="">
                        @csrf
                        @method('PUT')

                        <div class="pms-form-grid">
                            <div class="form-group">
                                <label>Vehicle ID / Bus No.</label>
                                <input id="edit_bus_no" type="text" name="bus_no" required>
                            </div>

                            <div class="form-group">
                                <label>Last PMS KM</label>
                                <input id="edit_last_pms_km" type="number" step="0.01" min="0" name="last_pms_km" required>
                            </div>

                            <div class="form-group">
                                <label>PMS Interval KM</label>
                                <input id="edit_pms_interval_km" type="number" step="0.01" min="1" name="pms_interval_km" required>
                            </div>

                            <div class="form-group">
                                <label>Maintenance Type</label>
                                <select id="edit_maintenance_type" name="maintenance_type" required>
                                    <option value="Preventive Maintenance">Preventive Maintenance</option>
                                    <option value="Oil Change">Oil Change</option>
                                    <option value="Brake Inspection">Brake Inspection</option>
                                    <option value="Regular Check-up">Regular Check-up</option>
                                </select>
                            </div>

                            <div class="form-group full-width">
                                <label>Recommended Date</label>
                                <input id="edit_recommended_date" type="date" name="recommended_date">
                            </div>
                        </div>

                        <div class="pms-modal-actions">
                            <button
                                type="button"
                                class="pms-cancel-btn"
                                onclick="document.getElementById('editPmsModal').classList.remove('show')"
                            >
                                Cancel
                            </button>

                            <button type="submit" class="pms-save-btn">
                                <i class="fa-solid fa-floppy-disk"></i>
                                Update PMS Schedule
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.querySelectorAll('.pms-modal-overlay').forEach(function (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    modal.classList.remove('show');
                }
            });
        });
    </script>
</x-layout.app>