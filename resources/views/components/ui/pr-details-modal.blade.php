@props([
    'modalId',
    'titleId',
    'closeId',
    'prId',
    'joId',
    'busId',
    'partsId',
    'requestedById',
    'createdId',
    'processingId',
    'partsCountId',
    'stockReferenceId',
    'remarksId',
    'statusBadgeId' => null,
])

{{-- Shared, read-only Purchase Request presentation used by Warehouse and Purchasing. --}}
<div id="{{ $modalId }}" class="modal-overlay shared-pr-details-overlay" aria-hidden="true">
    <div class="shared-pr-details-dialog" role="dialog" aria-modal="true" aria-labelledby="{{ $titleId }}">
        <header class="shared-pr-details-header">
            <span class="shared-pr-details-title-icon" aria-hidden="true"><i class="fa-solid fa-file-lines"></i></span>
            <div class="shared-pr-details-title-copy">
                <h2 id="{{ $titleId }}">Purchase Request Details</h2>
                <p>Review requested shortages, stock reference, and procurement details.</p>
            </div>
            @if($statusBadgeId)
                <span id="{{ $statusBadgeId }}" class="shared-pr-details-status">—</span>
            @endif
            <button type="button" id="{{ $closeId }}" class="shared-pr-details-close" aria-label="Close Purchase Request Details"><i class="fa-solid fa-xmark"></i></button>
        </header>

        <div class="shared-pr-details-scrollable">
            <x-ui.pr-summary :pr-id="$prId" :jo-id="$joId" :bus-id="$busId" />

            <section class="shared-pr-details-section">
                <header class="shared-pr-details-section-header">
                    <span class="shared-pr-details-section-icon"><i class="fa-solid fa-shield-halved"></i></span>
                    <div><h3>Maintenance Head Authorization</h3><p>Approval recorded on the linked Job Order (not a separate PR signature).</p></div>
                </header>
                <div class="shared-pr-details-section-body shared-pr-head-content">
                    <div class="shared-pr-head-person"><span>Approved By</span><strong data-pr-head-name>—</strong><small data-pr-head-status>Pending verification</small><small data-pr-head-date></small></div>
                    <div class="shared-pr-head-signature"><span>Authorized JO Signature</span><img data-pr-head-signature alt="Maintenance Head's saved Job Order approval signature" hidden><small data-pr-head-no-signature>Signature unavailable</small></div>
                </div>
            </section>

            <section class="shared-pr-details-section">
                <header class="shared-pr-details-section-header">
                    <span class="shared-pr-details-section-icon"><i class="fa-solid fa-cube"></i></span>
                    <div><h3>Parts Requiring Purchase</h3><p>Requested shortages and Warehouse stock reference.</p></div>
                </header>
                <div class="shared-pr-details-section-body">
                    <div id="{{ $partsId }}" class="shared-pr-parts-box"><div class="shared-pr-empty">No parts found.</div></div>
                    <p class="shared-pr-reference-info"><i class="fa-solid fa-circle-info"></i> Warehouse stock shown for reference only. PR quantities represent unavailable shortages to be procured.</p>
                </div>
            </section>

            <details class="shared-pr-extra-details">
                <summary><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Additional Request Information <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                <div class="shared-pr-extra-grid">
                    <div><span>Requested By</span><strong id="{{ $requestedById }}">—</strong></div>
                    <div><span>Date Requested</span><strong id="{{ $createdId }}">—</strong></div>
                    <div><span>Department</span><strong>Maintenance</strong></div>
                    <div><span>Processing</span><strong id="{{ $processingId }}">—</strong></div>
                    <div><span>Total Parts</span><strong id="{{ $partsCountId }}">—</strong></div>
                </div>
                <section class="shared-pr-details-section">
                    <header class="shared-pr-details-section-header">
                        <span class="shared-pr-details-section-icon"><i class="fa-solid fa-warehouse"></i></span>
                        <div><h3>Warehouse Stock Reference</h3><p>Physical stock currently visible to Warehouse while reviewing this PR.</p></div>
                    </header>
                    <div class="shared-pr-details-section-body"><div id="{{ $stockReferenceId }}" class="shared-pr-parts-box"><div class="shared-pr-empty">No stock reference found.</div></div></div>
                </section>
                <section class="shared-pr-details-section">
                    <header class="shared-pr-details-section-header">
                        <span class="shared-pr-details-section-icon"><i class="fa-solid fa-comment-dots"></i></span>
                        <div><h3>Remarks</h3></div>
                    </header>
                    <div class="shared-pr-details-section-body"><div class="shared-pr-readonly-note" id="{{ $remarksId }}">No remarks provided.</div></div>
                </section>
            </details>
        </div>

        <footer class="shared-pr-details-footer">{{ $footer }}</footer>
    </div>
</div>
