<x-layout.app title="FROMS - Warehouse Item Requests" :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Main-styles/form-components.css',
    'resources/css/Maintenance/item-requests.css',
    'resources/css/Warehouse/item-requests.css',
    'resources/js/Warehouse/item-requests.js'
]">
<div class="app">
    <x-layout.sidebar department="Warehouse" />
    <main class="main maintenance-ir-page warehouse-ir-page">
        <x-layout.topbar title="Item Requests" subtitle="Review Item Requests submitted by Maintenance" />

        <section class="ir-summary" aria-label="Warehouse Item Request overview">
            <div class="ir-summary-card"><span class="ir-summary-icon"><i class="fa-solid fa-boxes-stacked"></i></span><div><span>Total Requests</span><strong>{{ $totalCount }}</strong><small>All received Item Requests</small></div></div>
            <div class="ir-summary-card"><span class="ir-summary-icon ir-summary-icon-blue"><i class="fa-solid fa-paper-plane"></i></span><div><span>Submitted</span><strong>{{ $submittedCount }}</strong><small>Awaiting verification</small></div></div>
            <div class="ir-summary-card"><span class="ir-summary-icon ir-summary-icon-green"><i class="fa-solid fa-list-check"></i></span><div><span>Active</span><strong>{{ $activeCount }}</strong><small>Open Item Requests</small></div></div>
            <div class="ir-summary-card"><span class="ir-summary-icon ir-summary-icon-red"><i class="fa-solid fa-clock-rotate-left"></i></span><div><span>History</span><strong>{{ $historyCount }}</strong><small>Closed Item Requests</small></div></div>
        </section>

        <section class="ir-record-card">
            <header class="ir-record-header">
                <div><h2>Item Request Records</h2><p>Review JO-linked parts requests received from Maintenance.</p></div>
                <span class="ir-record-count">{{ $requests->total() }} {{ \Illuminate\Support\Str::plural('record', $requests->total()) }}</span>
            </header>
            <div class="ir-toolbar">
                <nav class="ir-tabs" aria-label="Item Request view">
                    <a href="{{ route('warehouse.item-requests', ['view' => 'active']) }}" class="ir-active-tab {{ $view === 'active' ? 'is-active' : '' }}" data-allow-partial-navigation="true"><i class="fa-solid fa-list-check"></i> Active</a>
                    <a href="{{ route('warehouse.item-requests', ['view' => 'history']) }}" class="ir-active-tab {{ $view === 'history' ? 'is-active' : '' }}" data-allow-partial-navigation="true"><i class="fa-solid fa-clock-rotate-left"></i> History</a>
                </nav>
                <div class="gct-table-toolbar-row">
                    <x-ui.table-toolbar action="{{ route('warehouse.item-requests') }}" data-server-filter="true"
                        search-label="Search Item Requests" search-placeholder="Search IR, JO, bus, or part..."
                        :filter-count="(int) (request()->filled('status') && request('status') !== 'All Statuses')"
                        filter-title="Filter Item Requests"
                        :sort-options="['created_at' => 'Request Date', 'id' => 'IR Number', 'status' => 'Status']"
                        default-sort="created_at" default-direction="desc">
                        <x-slot:hidden><input type="hidden" name="view" value="{{ $view }}"></x-slot:hidden>
                        <x-slot:filters>
                            <x-ui.table-toolbar-field label="Status" name="status" default="All Statuses">
                                <option value="All Statuses">All Statuses</option>
                                @foreach(($view === 'history' ? ['Issued', 'Completed', 'Cancelled', 'Rejected'] : ['Submitted', 'Partially Issued', 'Approved', 'Preparing', 'Partial', 'Pending']) as $status)
                                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                                @endforeach
                            </x-ui.table-toolbar-field>
                        </x-slot:filters>
                    </x-ui.table-toolbar>
                </div>
            </div>
            <div class="ir-table-wrap">
                <table class="ir-table">
                    <thead><tr><th>IR ID</th><th>ITEM</th><th>QTY</th><th>ON HAND</th><th>INVENTORY</th><th>POLICY</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
                    <tbody>
                    @forelse($requests as $ir)
                    @php
                        $stock = $stockByRequest->get($ir->id, ['status' => 'none', 'parts' => []]);
                        $stockParts = collect($stock['parts'] ?? []);
                        $requestedCount = collect($ir->requested_parts ?? [])->sum(fn ($p) => (int) ($p['quantity'] ?? 0));
                        $firstStock = $stockParts->first();
                        $shortPartsCount = $stockParts->filter(fn ($part) => (int) ($part['shortage'] ?? 0) > 0)->count();
                    @endphp
                    @php
                        $stockIsMixed = $stockParts->count() > 1;
                        $joRequirements = $ir->jobOrder
                            ? collect(app(\App\Services\PartParser::class)->parsePartText((string) $ir->jobOrder->part_needed))
                            : collect();
                    @endphp
                    @php
                        $waitForAllParts = $ir->jobOrder?->parts_issue_strategy !== 'issue_available_only';
                        $totalJoRequired = $joRequirements->sum(fn ($part) => (int) ($part['quantity'] ?? 0));
                        $totalIrRequested = collect($ir->requested_parts ?? [])->sum(fn ($part) => (int) ($part['quantity'] ?? 0));
                        $issueBlockedByPolicy = $waitForAllParts
                            && ($totalIrRequested < $totalJoRequired || ($stock['status'] ?? '') !== 'sufficient');
                    @endphp
                    <tr>
                        <td class="warehouse-ir-reference"><div class="warehouse-ir-reference-stack"><span class="warehouse-ir-id-badge">IR-{{ $ir->created_at?->format('Y') ?? '0000' }}-{{ str_pad((string) $ir->id, 4, '0', STR_PAD_LEFT) }}</span>@if($ir->jobOrder?->job_order_no)<span class="warehouse-ir-jo-tag" title="Linked Job Order"><i class="fa-solid fa-wrench" aria-hidden="true"></i> {{ $ir->jobOrder->job_order_no }}</span>@endif</div></td>
                        <td>
                            @php($parts = collect($ir->requested_parts ?? []))
                            @if($parts->isNotEmpty())
                                <span class="ir-part-name warehouse-ir-item-label">{{ $parts->first()['name'] ?? '—' }}</span>
                                @if($parts->count() > 1)
                                    <details class="ir-parts-details warehouse-ir-item-more"><summary>+{{ $parts->count() - 1 }} more <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                                        <div class="ir-parts-popover">
                                            @foreach($parts as $part)
                                                <div><span>{{ $part['name'] ?? '—' }}</span><strong>{{ $part['quantity'] ?? 0 }} {{ $part['unit'] ?? '' }}</strong></div>
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                            @else — @endif
                        </td>
                        <td><strong>{{ $stockParts->count() === 1 ? (($firstStock['quantity'] ?? 0).' '.($firstStock['unit'] ?? '')) : ($stockParts->count().' items') }}</strong></td>
                        <td>
                            <strong>{{ $firstStock['on_hand'] ?? 0 }} {{ $firstStock['unit'] ?? '' }}</strong>
                            @if($stockIsMixed)
                                <details class="ir-parts-details warehouse-ir-onhand-more">
                                    <summary>+{{ $stockParts->count() - 1 }} more <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                                    <div class="ir-parts-popover">
                                        @foreach($stockParts as $stockPart)
                                            <div><span>{{ $stockPart['name'] ?? '—' }}</span><strong>{{ $stockPart['on_hand'] ?? 0 }} {{ $stockPart['unit'] ?? '' }}</strong></div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </td>
                        <td><span title="IR allocation readiness, not full JO stock coverage" class="warehouse-ir-stock-status {{ ($stock['status'] ?? '') === 'sufficient' ? 'is-available' : (($stock['status'] ?? '') === 'partial' ? 'is-partial' : 'is-unavailable') }}"><i class="fa-solid {{ ($stock['status'] ?? '') === 'sufficient' ? 'fa-circle-check' : (($stock['status'] ?? '') === 'partial' ? 'fa-triangle-exclamation' : 'fa-circle-xmark') }}" aria-hidden="true"></i>{{ ($stock['status'] ?? '') === 'sufficient' ? 'Available' : (($stock['status'] ?? '') === 'partial' ? 'Partial' : 'Unavailable') }}</span></td>
                        <td>
                            <span class="warehouse-ir-policy {{ $ir->jobOrder?->parts_issue_strategy === 'issue_available_only' ? 'is-partial' : 'is-complete' }}">
                                {{ $ir->jobOrder?->parts_issue_strategy === 'issue_available_only' ? 'Issue Available Parts' : 'Wait Until Complete' }}
                            </span>
                        </td>
                        <td>
                            <span class="warehouse-ir-process-status {{ $ir->status === 'Issued' ? 'is-issued' : 'is-submitted' }}">
                                <i class="fa-solid {{ $ir->status === 'Issued' ? 'fa-circle-check' : 'fa-paper-plane' }}"></i>
                                {{ $ir->status }}
                            </span>
                        </td>
                        <td>
                            <x-ui.action-button type="view" title="View Item Request" aria-label="View Item Request" data-ir-view
                                data-ir-number="IR-{{ $ir->created_at?->format('Y') ?? '0000' }}-{{ str_pad((string) $ir->id, 4, '0', STR_PAD_LEFT) }}"
                                data-ir-job="{{ $ir->jobOrder?->job_order_no ?? '—' }}"
                                data-ir-bus="{{ $ir->jobOrder?->bus_no ?? '—' }}"
                                data-ir-requester="{{ $ir->requester?->name ?? '—' }}"
                                data-ir-date="{{ $ir->created_at?->format('M d, Y · h:i A') ?? '—' }}"
                                data-ir-parts-count="{{ $parts->count() }}"
                                data-ir-status="{{ $ir->status }}"
                                data-ir-release-policy="{{ $ir->jobOrder?->parts_issue_strategy === 'issue_available_only' ? 'Issue Available Parts' : 'Wait Until Complete' }}"
                                data-ir-verify-url="{{ route('warehouse.item-requests.verify', $ir) }}"
                                data-ir-jo-parts="{{ json_encode($joRequirements->values()->all()) }}"
                                data-ir-issue-url="{{ route('warehouse.item-requests.issue', $ir) }}"
                                data-ir-can-issue="{{ in_array($ir->status, ['Submitted', 'Partially Issued'], true) ? '1' : '0' }}"
                                data-ir-parts="{{ $parts->map(fn ($p) => ($p['name'] ?? '—').' — '.($p['quantity'] ?? 0).' '.($p['unit'] ?? ''))->implode(' | ') }}"
                                data-ir-remarks="{{ $ir->remarks ?? '' }}" />
                            @if(in_array($ir->status, ['Issued', 'Partially Issued'], true))
                                <x-ui.action-button type="view" title="Download Latest Signed Withdrawal Slip" aria-label="Download Signed Withdrawal Slip" :href="route('warehouse.item-requests.signed-slip', $ir)" />
                            @endif
                            @if(in_array($ir->status, ['Submitted', 'Partially Issued'], true) && auth()->user()?->hasSystemPermission('warehouse', 'edit'))
                                <x-ui.action-button type="issue" icon="fa-file-pen" :disabled="$issueBlockedByPolicy" :title="$issueBlockedByPolicy ? 'Waiting for all Job Order parts per Maintenance Head policy' : 'Prepare Issuance Slip'" aria-label="Prepare Issuance Slip" data-ir-prepare
                                    data-ir-number="IR-{{ $ir->created_at?->format('Y') ?? '0000' }}-{{ str_pad((string) $ir->id, 4, '0', STR_PAD_LEFT) }}"
                                    data-ir-job="{{ $ir->jobOrder?->job_order_no ?? '—' }}"
                                    data-ir-bus="{{ $ir->jobOrder?->bus_no ?? '—' }}"
                                    data-ir-mechanics="{{ $ir->jobOrder?->assigned_mechanic ?? '—' }}"
                                    data-ir-verify-url="{{ route('warehouse.item-requests.verify', $ir) }}"
                                    data-ir-issue-url="{{ route('warehouse.item-requests.issue', $ir) }}"
                                    data-ir-remarks="{{ $ir->remarks ?? '' }}" />
                            @endif
                        </td>
                    </tr>
                    @empty
                        <tr><td colspan="8"><div class="ir-empty"><span><i class="fa-solid fa-box-open"></i></span><strong>{{ $view === 'history' ? 'No Item Request history' : 'No active Item Requests' }}</strong><p>{{ $view === 'history' ? 'Closed requests will appear here.' : 'Submitted requests from Maintenance will appear here.' }}</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="ir-footer"><span class="warehouse-ir-stock-time">Inventory availability accounts for approved JO reservations. Open View for detailed verification and issuance.</span>{{ $requests->links() }}</div>
        </section>
    </main>
