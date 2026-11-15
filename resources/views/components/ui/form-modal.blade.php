@props([
    'id' => 'modal',

    'title' => 'Modal Title',
    'subtitle' => null,
    'description' => null,

    'icon' => null,
    'size' => 'large',

    'formId' => null,
    'action' => '#',
    'method' => 'POST',

    'submitText' => 'Save',
    'submitIcon' => 'fa-floppy-disk',

    'cancelText' => 'Cancel',
    'cancelId' => null,
    'closeId' => null,

    'submitClass' => 'ui-form-btn-primary',

    'confirm' => false,
    'confirmTitle' => null,
    'confirmMessage' => null,
    'confirmButton' => null,
    'confirmType' => 'warning',

    'showActions' => true,
])

@php
    /*
    |--------------------------------------------------------------------------
    | Modal Size
    |--------------------------------------------------------------------------
    */

    $sizeClass = match($size) {
        'small' => 'ui-form-modal-sm',
        'medium' => 'ui-form-modal-md',
        'large' => 'ui-form-modal-lg',
        'wide' => 'ui-form-modal-xl',
        default => 'ui-form-modal-lg',
    };


    /*
    |--------------------------------------------------------------------------
    | HTTP Method
    |--------------------------------------------------------------------------
    */

    $httpMethod = strtoupper($method);
@endphp


<div
    id="{{ $id }}"
    {{ $attributes->merge([
        'class' => 'modal-overlay ui-form-overlay'
    ]) }}
>

    <div
        class="
            modal-card
            modal-box
            wide-modal
            ui-form-modal
            {{ $sizeClass }}
        "
    >

        {{-- =====================================================
            HEADER
        ====================================================== --}}
        <div class="modal-header ui-form-modal-header">

            <div class="ui-form-title-wrap">

                @if($icon)

                    <div class="ui-form-title-icon">
                        <i class="fa-solid {{ $icon }}"></i>
                    </div>

                @endif


                <div class="ui-form-heading">

                    <h2>
                        {{ $title }}
                    </h2>

                    @if($description && !$subtitle)

                        <p>
                            {{ $description }}
                        </p>

                    @endif

                </div>

            </div>


            <button
                type="button"
                id="{{ $closeId ?? 'close-' . $id }}"
                class="modal-close close-btn ui-form-close"
                data-ui-modal-close
                aria-label="Close"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </div>


        {{-- =====================================================
            FORM
        ====================================================== --}}
        <form
            @if($formId)
                id="{{ $formId }}"
            @endif

            action="{{ $action }}"
            method="POST"

            class="job-form wide-form ui-form-content"

            @if($confirm)
                data-confirm-form
            @endif

            @if($confirmTitle)
                data-confirm-title="{{ $confirmTitle }}"
            @endif

            @if($confirmMessage)
                data-confirm-message="{{ $confirmMessage }}"
            @endif

            @if($confirmButton)
                data-confirm-button="{{ $confirmButton }}"
            @endif

            @if($confirm)
                data-confirm-type="{{ $confirmType }}"
            @endif
        >

            @csrf


            {{-- =================================================
                METHOD SPOOFING
            ================================================== --}}
            @if($httpMethod !== 'POST')

                @method($httpMethod)

            @endif


            {{-- =================================================
                BODY
            ================================================== --}}
            <div class="ui-form-modal-body">

                {{-- SECTION TITLE --}}
                @if($subtitle)

                    <div class="form-section-title ui-form-intro">

                        <h3>
                            {{ $subtitle }}
                        </h3>

                        @if($description)

                            <p>
                                {{ $description }}
                            </p>

                        @endif

                    </div>

                @endif


                {{-- MAIN FORM CONTENT --}}
                {{ $slot }}


                {{-- =================================================
                    ACTIONS
                ================================================== --}}
                @if($showActions)

                    <div class="modal-actions ui-form-actions">

                        <button
                            type="button"
                            id="{{ $cancelId ?? 'cancel-' . $id }}"
                            class="
                                secondary-btn
                                cancel-btn
                                ui-form-btn
                                ui-form-btn-cancel
                            "
                            data-ui-modal-close
                        >
                            {{ $cancelText }}
                        </button>


                        <button
                            type="submit"
                            class="
                                ui-form-btn
                                {{ $submitClass }}
                            "
                        >

                            @if($submitIcon)

                                <i class="fa-solid {{ $submitIcon }}"></i>

                            @endif


                            <span>
                                {{ $submitText }}
                            </span>

                        </button>

                    </div>

                @endif

            </div>

        </form>

    </div>

</div>