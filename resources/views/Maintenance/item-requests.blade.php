<x-layout.app title="FROMS - Item Requests" :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Main-styles/form-components.css',
    'resources/css/Maintenance/item-requests.css',
    'resources/js/Maintenance/item-requests.js'
]">
<div class="app">
    <x-layout.sidebar department="Maintenance" />
    <main class="main maintenance-ir-page">
        <x-layout.topbar title="Item Requests" subtitle="Manage JO-linked parts requests for Warehouse" />

        @if(session('success'))
            <div class="ir-alert ir-alert-success" role="status">{{ session('success') }}</div>
        @endif
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
                            <i class="fa-solid fa-eye" aria-hidden="true"></i> Review IR
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
                <table class="ir-table ir-records-table">
                    <thead>
                        <tr><th>IR NUMBER</th><th>JOB ORDER</th><th>BUS</th><th>REQUESTED PARTS</th><th>IR STATUS</th><th>DATE & TIME</th><th>ACTIONS</th></tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $ir)
                        <tr>
                            <td><strong class="ir-number maintenance-ir-id-badge">IR-{{ $ir->created_at?->format('Y') ?? '0000' }}-{{ str_pad((string) $ir->id, 4, '0', STR_PAD_LEFT) }}</strong></td>
                            <td><strong>{{ $ir->jobOrder?->job_order_no ?? '—' }}</strong></td>
                            <td><span class="ir-bus-tag"><i class="fa-solid fa-bus"></i> {{ $ir->jobOrder?->bus_no ?? '—' }}</span></td>
                            <td>
                                @php($parts = collect($ir->requested_parts ?? []))
                                @if($parts->isNotEmpty())
                                    <span class="ir-part-name">{{ $parts->first()['name'] ?? '—' }}</span>
                                    @if($parts->count() > 1)
                                        <details class="ir-parts-details">
                                            <summary>+{{ $parts->count() - 1 }} more <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                                            <div class="ir-parts-popover">
                                                @foreach($parts as $part)
                                                    <div><span>{{ $part['name'] ?? '—' }}</span><strong>{{ $part['quantity'] ?? 0 }} {{ $part['unit'] ?? '' }}</strong></div>
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif
                                @else
                                    <span>—</span>
                                @endif
                            </td>
                            <td><span class="ir-status">{{ $ir->status }}</span></td>
                            <td class="ir-date"><strong class="maintenance-ir-date-primary">{{ $ir->created_at?->copy()->timezone(config('app.business_timezone', 'Asia/Manila'))->format('M d, Y') ?? '—' }}</strong><small>{{ $ir->created_at?->copy()->timezone(config('app.business_timezone', 'Asia/Manila'))->format('h:i A') ?? '' }}</small></td><td><button class="ir-view-button" type="button" data-ir-view data-ir-number="IR-{{ $ir->created_at?->format('Y') ?? '0000' }}-{{ str_pad((string) $ir->id, 4, '0', STR_PAD_LEFT) }}" data-ir-job="{{ $ir->jobOrder?->job_order_no ?? '—' }}" data-ir-bus="{{ $ir->jobOrder?->bus_no ?? '—' }}" data-ir-status="{{ $ir->status }}" data-ir-date="{{ $ir->created_at?->copy()->timezone(config('app.business_timezone', 'Asia/Manila'))->format('M d, Y · h:i A') ?? '—' }}" data-ir-parts="{{ collect($ir->requested_parts ?? [])->map(fn ($p) => ($p['name'] ?? '—').' — '.($p['quantity'] ?? 0).' '.($p['unit'] ?? ''))->implode(' | ') }}" data-ir-remarks="{{ $ir->remarks ?? '' }}"><i class="fa-solid fa-eye"></i> View</button></td>
                        </tr>
                        @empty
                        <tr><td colspan="7">
                            <div class="ir-empty">
                                <span><i class="fa-solid fa-box-open"></i></span>
                                <strong>{{ $view === 'history' ? 'No historical Item Requests' : 'No Item Requests in progress' }}</strong>
                                <p>{{ $view === 'history' ? 'Completed or closed requests will appear here.' : 'Submitted Item Requests awaiting Warehouse fulfillment will appear here.' }}</p>
                            </div>
                        </td></tr>
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
    <div class="ir-modal ir-view-modal" role="dialog" aria-modal="true" aria-labelledby="viewItemRequestTitle">
        <header class="ir-modal-header ir-view-modal-header">
            <div class="ir-modal-heading">
                <span class="ir-modal-symbol"><i class="fa-solid fa-box-open"></i></span>
                <div><h2 id="viewItemRequestTitle">Item Request Details</h2><p>Request information and linked Job Order</p></div>
            </div>
            <button type="button" class="ir-icon-button" data-ir-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </header>
        <div class="ir-modal-body ir-view-modal-body">
            <section class="ir-view-summary-grid" aria-label="Request information">
                <div class="ir-view-info-card"><span><i class="fa-regular fa-file-lines"></i> IR Number</span><strong id="ir-detail-number"></strong></div>
                <div class="ir-view-info-card"><span><i class="fa-solid fa-clipboard-list"></i> Job Order</span><strong id="ir-detail-job"></strong></div>
                <div class="ir-view-info-card"><span><i class="fa-solid fa-bus"></i> Bus</span><strong id="ir-detail-bus"></strong></div>
                <div class="ir-view-info-card"><span><i class="fa-regular fa-calendar"></i> Date &amp; Time Created</span><strong id="ir-detail-date"></strong></div>
            </section>
            <section class="ir-view-panel" aria-labelledby="ir-view-parts-heading">
                <header class="ir-view-panel-header"><div><h3 id="ir-view-parts-heading"><i class="fa-solid fa-boxes-stacked"></i> Requested Parts</h3><p>Parts submitted to Warehouse for checking and fulfillment.</p></div><span id="ir-detail-parts-count" class="ir-view-count"></span></header>
                <div id="ir-detail-parts" class="ir-view-parts-list"></div>
            </section>
            <section class="ir-view-panel ir-view-status-panel" aria-labelledby="ir-view-status-heading">
                <div class="ir-view-status-row"><div><h3 id="ir-view-status-heading"><i class="fa-solid fa-warehouse"></i> Warehouse Status</h3><p id="ir-detail-status-description">Current request processing status.</p></div><strong class="ir-view-status" id="ir-detail-status"></strong></div>
            </section>
            <section class="ir-view-panel" aria-labelledby="ir-view-remarks-heading">
                <header class="ir-view-panel-header"><div><h3 id="ir-view-remarks-heading"><i class="fa-regular fa-message"></i> Remarks</h3></div></header>
                <div class="ir-view-remarks"><p id="ir-detail-remarks" class="ir-detail-text"></p></div>
            </section>
        </div>
        <footer class="ir-modal-footer ir-view-modal-footer"><button type="button" class="ir-primary-button" data-ir-close>Close</button></footer>
    </div>
