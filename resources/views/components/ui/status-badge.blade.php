@props([
    'status' => '',
    'type' => 'default',
    'class' => '',
])

@php
    $value = trim((string) ($status ?? ''));
    $statusKey = strtolower(str_replace([' ', '/', '_'], '-', $value));

    /*
     * Shared visual aliases keep status colors consistent across modules.
     * Purchase statuses now use the same mapped visual classes instead of
     * falling back to unstyled raw status names.
     */
    $statusMap = [
        'active' => 'active',
        'present' => 'active',
        'available' => 'active',
        'approved' => 'active',
        'issued' => 'active',
        'in-stock' => 'active',
        'allowed' => 'active',

        'completed' => 'completed',
        'done' => 'completed',
        'delivered' => 'completed',
        'picked-up' => 'completed',
        'success' => 'completed',

        'ongoing' => 'ongoing',
        'on-going' => 'ongoing',
        'on-duty' => 'ongoing',
        'in-progress' => 'ongoing',
        'processing' => 'ongoing',
        'for-delivery' => 'ongoing',

        'pending' => 'pending',
        'submitted' => 'pending',
        'late' => 'pending',
        'on-hold' => 'pending',
        'hold' => 'pending',
        'on-leave' => 'pending',
        'in-review' => 'pending',
        'for-purchase' => 'pending',
        'for-pick-up' => 'pending',
        'not-requested' => 'pending',

        'upcoming' => 'upcoming',
        'ordered' => 'upcoming',
        'due-soon' => 'due-soon',
        'overdue' => 'overdue',

        'efficient' => 'efficient',
        'normal' => 'normal',
        'inefficient' => 'inefficient',
        'under-maintenance' => 'under-maintenance',
        'under-maintainance' => 'under-maintenance',

        'inactive' => 'inactive',
        'absent' => 'inactive',
        'rejected' => 'inactive',
        'failed' => 'inactive',
        'cancelled' => 'inactive',
        'canceled' => 'inactive',
        'not-available' => 'inactive',
        'restricted' => 'inactive',

        'draft' => 'draft',
        'no-attendance' => 'draft',
        'unknown' => 'draft',
        'protected' => 'draft',
        'review-mode' => 'draft',
        'edit-mode-active' => 'ongoing',
    ];

    $statusClass = $statusMap[$statusKey] ?? ($statusKey ?: 'draft');

    if ($type === 'user') {
        $userStatuses = ['active', 'inactive', 'pending'];
        $badgeClass = in_array($statusClass, $userStatuses, true)
            ? 'status-pill ' . $statusClass
            : 'role-pill ' . $statusClass;
    } else {
        $badgeClass = 'badge';

        if ($type !== 'default') {
            $badgeClass .= ' ' . $type . '-badge';
        }

        if ($statusClass) {
            $badgeClass .= ' ' . $statusClass;
        }
    }

    if ($class) {
        $badgeClass .= ' ' . $class;
    }
@endphp

<span {{ $attributes->merge([
    'class' => $badgeClass,
    'data-ui-component' => 'status-badge',
    'data-status' => $statusClass,
]) }}>
    {{ $value ?: 'Unknown' }}
</span>
