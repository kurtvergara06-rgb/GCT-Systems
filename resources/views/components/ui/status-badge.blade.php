@props([
    'status' => '',
    'type' => 'default',
    'class' => '',
])

@php

    /*
    |--------------------------------------------------------------------------
    | ORIGINAL STATUS VALUE
    |--------------------------------------------------------------------------
    */

    $value = trim($status ?? '');


    /*
    |--------------------------------------------------------------------------
    | RAW STATUS KEY
    |--------------------------------------------------------------------------
    |
    | Examples:
    |
    | Approved      -> approved
    | For Purchase  -> for-purchase
    | For Pick-up   -> for-pick-up
    | On Going      -> on-going
    |
    */

    $statusKey = strtolower(
        str_replace(
            [' ', '/', '_'],
            ['-', '-', '-'],
            $value
        )
    );


    /*
    |--------------------------------------------------------------------------
    | GENERIC STATUS MAP
    |--------------------------------------------------------------------------
    |
    | Used by modules that depend on generic statuses such as:
    |
    | Active
    | Pending
    | Completed
    | Inactive
    |
    */

    $statusMap = [

        'active' =>
            'active',

        'present' =>
            'active',

        'available' =>
            'active',

        'approved' =>
            'active',

        'completed' =>
            'completed',

        'done' =>
            'completed',

        'ongoing' =>
            'ongoing',

        'on-going' =>
            'ongoing',

        'in-progress' =>
            'ongoing',

        'pending' =>
            'pending',

        'late' =>
            'pending',

        'on-hold' =>
            'pending',

        'upcoming' =>
            'upcoming',

        'due-soon' =>
            'due-soon',

        'overdue' =>
            'overdue',

        'efficient' =>
            'efficient',

        'normal' =>
            'normal',

        'inefficient' =>
            'inefficient',

        'under-maintenance' =>
            'under-maintenance',

        'under-maintainance' =>
            'under-maintenance',

        'inactive' =>
            'inactive',

        'absent' =>
            'inactive',

        'rejected' =>
            'inactive',

        'for-purchase' =>
            'pending',

        'for-pick-up' =>
            'pending',

        'ordered' =>
            'upcoming',

        'delivered' =>
            'completed',

        'issued' =>
            'active',

        'submitted' =>
            'pending',

    ];


    /*
    |--------------------------------------------------------------------------
    | PURCHASE STATUS
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | Purchase Request must keep its REAL status class.
    |
    | Approved     -> approved
    | Rejected     -> rejected
    | Submitted    -> submitted
    | For Purchase -> for-purchase
    |
    | This allows purchase-requests.css to control each status independently.
    |
    */

    if ($type === 'purchase') {

        $statusClass =
            $statusKey;

    } else {

        $statusClass =
            $statusMap[$statusKey]
            ?? $statusKey;

    }


    /*
    |--------------------------------------------------------------------------
    | USER MANAGEMENT
    |--------------------------------------------------------------------------
    */

    if ($type === 'user') {

        $userStatuses = [
            'active',
            'inactive',
            'pending',
        ];


        if (
            in_array(
                $statusClass,
                $userStatuses,
                true
            )
        ) {

            $badgeClass =
                'status-pill '
                . $statusClass;

        } else {

            $badgeClass =
                'role-pill '
                . $statusClass;

        }


        if ($class) {

            $badgeClass .=
                ' '
                . $class;

        }

    } else {

        /*
        |--------------------------------------------------------------------------
        | OTHER MODULES
        |--------------------------------------------------------------------------
        */

        $badgeClass =
            'badge';


        if (
            $type !==
            'default'
        ) {

            $badgeClass .=
                ' '
                . $type
                . '-badge';

        }


        if ($statusClass) {

            $badgeClass .=
                ' '
                . $statusClass;

        }


        if ($class) {

            $badgeClass .=
                ' '
                . $class;

        }

    }

@endphp


<span
    class="{{ $badgeClass }}"
    {{ $attributes }}
>
    {{ $value ?: 'Unknown' }}
</span>