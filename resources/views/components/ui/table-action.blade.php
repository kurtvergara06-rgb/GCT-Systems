@props([
    'variant' => 'view',
    'icon' => null,
    'label' => null,
    'disabled' => false,
    'href' => null,
])

@php
    $variant = in_array($variant, ['view', 'edit', 'delete'], true) ? $variant : 'view';
    $icon = $icon ?: match ($variant) {
        'edit' => 'fa-pen-to-square',
        'delete' => 'fa-trash',
        default => 'fa-eye',
    };
    $label = $label ?: ucfirst($variant);
    $classes = 'table-action-btn '.$variant;
@endphp

@if($href && ! $disabled)
    <a href="{{ $href }}" {{ $attributes->class([$classes])->merge(['title' => $label, 'aria-label' => $label]) }}>
        <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
    </a>
@else
    <button type="button" {{ $attributes->class([$classes])->merge(['title' => $label, 'aria-label' => $label]) }} @disabled($disabled)>
        <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
    </button>
@endif
