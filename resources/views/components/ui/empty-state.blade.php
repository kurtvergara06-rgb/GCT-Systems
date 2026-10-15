@props([
    'icon' => 'fa-inbox',
    'title' => 'No records found',
    'description' => null,
])

@php
    $rawIcon = $icon ?? 'fa-inbox';
    $iconClass = str_starts_with($rawIcon, 'fa-') ? $rawIcon : 'fa-' . $rawIcon;
    $resolvedDescription = $description;
    if ($description === null) {
        $resolvedDescription = 'No records matched your search or filter criteria. Try adjusting your filters or search terms.';
    }
@endphp

<div {{ $attributes->merge(['class' => 'ui-empty-state gct-empty-content']) }} style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; padding: 48px 20px; margin: 0 auto; max-width: 440px; text-align: center;">
    <div class="ui-empty-state-icon" style="line-height: 1;">
        <i class="fa-solid {{ $iconClass }} gct-empty-icon" style="font-size: 32px; color: #cbd5e1;" aria-hidden="true"></i>
    </div>

    <strong class="gct-empty-title" style="font-size: 15px; font-weight: 700; color: #0b2344;">{{ $title }}</strong>

    @if($resolvedDescription)
        <p class="gct-empty-description" style="font-size: 13px; margin: 0; color: #64748b; line-height: 1.5; max-width: 420px;">{{ $resolvedDescription }}</p>
    @endif

    {{ $slot }}
</div>
