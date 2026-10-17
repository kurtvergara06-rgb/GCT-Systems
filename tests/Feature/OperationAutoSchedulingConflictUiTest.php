<?php

it('renders conflicts as a severity queue with an actionable report panel', function (): void {
    $script = file_get_contents(resource_path(
        'js/Operation/Scheduling_And_Dispatch/auto-scheduling.js'
    ));

    expect($script)
        ->toContain('getConflictSeverity')
        ->toContain("renderConflictSortHeader('Severity', 'severity')")
        ->toContain('AI Conflict Report')
        ->toContain('Issue Summary')
        ->toContain('Detailed Explanation')
        ->toContain('Terminal')
        ->toContain('formatOrdinal')
        ->toContain('data-gct-conflict-sort')
        ->toContain('conflictSortDirection')
        ->toContain('data-gct-report-index')
        ->toContain('tabindex="0"')
        ->toContain("event.key !== 'Enter' && event.key !== ' '")
        ->not->toContain('class="gct-conflict-arrow')
        ->toContain('Recommended Assignments')
        ->toContain('Alternative Options')
        ->toContain('Driver Alternatives')
        ->toContain('Bus Alternatives')
        ->toContain('Valid Driver&ndash;Bus Pairs')
        ->toContain('Trained ML model')
        ->toContain('Data-derived fallback')
        ->toContain('Rule-based fallback')
        ->toContain('Apply Recommendation');
});

it('keeps conflict recommendations connected to safe existing resolution paths', function (): void {
    $script = file_get_contents(resource_path(
        'js/Operation/Scheduling_And_Dispatch/auto-scheduling.js'
    ));

    expect($script)
        ->toContain("const resolvePath = '/operation/auto-scheduling/resolve'")
        ->toContain("const assignmentPath = '/operation/driver-bus-assignment'")
        ->toContain('data-review-ai-resolution')
        ->toContain('delete container.dataset.gctWorkspaceSignature')
        ->toContain('data-gct-close-report')
        ->toContain('&& !conflictReportClosed')
        ->toContain('The system will verify')
        ->toContain('&& !missingDriver')
        ->toContain('&& !missingBus');
});

it('bridges reserve selection to the assignment editor by stable trip slot identity', function (): void {
    $script = file_get_contents(resource_path(
        'js/Operation/Scheduling_And_Dispatch/auto-scheduling.js'
    ));

    expect($script)
        ->toContain('const conflictIdentity = (conflict) => [')
        ->toContain("document.addEventListener('gct:apply-inline-alternative'")
        ->toContain("document.dispatchEvent(new CustomEvent('gct:apply-inline-alternative'")
        ->toContain("document.addEventListener('gct:apply-reserve-selection'")
        ->toContain('data-apply-selected-reserve')
        ->toContain('selectedReserveByConflict.get(conflictKey)')
        ->toContain("document.addEventListener('gct:auto-reserve-selections-reset'")
        ->not->toContain('const item = schedulingConflicts[index];\n            const selection = selectedReserveByConflict.get(index)');
});

it('provides responsive styles for the conflict queue and report', function (): void {
    $styles = file_get_contents(resource_path(
        'css/Operation/Scheduling_And_Dispatch/auto-dispatch.css'
    ));

    expect($styles)
        ->toContain('.gct-conflict-severity.high')
        ->toContain('.gct-conflict-severity.medium')
        ->toContain('.gct-conflict-severity.low')
        ->toContain('.gct-report-recommendations')
        ->toContain('.gct-report-actions')
        ->toContain('border: 1px solid #d9e4f2')
        ->toContain('align-items: start')
        ->toContain('@media (max-width: 720px)');
});

it('shows compact validated driver bus pairs first with collapsible details and retained server-side actions', function (): void {
    $script = file_get_contents(resource_path('js/Operation/Scheduling_And_Dispatch/auto-scheduling.js'));
    $styles = file_get_contents(resource_path('css/Operation/Scheduling_And_Dispatch/auto-dispatch.css'));

    expect($script)
        ->toContain('Choose a valid driver–bus pair')
        ->toContain('gct-compact-pair-list')
        ->toContain('gct-compact-details')
        ->toContain('data-gct-inline-pair')
        ->toContain("document.dispatchEvent(new CustomEvent('gct:apply-inline-alternative'")
        ->toContain('canAssignReserve()')
        ->toContain('Final Laravel validation applies');
    expect($styles)
        ->toContain('.gct-compact-pair-list')
        ->toContain('.gct-compact-details')
        ->toContain('.gct-report-actions { position:sticky');
});
