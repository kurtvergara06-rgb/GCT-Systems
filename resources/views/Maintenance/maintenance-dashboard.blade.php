<x-layout.app
  title="FROMS - Maintenance Dashboard"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Maintenance/maintenance-dashboard.css',
    'resources/js/Main-js/sidebar.js'
  ]"
>

  <div class="app maintenance-dashboard-page">

    {{-- =====================================================
        SIDEBAR
    ====================================================== --}}
    <x-layout.sidebar department="Maintenance" />

    <main class="main maintenance-dashboard-main">

      {{-- =====================================================
          TOPBAR
      ====================================================== --}}
      <x-layout.topbar
        title="Maintenance Dashboard"
        subtitle="Real-time operational monitoring: active repairs, workforce roster, PMS alerts, and referrals"
      />

      {{-- =====================================================
          ROW 1: TOP 5 KPI CARDS
      ====================================================== --}}
      <section data-ajax-region="summary" class="stats-grid maintenance-stats-grid">

        {{-- A. Active Job Orders --}}
        <x-ui.summary-card
          label="Active Job Orders"
          value="{{ $totalActiveJobOrders }}"
          small="{{ $ongoingCount }} Ongoing · {{ $onHoldCount }} On Hold"
          icon="fa-screwdriver-wrench"
          color="blue"
          href="{{ route('job-orders') }}"
        />

        {{-- B. Breakdown / Urgent Repairs --}}
        <x-ui.summary-card
          label="Breakdown / Urgent"
          value="{{ $urgentRepairsCount }}"
          small="{{ $breakdownRepairsCount }} breakdown · {{ $overdueJobOrdersCount }} overdue"
          icon="fa-triangle-exclamation"
          color="red"
          href="{{ route('job-orders') }}"
        />

        {{-- C. PMS Due / Overdue --}}
        <x-ui.summary-card
          label="Overdue / Due PMS"
          value="{{ $totalPmsAttention }}"
          small="{{ $pmsOverdueCount }} overdue · {{ $pmsDueSoonCount }} due soon"
          icon="fa-calendar-check"
          color="yellow"
          href="{{ route('PMS-Scheduling') }}"
        />

        {{-- D. Available Mechanics --}}
        <x-ui.summary-card
          label="Available Mechanics"
          value="{{ $availableMechanicsCount }} / {{ $totalMechanicsCount }}"
          small="{{ $onDutyMechanicsCount }} on duty · {{ $hasTodayAttendance ? 'Attendance active' : 'No shift logged' }}"
          icon="fa-user-check"
          color="green"
          href="{{ route('mechanic-list') }}"
        />

        {{-- E. Pending Referrals --}}
        <x-ui.summary-card
          label="Pending Referrals"
          value="{{ $referralPendingCount }}"
          small="Awaiting maintenance review"
          icon="fa-arrow-right-arrow-left"
          color="red"
          href="{{ route('maintenance-referrals') }}"
        />

      </section>

      {{-- =====================================================
          ROW 2: ACTIVE & RECENT JOB ORDERS + MECHANIC ROSTER
      ====================================================== --}}
      <section class="maintenance-dashboard-grid mb-4">

        {{-- ACTIVE & RECENT JOB ORDERS --}}
        <div class="maintenance-dashboard-card recent-job-orders-card">
          <div class="dashboard-card-header">
            <div>
              <span class="dashboard-eyebrow">WORK ORDERS</span>
              <h2>Active & Recent Job Orders</h2>
              <p>Current repair tickets, assigned personnel, and incident breakdown sources.</p>
            </div>
            <a href="{{ route('job-orders') }}" class="dashboard-view-link">
              View All Job Orders <i class="fa-solid fa-arrow-right"></i>
            </a>
          </div>

          <div class="recent-job-list">
            @forelse($recentJobOrders as $jobOrder)
              @php
                $statusClass = match($jobOrder->status ?? '') {
                  'Completed' => 'completed',
                  'On Going' => 'ongoing',
                  'On Hold' => 'hold',
                  default => 'default',
                };

                $incidentNo = $jobOrder->incident?->incident_no
                  ?? $jobOrder->maintenanceReferral?->incident?->incident_no;

                $isUrgent = !is_null($jobOrder->maintenance_referral_id)
                  || !is_null($jobOrder->incident_id)
                  || $jobOrder->is_overdue;
              @endphp

              <div class="recent-job-item {{ $isUrgent ? 'is-urgent-item' : '' }}">
                <div class="recent-job-icon {{ $statusClass }}">
                  <i class="fa-solid fa-screwdriver-wrench"></i>
                </div>

                <div class="recent-job-content">
                  <div class="recent-job-heading">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                      <span class="jo-badge-number">{{ $jobOrder->job_order_no ?? 'Job Order' }}</span>
                      <span class="bus-badge-pill"><i class="fa-solid fa-bus"></i> {{ $jobOrder->bus_no ?? 'No Bus Assigned' }}</span>
                      
                      @if($incidentNo)
                        <span class="jo-source-chip incident" title="Initiated from Operation Bus Breakdown Incident">
                          <i class="fa-solid fa-triangle-exclamation"></i> Breakdown {{ \Illuminate\Support\Str::startsWith($incidentNo, 'INC-') ? $incidentNo : 'INC-' . $incidentNo }}
                        </span>
                      @elseif($jobOrder->maintenance_referral_id)
                        <span class="jo-source-chip incident" title="Initiated from Operation Maintenance Referral">
                          <i class="fa-solid fa-arrow-right-arrow-left"></i> Breakdown Referral
                        </span>
                      @elseif($jobOrder->pms_schedule_id)
                        <span class="jo-source-chip pms" title="Linked to Preventive Maintenance Schedule">
                          <i class="fa-solid fa-calendar-check"></i> PMS
                        </span>
                      @endif

                      @if($jobOrder->is_overdue)
                        <span class="badge-chip overdue-chip" title="{{ $jobOrder->overdue_label }}">
                          <i class="fa-solid fa-clock"></i> {{ $jobOrder->overdue_label }}
                        </span>
                      @endif
                    </div>

                    <span class="dashboard-status {{ $statusClass }}">
                      {{ $jobOrder->status ?? 'Unknown' }}
                    </span>
                  </div>

                  <div class="recent-job-description">
                    {{ $jobOrder->problem_issue ?? 'No maintenance issue description provided.' }}
                  </div>

                  <div class="recent-job-meta">
                    <span>
                      <i class="fa-solid fa-wrench"></i>
                      {{ $jobOrder->maintenance_type ?? 'Maintenance' }}
                    </span>

                    <span>
                      <i class="fa-solid fa-user-gear"></i>
                      {{ $jobOrder->assigned_mechanic ?: 'Unassigned' }}
                    </span>

                    @if(!empty($jobOrder->part_needed))
                      <span class="part-badge {{ in_array($jobOrder->part_status, ['Requested', 'Waiting Approval', 'Waiting Purchase', 'Waiting Delivery']) ? 'part-waiting' : 'part-ready' }}">
                        <i class="fa-solid fa-gears"></i> Part: {{ $jobOrder->part_needed }}
                        @if(!empty($jobOrder->part_status))
                          ({{ $jobOrder->part_status }})
                        @endif
                      </span>
                    @endif

                    @if($jobOrder->start_date)
                      <span class="text-muted" title="Start Date">
                        <i class="fa-solid fa-calendar-days"></i> {{ $jobOrder->start_date->format('M d, Y') }}
                      </span>
                    @endif

                    @if($jobOrder->formatted_estimated_duration !== 'Not set')
                      <span class="text-muted" title="Estimated Duration">
                        <i class="fa-solid fa-hourglass-half"></i> Est: {{ $jobOrder->formatted_estimated_duration }}
                      </span>
                    @endif
                  </div>
                </div>
              </div>
            @empty
              <div class="dashboard-empty-state">
                <div class="dashboard-empty-icon">
                  <i class="fa-solid fa-clipboard-list"></i>
                </div>
                <h3>No active or recent job orders</h3>
                <p>Newly dispatched repair tickets and maintenance tasks will appear here.</p>
              </div>
            @endforelse
          </div>
        </div>

        {{-- MECHANIC AVAILABILITY ROSTER --}}
        <div class="maintenance-dashboard-card mechanic-roster-card">
          <div class="dashboard-card-header">
            <div>
              <span class="dashboard-eyebrow">WORKFORCE ROSTER</span>
              <h2>Mechanic Availability</h2>
              <p>Shift presence and active job assignments.</p>
            </div>
            <a href="{{ route('mechanic-list') }}" class="dashboard-view-link">
              View All <i class="fa-solid fa-arrow-right"></i>
            </a>
          </div>

          {{-- Summary Mini Counters --}}
          <div class="roster-metrics-bar">
            <div class="roster-metric-item available">
              <span class="roster-dot available"></span>
              <span class="roster-metric-label">Available</span>
              <strong>{{ $availableMechanicsCount }}</strong>
            </div>
            <div class="roster-metric-item duty">
              <span class="roster-dot duty"></span>
              <span class="roster-metric-label">On Duty</span>
              <strong>{{ $onDutyMechanicsCount }}</strong>
            </div>
            <div class="roster-metric-item other">
              <span class="roster-dot other"></span>
              <span class="roster-metric-label">Off/Leave</span>
              <strong>{{ $totalMechanicsCount - ($availableMechanicsCount + $onDutyMechanicsCount) }}</strong>
            </div>
          </div>

          <div class="mechanic-roster-list">
            {{-- On Duty Section --}}
            @if($onDutyMechanicsList->isNotEmpty())
              <div class="roster-group-label">
                <i class="fa-solid fa-screwdriver-wrench text-blue"></i> Currently On Duty ({{ $onDutyMechanicsList->count() }})
              </div>
              @foreach($onDutyMechanicsList as $mech)
                <div class="roster-item on-duty-item">
                  <div class="roster-avatar on-duty">
                    <i class="fa-solid fa-user-gear"></i>
                  </div>
                  <div class="roster-info">
                    <div class="roster-name-row">
                      <h4>{{ $mech->name }}</h4>
                      <span class="roster-pill pill-duty">On Duty</span>
                    </div>
                    <p class="roster-assignment">
                      <i class="fa-solid fa-clipboard-check"></i> {{ $mech->active_jo_no }}
                      @if($mech->bus_no)
                        · <span class="bus-tag"><i class="fa-solid fa-bus"></i> {{ $mech->bus_no }}</span>
                      @endif
                    </p>
                  </div>
                </div>
              @endforeach
            @endif

            {{-- Available Section --}}
            @if($availableMechanicsList->isNotEmpty())
              <div class="roster-group-label mt-3">
                <i class="fa-solid fa-user-check text-green"></i> Ready for Assignment ({{ $availableMechanicsList->count() }})
              </div>
              @foreach($availableMechanicsList as $mech)
                <div class="roster-item available-item">
                  <div class="roster-avatar available">
                    <i class="fa-solid fa-user-check"></i>
                  </div>
                  <div class="roster-info">
                    <div class="roster-name-row">
                      <h4>{{ $mech->name }}</h4>
                      <span class="roster-pill pill-available">{{ $mech->status }}</span>
                    </div>
                    <p class="roster-assignment text-muted">
                      <i class="fa-solid fa-clock"></i> Shift: {{ $mech->shift }} · Standing by in shop
                    </p>
                  </div>
                </div>
              @endforeach
            @endif

            {{-- Other / Off Duty Section --}}
            @if($otherMechanicsList->isNotEmpty())
              <div class="roster-group-label mt-3">
                <i class="fa-solid fa-calendar-xmark text-muted"></i> Off Duty / Absent ({{ $otherMechanicsList->count() }})
              </div>
              @foreach($otherMechanicsList->take(3) as $mech)
                <div class="roster-item other-item">
                  <div class="roster-avatar other">
                    <i class="fa-solid fa-user-slash"></i>
                  </div>
                  <div class="roster-info">
                    <div class="roster-name-row">
                      <h4>{{ $mech->name }}</h4>
                      <span class="roster-pill pill-other">{{ $mech->status }}</span>
                    </div>
                    <p class="roster-assignment text-muted">
                      Shift: {{ $mech->shift }}
                    </p>
                  </div>
                </div>
              @endforeach
            @endif

            @if($onDutyMechanicsList->isEmpty() && $availableMechanicsList->isEmpty() && $otherMechanicsList->isEmpty())
              <div class="dashboard-empty-state">
                <div class="dashboard-empty-icon">
                  <i class="fa-solid fa-users-slash"></i>
                </div>
                <h3>No mechanics registered</h3>
                <p>Register mechanics in the Personnel module to track shop availability.</p>
              </div>
            @endif
          </div>
        </div>

      </section>

      {{-- =====================================================
          ROW 3: PMS ATTENTION HUB + PARTS BOTTLENECK
      ====================================================== --}}
      <section class="maintenance-two-col-grid mb-4">

        {{-- PREVENTIVE MAINTENANCE ATTENTION HUB --}}
        <div class="maintenance-dashboard-card pms-hub-card">
          <div class="dashboard-card-header">
            <div>
              <span class="dashboard-eyebrow">PREVENTIVE MAINTENANCE</span>
              <h2>PMS Attention Hub</h2>
              <p>Buses overdue or within 500 km threshold based on live GPS mileage.</p>
            </div>
            <a href="{{ route('PMS-Scheduling') }}" class="dashboard-view-link">
              View All Schedules <i class="fa-solid fa-arrow-right"></i>
            </a>
          </div>

          <div class="pms-attention-list">
            @forelse($pmsAttentionList as $item)
              <div class="pms-attention-row {{ $item->status === 'Overdue' ? 'is-overdue' : 'is-due-soon' }}">
                <div class="pms-bus-badge">
                  <span class="bus-no-text"><i class="fa-solid fa-bus"></i> {{ $item->bus_no }}</span>
                  <span class="pms-type-text">{{ $item->maintenance_type }}</span>
                </div>

                <div class="pms-mileage-details">
                  <div class="mileage-numbers">
                    <span class="gps-label">Live GPS: <strong>{{ number_format($item->latest_gps_km) }} km</strong></span>
                    <span class="separator">/</span>
                    <span class="target-label">Next PMS: <strong>{{ number_format($item->next_pms_km) }} km</strong></span>
                  </div>
                  <div class="mileage-delta">
                    @if($item->status === 'Overdue')
                      <span class="text-danger">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        @if($item->km_difference <= 0)
                          {{ number_format(abs($item->km_difference)) }} km overdue
                        @else
                          Date overdue ({{ $item->recommended_date?->format('M d, Y') }})
                        @endif
                      </span>
                    @else
                      <span class="text-warning">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Due in {{ number_format($item->km_difference) }} km
                      </span>
                    @endif
                  </div>
                </div>

                <div class="pms-action-cell">
                  @if($item->has_active_jo)
                    <span class="pms-active-jo-badge" title="Active Job Order: {{ $item->active_job_order->job_order_no }}">
                      <i class="fa-solid fa-screwdriver-wrench"></i> {{ $item->active_job_order->job_order_no }}
                    </span>
                  @else
                    <a href="{{ route('pms-schedules.create-job-order', $item->schedule) }}" class="btn-create-jo-compact">
                      <i class="fa-solid fa-plus"></i> Create JO
                    </a>
                  @endif
                </div>
              </div>
            @empty
              <div class="dashboard-empty-state">
                <div class="dashboard-empty-icon text-green" style="background: #dcfce7; color: #15803d;">
                  <i class="fa-solid fa-circle-check"></i>
                </div>
                <h3>All PMS Schedules Up to Date</h3>
                <p>No buses currently exceed or approach their service mileage interval.</p>
              </div>
            @endforelse
          </div>
        </div>

        {{-- PARTS & PURCHASE REQUESTS (BOTTLENECK TRACKER) --}}
        <div class="maintenance-dashboard-card parts-tracker-card">
          <div class="dashboard-card-header">
            <div>
              <span class="dashboard-eyebrow">PARTS & PROCUREMENT</span>
              <h2>Parts Pipeline & Bottlenecks</h2>
              <p>Requisition flow and repairs waiting for warehouse or procurement.</p>
            </div>
            <a href="{{ route('purchase-requests') }}" class="dashboard-view-link">
              View PRs <i class="fa-solid fa-arrow-right"></i>
            </a>
          </div>

          {{-- Pipeline Strip --}}
          <div class="pr-pipeline-strip">
            <div class="pipeline-step">
              <span class="step-num">{{ $prPipeline['Submitted'] ?? 0 }}</span>
              <span class="step-label">Submitted</span>
            </div>
            <div class="pipeline-divider"><i class="fa-solid fa-chevron-right"></i></div>
            <div class="pipeline-step">
              <span class="step-num">{{ $prPipeline['Approved'] ?? 0 }}</span>
              <span class="step-label">Approved</span>
            </div>
            <div class="pipeline-divider"><i class="fa-solid fa-chevron-right"></i></div>
            <div class="pipeline-step">
              <span class="step-num">{{ $prPipeline['For Purchase'] ?? 0 }}</span>
              <span class="step-label">Purchase</span>
            </div>
            <div class="pipeline-divider"><i class="fa-solid fa-chevron-right"></i></div>
            <div class="pipeline-step">
              <span class="step-num">{{ $prPipeline['In Transit'] ?? 0 }}</span>
              <span class="step-label">Transit</span>
            </div>
            <div class="pipeline-divider"><i class="fa-solid fa-chevron-right"></i></div>
            <div class="pipeline-step">
              <span class="step-num">{{ $prPipeline['Delivered'] ?? 0 }}</span>
              <span class="step-label">Delivered</span>
            </div>
          </div>

          <div class="blocked-repairs-section mt-3">
            <h4 class="subhead-title">
              <i class="fa-solid fa-hourglass-start text-warning"></i> Active Repairs Blocked by Parts ({{ $blockedJobOrders->count() }})
            </h4>

            <div class="blocked-repairs-list">
              @forelse($blockedJobOrders as $blockedJo)
                <div class="blocked-item">
                  <div class="blocked-main">
                    <div class="blocked-header">
                      <strong>{{ $blockedJo->job_order_no }}</strong>
                      <span class="bus-tag"><i class="fa-solid fa-bus"></i> {{ $blockedJo->bus_no }}</span>
                    </div>
                    <p class="blocked-part-name">
                      <i class="fa-solid fa-box-open"></i> Needed: <strong>{{ $blockedJo->part_needed }}</strong>
                    </p>
                  </div>
                  <div class="blocked-status-side">
                    <span class="part-status-badge waiting">
                      {{ $blockedJo->part_status ?: 'Requested' }}
                    </span>
                  </div>
                </div>
              @empty
                <div class="dashboard-empty-state-sm">
                  <i class="fa-solid fa-check-double text-green"></i>
                  <span>No active repairs currently delayed waiting for parts.</span>
                </div>
              @endforelse
            </div>
          </div>
        </div>

      </section>

      {{-- =====================================================
          ROW 4: REFERRALS + FLEET READINESS & FUEL SUMMARY
      ====================================================== --}}
      <section class="maintenance-two-col-grid mb-4">

        {{-- OPERATION -> MAINTENANCE REFERRALS --}}
        <div class="maintenance-dashboard-card referrals-card">
          <div class="dashboard-card-header">
            <div>
              <span class="dashboard-eyebrow">INTER-DEPARTMENTAL</span>
              <h2>Operation → Maintenance Referrals</h2>
              <p>Vehicle breakdown incidents referred from Operation Head for shop servicing.</p>
            </div>
            <a href="{{ route('maintenance-referrals') }}" class="dashboard-view-link">
              Review Referrals <i class="fa-solid fa-arrow-right"></i>
            </a>
          </div>

          {{-- Referral Status Tally --}}
          <div class="referral-tally-bar">
            <div class="tally-box {{ $referralPendingCount > 0 ? 'highlight-pending' : '' }}">
              <span class="tally-count">{{ $referralPendingCount }}</span>
              <span class="tally-label">Pending</span>
            </div>
            <div class="tally-box">
              <span class="tally-count">{{ $referralApprovedCount }}</span>
              <span class="tally-label">Approved</span>
            </div>
            <div class="tally-box">
              <span class="tally-count">{{ $referralJobCreatedCount }}</span>
              <span class="tally-label">JO Created</span>
            </div>
            <div class="tally-box">
              <span class="tally-count">{{ $referralRejectedCount }}</span>
              <span class="tally-label">Rejected</span>
            </div>
          </div>

          <div class="referrals-stream-list mt-3">
            @forelse($recentReferrals as $referral)
              @php
                $refStatusClass = match($referral->status) {
                  'Pending' => 'ref-pending',
                  'Approved' => 'ref-approved',
                  'Job Order Created' => 'ref-jo',
                  'Rejected' => 'ref-rejected',
                  default => 'ref-default',
                };
              @endphp

              <div class="referral-stream-item {{ $referral->status === 'Pending' ? 'is-pending' : '' }}">
                <div class="ref-icon {{ $refStatusClass }}">
                  <i class="fa-solid fa-triangle-exclamation"></i>
                </div>

                <div class="ref-details">
                  <div class="ref-header-row">
                    <div class="d-flex align-items-center gap-2">
                      <strong>{{ $referral->incident?->incident_no ? (\Illuminate\Support\Str::startsWith($referral->incident->incident_no, 'INC-') ? $referral->incident->incident_no : 'INC-' . $referral->incident->incident_no) : 'Incident' }}</strong>
                      <span class="bus-tag"><i class="fa-solid fa-bus"></i> {{ $referral->incident?->bus?->bus_no ?? 'No Bus' }}</span>
                    </div>
                    <span class="ref-status-badge {{ $refStatusClass }}">
                      {{ $referral->status }}
                    </span>
                  </div>

                  <p class="ref-desc">
                    {{ Str::limit($referral->incident?->description ?? $referral->notes ?? 'No breakdown description provided.', 85) }}
                  </p>

                  <div class="ref-footer-row">
                    <span class="text-muted"><i class="fa-solid fa-clock"></i> {{ $referral->created_at->format('M d, Y h:i A') }}</span>
                    @if($referral->jobOrder)
                      <span class="linked-jo-pill"><i class="fa-solid fa-clipboard-check"></i> {{ $referral->jobOrder->job_order_no }}</span>
                    @endif
                  </div>
                </div>
              </div>
            @empty
              <div class="dashboard-empty-state">
                <div class="dashboard-empty-icon">
                  <i class="fa-solid fa-arrow-right-arrow-left"></i>
                </div>
                <h3>No breakdown referrals</h3>
                <p>Breakdown referrals submitted by Operation will appear here for review.</p>
              </div>
            @endforelse
          </div>
        </div>

        {{-- FLEET READINESS & FUEL SUMMARY --}}
        <div class="maintenance-dashboard-card readiness-fuel-card">
          <div class="dashboard-card-header">
            <div>
              <span class="dashboard-eyebrow">FLEET HEALTH</span>
              <h2>Fleet Readiness & Resources</h2>
              <p>Overall operational fleet availability and fuel efficiency statistics.</p>
            </div>
            <a href="{{ route('fuel-reports') }}" class="dashboard-view-link">
              Fuel Reports <i class="fa-solid fa-arrow-right"></i>
            </a>
          </div>

          {{-- Fleet Readiness Gauge --}}
          <div class="readiness-gauge-box">
            <div class="gauge-left">
              <span class="gauge-percentage">{{ $operationalRate }}%</span>
              <span class="gauge-label">Fleet Operational Rate</span>
            </div>
            <div class="gauge-stats">
              <div class="gauge-stat">
                <span class="num">{{ $totalBuses }}</span>
                <span class="lbl">Total Fleet</span>
              </div>
              <div class="gauge-stat">
                <span class="num text-green">{{ $activeBusesCount }}</span>
                <span class="lbl">Active Buses</span>
              </div>
              <div class="gauge-stat">
                <span class="num text-danger">{{ $underMaintenanceBusesCount }}</span>
                <span class="lbl">Under Maint.</span>
              </div>
              @if($inactiveBusesCount > 0)
                <div class="gauge-stat">
                  <span class="num text-muted">{{ $inactiveBusesCount }}</span>
                  <span class="lbl">Inactive</span>
                </div>
              @endif
            </div>
          </div>

          <div class="progress-bar-wrap mt-2">
            <div class="progress-bar-fill" style="width: {{ $operationalRate }}%;"></div>
          </div>

          {{-- Fuel Efficiency Summary --}}
          <div class="fuel-efficiency-box mt-4">
            <h4 class="subhead-title">
              <i class="fa-solid fa-gas-pump text-blue"></i> Fleet Fuel Consumption Summary
            </h4>

            @if($fuelSummary && $fuelSummary->has_data)
              <div class="fuel-stats-grid">
                <div class="fuel-stat-tile">
                  <span class="fuel-tile-val">{{ number_format($fuelSummary->total_km, 1) }} km</span>
                  <span class="fuel-tile-lbl">Total Traveled</span>
                </div>
                <div class="fuel-stat-tile">
                  <span class="fuel-tile-val">{{ number_format($fuelSummary->total_liters, 1) }} L</span>
                  <span class="fuel-tile-lbl">Fuel Consumed</span>
                </div>
                <div class="fuel-stat-tile highlight">
                  <span class="fuel-tile-val">{{ $fuelSummary->avg_km_per_liter }} km/L</span>
                  <span class="fuel-tile-lbl">Fleet Avg Economy</span>
                </div>
              </div>
            @else
              <div class="dashboard-empty-state-sm">
                <i class="fa-solid fa-gas-pump text-muted"></i>
                <span>No fuel reports logged yet. Enter daily reports to view metrics.</span>
              </div>
            @endif
          </div>

          {{-- Quick Navigation Tiles --}}
          <div class="quick-nav-tiles mt-4">
            <a href="{{ route('job-orders') }}" class="quick-tile">
              <i class="fa-solid fa-clipboard-list text-blue"></i>
              <span>Job Orders</span>
            </a>
            <a href="{{ route('PMS-Scheduling') }}" class="quick-tile">
              <i class="fa-solid fa-calendar-check text-yellow"></i>
              <span>PMS Schedules</span>
            </a>
            <a href="{{ route('maintenance-referrals') }}" class="quick-tile">
              <i class="fa-solid fa-arrow-right-arrow-left text-red"></i>
              <span>Referrals</span>
            </a>
            <a href="{{ route('mechanic-list') }}" class="quick-tile">
              <i class="fa-solid fa-user-gear text-green"></i>
              <span>Mechanics</span>
            </a>
          </div>

        </div>

      </section>

    </main>

  </div>

</x-layout.app>