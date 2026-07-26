@props([
    'label',
    'name',
    'id' => null,

    'options' => [],
    'selected' => null,

    'placeholder' => null,
    'icon' => null,

    'required' => false,
    'disabled' => false,
    'full' => false,
])

@php
    $fieldId = $id ?? $name;

    $currentValue = old(
        $name,
        $selected
    );
@endphp

<div class="ui-form-group {{ $full ? 'ui-form-full' : '' }}">

    <label for="{{ $fieldId }}">
        {{ $label }}

        @if($required)
            <span class="ui-required">*</span>
        @endif
    </label>


    <div class="ui-input-wrap {{ $icon ? 'has-icon' : '' }}">

        @if($icon)

            <span class="ui-input-icon">
                <i class="fa-solid {{ $icon }}"></i>
            </span>

        @endif


        <select
            name="{{ $name }}"
            id="{{ $fieldId }}"

            @if($required)
                required
            @endif

            @if($disabled)
                disabled
            @endif

            {{ $attributes->except([
                'class'
            ]) }}
        >

            @if($placeholder)

                <option value="">
                    {{ $placeholder }}
                </option>

            @endif


            @foreach($options as $key => $option)

                @php
                    $optionValue =
                        is_int($key)
                            ? $option
                            : $key;
                @endphp

                <option
                    value="{{ $optionValue }}"
                    {{ (string) $currentValue === (string) $optionValue ? 'selected' : '' }}
                >
                    {{ $option }}
                </option>

            @endforeach

        </select>

    </div>


    @error($name)

        <span class="ui-field-error">
            {{ $message }}
        </span>

    @enderror

</div>