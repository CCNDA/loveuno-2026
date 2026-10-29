@extends('layouts.app')

@section('content')
{{-- 歡迎頁，照設計稿 PageWelcome：MainBanner（我們需要您的支持＋支持奉獻）、FeatureCards 三張、LoveStory 四張＋「更多愛的故事」。
     登入／註冊按鈕放在最上面（設計稿在頁首）。 --}}
<section class="hero welcome-banner" aria-labelledby="hero-title">
    <div>
        <h1 id="hero-title">在信仰裡，認識另一半</h1>
        <p class="lead">全球華人的基督徒弟兄姊妹交友平台。「中華基督教網路發展協會」最新推出網路交友事工「愛諾園」，期待成為基督徒未婚男女交友、步入婚姻的媒合平台。</p>
        @auth
            <p><a class="button" href="{{ route('diaries.index') }}">看大家的心情日記</a></p>
        @else
            <p><a class="button" href="{{ route('login') }}">會員登入</a>@if (config('loveuno.registration_open')) <a class="button button-light" href="{{ route('register') }}">加入會員</a>@endif</p>
            <p class="hint">舊站的帳號可以直接登入。</p>
        @endauth
    </div>
    <img src="{{ asset('images/welcome-banner.svg') }}" alt="" width="375" height="234" fetchpriority="high">
</section>

<section aria-labelledby="features-title">
    <h2 id="features-title" class="visually-hidden">愛諾園的特色</h2>
    <ul class="cards">
        <li class="card">
            <h3>跨出教會聯誼 發現另一半</h3>
            <p>認識合拍屬靈伴侶、尋找交往對象</p>
        </li>
        <li class="card">
            <h3>嚴謹認證 安心交友</h3>
            <p>多重認證機制 優質交友環境</p>
        </li>
        <li class="card">
            <h3>交通團契 活出信仰</h3>
            <p>透過日記分享生活見證，互相代禱、彼此關心</p>
        </li>
    </ul>
</section>

@if ($stories->isNotEmpty())
    <section aria-labelledby="stories-title">
        <h2 id="stories-title">愛的見證</h2>
        <ul class="story-cards">
            @foreach ($stories as $story)
                @include('love-stories.partials.card')
            @endforeach
        </ul>
        <p><a href="{{ route('love-stories.index') }}">更多愛的故事</a></p>
    </section>
@endif

<section class="card donate" aria-labelledby="support-title">
    <h2 id="support-title">我們需要您的支持</h2>
    <p>愛諾園所有的服務都是免費的，網站的營運費用都來自弟兄姊妹的自由奉獻。</p>
    <p><a class="button" href="https://www.ccnda.org/devote/online" rel="noopener">支持奉獻<span class="visually-hidden">（到中華基督教網路發展協會的奉獻頁）</span></a></p>
</section>
@endsection
