  <x-layout.app
      title="FROMS - Purchase Requisitions"

      :assets="[
          'resources/css/Main-styles/main.css',
          'resources/css/Main-styles/sidebar.css',
          'resources/css/Main-styles/form-components.css',
          'resources/css/Maintenance/purchase-requests.css',
          'resources/css/Maintenance/request-modal-shared.css',
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
                          <thead><tr><th>JOB ORDER</th><th>SUBMITTED AT</th><th>PARTS FOR PURCHASE</th><th>STOCK STATUS</th><th>PR STATUS</th><th>RELEASE POLICY</th><th>ACTIONS</th></tr></thead>
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
                                      <div class="pr-ir-counts">
                                          <span class="pr-ir-count"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> PR: {{ $shortageParts->count() }} {{ \Illuminate\Support\Str::plural('part', $shortageParts->count()) }}</span>
                                          <span class="pr-ir-count is-ir"><i class="fa-solid fa-box" aria-hidden="true"></i> IR: {{ $availableAllocation->count() }} {{ \Illuminate\Support\Str::plural('part', $availableAllocation->count()) }}</span>
                                      </div>
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
                                  <td><span class="pr-ir-shortage-status {{ $availableAllocation->isNotEmpty() ? 'is-partial' : '' }}">{{ $availableAllocation->isNotEmpty() ? 'Partial Stock' : 'Unavailable Stock' }}</span></td>
                                  <td><span class="pr-pending-badge pr-ir-status-box"><i class="fa-regular fa-clock" aria-hidden="true"></i> For Review</span></td>
                                  <td><span class="pr-ir-release-policy {{ $sourceJo->parts_issue_strategy === 'issue_available_only' ? 'is-partial' : 'is-waiting' }}"><i class="fa-solid {{ $sourceJo->parts_issue_strategy === 'issue_available_only' ? 'fa-circle-check' : 'fa-clock' }}" aria-hidden="true"></i> {{ $sourceJo->parts_issue_strategy === 'issue_available_only' ? 'Issue Available Only' : 'Wait Until Complete' }}</span></td>
                                  <td><button type="button" class="pr-pending-review-btn" data-pr-review-job="{{ $sourceJo->job_order_no }}"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Review PR</button></td>
                              </tr>
                          @empty
                              <tr><td colspan="7" style="text-align:center;padding:30px">No pending purchase suggestions. Shortages from approved Job Orders appear here automatically.</td></tr>
                          @endforelse
                          </tbody>
                      </table>
                  </div>
                  @else
                  <div class="table-wrap purchase-table-wrap pr-pending-table-wrap">
                      <table class="purchase-request-table pr-ir-style-table pr-active-style-table">
                          <thead><tr><th>JOB ORDER / PR</th><th>SUBMITTED AT</th><th>PARTS FOR PURCHASE</th><th>STOCK STATUS</th><th>PR STATUS</th><th>RELEASE POLICY</th><th>ACTIONS</th></tr></thead>
                          <tbody>
                              @forelse($purchaseRequests as $pr)
                                  @php
                                      $requestedParts = app(\App\Services\PartParser::class)->parsePartText($pr->item);
                                      $sourceJo = ($prJobApprovals ?? collect())->get($pr->job_order_no);
                                      $sourceJoApprovalAudit = $sourceJo?->audits?->first();
                                      $sourceJoSignaturePath = data_get($sourceJoApprovalAudit?->metadata, 'handwritten_signature_path');
                                      $sourceJoApproved = $sourceJo?->review_status === 'Approved';
                                      $legacyAuthorized = $pr->status === 'Submitted' && $sourceJoApproved;
                                      $isSubmitted = $pr->status === 'Submitted';
                                      $isRejected = $pr->status === 'Rejected';
                                      $canEdit = $isRejected;
                                      $canApproveOrReject = false;
                                      $canDelete = $isSubmitted;
                                      $sourceParts = collect(app(\App\Services\PartParser::class)->parsePartText((string) ($sourceJo?->part_needed ?? '')));
                                      $prParts = collect($requestedParts);
                                      $shortageByKey = $prParts->groupBy(fn ($p) => mb_strtolower(trim((string) ($p['name'] ?? ''))).'|'.mb_strtolower(trim((string) ($p['unit'] ?? ''))))
                                          ->map(fn ($parts) => $parts->sum(fn ($p) => (int) ($p['quantity'] ?? 0)));
                                      $referenceIrParts = $sourceParts->map(function ($part) use ($shortageByKey) {
                                          $key = mb_strtolower(trim((string) ($part['name'] ?? ''))).'|'.mb_strtolower(trim((string) ($part['unit'] ?? '')));
                                          return array_merge($part, ['quantity' => max(0, (int) ($part['quantity'] ?? 0) - (int) $shortageByKey->get($key, 0))]);
                                      })->filter(fn ($part) => $part['quantity'] > 0)->values();
                                      $releasePartial = $sourceJo?->parts_issue_strategy === 'issue_available_only';
                                      // Legacy auto-approved PRs inherited JO authorization, not a separate PR approval.
                                      $displayPrStatus = $pr->status === 'Approved' && $sourceJoApproved && $pr->source_type === 'Maintenance Request' ? 'Submitted' : $pr->status;
                                  @endphp
                                  <tr>
                                      <td><div class="pr-active-jo-pr"><strong class="pr-ir-jo-badge">{{ $pr->job_order_no ?: '—' }}</strong><small>{{ $pr->pr_no }}</small></div></td>
                                      <td><div class="pr-ir-date"><strong>{{ $pr->created_at?->copy()->timezone(config('app.business_timezone', 'Asia/Manila'))->format('M d, Y') ?? '—' }}</strong><small>{{ $pr->created_at?->copy()->timezone(config('app.business_timezone', 'Asia/Manila'))->format('h:i A') ?? '—' }}</small></div></td>
                                      <td>
                                          <div class="pr-ir-counts">
                                              <span class="pr-ir-count"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> PR: {{ $prParts->count() }} {{ \Illuminate\Support\Str::plural('part', $prParts->count()) }}</span>
                                              @if($sourceJo)<span class="pr-ir-count is-ir"><i class="fa-solid fa-box" aria-hidden="true"></i> IR: {{ $referenceIrParts->count() }} {{ \Illuminate\Support\Str::plural('part', $referenceIrParts->count()) }}</span>@endif
                                          </div>
                                          <details class="pr-pending-parts-details pr-ir-breakdown">
                                              <summary>View breakdown <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                                              <div class="pr-pending-parts-list">
                                                  <strong class="pr-ir-popover-title is-pr"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> Recorded PR ({{ $prParts->count() }})</strong>
                                                  @forelse($prParts as $part)<div><span>{{ $part['name'] ?? '—' }}</span><strong>{{ $part['quantity'] ?? 0 }} {{ $part['unit'] ?? '' }}</strong></div>@empty<p>No recorded PR items</p>@endforelse
                                                  @if($sourceJo)
                                                      <strong class="pr-ir-popover-title is-ir"><i class="fa-solid fa-box" aria-hidden="true"></i> Reference IR ({{ $referenceIrParts->count() }})</strong>
                                                      @forelse($referenceIrParts as $part)<div><span>{{ $part['name'] ?? '—' }}</span><strong>{{ $part['quantity'] ?? 0 }} {{ $part['unit'] ?? '' }}</strong></div>@empty<p>No remaining IR allocation</p>@endforelse
                                                      <p class="pr-ir-allocation-note">IR figures are JO-minus-PR references, not confirmed Warehouse issuance.</p>
                                                  @endif
                                              </div>
                                          </details>
                                      </td>
                                      <td><span class="pr-ir-shortage-status {{ $referenceIrParts->isNotEmpty() ? 'is-partial' : '' }}">{{ $referenceIrParts->isNotEmpty() ? 'Partial Stock' : 'Unavailable Stock' }}</span></td>
                                      <td><span class="pr-pending-badge pr-ir-status-box">@if($displayPrStatus === 'For Purchase')<i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>@elseif($displayPrStatus === 'Submitted')<i class="fa-regular fa-clock" aria-hidden="true"></i>@endif {{ $displayPrStatus }}</span></td>
                                      <td>@if($sourceJo)<span class="pr-ir-release-policy {{ $releasePartial ? 'is-partial' : 'is-waiting' }}"><i class="fa-solid {{ $releasePartial ? 'fa-circle-check' : 'fa-clock' }}" aria-hidden="true"></i> {{ $releasePartial ? 'Issue Available Only' : 'Wait Until Complete' }}</span>@else<span>—</span>@endif</td>
                                      <td><div class="actions pr-review-action-cell">
                                              <button
                                                  type="button"
                                                  class="pr-review-btn open-view-pr-modal"
                                                  title="View Purchase Requisition"
                                                  data-id="{{ $pr->id }}"
                                                  data-pr-no="{{ $pr->pr_no }}"
                                                  data-reference-ir-parts="{{ json_encode($referenceIrParts->values()->all()) }}"
                                                  data-jo-approved="{{ $sourceJo?->review_status === 'Approved' ? '1' : '0' }}"
                                                  data-jo-review-status="{{ $sourceJo?->review_status ?? ($sourceJo ? 'Pending' : 'JO not found') }}"
                                                  data-jo-approver="{{ $sourceJo?->approver?->name ?? '' }}"
                                                  data-jo-approved-at="{{ ($sourceJo?->approved_at ?? $sourceJoApprovalAudit?->created_at)?->format('M d, Y · h:i A') ?? '' }}"
                                                  data-jo-signature-url="{{ $sourceJoApproved && is_string($sourceJoSignaturePath) && $sourceJoSignaturePath !== '' ? route('job-orders.approval-signature', $sourceJo->id, false) : '' }}"
                                                  data-job-order-no="{{ $pr->job_order_no }}"
                                                  data-bus-no="{{ $pr->bus_no }}"
                                                  data-item="{{ $pr->item }}"
                                                  data-quantity="{{ $pr->quantity }}"
                                                  data-status="{{ $displayPrStatus }}"
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
                                                  <span>View PR</span>
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
                                      </div></td>
                                  </tr>
                              @empty
                                  <x-ui.empty-row colspan="7" icon="fa-clipboard-list" message="No Purchase Requests Found" description="No maintenance purchase requisitions match your search or filter criteria." />
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
              <section class="pr-ir-review-summary" aria-label="Approved Job Order summary">
                  <div class="pr-ir-summary-main"><span>JOB ORDER NO.</span><strong id="prReviewLinkedJo">—</strong><small>Approved Maintenance source</small></div>
                  <div><span>JO REVIEW STATUS</span><strong class="pr-ir-jo-approved"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Approved</strong></div>
                  <div><span>BUS ID / PLATE</span><strong id="prReviewBus">—</strong></div>
                  <div><span>ASSIGNED MECHANICS</span><strong id="prReviewMechanics">—</strong></div>
              </section>
              <div class="pr-ir-review-top">
                  <section class="pr-step-card pr-ir-overview" aria-label="Request overview">
                      <div class="pr-ir-panel-heading"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i><h3>Request Overview</h3></div>
                      <div class="pr-ir-panel-body">
                          <label class="pr-document-field pr-document-field-full">
                              <span>Approved Job Order <span class="ui-required">*</span></span>
                              <select name="job_order_no" id="jobOrderSelect" class="pr-review-source-select" required>
                                  <option value="">Select an approved Job Order</option>
                                  @foreach($availablePrJobOrders as $jobOrder)
                                      <option value="{{ $jobOrder->job_order_no }}" data-job-order-url="{{ route('purchase-requests.job-order-data', $jobOrder, false) }}" @selected(old('job_order_no', $selectedJobOrder?->job_order_no) === $jobOrder->job_order_no)>{{ $jobOrder->job_order_no }}</option>
                                  @endforeach
                              </select>
                              <input type="hidden" name="bus_no" id="busNoInput">
                          </label>
                          <div class="pr-ir-overview-facts"><div><span>Request Type</span><strong>Job Order Parts Request</strong></div><div><span>Processing</span><strong>Shortage Purchase Request</strong></div></div>
                          <p>Only missing quantities are requested for procurement. Available parts remain under Warehouse IR processing.</p>
                      </div>
                  </section>
                  <section class="pr-step-card pr-ir-head-approval" aria-label="Head approval">
                      <div class="pr-ir-panel-heading"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><h3>Head Approval</h3></div>
                      <div class="pr-ir-approval-body">
                          <div class="pr-ir-approved-by"><span>Approved By</span><strong id="prApproverName">—</strong><small>Maintenance Head</small></div>
                          <div class="pr-jo-signature-reference pr-ir-signature"><span>Authorized Signature</span>
                              <img id="prApprovalSignature" alt="Saved Job Order authorization signature" hidden>
                              <small id="prSignaturePlaceholder">JO signature unavailable</small>
                          </div>
                      </div>
                      <span id="prJoApprovalBadge" class="pr-jo-approval-status" hidden><i class="fa-solid fa-circle-check" aria-hidden="true"></i> JO Approved</span>
                  </section>
              </div>
              <section class="pr-step-card pr-document-parts" aria-label="Parts requiring purchase">
                  <div class="pr-step-heading"><span class="pr-step-icon">02</span><div><h3>Parts &amp; Warehouse</h3><p>Missing parts — Purchase Request (PR), for procurement only</p></div><span class="pr-step-tag">JO shortage</span></div>
                  <div class="pr-dual-parts-grid">
                      <div class="pr-dual-parts-pane is-pr">
                          <div class="pr-dual-parts-heading"><strong><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> Missing Parts — Purchase Request (PR)</strong><span>For Procurement</span></div>
                          <div class="pr-shortage-table-wrap">
                              <table class="pr-shortage-table pr-review-parts-table">
                                  <thead><tr><th scope="col">PART NAME</th><th scope="col">MISSING QTY</th><th scope="col">STATUS</th></tr></thead>
                                  <tbody id="newPrPartsBody"><tr><td colspan="3" class="pr-table-message">Loading linked Job Order parts…</td></tr></tbody>
                              </table>
                          </div>
                      </div>
                      <div class="pr-dual-parts-pane is-ir">
                          <div class="pr-dual-parts-heading"><strong><i class="fa-solid fa-box" aria-hidden="true"></i> Available Parts — Item Request (IR)</strong><span>For Warehouse</span></div>
                          <div class="pr-shortage-table-wrap">
                              <table class="pr-shortage-table pr-review-parts-table">
                                  <thead><tr><th scope="col">PART NAME</th><th scope="col">REQUESTED QTY</th><th scope="col">AVAILABILITY</th></tr></thead>
                                  <tbody id="prReviewAvailableParts"><tr><td colspan="3" class="pr-table-message">Loading linked Job Order parts…</td></tr></tbody>
                              </table>
                          </div>
                      </div>
                  </div>
                  <p class="pr-stock-note">Only missing quantities are submitted in this PR. IR quantities are reference allocations and are not submitted again.</p>
                  <p id="prPartsError" class="pr-inline-error" role="alert" hidden></p>
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

          <div class="pr-view-document">
              <section class="pr-ir-review-summary pr-view-summary" aria-label="Saved Purchase Request summary">
                  <div class="pr-ir-summary-main"><span>PURCHASE REQUEST NO.</span><strong id="reviewPrNo">—</strong><small>Linked Job Order: <span id="reviewPrSummaryJobOrder">—</span></small></div>
                  <div><span>PR STATUS</span><strong><span id="reviewPrStatus" class="pr-review-status-pill">—</span></strong></div>
                  <div><span>BUS ID / PLATE</span><strong id="reviewPrBusNo">—</strong></div>
                  <div><span>SUBMITTED AT</span><strong id="reviewPrCreated">—</strong></div>
              </section>
              <div class="pr-ir-review-top">
                  <section class="pr-step-card pr-ir-overview" id="prReviewInformation" aria-label="Request overview">
                      <div class="pr-ir-panel-heading"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i><h3>Request Overview</h3></div>
                      <div class="pr-ir-panel-body">
                          <div class="pr-ir-overview-facts">
                              <div><span>Request Type</span><strong id="reviewPrSource">Maintenance Request</strong></div>
                              <div><span>Processing</span><strong>Shortage Purchase Request</strong></div>
                          </div>
                          <p>Saved PR for unavailable parts from the approved Job Order. Available IR quantities are shown for reference only.</p>
                          <div class="pr-view-secondary-data">
                              <div><span>Job Order</span><strong id="reviewPrJobOrderNo">—</strong></div>
                              <div><span>Requested By</span><strong id="reviewPrRequester">—</strong></div>
                              <div><span>Request Date</span><strong id="reviewPrRequestDate">—</strong></div>
                              <div><span>Department</span><strong>Maintenance</strong></div>
                          </div>
                      </div>
                  </section>
                  <section class="pr-step-card pr-ir-head-approval" id="prAuthorizationSection" aria-label="Maintenance Head approval">
                      <div class="pr-ir-panel-heading"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><h3>Head Approval</h3></div>
                      <div class="pr-ir-approval-body">
                          <div class="pr-ir-approved-by"><span>Approved By</span><strong id="reviewPrApprover">—</strong><small>Maintenance Head</small></div>
                          <div class="pr-jo-signature-reference pr-ir-signature"><span>Authorized Signature</span><img id="reviewPrSignature" alt="Saved Job Order approval signature" hidden><small id="reviewPrSignatureFallback">Not available</small></div>
                      </div>
                      <div class="pr-view-approval-meta"><span id="reviewPrJoAuthorization">—</span><span id="reviewPrApprovedAt">—</span><span id="reviewPrAuthorizationJobOrder">—</span></div>
                  </section>
              </div>
              <section class="pr-step-card pr-document-parts pr-view-parts" id="prReviewItemsSection" aria-label="Parts and Warehouse">
                  <div class="pr-step-heading">
                      <span class="pr-step-icon">02</span>
                      <div><h3>Parts &amp; Warehouse</h3><p>Missing parts — Purchase Request (PR), for procurement only</p></div>
                      <span id="reviewPrItemCount" class="pr-step-tag">0 items</span>
                  </div>
                  <div class="pr-dual-parts-grid">
                      <div class="pr-dual-parts-pane is-pr">
                          <div class="pr-dual-parts-heading"><strong><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> Missing Parts — Purchase Request (PR)</strong><span>For Procurement</span></div>
                          <div class="pr-shortage-table-wrap"><table class="pr-shortage-table pr-review-parts-table">
                              <thead><tr><th>PART NAME</th><th>MISSING QTY</th><th>STATUS</th></tr></thead>
                              <tbody id="reviewPrItemsBody"><tr><td colspan="3" class="pr-review-empty">No requested items recorded.</td></tr></tbody>
                          </table></div>
                      </div>
                      <div class="pr-dual-parts-pane is-ir">
                          <div class="pr-dual-parts-heading"><strong><i class="fa-solid fa-box" aria-hidden="true"></i> Available Parts — Item Request (IR)</strong><span>For Warehouse</span></div>
                          <div class="pr-shortage-table-wrap"><table class="pr-shortage-table pr-review-parts-table">
                              <thead><tr><th>PART NAME</th><th>REQUESTED QTY</th><th>AVAILABILITY</th></tr></thead>
                              <tbody id="reviewPrIrPartsBody"><tr><td colspan="3" class="pr-review-empty">No linked allocation.</td></tr></tbody>
                          </table></div>
                      </div>
                  </div>
                  <div class="pr-view-parts-note-row">IR quantities are JO-minus-PR reference allocations and do not confirm Warehouse issuance.</div>
                  <div class="pr-view-hidden-totals" hidden><span id="reviewPrTotalQuantity">—</span></div>
              </section>
              <section class="pr-step-card pr-view-remarks" id="prRequestDetailsSection" aria-label="Request details">
                  <div class="pr-ir-panel-heading"><i class="fa-regular fa-message" aria-hidden="true"></i><h3>Request Details</h3><span class="pr-view-parts-note">Read only</span></div>
                  <div class="pr-ir-panel-body">
                      <div class="pr-view-secondary-data"><div><span>Work / Repair to Perform</span><strong id="reviewPrWorkToPerform">—</strong></div><div><span>Purpose</span><strong id="reviewPrPurpose">—</strong></div><div><span>Target Date</span><strong id="reviewPrTargetDate">—</strong></div></div>
                      <div class="pr-view-remarks-field"><span>Remarks</span><strong id="reviewPrRemarks">No remarks provided.</strong></div>
                  </div>
              </section>
          </div>

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
