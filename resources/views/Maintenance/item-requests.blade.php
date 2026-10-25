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
            <div class="ir-summary-card">
                <span class="ir-summary-icon"><i class="fa-solid fa-file-lines"></i></span>
                <div><span>Total Requests</span><strong>{{ $requests->total() }}</strong><small>Item requests created</small></div>
            </div>
            <div class="ir-summary-card">
                <span class="ir-summary-icon ir-summary-icon-blue"><i class="fa-solid fa-paper-plane"></i></span>
                <div><span>Submitted</span><strong>{{ $requests->getCollection()->where('status', 'Submitted')->count() }}</strong><small>On this page</small></div>
            </div>
            <div class="ir-summary-card">
                <span class="ir-summary-icon ir-summary-icon-green"><i class="fa-solid fa-clipboard-check"></i></span>
                <div><span>Eligible Job Orders</span><strong>{{ $eligibleJobs->count() }}</strong><small>Approved, without an IR</small></div>
            </div>
        </section>

        <section class="ir-record-card">
            <header class="ir-record-header">
                <div>
                    <div class="ir-eyebrow">RESOURCE MANAGEMENT</div>
                    <h2>Item Request Records</h2>
                    <p>Track the parts requested from Warehouse for each approved Job Order.</p>
                </div>
                @if(auth()->user()?->hasSystemPermission('maintenance', 'edit'))
                    <button type="button" class="ir-primary-button" id="openItemRequestModal" @disabled($eligibleJobs->isEmpty())>
                        <i class="fa-solid fa-plus"></i> New Item Request
                    </button>
                @endif
            </header>

            <div class="ir-toolbar">
                <span class="ir-active-tab"><i class="fa-solid fa-list-check"></i> All Requests</span>
                <span class="ir-record-count">{{ $requests->total() }} {{ \Illuminate\Support\Str::plural('record', $requests->total()) }}</span>
            </div>

            <div class="ir-table-wrap">
                <table class="ir-table">
                    <thead>
                        <tr><th>IR NUMBER</th><th>JOB ORDER</th><th>BUS</th><th>REQUESTED PARTS</th><th>STATUS</th><th>DATE CREATED</th></tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $ir)
                        <tr>
                            <td><strong class="ir-number">IR-{{ str_pad((string) $ir->id, 5, '0', STR_PAD_LEFT) }}</strong></td>
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
                            <td class="ir-date">{{ $ir->created_at?->format('M d, Y') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6">
                            <div class="ir-empty">
                                <span><i class="fa-solid fa-box-open"></i></span>
                                <strong>No Item Requests yet</strong>
                                <p>Create an Item Request from an approved Job Order to begin Warehouse verification.</p>
                            </div>
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="ir-footer">{{ $requests->links() }}</div>
        </section>
    </main>
</div>

@if(auth()->user()?->hasSystemPermission('maintenance', 'edit'))
<div id="itemRequestModal" class="ir-modal-overlay" aria-hidden="true">
    <div class="ir-modal" role="dialog" aria-modal="true" aria-labelledby="itemRequestModalTitle">
        <header class="ir-modal-header">
            <div class="ir-modal-heading">
                <span class="ir-modal-symbol"><i class="fa-solid fa-box-open"></i></span>
                <div><h2 id="itemRequestModalTitle">New Item Request</h2><p>Request parts listed on an approved Job Order</p></div>
            </div>
            <button type="button" class="ir-icon-button" id="closeItemRequestModal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </header>
        <form method="POST" action="{{ route('item-requests.store') }}">
            @csrf
            <div class="ir-modal-body">
                <div class="ir-note"><i class="fa-solid fa-circle-info"></i><span>All required JO parts will be included. Warehouse checks actual availability; only shortages should proceed to Purchase.</span></div>
                <div class="ir-field">
                    <label for="item_request_job_order">Approved Job Order <span>*</span></label>
                    <select id="item_request_job_order" name="job_order_id" required>
                        <option value="">Select approved Job Order</option>
                        @foreach($eligibleJobs as $jo)
                            <option value="{{ $jo->id }}" @selected(old('job_order_id') == $jo->id)>{{ $jo->job_order_no }} — Bus {{ $jo->bus_no }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ir-field">
                    <label for="item_request_remarks">Remarks <small>(Optional)</small></label>
                    <textarea id="item_request_remarks" name="remarks" rows="3" maxlength="2000" placeholder="Add a note for Warehouse, if needed...">{{ old('remarks') }}</textarea>
                </div>
            </div>
            <footer class="ir-modal-footer">
                <button type="button" class="ir-secondary-button" id="cancelItemRequestModal">Cancel</button>
                <button type="submit" class="ir-primary-button"><i class="fa-solid fa-paper-plane"></i> Submit Item Request</button>
            </footer>
        </form>
    </div>
</div>
@endif
</x-layout.app>
