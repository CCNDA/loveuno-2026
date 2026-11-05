@extends('layouts.app')

@section('title', '我的約會')
@section('main_class', 'wrap wrap-wide')

@section('content')
<h1>約會邀請</h1>
{{-- 設計稿 約會邀請 2529:26799：一個邀請一張卡——藍灰條（頭像、名字、提出一個約會邀請、日期），下面日期／時間／地點／活動／內容，珊瑚色按鈕 --}}
<p class="hint">成為「聊天好友」以後，可以在對方的檔案按「約會」邀請對方在教會的活動見面；一起出席就成為「真實好友」。</p>
@foreach ([['received', '收到的邀請', 'sender', '還沒有收到約會邀請。'], ['sent', '我送出的邀請', 'recipient', '還沒有送出約會邀請。']] as [$key, $title, $rel, $empty])
    <section aria-labelledby="{{ $key }}-title" class="gift-board">
        <h2 id="{{ $key }}-title" class="threads-head"><span aria-hidden="true">♥</span> {{ $title }}（{{ $$key->count() }}）</h2>
        @if ($$key->isEmpty())
            <p>{{ $empty }}</p>
        @else
            <ul class="gift-cards">
                @foreach ($$key as $m)
                    @php($who = $m->$rel)
                    <li class="gift-letter {{ $key === 'sent' ? 'gift-letter-mine' : '' }}">
                        <div class="note-head gift-letter-head">
                            @if ($who)@include('partials.avatar', ['user' => $who, 'size' => 'sm'])@endif
                            <p class="note-who">{{ $key === 'sent' ? '邀請 ' : '' }}{{ $who?->name ?? '已註銷會員' }}</p>
                            <p class="gift-letter-title">{{ $key === 'sent' ? '的約會' : '提出一個約會邀請' }} <span class="tag">{{ $m->stateLabel() }}</span></p>
                            <p class="note-time"><time datetime="{{ $m->created_at->toIso8601String() }}">{{ $m->created_at->shown()->format('Y-m-d') }}</time></p>
                        </div>
                        <dl class="meetup-facts">
                            <div><dt>日期</dt><dd>{{ $m->meet_on->format('Y/m/d') }}</dd></div>
                            <div><dt>時間</dt><dd>{{ $m->meet_time }}</dd></div>
                            <div><dt>地點</dt><dd>{{ $m->place }}</dd></div>
                            <div><dt>活動</dt><dd>{{ \App\Models\Meetup::ACTIVITIES[$m->activity] ?? $m->activity }}</dd></div>
                            @if (($m->message ?? '') !== '')<div><dt>內容</dt><dd>{{ $m->message }}</dd></div>@endif
                        </dl>
                        <p class="gift-letter-actions"><a class="button note-send" href="{{ route('meetups.show', $m) }}">{{ $key === 'received' && $m->state() === 'pending' ? '回覆邀請' : '看詳細' }}<span class="visually-hidden">：{{ $m->meet_on->format('Y/n/j') }} {{ $m->meet_time }}・{{ $who?->name ?? '已註銷會員' }}</span></a></p>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endforeach
@endsection
