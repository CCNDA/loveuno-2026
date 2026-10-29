<!DOCTYPE html>
<html lang="zh-Hant-TW">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@hasSection('title')@yield('title')｜@endif愛諾園</title>
    <meta name="description" content="愛諾園：基督徒單身交友，用信仰認識彼此。">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ $appVersion }}">
</head>
<body>
    <a class="skip-link" href="#main">跳到主要內容</a>

    <p class="demo-banner" role="note">這是新版愛諾園的<strong>展示版</strong>，裡面的會員與日記都是示範資料。</p>

    <header class="site-header">
        <div class="wrap header-row">
            <a class="brand" href="{{ route('home') }}">愛諾園</a>
            <nav aria-label="主選單">
                <ul class="nav">
                    <li><a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>首頁</a></li>
                    @auth
                        <li><a href="{{ route('diaries.index') }}" @if(request()->routeIs('diaries.*')) aria-current="page" @endif>心情日記</a></li>
                        <li><a href="{{ route('members.index') }}" @if(request()->routeIs('members.index')) aria-current="page" @endif>認識朋友</a></li>
                        <li><a href="{{ route('me') }}" @if(request()->routeIs('members.show') && request()->route('user')?->is(auth()->user())) aria-current="page" @endif>我的檔案</a></li>
                        @if (auth()->user()->is_admin)
                            <li><a href="{{ route('admin.photos') }}" @if(request()->routeIs('admin.*')) aria-current="page" @endif>審核照片</a></li>
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

    <footer class="site-footer">
        <div class="wrap">
            <p>社團法人中華基督教網路發展協會 CCNDA</p>
            <p class="version">版本 {{ $appVersion }}</p>
        </div>
    </footer>
</body>
</html>