</div>
@if(auth()->user()?->hasSystemPermission('maintenance', 'edit'))
<div id="itemRequestModal" class="ir-modal-overlay" aria-hidden="true">
    <div class="ir-modal" role="dialog" aria-modal="true" aria-labelledby="itemRequestModalTitle">
        <header class="ir-modal-header">
            <div class="ir-modal-heading">
                <span class="ir-modal-symbol"><i class="fa-solid fa-box-open"></i></span>
                <div><span class="ir-modal-eyebrow">MAINTENANCE / RESOURCE REQUEST</span><h2 id="itemRequestModalTitle">New Item Request</h2><p>Review available IR parts and missing PR quantities</p></div>
            </div>
            <button type="button" class="ir-icon-button" id="closeItemRequestModal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </header>
        <form method="POST" action="{{ route('item-requests.store') }}">
            @csrf
            <div class="ir-modal-body">
                <div class="ir-form-section">
                    <h3><span>01</span> Job Order Information <small>Source details</small></h3>
                    <div class="ir-field">
                        <label for="item_request_job_order_display">Approved Job Order <span>*</span></label>
                        <input type="hidden" id="item_request_job_order" name="job_order_id" value="{{ old('job_order_id', '') }}" required>
                        <div id="item_request_job_order_display" class="ir-linked-jo-display" aria-live="polite">Linked automatically from the selected Job Order</div>
                    </div>
                    <div class="ir-preview-grid">
                        <div><span class="ir-preview-card-icon"><i class="fa-solid fa-bus-simple"></i></span><span>Bus Number</span><strong id="ir-preview-bus">Select a JO</strong></div>
                        <div><span class="ir-preview-card-icon"><i class="fa-solid fa-user-gear"></i></span><span>Assigned Mechanics</span><strong id="ir-preview-mechanic">Select a JO</strong></div>
                    </div>
                    <div class="ir-jo-authorization">
                        <div class="ir-jo-authorization-heading"><strong>Maintenance Head Authorization</strong><span class="ir-jo-approved-badge"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> JO Approved</span></div>
                        <div class="ir-jo-authorization-grid">
                            <div class="ir-jo-authorizer"><span>Approved By</span><strong id="ir-preview-approver">—</strong><small>Maintenance Head</small></div>
                            <div class="ir-jo-signature"><img id="ir-preview-signature" alt="Saved JO approval signature" hidden><span id="ir-preview-signature-placeholder">JO signature unavailable</span></div>
                        </div>
                    </div>
                </div>
                <div class="ir-form-section">
                    <h3><span>02</span> Requested Items <small>Automatically sourced from the JO</small></h3>
                    <p class="ir-form-hint">The system splits available stock into an IR and missing quantities into a PR. Warehouse confirms inventory before physical issuance.</p>
                    <div class="ir-preview-table-wrap">
                        <table class="ir-preview-table">
                            <thead><tr><th>Item / Part</th><th>Suggested Qty</th><th>Availability</th></tr></thead>
                            <tbody id="ir-preview-parts"><tr><td colspan="3" class="ir-preview-placeholder">Select an approved JO to review its required parts.</td></tr></tbody>
                        </table>
                    </div>
                </div>
                <div class="ir-form-section ir-pr-preview-section">
                    <h3><span>03</span> Missing Parts — Purchase Request <small>Created automatically with the IR</small></h3>
                    <p class="ir-form-hint">Only the shortage quantities are sent to Purchase. Existing procurement requests are not duplicated.</p>
                    <div class="ir-preview-table-wrap">
                        <table class="ir-preview-table">
                            <thead><tr><th>Item / Part</th><th>Missing Qty</th><th>Request</th></tr></thead>
                            <tbody id="ir-preview-missing"><tr><td colspan="3" class="ir-preview-placeholder">Choose an approved Job Order.</td></tr></tbody>
                        </table>
                    </div>
                </div>
                <div class="ir-form-section">
                    <h3><span>04</span> Request Details <small>Optional instructions</small></h3>
                    <div class="ir-field">
                        <label for="item_request_remarks">Remarks <small>(Optional)</small></label>
                        <textarea id="item_request_remarks" name="remarks" rows="3" maxlength="2000" placeholder="Additional instructions for Warehouse...">{{ old('remarks') }}</textarea>
                    </div>
                </div>
                <div class="ir-note"><i class="fa-solid fa-shield-halved"></i><span><strong>Warehouse verification required.</strong> Submitting these requests does not physically issue or deduct inventory. Warehouse verifies allocations and follows the Maintenance Head release policy.</span></div>
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
