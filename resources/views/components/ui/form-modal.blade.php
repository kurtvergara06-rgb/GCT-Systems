@props([
  'id' => 'modal',
  'title' => 'Modal Title',
  'subtitle' => null,
  'description' => null,
  'formId' => null,
  'action' => '#',
  'method' => 'POST',
  'submitText' => 'Save',
  'cancelId' => null,
  'closeId' => null,
  'submitClass' => 'primary-btn',
  'confirm' => false,
  'confirmTitle' => null,
  'confirmMessage' => null,
  'confirmButton' => null,
  'confirmType' => 'warning',
])

<div id="{{ $id }}" class="modal-overlay">
  <div class="modal-card modal-box wide-modal">

    <div class="modal-header">
      <h2>{{ $title }}</h2>

      <button
        type="button"
        id="{{ $closeId ?? 'close-' . $id }}"
        class="modal-close close-btn"
      >
        &times;
      </button>
    </div>

    <form
      @if($formId) id="{{ $formId }}" @endif
      action="{{ $action }}"
      method="POST"
      class="job-form wide-form"
      @if($confirm) data-confirm-form @endif
      @if($confirmTitle) data-confirm-title="{{ $confirmTitle }}" @endif
      @if($confirmMessage) data-confirm-message="{{ $confirmMessage }}" @endif
      @if($confirmButton) data-confirm-button="{{ $confirmButton }}" @endif
      @if($confirm) data-confirm-type="{{ $confirmType }}" @endif
    >
      @csrf

      @if(strtoupper($method) !== 'POST')
        @method($method)
      @endif

      @if($subtitle)
        <div class="form-section-title full-width">
          <h3>{{ $subtitle }}</h3>

          @if($description)
            <p>{{ $description }}</p>
          @endif
        </div>
      @endif

      {{ $slot }}

      <div class="modal-actions full-width">
        <button
          type="button"
          id="{{ $cancelId ?? 'cancel-' . $id }}"
          class="secondary-btn cancel-btn"
        >
          Cancel
        </button>

        <button type="submit" class="{{ $submitClass }}">
          {{ $submitText }}
        </button>
      </div>
    </form>

  </div>
</div>
