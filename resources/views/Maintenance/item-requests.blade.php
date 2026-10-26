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
            <div class="ir-summary-card"><span class="ir-summary-icon ir-summary-icon-green"><i class="fa-solid fa-list-check"></i></span><div><span>Active</span><strong>{{ $activeCount }}</strong><small>Open Item Requests</small></div></div>
            <div class="ir-summary-card"><span class="ir-summary-icon ir-summary-icon-red"><i class="fa-solid fa-clock-rotate-left"></i></span><div><span>History</span><strong>{{ $historyCount }}</strong><small>Closed Item Requests</small></div></div>
        </section>

        <section class="ir-record-card">
            <header class="ir-record-header">
                <div>
                    <h2>Item Request Records</h2>
                    <p>Track requested parts, linked Job Orders, and Warehouse processing.</p>
                </div>
                @if(auth()->user()?->hasSystemPermission('maintenance', 'edit'))
                    <button type="button" class="ir-primary-button" id="openItemRequestModal" @disabled($eligibleJobs->isEmpty())>
                        <i class="fa-solid fa-plus"></i> New IR
                    </button>
                @endif
            </header>
            <div class="ir-toolbar">
                <nav class="ir-tabs" aria-label="Item Request record view">
                    <a href="{{ route('item-requests', ['view' => 'pending']) }}" class="ir-active-tab {{ $view === 'pending' ? 'is-active' : '' }}" data-allow-partial-navigation="true"><i class="fa-solid fa-clock"></i> Pending Requests <span>({{ $eligibleJobs->count() }})</span></a>
                    <a href="{{ route('item-requests', ['view' => 'active']) }}" class="ir-active-tab {{ $view === 'active' ? 'is-active' : '' }}" data-allow-partial-navigation="true"><i class="fa-solid fa-list-check"></i> Active</a>
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
                                @foreach(($view === 'history' ? ['Issued', 'Completed', 'Cancelled', 'Rejected'] : ($view === 'pending' ? ['Pending Review', 'Head Decision Required'] : ['Submitted', 'Approved', 'Preparing', 'Partial', 'Pending'])) as $status)
                                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                                @endforeach
                            </x-ui.table-toolbar-field>
                        </x-slot:filters>
                    </x-ui.table-toolbar>
                </div>
            </div>
            @if($view === 'pending')
            <p class="ir-form-hint" style="padding:0 16px">Approved Job Orders appear here automatically when Warehouse has available stock. Review and submit to create an actual Item Request. Stock is rechecked when you submit.</p>
            <div class="ir-table-wrap"><table class="ir-table"><thead><tr><th>JOB ORDER</th><th>BUS</th><th>AVAILABLE ITEMS</th><th>STATUS</th><th>ACTIONS</th></tr></thead><tbody>
            @forelse($pendingJobs as $jo)
            @php
                $suggestedParts = $jobOrderPreviews->get($jo->id)['parts'] ?? [];
                $totalRequested = app(\App\Services\PartParser::class)->parsePartText((string) $jo->part_needed);
                $hasShortage = collect($totalRequested)->sum('quantity') > collect($suggestedParts)->sum('quantity');
                $needsHeadRelease = $hasShortage && $jo->parts_issue_strategy !== 'issue_available_only';
            @endphp
            <tr><td><strong>{{ $jo->job_order_no }}</strong></td><td><span class="ir-bus-tag"><i class="fa-solid fa-bus"></i> {{ $jo->bus_no }}</span></td><td>@foreach($suggestedParts as $part)<div>{{ $part['name'] }} — {{ $part['quantity'] }} {{ $part['unit'] }}</div>@endforeach</td><td><span class="ir-stock-badge {{ $needsHeadRelease ? 'ir-stock-partial' : 'ir-stock-available' }}">{{ $needsHeadRelease ? 'Head Decision Required' : 'Pending Review' }}</span></td><td><button type="button" class="ir-primary-button" data-ir-review-job="{{ $jo->id }}" @disabled($needsHeadRelease) title="{{ $needsHeadRelease ? 'The Maintenance Head must authorize partial release in the Job Order before an IR can be submitted.' : 'Review proposed available quantities' }}"><i class="fa-solid fa-eye"></i> Review IR</button></td></tr>
            @empty
            <tr><td colspan="5" class="ir-preview-placeholder">No pending IR suggestions. New suggestions appear after JO approval when stock is available.</td></tr>
            @endforelse
            </tbody></table></div>
            @else
            <div class="ir-table-wrap">
                <table class="ir-table">
                    <thead>
                        <tr><th>IR NUMBER</th><th>JOB ORDER</th><th>BUS</th><th>REQUESTED PARTS</th><th>STATUS</th><th>DATE CREATED</th><th>ACTIONS</th></tr>
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
                                            <summary>+{{ $parts->count() - 1 }} more</summary>
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
                            <td class="ir-date">{{ $ir->created_at?->format('M d, Y') }}</td><td><button class="ir-view-button" type="button" data-ir-view data-ir-number="IR-{{ $ir->created_at?->format('Y') ?? '0000' }}-{{ str_pad((string) $ir->id, 4, '0', STR_PAD_LEFT) }}" data-ir-job="{{ $ir->jobOrder?->job_order_no ?? '—' }}" data-ir-bus="{{ $ir->jobOrder?->bus_no ?? '—' }}" data-ir-status="{{ $ir->status }}" data-ir-parts="{{ collect($ir->requested_parts ?? [])->map(fn ($p) => ($p['name'] ?? '—').' — '.($p['quantity'] ?? 0).' '.($p['unit'] ?? ''))->implode(' | ') }}" data-ir-remarks="{{ $ir->remarks ?? '' }}"><i class="fa-solid fa-eye"></i> View</button></td>
                        </tr>
                        @empty
                        <tr><td colspan="7">
                            <div class="ir-empty">
                                <span><i class="fa-solid fa-box-open"></i></span>
                                <strong>{{ $view === 'history' ? 'No historical Item Requests' : 'No active Item Requests' }}</strong>
                                <p>{{ $view === 'history' ? 'Completed or closed requests will appear here.' : 'Create an Item Request from an approved Job Order to begin Warehouse verification.' }}</p>
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
    <div class="ir-modal" role="dialog" aria-modal="true" aria-labelledby="viewItemRequestTitle">
        <header class="ir-modal-header"><div class="ir-modal-heading"><span class="ir-modal-symbol"><i class="fa-solid fa-box-open"></i></span><div><h2 id="viewItemRequestTitle">Item Request Details</h2><p>Request information and linked Job Order</p></div></div><button type="button" class="ir-icon-button" data-ir-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button></header>
        <div class="ir-modal-body"><div class="ir-details-grid"><div><span>IR Number</span><strong id="ir-detail-number"></strong></div><div><span>Job Order</span><strong id="ir-detail-job"></strong></div><div><span>Bus</span><strong id="ir-detail-bus"></strong></div><div><span>Status</span><strong id="ir-detail-status"></strong></div></div><div class="ir-field"><label>Requested Parts</label><p id="ir-detail-parts" class="ir-detail-text"></p></div><div class="ir-field"><label>Remarks</label><p id="ir-detail-remarks" class="ir-detail-text"></p></div></div>
        <footer class="ir-modal-footer"><button type="button" class="ir-primary-button" data-ir-close>Close</button></footer>
    </div>
