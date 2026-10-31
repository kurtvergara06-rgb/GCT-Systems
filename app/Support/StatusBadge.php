<?php

namespace App\Support;

final class StatusBadge
{
    /**
     * Resolve a workflow status into one consistent visual treatment.
     *
     * The exact status controls the icon while the tone groups statuses that
     * share a visual meaning. This keeps, for example, Approved and Completed
     * visually related without pretending that they are the same state.
     *
     * @return array{key: string, label: string, tone: string, icon: string}
     */
    public static function resolve(mixed $status, string $type = 'default'): array
    {
        $value = trim((string) ($status ?? ''));
        $key = self::normalize($value);
        $definition = self::definitions()[$key] ?? [];

        $label = $type === 'purchase' && $key === 'submitted'
            ? 'Under Review'
            : ($value !== '' ? $value : 'Unknown');

        return [
            'key' => $key !== '' ? $key : 'unknown',
            'label' => $label,
            'tone' => $definition['tone'] ?? 'neutral',
            'icon' => $definition['icon'] ?? 'fa-circle-question',
        ];
    }

    public static function normalize(mixed $status): string
    {
        return strtolower(trim((string) preg_replace(
            '/[^a-z0-9]+/i',
            '-',
            trim((string) ($status ?? ''))
        ), '-'));
    }

