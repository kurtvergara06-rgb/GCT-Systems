<x-layout.app title="GCT - Item Requests" :assets="['resources/css/Main-styles/main.css', 'resources/css/Main-styles/sidebar.css', 'resources/css/Main-styles/form-components.css']">
<div class="app">
    <x-layout.sidebar department="Maintenance" />
    <main class="main">
        <x-layout.topbar title="Item Requests" subtitle="Request JO parts for Warehouse verification and issuance" />
        <div style="padding:24px;display:grid;gap:20px">
            @if(session('success'))
                <div role="status" style="padding:12px;border:1px solid #86efac;border-radius:10px">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div role="alert" style="padding:12px;border:1px solid #ef4444;border-radius:10px">
                    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            <section class="table-card" style="padding:20px">
                <h2 style="font-size:18px;margin-bottom:8px">Create Item Request</h2>
                <p style="margin-bottom:16px">Choose an approved Job Order. All JO parts are requested here; Warehouse will confirm actual stock. Missing quantities belong in a separate Purchase Request.</p>
                @if(auth()->user()?->hasSystemPermission('maintenance', 'edit'))
                <form method="POST" action="{{ route('item-requests.store') }}" style="display:grid;gap:12px;max-width:640px">
                    @csrf
                    <label for="item_request_job_order">Approved Job Order</label>
                    <select id="item_request_job_order" name="job_order_id" required style="padding:12px;border:1px solid #94a3b8;border-radius:8px">
                        <option value="">Select a Job Order</option>
                        @foreach($eligibleJobs as $jo)
                            <option value="{{ $jo->id }}" @selected(old('job_order_id') == $jo->id)>{{ $jo->job_order_no }} — Bus {{ $jo->bus_no }}</option>
                        @endforeach
                    </select>
                    <label for="item_request_remarks">Remarks (optional)</label>
                    <textarea id="item_request_remarks" name="remarks" rows="2" maxlength="2000" style="padding:12px;border:1px solid #94a3b8;border-radius:8px">{{ old('remarks') }}</textarea>
                    <button type="submit" class="btn btn-primary" style="padding:11px 18px;justify-self:start" @disabled($eligibleJobs->isEmpty())>Submit Item Request</button>
                </form>
                @else
                    <p>You have view-only access to Item Requests.</p>
                @endif
            </section>
            <section class="table-card" style="padding:20px">
                <h2 style="font-size:18px;margin-bottom:16px">Item Request Records</h2>
                <div style="overflow-x:auto">
                    <table class="inventory-table" style="width:100%">
                        <thead><tr><th>IR ID</th><th>Job Order</th><th>Bus</th><th>Requested Parts</th><th>Status</th><th>Created</th></tr></thead>
                        <tbody>
                        @forelse($requests as $ir)
                            <tr>
                                <td><strong>IR-{{ str_pad((string)$ir->id, 5, '0', STR_PAD_LEFT) }}</strong></td>
                                <td>{{ $ir->jobOrder?->job_order_no ?? '—' }}</td>
                                <td>{{ $ir->jobOrder?->bus_no ?? '—' }}</td>
                                <td>
                                    @foreach($ir->requested_parts ?? [] as $part)
                                        <div>{{ $part['name'] ?? '—' }} — {{ $part['quantity'] ?? 0 }} {{ $part['unit'] ?? '' }}</div>
                                    @endforeach
                                </td>
                                <td><span class="badge">{{ $ir->status }}</span></td>
                                <td>{{ $ir->created_at?->format('M d, Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="text-align:center;padding:24px">No Item Requests yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $requests->links() }}
            </section>
        </div>
    </main>
</div>
</x-layout.app>
