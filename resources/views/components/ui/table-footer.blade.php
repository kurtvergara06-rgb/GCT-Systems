@props([
    'items',
])

<div
    class="table-footer"
    data-scroll-pagination
    data-page-name="{{ $items->getPageName() }}"
    data-next-url="{{ $items->nextPageUrl() }}"
    data-total="{{ $items->total() }}"
>
    <p data-entry-count>
        Showing {{ $items->firstItem() ?? 0 }}
        to {{ $items->lastItem() ?? 0 }}
        of {{ $items->total() }} entries
    </p>

    <span
        class="table-loading-all"
        data-table-loading
        hidden
    >
        <x-ui.spinner size="sm" />
        Loading all records...
    </span>
</div>
