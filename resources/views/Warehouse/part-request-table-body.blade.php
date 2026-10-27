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
                  $parts = collect($partRequest->parts_breakdown ?? []);
                  $displayStatus = $isHistory ? ($warehouseStatusLabel ?: $status) : ($warehouseStatusLabel ?: $status);
                  $displayStatus = in_array($displayStatus, ['Ready to Issue', 'Approved'], true) && $canSendToPurchase ? 'For Purchase' : $displayStatus;
                @endphp

                <tr>
                  <td><strong class="pr-main-num">{{ $partRequest->pr_no ?? '—' }}</strong></td>
                  <td><span class="pr-meta-tag jo-tag">{{ $partRequest->job_order_no ?: '—' }}</span></td>
                  <td><span class="pr-meta-tag bus-tag"><i class="fa-solid fa-bus" aria-hidden="true"></i> {{ $partRequest->bus_no ?: '—' }}</span></td>
                  <td class="item-col">
                    <strong class="item-name-text">{{ $itemName }}</strong>
                    @if($parts->count() > 1)
                      <details class="warehouse-pr-more-details">
                        <summary>+{{ $parts->count() - 1 }} more</summary>
                        <div class="warehouse-pr-more-items">
                          @foreach($parts as $part)
                            <div><span>{{ $part['name'] ?? '—' }}</span><strong>{{ $part['needed_display'] ?? ($part['needed'] ?? $part['quantity'] ?? '—') }}</strong></div>
                          @endforeach
                        </div>
                      </details>
                    @endif
                  </td>
                  <td><span class="warehouse-pr-status">{{ $displayStatus }}</span></td>
                  <td class="actions-col">
                    <button type="button" class="warehouse-pr-review open-view-pr-modal"
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
                      data-parts='@json($partRequest->parts_breakdown ?? [])'>
                      <i class="fa-solid fa-eye" aria-hidden="true"></i> {{ $isHistory ? 'View' : 'Review' }}
                    </button>
                    <div class="warehouse-pr-row-actions" hidden>
                      @unless($isHistory)
                        @if($canSendToPurchase)
                          <form action="{{ route('part-requests.send-to-purchase', $partRequest->id) }}" method="POST"
                            class="inline-action-form" data-confirm-form
                            data-confirm-title="Send Missing Parts to Purchase?"
                            data-confirm-message="Are you sure you want to send the missing parts for {{ $partRequest->pr_no }} to Purchase?"
                            data-confirm-button="Yes, Send to Purchase" data-confirm-type="warning">
                            @csrf
                            <button type="submit" class="send-purchase-btn warehouse-pr-modal-action"><i class="fa-solid fa-cart-shopping"></i> Send to Purchase</button>
                          </form>
                        @endif
                        @if($canIssue)
                          <button type="button" class="issue-part-btn warehouse-pr-modal-action open-issue-modal"
                            data-action="{{ route('part-requests.issue', $partRequest->id) }}"
                            data-pr-no="{{ $partRequest->pr_no }}"
                            data-parts='@json($partRequest->parts_breakdown ?? [])'>
                            <i class="fa-solid fa-box-open"></i> Issue Parts
                          </button>
                        @endif
                      @endunless
                    </div>
                  </td>
                </tr>
              @endforeach
              @if($purchaseRequests->isEmpty())
                <x-ui.empty-row
                  colspan="6"
                  icon="fa-clipboard-list"
                  :message="$isHistory ? 'No Part Request History Found' : 'No Active Part Requests Found'"
                  :description="$isHistory ? 'Issued and fulfilled part requests will appear here.' : 'Maintenance part requisitions awaiting fulfillment will appear here.'"
                />
              @endif
            </tbody>
