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
    <div class="app">
        <x-layout.sidebar department="Maintenance" />

        <main class="main jo-page">
            <x-layout.topbar
                title="Job Orders"
                subtitle="Manage repair and preventive maintenance service requests"
                notification-count="6"
            />

            <section data-ajax-region="summary" class="stats-grid jo-stats-grid">
                <x-ui.summary-card label="On Hold" value="{{ $onHold ?? 0 }}" small="Job Orders" icon="fa-pause" color="yellow" />
                <x-ui.summary-card label="In Progress" value="{{ $onGoing ?? 0 }}" small="Job Orders" icon="fa-spinner" color="blue" />
                <x-ui.summary-card label="Completed" value="{{ $completed ?? 0 }}" small="Job Orders" icon="fa-check" color="green" />
                <x-ui.summary-card label="Needs Parts" value="{{ $needParts ?? 0 }}" small="Pending parts" icon="fa-screwdriver-wrench" color="red" />
            </section>

            <section data-ajax-region="records" class="table-card jo-table-card">
                @php
                    $jobOrderTabQuery = array_filter(['search' => request('search')]);
                    $activeJobOrderUrl = route('job-orders', $jobOrderTabQuery);
                    $historyJobOrderUrl = route('job-orders', array_merge($jobOrderTabQuery, ['record_view' => 'history']));
                    $currentUser = auth()->user();
                    $currentDepartment = strtolower(trim((string) $currentUser?->department));
                    $currentRole = strtolower(trim((string) $currentUser?->role));
                    $canReviewJobOrders = $currentUser?->hasSystemPermission('maintenance', 'approve')
                        && (($currentDepartment === 'maintenance' && in_array($currentRole, ['head', 'maintenance head', 'admin', 'maintenance admin'], true))
                            || ($currentDepartment === 'admin' && in_array($currentRole, ['head', 'admin', 'system admin'], true)));
                @endphp

                <div class="section-header maintenance-record-header">
                    <div>
                        <h2>Job Orders</h2>
                        <p>
                            {{ $recordView === 'history'
                                ? 'Completed Job Orders are kept here for reference and audit history.'
                                : 'Track job order details, assigned mechanics, completion status, and parts progress' }}
                        </p>
                    </div>
                    @if($recordView !== 'history')
                        <button type="button" id="openJobModal" class="primary-btn jo-header-create-btn">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i> New JO
                        </button>
                    @endif
                </div>

                <div class="maintenance-list-controls job-orders-controls-row">
                    <div class="maintenance-record-tabs" role="tablist" aria-label="Job Order record view">
                        <a
                            href="{{ $activeJobOrderUrl }}"
                            class="maintenance-record-tab {{ $recordView === 'active' ? 'is-active' : '' }}"
                            role="tab"
                            aria-selected="{{ $recordView === 'active' ? 'true' : 'false' }}"
                            data-allow-partial-navigation="true"
                        >
                            <i class="fa-solid fa-list-check"></i>
                            <span>Active</span>
                        </a>
                        <a
                            href="{{ $historyJobOrderUrl }}"
                            class="maintenance-record-tab {{ $recordView === 'history' ? 'is-active' : '' }}"
                            role="tab"
                            aria-selected="{{ $recordView === 'history' ? 'true' : 'false' }}"
                            data-allow-partial-navigation="true"
                        >
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            <span>History</span>
                        </a>
                    </div>
                    <x-ui.table-toolbar
                    :action="route('job-orders')"
                    class="toolbar job-toolbar"
                    search-placeholder="Search bus, mechanic, maintenance type, or part..."
                      search-label="Search Job Orders"
                      :filter-count="(int) ($recordView !== 'history' && request()->filled('part_status') && request('part_status') !== 'All Part Statuses') + (int) ($recordView !== 'history' && request()->filled('maintenance_type') && request('maintenance_type') !== 'All Types')"
                      filter-title="Filter Job Orders"
                      :sort-options="['created_at' => 'Created Date', 'job_order_no' => 'Job Order Number', 'bus_no' => 'Bus Number', 'start_date' => 'Start Date', 'status' => 'JO Status', 'part_status' => 'Part Status']"
                      default-sort="created_at"
                      default-direction="desc"
                    data-server-filter="{{ $recordView === 'history' ? 'true' : 'false' }}"
                >
                    <x-slot:hidden>
                        @if($recordView === 'history')
                            <input type="hidden" name="record_view" value="history">
                        @endif
                    </x-slot:hidden>
                    <x-slot:filters>
                        @if($recordView !== 'history')
                            <x-ui.table-toolbar-field label="Part Status" name="part_status" default="All Part Statuses">
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
                            </x-ui.table-toolbar-field>

                            <x-ui.table-toolbar-field label="Maintenance Type" name="maintenance_type" default="All Types">
                                <option value="All Types" {{ request('maintenance_type', 'All Types') === 'All Types' ? 'selected' : '' }}>All Types</option>
                                <option value="PMS" {{ request('maintenance_type') === 'PMS' ? 'selected' : '' }}>PMS</option>
                                <option value="Repair" {{ request('maintenance_type') === 'Repair' ? 'selected' : '' }}>Repair</option>
                            </x-ui.table-toolbar-field>
                        @endif
                    </x-slot:filters>
                </x-ui.table-toolbar>
                </div>

                <div class="table-wrap">
                    <table class="job-orders-table">
                        <thead>
                            <tr>
                                <th>Bus ID</th>
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
                                    $joStatus = $jobOrder->display_status;
                                    $isPendingReview = $jobOrder->review_status === 'For Review';
                                    $isRevisionRequired = $jobOrder->review_status === 'Revision Required';
                                    $isRejectedReview = $jobOrder->review_status === 'Rejected';
                                    $isApprovedReview = $jobOrder->review_status === 'Approved';
                                    $isOverdue = $jobOrder->is_overdue;
                                    $durationSuggestion = $durationSuggestions->get($jobOrder->id);

                                    $partStatus = $jobOrder->part_status;
                                    if (!$hasNeededParts) {
                                        $partStatus = '----';
                                    } elseif (!$partStatus || $partStatus === 'Unknown' || $partStatus === 'No Parts Needed') {
                                        $partStatus = 'Not Requested';
                                    }

                                    $linkedPrs = $linkedPrsByJobOrderNo->get($jobOrder->job_order_no, collect());

                                    $linkedPr = $linkedPrs->first();
                                    $hasLinkedPr = $linkedPrs->isNotEmpty();
                                    $isRejectedPr = $linkedPr && $linkedPr->status === 'Rejected';
                                    $allLinkedPrsRejected = $hasLinkedPr
                                        && $linkedPrs->every(
                                            fn ($purchaseRequest) => $purchaseRequest->status === 'Rejected'
                                        );

                                    $canCreatePr = $isApprovedReview
                                        && $hasMechanic
                                        && $hasNeededParts
                                        && !$isCompleted
                                        && !$hasLinkedPr
                                        && in_array($jobOrder->part_status, [null, 'Not Requested'], true);

                                    $isOngoing = $jobOrder->status === 'On Going';

                                    $canComplete = $isApprovedReview
                                        && !$isCompleted
                                        && $isOngoing
                                        && (!$hasNeededParts || $jobOrder->part_status === 'Issued');

                                    $lockedCompleteReason = !$isApprovedReview
                                        ? 'Maintenance Head approval is required before completion.'
                                        : ($isOnHold
                                            ? 'Cannot complete while job order is on hold.'
                                            : (!$isOngoing
                                                ? 'Only ongoing Job Orders can be completed.'
                                                : 'Cannot complete until required parts are issued by warehouse.'));

                                    $isLockedByPurchaseRequest = in_array($jobOrder->part_status, [
                                        'Approved', 'For Purchase', 'Ordered', 'For Pick-up', 'For Delivery',
                                        'Delivered', 'Picked Up', 'Issued'
                                    ], true);

                                    $isViewOnly = $isApprovedReview || $isCompleted || $isLockedByPurchaseRequest || $isPendingReview || $isRejectedReview;

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
                                    <td class="jo-bus-cell">
                                        <span class="bus-badge-pill">
                                            <i class="fa-solid fa-bus"></i> {{ $jobOrder->bus_no }}
                                        </span>
                                    </td>

                                    <td class="jo-type-cell">
                                        <strong class="jo-type-label">{{ $jobOrder->maintenance_type }}</strong>
                                        @if($jobOrder->estimated_duration_value && $jobOrder->estimated_duration_unit)
                                            <span class="jo-estimate-inline">
                                                Est. {{ $jobOrder->formatted_estimated_duration }}
                                            </span>
                                        @endif
                                        @if($isOverdue)
                                            <span class="jo-overdue-indicator" title="Estimated repair duration exceeded; revisit this Job Order.">
                                                <i class="fa-solid fa-clock" aria-hidden="true"></i>
                                                {{ $jobOrder->overdue_label }}
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        @php
                                            $assignedMechanicNames = $jobOrder->mechanicAssignments
                                                ->pluck('mechanic_name')
                                                ->map(fn ($name) => trim((string) $name))
                                                ->filter()
                                                ->unique()
                                                ->values();
                                            if ($assignedMechanicNames->isEmpty() && filled($jobOrder->assigned_mechanic)) {
                                                $assignedMechanicNames = collect([$jobOrder->assigned_mechanic]);
                                            }
                                        @endphp
                                        @if($assignedMechanicNames->isNotEmpty())
                                            <div class="jo-mechanic-profiles">
                                                @foreach($assignedMechanicNames as $mechanicName)
                                                    <div class="jo-mechanic-profile">
                                                        <span class="jo-mechanic-profile-icon" aria-hidden="true"><i class="fa-solid fa-user"></i></span>
                                                        <span class="jo-mechanic-profile-text">
                                                            <span class="jo-mechanic-profile-name">{{ $mechanicName }}</span>
                                                            <span class="jo-mechanic-profile-caption">Mechanic profile</span>
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="jo-mechanic-unassigned">Not assigned</span>
                                        @endif
                                    </td>

                                    <td class="{{ $jobOrder->start_date ? 'date-time-cell' : 'empty' }}">
                                        @if($jobOrder->start_date)
                                            <span class="date-value">{{ date('M d, Y', strtotime($jobOrder->start_date)) }}</span>
                                            <span class="time-value">{{ date('h:i A', strtotime($jobOrder->start_date)) }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>

                                    <td class="{{ $isCompleted && $jobOrder->completion_date ? 'date-time-cell' : 'empty' }}">
                                        @if($isCompleted)
                                            @if($jobOrder->completion_date)
                                                <span class="date-value">{{ date('M d, Y', strtotime($jobOrder->completion_date)) }}</span>
                                                <span class="time-value">{{ date('h:i A', strtotime($jobOrder->completion_date)) }}</span>
                                            @else
                                                <span class="empty">—</span>
                                            @endif
                                        @elseif($canComplete)
                                            <form id="finishForm-{{ $jobOrder->id }}" action="{{ route('job-orders.finish', $jobOrder->id) }}" method="POST" class="finish-order-form">
                                                @csrf
                                                <button
                                                    type="button"
                                                    class="finish-btn open-finish-modal"
                                                    data-id="{{ $jobOrder->id }}"
                                                    data-jo-no="{{ $jobOrder->job_order_no }}"
                                                    title="Complete this Job Order"
                                                >
                                                    <i class="fa-solid fa-check"></i>
                                                    Complete
                                                </button>
                                            </form>
                                        @else
                                            <button
                                                type="button"
                                                class="finish-btn locked-finish-btn"
                                                disabled
                                                title="{{ $lockedCompleteReason }}"
                                            >
                                                <i class="fa-solid fa-lock"></i>
                                                Complete
                                            </button>
                                        @endif
                                    </td>

                                    <td class="status-col">
                                        <x-ui.status-badge :status="$joStatus" class="{{ $isPendingReview ? 'jo-for-review-badge' : '' }}" />
                                    </td>

                                    <td class="status-col part-status-cell">
                                        @if(!$hasNeededParts || $partStatus === '----')
                                            <span class="jo-no-parts-required-badge" title="This Job Order does not require parts"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> No Parts Required</span>
                                        @elseif($partStatus === 'Not Requested')
                                            <span class="jo-parts-not-requested-badge" title="Parts are required but have not yet been requested"><i class="fa-regular fa-clock" aria-hidden="true"></i> Not Requested</span>
                                        @else
                                            <x-ui.status-badge :status="$partStatus" class="part-status-badge {{ $partStatusClass }}" />
                                        @endif
                                    </td>

                                    <td class="jo-actions-cell">
                                        <div class="actions">
                                            @if($isViewOnly && !($canReviewJobOrders && $isPendingReview))
                                                <button type="button" class="action-btn view jo-open-view jo-labeled-view" data-id="{{ $jobOrder->id }}" aria-label="View Job Order {{ $jobOrder->job_order_no }}" title="View Job Order">
                                                    <i class="fa-solid fa-eye" aria-hidden="true"></i><span>View</span>
                                                </button>
                                                <template class="jo-view-content">
                                                    <div class="jo-view-summary">
                                                        <div><span>Job Order</span><strong>{{ $jobOrder->job_order_no }}</strong></div>
                                                        <div><span>Current Status</span><strong><x-ui.status-badge :status="$jobOrder->display_status" /></strong></div>
                                                    </div>
                                                    <section class="jo-view-section">
                                                        <h3><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i> Basic Information</h3>
                                                        <div class="jo-view-grid">
                                                            <div><span><i class="fa-solid fa-bus-simple" aria-hidden="true"></i> Bus ID / Plate</span><strong>{{ $jobOrder->bus_no }}</strong></div>
                                                            <div><span><i class="fa-solid fa-gears" aria-hidden="true"></i> Maintenance Type</span><strong>{{ $jobOrder->maintenance_type }}</strong></div>
                                                            <div><span>Complaint / Problem</span><strong>{{ $jobOrder->problem_issue ?: 'Not recorded' }}</strong></div>
                                                            <div><span>Maintenance Work</span><strong>{{ $jobOrder->work_to_perform ?: 'Not recorded' }}</strong></div>
                                                        </div>
                                                    </section>
                                                    <section class="jo-view-section">
                                                        <h3><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> Repair &amp; Assignment</h3>
                                                        <div class="jo-view-grid">
                                                            <div><span><i class="fa-solid fa-user-gear" aria-hidden="true"></i> Assigned Mechanics</span><strong>{{ $jobOrder->mechanicAssignments->pluck('mechanic_name')->filter()->implode(', ') ?: ($jobOrder->assigned_mechanic ?: 'Not assigned') }}</strong></div>
                                                            <div><span><i class="fa-regular fa-clock" aria-hidden="true"></i> Verified Duration</span><strong class="jo-view-duration">{{ $jobOrder->formatted_estimated_duration }}</strong></div>
                                                            <div><span>Start Date &amp; Time</span><strong>{{ $jobOrder->start_date?->format('M d, Y · h:i A') ?? 'Not started' }}</strong></div>
                                                            <div><span>Completion Date &amp; Time</span><strong>{{ $jobOrder->completion_date?->format('M d, Y · h:i A') ?? 'Not completed' }}</strong></div>
                                                        </div>
                                                    </section>
                                                    <section class="jo-view-section">
                                                        <h3><i class="fa-solid fa-box-open" aria-hidden="true"></i> Parts &amp; Warehouse</h3>
                                                        <div class="jo-view-grid">
                                                            <div><span>Parts Status</span><strong><span class="jo-view-parts-pill {{ !$hasNeededParts ? 'is-none' : ($partStatus === 'Not Requested' ? 'is-pending' : 'is-active') }}">{{ $hasNeededParts ? $partStatus : 'No Parts Required' }}</span></strong></div>
                                                            <div><span>Requested Parts</span><strong>{{ $jobOrder->part_needed ?: 'None required' }}</strong></div>
                                                        </div>
                                                    </section>
                                                    <section class="jo-view-section">
                                                        <h3><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Head Approval</h3>
                                                        <div class="jo-view-grid">
                                                            <div><span>Approved By</span><strong><i class="fa-solid fa-circle-check jo-view-approved-icon" aria-hidden="true"></i> {{ $jobOrder->approver?->name ?? 'Not approved' }}</strong></div>
                                                            <div><span>Approved At</span><strong>{{ $jobOrder->approved_at?->format('M d, Y · h:i A') ?? 'Pending' }}</strong></div>
                                                        </div>
                                                        @if($jobOrder->review_status === 'Approved' && $jobOrder->audits->contains(fn ($audit) => $audit->action === 'approved' && !empty(data_get($audit->metadata, 'handwritten_signature_path'))))
                                                            <div class="jo-view-signature">
                                                                <span>Authorized Signature</span>
                                                                <img src="{{ route('job-orders.approval-signature', $jobOrder) }}" alt="Maintenance Head's signed approval for {{ $jobOrder->job_order_no }}" loading="lazy">
                                                            </div>
                                                        @else
                                                            <p class="jo-view-help">No saved signature image is available for this record.</p>
                                                        @endif
                                                    </section>
                                                    <details class="jo-view-audit">
                                                        <summary><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Audit History <span class="jo-view-audit-count">{{ $jobOrder->audits->count() }}</span></summary>
                                                        <ol>
                                                            @forelse($jobOrder->audits as $audit)
                                                                <li><strong>{{ str($audit->action)->replace('_', ' ')->title() }}</strong> — {{ $audit->user?->name ?? 'System' }} <small>{{ $audit->created_at?->format('M d, Y · h:i A') }}{{ $audit->notes ? ' · '.$audit->notes : '' }}</small></li>
                                                            @empty
                                                                <li>No audit records yet.</li>
                                                            @endforelse
                                                        </ol>
                                                    </details>
                                                </template>
                                            @elseif(!($canReviewJobOrders && $isPendingReview))
                                            <x-ui.action-buttom-modal
                                                class="{{ $isViewOnly ? 'view open-edit-modal jo-labeled-view' : 'edit open-edit-modal' }}"
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
                                                data-mechanic-ids="{{ $jobOrder->mechanicAssignments->pluck('mechanic_id')->filter()->implode(',') }}"
                                                data-part-needed="{{ $jobOrder->part_needed }}"
                                                data-status="{{ $jobOrder->status }}"
                                                data-review-status="{{ $jobOrder->review_status }}"
                                                data-review-notes="{{ $jobOrder->review_notes }}"
                                                data-view-only="{{ $isViewOnly ? '1' : '0' }}"
                                            >@if($isViewOnly)<span>View</span>@endif</x-ui.action-buttom-modal>
                                            @endif

                                            @if($canReviewJobOrders && $isPendingReview)
                                                <details class="jo-review-actions">
                                                    <summary class="action-btn jo-head-review-trigger" title="View and review Job Order"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i><span>Review</span></summary>
                                                    <div class="jo-review-panel">
                                                        <section class="jo-review-details" aria-label="Submitted Job Order details">
                                                            <div class="jo-review-details-heading">
                                                                <h4><i class="fa-regular fa-clipboard" aria-hidden="true"></i> Job Order Details</h4>
                                                                <span>{{ $jobOrder->job_order_no }}</span>
                                                            </div>
                                                            <div class="jo-review-detail-grid">
                                                                <div><span><i class="fa-solid fa-bus-simple" aria-hidden="true"></i> Bus ID / Plate</span><strong>{{ $jobOrder->bus_no }}</strong></div>
                                                                <div><span><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> Maintenance Type</span><strong>{{ $jobOrder->maintenance_type }}</strong></div>
                                                                <div><span><i class="fa-solid fa-user-gear" aria-hidden="true"></i> Assigned Mechanics</span><strong>{{ $jobOrder->mechanicAssignments->pluck('mechanic_name')->filter()->implode(', ') ?: ($jobOrder->assigned_mechanic ?: 'Not assigned') }}</strong></div>
                                                                <div><span><i class="fa-solid fa-box-open" aria-hidden="true"></i> Parts Status</span><strong>{{ $partStatus }}</strong></div>
                                                            </div>
                                                            <div class="jo-review-detail-long"><span><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Complaint / Problem</span><p>{{ $jobOrder->problem_issue }}</p></div>
                                                            <div class="jo-review-detail-long jo-review-requested-parts">
                                                                @php($reviewParts = app(\App\Services\PartParser::class)->parsePartText($jobOrder->part_needed))
                                                                @if(count($reviewParts))
                                                                    <div class="jo-review-parts-list" data-parts='@json($reviewParts)'>
                                                                        <div class="jo-review-parts-table-header" aria-hidden="true">
                                                                            <span>Part Description</span><span>Qty</span><span>Availability</span>
                                                                        </div>
                                                                        @foreach($reviewParts as $reviewPart)
                                                                            <div class="jo-review-part-item">
                                                                                <strong class="jo-review-part-name">{{ $reviewPart['name'] }}</strong>
                                                                                <span class="jo-review-part-qty">{{ $reviewPart['quantity'] }} {{ $reviewPart['unit'] }}</span>
                                                                                <span class="jo-review-part-availability is-checking">Checking…</span>
                                                                            </div>
                                                                        @endforeach
                                                                    </div>
                                                                @else
                                                                    <p>No parts requested</p>
                                                                @endif
                                                            </div>
                                                            <div class="jo-review-detail-long"><span><i class="fa-regular fa-clock" aria-hidden="true"></i> Submitted Duration</span><p>{{ $jobOrder->formatted_estimated_duration ?: 'Head estimate required' }}</p></div>
                                                        </section>
                                                        @if($jobOrder->audits->isNotEmpty())
                                                            <details class="jo-review-audit">
                                                                <summary>Audit History ({{ $jobOrder->audits->count() }})</summary>
                                                                <ol>
                                                                    @foreach($jobOrder->audits as $audit)
                                                                        <li><strong>{{ str($audit->action)->replace('_', ' ')->title() }}</strong> — {{ $audit->user?->name ?? 'System' }}<small>{{ $audit->created_at?->format('M d, Y h:i A') }}{{ $audit->notes ? ' · '.$audit->notes : '' }}</small></li>
                                                                    @endforeach
                                                                </ol>
                                                            </details>
                                                        @endif
                                                        <div class="jo-review-mode" data-review-mode="approve">
                                                            <form id="jo-head-approve-{{ $jobOrder->id }}" class="jo-review-form jo-review-approve-form" method="POST" action="{{ route('job-orders.approve', $jobOrder) }}">
                                                                @csrf
                                                                <div class="jo-head-duration-review">
                                                                    <div class="jo-review-step-heading">
                                                                        <span class="jo-review-step-number">01</span>
                                                                        <div><strong>Duration Verification</strong><p>Compare the historical data and confirm the estimated repair time.</p></div>
                                                                    </div>
                                                                    <div class="jo-duration-verification-grid">
                                                                        <div class="jo-duration-metric">
                                                                            <span><i class="fa-solid fa-chart-simple" aria-hidden="true"></i> Historical Median Duration</span>
                                                                            <strong>{{ $durationSuggestion ? rtrim(rtrim(number_format((float) $durationSuggestion['value'], 2, '.', ''), '0'), '.').' '.$durationSuggestion['unit'] : 'Not available' }}</strong>
                                                                            <small>{{ $durationSuggestion ? 'Based on '.$durationSuggestion['sample_size'].' completed Job Order(s)' : 'Insufficient reliable history' }}</small>
                                                                        </div>
                                                                        <div class="jo-duration-metric">
                                                                            <span><i class="fa-regular fa-clock" aria-hidden="true"></i> Submitted Duration</span>
                                                                            <strong>{{ $jobOrder->formatted_estimated_duration ?: 'Not provided' }}</strong>
                                                                            <small>Stored with this Job Order</small>
                                                                        </div>
                                                                        <div class="jo-duration-decision-field">
                                                                            <label for="jo-duration-decision-{{ $jobOrder->id }}"><i class="fa-regular fa-file-lines" aria-hidden="true"></i> Head's duration decision <span class="ui-required">*</span></label>
                                                                            <select id="jo-duration-decision-{{ $jobOrder->id }}" name="duration_decision" required>
                                                                                <option value="">Choose a decision</option>
                                                                                @if($jobOrder->estimated_duration_value && $jobOrder->estimated_duration_unit)
                                                                                    <option value="accept">Accept submitted duration ({{ $jobOrder->formatted_estimated_duration }})</option>
                                                                                @endif
                                                                                <option value="adjust">{{ $jobOrder->estimated_duration_value ? 'Adjust duration (specify value)' : 'Set initial duration (no history)' }}</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                    <div class="jo-duration-adjust-fields" hidden>
                                                                        <label>Verified duration <span class="ui-required">*</span></label>
                                                                        <div class="jo-head-duration-inputs">
                                                                            <input type="number" name="verified_duration_value" step="0.01" min="0.01" placeholder="Duration" disabled>
                                                                            <select name="verified_duration_unit" disabled>
                                                                                <option value="Hours">Hours</option>
                                                                                <option value="Minutes">Minutes</option>
                                                                                <option value="Days">Days</option>
                                                                            </select>
                                                                        </div>
                                                                        <label>Reason for adjustment <span class="ui-required">*</span></label>
                                                                        <textarea name="duration_adjustment_reason" maxlength="2000" placeholder="Explain the verified estimate" disabled></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="jo-head-authorization">
                                                                    <div class="jo-review-step-heading">
                                                                        <span class="jo-review-step-number">02</span>
                                                                        <div><strong>Head Authorization</strong><p>Upload your signature to authorize this Job Order.</p></div>
                                                                    </div>
                                                                    <div class="jo-signature-grid">
                                                                        <div class="jo-signature-draw-wrap">
                                                                            <label for="jo-signature-upload-{{ $jobOrder->id }}"><i class="fa-solid fa-file-arrow-up" aria-hidden="true"></i> Signature upload <span class="ui-required">*</span></label>
                                                                            <div class="jo-signature-upload-zone">
                                                                                <div class="jo-signature-upload-placeholder">
                                                                                    <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                                                                                    <strong>Upload signature</strong>
                                                                                    <small>JPG, PNG or WebP · Max 2 MB</small>
                                                                                </div>
                                                                                <img class="jo-signature-preview" alt="Selected signature preview" hidden>
                                                                                <input id="jo-signature-upload-{{ $jobOrder->id }}" class="jo-signature-file" type="file" accept="image/jpeg,image/png,image/webp" aria-label="Choose signature image">
                                                                                <div class="jo-signature-upload-actions">
                                                                                    <button type="button" class="jo-signature-choose"><i class="fa-regular fa-image" aria-hidden="true"></i> Choose file</button>
                                                                                    <button type="button" class="jo-signature-remove" hidden>Remove</button>
                                                                                </div>
                                                                            </div>
                                                                            <div class="jo-signature-crop-modal" hidden role="dialog" aria-modal="true" aria-label="Crop signature image">
                                                                                <div class="jo-signature-crop-dialog">
                                                                                    <div class="jo-signature-crop-header">
                                                                                        <div><strong>Crop signature</strong><small>Position your signature inside the landscape frame.</small></div>
                                                                                        <button type="button" class="jo-signature-crop-cancel" aria-label="Cancel signature crop"><i class="fa-solid fa-xmark"></i></button>
                                                                                    </div>
                                                                                    <div class="jo-signature-crop-viewport" aria-label="Drag image to reposition the signature">
                                                                                        <img class="jo-signature-crop-image" alt="Signature being cropped" draggable="false">
                                                                                    </div>
                                                                                    <label class="jo-signature-crop-zoom-label">Zoom <input class="jo-signature-crop-zoom" type="range" min="1" max="3" step="0.01" value="1"></label>
                                                                                    <div class="jo-signature-crop-footer">
                                                                                        <button type="button" class="jo-signature-crop-back">Cancel</button>
                                                                                        <button type="button" class="jo-signature-crop-apply">Apply Crop</button>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <input type="hidden" name="signature_image" class="jo-signature-image">
                                                                            <small class="jo-signature-feedback" role="status">Your uploaded signature will appear here before approval.</small>
                                                                        </div>
                                                                        <div class="jo-signature-identity">
                                                                            <label><i class="fa-regular fa-user" aria-hidden="true"></i> Signatory name <span class="ui-required">*</span></label>
                                                                            <input name="electronic_signature" required readonly autocomplete="off" value="{{ $currentUser->name }}" aria-label="Authenticated approving officer name">
                                                                            <small>Your authenticated name will appear as the approving officer.</small>
                                                                            <label><i class="fa-regular fa-note-sticky" aria-hidden="true"></i> Review notes <small>(optional)</small></label>
                                                                            <textarea name="review_notes" maxlength="2000" placeholder="Add any notes or remarks for this approval..."></textarea>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </form>
                                                            <form id="jo-head-revision-{{ $jobOrder->id }}" class="jo-review-form jo-review-revision-form" method="POST" action="{{ route('job-orders.return-for-revision', $jobOrder) }}" hidden>
                                                                @csrf
                                                                <strong>Return for Revision</strong>
                                                                <p>Explain what Maintenance Staff must correct before resubmitting.</p>
                                                                <label>Required revision notes <span class="ui-required">*</span></label>
                                                                <textarea name="review_notes" required disabled maxlength="2000" placeholder="Describe the changes needed"></textarea>
                                                            </form>
                                                            <form id="jo-head-reject-{{ $jobOrder->id }}" class="jo-review-form jo-review-reject-form" method="POST" action="{{ route('job-orders.reject', $jobOrder) }}" hidden>
                                                                @csrf
                                                                <strong>Reject Job Order</strong>
                                                                <p>Rejection keeps the JO in its audit history.</p>
                                                                <label>Required rejection reason <span class="ui-required">*</span></label>
                                                                <textarea name="review_notes" required disabled maxlength="2000" placeholder="Explain why the JO is rejected"></textarea>
                                                            </form>
                                                        </div>
                                                        <div class="jo-review-footer">
                                                            <button type="button" class="jo-review-back" hidden>Back to Approval</button>
                                                            <button type="button" class="jo-review-revision-trigger"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Return for Revision</button>
                                                            <button type="button" class="jo-review-reject-trigger"><i class="fa-regular fa-circle-xmark" aria-hidden="true"></i> Reject</button>
                                                            <button type="submit" form="jo-head-approve-{{ $jobOrder->id }}" class="primary-btn jo-review-footer-submit jo-review-approve-submit" disabled aria-disabled="true" title="Upload and crop a signature to enable approval"><i class="fa-solid fa-check" aria-hidden="true"></i> Approve &amp; Sign</button>
                                                            <button type="submit" form="jo-head-revision-{{ $jobOrder->id }}" class="secondary-btn jo-review-footer-submit jo-review-revision-submit" hidden>Confirm Revision</button>
                                                            <button type="submit" form="jo-head-reject-{{ $jobOrder->id }}" class="warning-btn jo-review-footer-submit jo-review-reject-submit" hidden>Confirm Rejection</button>
                                                        </div>
                                                    </div>
                                                </details>
                                            @endif

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

                                            @if($recordView !== 'history' && !$isCompleted && !$isPendingReview && !$isRejectedReview && (!$isApprovedReview || $hasLinkedPr))
                                                <form id="deleteForm-{{ $jobOrder->id }}" action="{{ route('job-orders.destroy', $jobOrder->id, false) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    @if($hasLinkedPr && !$allLinkedPrsRejected)
                                                        <button
                                                            type="button"
                                                            class="action-btn delete disabled-action-btn"
                                                            title="Cannot delete: this Job Order has an active linked Purchase Request."
                                                            disabled
                                                        >
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    @else
                                                        <button
                                                            type="button"
                                                            class="action-btn delete open-delete-modal"
                                                            title="{{ $allLinkedPrsRejected ? 'Delete Rejected Job Order and Purchase Request' : 'Delete Job Order' }}"
                                                            data-id="{{ $jobOrder->id }}"
                                                            data-jo-no="{{ $jobOrder->job_order_no }}"
                                                            data-rejected-pr="{{ $allLinkedPrsRejected ? '1' : '0' }}"
                                                        >
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    @endif
                                                </form>
                                            @endif


                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <x-ui.empty-row
                                    colspan="8"
                                    icon="fa-clipboard-check"
                                    message="No Job Orders Found"
                                    description="No maintenance job orders match your search or filter criteria. Try adjusting your filters."
                                />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-ui.table-footer
                    :items="$jobOrders"
                    data-lazy-pagination="{{ $recordView === 'history' ? 'true' : 'false' }}"
                />
            </section>
        </main>
    </div>

    <div class="jo-view-overlay" id="joReadonlyOverlay" hidden>
        <section class="jo-view-dialog" role="dialog" aria-modal="true" aria-labelledby="joReadonlyTitle" tabindex="-1">
            <header class="jo-view-header">
                <div class="jo-view-heading"><span class="jo-view-heading-icon"><i class="fa-solid fa-file-circle-check" aria-hidden="true"></i></span><div><h2 id="joReadonlyTitle">Job Order Details</h2><p>Maintenance summary, authorization and activity history</p></div></div>
                <button type="button" class="jo-view-close" aria-label="Close Job Order details"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </header>
            <div class="jo-view-body" id="joReadonlyBody"></div>
            <footer class="jo-view-footer"><button type="button" class="jo-view-close">Close</button></footer>
        </section>
    </div>

    <x-ui.form-modal
        id="jobModal"
        title="New Job Order"
        description="Create and assign a maintenance service request"
        icon="fa-screwdriver-wrench"
        size="large"
        form-id="newJobOrderForm"
        :action="route('job-orders.store')"
        method="POST"
        submit-text="Submit for Review"
        submit-id="saveJobOrderBtn"
        submit-icon="fa-floppy-disk"
        close-id="closeJobModal"
        cancel-id="cancelJobModal"
        :confirm="true"
        confirm-title="Submit Job Order?"
        confirm-message="This Job Order will be locked for Maintenance Head review."
        confirm-button="Yes, Submit for Review"
        confirm-type="create"
        class="jo-create-modal-overlay"
        data-pms-create="{{ $pmsCreate ? 'true' : 'false' }}"
    >
        <input
            type="hidden"
            name="pms_schedule_id"
            id="pms_schedule_id"
            value="{{ $pmsCreate?->id ?? '' }}"
        >
        <input type="hidden" name="maintenance_type" value="Repair" data-maintenance-type-hidden="newJo">

        <section class="jo-create-section jo-create-basic jo-create-job-details" data-jo-collapsible>
            <div class="jo-create-section-header">
                <span class="jo-create-section-icon">
                    <i class="fa-solid fa-file-lines"></i>
                </span>

                <div>
                    <h3><span class="jo-create-section-number">01 &mdash;</span> Job Details</h3>
                </div>
            </div>

            <div class="ui-form-grid jo-create-basic-grid jo-create-job-details-grid">
                <x-ui.form-select
                    label="Bus ID / Plate"
                    name="bus_no"
                    id="jobBusNo"
                    icon="fa-bus"
                    placeholder="Select bus"
                    :options="$availableBuses
                        ->mapWithKeys(function ($bus) {
                            return [
                                $bus->bus_no => $bus->bus_no.' · '.($bus->plate_no ?: 'Plate not assigned')
                            ];
                        })
                        ->toArray()"
                    required
                />

                <div class="jo-maintenance-job-slot" data-maintenance-job-slot="newJo"></div>

                <div id="jobLinkedDriverPreview" class="jo-linked-driver-preview ui-form-full" hidden>
                    <i class="fa-solid fa-id-card" aria-hidden="true"></i>
                    <span>Linked driver: <strong id="jobLinkedDriver" aria-live="polite"></strong></span>
                </div>

                <div class="ui-form-group ui-form-full jo-create-problem-field">
                    <label for="jobProblemIssue">
                        Complaint / Problem
                        <span class="ui-required">*</span>
                    </label>

                    <textarea
                        name="problem_issue"
                        id="jobProblemIssue"
                        placeholder="Describe the reported issue or complaint..."
                        required
                    >{{ old(
                        'problem_issue',
                        $pmsCreate
                            ? request(
                                'problem_issue',
                                'PMS maintenance is due based on processed GPS mileage.'
                            )
                            : ''
                    ) }}</textarea>
                </div>

            </div>
        </section>

        <section class="jo-create-section jo-create-details jo-create-mechanics" data-jo-collapsible>
            <div class="jo-create-section-header">
                <span class="jo-create-section-icon">
                    <i class="fa-solid fa-gears"></i>
                </span>

                <div>
                    <h3><span class="jo-create-section-number">02 &mdash;</span> Mechanic Assignment</h3>
                    <p class="jo-create-step-description">Assign on-duty mechanics qualified for this repair.</p>
                </div>
            </div>

            <div class="jo-create-details-grid">
                <div class="ui-form-group jo-mechanic-multi-field">
                    <label for="jobAssignedMechanics">
                        Assigned Mechanics <span class="ui-required">*</span>
                    </label>
                    <div class="ui-input-wrap">
                        <select name="mechanic_ids[]" id="jobAssignedMechanics" multiple size="4" required>
                            @foreach($availableMechanics as $mechanic)
                                <option
                                    value="{{ $mechanic->id }}"
                                    data-specializations="{{ $mechanic->specialization }}"
                                    data-availability="Available"
                                    {{ in_array($mechanic->id, old('mechanic_ids', [])) ? 'selected' : '' }}
                                >
                                    {{ $mechanic->mechanic_name }}{{ $mechanic->specialization ? ' · '.$mechanic->specialization : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <p class="jo-create-field-hint"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Only qualified, on-duty mechanics who are not assigned to a conflicting active Job Order appear. If none are available, check today's attendance and the selected maintenance job.</p>
                </div>
            </div>
        </section>

        <section class="jo-create-section jo-create-duration" data-jo-collapsible>
            <div class="jo-create-section-header">
                <span class="jo-create-section-icon"><i class="fa-regular fa-clock"></i></span>
                <div><h3><span class="jo-create-section-number">03 &mdash;</span> Estimated Duration</h3><p class="jo-create-step-description">Set the expected repair time for Head review.</p></div>
            </div>

            <div class="jo-create-duration-grid">
                <div id="newJoEstimatedDuration" class="ui-form-group jo-estimated-duration-field">
                    <label>Duration <span class="ui-required">*</span></label>
                    <div class="jo-duration-control">
                        <div class="ui-input-wrap">
                            <input type="number" name="estimated_duration_value" min="0.25" step="0.25" value="" placeholder="Enter estimated duration">
                        </div>
                        <select name="estimated_duration_unit" id="jobEstimatedDurationUnit" class="jo-duration-unit-select">
                            @foreach(['Hours', 'Minutes', 'Days'] as $durationUnit)
                                <option value="{{ $durationUnit }}" {{ old('estimated_duration_unit', 'Hours') === $durationUnit ? 'selected' : '' }}>{{ $durationUnit }}</option>
                            @endforeach
                        </select>
                    </div>
                    <small id="jobDurationSuggestion" class="jo-duration-suggestion" data-status="unavailable">
                        <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                        <span>Insufficient Historical Data</span>
                    </small>
                    <p class="jo-duration-compact-help"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Suggestions use at least 3 completed, approved Job Orders for the same specific maintenance work. Enter your own estimate for Head verification.</p>
                </div>
            </div>
        </section>

        <section id="newRequestedPartsSection" class="jo-create-section jo-create-parts jo-parts-section" data-jo-collapsible data-collapsed="true">
            <div class="jo-create-section-header jo-create-parts-header">
                <span class="jo-create-section-icon">
                    <i class="fa-solid fa-box-open"></i>
                </span>

                <div>
                    <h3><span class="jo-create-section-number">04 &mdash;</span> Requested Parts <small class="jo-section-optional">Optional</small></h3>
                </div>

                <button type="button" id="addPartBtn" class="jo-create-add-part-btn">
                    <i class="fa-solid fa-plus"></i>
                    Add Part
                </button>
            </div>

            <div class="jo-create-parts-table">
                <div class="jo-create-parts-table-head" aria-hidden="true">
                    <span>#</span>
                    <span>Item / Part</span>
                    <span>Qty</span>
                    <span>Unit</span>
                    <span>Stock</span>
                    <span>Actions</span>
                </div>

                <div id="partsNeededWrapper" class="jo-parts-repeater jo-create-parts-repeater">
                    <div class="jo-part-row part-needed-row">
                        <input type="text" name="parts[0][name]" placeholder="Part name">
                        <input type="number" name="parts[0][quantity]" min="1" placeholder="Qty">
                        <select name="parts[0][unit]">
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

                        <button type="button" class="remove-part-btn" title="Remove Part">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <input type="hidden" name="parts_issue_strategy" value="complete_before_issuance">

        </section>

        <script type="application/json" id="jobBusDriverMap">@json(
            $availableBuses->mapWithKeys(fn ($bus) => [
                $bus->bus_no => optional($bus->authorizedDrivers->first())->driver_name,
            ])
        )</script>
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
        class="jo-edit-modal-overlay"
    >
        <section class="jo-edit-section jo-edit-basic">
            <div class="jo-edit-section-header">
                <span class="jo-edit-section-icon">
                    <i class="fa-solid fa-file-lines"></i>
                </span>

                <div>
                    <h3>Basic Information</h3>
                    <p>Review the Job Order reference, affected bus, and reported issue.</p>
                </div>
            </div>

            <div class="ui-form-grid jo-edit-basic-grid">
                <x-ui.form-field
                    label="JO No."
                    name="job_order_no"
                    id="edit_job_order_no"
                    icon="fa-hashtag"
                    readonly
                    required
                />

                <x-ui.form-select
                    label="Bus ID / Plate"
                    name="bus_no"
                    id="edit_bus_no"
                    icon="fa-bus"
                    placeholder="Select Bus"
                    :options="$buses
                        ->mapWithKeys(function ($bus) {
                            return [
                                $bus->bus_no => $bus->bus_no.' · '.($bus->plate_no ?: 'Plate not assigned')
                            ];
                        })
                        ->toArray()"
                    required
                />

                <div class="ui-form-group ui-form-full jo-edit-problem-field">
                    <label for="edit_problem_issue">
                        Problem / Issue
                        <span class="ui-required">*</span>
                    </label>

                    <textarea
                        name="problem_issue"
                        id="edit_problem_issue"
                        placeholder="Describe the problem or issue..."
                        required
                    ></textarea>
                </div>
            </div>
        </section>

        <section class="jo-edit-section jo-edit-details">
            <div class="jo-edit-section-header">
                <span class="jo-edit-section-icon">
                    <i class="fa-solid fa-gears"></i>
                </span>

                <div>
                    <h3>Job Details</h3>
                    <p>Review the maintenance work, duration, status, and assigned mechanic.</p>
                </div>
            </div>

            <div class="jo-edit-details-grid">
                <x-ui.form-select
                    label="Maintenance Type"
                    name="maintenance_type"
                    id="edit_maintenance_type"
                    icon="fa-screwdriver-wrench"
                    :options="['PMS' => 'PMS', 'Repair' => 'Repair']"
                    required
                />

                <div id="editJoEstimatedDuration" class="ui-form-group jo-estimated-duration-field">
                    <label>
                        Estimated Work Duration
                        <span class="ui-required">*</span>
                    </label>

                    <div class="jo-duration-control">
                        <div class="ui-input-wrap has-icon">
                            <span class="ui-input-icon">
                                <i class="fa-solid fa-clock"></i>
                            </span>

                            <input
                                type="number"
                                name="estimated_duration_value"
                                min="0.25"
                                step="0.25"
                                placeholder="e.g. 4"
                                required
                            >
                        </div>

                        <select
                            name="estimated_duration_unit"
                            id="editJobEstimatedDurationUnit"
                            class="jo-duration-unit-select"
                            required
                        >
                            <option value="Hours">Hours</option>
                            <option value="Minutes">Minutes</option>
                            <option value="Days">Days</option>
                        </select>
                    </div>
                </div>

                <x-ui.form-select
                    label="Status"
                    name="status"
                    id="edit_status"
                    icon="fa-circle-check"
                    :options="['On Hold' => 'On Hold', 'On Going' => 'In Progress']"
                />

                <div class="ui-form-group">
                    <label for="edit_assigned_mechanic">Assigned Mechanics <small>(optional)</small></label>
                    <select name="mechanic_ids[]" id="edit_assigned_mechanic" multiple size="4">
                        @foreach($allMechanics as $mechanic)
                            <option value="{{ $mechanic->id }}">{{ $mechanic->mechanic_name }}{{ $mechanic->specialization ? ' · '.$mechanic->specialization : '' }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        <section
            id="editRequestedPartsSection"
            class="jo-edit-section jo-edit-parts jo-parts-section"
        >
            <div class="jo-edit-section-header jo-edit-parts-header">
                <span class="jo-edit-section-icon">
                    <i class="fa-solid fa-box-open"></i>
                </span>

                <div>
                    <h3>Requested Parts</h3>
                    <p>Review or update the parts required for this job order.</p>
                </div>

                <button type="button" id="editAddPartBtn" class="jo-edit-add-part-btn">
                    <i class="fa-solid fa-plus"></i>
                    Add Part
                </button>
            </div>

            <div id="editPartsLockedNotice" class="jo-parts-locked-notice" style="display: none;">
                <span class="jo-parts-lock-icon">
                    <i class="fa-solid fa-lock"></i>
                </span>

                <div>
                    <strong>Assign a mechanic first</strong>
                    <span>Requested parts can only be entered after a mechanic is assigned to inspect the bus.</span>
                </div>
            </div>

            <div class="jo-edit-parts-table">
                <div class="jo-edit-parts-table-head" aria-hidden="true">
                    <span>#</span>
                    <span>Item / Part</span>
                    <span>Qty</span>
                    <span>Unit</span>
                    <span>Actions</span>
                </div>

                <div id="editPartsNeededWrapper" class="jo-parts-repeater jo-edit-parts-repeater"></div>
            </div>
        </section>

        <div class="ui-form-actions jo-edit-footer" id="editJobMainActions">
            <button type="button" id="cancelEditJobModal" class="ui-form-btn ui-form-btn-cancel">
                Cancel
            </button>

            <button type="submit" id="updateJobOrderBtn" class="ui-form-btn ui-form-btn-primary">
                <i class="fa-solid fa-floppy-disk"></i>
                <span>Update Job Order</span>
            </button>
        </div>

        <div class="ui-form-actions jo-edit-footer" id="viewOnlyJobActions" style="display: none;">
            <button type="button" id="closeViewOnlyJob" class="ui-form-btn ui-form-btn-cancel">
                Close
            </button>
        </div>
    </x-ui.form-modal>

    <div id="finishJobModal" class="delete-modal-overlay">
        <div class="delete-modal-box">
            <div class="delete-icon finish-icon"><i class="fa-solid fa-check"></i></div>
            <h2>Complete Job Order?</h2>
            <p>
                Confirm that all maintenance work for this Job Order has been completed.
                Completion date and time will be recorded automatically.
            </p>
            <span id="finishJoNo" class="sr-only"></span>
            <div class="delete-modal-actions">
                <button type="button" id="cancelFinishJob" class="secondary-btn cancel-delete-btn">Cancel</button>
                <button type="button" id="confirmFinishJob" class="warning-btn confirm-finish-btn">Yes, Complete</button>
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


</x-layout.app>
