@props([
    'title',
    'description' => null,
    'badge' => null,
])

<article {{ $attributes->class(['analytics-card', 'analytics-domain-card']) }}>
    <x-analytics.card-header
        :title="$title"
        :description="$description"
        :badge="$badge"
    />

    {{ $slot }}
</article>
