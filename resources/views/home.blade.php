@extends('layouts.app')

@section('main_class', 'landing')

@section('content')
{{-- 沒登入的封面頁，照設計稿 Figma Index2 658:10391（電腦版）、Index - Mobile 803:16909（手機版）（熊叔叔 2026-11-05 TG「先把封面處理好」）：
     米黃大橫幅（我們需要您的支持＋支持奉獻＋插圖）→ 三張特色卡（一半疊在橫幅上）→ 愛的見證兩欄四篇。登入／註冊在頁首。 --}}
<section class="landing-hero" aria-labelledby="hero-title">
    <div class="wrap wrap-wide landing-hero-row">
        <div class="landing-hero-text">
            <h1 id="hero-title">我們需要您的支持</h1>
            <p>全球華人的基督徒弟兄姊妹交友平台。「中華基督教網路發展協會」2024年最新推出網路交友事工「愛諾園」，期待成為基督徒未婚男女交友、步入婚姻的媒合平台。</p>
            <p><a class="button" href="{{ config('loveuno.links.devote') }}" rel="noopener">支持奉獻<span class="visually-hidden">（到中華基督教網路發展協會的奉獻頁）</span></a></p>
            @auth
                <p><a href="{{ route('diaries.index') }}">看大家的心情日記 →</a></p>
            @endauth
        </div>
        <img class="landing-hero-img" src="{{ asset('images/welcome-hero.svg') }}" alt="" width="763" height="389" fetchpriority="high">
    </div>
</section>

<section class="wrap wrap-wide landing-features" aria-labelledby="features-title">
    <h2 id="features-title" class="visually-hidden">愛諾園的特色</h2>
    <ul>
        <li class="feature-card">
            <h3>跨出教會<br>聯誼發現<br>另一半</h3>
            <p>認識屬靈伴侶<br>尋找交往對象</p>
            <img src="{{ asset('images/feature-1.svg') }}" alt="" width="199" height="255" loading="lazy">
        </li>
        <li class="feature-card">
            <h3>嚴謹認證<br>安心交友</h3>
            <p>多重認證機制<br>優質交友環境</p>
            <img src="{{ asset('images/feature-2.svg') }}" alt="" width="218" height="227" loading="lazy">
        </li>
        <li class="feature-card">
            <h3>交通團契<br>活出信仰</h3>
            <p>透過日記分享生活見證，互相代禱、彼此關心</p>
            <img src="{{ asset('images/feature-3.svg') }}" alt="" width="341" height="153" loading="lazy">
        </li>
    </ul>
</section>

@if ($stories->isNotEmpty())
    <section class="wrap wrap-wide landing-stories" aria-labelledby="stories-title">
        <h2 id="stories-title">愛的見證</h2>
        <ul class="landing-story-list">
            @foreach ($stories as $story)
                <li class="landing-story">
                    @if ($story->photo_path)
                        <img src="{{ route('love-stories.photo', $story) }}" alt="" width="250" height="250" loading="lazy">
                    @endif
                    <div class="landing-story-text">
                        <h3>{{ $story->title }}</h3>
                        <p>{{ $story->excerpt() }}</p>
                        <a class="pill-link" href="{{ route('love-stories.show', $story) }}">更多愛的故事<span class="visually-hidden">：{{ $story->title }}</span></a>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
@endif
@endsection