</div>

<div id="warehouseItemRequestDetails" class="ir-modal-overlay" aria-hidden="true">
    <div class="ir-modal warehouse-ir-details-modal" role="dialog" aria-modal="true" aria-labelledby="warehouseIrDetailsTitle">
        <header class="ir-modal-header">
            <div class="ir-modal-heading"><span class="ir-modal-symbol"><i class="fa-solid fa-file-lines"></i></span>
                <div><h2 id="warehouseIrDetailsTitle">Item Request Details</h2><p>Review requested parts, inventory availability and JO requirements.</p></div>
            </div>
            <span id="warehouse-ir-status" class="warehouse-ir-view-status">—</span>
            <button type="button" class="ir-icon-button" data-ir-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </header>
        <div class="ir-modal-body warehouse-ir-details-body">
            <div class="warehouse-ir-reference-strip">
                <div><span>IR ID</span><strong id="warehouse-ir-number">—</strong></div>
                <div><span>Linked JO</span><strong id="warehouse-ir-job">—</strong></div>
                <div><span>Requested By</span><strong id="warehouse-ir-requester">—</strong></div>
                <div><span>Date Requested</span><strong id="warehouse-ir-date">—</strong></div>
                <div><span>Department</span><strong>Maintenance</strong></div>
                <div><span>Total Parts</span><strong id="warehouse-ir-parts-count">—</strong></div>
            </div>
            <div class="warehouse-ir-view-secondary">
                <span>Bus: <strong id="warehouse-ir-bus">—</strong></span>
                <span>Release Policy: <strong id="warehouse-ir-release-policy">—</strong></span>
            </div>
            <section class="warehouse-ir-view-section" aria-labelledby="warehouseIrViewStockTitle">
                <div class="warehouse-ir-verify-heading">
                    <div class="warehouse-ir-view-section-heading"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i><div><h3 id="warehouseIrViewStockTitle">Item Request Parts</h3><p>Parts &amp; Stock Verification — live physical inventory and approved allocations</p></div></div>
                    <button type="button" class="ir-secondary-button" id="warehouseIrRefreshStock"><i class="fa-solid fa-rotate"></i> Recheck Stock</button>
                </div>
                <div class="warehouse-ir-stock-table-wrap">
                    <table class="warehouse-ir-stock-table">
                        <thead><tr><th>PART NAME</th><th>JO REQUIRED</th><th>IR QUANTITY</th><th>ON HAND</th><th>ISSUABLE</th><th>IR SHORTAGE</th><th>STATUS</th></tr></thead>
                        <tbody id="warehouse-ir-stock-rows"><tr><td colspan="7" class="warehouse-ir-stock-empty">Checking current inventory...</td></tr></tbody>
                    </table>
                </div>
                <p id="warehouse-ir-stock-time" class="warehouse-ir-stock-time"></p>
                <p id="warehouse-ir-stock-message" class="warehouse-ir-stock-message" role="status"></p>
            </section>
            <section class="warehouse-ir-view-section warehouse-ir-view-jo-section" aria-labelledby="warehouseIrJoCoverageTitle">
                <div class="warehouse-ir-view-section-heading"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i><div><h3 id="warehouseIrJoCoverageTitle">Job Order Parts Summary</h3><p>Compare what the Job Order needs with what this Item Request includes</p></div></div>
                <div class="warehouse-ir-stock-table-wrap">
                    <table class="warehouse-ir-stock-table warehouse-ir-jo-table">
                        <thead><tr><th>PART</th><th>JO NEEDS</th><th>IR REQUESTED</th><th>REMAINING</th></tr></thead>
                        <tbody id="warehouse-ir-jo-coverage-rows"><tr><td colspan="4" class="warehouse-ir-stock-empty">Checking linked Job Order...</td></tr></tbody>
                    </table>
                </div>
                <p class="warehouse-ir-view-caution"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Remaining quantities are not included in this Item Request. Another IR or Purchase Request may already cover them.</p>
            </section>
            <section class="warehouse-ir-view-section warehouse-ir-details-remarks" aria-label="Item Request remarks">
                <div class="warehouse-ir-view-section-heading"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i><div><h3>Remarks</h3></div></div>
                <p id="warehouse-ir-remarks" class="ir-detail-text"></p>
            </section>
        </div>
        <footer class="ir-modal-footer">

            <button type="button" class="ir-secondary-button" data-ir-close>Close</button>
        </footer>
    </div>
