@props([
    'action',
    'class' => 'toolbar',
    'searchName' => 'search',
    'searchValue' => request('search'),
    'searchPlaceholder' => 'Search...',
    'buttonId' => null,
    'buttonLabel' => 'Add New',
    'buttonIcon' => 'fa-plus',
    'showButton' => true,
])

<form
    {{ $attributes->merge([
        'method' => 'GET',
        'action' => $action,
        'class' => $class,
        'data-ui-component' => 'table-toolbar',
    ]) }}
>
    <div class="search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input
            type="text"
            name="{{ $searchName }}"
            value="{{ $searchValue }}"
            placeholder="{{ $searchPlaceholder }}"
        >
    </div>

    {{ $slot }}

    @if($showButton && filled($buttonLabel))
        <button type="button" class="primary-btn" id="{{ $buttonId }}">
            <i class="fa-solid {{ $buttonIcon }}"></i>
            {{ $buttonLabel }}
        </button>
    @endif
</form>
