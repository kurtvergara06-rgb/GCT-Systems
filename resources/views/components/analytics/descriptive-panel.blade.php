@props([
    'eyebrow' => '',
    'title',
    'description' => '',
    'icon' => 'fa-chart-column',
])

<article {{ $attributes->class(['descriptive-dashboard-card']) }}>
    <div class="dashboard-card-header">
        <div>
            @if($eyebrow)
                <span class="dashboard-eyebrow">{{ $eyebrow }}</span>
            @endif
            <h2><i class="fa-solid {{ $icon }}" aria-hidden="true"></i> {{ $title }}</h2>
            @if($description)
                <p>{{ $description }}</p>
            @endif
        </div>
        @isset($actions)
            {{ $actions }}
        @endisset
    </div>
    {{ $slot }}
</article>
