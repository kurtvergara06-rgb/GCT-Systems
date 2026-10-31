<?php

namespace App\Models\Purchase;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrder extends Model
{
    protected $table = 'purchase_orders';

    protected $fillable = [
        'po_no',
        'po_date',
        'purchase_request_id',
        'supplier_name',
        'supplier_address_tel',
        'terms',
        'terms_of_payment',
        'purpose',
        'items',
        'gross_amount',
        'delivery_fee',
        'discount',
        'vat',
        'net_amount',
        'status',
        'inventory_posted_at',
        'received_quantities',
        'approved_by',
        'approved_at',
        'approval_signature_path',
        'reviewed_by',
        'reviewed_at',
        'review_remarks',
    ];

    protected $casts = [
        'items' => 'array',
        'po_date' => 'date',
        'gross_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'discount' => 'decimal:2',
        'vat' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'inventory_posted_at' => 'datetime',
        'received_quantities' => 'array',
        'approved_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];


    /**
     * Display-only receipt progress. Keep persisted PO statuses unchanged so
     * existing workflow filters, approval rules and inventory posting remain intact.
     */
    public function getIsPartiallyReceivedAttribute(): bool
    {
        if ($this->inventory_posted_at !== null) {
            return false;
        }

        $ordered = collect($this->items ?? [])->sum(fn ($item) => max(0, (int) ($item['quantity'] ?? 0)));
        $received = collect($this->received_quantities ?? [])->sum(fn ($quantity) => max(0, (int) $quantity));

        return $ordered > 0 && $received > 0 && $received < $ordered;
    }

    public function getReceiptProgressAttribute(): array
    {
        $ordered = collect($this->items ?? [])->sum(fn ($item) => max(0, (int) ($item['quantity'] ?? 0)));
        $received = collect($this->received_quantities ?? [])->sum(fn ($quantity) => max(0, (int) $quantity));

        return ['ordered' => $ordered, 'received' => min($ordered, $received), 'remaining' => max(0, $ordered - $received)];
    }

    public function getReceiptDisplayStatusAttribute(): string
    {
        return $this->is_partially_received ? 'Partially Received' : (string) $this->status;
    }

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(
            MaintenanceRequest::class,
            'purchase_request_id'
        );
    }

    public function getFirstPrNoAttribute(): ?string
    {
        if (! is_array($this->items) || empty($this->items)) {
            return null;
        }

        $firstItem = $this->items[0] ?? [];

        return $firstItem['pr_no'] ?? null;
    }

    public function relatedMaintenanceRequest(): ?MaintenanceRequest
    {
        if ($this->maintenanceRequest) {
            return $this->maintenanceRequest;
        }

        $prNo = $this->normalizePrNo($this->first_pr_no);

        if (! $prNo) {
            return null;
        }

        return MaintenanceRequest::where('pr_no', $prNo)->first();
    }

    private function normalizePrNo(?string $prNo): ?string
    {
        $prNo = trim((string) $prNo);

        if ($prNo === '') {
            return null;
        }

        return preg_replace('/-P$/i', '', $prNo);
    }
}