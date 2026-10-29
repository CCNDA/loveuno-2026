{{-- 頭像：有照片而且看的人看得到（通過審核，或是本人／管理員）就顯示照片；否則用名字第一個字＋固定配色。
     名字旁邊都會寫出來，所以照片 alt 留空、文字頭像 aria-hidden，不讓螢幕閱讀器念兩次。 --}}
@php
    $palette = ['#8a2d3b', '#2f5d50', '#3d4f8a', '#7a4b1f', '#5b3a7a', '#1f5f7a'];
    $prof = $user->profile;
    $showPhoto = $prof && auth()->check() && $prof->photoVisibleTo(auth()->user());
@endphp
@if ($showPhoto)
    <img class="avatar avatar-{{ $size ?? 'md' }} avatar-photo" src="{{ route('photos.show', $user) }}?v={{ $prof->photo_updated_at?->timestamp }}" alt="" loading="lazy">
@else
    <span class="avatar avatar-{{ $size ?? 'md' }}" style="background: {{ $palette[$user->id % count($palette)] }}" aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
@endif
