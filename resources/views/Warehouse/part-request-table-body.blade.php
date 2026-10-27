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
                  $partDetails = collect($partRequest->parts_breakdown ?? []);
                  $multipleParts = $partDetails->count() > 1;
                @endphp

                <tr>
                  <td>
                    <div class="pr-ref-cell">
                      <strong class="pr-main-num">{{ $partRequest->pr_no ?? '—' }}</strong>
                      @if($partRequest->job_order_no)
                        <div class="pr-sub-tags">
                          @if($partRequest->job_order_no)
                            <span class="pr-meta-tag jo-tag" title="Job Order"><i class="fa-solid fa-wrench"></i> {{ $partRequest->job_order_no }}</span>
                          @endif
                        </div>
                      @endif
                    </div>
                  </td>
                  <td class="item-col">
                    <strong class="item-name-text">{{ $itemName }}</strong>
                    @if($multipleParts)
                      <details class="warehouse-pr-parts-more">
                        <summary>+{{ $partDetails->count() - 1 }} more <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                        <div class="warehouse-pr-parts-popup">
                          @foreach($partDetails as $part)
                            <div><span>{{ $part['name'] ?? '—' }}</span><strong>{{ $part['needed_display'] ?? (($part['needed'] ?? 0).' '.($part['unit'] ?? '')) }}</strong></div>
                          @endforeach
                        </div>
                      </details>
                    @endif
                  </td>
                  <td class="qty-col">
                    <span class="qty-needed-badge">{{ $multipleParts ? $partDetails->count().' items' : $quantity }}</span>
                  </td>
                  <td class="qty-col">
                    @if($multipleParts)
                      <div class="warehouse-pr-onhand-summary">
                        <strong>Stock Summary</strong>
                        <details class="warehouse-pr-parts-more">
                          <summary>View Stock <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                          <div class="warehouse-pr-parts-popup warehouse-pr-stock-popover">
                            <div class="warehouse-pr-popup-title">JO requirement &amp; purchase shortage</div>
                            @php
                              $joParts = collect($partRequest->jo_parts_requirement ?? []);
                              $normalizedName = fn ($value) => mb_strtolower(trim(preg_replace('/\\s*\\[item:\\d+\\]/i', '', (string) $value)));
                              $displayParts = $joParts->isNotEmpty() ? $joParts : $partDetails;
                            @endphp
                            @foreach($displayParts as $part)
                              @php
                                $joName = $part['name'] ?? '—';
                                $joItemId = (int) ($part['inventory_item_id'] ?? 0);
                                $matchingPr = $joParts->isNotEmpty()
                                  ? $partDetails->first(function ($pr) use ($joName, $joItemId, $normalizedName) {
                                      $prName = (string) ($pr['name'] ?? '');
                                      return ($joItemId > 0 && str_contains($prName, '[item:'.$joItemId.']'))
                                        || $normalizedName($prName) === $normalizedName($joName);
                                    })
                                  : $part;
                                $joRequired = (int) ($part['quantity'] ?? $part['needed'] ?? 0);
                                $prRequired = (int) ($matchingPr['needed'] ?? 0);
                                $prAvailable = (int) ($matchingPr['available'] ?? 0);
                                $shortForPurchase = max(0, $prRequired - $prAvailable);
                                $unit = $part['unit'] ?? $matchingPr['unit'] ?? '';
                                $irQuantity = collect($partRequest->jo_ir_parts ?? [])->filter(function ($irPart) use ($joName, $joItemId, $normalizedName) {
                                    $irName = (string) ($irPart['name'] ?? '');
                                    return ($joItemId > 0 && (int) ($irPart['inventory_item_id'] ?? 0) === $joItemId)
                                        || $normalizedName($irName) === $normalizedName($joName);
                                })->sum(fn ($irPart) => (int) ($irPart['quantity'] ?? 0));
                              @endphp
                              <div class="warehouse-pr-stock-popover-row">
                                <span class="warehouse-pr-stock-popover-icon" aria-hidden="true"><i class="fa-solid fa-box"></i></span>
                                <span class="warehouse-pr-stock-popover-info">
                                  <strong class="warehouse-pr-stock-popover-name">{{ $joName }}</strong>
                                  <small>JO required: {{ $joRequired }} {{ $unit }}</small>
                                  @if($irQuantity > 0)<small class="warehouse-pr-stock-ok">{{ $irQuantity }} {{ $unit }} listed in linked IR</small>@endif
                                </span>
                                <span class="warehouse-pr-stock-popover-amount">
                                  @if($matchingPr)
                                    <strong>{{ $prRequired }} {{ $unit }} in PR</strong>
                                    @if($shortForPurchase > 0)
                                      <small class="warehouse-pr-shortage-quantity">{{ $shortForPurchase }} {{ $unit }} to purchase</small>
                                    @else
                                      <small class="warehouse-pr-stock-ok">PR quantity covered</small>
                                    @endif
                                  @else
                                    <strong>Not in PR</strong>
                                    <small class="warehouse-pr-stock-ok">{{ $irQuantity > 0 ? 'See linked IR for issuance' : 'No matching PR shortage' }}</small>
                                  @endif
                                </span>
                              </div>
                            @endforeach
                          </div>
                        </details>
                      </div>
                    @else
                      <span class="on-hand-pill {{ $onHandClass }}" title="Current Warehouse on-hand stock">{{ $onHand }}</span>
                    @endif
                  </td>
                  @php
                    $inventoryLabel = match (strtolower(trim((string) $inventoryStatus))) {
                      'available', 'sufficient' => 'Available',
                      'partial', 'partially available' => 'Partial',
                      default => 'Not Available',
                    };
                    $inventoryClass = match ($inventoryLabel) {
                      'Available' => 'is-available',
                      'Partial' => 'is-partial',
                      default => 'is-unavailable',
                    };
                    $inventoryIcon = match ($inventoryLabel) {
                      'Available' => 'fa-circle-check',
                      'Partial' => 'fa-triangle-exclamation',
                      default => 'fa-circle-xmark',
                    };
                    $workflowStatus = strtolower(trim((string) $warehouseStatusLabel));
                    $isWarehouseComplete = in_array($workflowStatus, ['issued', 'completed', 'delivered', 'picked up'], true);
                    $isWarehouseWarning = in_array($workflowStatus, ['needs purchase', 'for purchase', 'pending', 'partial', 'rejected', 'cancelled'], true);
                  @endphp
                  <td class="status-col">
                    <span class="warehouse-ir-stock-status {{ $inventoryClass }}">
                      <i class="fa-solid {{ $inventoryIcon }}" aria-hidden="true"></i>
                      {{ $inventoryLabel }}
                    </span>
                  </td>
                  <td class="status-col">
                    <span class="warehouse-ir-process-status {{ $isWarehouseComplete ? 'is-issued' : ($isWarehouseWarning ? 'is-warning' : 'is-submitted') }}" title="{{ $warehouseStatus }}">
                      <i class="fa-solid {{ $isWarehouseComplete ? 'fa-circle-check' : ($isWarehouseWarning ? 'fa-triangle-exclamation' : 'fa-paper-plane') }}" aria-hidden="true"></i>
                      {{ $warehouseStatusLabel }}
                    </span>
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
                  colspan="7"
                  icon="fa-clipboard-list"
                  :message="$isHistory ? 'No Part Request History Found' : 'No Active Part Requests Found'"
                  :description="$isHistory ? 'Issued and fulfilled part requests will appear here.' : 'Maintenance part requisitions awaiting fulfillment will appear here.'"
                />
              @endif
            </tbody>
