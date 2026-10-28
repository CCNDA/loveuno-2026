@if ($paginator->hasPages())
<nav class="pagination" aria-label="分頁">
    <ul>
        <li>
            @if ($paginator->onFirstPage())
                <span aria-disabled="true">上一頁</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">上一頁</a>
            @endif
        </li>
        <li><span aria-current="page">第 {{ $paginator->currentPage() }} 頁，共 {{ $paginator->lastPage() }} 頁</span></li>
        <li>
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">下一頁</a>
            @else
                <span aria-disabled="true">下一頁</span>
            @endif
        </li>
    </ul>
</nav>
@endif
