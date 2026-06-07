@props([
  'status' => '',
])

@php
  $badgeClass = match($status) {
    // Job Order Statuses
    'On Hold' => 'hold',
    'On Going' => 'ongoing',
    'Completed' => 'completed',
    'Urgent Repair' => 'urgent',

    // Purchase Request Statuses
    'Draft' => 'draft',
    'Submitted' => 'submitted',
    'Approved' => 'approved',
    'Rejected' => 'rejected',
    'For Purchase' => 'for-purchase',
    'Pending Purchase' => 'pending-purchase',
    'Delivering' => 'delivering',
    'Delivered' => 'delivered',
    'Issued' => 'issued',

    // Part Statuses (for Job Orders)
    'Not Requested' => 'not-requested',

    // Inventory Statuses
    'In Stock' => 'in-stock',
    'Low Stock' => 'low-stock',
    'Critical' => 'critical',
    'Out of Stock' => 'out-of-stock',

    default => 'draft',
  };
@endphp

<span class="badge {{ $badgeClass }}">
  {{ $status ?: 'Unknown' }}
</span>