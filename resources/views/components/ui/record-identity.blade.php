@props([
    'title',
    'subtitle' => null,
    'initials' => null,
    'tone' => 'blue',
])

@php
    $resolvedInitials = $initials;

    if (! $resolvedInitials) {
        $parts = collect(preg_split('/\s+/', trim((string) $title)))
            ->filter()
            ->values();

        $resolvedInitials = strtoupper(
            substr($parts->get(0, ''), 0, 1)
            . substr($parts->get(1, ''), 0, 1)
        ) ?: 'U';
    }
@endphp

<div {{ $attributes->merge(['class' => 'record-identity']) }} data-ui-component="record-identity">
    <div class="record-avatar record-avatar--{{ $tone }}" aria-hidden="true">
        {{ $resolvedInitials }}
    </div>

    <div class="record-identity__text">
        <strong>{{ $title }}</strong>
        @if($subtitle)
            <span>{{ $subtitle }}</span>
        @endif
    </div>
</div>
