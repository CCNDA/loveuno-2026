<!DOCTYPE html>
<html lang="zh-Hant-TW" class="fs-{{ auth()->user()?->font_size ?? 'medium' }}@if (auth()->user()?->font_family === 'huninn') ff-huninn @endif">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@hasSection('title')@yield('title')｜@endif愛諾園</title>
    <meta name="description" content="愛諾園：基督徒單身交友，用信仰認識彼此。">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ $appVersion }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/app/icon-192.png') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="愛諾園">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (auth()->user()?->font_family === 'huninn')<link rel="stylesheet" href="{{ asset('css/huninn.css') }}?v={{ $appVersion }}">@endif
    <script src="{{ asset('js/tz.js') }}?v={{ $appVersion }}" defer></script>
    <meta name="theme-color" content="#E5BBA2">
</head>
<body @auth class="has-bottom-nav" @endauth>
    @auth @php($unreadNotes = \App\Models\Note::unreadCountFor(auth()->user())) @endauth
    <a class="skip-link" href="#main">跳到主要內容</a>

    <p class="demo-banner" role="note">這是新版愛諾園的<strong>展示版</strong>，裡面的會員與日記都是示範資料。</p>

    <header class="site-header">
        <div class="wrap header-row">
            {{-- Logo 取自 loveuno-web 版型（public/images/brand/README.txt）；白色字的 Logo 屬於「標誌」，不受文字對比規定 --}}
            <a class="brand" href="{{ route('home') }}"><img src="{{ asset('images/brand/logo.svg') }}" alt="愛諾園 首頁" width="115" height="40"></a>
            <nav aria-label="主選單">
                <ul class="nav">
                    <li><a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>首頁</a></li>
                    @auth
                        <li class="nav-dup"><a href="{{ route('diaries.index') }}" @if(request()->routeIs('diaries.*')) aria-current="page" @endif>心情日記</a></li>
                        <li class="nav-dup"><a href="{{ route('members.index') }}" @if(request()->routeIs('members.index')) aria-current="page" @endif>認識朋友</a></li>
                        <li class="nav-dup"><a href="{{ route('me') }}" @if(request()->routeIs('members.show') && request()->route('user')?->is(auth()->user())) aria-current="page" @endif>我的檔案</a></li>
                        <li class="nav-dup"><a href="{{ route('notes.index') }}" @if(request()->routeIs('notes.*')) aria-current="page" @endif>傳紙條@if($unreadNotes > 0) <span class="badge" aria-hidden="true">{{ $unreadNotes }}</span><span class="visually-hidden">，有 {{ $unreadNotes }} 則未讀</span>@endif</a></li>
                        <li class="nav-dup"><a href="{{ route('center.account') }}" @if(request()->is('center*', 'me/edit', 'me/photo')) aria-current="page" @endif>會員中心</a></li>
                        @if (auth()->user()->is_admin)
                            <li><a href="{{ route('admin.photos') }}" @if(request()->routeIs('admin.photos')) aria-current="page" @endif>審核照片</a></li>
                            <li><a href="{{ route('admin.contacts') }}" @if(request()->routeIs('admin.contacts')) aria-current="page" @endif>來信</a></li>
                            <li><a href="{{ route('admin.news') }}" @if(request()->routeIs('admin.news*')) aria-current="page" @endif>公告</a></li>
                            <li><a href="{{ route('admin.meetups') }}" @if(request()->routeIs('admin.meetups')) aria-current="page" @endif>約會紀錄</a></li>
                            @php($pendingStories = \App\Models\LoveStory::where('status', 'pending')->count())
                            <li><a href="{{ route('admin.love-stories') }}" @if(request()->routeIs('admin.love-stories')) aria-current="page" @endif>愛的見證@if($pendingStories)（{{ $pendingStories }}<span class="visually-hidden"> 篇待審核</span>）@endif</a></li>
                            @php($pendingVerify = \App\Models\Verification::where('status', 'pending')->count())
                            <li><a href="{{ route('admin.verifications') }}" @if(request()->routeIs('admin.verifications')) aria-current="page" @endif>審核認證@if($pendingVerify)（{{ $pendingVerify }}<span class="visually-hidden"> 件</span>）@endif</a></li>
                            @php($openReports = \App\Models\Report::where('status', 'open')->count())
                            <li><a href="{{ route('admin.reports') }}" @if(request()->routeIs('admin.reports')) aria-current="page" @endif>檢舉@if($openReports)（{{ $openReports }}<span class="visually-hidden"> 件還沒處理</span>）@endif</a></li>
                        @endif
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="link-button">登出</button>
                            </form>
                        </li>
                    @else
                        <li><a href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>登入</a></li>
                        @if (config('loveuno.registration_open'))
                            <li><a href="{{ route('register') }}" @if(request()->routeIs('register')) aria-current="page" @endif>加入會員</a></li>
                        @endif
                    @endauth
                    <li>
                        @if (session('script') === 'hans')
                            <a href="{{ route('script', 'hant') }}" data-keep-script lang="zh-Hant" class="script-toggle">繁體<span class="visually-hidden">（切換成繁體中文）</span></a>
                        @else
                            <a href="{{ route('script', 'hans') }}" data-keep-script lang="zh-Hans" class="script-toggle">简体<span class="visually-hidden">（切换成简体中文）</span></a>
                        @endif
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <main id="main" tabindex="-1" class="wrap">
        @if (session('status'))
            <p class="notice" role="status">{{ session('status') }}</p>
        @endif
        @yield('content')
    </main>

    @auth
        {{-- 手機底部選單（照 loveuno-web 切版：日記牆／寫日記／認識朋友／傳紙條／我的檔案） --}}
        <nav class="bottom-nav" aria-label="手機快速選單">
            <ul>
                <li><a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif><img class="bn-icon" src="{{ asset('images/brand/nav-wall.svg') }}" alt="" width="24" height="24">日記牆</a></li>
                <li><a href="{{ route('diaries.create') }}" @if(request()->routeIs('diaries.create')) aria-current="page" @endif><img class="bn-icon" src="{{ asset('images/brand/nav-write.svg') }}" alt="" width="24" height="24">寫日記</a></li>
                <li><a href="{{ route('members.index') }}" @if(request()->routeIs('members.index')) aria-current="page" @endif><img class="bn-icon" src="{{ asset('images/brand/nav-friends.svg') }}" alt="" width="24" height="24">認識朋友</a></li>
                <li><a href="{{ route('notes.index') }}" @if(request()->routeIs('notes.*')) aria-current="page" @endif><img class="bn-icon" src="{{ asset('images/brand/nav-notes.svg') }}" alt="" width="24" height="24">傳紙條@if($unreadNotes > 0)<span class="badge bn-badge" aria-hidden="true">{{ $unreadNotes }}</span><span class="visually-hidden">，有 {{ $unreadNotes }} 則未讀</span>@endif</a></li>
                <li><a href="{{ route('me') }}" @if(request()->routeIs('members.show', 'profile.edit', 'photo.edit') && (! request()->route('user') || request()->route('user')->is(auth()->user()))) aria-current="page" @endif><img class="bn-icon" src="{{ asset('images/brand/nav-me.svg') }}" alt="" width="24" height="24">我的檔案</a></li>
            </ul>
        </nav>
    @endauth

    <footer class="site-footer">
        <nav aria-label="網站資訊" class="footer-links">
            <ul>
                {{-- 設計稿 MainFooter；下載 APP 兩項不做（新版是網頁，見 #42） --}}
                <li><a href="{{ route('about') }}">關於愛諾園</a></li>
                <li><a href="{{ route('news.index') }}">站務公告</a></li>
                <li><a href="{{ route('love-stories.index') }}">愛的見證</a></li>
                <li><a href="{{ route('faq') }}">常見問題</a></li>
                <li><a href="{{ route('contact') }}">聯絡我們</a></li>
                <li><a href="{{ route('terms') }}">網站服務條款</a></li>
                <li><a href="{{ route('privacy') }}">隱私權政策</a></li>
                <li><a href="https://www.ccnda.org/devote/online" rel="noopener">奉獻支持</a></li>
            </ul>
            <ul>
                <li><a href="https://www.ccnda.org" rel="noopener">網路發展協會</a></li>
                <li><a href="https://church.oursweb.net" rel="noopener">教會名錄</a></li>
                <li><a href="https://www.taiwanbible.com/web/first.jsp" rel="noopener">台灣聖經網</a></li>
            </ul>
        </nav>
        <div class="wrap">
            <p>社團法人中華基督教網路發展協會 CCNDA</p>
            <p class="version">版本 {{ $appVersion }}</p>
        </div>
    </footer>
</body>
</html>
