@props([
    'value' => null,
    'size' => null,
    'tone' => 'blue',
])

@php
    $sizeClass = match ($size) {
        'small', 'sm' => 'system-id-badge--small',
        'large', 'lg' => 'system-id-badge--large',
        default => null,
    };

    $toneClass = $tone === 'neutral'
        ? 'system-id-badge--neutral'
        : null;
@endphp

@if(filled($value))
    <span
        {{ $attributes->class([
            'system-id-badge',
            $sizeClass,
            $toneClass,
        ]) }}
        title="{{ $value }}"
    >
        {{ $value }}
    </span>
@else
    <span class="system-id-empty">—</span>
@endif
