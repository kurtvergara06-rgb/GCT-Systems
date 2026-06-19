@props([
  'status' => '',
  'type' => 'default', // purchase, inventory, user, job, default
  'class' => '',
])

@php
  /**
   * Status Badge Component - Reusable status display badge
   * 
   * Automatically converts status strings to CSS classes:
   * "Submitted" → "submitted"
   * "For Purchase" → "for-purchase"
   * "Picked Up" → "picked-up"
   * etc.
   */
  
  // Convert status to CSS class
  $statusClass = strtolower(str_replace([' ', '/'], ['-', '-'], $status ?? ''));
  
  // Build badge class
  $badgeClass = 'badge';
  
  // Add type-specific class
  if ($type !== 'default') {
    $badgeClass .= ' ' . $type . '-badge';
  }
  
  // Add status-specific class
  if ($statusClass) {
    $badgeClass .= ' ' . $statusClass;
  }
  
  // Add custom classes
  if ($class) {
    $badgeClass .= ' ' . $class;
  }
@endphp

<span class="{{ $badgeClass }}" {{ $attributes }}>
  {{ $status ?: 'Unknown' }}
</span>
