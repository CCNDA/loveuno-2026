{{-- 還有下一批就放「載入更多」：js/wall-scroll.js 拉到這裡就自動接上；沒有 JS 時是一般連結到下一頁 --}}
@if ($diaries->hasMorePages())
    <p class="wall-more"><a class="pill-link" href="{{ $diaries->nextPageUrl() }}" rel="next" data-wall-more>載入更多日記</a></p>
@else
    <p class="wall-more wall-end">已經是最舊的日記了。</p>
@endif
