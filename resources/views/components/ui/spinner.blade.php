@props([
    'size' => 'md',
    'label' => null,
])

@php
    $sizeClass = match ($size) {
        'sm', 'small' => 'gct-spinner-sm',
        'lg', 'large' => 'gct-spinner-lg',
        default => 'gct-spinner-md',
    };
@endphp

<span
    {{ $attributes->merge([
        'class' => "gct-spinner {$sizeClass}",
    ]) }}
    @if($label)
        role="status"
        aria-label="{{ $label }}"
    @else
        aria-hidden="true"
    @endif
>
    <span class="gct-spinner-ring"></span>

    @if($label)
        <span class="gct-spinner-label">{{ $label }}</span>
    @endif
</span>
