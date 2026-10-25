<x-layout.app title="GCT - Warehouse Item Requests" :assets="['resources/css/Main-styles/main.css', 'resources/css/Main-styles/sidebar.css', 'resources/css/Main-styles/form-components.css', 'resources/css/Warehouse/part-requests.css']">
<div class="app">
    <x-layout.sidebar department="Warehouse" />
    <main class="main">
        <x-layout.topbar title="Item Requests" subtitle="View Maintenance requests awaiting stock verification" />
        <div style="padding:24px">
            <section class="table-card" style="padding:20px">
                <h2 style="font-size:18px;margin-bottom:8px">Item Requests from Maintenance</h2>
                <p style="margin-bottom:16px">These requests do not deduct stock. Confirm parts and quantities before recording an actual Warehouse issue.</p>
                <div style="overflow-x:auto">
                <table class="inventory-table" style="width:100%">
                    <thead><tr><th>IR ID</th><th>JO ID</th><th>Bus</th><th>Required Parts</th><th>Status</th><th>Requested</th></tr></thead>
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
                        <td>{{ $ir->status }}</td>
                        <td>{{ $ir->created_at?->format('M d, Y') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="text-align:center;padding:24px">No Item Requests submitted.</td></tr>
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
