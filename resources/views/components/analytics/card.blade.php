@props([
    'title' => null,
    'description' => null,
    'badge' => null,
    'headerActions' => null,
])

<article {{ $attributes->class(['analytics-card']) }}>
    @if(filled($title) || filled($description) || filled($badge) || filled($headerActions))
        <div class="analytics-card__header">
            <div class="analytics-card__header-main">
                @if(filled($title))
                    <h3 class="analytics-card__title">{{ $title }}</h3>
                @endif
                @if(filled($description))
                    <p class="analytics-card__description">{{ $description }}</p>
                @endif
            </div>
            <div class="analytics-card__header-actions">
                @if(filled($badge))
                    <span class="analytics-card__badge">{{ $badge }}</span>
                @endif
                {!! $headerActions !!}
            </div>
        </div>
    @endif

    <div class="analytics-card__body">
        {{ $slot }}
    </div>
</article>