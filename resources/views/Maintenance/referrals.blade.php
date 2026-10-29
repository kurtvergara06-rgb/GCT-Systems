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

                <x-ui.table-toolbar
                    action="{{ route('maintenance-referrals', [], false) }}"
                    class="referral-toolbar"
                    data-server-filter="true"
                    search-label="Search Maintenance Referrals"
                    search-placeholder="Search incident, bus, location, or problem..."
                    :filter-count="(int) (request()->filled('status') && request('status') !== 'all')"
                    filter-title="Filter Referrals"
                    :sort-options="['created_at' => 'Submitted At', 'status' => 'Status']"
                    default-sort="created_at"
                    default-direction="desc"
                >
                    <x-slot:hidden>
                        <input type="hidden" name="record_view" value="{{ $recordView ?? 'active' }}">
                    </x-slot:hidden>
                    <x-slot:filters>
                        <x-ui.table-toolbar-field label="Status" name="status" default="all">
                            <option value="all" @selected(request('status', 'all') === 'all')>All Statuses</option>
                            @foreach(['Pending', 'Approved', 'Job Order Created', 'Rejected'] as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                            @endforeach
                        </x-ui.table-toolbar-field>
                    </x-slot:filters>
                </x-ui.table-toolbar>

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
                                    $canReview = $currentUser?->hasSystemPermission('maintenance', 'approve') ?? false;
                                    $canCreateJo = $currentUser?->hasSystemPermission('maintenance', 'edit') ?? false;
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
                                            <i class="fa-solid fa-bus"></i> {{ $referral->jobOrder?->bus_no ?? $incident?->bus?->bus_no ?? '—' }}
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
                                            <a href="{{ route('job-orders', [
                                                'record_view' => $referral->jobOrder->status === 'Completed' ? 'history' : 'active',
                                                'search' => $referral->jobOrder->job_order_no,
                                            ]) }}" class="linked-jo-pill">
                                                <i class="fa-solid fa-clipboard-check"></i> {{ $referral->jobOrder->job_order_no }}
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="referral-actions-wrap">
                                            @if($referral->status === 'Pending' && $canReview)
                                                <form method="POST" action="{{ route('maintenance-referrals.approve', $referral) }}"
                                                    data-confirm-form data-confirm-type="approve"
                                                    data-confirm-title="Approve Maintenance Referral?"
                                                    data-confirm-message="Approve this referral so Maintenance Staff can create a Job Order?"
                                                    data-confirm-button="Yes, Approve">
                                                    @csrf
                                                    <button type="submit" class="btn-ref-action btn-approve" title="Approve Referral">
                                                        <i class="fa-solid fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('maintenance-referrals.reject', $referral) }}"
                                                    data-confirm-form data-confirm-type="reject"
                                                    data-confirm-title="Reject Maintenance Referral?"
                                                    data-confirm-message="Reject this referral? The incident will remain in Operation for follow-up."
                                                    data-confirm-button="Yes, Reject">
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

                <x-ui.table-footer
                    :items="$referrals"
                    data-lazy-pagination="{{ $recordView === 'history' ? 'true' : 'false' }}"
                    data-server-filter="{{ $recordView === 'history' ? 'true' : 'false' }}"
                />
            </section>
        </main>
    </div>
</x-layout.app>