    /**
     * @return array<string, array{tone: string, icon: string}>
     */
    private static function definitions(): array
    {
        return [
            // Positive and available states.
            'active' => ['tone' => 'positive', 'icon' => 'fa-circle-check'],
            'present' => ['tone' => 'positive', 'icon' => 'fa-user-check'],
            'available' => ['tone' => 'positive', 'icon' => 'fa-circle-check'],
            'approved' => ['tone' => 'positive', 'icon' => 'fa-thumbs-up'],
            'allowed' => ['tone' => 'positive', 'icon' => 'fa-shield-halved'],
            'in-stock' => ['tone' => 'positive', 'icon' => 'fa-boxes-stacked'],
            'sufficient-stock' => ['tone' => 'positive', 'icon' => 'fa-boxes-stacked'],
            'ready' => ['tone' => 'positive', 'icon' => 'fa-circle-check'],
            'ready-to-issue' => ['tone' => 'positive', 'icon' => 'fa-box-open'],
            'received' => ['tone' => 'positive', 'icon' => 'fa-box-open'],
            'resolved' => ['tone' => 'positive', 'icon' => 'fa-circle-check'],
            'verified' => ['tone' => 'positive', 'icon' => 'fa-shield-halved'],
            'on-time' => ['tone' => 'positive', 'icon' => 'fa-clock'],
            'operational' => ['tone' => 'positive', 'icon' => 'fa-circle-check'],

            // Terminal success states.
            'schedule-ended' => ['tone' => 'neutral', 'icon' => 'fa-clock-rotate-left'],
            'scheduled-done' => ['tone' => 'positive', 'icon' => 'fa-calendar-check'],
            'partially-missed' => ['tone' => 'warning', 'icon' => 'fa-triangle-exclamation'],
            'completed' => ['tone' => 'success', 'icon' => 'fa-check-double'],
            'done' => ['tone' => 'success', 'icon' => 'fa-check-double'],
            'delivered' => ['tone' => 'success', 'icon' => 'fa-truck-ramp-box'],
            'picked-up' => ['tone' => 'success', 'icon' => 'fa-box-open'],
            'success' => ['tone' => 'success', 'icon' => 'fa-check-double'],
            'processed' => ['tone' => 'success', 'icon' => 'fa-check-double'],
            'fully-assigned' => ['tone' => 'success', 'icon' => 'fa-users-gear'],
            'issued' => ['tone' => 'success', 'icon' => 'fa-arrow-up-from-bracket'],
            'job-order-created' => ['tone' => 'success', 'icon' => 'fa-clipboard-check'],
            'replacement-bus-dispatched' => ['tone' => 'success', 'icon' => 'fa-bus'],

            // Work currently underway.
            'ongoing' => ['tone' => 'progress', 'icon' => 'fa-arrows-rotate'],
            'on-going' => ['tone' => 'progress', 'icon' => 'fa-arrows-rotate'],
            'on-duty' => ['tone' => 'progress', 'icon' => 'fa-user-gear'],
            'in-progress' => ['tone' => 'progress', 'icon' => 'fa-gears'],
            'processing' => ['tone' => 'progress', 'icon' => 'fa-gears'],
            'for-delivery' => ['tone' => 'progress', 'icon' => 'fa-truck'],
            'for-fuel-entry' => ['tone' => 'progress', 'icon' => 'fa-gas-pump'],
            'dispatched' => ['tone' => 'progress', 'icon' => 'fa-route'],
            'responding' => ['tone' => 'progress', 'icon' => 'fa-truck-medical'],
            'monitoring' => ['tone' => 'progress', 'icon' => 'fa-eye'],
            'on-trip' => ['tone' => 'progress', 'icon' => 'fa-bus'],
            'partial' => ['tone' => 'progress', 'icon' => 'fa-circle-half-stroke'],
            'partially-issued' => ['tone' => 'progress', 'icon' => 'fa-box-open'],
            'partially-received' => ['tone' => 'progress', 'icon' => 'fa-box-open'],
            'assigned' => ['tone' => 'progress', 'icon' => 'fa-user-check'],
            'investigate' => ['tone' => 'progress', 'icon' => 'fa-magnifying-glass-chart'],

            // Waiting, review, and procurement states.
            'pending' => ['tone' => 'pending', 'icon' => 'fa-clock'],
            'submitted' => ['tone' => 'pending', 'icon' => 'fa-file-circle-check'],
            'reported' => ['tone' => 'progress', 'icon' => 'fa-flag'],
            'scheduled' => ['tone' => 'scheduled', 'icon' => 'fa-calendar-check'],
            'upcoming' => ['tone' => 'scheduled', 'icon' => 'fa-calendar-day'],
            'ordered' => ['tone' => 'scheduled', 'icon' => 'fa-receipt'],
            'in-review' => ['tone' => 'pending', 'icon' => 'fa-magnifying-glass'],
            'review' => ['tone' => 'warning', 'icon' => 'fa-magnifying-glass'],
            'priority-review' => ['tone' => 'negative', 'icon' => 'fa-triangle-exclamation'],
            'for-purchase' => ['tone' => 'pending', 'icon' => 'fa-cart-shopping'],
            'needs-purchase' => ['tone' => 'pending', 'icon' => 'fa-cart-shopping'],
            'for-pick-up' => ['tone' => 'scheduled', 'icon' => 'fa-box'],
            'for-picking' => ['tone' => 'scheduled', 'icon' => 'fa-box'],
            'not-requested' => ['tone' => 'neutral', 'icon' => 'fa-minus'],
            'waiting-for-purchase' => ['tone' => 'pending', 'icon' => 'fa-cart-shopping'],
            'waiting-for-delivery' => ['tone' => 'pending', 'icon' => 'fa-truck'],
            'waiting-for-stock' => ['tone' => 'pending', 'icon' => 'fa-boxes-stacked'],
            'requested' => ['tone' => 'pending', 'icon' => 'fa-file-lines'],
            'due-soon' => ['tone' => 'warning', 'icon' => 'fa-triangle-exclamation'],
            'urgent-action' => ['tone' => 'warning', 'icon' => 'fa-triangle-exclamation'],
            'immediate-procurement' => ['tone' => 'warning', 'icon' => 'fa-cart-shopping'],
            'pending-approval' => ['tone' => 'pending', 'icon' => 'fa-clock'],
            'pending-calibration' => ['tone' => 'pending', 'icon' => 'fa-sliders'],
            'service-order-pending' => ['tone' => 'pending', 'icon' => 'fa-screwdriver-wrench'],
            'queued-for-lift' => ['tone' => 'pending', 'icon' => 'fa-clock'],
            'late' => ['tone' => 'warning', 'icon' => 'fa-clock'],
            'delayed' => ['tone' => 'warning', 'icon' => 'fa-clock'],
            'low-stock' => ['tone' => 'warning', 'icon' => 'fa-triangle-exclamation'],
            'on-hold' => ['tone' => 'warning', 'icon' => 'fa-circle-pause'],
            'hold' => ['tone' => 'warning', 'icon' => 'fa-circle-pause'],
            'paused' => ['tone' => 'warning', 'icon' => 'fa-circle-pause'],
            'on-leave' => ['tone' => 'warning', 'icon' => 'fa-calendar-minus'],

            // Maintenance has its own meaning and icon.
            'under-maintenance' => ['tone' => 'maintenance', 'icon' => 'fa-wrench'],
            'under-maintainance' => ['tone' => 'maintenance', 'icon' => 'fa-wrench'],
            'breakdown' => ['tone' => 'maintenance', 'icon' => 'fa-screwdriver-wrench'],

            // Negative and blocked states retain distinct icons.
            'inactive' => ['tone' => 'negative', 'icon' => 'fa-power-off'],
            'out-of-service' => ['tone' => 'negative', 'icon' => 'fa-power-off'],
            'absent' => ['tone' => 'negative', 'icon' => 'fa-user-xmark'],
            'rejected' => ['tone' => 'negative', 'icon' => 'fa-circle-xmark'],
            'for-review' => ['tone' => 'info', 'icon' => 'fa-magnifying-glass'],
            'revision-required' => ['tone' => 'warning', 'icon' => 'fa-rotate-left'],
            'failed' => ['tone' => 'negative', 'icon' => 'fa-triangle-exclamation'],
            'missing-gps' => ['tone' => 'negative', 'icon' => 'fa-location-crosshairs'],
            'cancelled' => ['tone' => 'negative', 'icon' => 'fa-ban'],
            'canceled' => ['tone' => 'negative', 'icon' => 'fa-ban'],
            'missed' => ['tone' => 'negative', 'icon' => 'fa-calendar-xmark'],
            'not-available' => ['tone' => 'negative', 'icon' => 'fa-circle-xmark'],
            'out-of-stock' => ['tone' => 'negative', 'icon' => 'fa-box-open'],
            'critical' => ['tone' => 'negative', 'icon' => 'fa-circle-exclamation'],
            'restricted' => ['tone' => 'negative', 'icon' => 'fa-lock'],
            'overdue' => ['tone' => 'negative', 'icon' => 'fa-circle-exclamation'],
            'needs-correction' => ['tone' => 'negative', 'icon' => 'fa-pen-to-square'],

            // Informational or intentionally neutral states.
            'draft' => ['tone' => 'neutral', 'icon' => 'fa-pen-ruler'],
            'no-attendance' => ['tone' => 'neutral', 'icon' => 'fa-user-clock'],
            'unknown' => ['tone' => 'neutral', 'icon' => 'fa-circle-question'],
            'unspecified' => ['tone' => 'neutral', 'icon' => 'fa-circle-question'],
            'protected' => ['tone' => 'neutral', 'icon' => 'fa-shield-halved'],
            'review-mode' => ['tone' => 'neutral', 'icon' => 'fa-eye'],
            'edit-mode-active' => ['tone' => 'progress', 'icon' => 'fa-pen-to-square'],
            'unassigned' => ['tone' => 'neutral', 'icon' => 'fa-user-slash'],
            'unavailable' => ['tone' => 'neutral', 'icon' => 'fa-minus'],
            'no-parts-needed' => ['tone' => 'neutral', 'icon' => 'fa-minus'],
            'no-parts-required' => ['tone' => 'neutral', 'icon' => 'fa-minus'],
            'deleted' => ['tone' => 'neutral', 'icon' => 'fa-trash-can'],
            'stable' => ['tone' => 'positive', 'icon' => 'fa-shield-halved'],
            'recommended' => ['tone' => 'positive', 'icon' => 'fa-circle-check'],
            'ready-to-deploy' => ['tone' => 'positive', 'icon' => 'fa-rocket'],
            'policy-ready' => ['tone' => 'positive', 'icon' => 'fa-shield-halved'],
            'training-assigned' => ['tone' => 'progress', 'icon' => 'fa-user-check'],
            'inspected' => ['tone' => 'success', 'icon' => 'fa-magnifying-glass'],
            'staged' => ['tone' => 'progress', 'icon' => 'fa-layer-group'],

            // Analytics states remain semantic without changing model logic.
            'efficient' => ['tone' => 'positive', 'icon' => 'fa-gauge-high'],
            'normal' => ['tone' => 'progress', 'icon' => 'fa-gauge'],
            'inefficient' => ['tone' => 'warning', 'icon' => 'fa-gauge-simple-low'],
        ];
    }
}
