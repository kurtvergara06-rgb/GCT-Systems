@props([
  'mode' => 'button',

  'type' => 'button',
  'class' => '',
  'title' => '',
  'icon' => '',

  'feedbackType' => 'success',
  'message' => null,
  'buttonText' => 'Okay',

  'id' => 'deleteModal',
  'deleteTitle' => 'Delete Record?',
  'deleteMessage' => 'Are you sure you want to delete',
  'nameId' => 'deleteRecordName',
  'cancelId' => 'cancelDelete',
  'confirmId' => 'confirmDelete',
])

@if($mode === 'button')

  <button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => $class]) }}
    title="{{ $title }}"
  >
    @if($icon)
      <i class="fa-solid {{ $icon }}"></i>
    @endif
  </button>

@elseif($mode === 'feedback')

  @php
    $isSuccess = $feedbackType === 'success';
    $modalTitle = $isSuccess ? 'Success' : 'Error';
    $iconClass = $isSuccess ? 'fa-check' : 'fa-triangle-exclamation';
    $iconWrapper = $isSuccess ? 'success-icon' : 'delete-icon';
  @endphp

  @if($message)
    <div class="success-modal-overlay show">
      <div class="success-modal-box">
        <div class="{{ $iconWrapper }}">
          <i class="fa-solid {{ $iconClass }}"></i>
        </div>

        <h2>{{ $modalTitle }}</h2>
        <p>{{ $message }}</p>

        <button type="button" class="save-btn close-feedback-modal">
          {{ $buttonText }}
        </button>
      </div>
    </div>
  @endif

@elseif($mode === 'delete')

  <div id="{{ $id }}" class="delete-modal-overlay">
    <div class="delete-modal-box">
      <div class="delete-icon">
        <i class="fa-solid fa-triangle-exclamation"></i>
      </div>

      <h2>{{ $deleteTitle }}</h2>

      <p>
        {{ $deleteMessage }}
        <strong id="{{ $nameId }}">this record</strong>?
        This action can’t be undone.
      </p>

      <div class="delete-modal-actions">
        <button type="button" id="{{ $cancelId }}" class="cancel-btn">
          Cancel
        </button>

        <button type="button" id="{{ $confirmId }}" class="danger-btn">
          Yes, Delete
        </button>
      </div>
    </div>
  </div>

@endif