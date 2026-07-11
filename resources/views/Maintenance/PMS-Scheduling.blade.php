<x-layout.app
    title="FROMS - PMS Scheduling"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Maintenance/pms-scheduling.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Maintenance/pms-scheduling.js'
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
                subtitle="Monitor preventive maintenance tasks based on processed GPS vehicle mileage data."
                notification-count="6"
            />

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
                        <small>Scheduled maintenance tasks</small>
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
                            One bus row can contain multiple PMS tasks.
                            Click the list icon to view the tasks in a popup.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="pms-add-btn"
                        data-open-add-pms
                    >
                        <i class="fa-solid fa-plus"></i>
                        Add PMS Task
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
                                placeholder="Search vehicle, PMS task, or status..."
                            >
                        </div>

                        <div class="filter-group">
                            <label for="pmsStatusFilter">Status</label>
                            <select
                                name="status"
                                id="pmsStatusFilter"
                                onchange="this.form.submit()"
                            >
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
                    </div>
                </form>

                <div class="table-wrap">
                    <table class="pms-table">
                        <thead>
                            <tr>
                                <th>Vehicle ID</th>
                                <th>GPS Report Date</th>
                                <th>Current KM</th>
                                <th>KM Traveled</th>
                                <th>Due PMS</th>
                                <th class="status-col">Overall Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($rows as $row)
                                @php
                                    $modalId = 'pmsTasksModal-'
                                        . $loop->iteration
                                        . '-'
                                        . preg_replace('/[^A-Za-z0-9]/', '-', $row->bus_no);
                                @endphp

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
                                    <td>
                                        {{ $row->due_pms_count }}
                                        task{{ $row->due_pms_count === 1 ? '' : 's' }}
                                    </td>
                                    <td class="status-col">
                                        <span class="badge {{ strtolower(str_replace(' ', '-', $row->overall_status)) }}">
                                            {{ $row->overall_status }}
                                        </span>
                                    </td>
                                    <td>
                                        <button
                                            type="button"
                                            class="pms-view-tasks-btn open-pms-tasks-modal"
                                            data-modal-target="{{ $modalId }}"
                                            title="View PMS Tasks"
                                        >
                                            <i class="fa-solid fa-list-check"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="pms-empty-row">
                                        No PMS schedule records found. Add a bus PMS task first.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-ui.table-footer :items="$rows" />
            </section>

            @foreach($rows as $row)
                @php
                    $modalId = 'pmsTasksModal-'
                        . $loop->iteration
                        . '-'
                        . preg_replace('/[^A-Za-z0-9]/', '-', $row->bus_no);
                @endphp

                <div class="pms-modal-overlay pms-tasks-popup" id="{{ $modalId }}">
                    <div class="pms-modal pms-wide-modal">
                        <div class="pms-modal-header">
                            <div>
                                <h2>PMS Tasks - {{ $row->bus_no }}</h2>
                                <p>
                                    Current KM:
                                    {{ $row->current_km !== null
                                        ? number_format($row->current_km, 2) . ' km'
                                        : 'No processed GPS KM'
                                    }}
                                    • Overall Status: {{ $row->overall_status }}
                                </p>
                            </div>

                            <button
                                type="button"
                                class="pms-close-btn close-pms-tasks-modal"
                                data-modal-target="{{ $modalId }}"
                            >
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <div class="table-wrap pms-popup-table-wrap">
                            <table class="pms-table">
                                <thead>
                                    <tr>
                                        <th>PMS Type</th>
                                        <th>Last PMS KM</th>
                                        <th>Interval</th>
                                        <th>Next PMS KM</th>
                                        <th>Recommended Date</th>
                                        <th class="status-col">Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @forelse($row->tasks as $task)
                                        <tr>
                                            <td><strong>{{ $task->maintenance_type }}</strong></td>
                                            <td>{{ number_format($task->last_pms_km, 2) }} km</td>
                                            <td>{{ number_format($task->pms_interval_km, 2) }} km</td>
                                            <td>{{ number_format($task->next_pms_km, 2) }} km</td>
                                            <td>
                                                {{ $task->status === 'Overdue'
                                                    ? 'Immediate'
                                                    : ($task->recommended_date
                                                        ? \Carbon\Carbon::parse($task->recommended_date)->format('M d, Y')
                                                        : '—')
                                                }}
                                            </td>
                                            <td class="status-col">
                                                <span class="badge {{ strtolower(str_replace(' ', '-', $task->status)) }}">
                                                    {{ $task->status }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="actions">
                                                    @if(in_array($task->status, ['Due Soon', 'Overdue']))
                                                        <a
                                                            href="{{ route('pms-schedules.create-job-order', $task->schedule) }}"
                                                            class="create-pms-jo-btn"
                                                            title="Create PMS Job Order"
                                                            aria-label="Create PMS Job Order"
                                                        >
                                                            <i class="fa-solid fa-plus"></i>
                                                        </a>
                                                    @else
                                                        <span
                                                            class="pms-action-placeholder"
                                                            title="Job Order available when Due Soon or Overdue"
                                                        >
                                                            —
                                                        </span>
                                                    @endif

                                                    <button
                                                        type="button"
                                                        class="pms-edit-task-btn open-edit-pms"
                                                        title="Edit PMS Task"
                                                        aria-label="Edit PMS Task"
                                                        data-update-url="{{ route('pms-schedules.update', $task->schedule) }}"
                                                        data-bus-no="{{ $task->schedule->bus_no }}"
                                                        data-maintenance-type="{{ $task->schedule->maintenance_type }}"
                                                        data-last-pms-km="{{ $task->schedule->last_pms_km }}"
                                                        data-pms-interval-km="{{ $task->schedule->pms_interval_km }}"
                                                        data-recommended-date="{{ $task->schedule->recommended_date
                                                            ? \Carbon\Carbon::parse($task->schedule->recommended_date)->format('Y-m-d')
                                                            : ''
                                                        }}"
                                                    >
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </button>

                                                    <form
                                                        action="{{ route('pms-schedules.destroy', $task->schedule) }}"
                                                        method="POST"
                                                        onsubmit="return confirm('Delete this PMS task?');"
                                                    >
                                                        @csrf
                                                        @method('DELETE')

                                                        <button
                                                            type="submit"
                                                            class="delete"
                                                            title="Delete PMS Task"
                                                        >
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="pms-empty-row">
                                                No PMS tasks found for this bus.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="pms-modal-overlay" id="addPmsModal">
                <div class="pms-modal">
                    <div class="pms-modal-header">
                        <div>
                            <h2>Add PMS Task</h2>
                            <p>Select a bus with processed GPS mileage data.</p>
                        </div>

                        <button
                            type="button"
                            class="pms-close-btn"
                            data-close-add-pms
                        >
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <form
                        id="addPmsForm"
                        action="{{ route('pms-schedules.store') }}"
                        method="POST"
                    >
                        @csrf

                        <div class="pms-form-grid">
                            <div class="form-group">
                                <label for="pmsBusSelect">Vehicle ID / Bus No.</label>
                                <select name="bus_no" id="pmsBusSelect" required>
                                    <option value="">Select processed GPS bus</option>
                                    @foreach($processedBuses as $bus)
                                        <option
                                            value="{{ $bus->bus_no }}"
                                            data-current-km="{{ $bus->current_km }}"
                                            data-gps-date="{{ $bus->gps_report_date
                                                ? \Carbon\Carbon::parse($bus->gps_report_date)->format('M d, Y h:i A')
                                                : ''
                                            }}"
                                        >
                                            {{ $bus->bus_no }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="currentGpsKm">Current GPS KM</label>
                                <input type="text" id="currentGpsKm" readonly placeholder="Select a bus first">
                            </div>

                            <div class="form-group">
                                <label for="gpsReportDate">GPS Report Date</label>
                                <input type="text" id="gpsReportDate" readonly placeholder="Select a bus first">
                            </div>

                            <div class="form-group">
                                <label for="lastPmsKm">Last PMS KM</label>
                                <input
                                    id="lastPmsKm"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="last_pms_km"
                                    placeholder="Enter last completed PMS KM"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="pmsIntervalKm">PMS Interval KM</label>
                                <input
                                    id="pmsIntervalKm"
                                    type="number"
                                    step="0.01"
                                    min="1"
                                    name="pms_interval_km"
                                    value="5000"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="nextPmsKm">Next PMS KM</label>
                                <input type="text" id="nextPmsKm" readonly placeholder="Automatic">
                            </div>

                            <div class="form-group">
                                <label for="pmsStatusPreview">Predicted Status</label>
                                <input type="text" id="pmsStatusPreview" readonly placeholder="Automatic">
                            </div>

                            <div class="form-group">
                                <label for="maintenanceType">PMS Type</label>
                                <select
                                    id="maintenanceType"
                                    name="maintenance_type_option"
                                    required
                                >
                                    <option value="Change Oil">Change Oil</option>
                                    <option value="Oil Filter">Oil Filter</option>
                                    <option value="Brake Check">Brake Check</option>
                                    <option value="Air Filter">Air Filter</option>
                                    <option value="Full PMS">Full PMS</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div
                                class="form-group full-width"
                                id="customMaintenanceTypeGroup"
                                hidden
                            >
                                <label for="customMaintenanceType">Other PMS Type</label>
                                <input
                                    type="text"
                                    id="customMaintenanceType"
                                    name="custom_maintenance_type"
                                    maxlength="255"
                                    placeholder="Example: Transmission Fluid Replacement"
                                >
                            </div>

                            <input
                                type="hidden"
                                id="finalMaintenanceType"
                                name="maintenance_type"
                            >

                            <div class="form-group full-width">
                                <label for="recommendedDate">Recommended Date</label>
                                <input
                                    type="date"
                                    id="recommendedDate"
                                    name="recommended_date"
                                >
                            </div>
                        </div>

                        <div class="pms-modal-actions modal-actions">
                            <button
                                type="button"
                                class="secondary-btn pms-cancel-btn"
                                data-close-add-pms
                            >
                                Cancel
                            </button>

                            <button type="submit" class="primary-btn pms-save-btn">
                                <i class="fa-solid fa-floppy-disk"></i>
                                Save PMS Task
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="pms-modal-overlay" id="editPmsModal">
                <div class="pms-modal">
                    <div class="pms-modal-header">
                        <div>
                            <h2>Edit PMS Task</h2>
                            <p>Update the selected preventive maintenance task.</p>
                        </div>

                        <button
                            type="button"
                            class="pms-close-btn"
                            data-close-edit-pms
                        >
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <form id="editPmsForm" action="#" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="pms-form-grid">
                            <div class="form-group">
                                <label for="editPmsBusNo">Vehicle ID / Bus No.</label>
                                <input
                                    type="text"
                                    id="editPmsBusNo"
                                    name="bus_no"
                                    readonly
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="editPmsMaintenanceType">PMS Type</label>
                                <select
                                    id="editPmsMaintenanceType"
                                    name="maintenance_type_option"
                                    required
                                >
                                    <option value="Change Oil">Change Oil</option>
                                    <option value="Oil Filter">Oil Filter</option>
                                    <option value="Brake Check">Brake Check</option>
                                    <option value="Air Filter">Air Filter</option>
                                    <option value="Full PMS">Full PMS</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div
                                class="form-group full-width"
                                id="editCustomMaintenanceTypeGroup"
                                hidden
                            >
                                <label for="editCustomMaintenanceType">Other PMS Type</label>
                                <input
                                    type="text"
                                    id="editCustomMaintenanceType"
                                    name="custom_maintenance_type"
                                    maxlength="255"
                                    placeholder="Enter custom PMS type"
                                >
                            </div>

                            <input
                                type="hidden"
                                id="editFinalMaintenanceType"
                                name="maintenance_type"
                            >

                            <div class="form-group">
                                <label for="editLastPmsKm">Last PMS KM</label>
                                <input
                                    type="number"
                                    id="editLastPmsKm"
                                    name="last_pms_km"
                                    min="0"
                                    step="0.01"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="editPmsIntervalKm">PMS Interval KM</label>
                                <input
                                    type="number"
                                    id="editPmsIntervalKm"
                                    name="pms_interval_km"
                                    min="1"
                                    step="0.01"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="editNextPmsKm">Next PMS KM</label>
                                <input
                                    type="text"
                                    id="editNextPmsKm"
                                    readonly
                                    placeholder="Automatic"
                                >
                            </div>

                            <div class="form-group">
                                <label for="editRecommendedDate">Recommended Date</label>
                                <input
                                    type="date"
                                    id="editRecommendedDate"
                                    name="recommended_date"
                                >
                            </div>
                        </div>

                        <div class="pms-modal-actions modal-actions">
                            <button
                                type="button"
                                class="secondary-btn pms-cancel-btn"
                                data-close-edit-pms
                            >
                                Cancel
                            </button>

                            <button type="submit" class="primary-btn pms-save-btn">
                                <i class="fa-solid fa-floppy-disk"></i>
                                Update PMS Task
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <style>
        .pms-wide-modal {
            width: min(1100px, 96vw);
            max-height: 90vh;
            overflow-y: auto;
        }

        .pms-popup-table-wrap {
            margin-top: 16px;
        }

        .pms-action-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            color: #94a3b8;
            font-weight: 700;
        }

        .create-pms-jo-btn,
        .pms-edit-task-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border: none;
            border-radius: 9px;
            cursor: pointer;
            text-decoration: none;
        }

        .create-pms-jo-btn {
            color: #2563eb;
            background: #eff6ff;
        }

        .create-pms-jo-btn:hover {
            background: #dbeafe;
        }

        .pms-edit-task-btn {
            color: #d97706;
            background: #fffbeb;
        }

        .pms-edit-task-btn:hover {
            background: #fef3c7;
        }

        .pms-view-tasks-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 10px;
            color: #2563eb;
            background: #eff6ff;
            cursor: pointer;
        }

        .pms-view-tasks-btn:hover {
            background: #dbeafe;
        }

        .actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }

        .actions form {
            margin: 0;
        }
    </style>
</x-layout.app>