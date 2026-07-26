@props([
    'label',
    'name',
    'id' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,

    'icon' => null,
    'unit' => null,

    'required' => false,
    'readonly' => false,
    'disabled' => false,
    'full' => false,

    'min' => null,
    'max' => null,
    'step' => null,
])

@php
    $fieldId = $id ?? $name;

    $fieldValue = old(
        $name,
        $value
    );
@endphp

<div class="ui-form-group {{ $full ? 'ui-form-full' : '' }}">

    <label for="{{ $fieldId }}">
        {{ $label }}

        @if($required)
            <span class="ui-required">*</span>
        @endif
    </label>


    <div
        class="
            ui-input-wrap
            {{ $icon ? 'has-icon' : '' }}
            {{ $unit ? 'has-unit' : '' }}
        "
    >

        @if($icon)

            <span class="ui-input-icon">
                <i class="fa-solid {{ $icon }}"></i>
            </span>

        @endif


        <input
            type="{{ $type }}"
            name="{{ $name }}"
            id="{{ $fieldId }}"
            value="{{ $fieldValue }}"
            placeholder="{{ $placeholder }}"

            @if($required)
                required
            @endif

            @if($readonly)
                readonly
            @endif

            @if($disabled)
                disabled
            @endif

            @if($min !== null)
                min="{{ $min }}"
            @endif

            @if($max !== null)
                max="{{ $max }}"
            @endif

            @if($step !== null)
                step="{{ $step }}"
            @endif

            {{ $attributes->except([
                'class'
            ]) }}
        >


        @if($unit)

            <span class="ui-input-unit">
                {{ $unit }}
            </span>

        @endif

    </div>


    @error($name)

        <span class="ui-field-error">
            {{ $message }}
        </span>

    @enderror

</div>