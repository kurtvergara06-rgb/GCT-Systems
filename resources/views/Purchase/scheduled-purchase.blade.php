<x-layout.app
  title="FROMS - Scheduled Purchase"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Purchase/scheduled-purchase.css',
    'resources/css/Purchase/purchase-module-ui.css',
    'resources/js/Purchase/scheduled-purchase.js'
  ]"
>
  <x-layout.sidebar department="Purchase" />

  <main class="main scheduled-purchase-page purchase-module-page records-page">
    <x-layout.topbar
      title="Scheduled Purchase"
      subtitle="Plan recurring purchases and create purchase orders when schedules become due"
      notification-count="6"
    />

    <section data-ajax-region="summary" class="stats-grid">
      <x-ui.summary-card label="Total Schedules" value="{{ $totalSchedules }}" small="Recurring purchase plans" icon="fa-calendar-days" color="gray" />
      <x-ui.summary-card label="Active" value="{{ $activeSchedules }}" small="Running schedules" icon="fa-play" color="green" />
      <x-ui.summary-card label="Paused" value="{{ $pausedSchedules }}" small="Temporarily stopped" icon="fa-pause" color="blue" />
      <x-ui.summary-card label="Due This Month" value="{{ $dueThisMonth }}" small="Upcoming purchases" icon="fa-clock" color="red" />
    </section>

    <section data-ajax-region="records" class="table-card schedule-card records-card">
      <div class="section-header purchase-record-header">
        <div>
          <span class="purchase-section-eyebrow">PROCUREMENT PLANNING</span>
          <h2>Recurring Purchase Schedules</h2>
          <p>Track items, suppliers, frequency, due dates, and estimated costs.</p>
        </div>
        <span class="purchase-section-count">{{ $schedules->total() }} schedules</span>
        <button type="button" id="openScheduleModal" class="primary-btn purchase-header-create-btn"><i class="fa-solid fa-plus"></i> New Schedule</button>
      </div>

      <div class="purchase-list-controls purchase-list-controls--toolbar-only">
        <x-ui.table-toolbar action="{{ route('scheduled-purchase') }}" class="records-toolbar" data-server-filter="true" search-label="Search Purchase Schedules" search-placeholder="Search schedule, supplier, item, or frequency..." :filter-count="(int) (request()->filled('frequency') && request('frequency') !== 'All Frequencies') + (int) (request()->filled('status') && request('status') !== 'All Statuses')" filter-title="Filter Purchase Schedules" :sort-options="['next_purchase_date' => 'Next Purchase', 'schedule_name' => 'Schedule Name', 'supplier_name' => 'Supplier', 'estimated_cost' => 'Estimated Cost', 'status' => 'Status']" default-sort="next_purchase_date">
          <x-slot:filters>
            <x-ui.table-toolbar-field label="Frequency" name="frequency" default="All Frequencies"><option value="All Frequencies">All Frequencies</option>@foreach($frequencies as $frequency)<option value="{{ $frequency }}" @selected(request('frequency') === $frequency)>{{ $frequency }}</option>@endforeach</x-ui.table-toolbar-field>
            <x-ui.table-toolbar-field label="Status" name="status" default="All Statuses">@foreach(['All Statuses','Active','Due Soon','Overdue','Paused','Completed'] as $status)<option value="{{ $status }}" @selected(request('status', 'All Statuses') === $status)>{{ $status }}</option>@endforeach</x-ui.table-toolbar-field>
          </x-slot:filters>
        </x-ui.table-toolbar>
      </div>

      <div class="table-wrap records-table-wrap">
        <table class="schedule-table records-table">
          <thead>
            <tr>
              <th>Schedule</th><th>Supplier</th><th>Item</th><th class="center-text">Qty</th><th class="center-text">Frequency</th><th class="center-text">Next Purchase</th><th class="center-text">Estimated Cost</th><th class="center-text">Status</th><th class="center-text">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($schedules as $schedule)
              @php $displayStatus = $schedule->display_status; @endphp
              <tr>
                <td>
                  <div class="two-line-cell">
                    <strong>{{ $schedule->schedule_name }}</strong>
                    <small class="schedule-record-id">{{ $schedule->schedule_no }}</small>
                  </div>
                </td>
                <td><div class="two-line-cell"><strong>{{ $schedule->supplier_name }}</strong><small>{{ $schedule->supplier_contact ?: 'No contact details' }}</small></div></td>
                <td>{{ $schedule->item }}</td>
                <td class="center-text">{{ number_format((int) $schedule->quantity) }} {{ $schedule->unit }}</td>
                <td class="center-text">{{ $schedule->frequency }}</td>
                <td class="center-text"><div class="two-line-cell"><strong>{{ $schedule->next_purchase_date->format('M d, Y') }}</strong><small>{{ $schedule->next_purchase_date->format('l') }}</small></div></td>
                <td class="center-text amount-cell">₱{{ number_format((float) $schedule->estimated_cost, 2) }}</td>
                <td class="center-text"><x-ui.status-badge :status="$displayStatus" /></td>
                <td class="center-text">
                  <div class="actions record-actions">
                    <button type="button" class="action-btn view open-view-schedule" title="View" data-schedule='@json($schedule)'><i class="fa-solid fa-eye"></i></button>
                    <button type="button" class="action-btn edit open-edit-schedule" title="Edit" data-schedule='@json($schedule)' data-update-url="{{ route('scheduled-purchase.update', $schedule) }}"><i class="fa-solid fa-pen-to-square"></i></button>

                    @if($schedule->status !== 'Completed')
                      <form method="POST" action="{{ route('scheduled-purchase.toggle-status', $schedule) }}" data-confirm-form data-confirm-title="{{ $schedule->status === 'Paused' ? 'Resume Scheduled Purchase?' : 'Pause Scheduled Purchase?' }}" data-confirm-message="Are you sure you want to {{ $schedule->status === 'Paused' ? 'resume' : 'pause' }} {{ $schedule->schedule_name }}?" data-confirm-button="{{ $schedule->status === 'Paused' ? 'Yes, Resume' : 'Yes, Pause' }}" data-confirm-type="status">
                        @csrf @method('PATCH')
                        <button type="submit" class="action-btn {{ $schedule->status === 'Paused' ? 'resume' : 'pause' }}" title="{{ $schedule->status === 'Paused' ? 'Resume' : 'Pause' }}"><i class="fa-solid {{ $schedule->status === 'Paused' ? 'fa-play' : 'fa-pause' }}"></i></button>
                      </form>
                    @endif

                    @if($schedule->status === 'Active' && $schedule->next_purchase_date->lte(today()))
                      <form method="POST" action="{{ route('scheduled-purchase.create-po', $schedule) }}" data-confirm-form data-confirm-title="Create PO from Schedule?" data-confirm-message="Are you sure you want to create a purchase order from {{ $schedule->schedule_name }}?" data-confirm-button="Yes, Create PO" data-confirm-type="create">
                        @csrf
                        <button type="submit" class="action-btn create-po" title="Create PO"><i class="fa-solid fa-cart-plus"></i></button>
                      </form>
                    @endif

                    <form method="POST" action="{{ route('scheduled-purchase.destroy', $schedule) }}" data-confirm-form data-confirm-title="Delete Scheduled Purchase?" data-confirm-message="Are you sure you want to delete {{ $schedule->schedule_name }}? This action cannot be undone." data-confirm-button="Yes, Delete" data-confirm-type="delete">
                      @csrf @method('DELETE')
                      <button type="submit" class="action-btn delete" title="Delete"><i class="fa-solid fa-trash"></i></button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <x-ui.empty-row
                colspan="9"
                icon="fa-calendar-check"
                message="No Scheduled Purchases Found"
                description="Upcoming scheduled purchases and recurring vendor orders will appear here."
              />
            @endforelse
          </tbody>
        </table>
      </div>

      <x-ui.table-footer :items="$schedules" data-lazy-pagination="true" />
    </section>
  </main>

  <div id="scheduleModal" class="schedule-modal-overlay">
    <div class="schedule-modal">
      <div class="schedule-modal-header">
        <div><h2 id="scheduleModalTitle">New Purchase Schedule</h2><p id="scheduleModalSubtitle">Create a recurring procurement plan.</p></div>
        <button type="button" id="closeScheduleModal" class="modal-close-btn"><i class="fa-solid fa-xmark"></i></button>
      </div>

      <form id="scheduleForm" method="POST" action="{{ route('scheduled-purchase.store') }}" data-store-url="{{ route('scheduled-purchase.store') }}" data-confirm-form data-confirm-title="Create Scheduled Purchase?" data-confirm-message="Are you sure you want to create this scheduled purchase?" data-confirm-button="Yes, Create Schedule" data-confirm-type="create">
        @csrf
        <input type="hidden" name="_method" id="scheduleFormMethod" value="POST">
        <input type="hidden" name="status" id="scheduleStatus" value="Active">

        <div class="schedule-form-grid">
          <div class="form-group"><label>Schedule Name</label><input type="text" name="schedule_name" id="scheduleName" required></div>
          <div class="form-group"><label>Supplier</label><input type="text" name="supplier_name" id="scheduleSupplier" required></div>
          <div class="form-group"><label>Supplier Contact</label><input type="text" name="supplier_contact" id="scheduleContact"></div>
          <div class="form-group"><label>Item</label><input type="text" name="item" id="scheduleItem" required></div>
          <div class="form-group"><label>Quantity</label><input type="number" name="quantity" id="scheduleQuantity" min="1" step="1" value="1" inputmode="numeric" required></div>
          <div class="form-group">
            <label>Unit</label>
            <select name="unit" id="scheduleUnit" required>
              @foreach(['PC','Liter','Gallon','Box','Pack','Set','Pair','Kilogram','Meter','Roll'] as $unit)
                <option value="{{ $unit }}">{{ $unit }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group"><label>Frequency</label><select name="frequency" id="scheduleFrequency" required>@foreach($frequencies as $frequency)<option value="{{ $frequency }}">{{ $frequency }}</option>@endforeach</select></div>
          <div class="form-group hidden" id="customIntervalGroup"><label>Custom Interval (Days)</label><input type="number" name="custom_interval_days" id="customIntervalDays" min="1" step="1"></div>
          <div class="form-group"><label>Start Date</label><input type="date" name="start_date" id="scheduleStartDate" value="{{ now()->toDateString() }}" required></div>
          <div class="form-group"><label>Next Purchase Date</label><input type="date" name="next_purchase_date" id="scheduleNextDate" value="{{ now()->toDateString() }}" readonly required></div>
          <div class="form-group"><label>Estimated Total Cost</label><input type="number" name="estimated_cost" id="scheduleEstimatedCost" min="0" step="0.01" value="0" required></div>
          <div class="form-group full"><label>Notes</label><textarea name="notes" id="scheduleNotes" rows="4" placeholder="Optional schedule notes..."></textarea></div>
        </div>

        <div class="schedule-modal-actions">
          <button type="button" id="cancelScheduleModal" class="secondary-btn">Cancel</button>
          <button type="submit" id="saveScheduleBtn" class="primary-btn">Save Schedule</button>
        </div>
      </form>
    </div>
  </div>
</x-layout.app>
