{{-- 個人化封面：看的人看得到（通過審核，或本人／管理員）就顯示照片，否則一條固定配色的色帶。裝飾用，alt 空 --}}
@php($cp = $user->profile)
@if ($cp && auth()->check() && $cp->coverVisibleTo(auth()->user()))
    <img class="cover" src="{{ route('covers.show', $user) }}?v={{ $cp->cover_updated_at?->timestamp }}" alt="" width="1200" height="400">
@else
    <div class="cover cover-empty" aria-hidden="true"></div>
@endif
