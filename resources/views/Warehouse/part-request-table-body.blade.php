            <tbody>
              @foreach($purchaseRequests->getCollection() as $partRequest)
                @php
                  // Initialize display values for every row before branching so
                  // Blade-rendered shared table fragments never reference undefined variables.
                  $itemName = $partRequest->first_item_display ?? $partRequest->item ?? '—';
                  $quantity = $partRequest->first_quantity_display ?? (string) ($partRequest->quantity ?? 0);
                  $inventoryStatus = $partRequest->first_inventory_status ?? $partRequest->inventory_label ?? 'Not Available';
                  $partDetails = collect($partRequest->parts_breakdown ?? []);
                  $multipleParts = $partDetails->count() > 1;
                @endphp
                @if($partRequest->source_type === 'Auto Restock')
                  @php
                    $restockLines = collect(json_decode((string) $partRequest->restock_items, true) ?: []);
                  @endphp
                  <tr>
                    <td><div class="pr-ref-cell"><strong class="pr-main-num">{{ $partRequest->pr_no }}</strong></div></td>
                    <td class="item-col"><div class="warehouse-pr-item-summary"><strong class="warehouse-pr-item-name">{{ $restockLines->first()['name'] ?? $partRequest->item }}</strong>
                      @if($restockLines->count() > 1)
                        <details class="warehouse-pr-parts-more"><summary>+{{ $restockLines->count()-1 }} more <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary><div class="warehouse-pr-parts-popup">
                          @foreach($restockLines as $line)<div><span>{{ $line['name'] ?? '—' }}</span><strong>{{ $line['quantity'] ?? 0 }} {{ $line['unit'] ?? '' }}</strong></div>@endforeach
                        </div></details>
                      @endif
                    </div></td>
                    <td class="qty-col"><span class="qty-needed-badge">{{ $restockLines->count() > 1 ? $restockLines->count().' items' : $partRequest->quantity }}</span></td>
                    <td class="status-col"><span class="warehouse-ir-stock-status is-partial"><i class="fa-solid fa-warehouse" aria-hidden="true"></i> Warehouse Restock</span></td>
                    <td class="status-col"><span class="warehouse-ir-process-status is-warning"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> {{ $partRequest->status }}</span></td>
                    <td class="actions-col"><div class="actions warehouse-actions">
                      <button type="button" class="action-btn view view-btn open-restock-warehouse-view"
                        data-pr-no="{{ $partRequest->pr_no }}"
                        data-status="{{ $partRequest->status }}"
                        data-purpose="{{ $partRequest->purpose ?? 'Inventory replenishment' }}"
                        data-remarks="{{ $partRequest->remarks ?? 'No remarks' }}"
                        data-parts="{{ $restockLines->toJson() }}"
                        title="View Restocking PR Details" aria-label="View Restocking PR Details">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i> <span>View</span>
                      </button>
                    </div></td>
                  </tr>
                @else
                @php
                  $status = $partRequest->purchase_progress_status ?? $partRequest->status ?? 'Approved';
                  $itemName = $partRequest->first_item_display ?? '—';
                  $quantity = $partRequest->first_quantity_display ?? '0';
                  $onHand = $partRequest->first_on_hand_display ?? '0';
                  $inventoryStatus = $partRequest->first_inventory_status ?? $partRequest->inventory_label ?? 'Not Available';
                  $onHandClass = $inventoryStatus === 'Available' ? 'enough' : 'low';
                  $missingPrAlreadyCreated = $partRequest->missing_pr_already_created ?? false;
                  $canSendToPurchase = ($partRequest->needs_purchase ?? false) && ($canApproveWarehouse ?? false)
                    && !$missingPrAlreadyCreated
                    && in_array($partRequest->status, ['Approved', 'Submitted'], true);
                  $canIssue = ($partRequest->can_issue ?? false) && $canEditWarehouse
                    && $inventoryStatus === 'Available';
                  $warehouseStatus = $isHistory
                    ? ($partRequest->warehouse_status ?: $partRequest->status)
                    : ($partRequest->warehouse_workflow_status ?? 'Ready to Issue');
                  $warehouseStatusLabel = $warehouseStatus;
                  $partDetails = collect($partRequest->parts_breakdown ?? []);
                  $multipleParts = $partDetails->count() > 1;
                @endphp

                @php
                  $sourceJo = \App\Models\Maintenance\JobOrder::query()->with(['approver', 'audits'])->where('job_order_no', $partRequest->job_order_no)->first();
                  $sourceApprovalAudit = $sourceJo?->audits?->first(fn ($audit) => data_get($audit->metadata, 'handwritten_signature_path'));
                  $sourceSignaturePath = data_get($sourceApprovalAudit?->metadata, 'handwritten_signature_path');
                  $sourceAuthorized = $sourceJo?->review_status === 'Approved';
                  $sourceSignatureUrl = $sourceAuthorized && $sourceSignaturePath ? route('warehouse.job-orders.approval-signature', $sourceJo->id, false) : '';
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
                    <div class="warehouse-pr-item-summary">
                      <span class="warehouse-pr-item-name" title="{{ $itemName }}">{{ trim(preg_replace('/\s*\[item:\d+\]/i', '', (string) $itemName)) ?: $itemName }}</span>
                    @if($multipleParts)
                      <details class="warehouse-pr-parts-more">
                        <summary>+{{ $partDetails->count() - 1 }} more <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                        <div class="warehouse-pr-parts-popup">
                          @foreach($partDetails as $part)
                            <div><span>{{ trim(preg_replace('/\s*\[item:\d+\]/i', '', (string) ($part['name'] ?? '—'))) }}</span><strong>{{ $part['needed_display'] ?? (($part['needed'] ?? 0).' '.($part['unit'] ?? '')) }}</strong></div>
                          @endforeach
                        </div>
                      </details>
                    @endif
                    </div>
                  </td>
                  <td class="qty-col">
                    <span class="qty-needed-badge">{{ $multipleParts ? $partDetails->count().' items' : $quantity }}</span>
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
                      @if(!$canSendToPurchase)
                      <button
                        type="button"
                        class="action-btn view view-btn open-view-pr-modal"
                        title="View Requisition Details"
                        data-jo-approver="{{ $sourceAuthorized ? ($sourceJo?->approver?->name ?? '—') : 'Not approved' }}"
                        data-jo-approval-status="{{ $sourceJo?->review_status ?? 'JO not found' }}"
                        data-jo-approval-signature="{{ $sourceSignatureUrl }}"
                        data-jo-approved-at="{{ ($sourceJo?->approved_at ?? $sourceApprovalAudit?->created_at)?->format('M d, Y · h:i A') ?? '' }}"
                        data-pr-no="{{ $partRequest->pr_no }}"
                        data-job-order-no="{{ $partRequest->job_order_no }}"
                        data-bus-no="{{ $partRequest->bus_no }}"
                        data-requested-by="{{ $partRequest->requester?->name ?? '—' }}"
                        data-item="{{ $partRequest->item }}"
                        data-quantity="{{ $quantity }}"
                        data-on-hand="{{ $onHand }}"
                        data-inventory-status="{{ $inventoryStatus }}"
                        data-status="{{ $status }}"
                        data-warehouse-status="{{ $warehouseStatusLabel }}"
                        data-issued-quantities='@json($partRequest->warehouse_issue_quantities ?? [])'
                        data-remarks="{{ $partRequest->remarks ?? 'No remarks' }}"
                        data-created="{{ $partRequest->created_at?->format('M d, Y · h:i A') ?? '—' }}"
                        data-parts='@json($partRequest->parts_breakdown ?? [])'
                        data-jo-parts='@json($partRequest->jo_parts_requirement ?? [])'
                      >
                        <i class="fa-solid fa-eye"></i> <span>View</span>
                      </button>
                      @endif

                      @unless($isHistory)
                      @if($canSendToPurchase)
                        <form
                          action="{{ route('part-requests.send-to-purchase', $partRequest->id) }}"
                          method="POST"
                          class="inline-action-form"
                          data-pr-send-form
                        data-jo-approver="{{ $sourceAuthorized ? ($sourceJo?->approver?->name ?? '—') : 'Not approved' }}"
                        data-jo-approval-status="{{ $sourceJo?->review_status ?? 'JO not found' }}"
                        data-jo-approval-signature="{{ $sourceSignatureUrl }}"
                        data-jo-approved-at="{{ ($sourceJo?->approved_at ?? $sourceApprovalAudit?->created_at)?->format('M d, Y · h:i A') ?? '' }}"
                          data-pr-no="{{ $partRequest->pr_no }}"
                          data-jo-no="{{ $partRequest->job_order_no }}"
                          data-bus-no="{{ $partRequest->bus_no }}"
                          data-parts='@json($partRequest->parts_breakdown ?? [])'
                        >
                          @csrf
                          <button type="submit" class="send-purchase-btn warehouse-pr-review-action" title="Review missing parts before forwarding to Purchasing" aria-label="Review Purchase Request">
                            <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> <span>Review</span>
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
                @endif
              @endforeach
              @if($purchaseRequests->getCollection()->isEmpty())
                <x-ui.empty-row
                  colspan="6"
                  icon="fa-clipboard-list"
                  :message="$isHistory ? 'No Part Request History Found' : 'No Active Part Requests Found'"
                  :description="$isHistory ? 'Issued and fulfilled part requests will appear here.' : 'Maintenance part requisitions awaiting fulfillment will appear here.'"
                />
              @endif
            </tbody>
