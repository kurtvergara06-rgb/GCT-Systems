@props([
    'title',
    'subtitle' => null,
    'icon' => null,
    'class' => '',
])

<div
    {{ $attributes->merge([
        'class' => trim('section-header ' . $class),
    ]) }}
>
    <div class="section-header-content">
        @if($icon)
            <div class="section-header-icon">
                <i
                    class="fa-solid {{ $icon }}"
                    aria-hidden="true"
                ></i>
            </div>
        @endif

        <div class="section-header-text">
            <h2>{{ $title }}</h2>

            @if($subtitle)
                <p>{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    @isset($actions)
        <div class="section-header-actions">
            {{ $actions }}
        </div>
    @endisset
</div>