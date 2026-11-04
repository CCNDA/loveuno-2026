@extends('layouts.app')

@section('title', '我的約會')

@section('content')
<h1>我的約會</h1>
<p class="hint">成為「聊天好友」以後，可以在對方的檔案按「約會」邀請對方在教會的活動見面；一起出席就成為「真實好友」。</p>
@foreach ([['received', '收到的邀請', 'sender', '還沒有收到約會邀請。'], ['sent', '我送出的邀請', 'recipient', '還沒有送出約會邀請。']] as [$key, $title, $rel, $empty])
    <section aria-labelledby="{{ $key }}-title">
        <h2 id="{{ $key }}-title">{{ $title }}</h2>
        @if ($$key->isEmpty())
            <p>{{ $empty }}</p>
        @else
            <ul class="plain-list meetup-list">
                @foreach ($$key as $m)
                    <li>
                        <a href="{{ route('meetups.show', $m) }}">{{ $m->meet_on->format('Y/n/j') }} {{ $m->meet_time }}・{{ $m->$rel?->name ?? '已註銷會員' }}</a>
                        <span class="tag">{{ $m->stateLabel() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endforeach
@endsection
