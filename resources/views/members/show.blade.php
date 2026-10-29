@extends('layouts.app')

@section('title', $member->name)

@section('content')
<div class="member-head">
    @include('partials.avatar', ['user' => $member, 'size' => 'lg'])
    <h1>{{ $member->name }}@if($isMe) <span class="tag">我</span>@endif</h1>
</div>
@if ($isMe)
    <p><a href="{{ route('photo.edit') }}">換大頭照</a>@if($member->profile?->photo_status === 'pending')（審核中）@endif</p>
@endif

@php($p = $member->profile)
@if (! $p)
    <p>這位會員還沒有填個人檔案。</p>
@else
    <dl class="profile">
        @if ($p->gender !== '')<div><dt>性別</dt><dd>{{ $p->genderLabel() }}</dd></div>@endif
        @if ($p->ageGroup())<div><dt>年齡</dt><dd>{{ $p->ageGroup() }}</dd></div>@endif
        @if ($p->live_place !== '')<div><dt>居住地</dt><dd>{{ $p->live_place }}</dd></div>@endif
        @if ($p->job !== '')<div><dt>職業</dt><dd>{{ $p->job }}</dd></div>@endif
        @if ($p->church_visible && $p->church !== '')<div><dt>教會</dt><dd>{{ $p->church }}</dd></div>@endif
    </dl>

    @if ($p->intro)
        <section aria-labelledby="intro-title">
            <h2 id="intro-title">自我介紹</h2>
            <p class="prose">{!! nl2br(e($p->intro)) !!}</p>
        </section>
    @endif

    @if ($p->hope)
        <section aria-labelledby="hope-title">
            <h2 id="hope-title">期待認識的人</h2>
            <p class="prose">{!! nl2br(e($p->hope)) !!}</p>
        </section>
    @endif
@endif

<section aria-labelledby="recent-title">
    <h2 id="recent-title">最近的心情日記</h2>
    @if ($diaries->isEmpty())
        <p>還沒有日記。</p>
    @else
        <ul class="plain-list">
            @foreach ($diaries as $diary)
                <li>
                    <a href="{{ route('diaries.show', $diary) }}">{{ $diary->title }}</a>
                    <span class="meta">・<time datetime="{{ $diary->published_on->toDateString() }}">{{ $diary->published_on->format('Y/n/j') }}</time></span>
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endsection
