@props([
    'title',
    'subtitle' => null,
    'icon' => null,
])

<section
    {{ $attributes->merge([
        'class' => 'ui-form-section'
    ]) }}
>

    <div class="ui-form-section-header">

        <div class="ui-form-section-title">

            @if($icon)

                <div class="ui-form-section-icon">
                    <i class="fa-solid {{ $icon }}"></i>
                </div>

            @endif


            <div>

                <h3>
                    {{ $title }}
                </h3>


                @if($subtitle)

                    <p>
                        {{ $subtitle }}
                    </p>

                @endif

            </div>

        </div>


        @isset($action)

            <div class="ui-form-section-action">
                {{ $action }}
            </div>

        @endisset

    </div>


    <div class="ui-form-section-body">
        {{ $slot }}
    </div>

</section>