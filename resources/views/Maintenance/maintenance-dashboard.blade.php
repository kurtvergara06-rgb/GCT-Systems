<x-layout.app
  title="FROMS - Maintenance Dashboard"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Maintenance/maintenance-dashboard.css',
    'resources/js/Main-js/sidebar.js'
  ]"
>

  @php
    $totalJobOrders = $totalJobOrders ?? 0;
    $ongoingJobOrders = $ongoingJobOrders ?? 0;
    $completedJobOrders = $completedJobOrders ?? 0;
    $urgentRepairs = $urgentRepairs ?? 0;

    $availableMechanics = $availableMechanics ?? 0;
    $onDutyMechanics = $onDutyMechanics ?? 0;
    $onLeaveMechanics = $onLeaveMechanics ?? 0;

    $submittedPr = $submittedPr ?? 0;
    $approvedPr = $approvedPr ?? 0;
    $forPurchasePr = $forPurchasePr ?? 0;

    $upcomingPms = $upcomingPms ?? 0;
    $overduePms = $overduePms ?? 0;

    $recentJobOrders = $recentJobOrders ?? collect();
  @endphp>


  <div class="app maintenance-dashboard-page">

    {{-- =====================================================
        SIDEBAR
    ====================================================== --}}
    <x-layout.sidebar
      department="Maintenance"
      subtitle="Department Module"
      icon="fa-truck"
      :items="[
        [
          'label' => 'Dashboard',
          'route' => 'maintenance-dashboard',
          'icon' => 'fa-table-cells-large'
        ],
        [
          'label' => 'Job Orders',
          'route' => 'job-orders',
          'icon' => 'fa-clipboard-list'
        ],
        [
          'label' => 'Mechanic List',
          'route' => 'mechanic-list',
          'icon' => 'fa-bus'
        ],
        [
          'label' => 'PMS Scheduling',
          'route' => 'PMS-Scheduling',
          'icon' => 'fa-calendar-check'
        ],
        [
          'label' => 'Purchase Requests',
          'route' => 'purchase-requests',
          'icon' => 'fa-file-invoice'
        ],
        [
          'label' => 'Fuel Reports',
          'route' => 'fuel-reports',
          'icon' => 'fa-gas-pump'
        ],
      ]"
    />


    <main class="main maintenance-dashboard-main">

      {{-- =====================================================
          TOPBAR
      ====================================================== --}}
      <x-layout.topbar
        title="Maintenance Dashboard"
        subtitle="Monitor job orders, mechanic availability, PMS schedules, and purchase requests"
        notification-count="6"
      />


      {{-- =====================================================
          SUMMARY CARDS
      ====================================================== --}}
      <section class="stats-grid maintenance-stats-grid">

        <x-ui.summary-card
          label="Total Job Orders"
          value="{{ $totalJobOrders }}"
          small="All maintenance records"
          icon="fa-clipboard-list"
          color="blue"
        />

        <x-ui.summary-card
          label="On Going"
          value="{{ $ongoingJobOrders }}"
          small="Currently being serviced"
          icon="fa-screwdriver-wrench"
          color="yellow"
        />

        <x-ui.summary-card
          label="Completed"
          value="{{ $completedJobOrders }}"
          small="Finished job orders"
          icon="fa-circle-check"
          color="green"
        />

        <x-ui.summary-card
          label="Urgent Repairs"
          value="{{ $urgentRepairs }}"
          small="Requires immediate attention"
          icon="fa-triangle-exclamation"
          color="red"
        />

      </section>


      {{-- =====================================================
          MAIN DASHBOARD GRID
      ====================================================== --}}
      <section class="maintenance-dashboard-grid">

        {{-- =================================================
            RECENT JOB ORDERS
        ================================================== --}}
        <div class="maintenance-dashboard-card recent-job-orders-card">

          <div class="dashboard-card-header">

            <div>
              <span class="dashboard-eyebrow">
                MAINTENANCE ACTIVITY
              </span>

              <h2>
                Recent Job Orders
              </h2>

              <p>
                Latest maintenance and repair work records.
              </p>
            </div>


            <a
              href="{{ route('job-orders') }}"
              class="dashboard-view-link"
            >
              View Job Orders

              <i class="fa-solid fa-arrow-right"></i>
            </a>

          </div>


          <div class="recent-job-list">

            @forelse($recentJobOrders as $jobOrder)

              @php
                $statusClass = match($jobOrder->status ?? '') {
                  'Completed' => 'completed',
                  'On Going' => 'ongoing',
                  'Urgent Repair' => 'urgent',
                  'On Hold' => 'hold',
                  default => 'default',
                };
              @endphp


              <div class="recent-job-item">

                <div class="recent-job-icon {{ $statusClass }}">

                  <i class="fa-solid fa-screwdriver-wrench"></i>

                </div>


                <div class="recent-job-content">

                  <div class="recent-job-heading">

                    <div>

                      <h3>
                        {{ $jobOrder->job_order_no ?? 'Job Order' }}
                      </h3>

                      <p>
                        {{ $jobOrder->bus_no ?? 'No Bus Assigned' }}
                      </p>

                    </div>


                    <span class="dashboard-status {{ $statusClass }}">
                      {{ $jobOrder->status ?? 'Unknown' }}
                    </span>

                  </div>


                  <div class="recent-job-description">

                    {{
                      $jobOrder->problem_issue
                      ?? 'No maintenance issue description.'
                    }}

                  </div>


                  <div class="recent-job-meta">

                    <span>
                      <i class="fa-solid fa-wrench"></i>

                      {{
                        $jobOrder->maintenance_type
                        ?? 'Maintenance'
                      }}
                    </span>


                    <span>
                      <i class="fa-solid fa-user-gear"></i>

                      {{
                        $jobOrder->assigned_mechanic
                        ?: 'Unassigned'
                      }}
                    </span>

                  </div>

                </div>

              </div>

            @empty

              <div class="dashboard-empty-state">

                <div class="dashboard-empty-icon">

                  <i class="fa-solid fa-clipboard-list"></i>

                </div>

                <h3>
                  No recent job orders
                </h3>

                <p>
                  Newly created maintenance records will appear here.
                </p>

              </div>

            @endforelse

          </div>

        </div>


        {{-- =================================================
            MECHANIC AVAILABILITY
        ================================================== --}}
        <div class="maintenance-dashboard-card">

          <div class="dashboard-card-header">

            <div>
              <span class="dashboard-eyebrow">
                WORKFORCE
              </span>

              <h2>
                Mechanic Availability
              </h2>

              <p>
                Current mechanic attendance from Operation.
              </p>
            </div>


            <a
              href="{{ route('mechanic-list') }}"
              class="dashboard-view-link"
            >
              View Mechanics

              <i class="fa-solid fa-arrow-right"></i>
            </a>

          </div>


          <div class="dashboard-status-list">

            <div class="dashboard-status-row">

              <div class="dashboard-status-info">

                <span class="dashboard-status-icon available">

                  <i class="fa-solid fa-user-check"></i>

                </span>

                <div>

                  <h3>
                    Available
                  </h3>

                  <p>
                    Ready for job assignment
                  </p>

                </div>

              </div>


              <strong>
                {{ $availableMechanics }}
              </strong>

            </div>


            <div class="dashboard-status-row">

              <div class="dashboard-status-info">

                <span class="dashboard-status-icon duty">

                  <i class="fa-solid fa-screwdriver-wrench"></i>

                </span>

                <div>

                  <h3>
                    On Duty
                  </h3>

                  <p>
                    Currently assigned mechanics
                  </p>

                </div>

              </div>


              <strong>
                {{ $onDutyMechanics }}
              </strong>

            </div>


            <div class="dashboard-status-row">

              <div class="dashboard-status-info">

                <span class="dashboard-status-icon leave">

                  <i class="fa-solid fa-calendar-minus"></i>

                </span>

                <div>

                  <h3>
                    On Leave
                  </h3>

                  <p>
                    Approved leave records
                  </p>

                </div>

              </div>


              <strong>
                {{ $onLeaveMechanics }}
              </strong>

            </div>

          </div>

        </div>

      </section>


      {{-- =====================================================
          SECOND ROW
      ====================================================== --}}
      <section class="maintenance-bottom-grid">

        {{-- =================================================
            PMS
        ================================================== --}}
        <div class="maintenance-dashboard-card">

          <div class="dashboard-card-header">

            <div>
              <span class="dashboard-eyebrow">
                PREVENTIVE MAINTENANCE
              </span>

              <h2>
                PMS Overview
              </h2>

              <p>
                Preventive maintenance schedule status.
              </p>
            </div>


            <a
              href="{{ route('PMS-Scheduling') }}"
              class="dashboard-view-link"
            >
              View PMS

              <i class="fa-solid fa-arrow-right"></i>
            </a>

          </div>


          <div class="dashboard-mini-grid">

            <div class="dashboard-mini-card">

              <div class="dashboard-mini-icon blue">

                <i class="fa-solid fa-calendar-check"></i>

              </div>


              <div>

                <span>
                  Upcoming
                </span>

                <strong>
                  {{ $upcomingPms }}
                </strong>

                <p>
                  Scheduled maintenance
                </p>

              </div>

            </div>


            <div class="dashboard-mini-card">

              <div class="dashboard-mini-icon red">

                <i class="fa-solid fa-triangle-exclamation"></i>

              </div>


              <div>

                <span>
                  Overdue
                </span>

                <strong>
                  {{ $overduePms }}
                </strong>

                <p>
                  Needs immediate review
                </p>

              </div>

            </div>

          </div>

        </div>


        {{-- =================================================
            PURCHASE REQUESTS
        ================================================== --}}
        <div class="maintenance-dashboard-card">

          <div class="dashboard-card-header">

            <div>
              <span class="dashboard-eyebrow">
                PART REQUESTS
              </span>

              <h2>
                Purchase Requests
              </h2>

              <p>
                Current maintenance purchasing workflow.
              </p>
            </div>


            <a
              href="{{ route('purchase-requests') }}"
              class="dashboard-view-link"
            >
              View Requests

              <i class="fa-solid fa-arrow-right"></i>
            </a>

          </div>


          <div class="purchase-summary-list">

            <div class="purchase-summary-row">

              <div>

                <span class="purchase-dot submitted"></span>

                Submitted

              </div>


              <strong>
                {{ $submittedPr }}
              </strong>

            </div>


            <div class="purchase-summary-row">

              <div>

                <span class="purchase-dot approved"></span>

                Approved

              </div>


              <strong>
                {{ $approvedPr }}
              </strong>

            </div>


            <div class="purchase-summary-row">

              <div>

                <span class="purchase-dot purchase"></span>

                For Purchase

              </div>


              <strong>
                {{ $forPurchasePr }}
              </strong>

            </div>

          </div>

        </div>


        {{-- =================================================
            QUICK ACTIONS
        ================================================== --}}
        <div class="maintenance-dashboard-card">

          <div class="dashboard-card-header">

            <div>
              <span class="dashboard-eyebrow">
                QUICK ACCESS
              </span>

              <h2>
                Maintenance Actions
              </h2>

              <p>
                Common maintenance department tasks.
              </p>

            </div>

          </div>


          <div class="maintenance-quick-actions">

            <a
              href="{{ route('job-orders') }}"
              class="maintenance-quick-item"
            >

              <span class="maintenance-quick-icon blue">

                <i class="fa-solid fa-clipboard-list"></i>

              </span>


              <div>

                <h3>
                  Job Orders
                </h3>

                <p>
                  Review maintenance work
                </p>

              </div>


              <i class="fa-solid fa-chevron-right"></i>

            </a>


            <a
              href="{{ route('PMS-Scheduling') }}"
              class="maintenance-quick-item"
            >

              <span class="maintenance-quick-icon yellow">

                <i class="fa-solid fa-calendar-check"></i>

              </span>


              <div>

                <h3>
                  PMS Scheduling
                </h3>

                <p>
                  Review preventive maintenance
                </p>

              </div>


              <i class="fa-solid fa-chevron-right"></i>

            </a>


            <a
              href="{{ route('purchase-requests') }}"
              class="maintenance-quick-item"
            >

              <span class="maintenance-quick-icon green">

                <i class="fa-solid fa-file-invoice"></i>

              </span>


              <div>

                <h3>
                  Purchase Requests
                </h3>

                <p>
                  Track requested parts
                </p>

              </div>


              <i class="fa-solid fa-chevron-right"></i>

            </a>

          </div>

        </div>

      </section>

    </main>

  </div>

</x-layout.app>