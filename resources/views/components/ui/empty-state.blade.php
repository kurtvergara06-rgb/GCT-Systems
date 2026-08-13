@props([
    'icon' => 'fa-inbox',
    'title' => 'No records found',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'ui-empty-state']) }}>
    <div class="ui-empty-state-icon">
        <i class="fa-solid {{ $icon }}"></i>
    </div>

    <h3>{{ $title }}</h3>

    @if($description)
        <p>{{ $description }}</p>
    @endif

    {{ $slot }}
</div>