</div>
@if(auth()->user()?->hasSystemPermission('maintenance', 'edit'))
<div id="itemRequestModal" class="ir-modal-overlay" aria-hidden="true">
    <div class="ir-modal" role="dialog" aria-modal="true" aria-labelledby="itemRequestModalTitle">
        <header class="ir-modal-header">
            <div class="ir-modal-heading">
                <span class="ir-modal-symbol"><i class="fa-solid fa-box-open"></i></span>
                <div><span class="ir-modal-eyebrow">MAINTENANCE / RESOURCE REQUEST</span><h2 id="itemRequestModalTitle">New Item Request</h2><p>Review the approved Job Order before submitting to Warehouse</p></div>
            </div>
            <button type="button" class="ir-icon-button" id="closeItemRequestModal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </header>
        <form method="POST" action="{{ route('item-requests.store') }}">
            @csrf
            <div class="ir-modal-body">
                <div class="ir-form-section">
                    <h3><span>01</span> Job Order Information <small>Source details</small></h3>
                    <div class="ir-field">
                        <label for="item_request_job_order">Approved Job Order <span>*</span></label>
                        <select id="item_request_job_order" name="job_order_id" required>
                            <option value="">Select approved Job Order</option>
                            @foreach($eligibleJobs as $jo)
                                <option value="{{ $jo->id }}" @selected(old('job_order_id') == $jo->id)>{{ $jo->job_order_no }} — Bus {{ $jo->bus_no }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ir-preview-grid">
                        <div><span class="ir-preview-card-icon"><i class="fa-solid fa-bus-simple"></i></span><span>Bus Number</span><strong id="ir-preview-bus">Select a JO</strong></div>
                        <div><span class="ir-preview-card-icon"><i class="fa-solid fa-user-gear"></i></span><span>Assigned Mechanics</span><strong id="ir-preview-mechanic">Select a JO</strong></div>
                    </div>
                </div>
                <div class="ir-form-section">
                    <h3><span>02</span> Requested Items <small>Automatically sourced from the JO</small></h3>
                    <p class="ir-form-hint">Only available quantities are suggested here. Missing quantities belong in a separate Purchase Request. Warehouse confirms stock again at issuance.</p>
                    <div class="ir-preview-table-wrap">
                        <table class="ir-preview-table">
                            <thead><tr><th>Item / Part</th><th>Suggested IR Qty</th><th>Stock Reference</th></tr></thead>
                            <tbody id="ir-preview-parts"><tr><td colspan="3" class="ir-preview-placeholder">Select an approved JO to review its required parts.</td></tr></tbody>
                        </table>
                    </div>
                </div>
                <div class="ir-form-section">
                    <h3><span>03</span> Request Details <small>Optional instructions</small></h3>
                    <div class="ir-field">
                        <label for="item_request_remarks">Remarks <small>(Optional)</small></label>
                        <textarea id="item_request_remarks" name="remarks" rows="3" maxlength="2000" placeholder="Additional instructions for Warehouse...">{{ old('remarks') }}</textarea>
                    </div>
                </div>
                <div class="ir-note"><i class="fa-solid fa-shield-halved"></i><span><strong>Warehouse verification required.</strong> Submitting an IR does not reserve, issue, or deduct inventory. Availability is checked again during fulfillment.</span></div>
            </div>
            <script type="application/json" id="ir-job-previews">@json($jobOrderPreviews)</script>
            <footer class="ir-modal-footer">
                <button type="button" class="ir-secondary-button" id="cancelItemRequestModal">Cancel</button>
                <button type="submit" class="ir-primary-button"><i class="fa-solid fa-paper-plane"></i> Submit to Warehouse</button>
            </footer>
        </form>
    </div>
</div>
@endif
</x-layout.app>
