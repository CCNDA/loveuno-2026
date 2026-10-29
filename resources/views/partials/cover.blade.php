{{-- 個人化封面：看的人看得到（通過審核，或本人／管理員）就顯示照片，否則一條固定配色的色帶。裝飾用，alt 空 --}}
@php($cp = $user->profile)
@if ($cp && auth()->check() && $cp->coverVisibleTo(auth()->user()))
    {{-- 一般頁面是裝飾（alt 空）；上傳頁上封面就是主角，傳 $alt 進來 --}}
    <img class="cover" src="{{ route('covers.show', $user) }}?v={{ $cp->cover_updated_at?->timestamp }}" alt="{{ $alt ?? '' }}" width="1200" height="400">
@else
    <div class="cover cover-empty" aria-hidden="true"></div>
@endif
