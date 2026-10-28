  <x-layout.app
      title="FROMS - Purchase Requisitions"

      :assets="[
          'resources/css/Main-styles/main.css',
          'resources/css/Main-styles/sidebar.css',
          'resources/css/Main-styles/form-components.css',
          'resources/css/Maintenance/purchase-requests.css',
          'resources/js/Main-js/sidebar.js',
          'resources/js/Maintenance/purchase-requests.js'
      ]"
  >

      @php
          $statuses = [
              'Submitted',
              'Rejected',
              'Approved',
              'For Purchase',
              'Ordered',
              'For Pick-up',
              'Picked Up',
              'For Delivery',
              'Delivered',
              'Issued',
          ];

          $submitted = $submitted ?? 0;
          $rejected = $rejected ?? 0;
          $approved = $approved ?? 0;
          $forPurchase = $forPurchase ?? 0;
          $isMaintenanceAdmin = $isMaintenanceAdmin ?? false;
          $workToPerformByJobOrder = $workToPerformByJobOrder ?? collect();

          // Controller supplies only approved JOs that still require procurement.
          $availablePrJobOrders = $jobOrders;
      @endphp

      @if($errors->any())
          <div id="validationErrorModal" class="modal-overlay show active">
              <div class="modal-card delete-modal-box">
                  <div class="delete-icon">
                      <i class="fa-solid fa-triangle-exclamation"></i>
                  </div>
                  <h2>Form Error</h2>
                  <p>Please check the form. Some required information is missing.</p>
                  <ul class="form-error-list">
                      @foreach($errors->all() as $error)
                          <li>{{ $error }}</li>
                      @endforeach
                  </ul>
                  <div class="delete-modal-actions">
                      <button
                          type="button"
                          id="closeValidationErrorModal"
                          class="secondary-btn cancel-delete-btn"
                      >
                          Okay
                      </button>
                  </div>
              </div>
          </div>
      @endif

      <div class="app">
          <x-layout.sidebar department="Maintenance" />

          <main class="main purchase-page">
              <x-layout.topbar
                  title="Purchase Requisitions"
                  subtitle="Manage requested parts and maintenance purchasing records"
                  notification-count="6"
              />

              <section data-ajax-region="summary" class="stats-grid purchase-stats-grid">
                  <x-ui.summary-card label="Submitted" value="{{ $submitted }}" small="Awaiting processing" icon="fa-paper-plane" color="blue" />
                  <x-ui.summary-card label="Rejected" value="{{ $rejected }}" small="Needs revision" icon="fa-xmark" color="red" />
                  <x-ui.summary-card label="Approved" value="{{ $approved }}" small="Approved requests" icon="fa-check" color="purple" />
                  <x-ui.summary-card label="For Purchase" value="{{ $forPurchase }}" small="Ready to purchase" icon="fa-cart-shopping" color="blue" />
              </section>

              <section data-ajax-region="records" class="table-card purchase-request-card {{ $recordView === 'history' ? 'is-history' : 'is-active' }}">
                  <div class="section-header maintenance-record-header">
                      <div>
                          <h2>Purchase Requisition Records</h2>
                      </div>
                      {{-- PR creation is initiated from an approved Job Order shortage, not from a general page action. --}}
                  </div>

                  @if($recordView === 'history')
                      <p class="maintenance-history-note">Issued Purchase Requests are kept here for reference and audit history.</p>
                  @endif

                  <div class="maintenance-list-controls purchase-requests-controls-row">
                      <div class="maintenance-record-tabs" data-maintenance-record-tabs="true" role="tablist" aria-label="Purchase request record view">
                          <a href="{{ route('purchase-requests', ['record_view' => 'pending']) }}" class="maintenance-record-tab {{ $recordView === 'pending' ? 'is-active' : '' }}" data-allow-partial-navigation="true" role="tab" aria-selected="{{ $recordView === 'pending' ? 'true' : 'false' }}"><i class="fa-solid fa-clock"></i><span>Pending Requests</span></a>
                          <a
                              href="{{ route('purchase-requests', array_filter(['record_view' => 'active', 'search' => request('search'), 'status' => 'All Statuses'])) }}"
                              class="maintenance-record-tab {{ $recordView === 'active' ? 'is-active' : '' }}"
                              data-allow-partial-navigation="true"
                              data-maintenance-record-tab-link="active"
                              role="tab"
                              aria-selected="{{ $recordView === 'active' ? 'true' : 'false' }}"
                          >
                              <i class="fa-solid fa-list-check"></i>
                              <span>In Progress</span>
                          </a>

                          <a
                              href="{{ route('purchase-requests', array_filter(['record_view' => 'history', 'search' => request('search')])) }}"
                              class="maintenance-record-tab {{ $recordView === 'history' ? 'is-active' : '' }}"
                              data-allow-partial-navigation="true"
                              data-maintenance-record-tab-link="history"
                              role="tab"
                              aria-selected="{{ $recordView === 'history' ? 'true' : 'false' }}"
                          >
                              <i class="fa-solid fa-clock-rotate-left"></i>
                              <span>History</span>
                          </a>
                      </div>
                      <div class="gct-table-toolbar-row">
                      <x-ui.table-toolbar action="{{ route('purchase-requests') }}" search-label="Search Purchase Requests" search-placeholder="Search PR no., JO no., bus no., or item..." :filter-count="(int) ($recordView === 'history' ? (int) request()->filled('created_from') + (int) request()->filled('created_to') : (int) (request()->filled('status') && request('status') !== 'All Statuses'))" filter-title="Filter Purchase Requests" :sort-options="['created_at' => 'Created Date', 'pr_no' => 'PR Number', 'status' => 'Status']" default-sort="created_at" default-direction="desc" data-server-filter="{{ $recordView === 'history' ? 'true' : 'false' }}">
                          <x-slot:hidden>
                              <input type="hidden" name="record_view" value="{{ $recordView }}">
                          </x-slot:hidden>
                          <x-slot:filters>
                              @if($recordView === 'pending')
                                  <x-ui.table-toolbar-field label="Status" name="status" default="All Statuses">
                                      <option value="All Statuses">All Statuses</option>
                                      <option value="Pending Review" @selected(request('status') === 'Pending Review')>Pending Review</option>
                                  </x-ui.table-toolbar-field>
                              @elseif($recordView !== 'history')
                                  <x-ui.table-toolbar-field label="Status" name="status" default="All Statuses">
                                      <option value="All Statuses">All Statuses</option>
                                      @foreach($statuses as $status)
                                          <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                                      @endforeach
                                  </x-ui.table-toolbar-field>
                              @else
                                  <x-ui.table-toolbar-field label="Created From" name="created_from" type="date" />
                                  <x-ui.table-toolbar-field label="Created To" name="created_to" type="date" />
                              @endif
                          </x-slot:filters>
                      </x-ui.table-toolbar>

                  </div>

                  </div>

                  @if($recordView === 'pending')
                  <div class="table-wrap purchase-table-wrap pr-pending-table-wrap">
                      <table class="purchase-request-table pr-pending-table pr-ir-style-table">
                          <thead><tr><th>JOB ORDER</th><th>SUBMITTED AT</th><th>PARTS FOR PURCHASE</th><th>SHORTAGE STATUS</th><th>PR STATUS</th><th>ACTIONS</th></tr></thead>
                          <tbody>
                          @forelse($pendingPrJobs as $proposal)
                              @php
                                  $shortageParts = collect($proposal['parts'] ?? []);
                                  $sourceJo = $proposal['job'];
                                  // Reference allocation only: JO requirement minus its PR shortage.
                                  // This does not submit an IR or change inventory.
                                  $allJoParts = collect(app(\App\Services\PartParser::class)->parsePartText((string) $sourceJo->part_needed));
                                  $shortageByKey = $shortageParts->groupBy(fn ($p) => mb_strtolower(trim((string) ($p['name'] ?? ''))).'|'.mb_strtolower(trim((string) ($p['unit'] ?? ''))))
                                      ->map(fn ($parts) => $parts->sum(fn ($part) => (int) ($part['quantity'] ?? 0)));
                                  $availableAllocation = $allJoParts->map(function ($part) use ($shortageByKey) {
                                      $key = mb_strtolower(trim((string) ($part['name'] ?? ''))).'|'.mb_strtolower(trim((string) ($part['unit'] ?? '')));
                                      return array_merge($part, ['quantity' => max(0, (int) ($part['quantity'] ?? 0) - (int) $shortageByKey->get($key, 0))]);
                                  })->filter(fn ($part) => $part['quantity'] > 0)->values();
                              @endphp
                              <tr>
                                  <td><strong class="pr-ir-jo-badge">{{ $sourceJo->job_order_no }}</strong></td>
                                  <td><div class="pr-ir-date"><strong>{{ $sourceJo->created_at?->timezone(config('app.business_timezone', 'Asia/Manila'))->format('M d, Y') ?? '—' }}</strong><small>{{ $sourceJo->created_at?->timezone(config('app.business_timezone', 'Asia/Manila'))->format('h:i A') ?? '—' }}</small><small>JO created date</small></div></td>
                                  <td>
                                      <span class="pr-ir-count"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> PR: {{ $shortageParts->count() }} {{ \Illuminate\Support\Str::plural('part', $shortageParts->count()) }}</span>
                                      <details class="pr-pending-parts-details pr-ir-breakdown">
                                          <summary aria-label="View IR allocation and PR shortages for {{ $sourceJo->job_order_no }}">View breakdown <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                                          <div class="pr-pending-parts-list">
                                              <strong class="pr-ir-popover-title is-ir"><i class="fa-solid fa-box" aria-hidden="true"></i> Available → IR ({{ $availableAllocation->count() }})</strong>
                                              @forelse($availableAllocation as $part)
                                                  <div><span>{{ $part['name'] ?? '—' }}</span><strong>{{ $part['quantity'] ?? 0 }} {{ $part['unit'] ?? '' }}</strong></div>
                                              @empty
                                                  <p>No available allocation</p>
                                              @endforelse
                                              <strong class="pr-ir-popover-title is-pr"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> Missing → PR ({{ $shortageParts->count() }})</strong>
                                              @forelse($shortageParts as $part)
                                                  <div><span>{{ $part['name'] ?? '—' }}</span><strong>{{ $part['quantity'] ?? 0 }} {{ $part['unit'] ?? '' }}</strong></div>
                                              @empty
                                                  <p>No missing parts</p>
                                              @endforelse
                                              <p class="pr-ir-allocation-note">IR quantities are a reference allocation from the JO; PR contains shortages only.</p>
                                          </div>
                                      </details>
                                  </td>
                                  <td><span class="pr-ir-shortage-status">Unavailable Stock</span></td>
                                  <td><span class="pr-pending-badge">For Review</span></td>
                                  <td><button type="button" class="pr-pending-review-btn" data-pr-review-job="{{ $sourceJo->job_order_no }}"><i class="fa-solid fa-eye" aria-hidden="true"></i> Review PR</button></td>
                              </tr>
                          @empty
                              <tr><td colspan="6" style="text-align:center;padding:30px">No pending purchase suggestions. Shortages from approved Job Orders appear here automatically.</td></tr>
                          @endforelse
                          </tbody>
                      </table>
                  </div>
                  @else
                  <div class="table-wrap purchase-table-wrap">
                      <table class="purchase-request-table">
                          <thead>
                              <tr>
                                  <th>PR ID</th>
                                  <th>JO ID</th>
                                  <th>Bus #</th>
                                  <th>Parts for Purchase</th>
                                  <th>Qty</th>
                                  <th>Status</th>
                                  <th>Created</th>
                                  <th>Actions</th>
                              </tr>
                          </thead>

                          <tbody>
                              @forelse($purchaseRequests as $pr)
                                  @php
                                      $requestedParts = app(\App\Services\PartParser::class)->parsePartText($pr->item);
                                      $firstRequestedPart = $requestedParts[0] ?? null;
                                      $firstRequestedItem = trim((string) ($firstRequestedPart['name'] ?? $pr->item ?? ''));
                                      $requestedPartCount = count($requestedParts);
                                      $requestedUnits = collect($requestedParts)->pluck('unit')->map(fn ($unit) => trim((string) $unit))->filter()->unique()->values();
                                      // Different measurement units cannot be summed into one meaningful unit.
                                      $hasMixedUnits = $requestedUnits->count() > 1;
                                      $totalQuantityDisplay = $hasMixedUnits
                                          ? $requestedPartCount.' items'
                                          : $pr->quantity.($requestedUnits->first() ? ' '.$requestedUnits->first() : '');
                                      $quantityBreakdown = collect($requestedParts)
                                          ->map(fn ($part) => trim(($part['name'] ?? '').': '.($part['quantity'] ?? '').' '.($part['unit'] ?? '')))
                                          ->implode(' | ');
                                      $firstPartQuantity = $firstRequestedPart
                                          ? trim(($firstRequestedPart['quantity'] ?? '').' '.($firstRequestedPart['unit'] ?? ''))
                                          : null;

                                      $sourceJo = ($prJobApprovals ?? collect())->get($pr->job_order_no);
                                      $sourceJoApprovalAudit = $sourceJo?->audits?->first();
                                      $sourceJoSignaturePath = data_get($sourceJoApprovalAudit?->metadata, 'handwritten_signature_path');
                                      $sourceJoApproved = $sourceJo?->review_status === 'Approved';
                                      $legacyAuthorized = $pr->status === 'Submitted' && $sourceJoApproved;

                                      $isSubmitted = $pr->status === 'Submitted';
                                      $isRejected = $pr->status === 'Rejected';
                                      $canEdit = $isRejected;
                                      $canApproveOrReject = false; // Approval already occurred during Job Order review.
                                      $canDelete = $isSubmitted;
                                  @endphp

                                  <tr>
                                      <td>
                                          <span class="pr-no-pill">{{ $pr->pr_no }}</span>
                                      </td>
                                      <td><span class="pr-jo-pill">{{ $pr->job_order_no ?: '—' }}</span></td>
                                      <td>
                                          <span class="bus-badge-pill"><i class="fa-solid fa-bus"></i> {{ $pr->bus_no }}</span>
                                      </td>
                                      <td class="requested-part-cell">
                                          <span class="pr-part-name" title="{{ $firstRequestedItem }}">{{ $firstRequestedItem ?: '—' }}</span>
                                          @if($requestedPartCount > 1)
                                              <details class="pr-parts-dropdown">
                                                  <summary aria-label="Show {{ $requestedPartCount - 1 }} additional parts for {{ $pr->pr_no }}">
                                                      +{{ $requestedPartCount - 1 }} more <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                                                  </summary>
                                                  <div class="pr-parts-dropdown-menu">
                                                      <strong>Other requested parts</strong>
                                                      @foreach(array_slice($requestedParts, 1) as $part)
                                                          <div class="pr-parts-dropdown-item">
                                                              <span title="{{ $part['name'] }}">{{ $part['name'] }}</span>
                                                              <b>{{ $part['quantity'] }} {{ $part['unit'] }}</b>
                                                          </div>
                                                      @endforeach
                                                  </div>
                                              </details>
                                          @endif
                                      </td>
                                      <td title="{{ $quantityBreakdown }}">{{ $requestedPartCount === 1 ? ($firstPartQuantity ?: $totalQuantityDisplay) : $totalQuantityDisplay }}</td>
                                      <td class="status-col">
                                          <x-ui.status-badge :status="$pr->status" type="purchase" />
                                      </td>
                                      <td class="created-cell">
                                          @if($pr->created_at)
                                              <div class="date-time-cell">
                                                  <strong>{{ $pr->created_at->format('M d, Y') }}</strong>
                                                  <small>{{ $pr->created_at->format('h:i A') }}</small>
                                              </div>
                                          @else
                                              —
                                          @endif
                                      </td>
                                      <td>
                                          <div class="actions pr-review-action-cell">
                                              <button
                                                  type="button"
                                                  class="pr-review-btn open-view-pr-modal"
                                                  title="View Purchase Requisition"
                                                  data-id="{{ $pr->id }}"
                                                  data-pr-no="{{ $pr->pr_no }}"
                                                  data-jo-approved="{{ $sourceJo?->review_status === 'Approved' ? '1' : '0' }}"
                                                  data-jo-review-status="{{ $sourceJo?->review_status ?? ($sourceJo ? 'Pending' : 'JO not found') }}"
                                                  data-jo-approver="{{ $sourceJo?->approver?->name ?? '' }}"
                                                  data-jo-approved-at="{{ ($sourceJo?->approved_at ?? $sourceJoApprovalAudit?->created_at)?->format('M d, Y · h:i A') ?? '' }}"
                                                  data-jo-signature-url="{{ $sourceJoApproved && is_string($sourceJoSignaturePath) && $sourceJoSignaturePath !== '' ? route('job-orders.approval-signature', $sourceJo->id, false) : '' }}"
                                                  data-job-order-no="{{ $pr->job_order_no }}"
                                                  data-bus-no="{{ $pr->bus_no }}"
                                                  data-item="{{ $pr->item }}"
                                                  data-quantity="{{ $pr->quantity }}"
                                                  data-status="{{ $pr->status }}"
                                                  data-remarks="{{ $pr->remarks }}"
                                                  data-purpose="{{ $pr->purpose }}"
                                                  data-target-date="{{ $pr->target_date?->format('M d, Y') }}"
                                                  data-requester="{{ $pr->requester?->name ?: 'Requester not recorded' }}"
                                                  data-created-at="{{ $pr->created_at?->format('M d, Y · h:i A') }}"
                                                  data-source-type="{{ $pr->source_type ?: 'Maintenance Request' }}"
                                                  data-work-to-perform="{{ $workToPerformByJobOrder->get($pr->job_order_no, '') }}"
                                                  data-update-url="{{ route('purchase-requests.update', $pr->id, false) }}"
                                                  data-resubmit-url="{{ route('purchase-requests.resubmit', $pr->id, false) }}"
                                                  data-approve-url="{{ route('purchase-requests.approve', $pr->id, false) }}"
                                                  data-reject-url="{{ route('purchase-requests.reject', $pr->id, false) }}"
                                                  data-can-edit="{{ $recordView !== 'history' && $canEdit ? '1' : '0' }}"
                                                  data-can-approve="{{ $recordView !== 'history' && $canApproveOrReject ? '1' : '0' }}"
                                                  data-can-delete="{{ $recordView !== 'history' && $canDelete ? '1' : '0' }}"
                                                  data-history="{{ $recordView === 'history' ? '1' : '0' }}"
                                              >
                                                  <i class="fa-solid fa-eye"></i>
                                                  <span>View</span>
                                              </button>

                                              @if($recordView !== 'history' && $canDelete)
                                                  <form
                                                      id="deletePrForm-{{ $pr->id }}"
                                                      action="{{ route('purchase-requests.destroy', $pr->id) }}"
                                                      method="POST"
                                                      hidden
                                                  >
                                                      @csrf
                                                      @method('DELETE')
                                                  </form>
                                              @endif
                                          </div>
                                      </td>
                                  </tr>
                              @empty
                                  <x-ui.empty-row
                                      colspan="8"
                                      icon="fa-clipboard-list"
                                      message="No Purchase Requests Found"
                                      description="No maintenance purchase requisitions match your search or filter criteria."
                                  />
                              @endforelse
                          </tbody>
                      </table>
                  </div>

                  <x-ui.table-footer
                      :items="$purchaseRequests"
                      data-lazy-pagination="{{ $recordView === 'history' ? 'true' : 'false' }}"
                  />
                  @endif
              </section>
          </main>
      </div>

      <x-ui.form-modal
          id="prModal"
          title="Review Purchase Request"
          description="Review unavailable parts and their linked approved Job Order."
          icon="fa-file-circle-plus"
          size="wide"
          form-id="newPrForm"
          :action="route('purchase-requests.store')"
          method="POST"
          close-id="closePrModal"
          :confirm="true"
          confirm-title="Submit Purchase Request to Warehouse?"
          confirm-message="Send this JO-authorized Purchase Request to Warehouse for shortage verification?"
          confirm-button="Submit to Warehouse"
          confirm-type="create"
          :show-actions="false"
          class="{{ isset($selectedJobOrder) && $selectedJobOrder ? 'show active' : '' }}"
      >
          <div class="pr-document pr-review-simplified" id="prDocument">
              <section class="pr-step-card pr-step-details" aria-label="Linked Job Order">
                  <div class="pr-step-heading"><span class="pr-step-icon">01</span><div><h3>Job Order Summary</h3><p>Automatically linked approved Job Order</p></div><span class="pr-step-tag">Source details</span></div>
                  <div class="pr-simple-body">
                      <label class="pr-document-field pr-document-field-full">
                          <span>Approved Job Order <span class="ui-required">*</span></span>
                          <select name="job_order_no" id="jobOrderSelect" class="pr-review-source-select" required>
                              <option value="">Select an approved Job Order</option>
                              @foreach($availablePrJobOrders as $jobOrder)
                                  <option value="{{ $jobOrder->job_order_no }}"
                                      data-job-order-url="{{ route('purchase-requests.job-order-data', $jobOrder, false) }}"
                                      @selected(old('job_order_no', $selectedJobOrder?->job_order_no) === $jobOrder->job_order_no)>
                                      {{ $jobOrder->job_order_no }}
                                  </option>
                              @endforeach
                          </select>
                          <div id="prReviewLinkedJo" class="pr-review-linked-jo" aria-live="polite">Linked from Pending Requests</div>
                          <input type="hidden" name="bus_no" id="busNoInput">
                      </label>
                      <div class="pr-simple-source-grid">
                          <div class="pr-simple-source-card"><span>Bus Number</span><strong id="prReviewBus">—</strong></div>
                          <div class="pr-simple-source-card"><span>Assigned Mechanics</span><strong id="prReviewMechanics">—</strong></div>
                      </div>
                      <div class="pr-jo-authorization-heading"><strong>Maintenance Head Authorization</strong><span id="prJoApprovalBadge" class="pr-jo-approval-status" hidden><i class="fa-solid fa-circle-check" aria-hidden="true"></i> JO Approved</span></div>
                      <div class="pr-jo-authorization-grid">
                        <div class="pr-simple-source-card"><span>Approved By</span><strong id="prApproverName">—</strong><small>Maintenance Head</small></div>
                        <div class="pr-jo-signature-reference">
                          <img id="prApprovalSignature" alt="Saved Job Order authorization signature" hidden>
                          <span id="prSignaturePlaceholder">JO signature unavailable</span>
                        </div>
                      </div>
                  </div>
              </section>
              <section class="pr-step-card pr-document-parts" aria-label="Parts requiring purchase">
                  <div class="pr-step-heading"><span class="pr-step-icon">02</span><div><h3>Missing Parts — Purchase Request (PR)</h3><p>Only unavailable quantities sourced from the approved Job Order</p></div><span class="pr-step-tag">JO shortage</span></div>
                  <div class="pr-simple-body">
                      <div class="pr-shortage-table-wrap">
                          <table class="pr-shortage-table pr-review-parts-table">
                              <thead><tr><th scope="col">ITEM / PART</th><th scope="col">SHORTAGE QTY</th><th scope="col">STATUS</th></tr></thead>
                              <tbody id="newPrPartsBody"><tr><td colspan="3" class="pr-table-message">Loading linked Job Order parts…</td></tr></tbody>
                          </table>
                      </div>
                      <p class="pr-stock-note">Only shortage quantities are included. Warehouse verifies availability during processing.</p>
                      <p id="prPartsError" class="pr-inline-error" role="alert" hidden></p>
                  </div>
              </section>
              <section class="pr-step-card pr-step-additional" aria-label="Request details">
                  <div class="pr-step-heading"><span class="pr-step-icon">03</span><div><h3>Request Details</h3><p>Purpose and optional purchasing instructions</p></div></div>
                  <div class="pr-simple-body">
                      <input id="prPurposeBus" type="hidden" value="">
                      <input id="prPurpose" type="hidden" name="purpose" maxlength="1000" value="{{ old('purpose') }}">
                      <div class="pr-simple-request-grid">
                          <label class="pr-document-field">
                              <span>Target Date <small>(Optional)</small></span>
                              <input type="date" name="target_date" min="{{ now()->toDateString() }}" value="{{ old('target_date') }}">
                          </label>
                          <label class="pr-document-field">
                              <span>Requested By</span>
                              <input type="text" value="{{ auth()->user()->name }}" readonly>
                          </label>
                      </div>
                      <label class="pr-document-field pr-document-field-full">
                          <span>Remarks <small>(Optional)</small></span>
                          <textarea name="remarks" id="newPrRemarks" maxlength="1000" placeholder="Additional instructions for purchasing…">{{ old('remarks') }}</textarea>
                      </label>
                  </div>
              </section>
          </div>

          <footer class="pr-document-footer">
              <button type="button" id="cancelPrModal" class="ui-form-btn ui-form-btn-cancel">Cancel</button>
              <button type="submit" id="createPrBtn" class="ui-form-btn ui-form-btn-primary" disabled><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Submit to Warehouse</button>
          </footer>
      </x-ui.form-modal>

      <x-ui.form-modal
          id="editPrModal"
          title="Purchase Requisition Details"
          title-id="editPrModalTitle"
          description="Maintenance purchase requisition information and authorization"
          icon="fa-file-circle-check"
          size="wide"
          form-id="editPrForm"
          action="#"
          method="PUT"
          close-id="closeEditPrModal"
          :show-actions="false"
          :confirm="true"
          confirm-title="Save Purchase Request Changes?"
          confirm-message="Are you sure you want to save these Purchase Request changes?"
          confirm-button="Yes, Save Changes"
          confirm-type="update"
          class="pr-review-modal-overlay"
      >
          <input type="hidden" name="pr_no" id="edit_pr_no">
          <input type="hidden" name="job_order_no" id="edit_job_order_no">
          <input type="hidden" name="bus_no" id="edit_bus_no">
          <input type="hidden" name="status_display" id="edit_status_display">

          <div class="pr-review-summary">
              <div class="pr-review-summary-item">
                  <span>PR ID</span>
                  <strong id="reviewPrNo">—</strong>
              </div>
              <div class="pr-review-summary-item">
                  <span>Current PR Status</span>
                  <strong><span id="reviewPrStatus" class="pr-review-status-pill">—</span></strong>
              </div>
              <div class="pr-review-summary-item">
                  <span>Source JO ID</span>
                  <strong id="reviewPrSummaryJobOrder">—</strong>
                  <small id="reviewPrJoAuthorization">—</small>
              </div>
              <div class="pr-review-summary-item pr-review-created">
                  <span>Created Date</span>
                  <strong id="reviewPrCreated">—</strong>
              </div>
          </div>

          <section id="prReviewInformation" class="pr-review-information-section">
              <div class="pr-review-section-heading pr-review-information-heading">
                  <div class="pr-review-section-title">
                      <span class="pr-review-section-icon">
                          <i class="fa-solid fa-file-lines"></i>
                      </span>
                      <div>
                          <h3>01 — Request Information</h3>
                          <p>Origin and requester information</p>
                      </div>
                  </div>
              </div>

              <div class="pr-review-information">
                  <div class="pr-review-detail-row">
                      <span>Job Order ID</span>
                      <strong id="reviewPrJobOrderNo">—</strong>
                  </div>
                  <div class="pr-review-detail-row">
                      <span>Bus ID</span>
                      <strong id="reviewPrBusNo">—</strong>
                  </div>
                  <div class="pr-review-detail-row">
                      <span>Department</span>
                      <strong>Maintenance</strong>
                  </div>
                  <div class="pr-review-detail-row">
                      <span>Source</span>
                      <strong id="reviewPrSource">Maintenance Request</strong>
                  </div>
                  <div class="pr-review-detail-row">
                      <span>Requested By</span>
                      <strong id="reviewPrRequester">—</strong>
                  </div>
                  <div class="pr-review-detail-row">
                      <span>Request Date</span>
                      <strong id="reviewPrRequestDate">—</strong>
                  </div>
              </div>
          </section>

          <section class="pr-review-section" id="prRequestDetailsSection">
              <div class="pr-review-section-heading">
                  <div class="pr-review-section-title">
                      <span class="pr-review-section-icon"><i class="fa-solid fa-screwdriver-wrench"></i></span>
                      <div><h3>02 — Request Details</h3><p>Repair purpose and requested schedule</p></div>
                  </div>
              </div>
              <div class="pr-review-information">
                  <div class="pr-review-detail-row pr-review-detail-full">
                  <span>Work / Repair to Perform</span>
                  <strong id="reviewPrWorkToPerform">No work / repair details recorded.</strong>
                  </div>
                  <div class="pr-review-detail-row pr-review-detail-full"><span>Purpose</span><strong id="reviewPrPurpose">—</strong></div>
                  <div class="pr-review-detail-row pr-review-detail-full"><span>Remarks</span><strong id="reviewPrRemarks">No remarks provided.</strong></div>
                  <div class="pr-review-detail-row pr-review-detail-full"><span>Target Date</span><strong id="reviewPrTargetDate">—</strong></div>
              </div>
          </section>

          <section id="prReviewItemsSection" class="pr-review-section">
              <div class="pr-review-section-heading">
                  <div class="pr-review-section-title">
                      <span class="pr-review-section-icon">
                          <i class="fa-solid fa-clipboard-list"></i>
                      </span>
                      <div>
                          <h3>03 — Requested Items</h3>
                          <p>Parts and quantities included in this requisition</p>
                      </div>
                  </div>

                  <span id="reviewPrItemCount" class="pr-review-count">0 items</span>
              </div>

              <div class="pr-review-items-table-wrap">
                  <table class="pr-review-items-table">
                      <thead>
                          <tr>
                              <th>#</th>
                              <th>Item / Part</th>
                              <th>Qty</th>
                          </tr>
                      </thead>
                      <tbody id="reviewPrItemsBody">
                          <tr>
                              <td colspan="3" class="pr-review-empty">No requested items recorded.</td>
                          </tr>
                      </tbody>
                  </table>
              </div>
              <div class="pr-review-items-total">
                  <span>Total requested quantity</span>
                  <strong id="reviewPrTotalQuantity">—</strong>
              </div>
          </section>

          <section class="pr-review-section" id="prAuthorizationSection">
              <div class="pr-review-section-heading">
                  <div class="pr-review-section-title">
                      <span class="pr-review-section-icon"><i class="fa-solid fa-shield-halved"></i></span>
                      <div><h3>04 — Authorization</h3><p>Inherited from the source Job Order approval; no second approval required.</p></div>
                  </div>
              </div>
              <div class="pr-review-information">
                  <div class="pr-review-detail-row"><span>Approved By</span><strong id="reviewPrApprover">—</strong></div>
                  <div class="pr-review-detail-row"><span>Approval Date and Time</span><strong id="reviewPrApprovedAt">—</strong></div>
                  <div class="pr-review-detail-row pr-review-detail-full"><span>Source Job Order</span><strong id="reviewPrAuthorizationJobOrder">—</strong></div>
                  <div class="pr-review-detail-row pr-review-detail-full">
                      <span>Authorized Handwritten Signature</span>
                      <div class="pr-review-signature"><img id="reviewPrSignature" alt="Maintenance Head handwritten approval signature" hidden />
                      <strong id="reviewPrSignatureFallback">Not available</strong></div>
                  </div>
              </div>
          </section>

          <section id="prEditableSection" class="pr-edit-workspace" hidden>
              <x-ui.form-section
                  title="Requested Parts"
                  subtitle="Update the requested items before saving or resubmitting."
                  icon="fa-gears"
              >
                  <x-slot:action>
                      <button type="button" id="addEditPrPartBtn" class="ui-btn-small">
                          <i class="fa-solid fa-plus"></i>
                          Add Part
                      </button>
                  </x-slot:action>

                  <p id="editPrDescription" class="pr-edit-description">
                      Review and update this purchase request.
                  </p>

                  <div id="editPrPartsContainer" class="pr-parts-container"></div>
              </x-ui.form-section>

              <div class="ui-form-group ui-form-full pr-edit-remarks">
                  <label for="edit_remarks">Remarks</label>
                  <textarea name="remarks" id="edit_remarks" placeholder="Optional remarks..."></textarea>
              </div>
          </section>

          <div class="ui-form-actions pr-review-footer" id="viewOnlyActions">
              <button type="button" id="closeViewOnlyPr" class="ui-form-btn ui-form-btn-cancel">
                  Close
              </button>
          </div>

          <div class="ui-form-actions" id="editPrMainActions" style="display: none;">
              <button type="button" id="cancelEditPrModal" class="ui-form-btn ui-form-btn-cancel">
                  Cancel
              </button>

              <button type="submit" id="submitEditPrBtn" class="ui-form-btn ui-form-btn-primary">
                  <i id="submitEditPrIcon" class="fa-solid fa-floppy-disk"></i>
                  <span id="submitEditPrText">Save Changes</span>
              </button>
          </div>
      </x-ui.form-modal>

      <form id="approvePrForm" action="#" method="POST" class="hidden">
          @csrf
      </form>

      <form id="rejectPrForm" action="#" method="POST" class="hidden">
          @csrf
          <input id="rejectPrRemarks" type="hidden" name="remarks" value="Rejected by Maintenance">
      </form>

      <x-ui.action-buttom-modal
          mode="delete"
          id="deletePrModal"
          delete-title="Delete Purchase Request?"
          delete-message="Are you sure you want to delete"
          name-id="deletePrNo"
          cancel-id="cancelDeletePr"
          confirm-id="confirmDeletePr"
      />

      @if(session('success'))
          <script>
              document.addEventListener('DOMContentLoaded', function () {
                  const message = @json(session('success'));

                  if (typeof window.showSystemToast === 'function') {
                      window.showSystemToast(message, 'success', 'Success', { timeout: 5000 });
                  } else {
                      console.log(message);
                  }
              });
          </script>
      @endif

      @if(session('error'))
          <script>
              document.addEventListener('DOMContentLoaded', function () {
                  const message = @json(session('error'));

                  if (typeof window.showSystemToast === 'function') {
                      window.showSystemToast(message, 'error', 'Action Failed', { timeout: 7000 });
                  } else {
                      console.error(message);
                  }
              });
          </script>
      @endif

  </x-layout.app>
