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

        @if(session("success"))<div class="ir-alert ir-alert-success" role="status">{{ session("success") }}</div>@endif
        @if($errors->any())<div class="ir-alert ir-alert-error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
        <section class="ir-summary" aria-label="Warehouse Item Request overview">
            <div class="ir-summary-card"><span class="ir-summary-icon"><i class="fa-solid fa-boxes-stacked"></i></span><div><span>Total Requests</span><strong>{{ $totalCount }}</strong><small>All received Item Requests</small></div></div>
            <div class="ir-summary-card"><span class="ir-summary-icon ir-summary-icon-blue"><i class="fa-solid fa-paper-plane"></i></span><div><span>Submitted</span><strong>{{ $submittedCount }}</strong><small>Awaiting verification</small></div></div>
            <div class="ir-summary-card"><span class="ir-summary-icon ir-summary-icon-green"><i class="fa-solid fa-list-check"></i></span><div><span>In Progress</span><strong>{{ $activeCount }}</strong><small>Open Item Requests</small></div></div>
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
                                @foreach(($view === 'history' ? ['Issued', 'Completed', 'Cancelled', 'Rejected'] : ['Submitted', 'Approved', 'Preparing', 'Partial', 'Pending']) as $status)
                                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                                @endforeach
                            </x-ui.table-toolbar-field>
                        </x-slot:filters>
                    </x-ui.table-toolbar>
                </div>
            </div>
            <div class="ir-table-wrap">
                <table class="ir-table">
                    <thead><tr><th>IR ID</th><th>JOB ORDER</th><th>BUS</th><th>REQUESTED PARTS</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
                    <tbody>
                    @forelse($requests as $ir)
                    @php($stock = $stockByRequest->get($ir->id, ['status' => 'none', 'parts' => []]))
                    @php($parts = collect($ir->requested_parts ?? []))
                    <tr>
                        <td class="warehouse-ir-reference"><span class="warehouse-ir-id-badge">IR-{{ $ir->created_at?->format('Y') ?? '0000' }}-{{ str_pad((string) $ir->id, 4, '0', STR_PAD_LEFT) }}</span></td>
                        <td><span class="warehouse-ir-row-meta">{{ $ir->jobOrder?->job_order_no ?? '—' }}</span></td>
                        <td><span class="warehouse-ir-row-meta"><i class="fa-solid fa-bus" aria-hidden="true"></i> {{ $ir->jobOrder?->bus_no ?? '—' }}</span></td>
                        <td>
                            <strong class="warehouse-ir-item-label">{{ $parts->first()['name'] ?? '—' }}</strong>
                            @if($parts->count() > 1)
                                <details class="ir-parts-details warehouse-ir-item-more">
                                    <summary>+{{ $parts->count() - 1 }} more</summary>
                                    <div class="ir-parts-popover">
                                        @foreach($parts as $part)
                                            <div><span>{{ $part['name'] ?? '—' }}</span><strong>{{ $part['quantity'] ?? 0 }} {{ $part['unit'] ?? '' }}</strong></div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </td>
                        <td><span class="warehouse-ir-process-status {{ $ir->status === 'Issued' ? 'is-issued' : 'is-submitted' }}">{{ $ir->status }}</span></td>
                        <td class="warehouse-ir-actions">
                            <button type="button" class="warehouse-ir-review" data-ir-view
                                data-ir-number="IR-{{ $ir->created_at?->format('Y') ?? '0000' }}-{{ str_pad((string) $ir->id, 4, '0', STR_PAD_LEFT) }}"
                                data-ir-job="{{ $ir->jobOrder?->job_order_no ?? '—' }}"
                                data-ir-bus="{{ $ir->jobOrder?->bus_no ?? '—' }}"
                                data-ir-status="{{ $ir->status }}"
                                data-ir-verify-url="{{ route('warehouse.item-requests.verify', $ir) }}"
                                data-ir-issue-url="{{ route('warehouse.item-requests.issue', $ir) }}"
                                data-ir-can-issue="{{ $ir->status === 'Submitted' && ($stock['status'] ?? '') === 'sufficient' ? '1' : '0' }}"
                                data-ir-parts="{{ $parts->map(fn ($p) => ($p['name'] ?? '—').' — '.($p['quantity'] ?? 0).' '.($p['unit'] ?? ''))->implode(' | ') }}"
                                data-ir-remarks="{{ $ir->remarks ?? '' }}"><i class="fa-solid fa-eye" aria-hidden="true"></i> {{ $view === 'history' ? 'View' : 'Review' }}</button>
                            <div class="warehouse-ir-hidden-actions" hidden>
                              @if($ir->status === 'Issued')
                                  <x-ui.action-button type="view" title="Download Signed Withdrawal Slip" aria-label="Download Signed Withdrawal Slip" :href="route('warehouse.item-requests.signed-slip', $ir)" />
                              @endif
                              @if($ir->status === 'Submitted' && auth()->user()?->hasSystemPermission('warehouse', 'edit'))
                                  <x-ui.action-button type="issue" icon="fa-file-pen" title="Prepare Issuance Slip" aria-label="Prepare Issuance Slip" data-ir-prepare
                                      data-ir-number="IR-{{ $ir->created_at?->format('Y') ?? '0000' }}-{{ str_pad((string) $ir->id, 4, '0', STR_PAD_LEFT) }}"
                                      data-ir-job="{{ $ir->jobOrder?->job_order_no ?? '—' }}"
                                      data-ir-bus="{{ $ir->jobOrder?->bus_no ?? '—' }}"
                                      data-ir-mechanics="{{ $ir->jobOrder?->assigned_mechanic ?? '—' }}"
                                      data-ir-verify-url="{{ route('warehouse.item-requests.verify', $ir) }}"
                                      data-ir-remarks="{{ $ir->remarks ?? '' }}" />
                              @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                        <tr><td colspan="6"><div class="ir-empty"><span><i class="fa-solid fa-box-open"></i></span><strong>{{ $view === 'history' ? 'No Item Request history' : 'No active Item Requests' }}</strong><p>{{ $view === 'history' ? 'Closed requests will appear here.' : 'Submitted requests from Maintenance will appear here.' }}</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="ir-footer"><span class="warehouse-ir-stock-time">Stock is a live reference, not reserved inventory. Open View for detailed verification and issuance.</span>{{ $requests->links() }}</div>
        </section>
    </main>
