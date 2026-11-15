@props([
  'type' => 'view', // view, edit, delete, create-po, issue, send, approve, reject, reset, activate, deactivate, status
  'title' => '',
  'class' => '',
  'buttonType' => 'button',
  'disabled' => false,
  'href' => null,
])

@php
  $iconMap = [
    'view' => 'fa-eye',
    'edit' => 'fa-pen-to-square',
    'delete' => 'fa-trash-can',
    'create-po' => 'fa-cart-plus',
    'issue' => 'fa-box-open',
    'send' => 'fa-cart-shopping',
    'approve' => 'fa-check',
    'reject' => 'fa-xmark',
    'reset' => 'fa-key',
    'activate' => 'fa-user-check',
    'deactivate' => 'fa-user-slash',
    'status' => 'fa-arrow-right-arrow-left',
  ];

  $icon = $iconMap[$type] ?? 'fa-circle';
  $buttonClass = 'action-btn gct-record-action ' . $type . ($disabled ? ' gct-action-unavailable' : '');

  if ($class) {
    $buttonClass .= ' ' . $class;
  }

  $titleAttr = $title ? ' title="' . e($title) . '"' : '';
  $disabledAttr = $disabled ? ' disabled' : '';
  $currentStatus = (string) $attributes->get('data-current-status', '');
  $shouldRender = ! ($type === 'status' && $currentStatus !== '' && $currentStatus !== 'Ordered');
@endphp

@if($shouldRender)
  @if($href && ! $disabled)
    <a
      href="{{ $href }}"
      class="{{ $buttonClass }}"
      data-ui-component="action-button"
      data-action="{{ $type }}"
      {{ $titleAttr }}
      {{ $attributes }}
    >
      <i class="fa-solid {{ $icon }}"></i>
    </a>
  @else
    <button
      type="{{ $buttonType }}"
      class="{{ $buttonClass }}"
      data-ui-component="action-button"
      data-action="{{ $type }}"
      {{ $titleAttr }}
      {{ $disabledAttr }}
      @if($disabled) aria-disabled="true" @endif
      {{ $attributes }}
    >
      <i class="fa-solid {{ $icon }}"></i>
    </button>
  @endif
@endif
