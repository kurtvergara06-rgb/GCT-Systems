@props([
    'items',
])

@php
    $currentPage = $items->currentPage();
    $lastPage = $items->lastPage();

    $queryParameters = request()->except([
        'page',
        $items->getPageName(),
    ]);

    $pageName = $items->getPageName();

    $previousQuery = http_build_query(array_merge(
        $queryParameters,
        [
            $pageName => max(
                $currentPage - 1,
                1
            ),
        ]
    ));

    $nextQuery = http_build_query(array_merge(
        $queryParameters,
        [
            $pageName => min(
                $currentPage + 1,
                $lastPage
            ),
        ]
    ));

    $path = '/' . ltrim(
        request()->path(),
        '/'
    );
@endphp

<div class="table-footer">
    <p>
        Showing {{ $items->firstItem() ?? 0 }}
        to {{ $items->lastItem() ?? 0 }}
        of {{ $items->total() }} entries
    </p>

    @if ($lastPage > 1)
        <div class="custom-pagination">
            @if ($items->onFirstPage())
                <span class="page-btn disabled">
                    Previous
                </span>
            @else
                <a
                    href="{{ $path }}?{{ $previousQuery }}"
                    class="page-btn"
                >
                    Previous
                </a>
            @endif

            <span class="page-number">
                Page {{ $currentPage }}
                of {{ $lastPage }}
            </span>

            @if ($items->hasMorePages())
                <a
                    href="{{ $path }}?{{ $nextQuery }}"
                    class="page-btn"
                >
                    Next
                </a>
            @else
                <span class="page-btn disabled">
                    Next
                </span>
            @endif
        </div>
    @endif
</div>