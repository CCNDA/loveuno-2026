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
    @auth<script src="{{ asset('js/side-menu.js') }}?v={{ $appVersion }}" defer></script>@endauth
    <meta name="theme-color" content="#E5BBA2">
</head>
<body @auth class="has-bottom-nav has-side-menu" @endauth>
    @auth @php($unreadNotes = \App\Models\Note::unreadCountFor(auth()->user())) @endauth
    <a class="skip-link" href="#main">跳到主要內容</a>

    <p class="demo-banner" role="note">這是新版愛諾園的<strong>展示版</strong>，裡面的會員與日記都是示範資料。</p>

    <header class="site-header">
        <div class="wrap header-row">
            {{-- Logo 取自 loveuno-web 版型（public/images/brand/README.txt）；白色字的 Logo 屬於「標誌」，不受文字對比規定 --}}
            @auth
                {{-- 設計稿手機頁首（Header/Mobile）左邊的選單按鈕，打開側邊選單（Figma 1156:11227） --}}
                <button type="button" class="menu-toggle" aria-expanded="false" aria-controls="side-menu">
                    <svg width="28" height="28" viewBox="0 0 28 28" aria-hidden="true" focusable="false"><path d="M4 7h20M4 14h20M4 21h14" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" fill="none"/></svg>
                    <span class="visually-hidden">選單</span>
                </button>
            @endauth
            <a class="brand" href="{{ route('home') }}"><img src="{{ asset('images/brand/logo.svg') }}" alt="愛諾園 首頁" width="115" height="40"></a>
            @auth
                {{-- 設計稿頁首右邊（Figma Profile 142:22363）：通知鈴（先接傳紙條未讀）、頭像＋暱稱（我的檔案） --}}
                <ul class="header-icons">
                    <li><a href="{{ route('notes.index') }}" class="header-icon"><svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 17V11a6 6 0 0 1 12 0v6l1.5 2h-15z M10 21h4" stroke="currentColor" stroke-width="2" stroke-linejoin="round" fill="none"/></svg><span class="visually-hidden">傳紙條</span>@if($unreadNotes > 0)<span class="badge header-badge" aria-hidden="true">{{ $unreadNotes }}</span><span class="visually-hidden">，有 {{ $unreadNotes }} 則未讀</span>@endif</a></li>
                    <li><a href="{{ route('me') }}" class="header-me">@include('partials.avatar', ['user' => auth()->user(), 'size' => 'sm'])<span class="header-me-name">{{ auth()->user()->name }}<span class="visually-hidden">（我的檔案）</span></span></a></li>
                </ul>
            @endauth
            <nav aria-label="主選單">
                <ul class="nav">
                    @guest
                        <li><a class="header-login" href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>登入@if (config('loveuno.registration_open'))<span aria-hidden="true">/</span><span class="visually-hidden">或</span>註冊@endif</a></li>
                    @endguest
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
    @auth @include('partials.side-menu') @endauth

    <main id="main" tabindex="-1" class="@yield('main_class', 'wrap')">
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
        <div class="wrap wrap-wide footer-row">
        <div class="footer-brand">
            <img src="{{ asset('images/brand/footer-logo.svg') }}" alt="愛諾園 LoveUNO" width="207" height="72" loading="lazy">
            {{-- 設計稿四個圓形圖示：Facebook、LINE、Telegram、星號；LINE 和星號還不知道連到哪，先放協會的 Facebook、Telegram --}}
            <ul class="footer-social">
                <li><a href="https://www.facebook.com/CCNDA" rel="noopener"><img src="{{ asset('images/brand/social-facebook.svg') }}" alt="協會 Facebook" width="40" height="40" loading="lazy"></a></li>
                <li><a href="https://t.me/ccnda" rel="noopener"><img src="{{ asset('images/brand/social-telegram.svg') }}" alt="協會 Telegram" width="40" height="40" loading="lazy"></a></li>
            </ul>
        </div>
        <nav aria-label="網站資訊" class="footer-links">
            <ul>
                {{-- 設計稿 MainFooter；下載 APP 四項不做（新版是網頁，見 #42；熊叔叔 11/5「下載APP現在不用」） --}}
                <li><a href="{{ route('about') }}">關於愛諾園</a></li>
                <li><a href="{{ route('news.index') }}">站務公告</a></li>
                <li><a href="{{ route('love-stories.index') }}">愛的見證</a></li>
                <li><a href="{{ route('faq') }}">常見問題</a></li>
                <li><a href="{{ route('contact') }}">聯絡我們</a></li>
                {{-- 回報問題／建議（熊叔叔 11/2 fb-A）：每一頁都有（沒登入也有），帶著現在這一頁；開新分頁，原頁寫到一半的不會不見；回報頁本身不放 --}}
                @unless (request()->routeIs('feedback.*'))<li><a href="{{ route('feedback.create', ['from' => request()->getRequestUri()]) }}" target="_blank" rel="noopener">回報問題／建議<span class="visually-hidden">（開新分頁）</span></a></li>@endunless
                <li><a href="{{ route('terms') }}">網站服務條款</a></li>
                <li><a href="{{ route('privacy') }}">隱私權政策</a></li>
                <li><a href="https://www.ccnda.org/devote/online" rel="noopener">奉獻支持</a></li>
            </ul>
            <ul>
                <li><a href="https://www.ccnda.org" rel="noopener">網路發展協會</a></li>
                <li><a href="https://church.oursweb.net" rel="noopener">教會名錄</a></li>
                <li><a href="https://oursweb.net" rel="noopener">Oursweb</a></li>
                <li><a href="https://www.taiwanbible.com/web/first.jsp" rel="noopener">台灣聖經網</a></li>
            </ul>
        </nav>
        </div>
        <div class="wrap wrap-wide footer-bottom">
            <p>網站服務由 中華基督教網路發展協會 提供　<span lang="en">Serve by Chinese Christian Network Development Association</span></p>
            <p class="version">版本 {{ $appVersion }}</p>
        </div>
    </footer>
</body>
</html>
