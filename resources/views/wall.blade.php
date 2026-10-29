@extends('layouts.app')

@section('title', '日記牆')

@section('content')
<h1 class="visually-hidden">日記牆</h1>
@if ($newGifts > 0)
    <p class="notice"><a href="{{ route('gifts.index') }}">您收到 {{ $newGifts }} 份新禮物，去看看 →</a></p>
@endif

<section aria-labelledby="suggest-title">
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

@include('partials.welcome-new')

<section aria-labelledby="wall-title">
    <div class="wall-head">
        <h2 id="wall-title">心情日記</h2>
        <a class="button" href="{{ route('diaries.create') }}">寫日記</a>
    </div>
    @if ($diaries->isEmpty())
        <p>還沒有日記，寫第一篇吧。</p>
    @else
        <ol class="wall-list">
            @foreach ($diaries as $d)
                <li>
                    <article class="wall-card" id="d{{ $d->id }}" aria-labelledby="wall-d{{ $d->id }}">
                        <header class="wall-card-head">
                            @include('partials.avatar', ['user' => $d->user])
                            <div>
                                <p class="wall-author"><a href="{{ route('members.show', $d->user) }}">{{ $d->user->name }}</a></p>
                                <p class="meta"><time datetime="{{ $d->published_on->toDateString() }}">{{ $d->published_on->format('Y-m-d') }}</time>@if($d->is_prayer)・<span class="tag tag-prayer">代禱</span>@endif @if($d->visibility === 'private')・<span class="tag">只有自己看得到</span>@endif</p>
                            </div>
                        </header>
                        <h3 id="wall-d{{ $d->id }}" class="wall-title"><a href="{{ route('diaries.show', $d) }}">{{ $d->title }}</a></h3>
                        <p class="excerpt">{{ \Illuminate\Support\Str::limit($d->body, 120) }}</p>
                        @if ($d->imageVisibleTo(auth()->user()))
                            <img class="diary-image" src="{{ route('diaries.image', $d) }}?v={{ $d->image_updated_at?->timestamp }}" alt="{{ $d->user->name }}〈{{ $d->title }}〉附的照片" loading="lazy">
                        @endif
                        @include('diaries.partials.interact', ['from' => 'wall'])
                        @include('diaries.partials.wall-comments')
                    </article>
                </li>
            @endforeach
        </ol>
        <p><a href="{{ route('diaries.index') }}">看更多日記 →</a></p>
    @endif
</section>
@endsection
