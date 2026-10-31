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
                      :filter-count="(int) (request()->filled('part_status') && request('part_status') !== 'All Part Statuses') + (int) (request()->filled('maintenance_type') && request('maintenance_type') !== 'All Types')"
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
                            <x-ui.table-toolbar-field label="Part Status" name="part_status" default="All Part Statuses">
                                @foreach([
                                    'All Part Statuses', 'No Parts Required', 'Not Requested', 'Not Issued',
                                    'Partially Issued', 'Waiting for Parts', 'Fully Issued'
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
                    </x-slot:filters>
                </x-ui.table-toolbar>
                </div>

                <div class="table-wrap">
                    <table class="job-orders-table">
                        <thead>
                            <tr>
                                <th>JO ID</th>
                                <th>Maintenance Type</th>
                                <th>Assigned Mechanic</th>
                                <th>Start Date & Time</th>
                                <th>Completion Date & Time</th>
                                <th class="status-col">JO Status</th>
                                <th class="status-col">Part Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        @php
                            // One shared live stock snapshot for all Head Review rows.
                            // Free means physically available less allocations to every JO.
                            $reviewStockItems = \App\Models\Warehouse\InventoryItem::query()->get();
                            $reviewCommittedStock = \App\Models\Maintenance\JobOrderPartReservation::query()
                                ->select('inventory_item_id', \Illuminate\Support\Facades\DB::raw('SUM(quantity) AS reserved'))
                                ->groupBy('inventory_item_id')->pluck('reserved', 'inventory_item_id');
                        @endphp
                        <tbody>
                            @forelse($jobOrders as $jobOrder)
                                @php
                                    $isCompleted = $jobOrder->status === 'Completed';
                                    $isOnHold = $jobOrder->display_status === 'On Hold';
                                    $hasMechanic = !empty($jobOrder->assigned_mechanic);
                                    $hasNeededParts = !empty($jobOrder->part_needed);
                                    $joStatus = $jobOrder->display_status;
                                    $isPendingReview = $jobOrder->review_status === 'For Review';
                                    $isRevisionRequired = $jobOrder->review_status === 'Revision Required';
                                    $isRejectedReview = $jobOrder->review_status === 'Rejected';
                                    $isApprovedReview = $jobOrder->review_status === 'Approved';
                                    $isOverdue = $jobOrder->is_overdue;
                                    $durationSuggestion = $durationSuggestions->get($jobOrder->id);

                                    $partStatus = ($joPartsDisplayStatuses ?? collect())->get($jobOrder->id, 'Not Requested');
                                    $linkedPrs = $linkedPrsByJobOrderNo->get($jobOrder->job_order_no, collect());

                                    // A linked, still-outstanding procurement request is an objective
                                    // delay risk. Do not infer delay from "Not Requested" alone or
                                    // flag an already fulfilled/completed Job Order.
                                    // Show the shortage warning until Warehouse confirms all required parts.
                                    $hasPartsDelayRisk = $isApprovedReview && !$isCompleted && $hasNeededParts
                                        && !in_array($partStatus, ['Fully Issued', 'No Parts Required'], true);
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

                                    $isOngoing = $jobOrder->display_status === 'In Progress';

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

                                    $partStatusClass = match ($partStatus) {
                                        'No Parts Required' => 'no-parts-needed',
                                        'Not Requested' => 'not-requested',
                                        'Not Issued' => 'rejected',
                                        'Partially Issued' => 'for-purchase',
                                        'Waiting for Parts' => 'for-purchase',
                                        'Fully Issued' => 'issued',
                                        default => 'not-requested',
                                    };
                                @endphp

                                <tr class="{{ $isOverdue ? 'jo-overdue-row' : '' }}">
                                    <td class="jo-bus-cell">
                                        <span class="bus-badge-pill">
                                            <i class="fa-solid fa-file-lines" aria-hidden="true"></i> {{ $jobOrder->job_order_no }}
                                        </span>
                                    </td>

                                    <td class="jo-type-cell">
                                        <strong class="jo-type-label">{{ $jobOrder->maintenance_type }}</strong>
                                        @if($jobOrder->estimated_duration_value && $jobOrder->estimated_duration_unit)
                                            <span class="jo-estimate-inline">
                                                Est. {{ $jobOrder->formatted_estimated_duration }}
                                            </span>
                                        @endif
                                        @if($hasPartsDelayRisk)
                                            <span class="jo-parts-delay-indicator" title="Required parts have not been fully issued. Completion may be delayed; the estimated duration is unchanged.">
                                                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Possible Delay — Missing Parts
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
                                                $assignedMechanicNames = collect(preg_split('/\\s*,\\s*/', (string) $jobOrder->assigned_mechanic) ?: [])
                                                    ->map(fn ($name) => trim((string) $name))->filter()->unique()->values();
                                            }
                                        @endphp
                                        @if($assignedMechanicNames->isNotEmpty())
                                            <div class="jo-mechanic-profiles">
                                                <div class="jo-mechanic-profile">
                                                    <span class="jo-mechanic-profile-icon" aria-hidden="true"><i class="fa-solid fa-user"></i></span>
                                                    <span class="jo-mechanic-profile-text">
                                                        <span class="jo-mechanic-profile-name">{{ $assignedMechanicNames->first() }}</span>
                                                        <span class="jo-mechanic-profile-caption">Mechanic profile</span>
                                                    </span>
                                                    @if($assignedMechanicNames->count() > 1)
                                                        <details class="jo-mechanic-more" data-jo-mechanic-more>
                                                            <summary aria-label="Show {{ $assignedMechanicNames->count() - 1 }} additional assigned mechanics">
                                                                +{{ $assignedMechanicNames->count() - 1 }} more
                                                                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                                                            </summary>
                                                            <div class="jo-mechanic-more-panel">
                                                                <div class="jo-mechanic-more-title">Other assigned mechanics</div>
                                                                @foreach($assignedMechanicNames->skip(1) as $mechanicName)
                                                                    <div class="jo-mechanic-profile">
                                                                        <span class="jo-mechanic-profile-icon" aria-hidden="true"><i class="fa-solid fa-user"></i></span>
                                                                        <span class="jo-mechanic-profile-text">
                                                                            <span class="jo-mechanic-profile-name">{{ $mechanicName }}</span>
                                                                            <span class="jo-mechanic-profile-caption">Mechanic profile</span>
                                                                        </span>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </details>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <span class="jo-mechanic-unassigned">Not assigned</span>
                                        @endif
                                    </td>

                                    <td class="{{ !$isOnHold && $jobOrder->start_date ? 'date-time-cell' : 'empty' }}">
                                        @if(!$isOnHold && $jobOrder->start_date)
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
                                                        <div class="jo-view-summary-number"><span>Job Order No.</span><strong>{{ $jobOrder->job_order_no }}</strong><small>Created {{ $jobOrder->created_at?->format('M d, Y · h:i A') ?? '—' }}</small></div>
                                                        <div><span>Review Status</span><strong><x-ui.status-badge :status="$jobOrder->review_status ?: 'Pending'" /></strong></div>
                                                        <div><span>Operational Status</span><strong><x-ui.status-badge :status="$jobOrder->status" /></strong></div>
                                                        <div><span>Bus ID / Plate</span><strong>{{ $jobOrder->bus_no }}</strong></div>
                                                        <div><span>Maintenance Type</span><strong>{{ $jobOrder->maintenance_type }}</strong></div>
                                                    </div>
                                                    <div class="jo-view-dual-column">
                                                    <section class="jo-view-section">
                                                        <h3><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i> Basic Information</h3>
                                                        <div class="jo-view-grid">
                                                            <div><span><i class="fa-solid fa-bus-simple" aria-hidden="true"></i> Bus ID / Plate</span><strong>{{ $jobOrder->bus_no }}</strong></div>
                                                            <div><span><i class="fa-solid fa-gears" aria-hidden="true"></i> Maintenance Type</span><strong>{{ $jobOrder->maintenance_type }}</strong></div>
                                                            <div><span>Maintenance Work</span>@php $workTypes = collect(preg_split('/\\s*,\\s*/', (string) $jobOrder->work_to_perform))->map(fn ($v) => trim($v))->filter()->values(); @endphp <div class="jo-view-work-list">@if($workTypes->isEmpty())<strong>Not recorded</strong>@else<strong>{{ $workTypes->first() }}</strong>@if($workTypes->count() > 1)<details class="jo-view-work-more"><summary>+{{ $workTypes->count() - 1 }} more <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary><div class="jo-view-work-expanded">@foreach($workTypes->skip(1) as $work)<span>{{ $work }}</span>@endforeach</div></details>@endif @endif</div></div>
                                                            <div class="jo-view-complaint"><span>Complaint / Problem</span><strong>{{ $jobOrder->problem_issue ?: 'Not recorded' }}</strong></div>
                                                        </div>
                                                    </section>
                                                    <section class="jo-view-section">
                                                        <h3><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> Repair &amp; Assignment</h3>
                                                        <div class="jo-view-grid">
                                                            <div class="jo-view-mechanics"><span><i class="fa-solid fa-user-gear" aria-hidden="true"></i> Assigned Mechanics</span>
                                                                <div class="jo-view-mechanic-chips">
                                                                    @php
                                                                        $viewMechanicNames = $jobOrder->mechanicAssignments->pluck('mechanic_name')->filter();
                                                                        if ($viewMechanicNames->isEmpty()) {
                                                                            $viewMechanicNames = collect(preg_split('/\\s*,\\s*/', (string) $jobOrder->assigned_mechanic))->filter();
                                                                        }
                                                                    @endphp
                                                                    @forelse($viewMechanicNames->unique()->values()->take(4) as $mechanicName)
                                                                        <span class="jo-view-mechanic-chip">{{ $mechanicName }}</span>
                                                                    @empty
                                                                        <strong>Not assigned</strong>
                                                                    @endforelse
                                                                    @if($viewMechanicNames->unique()->count() > 4)
                                                                        <details class="jo-view-work-more jo-view-mechanics-more"><summary>+{{ $viewMechanicNames->unique()->count() - 4 }} more <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary><div class="jo-view-work-expanded">@foreach($viewMechanicNames->unique()->values()->skip(4) as $mechanicName)<span>{{ $mechanicName }}</span>@endforeach</div></details>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            @if($hasPartsDelayRisk)
                                        <div class="jo-parts-delay-note" role="note"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><div><strong>Possible Completion Delay — Parts Shortage</strong><p>Some required parts are not yet fully issued. Check Warehouse availability and linked requests; any shortage may delay completion. Maintenance Head should review whether the estimated duration needs adjustment. This notice does not change the estimate.</p></div></div>
                                        @endif
                                        <div class="jo-view-time-fields">
                                                                <div><span><i class="fa-regular fa-clock" aria-hidden="true"></i> Verified Duration</span><strong class="jo-view-duration">{{ $jobOrder->formatted_estimated_duration }}</strong></div>
                                                                <div><span>Started</span><strong>{{ $jobOrder->start_date?->format('M d, Y · h:i A') ?? 'Not started' }}</strong></div>
                                                                <div><span>Completed</span><strong>{{ $jobOrder->completion_date?->format('M d, Y · h:i A') ?? 'Not completed' }}</strong></div>
                                                            </div>
                                                        </div>
                                                    </section>
                                                    </div>
                                                    <section class="jo-view-section jo-view-warehouse-section">
                                                        <h3><i class="fa-solid fa-box-open" aria-hidden="true"></i> Parts &amp; Warehouse <span class="jo-view-parts-pill {{ !$hasNeededParts ? 'is-none' : ($partStatus === 'Not Requested' ? 'is-pending' : 'is-active') }}">{{ $hasNeededParts ? $partStatus : 'No Parts Required' }}</span></h3>
                                                        <div class="jo-view-grid jo-view-warehouse-grid">
                                                            <div class="jo-view-parts-cell jo-view-parts-fullwidth">
                                                                <span>Requested Parts</span>
                                                                @php
                                                                    $viewRequestedParts = app(\App\Services\PartParser::class)->parsePartText($jobOrder->part_needed);
                                                                @endphp
                                                                @if(collect($viewRequestedParts ?? [])->isNotEmpty())
                                                                    <div class="jo-view-stock-table">
                                                                        <div class="jo-view-stock-head"><span>#</span><span>Part name</span><span>Item code</span><span>Requested qty</span><span>Stock information</span><span>Status</span></div>
                                                                        @foreach(collect($viewRequestedParts ?? []) as $part)
                                                                            @php
                                                                                $partKey = mb_strtolower(trim((string) $part['name']));
                                                                                $stockItem = $viewPartsStock->get($part['inventory_item_id'] ?? null);
                                                                                $stockUnit = mb_strtolower(trim((string) ($stockItem?->unit_of_measurement ?: $stockItem?->unit)));
                                                                                $requestedUnit = mb_strtolower(trim((string) ($part['unit'] ?? '')));
                                                                                $partUnitsMatch = $requestedUnit === '' || $stockUnit === '' || $requestedUnit === $stockUnit
                                                                                    || (in_array($requestedUnit, ['pc', 'pcs', 'piece', 'pieces']) && in_array($stockUnit, ['pc', 'pcs', 'piece', 'pieces']));
                                                                                $partStock = $stockItem && $partUnitsMatch ? max(0, (int) $stockItem->quantity_available) : 0;
                                                                                $partDemand = (int) $part['quantity'];
                                                                                $partAvailability = $partStock >= $partDemand ? 'Sufficient' : ($partStock > 0 ? 'Partial' : 'Unavailable');
                                                                            @endphp
                                                                            <div class="jo-view-stock-row">
                                                                                <span>{{ $loop->iteration }}</span>
                                                                                <strong class="jo-view-part-name">{{ $part['name'] }}</strong>
                                                                                <span>{{ $stockItem?->item_code ?: '—' }}</span>
                                                                                <span class="jo-view-part-quantity">{{ $part['quantity'] }} {{ $part['unit'] }}</span>
                                                                                <span class="jo-view-stock-qty">{{ $partStock }} on hand</span>
                                                                                <span class="jo-view-part-availability {{ $partAvailability === 'Sufficient' ? 'is-available' : ($partAvailability === 'Partial' ? 'is-partial' : 'is-unavailable') }}">{{ $partAvailability }}</span>
                                                                            </div>
                                                                        @endforeach
                                                                    </div>
                                                                @else
                                                                    <strong>None required</strong>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </section>
                                                    <div class="jo-view-dual-column jo-view-bottom-columns">
                                                    <section class="jo-view-section jo-view-head-approval-section">
                                                        <h3><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Head Approval</h3>
                                                        <div class="jo-view-grid jo-view-approval-grid">
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
                                                    <section class="jo-view-audit jo-view-audit-compact">
                                                        <h3 class="jo-view-audit-heading"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Audit History <span class="jo-view-audit-count">{{ $jobOrder->audits->count() }}</span></h3>
                                                        <ol>
                                                            @forelse($jobOrder->audits->sortBy(fn ($entry) => sprintf('%020d-%010d', $entry->created_at?->getTimestamp() ?? 0, $entry->id)) as $audit)
                                                                <li><strong>{{ str($audit->action)->replace('_', ' ')->title() }}</strong> — {{ $audit->action === 'created' ? ($jobOrder->creator?->name ?? $audit->user?->name ?? 'Unknown creator') : ($audit->user?->name ?? 'Unknown actor') }} <small>{{ $audit->created_at?->format('M d, Y · h:i A') }}{{ $audit->notes ? ' · '.$audit->notes : '' }}</small></li>
                                                            @empty
                                                                <li>No audit records yet.</li>
                                                            @endforelse
                                                        </ol>
                                                    </section>
                                                    </div>
                                                </template>
                                            @elseif(!($canReviewJobOrders && $isPendingReview))
                                            @php
                                                $jobOrderSpecializations = collect(data_get(
                                                    $jobOrder->audits->whereIn('action', ['created', 'resubmitted'])->sortByDesc('id')->first(),
                                                    'metadata.required_specializations',
                                                    []
                                                ))->filter(fn ($specialization) => is_string($specialization) && trim($specialization) !== '')->values();
                                            @endphp
                                            <x-ui.action-buttom-modal
                                                class="{{ $isViewOnly ? 'view open-edit-modal jo-labeled-view' : 'edit open-edit-modal jo-labeled-view' }}"
                                                title="{{ $isViewOnly ? 'View Job Order' : 'Edit Job Order' }}"
                                                icon="{{ $isViewOnly ? 'fa-eye' : 'fa-pen-to-square' }}"
                                                data-id="{{ $jobOrder->id }}"
                                                data-update-url="{{ route('job-orders.update', $jobOrder->id, false) }}"
                                                data-job-order-no="{{ $jobOrder->job_order_no }}"
                                                data-bus-no="{{ $jobOrder->bus_no }}"
                                                data-problem-issue="{{ $jobOrder->problem_issue }}"
                                                data-work-to-perform="{{ $jobOrder->work_to_perform }}"
                                                data-maintenance-type="{{ $jobOrder->maintenance_type }}"
                                                data-estimated-duration-value="{{ $jobOrder->estimated_duration_value }}"
                                                data-estimated-duration-unit="{{ $jobOrder->estimated_duration_unit }}"
                                                data-assigned-mechanic="{{ $jobOrder->assigned_mechanic }}"
                                                data-mechanic-ids="{{ $jobOrder->mechanicAssignments->pluck('mechanic_id')->filter()->implode(',') }}"
                                                data-specializations='@json($jobOrderSpecializations)'
                                                data-part-needed="{{ $jobOrder->part_needed }}"
                                                data-status="{{ $jobOrder->status }}"
                                                data-review-status="{{ $jobOrder->review_status }}"
                                                data-review-notes="{{ $jobOrder->review_notes }}"
                                                data-view-only="{{ $isViewOnly ? '1' : '0' }}"
                                            ><span>{{ $isViewOnly ? 'View' : 'Edit' }}</span></x-ui.action-buttom-modal>
                                            @endif

                                            @if($canReviewJobOrders && $isPendingReview)
                                                <details class="jo-review-actions">
                                                    <summary class="action-btn jo-head-review-trigger" title="View and review Job Order"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i><span>Review</span></summary>
                                                    <div class="jo-review-panel">
                                                        <section class="jo-review-details" aria-label="Submitted Job Order details">
                                                            <div class="jo-review-details-heading">
                                                                <div>
                                                                    <h4><i class="fa-regular fa-clipboard" aria-hidden="true"></i> Job Order Details</h4>
                                                                    <p>Verify details, confirm duration, and authorize the Job Order.</p>
                                                                </div>
                                                                <span>{{ $jobOrder->job_order_no }}</span>
                                                            </div>
                                                            <div class="jo-review-detail-grid">
                                                                <div><span><i class="fa-solid fa-bus-simple" aria-hidden="true"></i> Bus ID / Plate</span><strong>{{ $jobOrder->bus_no }}</strong></div>
                                                                <div><span><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> Maintenance Type</span><strong>{{ $jobOrder->maintenance_type }}</strong></div>
                                                                <div><span><i class="fa-regular fa-clock" aria-hidden="true"></i> Estimated Duration</span><strong>{{ $jobOrder->formatted_estimated_duration ?: 'Head estimate required' }}</strong></div>
                                                                <div class="jo-review-parts-state"><span><i class="fa-solid fa-box-open" aria-hidden="true"></i> Parts Status</span><span class="jo-review-parts-status-badge {{ $partStatus === 'Not Requested' ? 'is-not-requested' : '' }}">{{ $partStatus }}</span></div>
                                                            </div>
                                                            <div class="jo-review-detail-support-grid">
                                                                @php
                                                                    $headReviewMechanics = $jobOrder->mechanicAssignments->pluck('mechanic_name')->filter()->unique()->values();
                                                                    if ($headReviewMechanics->isEmpty()) {
                                                                        $headReviewMechanics = collect(preg_split('/\\s*,\\s*/', (string) $jobOrder->assigned_mechanic))->map(fn ($name) => trim($name))->filter()->unique()->values();
                                                                    }
                                                                @endphp
                                                                <div class="jo-review-mechanic-field">
                                                                    <span><i class="fa-solid fa-user-gear" aria-hidden="true"></i> Assigned Mechanics <strong class="jo-review-mechanic-count" aria-label="{{ $headReviewMechanics->count() }} assigned mechanics">{{ $headReviewMechanics->count() }}</strong></span>
                                                                    <div class="jo-review-mechanic-summary" data-fit-mechanics>
                                                                        @forelse($headReviewMechanics as $mechanicName)
                                                                            <span class="jo-review-mechanic-primary" data-mechanic-chip><i class="fa-solid fa-user-gear" aria-hidden="true"></i> {{ $mechanicName }}</span>
                                                                        @empty
                                                                            <strong>Not assigned</strong>
                                                                        @endforelse
                                                                        @if($headReviewMechanics->count() > 1)
                                                                            <details class="jo-review-mechanic-more" data-mechanic-overflow hidden>
                                                                                <summary aria-label="Show additional assigned mechanics"><span data-overflow-count></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                                                                                <div class="jo-review-mechanic-expanded" data-overflow-list></div>
                                                                            </details>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                                <div class="jo-review-detail-long jo-review-complaint"><span><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Complaint / Problem</span><p>{{ $jobOrder->problem_issue }}</p></div>
                                                            </div>
                                                        </section>
                                                            <div class="jo-review-requested-parts">
                                                                @php
                                                                    $reviewParts = app(\App\Services\PartParser::class)->parsePartText($jobOrder->part_needed);
                                                                    $reviewAllocations = collect($jobOrder->inventory_snapshot['parts'] ?? []);
                                                                    $reviewLiveStock = collect(app(\App\Services\Maintenance\JobOrderWorkflowService::class)
                                                                        ->inventoryAvailability($reviewParts, $reviewStockItems, $reviewCommittedStock)['parts'] ?? []);

                                                                    $reviewRequiredTotal = collect($reviewParts)->sum(fn ($part) => max(0, (int) ($part['quantity'] ?? 0)));
                                                                    $reviewReservedTotal = $reviewAllocations->sum(fn ($part) => max(0, (int) ($part['reserved'] ?? 0)));
                                                                    $reviewAvailablePartCount = collect($reviewParts)->filter(function ($part, $index) use ($reviewAllocations) {
                                                                        $allocation = $reviewAllocations->get($index);
                                                                        return $allocation !== null
                                                                            && (int) ($allocation['reserved'] ?? 0) >= (int) ($part['quantity'] ?? 0);
                                                                    })->count();
                                                                    $reviewAvailabilityPercent = count($reviewParts) > 0
                                                                        ? min(100, (int) round(($reviewAvailablePartCount / count($reviewParts)) * 100))
                                                                        : 100;
                                                                    $reviewHasShortage = $reviewRequiredTotal > $reviewReservedTotal;
                                                                @endphp
                                                                <div class="jo-review-parts-heading">
                                                                    <div class="jo-review-parts-title"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i><div><strong>Requested Parts ({{ count($reviewParts) }})</strong><small>Review the parts availability and expected fulfillment.</small></div></div>
                                                                    @if(count($reviewParts))
                                                                        <div class="jo-review-parts-progress" aria-label="{{ $reviewAvailablePartCount }} of {{ count($reviewParts) }} requested parts fully available">
                                                                            <span><i class="fa-solid fa-chart-pie" aria-hidden="true"></i> <strong>{{ $reviewAvailablePartCount }} / {{ count($reviewParts) }}</strong> Available</span>
                                                                            <span class="jo-review-parts-progress-track" aria-hidden="true"><span style="width: {{ $reviewAvailabilityPercent }}%"></span></span>
                                                                            <strong>{{ $reviewAvailabilityPercent }}%</strong>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                                @if(count($reviewParts))
                                                                    <div class="jo-review-parts-list" data-parts='@json($reviewParts)'>
                                                                        <div class="jo-review-parts-table-header" aria-hidden="true">
                                                                            <span>#</span><span>Part Description</span><span>Required</span><span>Reserved</span><span>Shortage</span><span>Status</span>
                                                                        </div>
                                                                        @foreach($reviewParts as $reviewPart)
                                                                            @php
                                                                                $allocation = $reviewAllocations->get($loop->index);
                                                                                $requiredQty = (int) ($reviewPart['quantity'] ?? 0);
                                                                                $reservedQty = $allocation !== null ? min($requiredQty, max(0, (int) ($allocation['reserved'] ?? 0))) : null;
                                                                                $shortageQty = $reservedQty === null ? null : max(0, $requiredQty - $reservedQty);
                                                                                $livePart = $reviewLiveStock->get($loop->index, []);
                                                                                $freeToAllocate = max(0, (int) ($livePart['available'] ?? 0));
                                                                                $ambiguousStock = (bool) ($livePart['ambiguous'] ?? false);
                                                                                $reviewAvailability = $reservedQty === null || $ambiguousStock ? 'Not Verified'
                                                                                    : ($shortageQty === 0 ? 'Sufficient'
                                                                                    : ($reservedQty > 0 ? 'Partial'
                                                                                    : ($freeToAllocate > 0 ? 'Not Allocated' : 'Unavailable')));
                                                                            @endphp
                                                                            <div class="jo-review-part-item">
                                                                                <span class="jo-review-part-index">{{ $loop->iteration }}</span>
                                                                                <strong class="jo-review-part-name">{{ $reviewPart['name'] }}</strong>
                                                                                <span class="jo-review-part-qty">{{ $reviewPart['quantity'] }} {{ $reviewPart['unit'] }}</span>
                                                                                <span class="jo-review-part-stock">{{ $reservedQty === null ? '—' : $reservedQty.' '.$reviewPart['unit'] }}</span>
                                                                                <span class="jo-review-part-shortage">{{ $shortageQty === null ? '—' : $shortageQty.' '.$reviewPart['unit'] }}</span>
                                                                                <span class="jo-review-part-availability {{ $reviewAvailability === 'Sufficient' ? 'is-available' : ($reviewAvailability === 'Partial' ? 'is-partial' : ($reviewAvailability === 'Unavailable' ? 'is-unavailable' : 'is-pending')) }}" title="{{ $reviewAvailability === 'Not Allocated' ? 'Free Warehouse stock exists but has not been allocated to this JO.' : 'Status is based on the JO allocation and uncommitted Warehouse stock; other JOs reservations are excluded.' }}">{{ $reviewAvailability }}</span>
                                                                            </div>
                                                                        @endforeach
                                                                    </div>
                                                                @else
                                                                    <p>No parts requested</p>
                                                                @endif
                                                                @if($reviewHasShortage)
                                                                    <div class="jo-review-shortage-advisory" role="note">
                                                                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                                                        <div><strong>Possible Completion Delay</strong><p>Some required parts are currently unavailable or only partially reserved. The estimated completion time may be affected.</p></div>
                                                                        <span><i class="fa-regular fa-clock" aria-hidden="true"></i> Submitted Duration <strong>{{ $jobOrder->formatted_estimated_duration ?: 'Not provided' }}</strong></span>
                                                                    </div>
                                                                @else
                                                                    <div class="jo-review-submitted-duration"><span><i class="fa-regular fa-clock" aria-hidden="true"></i> Submitted Duration</span><p>{{ $jobOrder->formatted_estimated_duration ?: 'Head estimate required' }}</p></div>
                                                                @endif
                                                            </div>
                                                        @if($jobOrder->audits->isNotEmpty())
                                                            <details class="jo-review-audit">
                                                                <summary>Audit History ({{ $jobOrder->audits->count() }})</summary>
                                                                <ol>
                                                                    @foreach($jobOrder->audits->sortBy(fn ($entry) => sprintf('%020d-%010d', $entry->created_at?->getTimestamp() ?? 0, $entry->id)) as $audit)
                                                                        <li><strong>{{ str($audit->action)->replace('_', ' ')->title() }}</strong> — {{ $audit->action === 'created' ? ($jobOrder->creator?->name ?? $audit->user?->name ?? 'Unknown creator') : ($audit->user?->name ?? 'Unknown actor') }}<small>{{ $audit->created_at?->format('M d, Y h:i A') }}{{ $audit->notes ? ' · '.$audit->notes : '' }}</small></li>
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
                                                                @if(trim((string) $jobOrder->part_needed) !== '' && $jobOrder->inventory_availability === 'partial')
                                                                    <div class="jo-head-duration-review jo-head-parts-decision">
                                                                        <div class="jo-review-step-heading">
                                                                            <span class="jo-review-step-number">02</span>
                                                                            <div><strong>Parts Release Decision</strong><p>Choose whether Warehouse may issue available parts while shortages are being purchased.</p></div>
                                                                        </div>
                                                                        <div class="jo-duration-decision-field">
                                                                            <label for="jo-parts-decision-{{ $jobOrder->id }}">Maintenance Head decision <span class="ui-required">*</span></label>
                                                                            <select id="jo-parts-decision-{{ $jobOrder->id }}" name="parts_issue_strategy" required aria-describedby="jo-parts-decision-help-{{ $jobOrder->id }}">
                                                                                <option value="" disabled @selected(!old('parts_issue_strategy'))>Select parts release decision...</option>
                                                                                <option value="complete_before_issuance" @selected(old('parts_issue_strategy') === 'complete_before_issuance')>Wait until all JO parts are available</option>
                                                                                <option value="issue_available_only" @selected(old('parts_issue_strategy') === 'issue_available_only')>Allow partial release of available parts</option>
                                                                            </select>
                                                                            <small id="jo-parts-decision-help-{{ $jobOrder->id }}">Approval reserves currently free stock for this JO. Warehouse deducts physical inventory only upon signed issuance.</small>
                                                                        </div>
                                                                    </div>
                                                                @endif
                                                                <div class="jo-head-authorization">
                                                                    <div class="jo-review-step-heading">
                                                                        <span class="jo-review-step-number">{{ trim((string) $jobOrder->part_needed) !== '' ? '03' : '02' }}</span>
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
                                                            <button type="button" class="jo-review-footer-close"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Close</button>
                                                            <button type="button" class="jo-review-back" hidden>Back to Approval</button>
                                                            <button type="button" class="jo-review-revision-trigger"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Return for Revision</button>
                                                            <button type="button" class="jo-review-reject-trigger"><i class="fa-regular fa-circle-xmark" aria-hidden="true"></i> Reject</button>
                                                            <button type="submit" form="jo-head-approve-{{ $jobOrder->id }}" class="primary-btn jo-review-footer-submit jo-review-approve-submit" disabled aria-disabled="true" title="Upload and crop a signature to enable approval"><i class="fa-solid fa-check" aria-hidden="true"></i> Approve Job Order</button>
                                                            <button type="submit" form="jo-head-revision-{{ $jobOrder->id }}" class="secondary-btn jo-review-footer-submit jo-review-revision-submit" hidden>Confirm Revision</button>
                                                            <button type="submit" form="jo-head-reject-{{ $jobOrder->id }}" class="warning-btn jo-review-footer-submit jo-review-reject-submit" hidden>Confirm Rejection</button>
                                                        </div>
                                                    </div>
                                                </details>
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
                    data-server-filter="{{ $recordView === 'history' ? 'true' : 'false' }}"
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

        <nav class="jo-new-stepper" aria-label="Job Order form sections">
            <span class="jo-new-step is-current"><span class="jo-new-step-index">1</span><span><strong>Job Details</strong><small>Bus and maintenance work</small></span></span>
            <span class="jo-new-step"><span class="jo-new-step-index">2</span><span><strong>Mechanic Assignment</strong><small>Qualified technicians</small></span></span>
            <span class="jo-new-step"><span class="jo-new-step-index">3</span><span><strong>Estimated Duration</strong><small>Repair time estimate</small></span></span>
            <span class="jo-new-step"><span class="jo-new-step-index">4</span><span><strong>Requested Parts</strong><small>Parts and stock</small></span></span>
        </nav>

        @include('Maintenance.partials.job-order-form-sections', ['mode' => 'create'])

        <script type="application/json" id="jobBusDriverMap">@json(
            $availableBuses->mapWithKeys(fn ($bus) => [
                $bus->bus_no => optional($bus->authorizedDrivers->first())->driver_name,
            ])
        )</script>
    </x-ui.form-modal>

    <x-ui.form-modal
        id="editJobModal"
        title="Edit Job Order"
        description="Update the maintenance work, assigned mechanics, duration, and requested parts"
        icon="fa-screwdriver-wrench"
        size="large"
        form-id="editJobForm"
        action="#"
        method="PUT"
        submit-text="Update Job Order"
        submit-id="updateJobOrderBtn"
        submit-icon="fa-floppy-disk"
        close-id="closeEditJobModal"
        cancel-id="cancelEditJobModal"
        :confirm="true"
        confirm-title="Update Job Order?"
        confirm-message="Are you sure you want to update this Job Order?"
        confirm-button="Yes, Update Job Order"
        confirm-type="update"
        class="jo-create-modal-overlay jo-edit-modal-overlay"
    >
        <input type="hidden" name="job_order_no" id="edit_job_order_no">
        <input type="hidden" name="maintenance_type" id="edit_maintenance_type" value="Repair">
        <nav class="jo-new-stepper" aria-label="Job Order form sections">
            <span class="jo-new-step is-current"><span class="jo-new-step-index">1</span><span><strong>Job Details</strong><small>Bus and maintenance work</small></span></span>
            <span class="jo-new-step"><span class="jo-new-step-index">2</span><span><strong>Mechanic Assignment</strong><small>Qualified technicians</small></span></span>
            <span class="jo-new-step"><span class="jo-new-step-index">3</span><span><strong>Estimated Duration</strong><small>Repair time estimate</small></span></span>
            <span class="jo-new-step"><span class="jo-new-step-index">4</span><span><strong>Requested Parts</strong><small>Parts and stock</small></span></span>
        </nav>

        @include('Maintenance.partials.job-order-form-sections', ['mode' => 'edit'])

        <script type="application/json" id="editJobBusDriverMap">@json(
            $buses->mapWithKeys(fn ($bus) => [
                $bus->bus_no => optional($bus->authorizedDrivers->first())->driver_name,
            ])
        )</script>
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
