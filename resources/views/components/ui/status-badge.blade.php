@props([
    'status' => '',
    'type' => 'default',
    'class' => '',
])

@php
    $value = trim((string) ($status ?? ''));
    $statusKey = strtolower(str_replace([' ', '/', '_'], '-', $value));

    /*
     * Generic visual aliases keep status colors consistent across modules.
     * Purchase badges preserve their real workflow class because Purchase CSS
     * styles individual states such as approved, rejected and for-purchase.
     */
    $statusMap = [
        'active' => 'active',
        'present' => 'active',
        'available' => 'active',
        'approved' => 'active',
        'issued' => 'active',
        'in-stock' => 'active',

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

        'pending' => 'pending',
        'submitted' => 'pending',
        'late' => 'pending',
        'on-hold' => 'pending',
        'hold' => 'pending',
        'on-leave' => 'pending',
        'in-review' => 'pending',
        'for-purchase' => 'pending',
        'for-pick-up' => 'pending',
        'for-delivery' => 'pending',
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

        'draft' => 'draft',
        'no-attendance' => 'draft',
        'unknown' => 'draft',
    ];

    $statusClass = $type === 'purchase'
        ? $statusKey
        : ($statusMap[$statusKey] ?? ($statusKey ?: 'draft'));

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

<span class="{{ $badgeClass }}" {{ $attributes }}>
    {{ $value ?: 'Unknown' }}
</span>
