@props([
    'prId' => '',
    'joId' => '',
    'busId' => '',
])

{{-- Shared PR identity panel: same markup in Warehouse and Purchasing. --}}
<div {{ $attributes->class(['warehouse-request-summary', 'warehouse-pr-reference-strip', 'warehouse-send-missing-meta', 'shared-pr-summary']) }} aria-label="Purchase Request summary">
    <div class="warehouse-summary-item"><span>PR ID</span><strong id="{{ $prId }}">—</strong></div>
    <div class="warehouse-summary-item"><span>Linked JO</span><strong id="{{ $joId }}">—</strong></div>
    <div class="warehouse-summary-item"><span>Bus</span><strong id="{{ $busId }}">—</strong></div>
</div>