</div>

<div id="warehouseItemRequestDetails" class="ir-modal-overlay" aria-hidden="true">
    <div class="ir-modal warehouse-ir-details-modal" role="dialog" aria-modal="true" aria-labelledby="warehouseIrDetailsTitle">
        <header class="ir-modal-header">
            <div class="ir-modal-heading"><span class="ir-modal-symbol"><i class="fa-solid fa-box-open"></i></span>
                <div><h2 id="warehouseIrDetailsTitle">Item Request Details</h2><p>Maintenance request information for Warehouse review</p></div>
            </div>
            <button type="button" class="ir-icon-button" data-ir-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </header>
        <div class="ir-modal-body warehouse-ir-details-body">
            <div class="ir-details-grid warehouse-ir-details-meta">
                <div><span>Item Request</span><strong id="warehouse-ir-number">—</strong></div>
                <div><span>Linked Job Order</span><strong id="warehouse-ir-job">—</strong></div>
                <div><span>Bus</span><strong id="warehouse-ir-bus">—</strong></div>
                <div><span>Status</span><strong id="warehouse-ir-status">—</strong></div>
            </div>
            <div class="ir-field">
                <div class="warehouse-ir-verify-heading"><label>Requested Items &amp; Stock Verification</label><button type="button" class="ir-secondary-button" id="warehouseIrRefreshStock"><i class="fa-solid fa-rotate"></i> Recheck Stock</button></div>
                <p class="ir-form-hint warehouse-ir-stock-hint"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Live inventory reference. Actual stock is rechecked before issuance.</p>
                <div class="warehouse-ir-stock-table-wrap">
                    <table class="warehouse-ir-stock-table">
                        <thead><tr><th>PART</th><th>REQUIRED</th><th>ON HAND</th><th>SHORTAGE</th><th>AVAILABILITY</th></tr></thead>
                        <tbody id="warehouse-ir-stock-rows"><tr><td colspan="5" class="warehouse-ir-stock-empty">Select View to check current stock.</td></tr></tbody>
                    </table>
                </div>
                <p id="warehouse-ir-stock-time" class="warehouse-ir-stock-time"></p>
                <p id="warehouse-ir-stock-message" class="warehouse-ir-stock-message" role="status"></p>
            </div>
            <div class="ir-field warehouse-ir-details-remarks"><label>Remarks</label><p id="warehouse-ir-remarks" class="ir-detail-text"></p></div>
        </div>
        <footer class="ir-modal-footer">
            <div id="warehouseIrReviewActions" class="warehouse-ir-review-actions"></div>
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
                <div><span>Item Request</span><strong id="ir-slip-number">—</strong></div>
                <div><span>Job Order</span><strong id="ir-slip-job">—</strong></div>
                <div><span>Department</span><strong>Maintenance</strong></div>
                <div><span>Bus / Purpose</span><strong id="ir-slip-bus">—</strong></div>
                <div><span>Requested By / Mechanic</span><strong id="ir-slip-mechanic">—</strong></div>
                <div><span>Date</span><strong id="ir-slip-date">—</strong></div>
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
                <p>Only complete issuances are currently supported. After the mechanic and Warehouse personnel sign the printed slip, upload its photo or PDF to complete the transaction.</p>
                <form id="warehouseIrSignedIssueForm" method="POST" action="#" enctype="multipart/form-data">
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
