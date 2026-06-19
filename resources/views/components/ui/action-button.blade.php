@props([
  'type' => 'view', // view, edit, delete, create-po, issue, send, approve, reject, reset, activate, deactivate
  'title' => '',
  'class' => '',
  'buttonType' => 'button',
  'disabled' => false,
  'href' => null,
])

@php
  /**
   * Action Button Component - Reusable action button with Font Awesome icons
   * 
   * Icon mapping:
   * view → fa-eye
   * edit → fa-pen-to-square
   * delete → fa-trash
   * create-po → fa-cart-plus
   * issue → fa-box-open
   * send → fa-cart-shopping
   * approve → fa-check
   * reject → fa-xmark
   * reset → fa-key
   * activate → fa-user-check
   * deactivate → fa-user-slash
   */
  
  $iconMap = [
    'view' => 'fa-eye',
    'edit' => 'fa-pen-to-square',
    'delete' => 'fa-trash',
    'create-po' => 'fa-cart-plus',
    'issue' => 'fa-box-open',
    'send' => 'fa-cart-shopping',
    'approve' => 'fa-check',
    'reject' => 'fa-xmark',
    'reset' => 'fa-key',
    'activate' => 'fa-user-check',
    'deactivate' => 'fa-user-slash',
  ];
  
  $icon = $iconMap[$type] ?? 'fa-circle';
  $buttonClass = 'action-btn ' . $type;
  
  if ($class) {
    $buttonClass .= ' ' . $class;
  }
  
  $titleAttr = $title ? ' title="' . $title . '"' : '';
  $disabledAttr = $disabled ? ' disabled' : '';
@endphp

@if($href)
  <a href="{{ $href }}" class="{{ $buttonClass }}"{{ $titleAttr }} {{ $attributes }}>
    <i class="fa-solid {{ $icon }}"></i>
  </a>
@else
  <button type="{{ $buttonType }}" class="{{ $buttonClass }}"{{ $titleAttr }}{{ $disabledAttr }} {{ $attributes }}>
    <i class="fa-solid {{ $icon }}"></i>
  </button>
@endif
