{{-- 側邊選單：照設計稿 Figma 1156:11227 的項目和順序（熊叔叔 2026-11-05「figma 應該有規劃選單的結構」）。
     設計稿下面的 App Store／Google Play 不放（新版是網頁，design-deviations #42）；會員中心設計稿沒有獨立一項，點頭像／暱稱進去。
     管理員的項目設計稿沒有，放在最後「管理」一段。
     寬螢幕（≥1200px，設計稿 Index 電腦版）固定在左邊開著（CSS 蓋過 hidden，沒有 JS 也看得到），☰／✕ 可以收起來；窄螢幕是 ☰ 打開的抽屜，role=dialog 由 side-menu.js 打開時才加。 --}}
@php($me = auth()->user())
<div class="side-menu" id="side-menu" hidden>
    <div class="side-menu-panel">
        <button type="button" class="side-menu-close"><span aria-hidden="true">✕</span><span class="visually-hidden">關閉選單</span></button>
        <a class="side-menu-me" href="{{ route('center.account') }}">
            @include('partials.avatar', ['user' => $me])
            <span id="side-menu-title" class="side-menu-name">{{ $me->name }}<span class="visually-hidden">（會員中心）</span></span>
        </a>
        <a class="side-menu-points" href="{{ route('center.points') }}"><span aria-hidden="true">♥</span> {{ number_format((int) $me->points) }} 點<span class="visually-hidden">（愛積點）</span></a>
        <nav aria-label="選單">
            <ul class="side-menu-list">
                <li><a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>生活日記牆</a></li>
                <li><a href="{{ route('diaries.index', ['mine' => 1]) }}" @if(request()->routeIs('diaries.index') && request()->boolean('mine')) aria-current="page" @endif>我的日記</a></li>
                <li><a href="{{ route('members.index') }}" @if(request()->routeIs('members.index')) aria-current="page" @endif>認識朋友</a></li>
                <li><a href="{{ route('notes.index') }}" @if(request()->routeIs('notes.*')) aria-current="page" @endif>傳紙條@if(($unreadNotes ?? 0) > 0) <span class="badge" aria-hidden="true">{{ $unreadNotes }}</span><span class="visually-hidden">，有 {{ $unreadNotes }} 則未讀</span>@endif</a></li>
                <li><a href="{{ route('meetups.index') }}" @if(request()->routeIs('meetups.*')) aria-current="page" @endif>約會</a></li>
                <li><a href="{{ route('love-stories.index') }}" @if(request()->routeIs('love-stories.*')) aria-current="page" @endif>愛的見證</a></li>
                <li><a href="{{ route('about') }}" @if(request()->routeIs('about')) aria-current="page" @endif>關於愛諾園</a></li>
                <li><a href="{{ config('loveuno.links.devote') }}" rel="noopener">支持奉獻</a></li>
                <li><a href="{{ route('contact') }}" @if(request()->routeIs('contact')) aria-current="page" @endif>聯絡我們</a></li>
                <li><a href="{{ route('faq') }}" @if(request()->routeIs('faq')) aria-current="page" @endif>常見問題</a></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="side-menu-logout">登出</button>
                    </form>
                </li>
            </ul>
            @if ($me->is_admin)
                <h2 class="side-menu-sub">管理</h2>
                <ul class="side-menu-list side-menu-admin">
                    <li><a href="{{ route('admin.members') }}">會員管理</a></li>
                    @php($openFeedback = \App\Models\Feedback::where('status', 'open')->count())
                    <li><a href="{{ route('admin.feedback') }}">回報@if($openFeedback)（{{ $openFeedback }}<span class="visually-hidden"> 則還沒處理</span>）@endif</a></li>
                    <li><a href="{{ route('admin.photos') }}">審核照片</a></li>
                    <li><a href="{{ route('admin.contacts') }}">來信</a></li>
                    <li><a href="{{ route('admin.news') }}">公告</a></li>
                    <li><a href="{{ route('admin.meetups') }}">約會紀錄</a></li>
                    @php($pendingStories = \App\Models\LoveStory::where('status', 'pending')->count())
                    <li><a href="{{ route('admin.love-stories') }}">愛的見證審核@if($pendingStories)（{{ $pendingStories }}<span class="visually-hidden"> 篇待審核</span>）@endif</a></li>
                    @php($pendingVerify = \App\Models\Verification::where('status', 'pending')->count())
                    <li><a href="{{ route('admin.verifications') }}">審核認證@if($pendingVerify)（{{ $pendingVerify }}<span class="visually-hidden"> 件</span>）@endif</a></li>
                    @php($openReports = \App\Models\Report::where('status', 'open')->count())
                    <li><a href="{{ route('admin.reports') }}">檢舉@if($openReports)（{{ $openReports }}<span class="visually-hidden"> 件還沒處理</span>）@endif</a></li>
                </ul>
            @endif
        </nav>
        {{-- 設計稿選單最下面：社群圖示、服務條款｜隱私權政策、Serve by（連到協會） --}}
        @include('partials.social-links', ['class' => 'side-menu-social', 'light' => true])
        {{-- 手機頁首放不下繁简切換，放這裡（Salt Chang 在 Figma 留言：登入後也要能切換語言） --}}
        <p class="side-menu-script">@include('partials.script-toggle')</p>
        <p class="side-menu-legal"><a href="{{ route('terms') }}">服務條款</a><span aria-hidden="true">｜</span><a href="{{ route('privacy') }}">隱私權政策</a></p>
        <p class="side-menu-serve"><a href="{{ config('loveuno.links.ccnda') }}" rel="noopener">Serve by<br>Chinese Christian Network<br>Development Association</a></p>
    </div>
</div>
