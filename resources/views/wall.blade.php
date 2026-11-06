@extends('layouts.app')

@section('title', '生活日記牆')
@section('main_class', 'wrap wrap-wide')

@section('content')
{{-- 照設計稿 Figma 首頁（電腦版 Index 6:6038、手機版 Index - Mobile 216:8236；熊叔叔 2026-11-05 TG「有照著 figma 走嗎」→ A）：
     電腦版兩欄——左：好友推薦、生活日記牆；右：特殊節日、歡迎新朋友、廣告（300×250）。
     手機版一欄：好友推薦、特殊節日、生活日記牆、廣告、歡迎新朋友（右欄 display: contents，用 order 排）。
     篩選（全部人／只看朋友 × 全部／心情日記／代禱事項）設計稿沒有，熊叔叔 11/5 要的，放在標題下面一列小按鈕。 --}}
<h1 class="visually-hidden">生活日記牆</h1>
@if ($pendingDates > 0)
    <p class="notice"><a href="{{ route('meetups.index') }}">您有 {{ $pendingDates }} 個約會邀請等您回覆，去看看 →</a></p>
@endif
@if ($newGifts > 0)
    <p class="notice"><a href="{{ route('gifts.index') }}">您收到 {{ $newGifts }} 份新禮物，去看看 →</a></p>
@endif

<div class="home-grid">
    <section aria-labelledby="suggest-title" class="card home-suggest">
        <h2 id="suggest-title">好友推薦</h2>
        @if ($suggestions->isEmpty())
            <p class="hint">目前沒有符合您<a href="{{ route('center.preferences') }}">交友條件</a>的會員，可以把條件放寬一點。</p>
        @else
            <ul class="suggest-row">
                @foreach ($suggestions as $s)
                    <li>
                        <a href="{{ route('members.show', $s) }}" class="suggest-item">
                            @include('partials.avatar', ['user' => $s])
                            <span class="suggest-name">{{ $s->name }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section aria-labelledby="wall-title" class="card home-wall">
        <div class="wall-head">
            <h2 id="wall-title">生活日記牆</h2>
            <p class="wall-write">
                <a href="{{ route('diaries.create') }}" data-write-dialog="0">寫心情日記</a>
                <a href="{{ route('diaries.create', ['kind' => 'prayer']) }}" data-write-dialog="1">寫代禱事項</a>
            </p>
        </div>
        @php
            // 兩組篩選互相保留：換「看誰的」時，「看哪一種」不變
            $wallLink = fn (array $change) => route('home', array_filter(array_merge(
                ['scope' => $scope === 'friends' ? 'friends' : null, 'kind' => $kind === 'all' ? null : $kind], $change)));
            $kindLabel = ['all' => '日記', 'diary' => '心情日記', 'prayer' => '代禱事項'][$kind];
        @endphp
        <div class="wall-filters">
            <nav aria-label="生活日記牆：看誰的">
                <ul class="chip-group">
                    <li><a href="{{ $wallLink(['scope' => null]) }}" @if ($scope === 'all') aria-current="page" @endif>全部人</a></li>
                    <li><a href="{{ $wallLink(['scope' => 'friends']) }}" @if ($scope === 'friends') aria-current="page" @endif>只看朋友</a></li>
                </ul>
            </nav>
            <nav aria-label="生活日記牆：看哪一種">
                <ul class="chip-group">
                    <li><a href="{{ $wallLink(['kind' => null]) }}" @if ($kind === 'all') aria-current="page" @endif>全部</a></li>
                    <li><a href="{{ $wallLink(['kind' => 'diary']) }}" @if ($kind === 'diary') aria-current="page" @endif>心情日記</a></li>
                    <li><a href="{{ $wallLink(['kind' => 'prayer']) }}" @if ($kind === 'prayer') aria-current="page" @endif>代禱事項</a></li>
                </ul>
            </nav>
        </div>
        @if ($diaries->isEmpty())
            <p class="wall-empty">{{ $scope === 'friends' ? '朋友們還沒有' . $kindLabel . '。' : '還沒有' . $kindLabel . '，寫第一篇吧。' }}</p>
        @else
            <ol class="wall-list">
                @include('partials.wall-items')
            </ol>
            @include('partials.wall-more')
        @endif
    </section>

    <div class="home-side">
        @include('partials.occasions')
        @include('partials.welcome-new')
        @include('partials.ad-slot')
    </div>
</div>

<p class="visually-hidden" aria-live="polite" id="wall-status"></p>
<script src="{{ asset('js/wall-scroll.js') }}?v={{ $appVersion }}" defer></script>

{{-- 設計稿電腦版右下角浮動的寫日記按鈕（手機版用底部選單的「寫日記」，所以 640px 以下不顯示） --}}
<a class="fab-write" href="{{ route('diaries.create') }}" data-write-dialog="0"><img src="{{ asset('images/brand/nav-write.svg') }}" alt="" width="28" height="28"><span class="visually-hidden">寫日記</span></a>

{{-- 寫日記對話框（設計稿 235:8504「心情日記 / 需要代禱」） --}}
@php($draft = new \App\Models\Diary(['visibility' => 'public', 'is_prayer' => false]))
<dialog id="write-dialog" class="write-dialog" aria-labelledby="write-dialog-title">
    <div class="write-dialog-head">
        <h2 id="write-dialog-title">心情日記 / 需要代禱</h2>
        <button type="button" class="dialog-close" data-close><span aria-hidden="true">×</span><span class="visually-hidden">關閉</span></button>
    </div>
    <form method="POST" action="{{ route('diaries.store') }}" class="diary-form" enctype="multipart/form-data" novalidate>
        @csrf
        @include('diaries.partials.form-fields', ['diary' => $draft, 'px' => 'w-'])
        @include('diaries.partials.visibility-field', ['diary' => $draft, 'px' => 'w-'])
        {{-- 設計稿「預覽」：在這裡看一下排起來的樣子，不會存 --}}
        <section class="write-preview" id="write-preview" hidden aria-labelledby="write-preview-title">
            <h3 id="write-preview-title">預覽</h3>
            <p class="write-preview-tag"></p>
            <p class="write-preview-heading"></p>
            <p class="write-preview-body"></p>
            <img class="write-preview-img" alt="" hidden>
        </section>
        <p class="write-dialog-actions"><button type="button" class="pill-link write-preview-btn" aria-controls="write-preview" aria-expanded="false" hidden>預覽</button> <button type="submit" class="button pill-submit">發佈</button></p>
    </form>
</dialog>
<script src="{{ asset('js/write-dialog.js') }}?v={{ $appVersion }}" defer></script>
@endsection
