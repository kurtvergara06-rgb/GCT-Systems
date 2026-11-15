@props([
    'status' => '',
    'type' => 'default',
    'class' => '',
    'icon' => null,
    'label' => null,
    'showIcon' => true,
])

@php
    $presentation = \App\Support\StatusBadge::resolve($status, $type);
    $statusKey = $presentation['key'];
    $statusTone = $presentation['tone'];
    $statusIcon = $icon ?: $presentation['icon'];
    $displayValue = $label ?: $presentation['label'];
    $renderIcon = $showIcon;
    $statusData = $statusTone;

    if ($type === 'user') {
        $userStatuses = ['active', 'inactive', 'pending'];
        $isUserStatus = in_array($statusKey, $userStatuses, true);
        $badgeClass = ($isUserStatus ? 'status-pill ' : 'role-pill ') . $statusKey;
        $renderIcon = $isUserStatus && $showIcon;
        $statusData = $isUserStatus ? $statusTone : $statusKey;
    } else {
        $badgeClass = 'badge';

        if ($type !== 'default') {
            $badgeClass .= ' ' . $type . '-badge';
        }

        if ($statusKey) {
            $badgeClass .= ' ' . $statusKey;
        }
    }

    if ($class) {
        $badgeClass .= ' ' . $class;
    }
@endphp

<span {{ $attributes->merge([
    'class' => $badgeClass,
    'data-ui-component' => 'status-badge',
    'data-status' => $statusData,
    'data-status-key' => $statusKey,
]) }}>
    @if($renderIcon)
        <i class="fa-solid {{ $statusIcon }} status-badge__icon" aria-hidden="true"></i>
    @endif
    <span class="status-badge__label">{{ $displayValue }}</span>
</span>
