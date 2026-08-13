@props([
    'label',
    'value',
    'small' => null,
    'icon',
    'tone' => null,
])

<article {{ $attributes->class(['analytics-kpi']) }}>
    <div class="analytics-kpi-icon{{ filled($tone) ? ' ' . $tone : '' }}">
        <i class="fa-solid {{ $icon }}"></i>
    </div>

    <div>
        <span>{{ $label }}</span>
        <strong>{{ $value }}</strong>
        @if(filled($small))
            <small>{{ $small }}</small>
        @endif
    </div>
</article>
