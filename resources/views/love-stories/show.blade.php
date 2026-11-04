@extends('layouts.app')

@section('title', $story->title . '｜愛的見證')

@section('content')
{{-- 設計稿 PageViewLoveStory：標題、兩位的頭像＋名字（名字旁有已認證徽章）、內文、照片、「我要投稿」 --}}
<p class="meta"><a href="{{ route('love-stories.index') }}">愛的見證</a></p>
<article aria-labelledby="story-title">
    <h1 id="story-title">{{ $story->title }}</h1>
    @if ($story->status !== 'approved')
        <p class="notice" role="status">{{ $story->status === 'pending' ? '審核中：管理員看過以後才會公開，現在只有您和管理員看得到。' : '這篇沒有公開。' }}</p>
    @endif
    @auth
    <ul class="story-couple">
        @foreach ([$story->male, $story->female] as $person)
            <li>
                @if ($person)
                    @include('partials.avatar', ['user' => $person, 'size' => 'md'])
                    <span><a href="{{ route('members.show', $person) }}">{{ $person->name }}</a>
                        @if ($person->approvedVerifications()->isNotEmpty()) <img src="{{ asset('images/brand/verified-badge.svg') }}" alt="已認證" width="15" height="15" class="verified-badge">@endif</span>
                @else
                    <span class="meta">（已經離開愛諾園的會員）</span>
                @endif
            </li>
        @endforeach
    </ul>
    @else
    <p class="meta">會員<a href="{{ route('login') }}">登入</a>後可以看到兩位的檔案。</p>
    @endauth
    @if ($story->photo_path)
        <img src="{{ route('love-stories.photo', $story) }}" alt="{{ $story->title }}的照片" class="story-photo" loading="lazy">
    @endif
    <div class="prose">{!! nl2br(e($story->body)) !!}</div>
</article>
<section class="card donate" aria-labelledby="submit-title">
    <h2 id="submit-title">投稿愛的見證</h2>
    <p>您在愛諾園找到了另一半嗎？歡迎投稿愛的見證，鼓勵其他還在愛諾園努力的朋友們！</p>
    <p><a class="button" href="{{ route('love-stories.create') }}">我要投稿</a></p>
</section>
@endsection
