<x-layout.app
  title="FROMS - Scheduled Purchase"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Purchase/scheduled-purchase.css',
    'resources/js/Purchase/scheduled-purchase.js'
  ]"
>
  <x-ui.action-buttom-modal
    mode="feedback"
    feedback-type="error"
    :message="session('error')"
  />

  <div class="app">
    <x-layout.sidebar
      department="Purchase"
      subtitle="Department Module"
      icon="fa-cart-shopping"
      user-name="P. Admin"
      user-role="Purchase Admin"
      :items="[
        ['label' => 'Purchase Orders', 'route' => 'purchase-orders', 'icon' => 'fa-file-invoice'],
        [
          'label' => 'Requested Purchase',
          'icon' => 'fa-clipboard-list',
          'children' => [
            ['label' => 'Maintenance Requests', 'route' => 'maintenance-requests', 'icon' => 'fa-screwdriver-wrench'],
            ['label' => 'Inventory Restock', 'route' => 'inventory-restock', 'icon' => 'fa-boxes-stacked'],
          ],
        ],
        ['label' => 'Scheduled Purchase', 'route' => 'scheduled-purchase', 'icon' => 'fa-calendar-check'],
      ]"
    />

    <main class="main">
      <x-layout.topbar
        title="Scheduled Purchase"
        subtitle="Plan recurring purchases and create purchase orders when schedules become due"
        notification-count="6"
      />

      <section class="stats-grid">
        <x-ui.summary-card label="Total Schedules" value="{{ $totalSchedules }}" small="Recurring purchase plans" icon="fa-calendar-days" color="gray" />
        <x-ui.summary-card label="Active" value="{{ $activeSchedules }}" small="Running schedules" icon="fa-play" color="green" />
        <x-ui.summary-card label="Paused" value="{{ $pausedSchedules }}" small="Temporarily stopped" icon="fa-pause" color="blue" />
        <x-ui.summary-card label="Due This Month" value="{{ $dueThisMonth }}" small="Upcoming purchases" icon="fa-clock" color="red" />
      </section>

      <section class="table-card schedule-card">
        <div class="section-header">
          <div>
            <h2>Recurring Purchase Schedules</h2>
            <p>Track items, suppliers, frequency, due dates, and estimated costs.</p>
          </div>
        </div>

        <form method="GET" action="{{ route('scheduled-purchase') }}" class="schedule-toolbar">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search schedule, supplier, item, or frequency...">
          </div>

          <div class="filter-group">
            <label for="frequencyFilter">Frequency</label>
            <select name="frequency" id="frequencyFilter" onchange="this.form.submit()">
              <option value="All Frequencies" {{ request('frequency', 'All Frequencies') === 'All Frequencies' ? 'selected' : '' }}>All Frequencies</option>
              @foreach($frequencies as $frequency)
                <option value="{{ $frequency }}" {{ request('frequency') === $frequency ? 'selected' : '' }}>{{ $frequency }}</option>
              @endforeach
            </select>
          </div>

          <div class="filter-group">
            <label for="statusFilter">Status</label>
            <select name="status" id="statusFilter" onchange="this.form.submit()">
              @foreach(['All Statuses','Active','Due Soon','Overdue','Paused','Completed'] as $status)
                <option value="{{ $status }}" {{ request('status', 'All Statuses') === $status ? 'selected' : '' }}>{{ $status }}</option>
              @endforeach
            </select>
          </div>

          <button type="button" id="openScheduleModal" class="primary-btn">
            <i class="fa-solid fa-plus"></i>
            New Schedule
          </button>
        </form>

        <div class="table-wrap">
          <table class="schedule-table">
            <thead>
              <tr>
                <th>Schedule</th>
                <th>Supplier</th>
                <th>Item</th>
                <th class="center-text">Qty</th>
                <th class="center-text">Frequency</th>
                <th class="center-text">Next Purchase</th>
                <th class="center-text">Estimated Cost</th>
                <th class="center-text">Status</th>
                <th class="center-text">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($schedules as $schedule)
                @php
                  $displayStatus = $schedule->display_status;
                  $statusClass = strtolower(str_replace(' ', '-', $displayStatus));
                @endphp
                <tr>
                  <td>
                    <div class="two-line-cell">
                      <strong>{{ $schedule->schedule_name }}</strong>
                      <small>{{ $schedule->schedule_no }}</small>
                    </div>
                  </td>
                  <td>
                    <div class="two-line-cell">
                      <strong>{{ $schedule->supplier_name }}</strong>
                      <small>{{ $schedule->supplier_contact ?: 'No contact details' }}</small>
                    </div>
                  </td>
                  <td>{{ $schedule->item }}</td>
                  <td class="center-text">{{ number_format((int) $schedule->quantity) }} {{ $schedule->unit }}</td>
                  <td class="center-text">{{ $schedule->frequency }}</td>
                  <td class="center-text">
                    <div class="two-line-cell">
                      <strong>{{ $schedule->next_purchase_date->format('M d, Y') }}</strong>
                      <small>{{ $schedule->next_purchase_date->format('l') }}</small>
                    </div>
                  </td>
                  <td class="center-text amount-cell">₱{{ number_format((float) $schedule->estimated_cost, 2) }}</td>
                  <td class="center-text"><span class="schedule-status {{ $statusClass }}">{{ $displayStatus }}</span></td>
                  <td class="center-text">
                    <div class="actions">
                      <button type="button" class="action-btn view open-view-schedule" title="View" data-schedule='@json($schedule)'>
                        <i class="fa-solid fa-eye"></i>
                      </button>

                      <button type="button" class="action-btn edit open-edit-schedule" title="Edit" data-schedule='@json($schedule)' data-update-url="{{ route('scheduled-purchase.update', $schedule) }}">
                        <i class="fa-solid fa-pen-to-square"></i>
                      </button>

                      @if($schedule->status !== 'Completed')
                        <form method="POST" action="{{ route('scheduled-purchase.toggle-status', $schedule) }}">
                          @csrf
                          @method('PATCH')
                          <button type="submit" class="action-btn {{ $schedule->status === 'Paused' ? 'resume' : 'pause' }}" title="{{ $schedule->status === 'Paused' ? 'Resume' : 'Pause' }}">
                            <i class="fa-solid {{ $schedule->status === 'Paused' ? 'fa-play' : 'fa-pause' }}"></i>
                          </button>
                        </form>
                      @endif

                      @if($schedule->status === 'Active' && $schedule->next_purchase_date->lte(today()))
                        <form method="POST" action="{{ route('scheduled-purchase.create-po', $schedule) }}" onsubmit="return confirm('Create a purchase order from this schedule?')">
                          @csrf
                          <button type="submit" class="action-btn create-po" title="Create PO">
                            <i class="fa-solid fa-cart-plus"></i>
                          </button>
                        </form>
                      @endif

                      <form method="POST" action="{{ route('scheduled-purchase.destroy', $schedule) }}" onsubmit="return confirm('Delete this schedule?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="action-btn delete" title="Delete">
                          <i class="fa-solid fa-trash"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              @empty
                <x-ui.empty-row colspan="9" message="No scheduled purchases found." />
              @endforelse
            </tbody>
          </table>
        </div>

        <x-ui.table-footer :items="$schedules" />
      </section>
    </main>
  </div>

  <div id="scheduleModal" class="schedule-modal-overlay">
    <div class="schedule-modal">
      <div class="schedule-modal-header">
        <div>
          <h2 id="scheduleModalTitle">New Purchase Schedule</h2>
          <p id="scheduleModalSubtitle">Create a recurring procurement plan.</p>
        </div>
        <button type="button" id="closeScheduleModal" class="modal-close-btn">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <form id="scheduleForm" method="POST" action="{{ route('scheduled-purchase.store') }}" data-store-url="{{ route('scheduled-purchase.store') }}">
        @csrf
        <input type="hidden" name="_method" id="scheduleFormMethod" value="POST">
        <input type="hidden" name="status" id="scheduleStatus" value="Active">

        <div class="schedule-form-grid">
          <div class="form-group">
            <label>Schedule Name</label>
            <input type="text" name="schedule_name" id="scheduleName" required>
          </div>

          <div class="form-group">
            <label>Supplier</label>
            <input type="text" name="supplier_name" id="scheduleSupplier" required>
          </div>

          <div class="form-group">
            <label>Supplier Contact</label>
            <input type="text" name="supplier_contact" id="scheduleContact">
          </div>

          <div class="form-group">
            <label>Item</label>
            <input type="text" name="item" id="scheduleItem" required>
          </div>

          <div class="form-group">
            <label>Quantity</label>
            <input type="number" name="quantity" id="scheduleQuantity" min="1" step="1" value="1" inputmode="numeric" required>
          </div>

          <div class="form-group">
            <label>Unit</label>
            <select name="unit" id="scheduleUnit" required>
              <option value="PC">PC</option>
              <option value="Liter">Liter</option>
              <option value="Gallon">Gallon</option>
              <option value="Box">Box</option>
              <option value="Pack">Pack</option>
              <option value="Set">Set</option>
              <option value="Pair">Pair</option>
              <option value="Kilogram">Kilogram</option>
              <option value="Meter">Meter</option>
              <option value="Roll">Roll</option>
            </select>
          </div>

          <div class="form-group">
            <label>Frequency</label>
            <select name="frequency" id="scheduleFrequency" required>
              @foreach($frequencies as $frequency)
                <option value="{{ $frequency }}">{{ $frequency }}</option>
              @endforeach
            </select>
          </div>

          <div class="form-group hidden" id="customIntervalGroup">
            <label>Custom Interval (Days)</label>
            <input type="number" name="custom_interval_days" id="customIntervalDays" min="1" step="1">
          </div>

          <div class="form-group">
            <label>Start Date</label>
            <input type="date" name="start_date" id="scheduleStartDate" value="{{ now()->toDateString() }}" required>
          </div>

          <div class="form-group">
            <label>Next Purchase Date</label>
            <input type="date" name="next_purchase_date" id="scheduleNextDate" value="{{ now()->toDateString() }}" readonly required>
          </div>

          <div class="form-group">
            <label>Estimated Total Cost</label>
            <input type="number" name="estimated_cost" id="scheduleEstimatedCost" min="0" step="0.01" value="0" required>
          </div>

          <div class="form-group full">
            <label>Notes</label>
            <textarea name="notes" id="scheduleNotes" rows="4" placeholder="Optional schedule notes..."></textarea>
          </div>
        </div>

        <div class="schedule-modal-actions">
          <button type="button" id="cancelScheduleModal" class="secondary-btn">Cancel</button>
          <button type="submit" id="saveScheduleBtn" class="primary-btn">Save Schedule</button>
        </div>
      </form>
    </div>
  </div>
</x-layout.app>