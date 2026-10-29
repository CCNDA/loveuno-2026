{{-- 日記卡片下面的互動列（設計稿 DiaryWall：禱告圖示＋人數、留言圖示＋則數）。
     $d 要先用 Diary::withInteractions() 查（prayers_count、comments_count、prayed_by_me）。
     $from：wall／list／show，按完回到哪一頁；$page：列表第幾頁。 --}}
<div class="diary-actions">
    <form method="POST" action="{{ route('diaries.pray', $d) }}">
        @csrf
        <input type="hidden" name="from" value="{{ $from }}">
        @if (($page ?? 1) > 1)<input type="hidden" name="page" value="{{ $page }}">@endif
        <button type="submit" class="action-button{{ $d->prayed_by_me ? ' is-on' : '' }}" aria-pressed="{{ $d->prayed_by_me ? 'true' : 'false' }}">
            <img src="{{ asset('images/brand/pray.svg') }}" alt="" width="24" height="24">
            <span>為你禱告</span>
            <span class="action-count">{{ $d->prayers_count }}<span class="visually-hidden"> 人禱告</span></span>
        </button>
    </form>
    <a class="action-button" href="{{ route('diaries.show', $d) }}#comments-title">
        <img src="{{ asset('images/brand/comment.svg') }}" alt="" width="24" height="24">
        <span>留言</span>
        <span class="action-count">{{ $d->comments_count }}<span class="visually-hidden"> 則</span></span>
    </a>
</div>
