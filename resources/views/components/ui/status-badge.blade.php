@props([
    'status' => '',
    'type' => 'default',
    'class' => '',
])

@php
    $value = trim($status ?? '');

    $statusClass = strtolower(
        str_replace(
            [' ', '/', '_'],
            ['-', '-', '-'],
            $value
        )
    );

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