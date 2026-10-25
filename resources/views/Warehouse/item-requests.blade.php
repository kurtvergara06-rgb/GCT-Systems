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
                    <thead><tr><th>IR NUMBER</th><th>JOB ORDER</th><th>BUS</th><th>ITEM</th><th>QTY</th><th>ON HAND</th><th>INVENTORY</th><th>IR STATUS</th><th>ACTIONS</th></tr></thead>
                    <tbody>
                    @forelse($requests as $ir)
                    @php($stock = $stockByRequest->get($ir->id, ['status' => 'none', 'parts' => []]))
                    @php($stockParts = collect($stock['parts'] ?? []))
                    @php($requestedCount = collect($ir->requested_parts ?? [])->sum(fn ($p) => (int) ($p['quantity'] ?? 0)))
                    @php($firstStock = $stockParts->first())
                    <tr>
                        <td><strong class="ir-number">IR-{{ str_pad((string) $ir->id, 5, '0', STR_PAD_LEFT) }}</strong></td>
                        <td><strong>{{ $ir->jobOrder?->job_order_no ?? '—' }}</strong></td>
                        <td><span class="ir-bus-tag"><i class="fa-solid fa-bus"></i> {{ $ir->jobOrder?->bus_no ?? '—' }}</span></td>
                        <td>
                            @php($parts = collect($ir->requested_parts ?? []))
                            @if($parts->isNotEmpty())
                                <strong class="ir-part-name">{{ $parts->first()['name'] ?? '—' }}</strong>
                                @if($parts->count() > 1)
                                    <details class="ir-parts-details"><summary>+{{ $parts->count() - 1 }} more</summary>
                                        <div class="ir-parts-popover">
                                            @foreach($parts as $part)
                                                <div><span>{{ $part['name'] ?? '—' }}</span><strong>{{ $part['quantity'] ?? 0 }} {{ $part['unit'] ?? '' }}</strong></div>
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                            @else — @endif
                        </td>
                        <td><strong>{{ $requestedCount }}</strong> total</td>
                        <td><strong>{{ $firstStock['available'] ?? 0 }}</strong> {{ $firstStock['unit'] ?? '' }} @if($stockParts->count() > 1)<span class="warehouse-ir-more-stock">+{{ $stockParts->count() - 1 }} items</span>@endif</td>
                        <td><span class="warehouse-ir-stock-status {{ ($stock['status'] ?? '') === 'sufficient' ? 'is-available' : (($stock['status'] ?? '') === 'partial' ? 'is-partial' : 'is-unavailable') }}">{{ ($stock['status'] ?? '') === 'sufficient' ? 'Available' : (($stock['status'] ?? '') === 'partial' ? 'Partial' : 'Unavailable') }}</span></td>
                        <td><span class="ir-status">{{ $ir->status }}</span></td>
                        <td>
                            <button type="button" class="ir-view-button" data-ir-view
                                data-ir-number="IR-{{ str_pad((string) $ir->id, 5, '0', STR_PAD_LEFT) }}"
                                data-ir-job="{{ $ir->jobOrder?->job_order_no ?? '—' }}"
                                data-ir-bus="{{ $ir->jobOrder?->bus_no ?? '—' }}"
                                data-ir-status="{{ $ir->status }}"
                                data-ir-verify-url="{{ route('warehouse.item-requests.verify', $ir) }}"
                                data-ir-issue-url="{{ route('warehouse.item-requests.issue', $ir) }}"
                                data-ir-can-issue="{{ $ir->status === 'Submitted' && ($stock['status'] ?? '') === 'sufficient' ? '1' : '0' }}"
                                data-ir-parts="{{ $parts->map(fn ($p) => ($p['name'] ?? '—').' — '.($p['quantity'] ?? 0).' '.($p['unit'] ?? ''))->implode(' | ') }}"
                                data-ir-remarks="{{ $ir->remarks ?? '' }}"><i class="fa-solid fa-eye"></i> View</button>
                        </td>
                    </tr>
                    @empty
                        <tr><td colspan="8"><div class="ir-empty"><span><i class="fa-solid fa-box-open"></i></span><strong>{{ $view === 'history' ? 'No Item Request history' : 'No active Item Requests' }}</strong><p>{{ $view === 'history' ? 'Closed requests will appear here.' : 'Submitted requests from Maintenance will appear here.' }}</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="ir-footer"><span class="warehouse-ir-stock-time">On hand shows the first part only; open View for the complete per-item stock check.</span>{{ $requests->links() }}</div>
        </section>
    </main>
</div>

<div id="warehouseItemRequestDetails" class="ir-modal-overlay" aria-hidden="true">
    <div class="ir-modal" role="dialog" aria-modal="true" aria-labelledby="warehouseIrDetailsTitle">
        <header class="ir-modal-header">
            <div class="ir-modal-heading"><span class="ir-modal-symbol"><i class="fa-solid fa-box-open"></i></span>
                <div><h2 id="warehouseIrDetailsTitle">Item Request Details</h2><p>Maintenance request information for Warehouse review</p></div>
            </div>
            <button type="button" class="ir-icon-button" data-ir-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </header>
        <div class="ir-modal-body">
            <div class="ir-details-grid">
                <div><span>Item Request</span><strong id="warehouse-ir-number">—</strong></div>
                <div><span>Linked Job Order</span><strong id="warehouse-ir-job">—</strong></div>
                <div><span>Bus</span><strong id="warehouse-ir-bus">—</strong></div>
                <div><span>Status</span><strong id="warehouse-ir-status">—</strong></div>
            </div>
            <div class="ir-field">
                <div class="warehouse-ir-verify-heading"><label>Requested Items &amp; Stock Verification</label><button type="button" class="ir-secondary-button" id="warehouseIrRefreshStock"><i class="fa-solid fa-rotate"></i> Recheck Stock</button></div>
                <p class="ir-form-hint">This is a live stock reference, not an authorization to issue or reserve parts.</p>
                <div class="warehouse-ir-stock-table-wrap">
                    <table class="warehouse-ir-stock-table">
                        <thead><tr><th>PART</th><th>REQUIRED</th><th>ON HAND</th><th>SHORTAGE</th><th>AVAILABILITY</th></tr></thead>
                        <tbody id="warehouse-ir-stock-rows"><tr><td colspan="5" class="warehouse-ir-stock-empty">Select View to check current stock.</td></tr></tbody>
                    </table>
                </div>
                <p id="warehouse-ir-stock-time" class="warehouse-ir-stock-time"></p>
                <p id="warehouse-ir-stock-message" class="warehouse-ir-stock-message" role="status"></p>
            </div>
            <div class="ir-field"><label>Remarks</label><p id="warehouse-ir-remarks" class="ir-detail-text"></p></div>
            <div class="ir-note"><i class="fa-solid fa-circle-info"></i><span>Warehouse rechecks live stock at issuance. Complete requests can be issued only after physical handover. Partial requests require a separate Head decision; missing parts must be purchased through PR.</span></div>
        </div>
        <footer class="ir-modal-footer">
            @if(auth()->user()?->hasSystemPermission('warehouse', 'edit'))
            <form id="warehouseIrIssueForm" method="POST" action="#" class="warehouse-ir-issue-form" hidden>
                @csrf
                <button type="submit" id="warehouseIrIssueButton" class="ir-primary-button" disabled><i class="fa-solid fa-box-open"></i> Confirm Physical Issuance</button>
            </form>
            @endif
            <button type="button" class="ir-secondary-button" data-ir-close>Close</button>
        </footer>
    </div>
</div>
</x-layout.app>
