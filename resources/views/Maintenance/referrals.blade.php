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
            @php
                $currentUser = auth()->user();
                $canReview = $currentUser?->hasSystemPermission('maintenance', 'approve') ?? false;
                $canCreateJo = $currentUser?->hasSystemPermission('maintenance', 'edit') ?? false;
            @endphp

            <x-layout.topbar
                title="Maintenance Referrals"
                subtitle="Review bus breakdown referrals from Operation and create traceable Job Orders"
            />

            

            <section data-ajax-region="summary" class="stats-grid referral-stats-grid">
                <x-ui.summary-card label="Pending" :value="$pendingCount" small="Needs review" icon="fa-clock" color="yellow" />
                <x-ui.summary-card label="Approved" :value="$approvedCount" small="Ready for JO" icon="fa-circle-check" color="green" />
                <x-ui.summary-card label="JO Created" :value="$createdCount" small="Linked to repair" icon="fa-clipboard-list" color="blue" />
                <x-ui.summary-card label="Rejected" :value="$rejectedCount" small="Not accepted" icon="fa-circle-xmark" color="red" />
            </section>

            <section data-ajax-region="records" class="table-card referrals-card">
                <div class="section-header referral-section-header">
                    <div>
                        <h2>Operation Referrals</h2>
                        <p>Every Job Order created here keeps the source incident and referral reference.</p>
                    </div>


                </div>

                @if($recordView === 'history')
                    <p class="referral-history-note">Job Order Created and Rejected referrals are kept here for reference and audit history.</p>
                @endif

                <div class="maintenance-list-controls referrals-controls-row">
                    <div class="referral-record-tabs" role="tablist" aria-label="Maintenance referral record view">
                        <a
                            href="{{ route('maintenance-referrals', array_filter([
                                'search' => request('search'),
                                'status' => 'all',
                            ])) }}"
                            class="referral-record-tab {{ $recordView === 'active' ? 'is-active' : '' }}"
                            role="tab"
                            aria-selected="{{ $recordView === 'active' ? 'true' : 'false' }}"
                        >
                            <i class="fa-solid fa-list-check"></i>
                            <span>Active</span>
                        </a>

                        <a
                            href="{{ route('maintenance-referrals', array_filter([
                                'record_view' => 'history',
                                'search' => request('search'),
                                'status' => 'all',
                            ])) }}"
                            class="referral-record-tab {{ $recordView === 'history' ? 'is-active' : '' }}"
                            role="tab"
                            aria-selected="{{ $recordView === 'history' ? 'true' : 'false' }}"
                        >
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            <span>History</span>
                        </a>
                    </div>
                    <x-ui.table-toolbar action="{{ route('maintenance-referrals') }}" search-label="Search Referrals" search-placeholder="Search incident, bus, location, or problem..." :filter-count="(int) (request()->filled('status') && request('status') !== 'all')" filter-title="Filter Referrals" :sort-options="['created_at' => 'Referral Date', 'status' => 'Status']" default-sort="created_at" default-direction="desc" data-server-filter="{{ $recordView === 'history' ? 'true' : 'false' }}">
                    <x-slot:hidden>
                        @if($recordView === 'history')
                            <input type="hidden" name="record_view" value="history">
                        @endif
                    </x-slot:hidden>
                    <x-slot:filters>
                        <x-ui.table-toolbar-field label="Status" name="status" default="all">
                            <option value="all">All Statuses</option>
                            @foreach($viewStatuses as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                            @endforeach
                        </x-ui.table-toolbar-field>
                    </x-slot:filters>
                </x-ui.table-toolbar>
                </div>

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
                                    $linkedJobOrder = $referral->jobOrder;
                                    $displayBusNo = $linkedJobOrder?->bus_no
                                        ?: $incident?->bus?->bus_no
                                        ?: '—';
                                    $jobOrderRecordView = $linkedJobOrder?->status === 'Completed'
                                        ? 'history'
                                        : 'active';
                                    $jobOrderUrl = $linkedJobOrder
                                        ? route('job-orders', [
                                            'record_view' => $jobOrderRecordView,
                                            'search' => $linkedJobOrder->job_order_no,
                                        ])
                                        : null;
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
                                            <i class="fa-solid fa-bus"></i> {{ $displayBusNo }}
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
                                        @if($linkedJobOrder)
                                            <a href="{{ $jobOrderUrl }}" class="linked-jo-pill">
                                                <i class="fa-solid fa-clipboard-check"></i> {{ $linkedJobOrder->job_order_no }}
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="referral-actions-wrap">
                                            @if($referral->status === 'Pending' && $canReview)
                                                <form method="POST" action="{{ route('maintenance-referrals.approve', $referral) }}" data-confirm-form data-confirm-type="approve" data-confirm-title="Approve Maintenance Referral?" data-confirm-message="Approve this referral so Maintenance Staff can create a Job Order?" data-confirm-button="Yes, Approve">
                                                    @csrf
                                                    <button type="submit" class="btn-ref-action btn-approve" title="Approve Referral">
                                                        <i class="fa-solid fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('maintenance-referrals.reject', $referral) }}" data-confirm-form data-confirm-type="reject" data-confirm-title="Reject Maintenance Referral?" data-confirm-message="Reject this referral? It will move to History and cannot be used to create a Job Order." data-confirm-button="Yes, Reject">
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

                                            @if($referral->status === 'Job Order Created' && $linkedJobOrder)
                                                <a href="{{ $jobOrderUrl }}" class="btn-ref-action btn-view-jo" title="View Job Order">
                                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View JO
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <x-ui.empty-row
                                    colspan="6"
                                    icon="fa-arrows-split-up-and-left"
                                    :message="$recordView === 'history' ? 'No Referral History Found' : 'No Active Maintenance Referrals Found'"
                                    :description="$recordView === 'history' ? 'Processed and completed referrals will appear here.' : 'No incoming maintenance referrals currently require attention.'"
                                />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-ui.table-footer
                    :items="$referrals"
                    data-lazy-pagination="{{ $recordView === 'history' ? 'true' : 'false' }}"
                />
            </section>
        </main>
    </div>
</x-layout.app>
