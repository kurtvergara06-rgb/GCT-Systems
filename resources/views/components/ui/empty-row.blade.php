@props([
    'colspan' => 1,
    'message' => null,
    'title' => null,
    'description' => null,
    'icon' => null,
])

@php
    $displayTitle = $title ?? $message ?? 'No Records Found';
    $rawIcon = $icon ?? 'fa-file-lines';
    $iconClass = str_starts_with($rawIcon, 'fa-') ? $rawIcon : 'fa-' . $rawIcon;
    $resolvedDescription = $description;
    if ($description === null) {
        $resolvedDescription = 'No records matched your search or filter criteria. Try adjusting your filters or search terms.';
    }
@endphp

<tr {{ $attributes->merge(['class' => 'gct-empty-table-row']) }}>
    <td colspan="{{ $colspan }}" class="empty-row gct-empty-cell" style="text-align: center; padding: 48px 20px; background: transparent; border-bottom: 0;">
        <div class="gct-empty-content" role="status" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; margin: 0 auto; max-width: 440px; text-align: center;">
            <i class="fa-solid {{ $iconClass }} gct-empty-icon" style="font-size: 32px; color: #cbd5e1; line-height: 1;" aria-hidden="true"></i>
            <strong class="gct-empty-title" style="font-size: 15px; font-weight: 700; color: #0b2344;">{{ $displayTitle }}</strong>
            @if($resolvedDescription)
                <p class="gct-empty-description" style="font-size: 13px; margin: 0; color: #64748b; line-height: 1.5; max-width: 420px;">{{ $resolvedDescription }}</p>
            @endif
        </div>
    </td>
</tr>
