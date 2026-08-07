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

@php
    $buttonClasses =
        'action-btn ' . trim($class);
@endphp


@if($mode === 'button')

    <button
        type="{{ $type }}"
        {{ $attributes->merge([
            'class' => $buttonClasses
        ]) }}
        title="{{ $title }}"
    >

        @if($icon)
            <i class="fa-solid {{ $icon }}"></i>
        @endif

        {{ $slot }}

    </button>


@elseif($mode === 'feedback')

    @php
        $isSuccess =
            $feedbackType === 'success';

        $modalTitle =
            $isSuccess
                ? 'Success'
                : 'Error';

        $iconClass =
            $isSuccess
                ? 'fa-check'
                : 'fa-triangle-exclamation';

        $iconWrapper =
            $isSuccess
                ? 'success-icon'
                : 'delete-icon';
    @endphp


    @if($message)

        <div
            class="
                modal-overlay
                show
                active
                feedback-modal-overlay
            "
        >

            <div class="modal-card success-modal-box">

                <div class="{{ $iconWrapper }}">
                    <i class="fa-solid {{ $iconClass }}"></i>
                </div>


                <h2>
                    {{ $modalTitle }}
                </h2>


                <p>
                    {{ $message }}
                </p>


                <button
                    type="button"
                    class="primary-btn close-feedback-modal"
                    data-close-feedback
                >
                    {{ $buttonText }}
                </button>

            </div>

        </div>

    @endif


@elseif($mode === 'delete')

    <div
        id="{{ $id }}"
        class="modal-overlay"
    >

        <div class="modal-card delete-modal-box">

            <div class="delete-icon">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>


            <h2>
                {{ $deleteTitle }}
            </h2>


            <p>
                {{ $deleteMessage }}

                <strong id="{{ $nameId }}">
                    this record
                </strong>?

                This action cannot be undone.
            </p>


            <div class="delete-modal-actions">

                <button
                    type="button"
                    id="{{ $cancelId }}"
                    class="secondary-btn cancel-btn"
                >
                    Cancel
                </button>


                <button
                    type="button"
                    id="{{ $confirmId }}"
                    class="danger-btn"
                >
                    Yes, Delete
                </button>

            </div>

        </div>

    </div>


@elseif($mode === 'global-confirmation')

    <div
        id="globalConfirmationModal"
        class="
            modal-overlay
            global-confirmation-overlay
        "
        data-global-confirmation-modal
        aria-hidden="true"
        tabindex="-1"
    >

        <div
            class="
                modal-card
                delete-modal-box
                global-confirmation-box
            "
            role="dialog"
            aria-modal="true"
            aria-labelledby="globalConfirmationTitle"
        >

            <div
                id="globalConfirmationIcon"
                class="
                    global-confirmation-icon
                    warning
                "
            >
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>


            <h2 id="globalConfirmationTitle">
                Confirm Action
            </h2>


            <p id="globalConfirmationMessage">
                Are you sure you want to continue?
            </p>


            <div class="delete-modal-actions">

                <button
                    type="button"
                    id="cancelGlobalConfirmation"
                    class="
                        secondary-btn
                        cancel-btn
                    "
                >
                    Cancel
                </button>


                <button
                    type="button"
                    id="confirmGlobalAction"
                    class="
                        global-confirm-btn
                        primary-btn
                    "
                >
                    Confirm
                </button>

            </div>

        </div>

    </div>

    <style>
        /* Fuel Reports uses the same action language as Job Orders. */
        .fuel-page .fuel-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            white-space: nowrap;
        }

        .fuel-page .fuel-actions form {
            display: inline-flex;
            margin: 0;
        }

        .fuel-page .fuel-action-btn {
            width: 36px !important;
            height: 36px !important;
            min-width: 36px !important;
            padding: 0 !important;
            border: 1px solid transparent !important;
            border-radius: 9px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            box-shadow: none !important;
            cursor: pointer;
            transition: background .18s ease, color .18s ease, border-color .18s ease, transform .18s ease;
        }

        .fuel-page .fuel-action-btn i {
            margin: 0 !important;
            font-size: 13px !important;
            line-height: 1 !important;
        }

        .fuel-page .fuel-action-btn.view,
        .fuel-page .fuel-action-btn.edit {
            border-color: #d5e4f8 !important;
            background: #edf3fb !important;
            color: #0b4cb8 !important;
        }

        .fuel-page .fuel-action-btn.view:hover,
        .fuel-page .fuel-action-btn.edit:hover {
            border-color: #bfd6f4 !important;
            background: #dbeafe !important;
            color: #0644a8 !important;
            transform: translateY(-1px);
        }

        .fuel-page .fuel-action-btn.delete {
            border-color: #ffd5d5 !important;
            background: #fff0f0 !important;
            color: #dc2626 !important;
        }

        .fuel-page .fuel-action-btn.delete:hover {
            border-color: #fecaca !important;
            background: #fee2e2 !important;
            color: #b91c1c !important;
            transform: translateY(-1px);
        }

        /* Keep Recent Fuel Entries compact and scrollable. */
        .fuel-page .recent-fuel-card .table-wrap {
            width: 100%;
            max-height: 255px !important;
            overflow: auto !important;
            scrollbar-gutter: stable;
            overscroll-behavior: contain;
        }

        .fuel-page .recent-fuel-card .recent-records-table {
            min-width: 1120px;
            margin: 0;
        }

        .fuel-page .recent-fuel-card thead th {
            position: sticky;
            top: 0;
            z-index: 4;
            background: #edf3fb;
            box-shadow: 0 1px 0 #dbe4f0;
        }

        .fuel-page .recent-fuel-card .table-wrap::-webkit-scrollbar {
            width: 9px;
            height: 9px;
        }

        .fuel-page .recent-fuel-card .table-wrap::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 999px;
        }

        .fuel-page .recent-fuel-card .table-wrap::-webkit-scrollbar-thumb {
            background: #c5d1df;
            border: 2px solid #f1f5f9;
            border-radius: 999px;
        }

        .fuel-page .recent-fuel-card .table-wrap::-webkit-scrollbar-thumb:hover {
            background: #9fb0c4;
        }
    </style>

@endif