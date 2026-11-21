@props([
    'items' => [],
    'label' => 'Record views',
    'class' => '',
])

<nav {{ $attributes->class(['gct-record-tabs', $class]) }} aria-label="{{ $label }}">
    @foreach($items as $item)
        @php($selected = (bool) ($item['active'] ?? false))
        <a
            href="{{ $item['url'] }}"
            class="gct-record-tab {{ $selected ? 'is-active' : '' }}"
            @if($selected) aria-current="page" @endif
            @if($item['partial'] ?? false) data-allow-partial-navigation="true" @endif
        >
            @if(!empty($item['icon']))
                <i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i>
            @endif
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
