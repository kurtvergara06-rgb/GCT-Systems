@props([
    'variant' => 'view',
    'icon' => null,
    'label' => null,
    'showLabel' => false,
    'iconOnly' => null,
    'disabled' => false,
    'href' => null,
    'buttonType' => 'button',
])

@php
    $variant = strtolower(str_replace('_', '-', trim((string) $variant)));
    $variants = [
        'view', 'edit', 'delete', 'review', 'approve', 'reject',
        'submit', 'receive', 'create-po', 'close',
    ];
    $variant = in_array($variant, $variants, true) ? $variant : 'view';
    $icon = $icon ?: match ($variant) {
        'edit' => 'fa-pen-to-square',
        'delete' => 'fa-trash',
        'review' => 'fa-clipboard-check',
        'approve' => 'fa-check',
        'reject' => 'fa-xmark',
        'submit' => 'fa-paper-plane',
        'receive' => 'fa-box-open',
        'create-po' => 'fa-cart-shopping',
        'close' => 'fa-xmark',
        default => 'fa-eye',
    };
    $label = $label ?: match ($variant) {
        'create-po' => 'Create PO',
        default => str($variant)->replace('-', ' ')->title()->toString(),
    };
    $renderLabel = $iconOnly === null ? (bool) $showLabel : ! (bool) $iconOnly;
    $classes = 'table-action-btn '.$variant.' '.($renderLabel
        ? 'table-action-btn--labeled'
        : 'table-action-btn--icon');
    $commonAttributes = [
        'title' => $label,
        'aria-label' => $label,
        'data-ui-component' => 'table-action',
        'data-action' => $variant,
    ];
@endphp

@if($href && ! $disabled)
    <a href="{{ $href }}" {{ $attributes->class([$classes])->merge($commonAttributes) }}>
        <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
        @if($renderLabel)<span>{{ $label }}</span>@endif
    </a>
@else
    <button type="{{ $buttonType }}" {{ $attributes->class([$classes])->merge($commonAttributes) }} @disabled($disabled)>
        <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
        @if($renderLabel)<span>{{ $label }}</span>@endif
    </button>
@endif
