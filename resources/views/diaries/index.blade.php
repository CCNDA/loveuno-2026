@extends('layouts.app')

@section('title', '心情日記')

@section('content')
<h1>心情日記</h1>
<p><a class="button" href="{{ route('diaries.create') }}">寫日記</a></p>

@if ($diaries->isEmpty())
    <p>還沒有日記。</p>
@else
    <ol class="diary-list">
        @foreach ($diaries as $diary)
            <li class="diary-item">
                <article aria-labelledby="diary-{{ $diary->id }}">
                    <h2 id="diary-{{ $diary->id }}" class="diary-title">
                        <a href="{{ route('diaries.show', $diary) }}">{{ $diary->title }}</a>
                    </h2>
                    <p class="meta">
                        <a href="{{ route('members.show', $diary->user) }}">{{ $diary->user->name }}</a>
                        ・<time datetime="{{ $diary->published_on->toDateString() }}">{{ $diary->published_on->format('Y 年 n 月 j 日') }}</time>
                        @if ($diary->visibility === 'private')・<span class="tag">只有自己看得到</span>@endif
                    </p>
                    <p class="excerpt">{{ \Illuminate\Support\Str::limit($diary->body, 90) }}</p>
                </article>
            </li>
        @endforeach
    </ol>

    {{ $diaries->links('partials.pagination') }}
@endif
@endsection
