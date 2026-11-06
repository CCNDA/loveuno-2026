@extends('layouts.app')

@section('title', $diary->title)

@section('content')
<article class="diary" id="d{{ $diary->id }}">
    <h1>{{ $diary->title }}</h1>
    <p class="meta">
        <a href="{{ route('members.show', $diary->user) }}">{{ $diary->user->name }}</a>
        ・<time datetime="{{ $diary->published_on->toDateString() }}">{{ $diary->published_on->format('Y 年 n 月 j 日') }}</time>
        ・<span class="tag {{ $diary->is_prayer ? 'tag-prayer' : 'tag-diary' }}">{{ $diary->is_prayer ? '代禱事項' : '心情日記' }}</span>
        @if ($diary->visibility === 'private')・<span class="tag">只有自己看得到</span>@endif
    </p>
    <div class="diary-body">{!! nl2br(e($diary->body)) !!}</div>
    @if ($diary->imageVisibleTo(auth()->user()))
        <img class="diary-image" src="{{ route('diaries.image', $diary) }}?v={{ $diary->image_updated_at?->timestamp }}"
             alt="{{ $diary->user->name }} 這篇日記附的照片" loading="lazy">
    @endif
    @include('diaries.partials.interact', ['d' => $diary, 'from' => 'show'])
</article>

@if ($diary->isOwnedBy(auth()->user()))
    <p><a href="{{ route('diaries.edit', $diary) }}">修改這篇</a></p>
@endif

<section aria-labelledby="comments-title" class="comments">
    <h2 id="comments-title" tabindex="-1">留言（{{ $comments->count() }}）</h2>

    @if ($diary->commentableBy(auth()->user()))
    <form method="POST" action="{{ route('diaries.comments.store', $diary) }}" class="form comment-form" novalidate>
        @csrf
        @include('partials.errors', ['title' => '留言還沒送出'])
        <div class="field">
            <label for="body">寫留言</label>
            <p id="body-hint" class="hint">500 字以內。看得到這篇日記的會員都看得到留言。</p>
            <textarea id="body" name="body" rows="3" maxlength="500" required
                      aria-describedby="body-hint @error('body') body-error @enderror"
                      @error('body') aria-invalid="true" @enderror>{{ old('body') }}</textarea>
            @error('body')<p id="body-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="button">送出留言</button>
    </form>
    @else
    <p class="hint">成為 {{ $diary->user->name }} 的朋友以後才能留言。</p>
    @endif

    @if ($comments->isEmpty())
        <p>還沒有留言。</p>
    @else
        <ol class="comment-list">
            @foreach ($comments as $c)
                <li class="comment-item" id="comment-{{ $c->id }}">
                    @include('partials.avatar', ['user' => $c->user, 'size' => 'sm'])
                    <div class="comment-main">
                        <p class="comment-head">
                            <a href="{{ route('members.show', $c->user) }}">{{ $c->user->name }}</a>
                            <time class="meta" datetime="{{ $c->created_at->toIso8601String() }}">{{ $c->ago() }}</time>
                        </p>
                        <p class="comment-body">{!! nl2br(e($c->body)) !!}</p>
                        @if ($c->deletableBy(auth()->user()))
                            <form method="POST" action="{{ route('diaries.comments.destroy', [$diary, $c]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="link-button">刪掉這則留言<span class="visually-hidden">（{{ $c->user->name }}：{{ \Illuminate\Support\Str::limit($c->body, 20) }}）</span></button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</section>

<p><a href="{{ route('home') }}">← 回生活日記牆</a></p>
@endsection
