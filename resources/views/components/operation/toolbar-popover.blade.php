@props([
    'type',
    'label',
    'icon',
    'popoverId',
    'active' => false,
    'count' => 0,
])

<div class="bus-tool" data-bus-tool="{{ $type }}">
    <button type="button"
        class="bus-tool-trigger bus-tool-trigger--{{ $type }} {{ $active ? 'bus-tool-active' : '' }}"
        data-bus-tool-trigger="{{ $type }}"
        aria-expanded="false"
        aria-controls="{{ $popoverId }}">
        <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
        <span>{{ $label }}</span>
        @if($type === 'filter')
            <span class="bus-filter-count" data-bus-filter-count @if((int) $count < 1) hidden @endif>{{ (int) $count }}</span>
        @endif
    </button>
    {{ $slot }}
</div>
