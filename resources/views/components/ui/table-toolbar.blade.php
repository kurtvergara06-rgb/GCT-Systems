@props([
    'action',
    'searchPlaceholder' => 'Search records...',
    'searchLabel' => 'Search records',
    'searchHelp' => null,
    'filterTitle' => 'Filter Records',
    'filterView' => null,
    'filterData' => [],
    'filterHelp' => 'Show only records that match the selected criteria.',
    'filterCount' => 0,
    'showFilterCount' => true,
    'sortTitle' => 'Sort Records',
    'sortOptions' => [],
    'defaultSort' => '',
    'defaultDirection' => 'asc',
    'formId' => null,
    'buttonId' => null,
    'buttonLabel' => null,
    'buttonIcon' => 'fa-plus',
    'showButton' => true,
])

@php
    $toolbarId = $formId ?: $attributes->get('id') ?: 'tableToolbar'.substr(md5($action.'|'.request()->path()), 0, 10);
    $filterContent = isset($filters) && trim((string) $filters) !== '' ? $filters : $slot;
    // Provide a concise search hint on Operation screens without altering other modules.
    $isOperationSearch = request()->is('operation/*', 'driver-attendance', 'mechanic-attendance');
    $searchHintFields = trim(preg_replace('/[.\\s]+$/', '', preg_replace('/^Search\\s+/i', '', (string) $searchPlaceholder)));
    $resolvedSearchHelp = $searchHelp ?: ($isOperationSearch
        ? 'Enter a value such as '.lcfirst($searchHintFields).'.'
        : null);
    $hasFilters = (bool) $filterView || trim((string) $filterContent) !== '';
    $hasSort = count($sortOptions) > 0;
    $selectedSort = (string) request('sort', $defaultSort);
    $legacyDirection = in_array(request('direction'), ['asc', 'desc'], true) ? request('direction') : null;
    $selectedDirection = request('sort_direction', $legacyDirection ?: $defaultDirection) === 'desc' ? 'desc' : 'asc';
    $sortIsActive = $hasSort && ($selectedSort !== (string) $defaultSort || $selectedDirection !== $defaultDirection);
    $formAttributes = $attributes->except('id')->class(['gct-table-toolbar']);
    if (! $attributes->has('data-server-filter') && ! $attributes->has('data-client-filter')) {
        $formAttributes = $formAttributes->merge(['data-server-filter' => 'true']);
    }
@endphp

<form method="GET"
    action="{{ $action }}"
    id="{{ $toolbarId }}"
    {{ $formAttributes->merge(['data-table-toolbar' => 'true']) }}>
    @isset($hidden)
        {{ $hidden }}
    @endisset

    <div class="gct-table-tool" data-table-tool="search">
        <button type="button" class="gct-table-tool-trigger {{ request()->filled('search') ? 'is-active' : '' }}"
            data-table-tool-trigger="search" aria-expanded="false" aria-controls="{{ $toolbarId }}Search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Search</span>
        </button>
        <div class="gct-table-tool-popover gct-table-search-popover" id="{{ $toolbarId }}Search" hidden>
            <div class="gct-table-popover-heading">
                <strong>{{ $searchLabel }}</strong>
                <button type="button" data-table-popover-close aria-label="Close search"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <label class="gct-table-search-field">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="{{ $searchPlaceholder }}" autocomplete="off">
            </label>
            @if($resolvedSearchHelp)<small>{{ $resolvedSearchHelp }}</small>@endif
        </div>
    </div>

    @if($hasFilters)
        <div class="gct-table-tool" data-table-tool="filter">
            <button type="button" class="gct-table-tool-trigger {{ (int) $filterCount > 0 ? 'is-active' : '' }}"
                data-table-tool-trigger="filter" aria-expanded="false" aria-controls="{{ $toolbarId }}Filter">
                <i class="fa-solid fa-filter" aria-hidden="true"></i><span>Filter</span>

            </button>
            <div class="gct-table-tool-popover gct-table-filter-popover" id="{{ $toolbarId }}Filter" hidden>
                <div class="gct-table-popover-heading">
                    <strong>{{ $filterTitle }}</strong>
                    <button type="button" data-table-popover-close aria-label="Close filters"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <p class="gct-table-popover-help">{{ $filterHelp }}</p>
                <div class="gct-table-filter-fields">
                    @if($filterView)
                        @include($filterView, $filterData)
                    @else
                        {{ $filterContent }}
                    @endif
                </div>
                <div class="gct-table-popover-actions">
                    <button type="button" class="is-secondary" data-table-clear-filters><i class="fa-solid fa-rotate-left"></i> Clear Filter</button>
                    <button type="submit" class="is-primary"><i class="fa-solid fa-check"></i> Apply Filter</button>
                </div>
            </div>
        </div>
    @endif

    @if($hasSort)
        <div class="gct-table-tool" data-table-tool="sort">
            <button type="button" class="gct-table-tool-trigger {{ $sortIsActive ? 'is-active' : '' }}"
                data-table-tool-trigger="sort" aria-expanded="false" aria-controls="{{ $toolbarId }}Sort">
                <i class="fa-solid fa-arrow-down-wide-short" aria-hidden="true"></i><span>Sort</span>
            </button>
            <div class="gct-table-tool-popover gct-table-sort-popover" id="{{ $toolbarId }}Sort" hidden>
                <div class="gct-table-popover-heading">
                    <strong>{{ $sortTitle }}</strong>
                    <button type="button" data-table-popover-close aria-label="Close sorting"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <p class="gct-table-popover-help">Choose a field and direction.</p>
                <label class="gct-table-filter-field"><span>Sort Field</span><select name="sort" data-default="{{ $defaultSort }}">
                    @foreach($sortOptions as $value => $label)
                        <option value="{{ $value }}" @selected($selectedSort === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select></label>
                <label class="gct-table-filter-field"><span>Sort Order</span><select name="sort_direction" data-default="{{ $defaultDirection }}">
                    <option value="asc" @selected($selectedDirection === 'asc')>Ascending (A-Z)</option>
                    <option value="desc" @selected($selectedDirection === 'desc')>Descending (Z-A)</option>
                </select></label>
                <div class="gct-table-popover-actions">
                    <button type="button" class="is-secondary" data-table-reset-sort><i class="fa-solid fa-rotate-left"></i> Reset Sort</button>
                    <button type="submit" class="is-primary"><i class="fa-solid fa-check"></i> Apply Sort</button>
                </div>
            </div>
        </div>
    @endif

    @if($buttonLabel && $showButton)
        <button type="button" @if($buttonId) id="{{ $buttonId }}" @endif class="primary-btn gct-table-toolbar-action">
            <i class="fa-solid {{ $buttonIcon }}" aria-hidden="true"></i>{{ $buttonLabel }}
        </button>
    @endif
</form>
