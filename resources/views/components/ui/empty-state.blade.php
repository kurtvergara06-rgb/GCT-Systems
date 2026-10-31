@props([
    'icon' => 'fa-inbox',
    'title' => 'No records found',
    'description' => null,
])

@php
    $rawIcon = $icon ?? 'fa-inbox';
    $iconClass = str_starts_with($rawIcon, 'fa-') ? $rawIcon : 'fa-' . $rawIcon;
    $resolvedDescription = $description;
    if ($description === null) {
        $resolvedDescription = 'No records matched your search or filter criteria. Try adjusting your filters or search terms.';
    }
@endphp

<div {{ $attributes->merge(['class' => 'ui-empty-state gct-empty-content']) }}>
    <div class="ui-empty-state-icon">
        <i class="fa-solid {{ $iconClass }} gct-empty-icon" aria-hidden="true"></i>
    </div>

    <strong class="gct-empty-title">{{ $title }}</strong>

    @if($resolvedDescription)
        <p class="gct-empty-description">{{ $resolvedDescription }}</p>
    @endif

    {{ $slot }}
</div>
