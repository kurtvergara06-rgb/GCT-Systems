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
        <i class="fa-solid fa-spinner fa-spin"></i>
        Loading all records...
    </span>
</div>