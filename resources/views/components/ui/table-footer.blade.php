@props([
  'items',
])

<div class="table-footer">
  <p>
    Showing {{ $items->firstItem() ?? 0 }}
    to {{ $items->lastItem() ?? 0 }}
    of {{ $items->total() }} entries
  </p>

  <div class="custom-pagination">
    @if($items->onFirstPage())
      <span class="page-btn disabled">Previous</span>
    @else
      <a href="{{ $items->previousPageUrl() }}" class="page-btn">Previous</a>
    @endif

    <span class="page-number">
      Page {{ $items->currentPage() }} of {{ $items->lastPage() }}
    </span>

    @if($items->hasMorePages())
      <a href="{{ $items->nextPageUrl() }}" class="page-btn">Next</a>
    @else
      <span class="page-btn disabled">Next</span>
    @endif
  </div>
</div>