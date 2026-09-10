@props([
    'label',
    'value',
    'small' => null,
    'description' => null,
    'status' => null,
    'statusTone' => null,
    'icon',
    'tone' => null,
    'trend' => null,
    'trendTone' => null,
    'iconVariant' => null,
    'change' => null,
    'changeType' => 'neutral',
])

@php
    $useCardVariant = filled($iconVariant) || filled($change);

    if ($useCardVariant) {
        $iconVariants = [
            'blue'   => ['bg' => 'var(--ds-blue-soft)',   'color' => 'var(--ds-blue)'],
            'green'  => ['bg' => 'var(--ds-green-soft)',  'color' => 'var(--ds-green)'],
            'yellow' => ['bg' => 'var(--ds-yellow-soft)', 'color' => 'var(--ds-yellow-dark)'],
            'red'    => ['bg' => 'var(--ds-red-soft)',    'color' => 'var(--ds-red)'],
            'purple' => ['bg' => 'var(--ds-purple-soft)', 'color' => 'var(--ds-purple)'],
        ];
        $variant = $iconVariants[$iconVariant] ?? $iconVariants['blue'];

        $changeTypes = [
            'positive' => 'var(--ds-green)',
            'negative' => 'var(--ds-red)',
            'warning'  => 'var(--ds-yellow-dark)',
            'info'     => 'var(--ds-blue)',
            'neutral'  => 'var(--ds-muted)',
        ];
        $changeColor = $changeTypes[$changeType] ?? $changeTypes['neutral'];
    } else {
        $descriptionText = filled($description) ? $description : $small;
        $statusText = filled($status) ? $status : $trend;
        $statusToneClass = filled($statusTone) ? $statusTone : $trendTone;
    }
@endphp

@if($useCardVariant)
    <article
        {{ $attributes->class(['analytics-kpi-card']) }}
        style="--icon-bg: {{ $variant['bg'] }}; --icon-color: {{ $variant['color'] }}; --change-color: {{ $changeColor }};"
    >
        <div class="analytics-kpi-card__icon" aria-hidden="true">
            <i class="fa-solid {{ $icon }}"></i>
        </div>

        <div class="analytics-kpi-card__content">
            <span class="analytics-kpi-card__label">{{ $label }}</span>
            <strong class="analytics-kpi-card__value">{{ $value }}</strong>
            @if(filled($description))
                <span class="analytics-kpi-card__description">{{ $description }}</span>
            @endif
            @if(filled($change))
                <span class="analytics-kpi-card__change">{{ $change }}</span>
            @endif
        </div>
    </article>
@else
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
            @if(filled($statusText))
                <small class="analytics-kpi-status{{ filled($statusToneClass) ? ' ' . $statusToneClass : '' }}">{{ $statusText }}</small>
            @endif
        </div>
    </article>
@endif