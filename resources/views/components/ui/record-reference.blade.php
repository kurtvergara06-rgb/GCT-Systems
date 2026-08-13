@props([
    'primary' => null,
    'secondary' => null,
    'icon' => null,
    'size' => 'small',
])

<div {{ $attributes->class('record-reference') }}>
    <x-ui.id-badge :value="$primary" :size="$size" />

    @if(filled($secondary))
        <span class="record-reference__secondary" title="{{ $secondary }}">
            @if($icon)
                <i class="fa-solid {{ $icon }}"></i>
            @endif
            {{ $secondary }}
        </span>
    @endif
</div>
