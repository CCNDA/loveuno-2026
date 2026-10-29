{{-- 頭像：還沒有照片功能，先用名字第一個字＋固定配色（顏色由 id 決定，每次一樣）。
     名字旁邊都會寫出來，所以這裡 aria-hidden，不讓螢幕閱讀器念兩次。 --}}
@php($palette = ['#8a2d3b', '#2f5d50', '#3d4f8a', '#7a4b1f', '#5b3a7a', '#1f5f7a'])
<span class="avatar avatar-{{ $size ?? 'md' }}" style="background: {{ $palette[$user->id % count($palette)] }}" aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
