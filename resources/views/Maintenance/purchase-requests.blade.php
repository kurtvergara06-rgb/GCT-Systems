  <x-layout.app
      title="FROMS - Purchase Requests"

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

          $availablePrJobOrders = $jobOrders->filter(function ($jobOrder) {
              if (
                  empty($jobOrder->assigned_mechanic) ||
                  empty($jobOrder->part_needed) ||
                  $jobOrder->status === 'Completed'
              ) {
                  return false;
              }

              return !\App\Models\Maintenance\PurchaseRequest::query()
                  ->where('job_order_no', $jobOrder->job_order_no)
                  ->where('pr_no', 'not like', '%-P')
                  ->where(function ($query) {
                      $query
                          ->whereNull('source_type')
                          ->orWhere('source_type', 'Maintenance Request');
                  })
                  ->exists();
          })->values();
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
                  title="Purchase Requests"
                  subtitle="Manage requested parts and maintenance purchasing records"
                  notification-count="6"
              />

              <section data-ajax-region="summary" class="stats-grid purchase-stats-grid">
                  <x-ui.summary-card label="Submitted" value="{{ $submitted }}" small="Waiting approval" icon="fa-paper-plane" color="blue" />
                  <x-ui.summary-card label="Rejected" value="{{ $rejected }}" small="Needs revision" icon="fa-xmark" color="red" />
                  <x-ui.summary-card label="Approved" value="{{ $approved }}" small="Approved requests" icon="fa-check" color="purple" />
                  <x-ui.summary-card label="For Purchase" value="{{ $forPurchase }}" small="Ready to purchase" icon="fa-cart-shopping" color="blue" />
              </section>

              <section data-ajax-region="records" class="table-card purchase-request-card">
                  <div class="section-header maintenance-record-header">
                      <div>
                          <h2>Purchase Request Records</h2>
                      </div>

                      <div class="maintenance-record-tabs" data-maintenance-record-tabs="true" role="tablist" aria-label="Purchase request record view">
                          <a
                              href="{{ route('purchase-requests', array_filter(['search' => request('search'), 'status' => 'All Statuses'])) }}"
                              class="maintenance-record-tab {{ $recordView === 'active' ? 'is-active' : '' }}"
                              data-allow-partial-navigation="true"
                              data-maintenance-record-tab-link="active"
                              role="tab"
                              aria-selected="{{ $recordView === 'active' ? 'true' : 'false' }}"
                          >
                              <i class="fa-solid fa-list-check"></i>
                              <span>Active</span>
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
                  </div>

                  @if($recordView === 'history')
                      <p class="maintenance-history-note">Issued Purchase Requests are kept here for reference and audit history.</p>
                  @endif

                  <form
                      action="{{ route('purchase-requests') }}"
                      method="GET"
                      class="toolbar purchase-toolbar"
                      data-server-filter="{{ $recordView === 'history' ? 'true' : 'false' }}"
                  >
                      @if($recordView === 'history')
                          <input type="hidden" name="record_view" value="history">
                      @endif

                      <div class="search-box">
                          <i class="fa-solid fa-magnifying-glass"></i>
                          <input
                              type="text"
                              name="search"
                              value="{{ request('search') }}"
                              placeholder="Search PR no., JO no., bus no., item..."
                          >
                      </div>

                      @if($recordView !== 'history')
                          <div class="filter-group">
                              <label for="prStatusFilter" class="sr-only"></label>
                              <select
                                  name="status"
                                  id="prStatusFilter"
                                  class="pr-status-select"
                                  onchange="this.form.requestSubmit()"
                              >
                                  <option
                                      value="All Statuses"
                                      @selected(request('status', 'All Statuses') === 'All Statuses')
                                  >
                                      All Statuses
                                  </option>
                                  @foreach($statuses as $status)
                                      <option value="{{ $status }}" @selected(request('status') === $status)>
                                          {{ $status }}
                                      </option>
                                  @endforeach
                              </select>
                          </div>
                      @endif

                      @if($recordView !== 'history')
                          <button type="button" id="openPrModal" class="primary-btn compact-new-pr-btn">
                              <i class="fa-solid fa-plus"></i>
                              New PR
                          </button>
                      @endif
                  </form>

                  <div class="table-wrap purchase-table-wrap">
                      <table class="purchase-request-table">
                          <thead>
                              <tr>
                                  <th>PR #</th>
                                  <th>Bus #</th>
                                  <th>Requested Item / Part</th>
                                  <th>Qty</th>
                                  <th>Status</th>
                                  <th>Created</th>
                                  <th>Actions</th>
                              </tr>
                          </thead>

                          <tbody>
                              @forelse($purchaseRequests as $pr)
                                  @php
                                      $firstRequestedItem = trim(explode(',', $pr->item ?? '')[0] ?? '');

                                      if (str_contains($firstRequestedItem, ' - Qty:')) {
                                          $firstRequestedItem = trim(
                                              explode(' - Qty:', $firstRequestedItem)[0] ?? $firstRequestedItem
                                          );
                                      }

                                      $isSubmitted = $pr->status === 'Submitted';
                                      $isRejected = $pr->status === 'Rejected';
                                      $canEdit = $isSubmitted || $isRejected;
                                      $canApproveOrReject = $isMaintenanceAdmin && $isSubmitted;
                                      $canDelete = $isSubmitted;
                                  @endphp

                                  <tr>
                                      <td>
                                          <span class="pr-no-pill">{{ $pr->pr_no }}</span>
                                      </td>
                                      <td>
                                          <span class="bus-badge-pill"><i class="fa-solid fa-bus"></i> {{ $pr->bus_no }}</span>
                                      </td>
                                      <td class="requested-part-cell">{{ $firstRequestedItem ?: '—' }}</td>
                                      <td>{{ $pr->quantity }}</td>
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
                                                  title="Review Purchase Request"
                                                  data-id="{{ $pr->id }}"
                                                  data-pr-no="{{ $pr->pr_no }}"
                                                  data-job-order-no="{{ $pr->job_order_no }}"
                                                  data-bus-no="{{ $pr->bus_no }}"
                                                  data-item="{{ $pr->item }}"
                                                  data-quantity="{{ $pr->quantity }}"
                                                  data-status="{{ $pr->status }}"
                                                  data-remarks="{{ $pr->remarks }}"
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
                                                  <span>Review</span>
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
                                  <x-ui.empty-row colspan="7" message="No purchase requests found." />
                              @endforelse
                          </tbody>
                      </table>
                  </div>

                  <x-ui.table-footer
                      :items="$purchaseRequests"
                      data-lazy-pagination="{{ $recordView === 'history' ? 'true' : 'false' }}"
                  />
              </section>
          </main>
      </div>

      <x-ui.form-modal
          id="prModal"
          title="New Purchase Request"
          description="Create a purchase request from an inspected Job Order."
          icon="fa-file-circle-plus"
          size="large"
          form-id="newPrForm"
          :action="route('purchase-requests.store')"
          method="POST"
          submit-text="Create PR"
          submit-id="createPrBtn"
          submit-icon="fa-file-circle-plus"
          close-id="closePrModal"
          cancel-id="cancelPrModal"
          :confirm="true"
          confirm-title="Create Purchase Request?"
          confirm-message="Are you sure you want to create this Purchase Request?"
          confirm-button="Yes, Create PR"
          confirm-type="create"
          class="{{ isset($selectedJobOrder) && $selectedJobOrder ? 'show active' : '' }}"
      >
          <x-ui.form-section
              title="Purchase Request Information"
              subtitle="Only Job Orders with an assigned mechanic and requested parts can create a PR."
              icon="fa-file-invoice"
          >
              <div class="ui-form-grid">
                  <x-ui.form-field
                      label="PR No."
                      name="display_pr_no"
                      id="newPrNo"
                      :value="$nextPrNo"
                      icon="fa-hashtag"
                      readonly
                  />

                  <div class="ui-form-group">
                      <label for="jobOrderSelect">
                          Job Order <span class="ui-required">*</span>
                      </label>
                      <div class="pr-select-control">
                          <i class="fa-solid fa-clipboard-list"></i>
                          <select name="job_order_no" id="jobOrderSelect" required>
                              <option value="">Select Job Order</option>
                              @foreach($availablePrJobOrders as $jobOrder)
                                  <option
                                      value="{{ $jobOrder->job_order_no }}"
                                      data-bus="{{ $jobOrder->bus_no }}"
                                      data-parts="{{ $jobOrder->part_needed }}"
                                      @selected(old('job_order_no', $selectedJobOrder?->job_order_no) === $jobOrder->job_order_no)
                                  >
                                      {{ $jobOrder->job_order_no }} - {{ $jobOrder->bus_no }}
                                  </option>
                              @endforeach
                          </select>
                      </div>
                  </div>

                  <x-ui.form-field
                      label="Bus #"
                      name="bus_no"
                      id="busNoInput"
                      value="{{ old('bus_no', $selectedJobOrder?->bus_no) }}"
                      icon="fa-bus"
                      readonly
                      required
                  />
              </div>
          </x-ui.form-section>

          <x-ui.form-section
              title="Requested Parts"
              subtitle="Review the parts identified during the mechanic inspection."
              icon="fa-gears"
          >
              <x-slot:action>
                  <button type="button" id="addNewPrPartBtn" class="ui-btn-small">
                      <i class="fa-solid fa-plus"></i>
                      Add Part
                  </button>
              </x-slot:action>

              <div
                  id="newPrPartsContainer"
                  class="pr-parts-container"
                  data-initial-parts="{{
                      old('job_order_no')
                          ? (optional($jobOrders->firstWhere('job_order_no', old('job_order_no')))->part_needed ?? '')
                          : ($selectedJobOrder?->part_needed ?? '')
                  }}"
              ></div>
          </x-ui.form-section>

          <div class="ui-form-group ui-form-full">
              <label for="newPrRemarks">Remarks</label>
              <textarea
                  name="remarks"
                  id="newPrRemarks"
                  placeholder="Optional remarks..."
              >{{ old('remarks') }}</textarea>
          </div>
      </x-ui.form-modal>

      <x-ui.form-modal
          id="editPrModal"
          title="Purchase Request Details"
          title-id="editPrModalTitle"
          description="Review the purchase request information and take the appropriate action."
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
              <div class="pr-review-summary-main">
                  <div class="pr-review-doc-icon">
                      <i class="fa-solid fa-clipboard-list"></i>
                  </div>

                  <div>
                      <strong id="reviewPrNo">—</strong>
                      <span id="reviewPrStatus" class="pr-review-status-pill">—</span>
                  </div>
              </div>

              <div class="pr-review-created">
                  <span>
                      <i class="fa-regular fa-calendar"></i>
                      Created
                  </span>
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
                          <h3>Request Information</h3>
                          <p>Review the source Job Order and request details.</p>
                      </div>
                  </div>
              </div>

              <div class="pr-review-information">
              <div class="pr-review-information-column">
                  <div class="pr-review-detail-row">
                      <span>Job Order No.</span>
                      <strong id="reviewPrJobOrderNo">—</strong>
                  </div>

                  <div class="pr-review-detail-row">
                      <span>Bus No.</span>
                      <strong id="reviewPrBusNo">—</strong>
                  </div>

                  <div class="pr-review-detail-row">
                      <span>Department</span>
                      <strong>Maintenance</strong>
                  </div>
              </div>

              <div class="pr-review-information-column">
                  <div class="pr-review-detail-row">
                      <span>Request Date</span>
                      <strong id="reviewPrRequestDate">—</strong>
                  </div>

                  <div class="pr-review-detail-row">
                      <span>Status</span>
                      <strong id="reviewPrStatusText">—</strong>
                  </div>

                  <div class="pr-review-detail-row">
                      <span>Total Quantity</span>
                      <strong id="reviewPrTotalQuantity">—</strong>
                  </div>
              </div>

              <div class="pr-review-detail-row pr-review-detail-full">
                  <span>Source</span>
                  <strong id="reviewPrSource">Maintenance Request</strong>
              </div>

              <div class="pr-review-detail-row pr-review-detail-full">
                  <span>Work / Repair to Perform</span>
                  <strong id="reviewPrWorkToPerform">No work / repair details recorded.</strong>
              </div>

              <div class="pr-review-detail-row pr-review-detail-full">
                  <span>Remarks</span>
                  <strong id="reviewPrRemarks">No remarks provided.</strong>
              </div>
              </div>
          </section>

          <section id="prReviewItemsSection" class="pr-review-section">
              <div class="pr-review-section-heading">
                  <div class="pr-review-section-title">
                      <span class="pr-review-section-icon">
                          <i class="fa-solid fa-clipboard-list"></i>
                      </span>
                      <div>
                          <h3>Requested Items</h3>
                          <p>Parts included in this purchase request.</p>
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
                              <th>Unit</th>
                          </tr>
                      </thead>
                      <tbody id="reviewPrItemsBody">
                          <tr>
                              <td colspan="4" class="pr-review-empty">No requested items recorded.</td>
                          </tr>
                      </tbody>
                  </table>
              </div>
          </section>

          <section id="reviewDecisionBlock" class="pr-review-decision" hidden>
              <div class="pr-review-section-heading">
                  <div class="pr-review-section-title">
                      <span class="pr-review-section-icon">
                          <i class="fa-solid fa-user-check"></i>
                      </span>
                      <div>
                          <h3>Approval / Rejection</h3>
                          <p>Optional remarks. Rejection remarks will be saved with the request.</p>
                      </div>
                  </div>
              </div>

              <label for="reviewDecisionRemarks">Remarks / Reason (Optional)</label>
              <textarea
                  id="reviewDecisionRemarks"
                  placeholder="Enter remarks or reason here..."
              ></textarea>
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

              <div class="pr-review-footer-actions">
                  <button
                      type="button"
                      id="reviewEditPrBtn"
                      class="ui-form-btn pr-review-btn-edit"
                      hidden
                  >
                      <i class="fa-solid fa-pen-to-square"></i>
                      <span>Edit</span>
                  </button>

                  <button
                      type="button"
                      id="reviewRejectPrBtn"
                      class="ui-form-btn pr-review-btn-reject open-pr-confirmation"
                      data-action="reject"
                      hidden
                  >
                      <i class="fa-solid fa-xmark"></i>
                      Reject
                  </button>

                  <button
                      type="button"
                      id="reviewApprovePrBtn"
                      class="ui-form-btn pr-review-btn-approve open-pr-confirmation"
                      data-action="approve"
                      hidden
                  >
                      <i class="fa-solid fa-check"></i>
                      Approve
                  </button>
              </div>
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
