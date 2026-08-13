@props([
    'title',
    'description' => null,
    'badge' => null,
])

<div {{ $attributes->class(['analytics-card-header']) }}>
    <div>
        <h3>{{ $title }}</h3>
        @if(filled($description))
            <p>{{ $description }}</p>
        @endif
    </div>

    @if(filled($badge))
        <span class="analytics-card-badge">{{ $badge }}</span>
    @endif
</div>
