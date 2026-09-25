<x-layout.app
    title="FROMS - Maintenance Referrals"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Main-styles/form-components.css',
        'resources/css/Maintenance/referrals.css',
        'resources/js/Main-js/sidebar.js',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Maintenance" />

        <main class="main referrals-page">
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

            <section class="stats-grid referral-stats-grid">
                <x-ui.summary-card label="Pending" :value="$pendingCount" small="Needs review" icon="fa-clock" color="yellow" />
                <x-ui.summary-card label="Approved" :value="$approvedCount" small="Ready for JO" icon="fa-circle-check" color="green" />
                <x-ui.summary-card label="JO Created" :value="$createdCount" small="Linked to repair" icon="fa-clipboard-list" color="blue" />
                <x-ui.summary-card label="Rejected" :value="$rejectedCount" small="Not accepted" icon="fa-circle-xmark" color="red" />
            </section>

            <section class="table-card referrals-card">
                <div class="section-header">
                    <div>
                        <h2>Operation Referrals</h2>
                        <p>Every Job Order created here keeps the source incident and referral reference.</p>
                    </div>
                </div>

                <form method="GET" action="{{ route('maintenance-referrals') }}" class="toolbar referral-toolbar">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search incident, bus, location, or problem...">
                    </div>
                    <div class="filter-group">
                        <select name="status" onchange="this.form.submit()">
                            <option value="all">All Statuses</option>
                            @foreach(['Pending', 'Approved', 'Job Order Created', 'Rejected'] as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>

                <div class="table-wrap">
                    <table class="referrals-table">
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
                                    $currentUser = auth()->user();
                                    $department = strtolower(trim((string) optional($currentUser)->department));
                                    $role = strtolower(trim((string) optional($currentUser)->role));

                                    $maintenanceReviewRoles = ['head', 'admin', 'maintenance head', 'maintenance admin'];
                                    $maintenanceCreateRoles = ['staff', 'head', 'admin', 'maintenance staff', 'maintenance head', 'maintenance admin'];
                                    $adminRoles = ['head', 'admin', 'system admin'];

                                    $canReview = false;
                                    if ($department === 'maintenance') {
                                        $canReview = in_array($role, $maintenanceReviewRoles, true);
                                    } elseif ($department === 'admin') {
                                        $canReview = in_array($role, $adminRoles, true);
                                    }

                                    $canCreateJo = false;
                                    if ($department === 'maintenance') {
                                        $canCreateJo = in_array($role, $maintenanceCreateRoles, true);
                                    } elseif ($department === 'admin') {
                                        $canCreateJo = in_array($role, $adminRoles, true);
                                    }
                                @endphp
                                <tr>
                                    <td>
                                        <div class="incident-cell">
                                            <strong class="incident-number">{{ $incident?->incident_no ?? '—' }}</strong>
                                            <span class="incident-type-tag">
                                                <i class="fa-solid fa-triangle-exclamation"></i>
                                                {{ $incident?->incident_type ?? 'Incident' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="bus-badge-pill">
                                            <i class="fa-solid fa-bus"></i> {{ $incident?->bus?->bus_no ?? '—' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="location-problem-cell">
                                            <strong class="location-text">
                                                <i class="fa-solid fa-location-dot"></i> {{ $incident?->location ?? 'No location provided' }}
                                            </strong>
                                            <p class="problem-desc">{{ \Illuminate\Support\Str::limit($incident?->description ?: 'No description provided.', 95) }}</p>
                                        </div>
                                    </td>
                                    <td><x-ui.status-badge :status="$referral->status" /></td>
                                    <td>
                                        @if($referral->jobOrder)
                                            <a href="{{ route('job-orders', ['search' => $referral->jobOrder->job_order_no]) }}" class="linked-jo-pill">
                                                <i class="fa-solid fa-clipboard-check"></i> {{ $referral->jobOrder->job_order_no }}
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="referral-actions-wrap">
                                            @if($referral->status === 'Pending' && $canReview)
                                                <form method="POST" action="{{ route('maintenance-referrals.approve', $referral) }}">
                                                    @csrf
                                                    <button type="submit" class="btn-ref-action btn-approve" title="Approve Referral">
                                                        <i class="fa-solid fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('maintenance-referrals.reject', $referral) }}">
                                                    @csrf
                                                    <button type="submit" class="btn-ref-action btn-reject" title="Reject Referral">
                                                        <i class="fa-solid fa-xmark"></i> Reject
                                                    </button>
                                                </form>
                                            @endif

                                            @if($referral->status === 'Approved' && $canCreateJo)
                                                <form method="POST" action="{{ route('maintenance-referrals.job-order.store', $referral) }}">
                                                    @csrf
                                                    <button type="submit" class="btn-ref-action btn-create-jo" title="Create Job Order">
                                                        <i class="fa-solid fa-screwdriver-wrench"></i> Create JO
                                                    </button>
                                                </form>
                                            @endif

                                            @if($referral->status === 'Job Order Created' && $referral->jobOrder)
                                                <a href="{{ route('job-orders', ['search' => $referral->jobOrder->job_order_no]) }}" class="btn-ref-action btn-view-jo" title="View Job Order">
                                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View JO
                                                </a>
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
