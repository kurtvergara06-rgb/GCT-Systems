@props([
    'labelledBy',
    'describedBy' => null,
    'panelClass' => '',
    'native' => false,
])

@if($native)
    <dialog {{ $attributes }} aria-labelledby="{{ $labelledBy }}" @if($describedBy) aria-describedby="{{ $describedBy }}" @endif>
        {{ $slot }}
    </dialog>
@else
    <div {{ $attributes }} role="presentation">
        <div class="{{ $panelClass }}" role="dialog" aria-modal="true" aria-labelledby="{{ $labelledBy }}" @if($describedBy) aria-describedby="{{ $describedBy }}" @endif>
            {{ $slot }}
        </div>
    </div>
@endif
