@extends('layouts.app')

@section('title', $diary->title)

@section('content')
<article class="diary">
    <h1>{{ $diary->title }}</h1>
    <p class="meta">
        <a href="{{ route('members.show', $diary->user) }}">{{ $diary->user->name }}</a>
        ・<time datetime="{{ $diary->published_on->toDateString() }}">{{ $diary->published_on->format('Y 年 n 月 j 日') }}</time>
        @if ($diary->visibility === 'private')・<span class="tag">只有自己看得到</span>@endif
    </p>
    <div class="diary-body">{!! nl2br(e($diary->body)) !!}</div>
</article>

@if ($diary->isOwnedBy(auth()->user()))
    <p><a href="{{ route('diaries.edit', $diary) }}">修改這篇</a></p>
@endif
<p><a href="{{ route('diaries.index') }}">← 回心情日記</a></p>
@endsection
