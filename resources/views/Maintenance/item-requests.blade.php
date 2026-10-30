<x-layout.app title="FROMS - Item Requests" :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Main-styles/form-components.css',
    'resources/css/Maintenance/item-requests.css',
          'resources/css/Maintenance/request-modal-shared.css',
    'resources/js/Maintenance/item-requests.js'
]">
<div class="app">
    <x-layout.sidebar department="Maintenance" />
    <main class="main maintenance-ir-page">
        <x-layout.topbar title="Item Requests" subtitle="Manage JO-linked parts requests for Warehouse" />

        @if($errors->any())
            <div class="ir-alert ir-alert-error" role="alert">
                <strong>Please check your request.</strong>
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        <section class="ir-summary" aria-label="Request overview">
            <div class="ir-summary-card"><span class="ir-summary-icon"><i class="fa-solid fa-boxes-stacked"></i></span><div><span>Total Requests</span><strong>{{ $totalCount }}</strong><small>Item requests recorded</small></div></div>
            <div class="ir-summary-card"><span class="ir-summary-icon ir-summary-icon-blue"><i class="fa-solid fa-paper-plane"></i></span><div><span>Submitted</span><strong>{{ $submittedCount }}</strong><small>Awaiting Warehouse review</small></div></div>
            <div class="ir-summary-card"><span class="ir-summary-icon ir-summary-icon-green"><i class="fa-solid fa-list-check"></i></span><div><span>In Progress</span><strong>{{ $activeCount }}</strong><small>Open Item Requests</small></div></div>
            <div class="ir-summary-card"><span class="ir-summary-icon ir-summary-icon-red"><i class="fa-solid fa-clock-rotate-left"></i></span><div><span>History</span><strong>{{ $historyCount }}</strong><small>Closed Item Requests</small></div></div>
        </section>

        <section class="ir-record-card">
            <header class="ir-record-header">
                <div>
                    <h2>Item Request Records</h2>
                    <p>Track requested parts, linked Job Orders, and Warehouse processing.</p>
                </div>
            </header>
            <div class="ir-toolbar">
                <nav class="ir-tabs" aria-label="Item Request record view">
                    <a href="{{ route('item-requests', ['view' => 'pending']) }}" class="ir-active-tab {{ $view === 'pending' ? 'is-active' : '' }}" data-allow-partial-navigation="true"><i class="fa-solid fa-clock"></i> Pending Requests</a>
                    <a href="{{ route('item-requests', ['view' => 'active']) }}" class="ir-active-tab {{ $view === 'active' ? 'is-active' : '' }}" data-allow-partial-navigation="true"><i class="fa-solid fa-list-check"></i> In Progress</a>
                    <a href="{{ route('item-requests', ['view' => 'history']) }}" class="ir-active-tab {{ $view === 'history' ? 'is-active' : '' }}" data-allow-partial-navigation="true"><i class="fa-solid fa-clock-rotate-left"></i> History</a>
                </nav>
                <div class="gct-table-toolbar-row">
                    <x-ui.table-toolbar
                        action="{{ route('item-requests') }}"
                        search-label="Search Item Requests"
                        search-placeholder="Search IR no., JO no., bus, or part..."
                        :filter-count="(int) (request()->filled('status') && request('status') !== 'All Statuses')"
                        filter-title="Filter Item Requests"
                        :sort-options="['created_at' => 'Created Date', 'id' => 'IR Number', 'status' => 'Status']"
                        default-sort="created_at"
                        default-direction="desc"
                        data-server-filter="true">
                        <x-slot:hidden><input type="hidden" name="view" value="{{ $view }}"></x-slot:hidden>
                        <x-slot:filters>
                            <x-ui.table-toolbar-field label="Status" name="status" default="All Statuses">
                                <option value="All Statuses">All Statuses</option>
                                @foreach(($view === 'history' ? ['Issued', 'Completed', 'Cancelled', 'Rejected'] : ($view === 'pending' ? ['For Review', 'Waiting for Complete Stock'] : ['Submitted', 'Partially Issued', 'Approved', 'Preparing', 'Partial', 'Pending'])) as $status)
                                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                                @endforeach
                            </x-ui.table-toolbar-field>
                        </x-slot:filters>
                    </x-ui.table-toolbar>
                </div>
            </div>
            @if($view === 'pending')
            <div class="ir-table-wrap"><table class="ir-table ir-pending-table ir-split-table">
                <thead><tr><th>JOB ORDER</th><th>SUBMITTED AT</th><th>PARTS SPLIT</th><th>STOCK STATUS</th><th>IR STATUS</th><th>RELEASE POLICY</th><th>ACTIONS</th></tr></thead>
                <tbody>
                @forelse($pendingJobs as $jo)
                @php
                    $preview = $jobOrderPreviews->get($jo->id) ?? [];
                    $availableParts = $preview['parts'] ?? [];
                    $missingParts = $preview['missing_parts'] ?? [];
                    $availableCount = count($availableParts);
                    $missingCount = count($missingParts);
                    $stockLabel = $availableCount && $missingCount ? 'Partial Stock' : ($availableCount ? 'Available' : 'No Stock');
                    $releaseLabel = $jo->parts_issue_strategy === 'issue_available_only' ? 'Issue Available Only' : 'Wait Until Complete';
                    $latestSubmissionAudit = $jo->audits->first();
                    $submissionDate = $latestSubmissionAudit?->created_at ?? $jo->created_at;
                @endphp
                <tr class="ir-pending-record">
                    <td>
                        <div class="ir-split-jo"><strong class="ir-number maintenance-ir-id-badge">{{ $jo->job_order_no }}</strong></div>
                    </td>
                    <td><div class="ir-submitted-time"><strong>{{ $submissionDate?->timezone(config('app.business_timezone', 'Asia/Manila'))->format('M d, Y') ?? '—' }}</strong><small>{{ $submissionDate?->timezone(config('app.business_timezone', 'Asia/Manila'))->format('h:i A') ?? 'Unknown time' }}</small><small>{{ $latestSubmissionAudit ? ($latestSubmissionAudit->action === 'resubmitted' ? 'Resubmitted' : 'Submitted') : 'Created date (fallback)' }}</small></div></td>
                    <td>
                        <div class="ir-split-parts">
                            <span class="ir-split-chip is-ir"><i class="fa-solid fa-box" aria-hidden="true"></i> IR: {{ $availableCount }} {{ \Illuminate\Support\Str::plural('part', $availableCount) }}</span>
                            @if($missingCount > 0)
                                <span class="ir-split-chip is-pr"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> PR: {{ $missingCount }} {{ \Illuminate\Support\Str::plural('part', $missingCount) }}</span>
                            @else
                                <span class="ir-split-chip is-no-pr">No PR needed</span>
                            @endif
                        </div>
                        <details class="ir-split-breakdown">
                            <summary class="ir-split-part-preview" aria-label="Show available and missing parts for {{ $jo->job_order_no }}">
                                
                                <span class="ir-split-more">View breakdown <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></span>
                            </summary>
                            <div class="ir-split-popover">
                                <div class="ir-split-popover-heading">Parts Allocation</div>
                                <div class="ir-split-popover-section">
                                    <strong class="ir-split-popover-label is-ir"><i class="fa-solid fa-box" aria-hidden="true"></i> Available → IR ({{ $availableCount }})</strong>
                                    @forelse($availableParts as $part)
                                        <div class="ir-split-popover-line"><span>{{ $part['name'] ?? '—' }}</span><strong>{{ $part['quantity'] ?? 0 }} {{ $part['unit'] ?? '' }}</strong></div>
                                    @empty
                                        <p class="ir-split-popover-empty">No available parts</p>
                                    @endforelse
                                </div>
                                <div class="ir-split-popover-section">
                                    <strong class="ir-split-popover-label is-pr"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> Missing → PR ({{ $missingCount }})</strong>
                                    @forelse($missingParts as $part)
                                        <div class="ir-split-popover-line"><span>{{ $part['name'] ?? '—' }}</span><strong>{{ $part['quantity'] ?? 0 }} {{ $part['unit'] ?? '' }}</strong></div>
                                    @empty
                                        <p class="ir-split-popover-empty">No missing parts</p>
                                    @endforelse
                                </div>
                            </div>
                        </details>
                    </td>
                    <td><span class="ir-split-status {{ $missingCount ? ($availableCount ? 'is-partial' : 'is-empty') : 'is-available' }}">{{ $stockLabel }}</span></td>
                    <td><span class="ir-request-status is-review"><i class="fa-regular fa-clock" aria-hidden="true"></i> For Review</span></td>
                    <td><div class="ir-release-policy"><span class="ir-release-badge {{ $jo->parts_issue_strategy === 'issue_available_only' ? 'is-partial' : 'is-waiting' }}"><i class="fa-solid {{ $jo->parts_issue_strategy === 'issue_available_only' ? 'fa-circle-check' : 'fa-clock' }}" aria-hidden="true"></i> {{ $releaseLabel }}</span></div></td>
                    <td>
                        <button type="button" class="ir-view-button ir-split-review" data-ir-review-job="{{ $jo->id }}" data-ir-review-number="{{ $jo->job_order_no }}" data-ir-review-bus="{{ $jo->bus_no }}" title="Review available IR and missing PR quantities">
                            <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Review IR
                        </button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="ir-pending-empty-cell"><div class="ir-pending-empty"><span class="ir-pending-empty-icon"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i></span><strong>No pending Item Requests</strong><p>New recommendations will appear after an approved Job Order has available stock.</p></div></td></tr>
                @endforelse
                </tbody>
            </table></div>
            @else
            <div class="ir-table-wrap">
                <table class="ir-table ir-records-table ir-split-table ir-lifecycle-table">
                    <thead><tr><th>IR / JOB ORDER</th><th>SUBMITTED AT</th><th>REQUESTED PARTS</th><th>IR STATUS</th><th>RELEASE POLICY</th><th>ACTIONS</th></tr></thead>
                    <tbody>
                        @forelse($requests as $ir)
                        @php
                            $irParts = collect($ir->requested_parts ?? []);
                            $issueAvailable = $ir->jobOrder?->parts_issue_strategy === 'issue_available_only';
                            $prParts = collect($linkedPrPartsByJo->get($ir->jobOrder?->job_order_no, []));
                        @endphp
                        <tr class="ir-lifecycle-record">
                            <td>
                                <div class="ir-split-jo">
                                    <strong class="ir-number maintenance-ir-id-badge">IR-{{ $ir->created_at?->format('Y') ?? '0000' }}-{{ str_pad((string) $ir->id, 4, '0', STR_PAD_LEFT) }}</strong>
                                    <small class="ir-lifecycle-jo">{{ $ir->jobOrder?->job_order_no ?? 'JO unavailable' }}</small>
                                </div>
                            </td>
                            <td><div class="ir-submitted-time">
                                <strong>{{ $ir->created_at?->copy()->timezone(config('app.business_timezone', 'Asia/Manila'))->format('M d, Y') ?? '—' }}</strong>
                                <small>{{ $ir->created_at?->copy()->timezone(config('app.business_timezone', 'Asia/Manila'))->format('h:i A') ?? '—' }}</small>
                            </div></td>
                            <td>
                                <div class="ir-split-parts">
                                    <span class="ir-split-chip is-ir"><i class="fa-solid fa-box" aria-hidden="true"></i> IR: {{ $irParts->count() }} {{ \Illuminate\Support\Str::plural('part', $irParts->count()) }}</span>
                                    @if($prParts->isNotEmpty())
                                        <span class="ir-split-chip is-pr"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> PR: {{ $prParts->count() }} {{ \Illuminate\Support\Str::plural('part', $prParts->count()) }}</span>
                                    @else
                                        <span class="ir-split-chip is-no-pr">No linked PR</span>
                                    @endif
                                </div>
                                @if($irParts->isNotEmpty() || $prParts->isNotEmpty())
                                <details class="ir-split-breakdown">
                                    <summary class="ir-split-part-preview" aria-label="Show available IR parts and linked Purchase Request parts for IR {{ $ir->id }}">
                                        <span class="ir-split-more">View breakdown <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></span>
                                    </summary>
                                    <div class="ir-split-popover">
                                        <div class="ir-split-popover-heading">Parts Allocation</div>
                                        <div class="ir-split-popover-section">
                                            <strong class="ir-split-popover-label is-ir"><i class="fa-solid fa-box" aria-hidden="true"></i> Submitted → IR ({{ $irParts->count() }})</strong>
                                            @forelse($irParts as $part)
                                                <div class="ir-split-popover-line"><span>{{ $part['name'] ?? '—' }}</span><strong>{{ $part['quantity'] ?? 0 }} {{ $part['unit'] ?? '' }}</strong></div>
                                            @empty
                                                <p class="ir-split-popover-empty">No IR parts</p>
                                            @endforelse
                                        </div>
                                        <div class="ir-split-popover-section">
                                            <strong class="ir-split-popover-label is-pr"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> Linked → PR ({{ $prParts->count() }})</strong>
                                            @forelse($prParts as $part)
                                                <div class="ir-split-popover-line"><span>{{ $part['name'] ?? '—' }}</span><strong>{{ $part['quantity'] ?? 0 }} {{ $part['unit'] ?? '' }}</strong></div>
                                            @empty
                                                <p class="ir-split-popover-empty">No linked Purchase Request recorded</p>
                                            @endforelse
                                        </div>
                                    </div>
                                </details>
                                @endif
                            </td>
                            <td><span class="ir-status">{{ $ir->status }}</span></td>
                            <td>
                                @if($ir->jobOrder)
                                    <span class="ir-release-badge {{ $issueAvailable ? 'is-partial' : 'is-waiting' }}"><i class="fa-solid {{ $issueAvailable ? 'fa-circle-check' : 'fa-clock' }}" aria-hidden="true"></i> {{ $issueAvailable ? 'Issue Available Only' : 'Wait Until Complete' }}</span>
                                @else
                                    <span class="ir-split-policy">—</span>
                                @endif
                            </td>
                            <td><button class="ir-view-button" type="button" data-ir-view data-ir-number="IR-{{ $ir->created_at?->format('Y') ?? '0000' }}-{{ str_pad((string) $ir->id, 4, '0', STR_PAD_LEFT) }}" data-ir-job="{{ $ir->jobOrder?->job_order_no ?? '—' }}" data-ir-bus="{{ $ir->jobOrder?->bus_no ?? '—' }}" data-ir-status="{{ $ir->status }}" data-ir-date="{{ $ir->created_at?->copy()->timezone(config('app.business_timezone', 'Asia/Manila'))->format('M d, Y · h:i A') ?? '—' }}" data-ir-parts="{{ $irParts->map(fn ($p) => ($p['name'] ?? '—').' — '.($p['quantity'] ?? 0).' '.($p['unit'] ?? ''))->implode(' | ') }}" data-ir-pr-parts="{{ json_encode($prParts->values()->all()) }}" data-ir-approver="{{ $ir->jobOrder?->approver?->name ?? 'Not recorded' }}" data-ir-signature-url="{{ $ir->jobOrder ? route('job-orders.approval-signature', $ir->jobOrder, false) : '' }}" data-ir-requester="{{ $ir->requester?->name ?? 'Not recorded' }}" data-ir-remarks="{{ $ir->remarks ?? '' }}"><i class="fa-solid fa-eye" aria-hidden="true"></i> View IR</button></td>
                        </tr>
                        @empty
                        <tr><td colspan="6"><div class="ir-empty">
                            <span><i class="fa-solid fa-box-open"></i></span>
                            <strong>{{ $view === 'history' ? 'No historical Item Requests' : 'No Item Requests in progress' }}</strong>
                            <p>{{ $view === 'history' ? 'Completed or closed requests will appear here.' : 'Submitted Item Requests awaiting Warehouse fulfillment will appear here.' }}</p>
                        </div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="ir-footer">{{ $requests->links() }}</div>
            @endif
        </section>
    </main>
