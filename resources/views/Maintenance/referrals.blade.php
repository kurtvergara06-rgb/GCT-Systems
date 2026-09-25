<x-layout.app
    title="FROMS - Maintenance Referrals"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Main-styles/form-components.css',
        'resources/js/Main-js/sidebar.js',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Maintenance" />

        <main class="main" style="padding:24px;">
            <x-layout.topbar
                title="Maintenance Referrals"
                subtitle="Review bus breakdown referrals from Operation and create traceable Job Orders"
            />

            @if (session('success'))
                <div style="margin:16px 0;padding:12px 14px;border:1px solid #bbf7d0;background:#f0fdf4;border-radius:10px;color:#166534;">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div style="margin:16px 0;padding:12px 14px;border:1px solid #fecaca;background:#fef2f2;border-radius:10px;color:#991b1b;">
                    {{ session('error') }}
                </div>
            @endif

            <section style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:18px 0;">
                <x-ui.summary-card label="Pending" :value="$pendingCount" small="Needs review" icon="fa-clock" color="yellow" />
                <x-ui.summary-card label="Approved" :value="$approvedCount" small="Ready for JO" icon="fa-circle-check" color="green" />
                <x-ui.summary-card label="JO Created" :value="$createdCount" small="Linked to repair" icon="fa-clipboard-list" color="blue" />
                <x-ui.summary-card label="Rejected" :value="$rejectedCount" small="Not accepted" icon="fa-circle-xmark" color="red" />
            </section>

            <section class="table-card">
                <div class="section-header">
                    <div>
                        <h2>Operation Referrals</h2>
                        <p>Every Job Order created here keeps the source incident and referral reference.</p>
                    </div>
                </div>

                <form method="GET" action="{{ route('maintenance-referrals') }}" style="display:flex;gap:10px;align-items:center;margin:14px 0;">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search incident, bus, location..." style="min-width:280px;">
                    <select name="status" onchange="this.form.submit()">
                        <option value="all">All Statuses</option>
                        @foreach(['Pending', 'Approved', 'Job Order Created', 'Rejected'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="ui-btn-small">Search</button>
                </form>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Incident</th>
                                <th>Bus</th>
                                <th>Location / Problem</th>
                                <th>Referral Status</th>
                                <th>Job Order</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($referrals as $referral)
                                @php
                                    $incident = $referral->incident;
                                    $user = auth()->user();
                                    $department = strtolower(trim((string) ($user?->department ?? ''));
                                    $role = strtolower(trim((string) ($user?->role ?? '')));
                                    $canReview = ($department === 'maintenance' && in_array($role, ['head', 'admin', 'maintenance head', 'maintenance admin'], true))
                                        || ($department === 'admin' && in_array($role, ['head', 'admin', 'system admin'], true));
                                    $canCreateJo = ($department === 'maintenance' && in_array($role, ['staff', 'head', 'admin', 'maintenance staff', 'maintenance head', 'maintenance admin'], true))
                                        || ($department === 'admin' && in_array($role, ['head', 'admin', 'system admin'], true));
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $incident?->incident_no ?? '—' }}</strong><br>
                                        <small>{{ $incident?->incident_type ?? '—' }}</small>
                                    </td>
                                    <td>{{ $incident?->bus?->bus_no ?? '—' }}</td>
                                    <td>
                                        <strong>{{ $incident?->location ?? '—' }}</strong><br>
                                        <small>{{ \Illuminate\Support\Str::limit($incident?->description ?: 'No description provided.', 90) }}</small>
                                    </td>
                                    <td><x-ui.status-badge :status="$referral->status" /></td>
                                    <td>
                                        @if($referral->jobOrder)
                                            <a href="{{ route('job-orders', ['search' => $referral->jobOrder->job_order_no]) }}">
                                                {{ $referral->jobOrder->job_order_no }}
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                            @if($referral->status === 'Pending' && $canReview)
                                                <form method="POST" action="{{ route('maintenance-referrals.approve', $referral) }}">
                                                    @csrf
                                                    <button type="submit" class="ui-btn-small">Approve</button>
                                                </form>
                                                <form method="POST" action="{{ route('maintenance-referrals.reject', $referral) }}">
                                                    @csrf
                                                    <button type="submit" class="ui-btn-small">Reject</button>
                                                </form>
                                            @endif

                                            @if($referral->status === 'Approved' && $canCreateJo)
                                                <form method="POST" action="{{ route('maintenance-referrals.job-order.store', $referral) }}">
                                                    @csrf
                                                    <button type="submit" class="ui-btn-small">
                                                        <i class="fa-solid fa-clipboard-list"></i> Create JO
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <x-ui.empty-row colspan="6" message="No maintenance referrals found." />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-ui.table-footer :items="$referrals" />
            </section>
        </main>
    </div>
</x-layout.app>
