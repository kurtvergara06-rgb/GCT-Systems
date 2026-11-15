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

          /*
          |--------------------------------------------------------------------------
          | STATUS ORDER
          |--------------------------------------------------------------------------
          |
          | Rejected is a branch:
          |
          | Submitted → Rejected → Revise → Submitted
          | Submitted → Approved → For Purchase → Ordered
          | → Pick-up / Delivery → Issued
          |
          */

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


          $submitted =
              $submitted ?? 0;

          $rejected =
              $rejected ?? 0;

          $approved =
              $approved ?? 0;

          $forPurchase =
              $forPurchase ?? 0;

          $isMaintenanceAdmin =
              $isMaintenanceAdmin ?? false;


          /*
          |--------------------------------------------------------------------------
          | AVAILABLE JOB ORDERS FOR NEW PR
          |--------------------------------------------------------------------------
          |
          | Controller still protects this on backend.
          | This also keeps existing PR JOs out of the dropdown.
          |
          */

          $availablePrJobOrders =
              $jobOrders->filter(function ($jobOrder) {

                  if (
                      empty($jobOrder->assigned_mechanic) ||
                      empty($jobOrder->part_needed) ||
                      $jobOrder->status === 'Completed'
                  ) {
                      return false;
                  }


                  return !\App\Models\Maintenance\PurchaseRequest::query()
                      ->where(
                          'job_order_no',
                          $jobOrder->job_order_no
                      )
                      ->where(
                          'pr_no',
                          'not like',
                          '%-P'
                      )
                      ->where(function ($query) {

                          $query
                              ->whereNull(
                                  'source_type'
                              )
                              ->orWhere(
                                  'source_type',
                                  'Maintenance Request'
                              );

                      })
                      ->exists();

              })
              ->values();

      @endphp


      {{-- =========================================================
          VALIDATION ERROR
      ========================================================== --}}
      @if($errors->any())

          <div
              id="validationErrorModal"
              class="modal-overlay show active"
          >

              <div class="modal-card delete-modal-box">

                  <div class="delete-icon">

                      <i class="fa-solid fa-triangle-exclamation"></i>

                  </div>


                  <h2>
                      Form Error
                  </h2>


                  <p>
                      Please check the form. Some required information is missing.
                  </p>


                  <ul class="form-error-list">

                      @foreach($errors->all() as $error)

                          <li>
                              {{ $error }}
                          </li>

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


          <main class="main purchase-page">

              {{-- =====================================================
                  TOPBAR
              ====================================================== --}}
              <x-layout.topbar
                  title="Purchase Requests"

                  subtitle="Manage requested parts and maintenance purchasing records"

                  notification-count="6"
              />


              {{-- =====================================================
                  SUMMARY
              ====================================================== --}}
              <section class="stats-grid purchase-stats-grid">

                  <x-ui.summary-card
                      label="Submitted"

                      value="{{ $submitted }}"

                      small="Waiting approval"

                      icon="fa-paper-plane"

                      color="blue"
                  />


                  <x-ui.summary-card
                      label="Rejected"

                      value="{{ $rejected }}"

                      small="Needs revision"

                      icon="fa-xmark"

                      color="red"
                  />


                  <x-ui.summary-card
                      label="Approved"

                      value="{{ $approved }}"

                      small="Approved requests"

                      icon="fa-check"

                      color="purple"
                  />


                  <x-ui.summary-card
                      label="For Purchase"

                      value="{{ $forPurchase }}"

                      small="Ready to purchase"

                      icon="fa-cart-shopping"

                      color="blue"
                  />

              </section>


              {{-- =====================================================
                  TABLE CARD
              ====================================================== --}}
              <section class="table-card purchase-request-card">

                  <div class="section-header">

                      <div>

                          <h2>
                              Purchase Request Records
                          </h2>


                          <p>
                              Track requested parts, approval status,
                              warehouse issuance, and purchasing progress
                          </p>

                      </div>

                  </div>


                  {{-- =================================================
                      TOOLBAR
                  ================================================== --}}
                  <form
                      action="{{ route('purchase-requests') }}"

                      method="GET"

                      class="toolbar purchase-toolbar"
                  >

                      {{-- SEARCH --}}
                      <div class="search-box">

                          <i class="fa-solid fa-magnifying-glass"></i>


                          <input
                              type="text"

                              name="search"

                              value="{{ request('search') }}"

                              placeholder="Search PR no., JO no., bus no., item..."
                          >

                      </div>


                      {{-- STATUS --}}
                      <div class="filter-group">

                          <label for="prStatusFilter">
                              Status
                          </label>


                          <select
                              name="status"

                              id="prStatusFilter"

                              class="pr-status-select"

                              onchange="this.form.requestSubmit()"
                          >

                              <option
                                  value="All Statuses"

                                  @selected(
                                      request(
                                          'status',
                                          'All Statuses'
                                      ) === 'All Statuses'
                                  )
                              >
                                  All Statuses
                              </option>


                              @foreach($statuses as $status)

                                  <option
                                      value="{{ $status }}"

                                      @selected(
                                          request('status')
                                          === $status
                                      )
                                  >
                                      {{ $status }}
                                  </option>

                              @endforeach

                          </select>

                      </div>


                      {{-- NEW PR --}}
                      <button
                          type="button"

                          id="openPrModal"

                          class="primary-btn compact-new-pr-btn"
                      >

                          <i class="fa-solid fa-plus"></i>

                          New PR

                      </button>

                  </form>


                  {{-- =================================================
                      TABLE
                  ================================================== --}}
                  <div class="table-wrap purchase-table-wrap">

                      <table class="purchase-request-table">

                          <thead>

                              <tr>

                                  <th>
                                      PR #
                                  </th>

                                  <th>
                                      Bus #
                                  </th>

                                  <th>
                                      Requested Item / Part
                                  </th>

                                  <th>
                                      Qty
                                  </th>

                                  <th>
                                      Status
                                  </th>

                                  <th>
                                      Created
                                  </th>

                                  <th>
                                      Actions
                                  </th>

                              </tr>

                          </thead>


                          <tbody>

                              @forelse($purchaseRequests as $pr)

                                  @php

                                      /*
                                      |--------------------------------------------------------------------------
                                      | FIRST ITEM FOR TABLE
                                      |--------------------------------------------------------------------------
                                      */

                                      $firstRequestedItem =
                                          trim(
                                              explode(
                                                  ',',
                                                  $pr->item ?? ''
                                              )[0] ?? ''
                                          );


                                      if (
                                          str_contains(
                                              $firstRequestedItem,
                                              ' - Qty:'
                                          )
                                      ) {

                                          $firstRequestedItem =
                                              trim(
                                                  explode(
                                                      ' - Qty:',
                                                      $firstRequestedItem
                                                  )[0]
                                                  ?? $firstRequestedItem
                                              );

                                      }


                                      /*
                                      |--------------------------------------------------------------------------
                                      | PR STATE
                                      |--------------------------------------------------------------------------
                                      */

                                      $isSubmitted =
                                          $pr->status === 'Submitted';


                                      $isRejected =
                                          $pr->status === 'Rejected';


                                      $isProcessed =
                                          in_array(
                                              $pr->status,
                                              [
                                                  'Approved',
                                                  'For Purchase',
                                                  'Ordered',
                                                  'For Pick-up',
                                                  'Picked Up',
                                                  'For Delivery',
                                                  'Delivered',
                                                  'Issued',
                                              ],
                                              true
                                          );


                                      /*
                                      |--------------------------------------------------------------------------
                                      | EDIT
                                      |--------------------------------------------------------------------------
                                      |
                                      | Submitted = normal Edit
                                      | Rejected  = Revise & Resubmit
                                      | Processed = locked
                                      |
                                      */

                                      $canEdit =
                                          $isSubmitted ||
                                          $isRejected;


                                      /*
                                      |--------------------------------------------------------------------------
                                      | APPROVE / REJECT
                                      |--------------------------------------------------------------------------
                                      */

                                      $canApproveOrReject =
                                          $isMaintenanceAdmin &&
                                          $isSubmitted;


                                      /*
                                      |--------------------------------------------------------------------------
                                      | DELETE
                                      |--------------------------------------------------------------------------
                                      |
                                      | Only Submitted may be deleted.
                                      |
                                      | Rejected must remain for history
                                      | and be revised/resubmitted.
                                      |
                                      */

                                      $canDelete =
                                          $isSubmitted;

                                  @endphp


                                  <tr>

                                      {{-- PR --}}
                                      <td>
                                          {{ $pr->pr_no }}
                                      </td>


                                      {{-- BUS --}}
                                      <td>
                                          {{ $pr->bus_no }}
                                      </td>


                                      {{-- ITEM --}}
                                      <td class="requested-part-cell">

                                          {{
                                              $firstRequestedItem
                                                  ?: '—'
                                          }}

                                      </td>


                                      {{-- QTY --}}
                                      <td>
                                          {{ $pr->quantity }}
                                      </td>


                                      {{-- STATUS --}}
                                      <td class="status-col">

                                          <x-ui.status-badge
                                              :status="$pr->status"

                                              type="purchase"
                                          />

                                      </td>


                                      {{-- CREATED --}}
                                      <td class="created-cell">

                                          {{
                                              $pr->created_at
                                                  ? $pr->created_at
                                                      ->format(
                                                          'm/d/y | h:i A'
                                                      )
                                                  : '—'
                                          }}

                                      </td>


                                      {{-- ACTIONS --}}
                                      <td>

                                          <div class="actions">

                                              {{-- =================================
                                                  VIEW
                                              ================================== --}}
                                              <x-ui.action-button
                                                  type="view"

                                                  title="View Purchase Request"

                                                  class="open-view-pr-modal"

                                                  data-id="{{
                                                      $pr->id
                                                  }}"

                                                  data-pr-no="{{
                                                      $pr->pr_no
                                                  }}"

                                                  data-job-order-no="{{
                                                      $pr->job_order_no
                                                  }}"

                                                  data-bus-no="{{
                                                      $pr->bus_no
                                                  }}"

                                                  data-item="{{
                                                      $pr->item
                                                  }}"

                                                  data-quantity="{{
                                                      $pr->quantity
                                                  }}"

                                                  data-status="{{
                                                      $pr->status
                                                  }}"

                                                  data-remarks="{{
                                                      $pr->remarks
                                                  }}"

                                                  data-update-url="{{
                                                      route(
                                                          'purchase-requests.update',
                                                          $pr->id
                                                      )
                                                  }}"

                                                  data-resubmit-url="{{
                                                      route(
                                                          'purchase-requests.resubmit',
                                                          $pr->id
                                                      )
                                                  }}"
                                              />


                                              {{-- =================================
                                                  EDIT / REVISE
                                              ================================== --}}
                                              @if($canEdit)

                                                  <x-ui.action-button
                                                      type="edit"

                                                      title="{{
                                                          $isRejected
                                                              ? 'Revise and Resubmit Purchase Request'
                                                              : 'Edit Purchase Request'
                                                      }}"

                                                      class="
                                                          open-edit-pr-modal
                                                          {{
                                                              $isRejected
                                                                  ? 'revise-pr-action'
                                                                  : ''
                                                          }}
                                                      "

                                                      data-id="{{
                                                          $pr->id
                                                      }}"

                                                      data-pr-no="{{
                                                          $pr->pr_no
                                                      }}"

                                                      data-job-order-no="{{
                                                          $pr->job_order_no
                                                      }}"

                                                      data-bus-no="{{
                                                          $pr->bus_no
                                                      }}"

                                                      data-item="{{
                                                          $pr->item
                                                      }}"

                                                      data-quantity="{{
                                                          $pr->quantity
                                                      }}"

                                                      data-status="{{
                                                          $pr->status
                                                      }}"

                                                      data-remarks="{{
                                                          $pr->remarks
                                                      }}"

                                                      data-update-url="{{
                                                          route(
                                                              'purchase-requests.update',
                                                              $pr->id
                                                          )
                                                      }}"

                                                      data-resubmit-url="{{
                                                          route(
                                                              'purchase-requests.resubmit',
                                                              $pr->id
                                                          )
                                                      }}"
                                                  />

                                              @else

                                                  <x-ui.action-button
                                                      type="edit"

                                                      title="This Purchase Request can no longer be edited."

                                                      class="disabled-pr-edit-btn"

                                                      :disabled="true"
                                                  />

                                              @endif


                                              {{-- =================================
                                                  APPROVE / REJECT
                                              ================================== --}}
                                              @if($canApproveOrReject)

                                                  <x-ui.action-button
                                                      type="approve"

                                                      title="Approve Purchase Request"

                                                      class="open-pr-confirmation"

                                                      data-action="approve"

                                                      data-pr-no="{{
                                                          $pr->pr_no
                                                      }}"

                                                      data-action-url="{{
                                                          route(
                                                              'purchase-requests.approve',
                                                              $pr->id
                                                          )
                                                      }}"
                                                  />


                                                  <x-ui.action-button
                                                      type="reject"

                                                      title="Reject Purchase Request"

                                                      class="open-pr-confirmation"

                                                      data-action="reject"

                                                      data-pr-no="{{
                                                          $pr->pr_no
                                                      }}"

                                                      data-action-url="{{
                                                          route(
                                                              'purchase-requests.reject',
                                                              $pr->id
                                                          )
                                                      }}"
                                                  />

                                              @endif


                                              {{-- =================================
                                                  DELETE
                                              ================================== --}}
                                              <form
                                                  id="deletePrForm-{{ $pr->id }}"

                                                  action="{{
                                                      route(
                                                          'purchase-requests.destroy',
                                                          $pr->id
                                                      )
                                                  }}"

                                                  method="POST"
                                              >

                                                  @csrf
                                                  @method('DELETE')


                                                  @if($canDelete)

                                                      <x-ui.action-button
                                                          type="delete"

                                                          title="Delete Purchase Request"

                                                          class="open-delete-pr-modal"

                                                          data-id="{{
                                                              $pr->id
                                                          }}"

                                                          data-pr-no="{{
                                                              $pr->pr_no
                                                          }}"
                                                      />

                                                  @else

                                                      <x-ui.action-button
                                                          type="delete"

                                                          title="{{
                                                              $isRejected
                                                                  ? 'Rejected PR cannot be deleted. Revise and resubmit it.'
                                                                  : 'This PR can no longer be deleted because it is already being processed.'
                                                          }}"

                                                          :disabled="true"
                                                      />

                                                  @endif

                                              </form>

                                          </div>

                                      </td>

                                  </tr>


                              @empty

                                  <x-ui.empty-row
                                      colspan="7"

                                      message="No purchase requests found."
                                  />

                              @endforelse

                          </tbody>

                      </table>

                  </div>


                  <x-ui.table-footer
                      :items="$purchaseRequests"
                  />

              </section>

          </main>

      </div>


      {{-- =============================================================
          NEW PURCHASE REQUEST
      ============================================================== --}}
      <x-ui.form-modal
          id="prModal"

          title="New Purchase Request"

          description="Create a purchase request from an inspected Job Order."

          icon="fa-file-circle-plus"

          size="large"

          form-id="newPrForm"

          :action="route(
              'purchase-requests.store'
          )"

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

          class="{{
              isset($selectedJobOrder) &&
              $selectedJobOrder
                  ? 'show active'
                  : ''
          }}"
      >

          {{-- =====================================================
              PR INFORMATION
          ====================================================== --}}
          <x-ui.form-section
              title="Purchase Request Information"

              subtitle="Only Job Orders with an assigned mechanic and requested parts can create a PR."

              icon="fa-file-invoice"
          >

              <div class="ui-form-grid">

                  {{-- PR NO --}}
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
          Job Order

          <span class="ui-required">
              *
          </span>
      </label>

      <div class="pr-select-control">

          <i class="fa-solid fa-clipboard-list"></i>

          <select
              name="job_order_no"
              id="jobOrderSelect"
              required
          >
              <option value="">
                  Select Job Order
              </option>

              @foreach($availablePrJobOrders as $jobOrder)

                  <option
                      value="{{ $jobOrder->job_order_no }}"

                      data-bus="{{ $jobOrder->bus_no }}"

                      data-parts="{{ $jobOrder->part_needed }}"

                      @selected(
                          old(
                              'job_order_no',
                              $selectedJobOrder?->job_order_no
                          ) === $jobOrder->job_order_no
                      )
                  >
                      {{ $jobOrder->job_order_no }}
                      - {{ $jobOrder->bus_no }}
                  </option>

              @endforeach

          </select>

      </div>

  </div>


                  {{-- BUS --}}
                  <x-ui.form-field
                      label="Bus #"

                      name="bus_no"

                      id="busNoInput"

                      value="{{
                          old(
                              'bus_no',
                              $selectedJobOrder
                                  ?->bus_no
                          )
                      }}"

                      icon="fa-bus"

                      readonly

                      required
                  />

              </div>

          </x-ui.form-section>


          {{-- =====================================================
              REQUESTED PARTS
          ====================================================== --}}
          <x-ui.form-section
              title="Requested Parts"

              subtitle="Review the parts identified during the mechanic inspection."

              icon="fa-gears"
          >

              <x-slot:action>

                  <button
                      type="button"

                      id="addNewPrPartBtn"

                      class="ui-btn-small"
                  >

                      <i class="fa-solid fa-plus"></i>

                      Add Part

                  </button>

              </x-slot:action>


              <div
                  id="newPrPartsContainer"

                  class="pr-parts-container"

                  data-initial-parts="{{
                      old(
                          'job_order_no'
                      )
                          ? (
                              optional(
                                  $jobOrders->firstWhere(
                                      'job_order_no',
                                      old('job_order_no')
                                  )
                              )->part_needed
                              ?? ''
                          )
                          : (
                              $selectedJobOrder
                                  ?->part_needed
                              ?? ''
                          )
                  }}"
              >
              </div>

          </x-ui.form-section>


          {{-- =====================================================
              REMARKS
          ====================================================== --}}
          <div class="ui-form-group ui-form-full">

              <label for="newPrRemarks">
                  Remarks
              </label>


              <textarea
                  name="remarks"

                  id="newPrRemarks"

                  placeholder="Optional remarks..."
              >{{ old('remarks') }}</textarea>

          </div>

      </x-ui.form-modal>


      {{-- =============================================================
          EDIT / VIEW / REVISE PR
      ============================================================== --}}
      <x-ui.form-modal
          id="editPrModal"

          title="Purchase Request Details"

          title-id="editPrModalTitle"

          description="Review the selected purchase request."

          icon="fa-file-invoice"

          size="large"

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
      >

          {{-- =====================================================
              INFORMATION
          ====================================================== --}}
          <x-ui.form-section
              title="Purchase Request Information"

              subtitle="Review the source Job Order and PR status."

              icon="fa-file-lines"
          >

              <div class="ui-form-grid">

                  {{-- PR --}}
                  <x-ui.form-field
                      label="PR No."

                      name="pr_no"

                      id="edit_pr_no"

                      icon="fa-hashtag"

                      readonly
                  />


                  {{-- JO --}}
                  <x-ui.form-field
                      label="JO No."

                      name="job_order_no"

                      id="edit_job_order_no"

                      icon="fa-clipboard-list"

                      readonly

                      required
                  />


                  {{-- BUS --}}
                  <x-ui.form-field
                      label="Bus #"

                      name="bus_no"

                      id="edit_bus_no"

                      icon="fa-bus"

                      readonly

                      required
                  />


                  {{-- STATUS --}}
                  <x-ui.form-field
                      label="Status"

                      name="status_display"

                      id="edit_status_display"

                      icon="fa-circle-info"

                      readonly
                  />

              </div>

          </x-ui.form-section>


          {{-- =====================================================
              PARTS
          ====================================================== --}}
          <x-ui.form-section
              title="Requested Parts"

              subtitle="Revise the requested parts before resubmitting a rejected PR."

              icon="fa-gears"
          >

              <x-slot:action>

                  <button
                      type="button"

                      id="addEditPrPartBtn"

                      class="ui-btn-small"
                  >

                      <i class="fa-solid fa-plus"></i>

                      Add Part

                  </button>

              </x-slot:action>


              <p
                  id="editPrDescription"

                  class="pr-edit-description"
              >
                  Review the purchase request information.
              </p>


              <div
                  id="editPrPartsContainer"

                  class="pr-parts-container"
              >
              </div>

          </x-ui.form-section>


          {{-- =====================================================
              REMARKS
          ====================================================== --}}
          <div class="ui-form-group ui-form-full">

              <label for="edit_remarks">
                  Remarks
              </label>


              <textarea
                  name="remarks"

                  id="edit_remarks"

                  placeholder="Optional remarks..."
              ></textarea>

          </div>


          {{-- =====================================================
              EDIT / RESUBMIT ACTIONS
          ====================================================== --}}
          <div
              class="ui-form-actions"

              id="editPrMainActions"
          >

              <button
                  type="button"

                  id="cancelEditPrModal"

                  class="
                      ui-form-btn
                      ui-form-btn-cancel
                  "
              >
                  Cancel
              </button>


              <button
                  type="submit"

                  id="submitEditPrBtn"

                  class="
                      ui-form-btn
                      ui-form-btn-primary
                  "
              >

                  <i
                      id="submitEditPrIcon"

                      class="fa-solid fa-floppy-disk"
                  ></i>


                  <span id="submitEditPrText">
                      Save Changes
                  </span>

              </button>

          </div>


          {{-- =====================================================
              VIEW ONLY
          ====================================================== --}}
          <div
              class="ui-form-actions"

              id="viewOnlyActions"

              style="display: none;"
          >

              <button
                  type="button"

                  id="closeViewOnlyPr"

                  class="
                      ui-form-btn
                      ui-form-btn-cancel
                  "
              >
                  Close
              </button>

          </div>

      </x-ui.form-modal>


      {{-- =============================================================
          APPROVE
      ============================================================== --}}
      <form
          id="approvePrForm"

          action="#"

          method="POST"

          class="hidden"
      >

          @csrf

      </form>


      {{-- =============================================================
          REJECT
      ============================================================== --}}
      <form
          id="rejectPrForm"

          action="#"

          method="POST"

          class="hidden"
      >

          @csrf


          <input
              type="hidden"

              name="remarks"

              value="Rejected by Maintenance Head"
          >

      </form>


      {{-- =============================================================
          GLOBAL APPROVE / REJECT CONFIRMATION
      ============================================================== --}}
      <x-ui.action-buttom-modal
          mode="global-confirmation"
      />


      {{-- =============================================================
          DELETE
      ============================================================== --}}
      <x-ui.action-buttom-modal
          mode="delete"

          id="deletePrModal"

          delete-title="Delete Purchase Request?"

          delete-message="Are you sure you want to delete"

          name-id="deletePrNo"

          cancel-id="cancelDeletePr"

          confirm-id="confirmDeletePr"
      />


  </x-layout.app>