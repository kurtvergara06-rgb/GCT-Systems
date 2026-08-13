<x-layout.app
    title="FROMS - Job Orders"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Main-styles/form-components.css',
        'resources/css/Maintenance/job-order.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Maintenance/job-order.js'
    ]"
>
    @if($errors->any())
        <div id="validationErrorModal" class="modal-overlay delete-modal-overlay show active">
            <div class="modal-card delete-modal-box">
                <div class="delete-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <h2>Form Error</h2>
                <p>Please check the form. Some required information is missing.</p>
                <ul class="form-error-list">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <div class="delete-modal-actions">
                    <button type="button" id="closeValidationErrorModal" class="secondary-btn cancel-delete-btn">Okay</button>
                </div>
            </div>
        </div>
    @endif

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
            ]"
        />

        <main class="main jo-page">
            <x-layout.topbar
                title="Job Orders"
                subtitle="Manage repair and preventive maintenance service requests"
                notification-count="6"
            />

            <section class="stats-grid jo-stats-grid">
                <x-ui.summary-card label="On Hold" value="{{ $onHold ?? 0 }}" small="Job Orders" icon="fa-pause" color="yellow" />
                <x-ui.summary-card label="On Going" value="{{ $onGoing ?? 0 }}" small="Job Orders" icon="fa-spinner" color="blue" />
                <x-ui.summary-card label="Completed" value="{{ $completed ?? 0 }}" small="Job Orders" icon="fa-check" color="green" />
                <x-ui.summary-card label="Needs Parts" value="{{ $needParts ?? 0 }}" small="Pending parts" icon="fa-screwdriver-wrench" color="red" />
            </section>

            <section class="table-card jo-table-card">
                <div class="section-header">
                    <div>
                        <h2>Job Orders</h2>
                        <p>Track job order details, assigned mechanics, completion status, and parts progress</p>
                    </div>
                </div>

                <x-ui.table-toolbar
                    :action="route('job-orders')"
                    class="toolbar job-toolbar"
                    search-placeholder="Search bus, mechanic, maintenance type, or part..."
                    button-id="openJobModal"
                    button-label="New JO"
                >
                    <div class="filter-group">
                        <label for="partStatusFilter"></label>
                        <select name="part_status" id="partStatusFilter" class="part-status-select" onchange="this.form.submit()">
                            @foreach([
                                'All Part Statuses', 'Not Requested', 'Submitted', 'Approved', 'Rejected',
                                'For Purchase', 'Ordered', 'For Pick-up', 'For Delivery', 'Delivered',
                                'Picked Up', 'Issued', 'No Parts Needed'
                            ] as $partStatusOption)
                                <option
                                    value="{{ $partStatusOption }}"
                                    {{ request('part_status', 'All Part Statuses') === $partStatusOption ? 'selected' : '' }}
                                >
                                    {{ $partStatusOption }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="maintenanceTypeFilter"></label>
                        <select name="maintenance_type" id="maintenanceTypeFilter" onchange="this.form.submit()">
                            <option value="All Types" {{ request('maintenance_type', 'All Types') === 'All Types' ? 'selected' : '' }}>All Types</option>
                            <option value="PMS" {{ request('maintenance_type') === 'PMS' ? 'selected' : '' }}>PMS</option>
                            <option value="Repair" {{ request('maintenance_type') === 'Repair' ? 'selected' : '' }}>Repair</option>
                        </select>
                    </div>
                </x-ui.table-toolbar>

                <div class="table-wrap">
                    <table class="job-orders-table">
                        <thead>
                            <tr>
                                <th>Bus #</th>
                                <th>Maintenance Type</th>
                                <th>Assigned Mechanic</th>
                                <th>Start Date & Time</th>
                                <th>Completion Date & Time</th>
                                <th class="status-col">JO Status</th>
                                <th class="status-col">Part Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($jobOrders as $jobOrder)
                                @php
                                    $isCompleted = $jobOrder->status === 'Completed';
                                    $isOnHold = $jobOrder->status === 'On Hold';
                                    $hasMechanic = !empty($jobOrder->assigned_mechanic);
                                    $hasNeededParts = !empty($jobOrder->part_needed);
                                    $joStatus = $jobOrder->status ?: 'On Going';
                                    $isOverdue = $jobOrder->is_overdue;

                                    $partStatus = $jobOrder->part_status;
                                    if (!$hasNeededParts) {
                                        $partStatus = '----';
                                    } elseif (!$partStatus || $partStatus === 'Unknown' || $partStatus === 'No Parts Needed') {
                                        $partStatus = 'Not Requested';
                                    }

                                    $linkedPr = \App\Models\Maintenance\PurchaseRequest::query()
                                        ->where('job_order_no', $jobOrder->job_order_no)
                                        ->where('pr_no', 'not like', '%-P')
                                        ->where(function ($query) {
                                            $query->whereNull('source_type')
                                                ->orWhere('source_type', 'Maintenance Request');
                                        })
                                        ->latest()
                                        ->first();

                                    $hasLinkedPr = $linkedPr !== null;
                                    $isRejectedPr = $linkedPr && $linkedPr->status === 'Rejected';

                                    $canCreatePr = $hasMechanic
                                        && $hasNeededParts
                                        && !$isCompleted
                                        && !$hasLinkedPr
                                        && in_array($jobOrder->part_status, [null, 'Not Requested'], true);

                                    $canFinish = !$isOnHold
                                        && (!$hasNeededParts || in_array($jobOrder->part_status, ['Issued', 'Rejected'], true));

                                    $isLockedByPurchaseRequest = in_array($jobOrder->part_status, [
                                        'Approved', 'For Purchase', 'Ordered', 'For Pick-up', 'For Delivery',
                                        'Delivered', 'Picked Up', 'Issued'
                                    ], true);

                                    $isViewOnly = $isCompleted || $isLockedByPurchaseRequest;

                                    $partStatusClass = match($partStatus) {
                                        'Not Requested' => 'not-requested',
                                        'Submitted' => 'submitted',
                                        'Approved' => 'approved',
                                        'Rejected' => 'rejected',
                                        'For Purchase' => 'for-purchase',
                                        'Ordered' => 'ordered',
                                        'For Pick-up' => 'for-pick-up',
                                        'For Delivery' => 'for-delivery',
                                        'Delivered' => 'delivered',
                                        'Picked Up' => 'picked-up',
                                        'Issued' => 'issued',
                                        'No Parts Needed' => 'no-parts-needed',
                                        default => 'not-requested',
                                    };
                                @endphp

                                <tr class="{{ $isOverdue ? 'jo-overdue-row' : '' }}">
                                    <td>{{ $jobOrder->bus_no }}</td>

                                    <td>
                                        {{ $jobOrder->maintenance_type }}
                                        @if($jobOrder->estimated_duration_value && $jobOrder->estimated_duration_unit)
                                            <span class="jo-estimate-inline">
                                                Est. {{ $jobOrder->formatted_estimated_duration }}
                                            </span>
                                        @endif
                                        @if($isOverdue)
                                            <span class="jo-overdue-indicator" title="Estimated completion time has been exceeded.">
                                                <i class="fa-solid fa-triangle-exclamation"></i>
                                                {{ $jobOrder->overdue_label }}
                                            </span>
                                        @endif
                                    </td>

                                    <td>{{ $jobOrder->assigned_mechanic ?: 'No mechanic assigned' }}</td>

                                    <td class="{{ $jobOrder->start_date ? 'date-time-cell' : 'empty' }}">
                                        @if($jobOrder->start_date)
                                            <span class="date-value">{{ date('M d, Y', strtotime($jobOrder->start_date)) }}</span>
                                            <span class="time-value">{{ date('h:i A', strtotime($jobOrder->start_date)) }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>

                                    <td class="{{ $jobOrder->completion_date ? 'date-time-cell' : 'empty' }}">
                                        @if($jobOrder->completion_date)
                                            <span class="date-value">{{ date('M d, Y', strtotime($jobOrder->completion_date)) }}</span>
                                            <span class="time-value">{{ date('h:i A', strtotime($jobOrder->completion_date)) }}</span>
                                        @else
                                            @if($canFinish)
                                                <form id="finishForm-{{ $jobOrder->id }}" action="{{ route('job-orders.finish', $jobOrder->id) }}" method="POST">
                                                    @csrf
                                                    <button
                                                        type="button"
                                                        class="finish-btn open-finish-modal"
                                                        data-id="{{ $jobOrder->id }}"
                                                        data-jo-no="{{ $jobOrder->job_order_no }}"
                                                    >
                                                        <i class="fa-solid fa-check"></i>
                                                        Finish
                                                    </button>
                                                </form>
                                            @else
                                                <button type="button" class="finish-btn locked-finish-btn" disabled>
                                                    <i class="fa-solid fa-lock"></i>
                                                    Locked
                                                </button>
                                            @endif
                                        @endif
                                    </td>

                                    <td class="status-col">
                                        <x-ui.status-badge :status="$joStatus" />
                                    </td>

                                    <td class="status-col part-status-cell">
                                        @if(!$hasNeededParts || $partStatus === '----')
                                            <span class="empty">----</span>
                                        @else
                                            <span class="part-status-badge {{ $partStatusClass }}">{{ $partStatus }}</span>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="actions">
                                            <x-ui.action-buttom-modal
                                                class="{{ $isViewOnly ? 'view open-edit-modal' : 'edit open-edit-modal' }}"
                                                title="{{ $isViewOnly ? 'View Job Order' : 'Edit Job Order' }}"
                                                icon="{{ $isViewOnly ? 'fa-eye' : 'fa-pen-to-square' }}"
                                                data-id="{{ $jobOrder->id }}"
                                                data-update-url="{{ route('job-orders.update', $jobOrder->id, false) }}"
                                                data-job-order-no="{{ $jobOrder->job_order_no }}"
                                                data-bus-no="{{ $jobOrder->bus_no }}"
                                                data-problem-issue="{{ $jobOrder->problem_issue }}"
                                                data-maintenance-type="{{ $jobOrder->maintenance_type }}"
                                                data-estimated-duration-value="{{ $jobOrder->estimated_duration_value }}"
                                                data-estimated-duration-unit="{{ $jobOrder->estimated_duration_unit }}"
                                                data-assigned-mechanic="{{ $jobOrder->assigned_mechanic }}"
                                                data-part-needed="{{ $jobOrder->part_needed }}"
                                                data-status="{{ $jobOrder->status }}"
                                                data-view-only="{{ $isViewOnly ? '1' : '0' }}"
                                            />

                                            @if($canCreatePr)
                                                <form
                                                    action="{{ route('job-orders.create-pr', $jobOrder->id) }}"
                                                    method="POST"
                                                    class="create-pr-form"
                                                    data-confirm-form
                                                    data-confirm-title="Create Purchase Request?"
                                                    data-confirm-message="Are you sure you want to create a Purchase Request from job order {{ $jobOrder->job_order_no }}?"
                                                    data-confirm-button="Yes, Create PR"
                                                    data-confirm-type="create"
                                                >
                                                    @csrf
                                                    <button type="submit" class="action-btn create-pr-btn" title="Create Purchase Request">
                                                        <i class="fa-solid fa-file-circle-plus"></i>
                                                    </button>
                                                </form>
                                            @endif

                                            @if($isRejectedPr)
                                                <a
                                                    href="{{ route('purchase-requests', ['search' => $jobOrder->job_order_no]) }}"
                                                    class="action-btn create-pr-btn"
                                                    title="Revise Rejected Purchase Request"
                                                >
                                                    <i class="fa-solid fa-rotate"></i>
                                                </a>
                                            @endif

                                            <form id="deleteForm-{{ $jobOrder->id }}" action="{{ route('job-orders.destroy', $jobOrder->id, false) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                @if($hasLinkedPr)
                                                    <button
                                                        type="button"
                                                        class="action-btn disabled-action-btn"
                                                        title="Cannot delete: this Job Order already has a linked Purchase Request."
                                                        disabled
                                                    >
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                @else
                                                    <button
                                                        type="button"
                                                        class="action-btn delete open-delete-modal"
                                                        title="Delete Job Order"
                                                        data-id="{{ $jobOrder->id }}"
                                                        data-jo-no="{{ $jobOrder->job_order_no }}"
                                                    >
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                @endif
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <x-ui.empty-row colspan="8" message="No job orders found." />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-ui.table-footer :items="$jobOrders" />
            </section>
        </main>
    </div>

    <x-ui.form-modal
        id="jobModal"
        title="New Job Order"
        description="Enter the required job order information."
        icon="fa-clipboard-list"
        size="large"
        form-id="newJobOrderForm"
        :action="route('job-orders.store')"
        method="POST"
        submit-text="Save Job Order"
        submit-id="saveJobOrderBtn"
        submit-icon="fa-floppy-disk"
        close-id="closeJobModal"
        cancel-id="cancelJobModal"
        :confirm="true"
        confirm-title="Create Job Order?"
        confirm-message="Are you sure you want to create this Job Order?"
        confirm-button="Yes, Create Job Order"
        confirm-type="create"
    >
        <input type="hidden" name="pms_schedule_id" id="pms_schedule_id" value="">

        <div class="ui-form-grid">
            <x-ui.form-field
                label="JO No."
                name="display_job_order_no"
                id="jobOrderNo"
                :value="$nextJobOrderNo"
                icon="fa-hashtag"
                readonly
            />

            <x-ui.form-select
                label="Bus #"
                name="bus_no"
                id="jobBusNo"
                icon="fa-bus"
                placeholder="Select Bus"
                :options="$availableBuses
                    ->mapWithKeys(function ($bus) {
                        return [
                            $bus->bus_no => $bus->bus_no . ($bus->plate_no ? ' - ' . $bus->plate_no : '')
                        ];
                    })
                    ->toArray()"
                required
            />

            <div class="ui-form-group ui-form-full">
                <label for="jobProblemIssue">Problem / Issue <span class="ui-required">*</span></label>
                <textarea
                    name="problem_issue"
                    id="jobProblemIssue"
                    placeholder="Describe the problem or issue..."
                    required
                >{{ old('problem_issue') }}</textarea>
            </div>

            <x-ui.form-select
                label="Maintenance Type"
                name="maintenance_type"
                id="jobMaintenanceType"
                icon="fa-screwdriver-wrench"
                placeholder="Select Maintenance Type"
                :options="['Repair' => 'Repair']"
                required
            />

            <x-ui.form-select
                label="Assigned Mechanic"
                name="assigned_mechanic"
                id="jobAssignedMechanic"
                icon="fa-user-gear"
                :placeholder="$availableMechanics->count()
                    ? 'Select Available Mechanic'
                    : 'No available mechanic - JO will be On Hold'"
                :options="$availableMechanics->pluck('mechanic_name', 'mechanic_name')->toArray()"
            />
        </div>

        <div id="newRequestedPartsSection" class="jo-parts-section is-locked">
            <x-ui.form-section
                title="Requested Parts"
                subtitle="Add each part separately so Warehouse can check inventory correctly."
                icon="fa-gears"
            >
                <x-slot:action>
                    <button type="button" id="addPartBtn" class="ui-btn-small" disabled>
                        <i class="fa-solid fa-plus"></i>
                        Add Part
                    </button>
                </x-slot:action>

                <div id="newPartsLockedNotice" class="jo-parts-locked-notice">
                    <i class="fa-solid fa-lock"></i>
                    <div>
                        <strong>Assign a mechanic first</strong>
                        <span>Requested parts can be added after a mechanic is assigned to inspect the bus.</span>
                    </div>
                </div>

                <div id="partsNeededWrapper" class="jo-parts-repeater">
                    <div class="jo-part-row part-needed-row">
                        <input type="text" name="parts[0][name]" placeholder="Part name" disabled>
                        <input type="number" name="parts[0][quantity]" min="1" placeholder="Qty" disabled>
                        <select name="parts[0][unit]" disabled>
                            <option value="">Unit</option>
                            <option value="pcs">pcs</option>
                            <option value="set">set</option>
                            <option value="liter">liter</option>
                            <option value="gallon">gallon</option>
                            <option value="bottle">bottle</option>
                            <option value="box">box</option>
                            <option value="meter">meter</option>
                            <option value="kg">kg</option>
                            <option value="pack">pack</option>
                            <option value="pair">pair</option>
                            <option value="roll">roll</option>
                            <option value="tube">tube</option>
                        </select>
                        <button type="button" class="remove-part-btn" title="Remove Part" disabled>
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            </x-ui.form-section>
        </div>
    </x-ui.form-modal>

    <x-ui.form-modal
        id="editJobModal"
        title="Job Order Details"
        description="Review and update the selected job order."
        icon="fa-clipboard-check"
        size="large"
        form-id="editJobForm"
        action="#"
        method="PUT"
        close-id="closeEditJobModal"
        :confirm="true"
        confirm-title="Update Job Order?"
        confirm-message="Are you sure you want to update this Job Order?"
        confirm-button="Yes, Update Job Order"
        confirm-type="update"
        :show-actions="false"
    >
        <div class="ui-form-grid">
            <x-ui.form-field
                label="JO No."
                name="job_order_no"
                id="edit_job_order_no"
                icon="fa-hashtag"
                readonly
                required
            />

            <x-ui.form-select
                label="Bus #"
                name="bus_no"
                id="edit_bus_no"
                icon="fa-bus"
                placeholder="Select Bus"
                :options="$buses
                    ->mapWithKeys(function ($bus) {
                        return [
                            $bus->bus_no => $bus->bus_no . ($bus->plate_no ? ' - ' . $bus->plate_no : '')
                        ];
                    })
                    ->toArray()"
                required
            />

            <div class="ui-form-group ui-form-full">
                <label for="edit_problem_issue">Problem / Issue <span class="ui-required">*</span></label>
                <textarea name="problem_issue" id="edit_problem_issue" required></textarea>
            </div>

            <x-ui.form-select
                label="Maintenance Type"
                name="maintenance_type"
                id="edit_maintenance_type"
                icon="fa-screwdriver-wrench"
                :options="['PMS' => 'PMS', 'Repair' => 'Repair']"
                required
            />

            <x-ui.form-select
                label="Status"
                name="status"
                id="edit_status"
                icon="fa-circle-check"
                :options="['On Hold' => 'On Hold', 'On Going' => 'On Going']"
            />

            <x-ui.form-select
                label="Assigned Mechanic"
                name="assigned_mechanic"
                id="edit_assigned_mechanic"
                icon="fa-user-gear"
                placeholder="No mechanic assigned"
                :options="$availableMechanics->pluck('mechanic_name', 'mechanic_name')->toArray()"
                full
            />
        </div>

        <div id="editRequestedPartsSection" class="jo-parts-section">
            <x-ui.form-section
                title="Requested Parts"
                subtitle="Review or update the parts required for this job order."
                icon="fa-gears"
            >
                <x-slot:action>
                    <button type="button" id="editAddPartBtn" class="ui-btn-small">
                        <i class="fa-solid fa-plus"></i>
                        Add Part
                    </button>
                </x-slot:action>

                <div id="editPartsLockedNotice" class="jo-parts-locked-notice" style="display: none;">
                    <i class="fa-solid fa-lock"></i>
                    <div>
                        <strong>Assign a mechanic first</strong>
                        <span>Requested parts can only be entered after a mechanic is assigned to inspect the bus.</span>
                    </div>
                </div>

                <div id="editPartsNeededWrapper" class="jo-parts-repeater"></div>
            </x-ui.form-section>
        </div>

        <div class="ui-form-actions" id="editJobMainActions">
            <button type="button" id="cancelEditJobModal" class="ui-form-btn ui-form-btn-cancel">Cancel</button>
            <button type="submit" id="updateJobOrderBtn" class="ui-form-btn ui-form-btn-primary">
                <i class="fa-solid fa-floppy-disk"></i>
                <span>Update Job Order</span>
            </button>
        </div>

        <div class="ui-form-actions" id="viewOnlyJobActions" style="display: none;">
            <button type="button" id="closeViewOnlyJob" class="ui-form-btn ui-form-btn-cancel">Close</button>
        </div>
    </x-ui.form-modal>

    <div id="finishJobModal" class="delete-modal-overlay">
        <div class="delete-modal-box">
            <div class="delete-icon finish-icon"><i class="fa-solid fa-check"></i></div>
            <h2>Finish Job Order?</h2>
            <p>
                Are you sure you want to finish
                <strong id="finishJoNo">this job order</strong>?
                This record will be marked as completed.
            </p>
            <div class="delete-modal-actions">
                <button type="button" id="cancelFinishJob" class="secondary-btn cancel-delete-btn">Cancel</button>
                <button type="button" id="confirmFinishJob" class="warning-btn confirm-finish-btn">Yes, Finish</button>
            </div>
        </div>
    </div>

    <x-ui.action-buttom-modal
        mode="delete"
        id="deleteJobModal"
        delete-title="Delete Job Order?"
        delete-message="Are you sure you want to delete"
        name-id="deleteJoNo"
        cancel-id="cancelDeleteJob"
        confirm-id="confirmDeleteJob"
    />

    @if(request('create_pms') && $pmsCreate)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('jobModal');
                const busSelect = document.querySelector('#jobModal select[name="bus_no"]');
                const issueField = document.querySelector('#jobModal textarea[name="problem_issue"]');
                const typeSelect = document.querySelector('#jobModal select[name="maintenance_type"]');
                const pmsScheduleId = document.getElementById('pms_schedule_id');

                if (busSelect) {
                    busSelect.value = @json($pmsCreate->bus_no);
                }

                if (issueField) {
                    issueField.value = @json(request('problem_issue', 'PMS maintenance is due based on processed GPS mileage.'));
                }

                if (typeSelect) {
                    const hasPms = Array.from(typeSelect.options).some(option => option.value === 'PMS');

                    if (!hasPms) {
                        const pmsOption = document.createElement('option');
                        pmsOption.value = 'PMS';
                        pmsOption.textContent = 'PMS';
                        typeSelect.appendChild(pmsOption);
                    }

                    typeSelect.value = 'PMS';
                }

                if (pmsScheduleId) {
                    pmsScheduleId.value = @json($pmsCreate->id);
                }

                if (modal) {
                    modal.classList.add('show', 'active');
                }
            });
        </script>
    @endif
</x-layout.app>
