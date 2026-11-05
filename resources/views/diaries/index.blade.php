@extends('layouts.app')

@section('title', ($mine ?? false) ? '我的日記' : '心情日記')

@section('main_class', 'wrap wrap-wide center-main page-card diary-page')

@section('content')
<h1>{{ ($mine ?? false) ? '我的日記' : '心情日記' }}</h1>
<p class="diary-page-write"><a class="button pill-submit" href="{{ route('diaries.create') }}" data-write-dialog="0">寫日記</a></p>

@if ($diaries->isEmpty())
    <p>還沒有日記。</p>
@else
    <ol class="diary-list">
        @foreach ($diaries as $diary)
            <li class="diary-item">
                <article id="d{{ $diary->id }}" aria-labelledby="diary-{{ $diary->id }}">
                    <h2 id="diary-{{ $diary->id }}" class="diary-title">
                        <a href="{{ route('diaries.show', $diary) }}">{{ $diary->title }}</a>
                    </h2>
                    <p class="meta">
                        <a href="{{ route('members.show', $diary->user) }}">{{ $diary->user->name }}</a>
                        ・<time datetime="{{ $diary->published_on->toDateString() }}">{{ $diary->published_on->format('Y 年 n 月 j 日') }}</time>
                        @if ($diary->is_prayer)・<span class="tag tag-prayer">代禱</span>@endif
                        @if ($diary->visibility === 'private')・<span class="tag">只有自己看得到</span>@endif
                    </p>
                    <p class="excerpt">{{ \Illuminate\Support\Str::limit($diary->body, 90) }}</p>
                    @include('diaries.partials.interact', ['d' => $diary, 'from' => 'list', 'page' => $diaries->currentPage()])
                </article>
            </li>
        @endforeach
    </ol>

    {{ $diaries->links('partials.pagination') }}
@endif
@endsection
