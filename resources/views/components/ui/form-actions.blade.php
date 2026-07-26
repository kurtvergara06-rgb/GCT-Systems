@props([
    'cancelText' => 'Cancel',
    'submitText' => 'Save',

    'cancelId' => null,
    'submitId' => null,

    'submitIcon' => 'fa-floppy-disk',

    'cancelClass' => '',
    'submitClass' => '',
])

<div
    {{ $attributes->merge([
        'class' => 'ui-form-actions'
    ]) }}
>

    {{-- CANCEL --}}
    <button
        type="button"
        class="
            ui-form-btn
            ui-form-btn-cancel
            {{ $cancelClass }}
        "

        @if($cancelId)
            id="{{ $cancelId }}"
        @endif

        data-ui-modal-close
    >
        {{ $cancelText }}
    </button>


    {{-- SUBMIT --}}
    <button
        type="submit"
        class="
            ui-form-btn
            ui-form-btn-primary
            {{ $submitClass }}
        "

        @if($submitId)
            id="{{ $submitId }}"
        @endif
    >

        @if($submitIcon)

            <i class="fa-solid {{ $submitIcon }}"></i>

        @endif


        <span>
            {{ $submitText }}
        </span>

    </button>

</div>