</div>

<div id="warehouseIrSlipModal" class="ir-modal-overlay" aria-hidden="true">
    <div class="ir-modal warehouse-ir-slip-modal" role="dialog" aria-modal="true" aria-labelledby="warehouseIrSlipTitle">
        <header class="ir-modal-header warehouse-ir-slip-header">
            <div class="ir-modal-heading">
                <span class="ir-modal-symbol"><i class="fa-solid fa-file-signature"></i></span>
                <div><span class="warehouse-ir-slip-eyebrow">GCT TRANSPORT SERVICES, INC.</span>
                    <h2 id="warehouseIrSlipTitle">Stock Withdrawal / Issuance Slip</h2>
                    <p>Prepare the parts handover document — signatures required before issuance</p>
                </div>
            </div>
            <button type="button" class="ir-icon-button" data-ir-slip-close aria-label="Close issuance slip"><i class="fa-solid fa-xmark"></i></button>
        </header>
        <div class="ir-modal-body warehouse-ir-slip-body">
            <div class="warehouse-ir-slip-fields">
                <div><span>IR ID</span><strong id="ir-slip-number">—</strong></div>
                <div><span>Linked JO</span><strong id="ir-slip-job">—</strong></div>
                <div><span>Requested By / Mechanic</span><strong id="ir-slip-mechanic">—</strong></div>
                <div><span>Date</span><strong id="ir-slip-date">—</strong></div>
                <div><span>Department</span><strong>Maintenance</strong></div>
                <div><span>Total Parts</span><strong id="ir-slip-total-parts">—</strong></div>
            </div>
            <div class="warehouse-ir-slip-meta-line">
                <span>Bus: <strong id="ir-slip-bus">—</strong></span>
                <span>Release Policy: <strong id="ir-slip-release-policy">—</strong></span>
            </div>
            <div class="warehouse-ir-stock-table-wrap">
                <table class="warehouse-ir-stock-table warehouse-ir-slip-table">
                    <thead><tr><th>STOCK NO.</th><th>ITEM DESCRIPTION</th><th>QTY NEEDED</th><th>PURPOSE / BUS NO.</th><th>QTY ISSUED</th><th>MECHANIC SIGNATURE</th><th>REMARKS</th></tr></thead>
                    <tbody id="ir-slip-lines"><tr><td colspan="7" class="warehouse-ir-stock-empty">Checking current stock...</td></tr></tbody>
                </table>
            </div>
            <div class="warehouse-ir-slip-signatures">
                <div><span>Approved By — Mechanic</span><div class="warehouse-ir-signature-line"></div><small>Handwritten signature and date</small></div>
                <div><span>Issued By</span><div class="warehouse-ir-signature-line"></div><small>Handwritten signature and date</small></div>
            </div>
            <section class="warehouse-ir-confirm-section">
                <h3><i class="fa-solid fa-clipboard-check"></i> Confirm Signed Handover</h3>
                <p>Upload the signed withdrawal slip after physical handover. Issuance quantities are fixed by the approved Item Request and available allocation.</p>
                <form id="warehouseIrSignedIssueForm" method="POST" action="#" enctype="multipart/form-data"
                      data-confirm-form
                      data-confirm-title="Confirm Signed Physical Handover?"
                      data-confirm-message="Confirm signed handover? Issued stock will be deducted. Remaining quantities stay pending."
                      data-confirm-button="Confirm Issuance &amp; Deduct Stock"
                      data-confirm-type="warning">
                    @csrf
                    <div id="ir-slip-quantities" class="warehouse-ir-confirm-quantities"></div>
                    <div class="warehouse-ir-confirm-fields">
                        <label>Receiving Mechanic / Representative <span>*</span>
                            <input name="received_by" id="ir-slip-received-by" required maxlength="255" placeholder="Full name of person who collected the parts">
                        </label>
                        <label>Signed Withdrawal Slip (JPG, PNG, PDF; max 10 MB) <span>*</span>
                            <input type="file" name="signed_slip" required accept=".jpg,.jpeg,.png,.pdf">
                        </label>
                    </div>
                    <label class="warehouse-ir-confirm-check">
                        <input type="checkbox" name="handover_confirmed" value="1" required>
                        I confirm these quantities were physically handed over and the attached slip contains the mechanic's and Warehouse issuer's signatures.
                    </label>
                    <button type="submit" id="ir-slip-confirm" class="ir-primary-button" disabled><i class="fa-solid fa-circle-check"></i> Confirm Issuance &amp; Deduct Stock</button>
                </form>
            </section>
            <p id="ir-slip-message" class="ir-note"><i class="fa-solid fa-circle-info"></i><span>This is a preparation and print preview only. No parts are deducted or marked Issued. Record the signed handover before processing inventory issuance.</span></p>
        </div>
        <footer class="ir-modal-footer">
            <button type="button" class="ir-secondary-button" data-ir-slip-close>Close</button>
            <button type="button" class="ir-primary-button" id="ir-slip-print" disabled><i class="fa-solid fa-print"></i> Print Slip</button>
        </footer>
    </div>
</div>
</x-layout.app>
