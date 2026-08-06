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

    /*
    |--------------------------------------------------------------------------
    | PMS JOB ORDER BUS PREFILL
    |--------------------------------------------------------------------------
    |
    | A Job Order opened from PMS Scheduling already belongs to one bus.
    | Ensure that bus exists in the select options, select it automatically,
    | lock the dropdown, and submit its value through a hidden input.
    |
    */
    $isPmsJobOrderBus =
        $name === 'bus_no'
        && request()->routeIs('job-orders')
        && request()->boolean('create_pms')
        && request()->filled('bus_no');

    $pmsBusNo = $isPmsJobOrderBus
        ? trim((string) request('bus_no'))
        : null;

    $fieldOptions = is_array($options)
        ? $options
        : collect($options)->toArray();

    if (
        $isPmsJobOrderBus
        && $pmsBusNo !== ''
        && ! array_key_exists($pmsBusNo, $fieldOptions)
    ) {
        $fieldOptions[$pmsBusNo] = $pmsBusNo;
    }

    $currentValue = $isPmsJobOrderBus
        ? $pmsBusNo
        : old($name, $selected);

    $isEffectivelyDisabled =
        $disabled || $isPmsJobOrderBus;
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

            @if($isEffectivelyDisabled)
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


            @foreach($fieldOptions as $key => $option)

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


        @if($isPmsJobOrderBus)
            <input
                type="hidden"
                name="{{ $name }}"
                value="{{ $pmsBusNo }}"
            >
        @endif

    </div>


    @error($name)

        <span class="ui-field-error">
            {{ $message }}
        </span>

    @enderror

</div>