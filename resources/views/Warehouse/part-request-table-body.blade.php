            <tbody>
              @foreach($purchaseRequests as $partRequest)
                @php
                  $status = $partRequest->purchase_progress_status ?? $partRequest->status ?? 'Approved';
                  $itemName = $partRequest->first_item_display ?? '—';
                  $quantity = $partRequest->first_quantity_display ?? '0';
                  $onHand = $partRequest->first_on_hand_display ?? '0';
                  $inventoryStatus = $partRequest->first_inventory_status ?? $partRequest->inventory_label ?? 'Not Available';
                  $onHandClass = $inventoryStatus === 'Available' ? 'enough' : 'low';
                  $missingPrAlreadyCreated = $partRequest->missing_pr_already_created ?? false;
                  $canSendToPurchase = ($partRequest->needs_purchase ?? false) && $canEditWarehouse
                    && !$missingPrAlreadyCreated
                    && $status === 'Approved';
                  $canIssue = ($partRequest->can_issue ?? false) && $canEditWarehouse
                    && $inventoryStatus === 'Available';
                  $warehouseStatus = $isHistory
                    ? ($partRequest->warehouse_status ?: $partRequest->status)
                    : ($partRequest->warehouse_workflow_status ?? 'Ready to Issue');
                  $warehouseStatusLabel = $warehouseStatus;
                @endphp

                <tr>
                  <td>
                    <div class="pr-ref-cell">
                      <strong class="pr-main-num">{{ $partRequest->pr_no ?? '—' }}</strong>
                      @if($partRequest->job_order_no || $partRequest->bus_no)
                        <div class="pr-sub-tags">
                          @if($partRequest->job_order_no)
                            <span class="pr-meta-tag jo-tag" title="Job Order"><i class="fa-solid fa-wrench"></i> {{ $partRequest->job_order_no }}</span>
                          @endif
                          @if($partRequest->bus_no)
                            <span class="pr-meta-tag bus-tag" title="Bus Number"><i class="fa-solid fa-bus"></i> {{ $partRequest->bus_no }}</span>
                          @endif
                        </div>
                      @endif
                    </div>
                  </td>
                  <td class="item-col" title="{{ $itemName }}">
                    <div class="item-display-cell">
                      <strong class="item-name-text">{{ $itemName }}</strong>
                    </div>
                  </td>
                  <td class="qty-col">
                    <span class="qty-needed-badge">{{ $quantity }}</span>
                  </td>
                  <td class="qty-col">
                    <span class="on-hand-pill {{ $onHandClass }}" title="Quantity on hand in inventory">
                      <i class="fa-solid {{ $onHandClass === 'enough' ? 'fa-circle-check' : 'fa-triangle-exclamation' }}"></i>
                      {{ $onHand }}
                    </span>
                  </td>
                  <td class="status-col"><x-ui.status-badge :status="$inventoryStatus" type="inventory" /></td>
                  <td class="status-col"><x-ui.status-badge :status="$status" type="purchase" /></td>
                  <td class="status-col">
                    <x-ui.status-badge :status="$warehouseStatusLabel" class="warehouse-status-pill" title="{{ $warehouseStatus }}" />
                  </td>
                  <td class="actions-col">
                    <div class="actions warehouse-actions">
                      <button
                        type="button"
                        class="action-btn view view-btn open-view-pr-modal"
                        title="View Requisition Details"
                        data-pr-no="{{ $partRequest->pr_no }}"
                        data-job-order-no="{{ $partRequest->job_order_no }}"
                        data-bus-no="{{ $partRequest->bus_no }}"
                        data-item="{{ $partRequest->item }}"
                        data-quantity="{{ $quantity }}"
                        data-on-hand="{{ $onHand }}"
                        data-inventory-status="{{ $inventoryStatus }}"
                        data-status="{{ $status }}"
                        data-warehouse-status="{{ $warehouseStatusLabel }}"
                        data-issued-quantities='@json($partRequest->warehouse_issue_quantities ?? [])'
                        data-remarks="{{ $partRequest->remarks ?? 'No remarks' }}"
                        data-created="{{ $partRequest->created_at?->format('M d, Y') ?? '—' }}"
                        data-parts='@json($partRequest->parts_breakdown ?? [])'
                      >
                        <i class="fa-solid fa-eye"></i>
                      </button>

                      @unless($isHistory)
                      @if($canSendToPurchase)
                        <form
                          action="{{ route('part-requests.send-to-purchase', $partRequest->id) }}"
                          method="POST"
                          class="inline-action-form"
                          data-confirm-form
                          data-confirm-title="Send Missing Parts to Purchase?"
                          data-confirm-message="Are you sure you want to send the missing parts for {{ $partRequest->pr_no }} to Purchase?"
                          data-confirm-button="Yes, Send to Purchase"
                          data-confirm-type="warning"
                        >
                          @csrf
                          <button type="submit" class="send-purchase-btn icon-only-btn" title="Send Missing Parts to Purchase">
                            <i class="fa-solid fa-cart-shopping"></i>
                          </button>
                        </form>
                      @endif

                      @if($canIssue)
                        <button
                          type="button"
                          class="issue-part-btn icon-only-btn open-issue-modal"
                          title="Record and Issue Parts"
                          data-action="{{ route('part-requests.issue', $partRequest->id) }}"
                          data-pr-no="{{ $partRequest->pr_no }}"
                          data-parts='@json($partRequest->parts_breakdown ?? [])'
                        >
                          <i class="fa-solid fa-box-open"></i>
                        </button>
                      @endif
                      @endunless
                    </div>
                  </td>
                </tr>
              @endforeach
              @if($purchaseRequests->isEmpty())
                <x-ui.empty-row
                  colspan="8"
                  icon="fa-clipboard-list"
                  :message="$isHistory ? 'No Part Request History Found' : 'No Active Part Requests Found'"
                  :description="$isHistory ? 'Issued and fulfilled part requests will appear here.' : 'Maintenance part requisitions awaiting fulfillment will appear here.'"
                />
              @endif
            </tbody>
