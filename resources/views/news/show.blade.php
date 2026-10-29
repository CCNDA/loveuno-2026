@extends('layouts.app')

@section('title', $item->title . '｜站務公告')

@section('content')
<p class="meta"><a href="{{ route('news.index') }}">站務公告</a></p>
<article aria-labelledby="news-title">
    <h1 id="news-title">{{ $item->title }}</h1>
    <p class="meta">
        @if ($item->isPublished())
            <span class="tag">站務公告</span> <time datetime="{{ $item->published_at->toIso8601String() }}">{{ $item->published_at->shown()->format('Y/n/j') }}</time>
        @else
            <span class="tag">還沒發佈（只有管理員看得到）</span>
        @endif
    </p>
    <div class="prose">{!! nl2br(e($item->body)) !!}</div>
</article>
@endsection
