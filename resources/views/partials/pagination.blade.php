@if ($paginator->hasPages())
<nav class="pagination" aria-label="Pagination">
    <div class="pagination-info">
        @if(method_exists($paginator, 'total'))
            Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ number_format($paginator->total()) }}
        @else
            Page {{ $paginator->currentPage() }}
        @endif
    </div>
    <div class="pagination-links">
        @if ($paginator->onFirstPage())
            <span class="page-link is-disabled" aria-disabled="true" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></span>
        @else
            <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>
        @endif

        @isset($elements)
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="page-link is-disabled">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="page-link is-active" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        @endisset

        @if ($paginator->hasMorePages())
            <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>
        @else
            <span class="page-link is-disabled" aria-disabled="true" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></span>
        @endif
    </div>
</nav>
@endif
