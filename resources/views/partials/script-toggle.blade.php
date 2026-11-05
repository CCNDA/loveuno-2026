{{-- 繁／简切換（頁首、登入頁共用） --}}
@if (session('script') === 'hans')
    <a href="{{ route('script', 'hant') }}" data-keep-script lang="zh-Hant" class="script-toggle">繁體<span class="visually-hidden">（切換成繁體中文）</span></a>
@else
    <a href="{{ route('script', 'hans') }}" data-keep-script lang="zh-Hans" class="script-toggle">简体<span class="visually-hidden">（切换成简体中文）</span></a>
@endif
