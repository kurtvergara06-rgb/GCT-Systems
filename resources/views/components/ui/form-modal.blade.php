@props([
    'id' => 'modal',

    'title' => 'Modal Title',
    'titleId' => null,

    'subtitle' => null,
    'description' => null,

    'icon' => null,
    'size' => 'large',

    'formId' => null,
    'action' => '#',
    'method' => 'POST',

    'submitText' => 'Save',
    'submitTextId' => null,
    'submitId' => null,
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

    'closeDataAttribute' => null,
])

@php
    $sizeClass = match($size) {
        'small' => 'ui-form-modal-sm',
        'medium' => 'ui-form-modal-md',
        'large' => 'ui-form-modal-lg',
        'wide' => 'ui-form-modal-xl',
        default => 'ui-form-modal-lg',
    };

    $httpMethod = strtoupper($method);
@endphp


<div
    id="{{ $id }}"
    {{ $attributes->merge([
        'class' => 'ui-form-overlay'
    ]) }}
>

    <div class="ui-form-modal {{ $sizeClass }}">

        {{-- =====================================================
            HEADER
        ====================================================== --}}
        <div class="ui-form-modal-header">

            <div class="ui-form-title-wrap">

                @if($icon)

                    <div class="ui-form-title-icon">
                        <i class="fa-solid {{ $icon }}"></i>
                    </div>

                @endif


                <div class="ui-form-heading">

                    <h2
                        @if($titleId)
                            id="{{ $titleId }}"
                        @endif
                    >
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
                class="ui-form-close"

                data-ui-modal-close

                @if($closeDataAttribute)
                    {{ $closeDataAttribute }}
                @endif

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

            class="ui-form-content"

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


            @if($httpMethod !== 'POST')
                @method($httpMethod)
            @endif


            <div class="ui-form-modal-body">

                {{-- =================================================
                    OPTIONAL INTRO
                ================================================== --}}
                @if($subtitle)

                    <div class="ui-form-intro">

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


                {{-- =================================================
                    FORM CONTENT
                ================================================== --}}
                {{ $slot }}


                {{-- =================================================
                    ACTIONS
                ================================================== --}}
                @if($showActions)

                    <div class="ui-form-actions">

                        <button
                            type="button"

                            id="{{ $cancelId ?? 'cancel-' . $id }}"

                            class="
                                ui-form-btn
                                ui-form-btn-cancel
                            "

                            data-ui-modal-close

                            @if($closeDataAttribute)
                                {{ $closeDataAttribute }}
                            @endif
                        >
                            {{ $cancelText }}
                        </button>


                        <button
                            type="submit"

                            @if($submitId)
                                id="{{ $submitId }}"
                            @endif

                            class="
                                ui-form-btn
                                {{ $submitClass }}
                            "
                        >

                            @if($submitIcon)

                                <i class="fa-solid {{ $submitIcon }}"></i>

                            @endif


                            <span
                                @if($submitTextId)
                                    id="{{ $submitTextId }}"
                                @endif
                            >
                                {{ $submitText }}
                            </span>

                        </button>

                    </div>

                @endif

            </div>

        </form>

    </div>

</div>