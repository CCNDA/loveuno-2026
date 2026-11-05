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
                <a href="{{ route('diaries.create') }}">寫心情日記</a>
                <a href="{{ route('diaries.create', ['kind' => 'prayer']) }}">寫代禱事項</a>
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
                @foreach ($diaries as $d)
                    <li>
                        <article class="wall-card" id="d{{ $d->id }}" aria-labelledby="wall-d{{ $d->id }}">
                            <header class="wall-card-head">
                                @include('partials.avatar', ['user' => $d->user])
                                <div>
                                    <p class="wall-author"><a href="{{ route('members.show', $d->user) }}">{{ $d->user->name }}</a>@if ($d->user->approvedVerifications()->isNotEmpty()) <img src="{{ asset('images/brand/verified-badge.svg') }}" alt="已認證" width="15" height="15" class="verified-badge">@endif</p>
                                    <p class="meta"><time datetime="{{ $d->published_on->toDateString() }}">{{ $d->published_on->format('Y-m-d') }}</time>・<span class="tag {{ $d->is_prayer ? 'tag-prayer' : 'tag-diary' }}">{{ $d->is_prayer ? '代禱事項' : '心情日記' }}</span> @if($d->visibility === 'private')・<span class="tag">只有自己看得到</span>@endif</p>
                                </div>
                            </header>
                            <h3 id="wall-d{{ $d->id }}" class="wall-title"><a href="{{ route('diaries.show', $d) }}">{{ $d->title }}</a></h3>
                            <p class="excerpt">{{ \Illuminate\Support\Str::limit($d->body, 120) }}@if (mb_strlen($d->body) > 120) <a href="{{ route('diaries.show', $d) }}" class="read-more">查看更多<span class="visually-hidden">：{{ $d->title }}</span></a>@endif</p>
                            @if ($d->imageVisibleTo(auth()->user()))
                                <img class="diary-image" src="{{ route('diaries.image', $d) }}?v={{ $d->image_updated_at?->timestamp }}" alt="{{ $d->user->name }}〈{{ $d->title }}〉附的照片" loading="lazy">
                            @endif
                            @include('diaries.partials.interact', ['from' => 'wall', 'wallScope' => $scope, 'wallKind' => $kind])
                            @include('diaries.partials.wall-comments', ['wallScope' => $scope, 'wallKind' => $kind])
                        </article>
                    </li>
                @endforeach
            </ol>
            <p class="wall-more"><a href="{{ route('diaries.index') }}">看更多日記 →</a></p>
        @endif
    </section>

    <div class="home-side">
        @include('partials.occasions')
        @include('partials.welcome-new')
        @include('partials.ad-slot')
    </div>
</div>

{{-- 設計稿電腦版右下角浮動的寫日記按鈕（手機版用底部選單的「寫日記」，所以 640px 以下不顯示） --}}
<a class="fab-write" href="{{ route('diaries.create') }}"><img src="{{ asset('images/brand/nav-write.svg') }}" alt="" width="28" height="28"><span class="visually-hidden">寫日記</span></a>
@endsection
