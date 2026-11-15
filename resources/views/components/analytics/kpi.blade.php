@props([
    'label',
    'value',
    'small' => null,
    'description' => null,
    'status' => null,
    'statusTone' => null,
    'icon',
    'tone' => null,
])

@php
    $descriptionText = filled($description) ? $description : $small;
@endphp

<article {{ $attributes->class(['analytics-kpi']) }}>
    <div class="analytics-kpi-icon{{ filled($tone) ? ' ' . $tone : '' }}">
        <i class="fa-solid {{ $icon }}"></i>
    </div>

    <div class="analytics-kpi-copy">
        <span class="analytics-kpi-label">{{ $label }}</span>
        <strong class="analytics-kpi-value">{{ $value }}</strong>
        @if(filled($descriptionText))
            <small class="analytics-kpi-description">{{ $descriptionText }}</small>
        @endif
        @if(filled($status))
            <small class="analytics-kpi-status{{ filled($statusTone) ? ' ' . $statusTone : '' }}">{{ $status }}</small>
        @endif
    </div>
</article>
