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
    <td colspan="{{ $colspan }}" class="empty-row gct-empty-cell">
        <div class="gct-empty-content" role="status">
            <i class="fa-solid {{ $iconClass }} gct-empty-icon" aria-hidden="true"></i>
            <strong class="gct-empty-title">{{ $displayTitle }}</strong>
            @if($resolvedDescription)
                <p class="gct-empty-description">{{ $resolvedDescription }}</p>
            @endif
        </div>
    </td>
</tr>