</div>

<div id="viewItemRequestModal" class="ir-modal-overlay" aria-hidden="true">
    <div class="ir-modal ir-view-modal ir-review-document-modal" role="dialog" aria-modal="true" aria-labelledby="viewItemRequestTitle">
        <header class="ir-modal-header">
            <div class="ir-modal-heading">
                <span class="ir-modal-symbol"><i class="fa-solid fa-box-open"></i></span>
                <div><span class="ir-modal-eyebrow">MAINTENANCE / RESOURCE REQUEST</span><h2 id="viewItemRequestTitle">View Item Request</h2><p>Submitted Item Request and linked Job Order details</p></div>
            </div>
            <button type="button" class="ir-icon-button" data-ir-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </header>
        <div class="ir-modal-body ir-view-modal-body ir-review-document-body">
            <section class="ir-review-jo-banner" aria-label="Submitted Item Request summary">
                <div class="ir-review-banner-main"><span>ITEM REQUEST NO.</span><strong id="ir-detail-number">—</strong><small>Linked JO: <span id="ir-detail-job">—</span></small></div>
                <div><span>IR STATUS</span><strong class="ir-view-status" id="ir-detail-status">—</strong></div>
                <div><span>BUS ID / PLATE</span><strong id="ir-detail-bus">—</strong></div>
                <div><span>SUBMITTED AT</span><strong id="ir-detail-date">—</strong></div>
            </section>
            <div class="ir-review-document-top">
                <section class="ir-review-section" aria-labelledby="ir-view-overview-title">
                    <header><h3 id="ir-view-overview-title"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i> Request Overview</h3></header>
                    <div class="ir-review-section-content">
                        <div class="ir-review-overview-grid">
                            <div><span>Request Type</span><strong>Job Order Parts Request</strong></div>
                            <div><span>Destination</span><strong>Warehouse Department</strong></div>
                        </div>
                        <p class="ir-review-muted" id="ir-detail-status-description">Current Warehouse processing status.</p>
                        <div class="ir-review-overview-grid ir-view-secondary-data">
                            <div><span>Job Order</span><strong id="ir-detail-overview-job">—</strong></div>
                            <div><span>Requested By</span><strong id="ir-detail-requester">—</strong></div>
                            <div><span>Request Date</span><strong id="ir-detail-request-date">—</strong></div>
                            <div><span>Department</span><strong>Maintenance</strong></div>
                        </div>
                    </div>
                </section>
                <section class="ir-review-section" aria-labelledby="ir-view-head-approval-title">
                    <header><h3 id="ir-view-head-approval-title"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Head Approval</h3></header>
                    <div class="ir-review-section-content ir-review-approval-grid">
                        <div class="ir-review-approver"><span>Approved By</span><strong id="ir-detail-approver">—</strong><small>Maintenance Head</small></div>
                        <div class="ir-review-signature"><span>Authorized Signature</span><img id="ir-detail-signature" alt="Saved Job Order approval signature" hidden><small id="ir-detail-signature-placeholder">Signature unavailable</small></div>
                    </div>
                </section>
            </div>
            <section class="ir-review-section ir-review-parts-section" aria-labelledby="ir-view-parts-heading">
                <header><h3 id="ir-view-parts-heading"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Parts &amp; Warehouse</h3><span id="ir-detail-parts-count" class="ir-review-section-note"></span></header>
                <div class="ir-review-parts-content ir-review-parts-two-column">
                    <div class="ir-review-parts-pane is-ir">
                        <div class="ir-review-portion-head"><strong><i class="fa-solid fa-box" aria-hidden="true"></i> Available Parts — Item Request (IR)</strong><span>For Warehouse</span></div>
                        <div class="ir-preview-table-wrap"><table class="ir-preview-table">
                            <thead><tr><th>PART NAME</th><th>REQUESTED QTY</th><th>STATUS</th></tr></thead>
                            <tbody id="ir-detail-available-parts"></tbody>
                        </table></div>
                    </div>
                    <div class="ir-review-parts-pane is-pr">
                        <div class="ir-review-portion-head is-pr"><strong><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> Missing Parts — Purchase Request (PR)</strong><span>For Procurement</span></div>
                        <div class="ir-preview-table-wrap"><table class="ir-preview-table">
                            <thead><tr><th>PART NAME</th><th>MISSING QTY</th><th>STATUS</th></tr></thead>
                            <tbody id="ir-detail-missing-parts"></tbody>
                        </table></div>
                    </div>
                </div>
            </section>
            <section class="ir-review-section ir-review-remarks-section" aria-labelledby="ir-view-remarks-heading">
                <header><h3 id="ir-view-remarks-heading"><i class="fa-regular fa-message" aria-hidden="true"></i> Request Remarks</h3><span class="ir-review-section-note">Read only</span></header>
                <div class="ir-review-section-content"><span class="ir-view-remarks-label">Instructions for Warehouse</span><p id="ir-detail-remarks" class="ir-detail-text">—</p></div>
            </section>
            <div class="ir-note"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>Submitting parts does not physically issue inventory. Warehouse fulfills the IR according to the approved release policy.</span></div>
        </div>
        <footer class="ir-modal-footer ir-view-modal-footer"><button type="button" class="ir-primary-button" data-ir-close>Close</button></footer>
    </div>
