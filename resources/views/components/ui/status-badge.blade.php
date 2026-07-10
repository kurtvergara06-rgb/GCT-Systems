@props([
    'status' => '',
    'type' => 'default',
    'class' => '',
])

@php
    $value = trim($status ?? '');

    $statusKey = strtolower(
        str_replace(
            [' ', '/', '_'],
            ['-', '-', '-'],
            $value
        )
    );

    $statusMap = [
        'active' => 'active',
        'present' => 'active',
        'available' => 'active',
        'approved' => 'active',
        'completed' => 'completed',
        'done' => 'completed',
        'ongoing' => 'ongoing',
        'on-going' => 'ongoing',
        'in-progress' => 'ongoing',
        'pending' => 'pending',
        'late' => 'pending',
        'on-hold' => 'pending',
        'upcoming' => 'upcoming',
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
        'for-purchase' => 'pending',
        'for-pick-up' => 'pending',
        'ordered' => 'upcoming',
        'delivered' => 'completed',
        'issued' => 'active',
        'submitted' => 'pending',
    ];

    $statusClass = $statusMap[$statusKey] ?? $statusKey;

    /*
    |--------------------------------------------------------------------------
    | User Management Badges
    |--------------------------------------------------------------------------
    | Role examples:
    | System Admin       -> system-admin
    | Maintenance Head   -> maintenance-head
    | Purchase Staff     -> purchase-staff
    |
    | Status examples:
    | Active             -> active
    | Inactive           -> inactive
    | Pending            -> pending
    */
    if ($type === 'user') {
        $userStatuses = ['active', 'inactive', 'pending'];

        if (in_array($statusClass, $userStatuses)) {
            $badgeClass = 'status-pill ' . $statusClass;
        } else {
            $badgeClass = 'role-pill ' . $statusClass;
        }

        if ($class) {
            $badgeClass .= ' ' . $class;
        }
    } else {
        /*
        |--------------------------------------------------------------------------
        | Other Modules: Purchase, Inventory, Job Orders, etc.
        |--------------------------------------------------------------------------
        */
        $badgeClass = 'badge';

        if ($type !== 'default') {
            $badgeClass .= ' ' . $type . '-badge';
        }

        if ($statusClass) {
            $badgeClass .= ' ' . $statusClass;
        }

        if ($class) {
            $badgeClass .= ' ' . $class;
        }
    }
@endphp

<span class="{{ $badgeClass }}" {{ $attributes }}>
    {{ $value ?: 'Unknown' }}
</span>