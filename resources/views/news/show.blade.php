@extends('layouts.app')

@section('title', $item->title . '｜站務公告')
@section('main_class', 'wrap wrap-wide center-main page-card')

@section('content')
<p class="meta"><a href="{{ route('news.index') }}">站務公告</a></p>
<article aria-labelledby="news-title" class="news-article">
    {{-- 設計稿 站務公告_article 163:31161：上插圖、標題、站務公告標籤＋日期、內文 --}}
    <div class="about-hero news-hero"><img src="{{ asset('images/covers/cover-2.svg') }}" alt="" width="551" height="165"></div>
    <h1 id="news-title">{{ $item->title }}</h1>
    <p class="meta">
        @if ($item->isPublished())
            <span class="news-tag">站務公告</span> <time datetime="{{ $item->published_at->toIso8601String() }}">{{ $item->published_at->shown()->format('Y/n/j') }}</time>
        @else
            <span class="tag">還沒發佈（只有管理員看得到）</span>
        @endif
    </p>
    <div class="prose">{!! nl2br(e($item->body)) !!}</div>
</article>
@endsection