</div>

@if(auth()->user()?->hasSystemPermission('maintenance', 'edit'))
<div id="itemRequestModal" class="ir-modal-overlay" aria-hidden="true">
    <div class="ir-modal ir-review-document-modal" role="dialog" aria-modal="true" aria-labelledby="itemRequestModalTitle">
        <header class="ir-modal-header">
            <div class="ir-modal-heading">
                <span class="ir-modal-symbol"><i class="fa-solid fa-box-open"></i></span>
                <div><span class="ir-modal-eyebrow">MAINTENANCE / RESOURCE REQUEST</span><h2 id="itemRequestModalTitle">Review Item Request</h2><p>Review available IR parts and missing PR quantities</p></div>
            </div>
            <button type="button" class="ir-icon-button" id="closeItemRequestModal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </header>
        <form method="POST" action="{{ route('item-requests.store') }}">
            @csrf
            <div class="ir-modal-body ir-review-document-body">
                <section class="ir-review-jo-banner" aria-label="Approved Job Order summary">
                    <div class="ir-review-banner-main"><span>JOB ORDER NO.</span>
                        <input type="hidden" id="item_request_job_order" name="job_order_id" value="{{ old('job_order_id', '') }}" required>
                        <strong id="item_request_job_order_display" aria-live="polite">Linked automatically from the selected Job Order</strong>
                        <small>Approved Maintenance source</small>
                    </div>
                    <div><span>JO REVIEW STATUS</span><strong class="ir-jo-approved-badge"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Approved</strong></div>
                    <div><span>BUS ID / PLATE</span><strong id="ir-preview-bus">—</strong></div>
                    <div><span>ASSIGNED MECHANICS</span><strong id="ir-preview-mechanic">—</strong></div>
                </section>
                <div class="ir-review-document-top">
                    <section class="ir-review-section" aria-labelledby="ir-review-details-title">
                        <header><h3 id="ir-review-details-title"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i> Request Overview</h3></header>
                        <div class="ir-review-section-content">
                            <div class="ir-review-overview-grid">
                                <div><span>Request Type</span><strong>Job Order Parts Request</strong></div>
                                <div><span>Processing</span><strong>Warehouse IR + Shortage PR</strong></div>
                            </div>
                            <p class="ir-review-muted">Available stock is allocated to the Item Request; only missing quantities proceed to procurement.</p>
                        </div>
                    </section>
                    <section class="ir-review-section" aria-labelledby="ir-review-approval-title">
                        <header><h3 id="ir-review-approval-title"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Head Approval</h3></header>
                        <div class="ir-review-section-content ir-review-approval-grid">
                            <div class="ir-review-approver"><span>Approved By</span><strong id="ir-preview-approver">—</strong><small>Maintenance Head</small></div>
                            <div class="ir-review-signature"><span>Authorized Signature</span><img id="ir-preview-signature" alt="Saved JO approval signature" hidden><small id="ir-preview-signature-placeholder">JO signature unavailable</small></div>
                        </div>
                    </section>
                </div>
                <section class="ir-review-section ir-review-parts-section" aria-labelledby="ir-review-parts-title">
                    <header><h3 id="ir-review-parts-title"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Parts &amp; Warehouse</h3><span class="ir-review-section-note">Quantities calculated from the approved JO</span></header>
                    <div class="ir-review-parts-content ir-review-parts-two-column"><div class="ir-review-parts-pane is-ir">
                        <div class="ir-review-portion-head"><strong><i class="fa-solid fa-box" aria-hidden="true"></i> Available Parts — Item Request (IR)</strong><span>For Warehouse</span></div>
                        <div class="ir-preview-table-wrap"><table class="ir-preview-table">
                            <thead><tr><th>PART NAME</th><th>REQUESTED QTY</th><th>AVAILABILITY</th></tr></thead>
                            <tbody id="ir-preview-parts"><tr><td colspan="3" class="ir-preview-placeholder">Select an approved JO to review its required parts.</td></tr></tbody>
                        </table></div>
                        </div><div class="ir-review-parts-pane is-pr">
                        <div class="ir-review-portion-head is-pr"><strong><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> Missing Parts — Purchase Request (PR)</strong><span>For Procurement</span></div>
                        <div class="ir-preview-table-wrap"><table class="ir-preview-table">
                            <thead><tr><th>PART NAME</th><th>MISSING QTY</th><th>STATUS</th></tr></thead>
                            <tbody id="ir-preview-missing"><tr><td colspan="3" class="ir-preview-placeholder">Choose an approved Job Order.</td></tr></tbody>
                        </table></div></div>
                    </div>
                </section>
                <section class="ir-review-section ir-review-remarks-section" aria-labelledby="ir-review-remarks-title">
                    <header><h3 id="ir-review-remarks-title"><i class="fa-regular fa-message" aria-hidden="true"></i> Request Remarks</h3><span class="ir-review-section-note">Optional</span></header>
                    <div class="ir-review-section-content"><label for="item_request_remarks">Instructions for Warehouse</label>
                        <textarea id="item_request_remarks" name="remarks" rows="2" maxlength="2000" placeholder="Additional instructions for Warehouse...">{{ old('remarks') }}</textarea></div>
                </section>
                <div class="ir-note"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>Submitting IR and PR does not physically issue or deduct inventory. Warehouse verifies allocations and follows the Maintenance Head release policy.</span></div>
            </div>
            <script type="application/json" id="ir-job-previews">@json($jobOrderPreviews)</script>
            <footer class="ir-modal-footer">
                <button type="button" class="ir-secondary-button" id="cancelItemRequestModal">Cancel</button>
                <button type="submit" class="ir-primary-button"><i class="fa-solid fa-paper-plane"></i> Submit IR &amp; PR</button>
            </footer>
        </form>
    </div>
</div>
@endif
</x-layout.app>
