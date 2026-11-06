@extends('layouts.app')

@section('title', '我的約會')
@section('main_class', 'wrap wrap-wide')

@section('content')
<h1>約會邀請</h1>
{{-- 設計稿 約會邀請 2529:26799／手機 2529:28605、2529:30283（熊叔叔 11/6 決定 #9）：左邊一個邀請一列的清單（可勾選刪除），右邊邀請卡；
     手機只有清單，點一個才看卡片。卡片：藍灰條（頭像、名字、提出一個約會邀請、日期），下面日期／時間／地點／活動／內容，珊瑚色按鈕 --}}
<p class="hint">成為「聊天好友」以後，可以在對方的檔案按「約會」邀請對方在教會的活動見面；一起出席就成為「真實好友」。</p>
@php($me = auth()->user())

<div class="notes-layout @if($other) has-thread @endif">
    <section class="notes-threads" aria-labelledby="date-list-title">
        <form method="POST" action="{{ route('meetups.destroy') }}" id="letters-form" data-confirm="要刪除勾選的 {n} 個約會邀請嗎？只會從您這邊刪除，對方還看得到。">
            @csrf
            <h2 id="date-list-title" class="letters-head"><span class="letters-heart" aria-hidden="true">♥</span>
                @if ($newCount > 0)<span>您有 <span class="threads-count">{{ $newCount }}</span> 個新約會邀請</span>@else<span>約會邀請清單</span>@endif
                @if ($letters->isNotEmpty())
                    <button type="submit" class="letters-del" aria-label="刪除勾選的約會邀請（只刪除您這邊）"><svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 3h6l1 2h4v2H4V5h4l1-2zm-3 6h12l-1 12H7L6 9zm4 2v8h2v-8h-2zm4 0v8h2v-8h-2z" fill="currentColor"/></svg></button>
                @endif
            </h2>
            @if ($letters->isEmpty())
                <p class="hint" style="padding: 12px 14px">還沒有約會邀請。</p>
            @else
                <ul class="letter-list">
                    @foreach ($letters as $m)
                        @php($mine = $m->sender_id === (int) $me->id)
                        @php($who = $m->otherThan($me))
                        <li class="letter-item @if($other && $who && $other->is($who)) is-current @endif">
                            <input type="checkbox" name="ids[]" value="{{ $m->id }}" id="del-{{ $m->id }}" aria-label="勾選：{{ $mine ? '邀請' : '來自' }} {{ $who?->name ?? '已註銷會員' }} 的約會（{{ $m->meet_on->format('Y/n/j') }}）">
                            <a href="{{ $who ? route('meetups.index', ['with' => $who->id]) : route('meetups.show', $m) }}#date-{{ $m->id }}" class="letter-link">
                                <span class="letter-top">
                                    @if ($who)@include('partials.avatar', ['user' => $who, 'size' => 'sm'])@endif
                                    <span class="letter-who">{{ $mine ? '邀請 ' : '' }}{{ $who?->name ?? '已註銷會員' }}</span>
                                    <time class="letter-date" datetime="{{ $m->created_at->toIso8601String() }}">{{ $m->created_at->shown()->format('Y/m/d') }}</time>
                                    @if (! $mine && $m->state() === 'pending')<span class="letter-dot" aria-hidden="true"></span><span class="visually-hidden">（等您回覆）</span>@endif
                                </span>
                                <span class="letter-title">{{ $mine ? '我提出的約會邀請' : '提出一個約會邀請' }} <span class="tag">{{ $m->stateLabel() }}</span></span>
                                <span class="letter-excerpt">{{ $m->meet_on->format('Y/m/d') }} {{ $m->meet_time }}・{{ $m->place }}@if(($m->message ?? '') !== '')・{{ $m->message }}@endif</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </form>
    </section>

    <section class="notes-thread gift-pane" aria-labelledby="date-pane-title">
        @if ($other)
            <h2 id="date-pane-title">和 <a href="{{ route('members.show', $other) }}">{{ $other->name }}</a> 的約會</h2>
            <p class="notes-back"><a href="{{ route('meetups.index') }}">← 回約會清單</a></p>
        @else
            <h2 id="date-pane-title" class="visually-hidden">約會邀請內容</h2>
        @endif
        @if ($cards->isEmpty())
            <p class="hint">還沒有約會邀請。</p>
        @else
            <ul class="gift-cards">
                @foreach ($cards as $m)
                    @php($mine = $m->sender_id === (int) $me->id)
                    @php($who = $m->otherThan($me))
                    <li class="gift-letter @if($mine) gift-letter-mine @endif" id="date-{{ $m->id }}">
                        <div class="note-head gift-letter-head">
                            @if ($who)@include('partials.avatar', ['user' => $who, 'size' => 'sm'])@endif
                            <p class="note-who">{{ $mine ? '邀請 ' : '' }}{{ $who?->name ?? '已註銷會員' }}</p>
                            <p class="gift-letter-title">{{ $mine ? '的約會' : '提出一個約會邀請' }} <span class="tag">{{ $m->stateLabel() }}</span></p>
                            <p class="note-time"><time datetime="{{ $m->created_at->toIso8601String() }}">{{ $m->created_at->shown()->format('Y-m-d') }}</time></p>
                        </div>
                        <dl class="meetup-facts">
                            <div><dt>日期</dt><dd>{{ $m->meet_on->format('Y/m/d') }}</dd></div>
                            <div><dt>時間</dt><dd>{{ $m->meet_time }}</dd></div>
                            <div><dt>地點</dt><dd>{{ $m->place }}</dd></div>
                            <div><dt>活動</dt><dd>{{ \App\Models\Meetup::ACTIVITIES[$m->activity] ?? $m->activity }}</dd></div>
                            @if (($m->message ?? '') !== '')<div><dt>內容</dt><dd>{{ $m->message }}</dd></div>@endif
                        </dl>
                        @if (! $mine && $m->state() === 'pending')
                            {{-- 照設計稿 2529:26799：直接「不參加」「參加」（熊叔叔 11/6）；想寫一句話的到詳細頁 --}}
                            <form method="POST" action="{{ route('meetups.respond', $m) }}" class="meetup-quick">
                                @csrf
                                <button type="submit" name="decision" value="decline" class="button button-light">不參加<span class="visually-hidden">：{{ $m->meet_on->format('Y/n/j') }} {{ $who?->name ?? '已註銷會員' }} 的邀請</span></button>
                                <button type="submit" name="decision" value="accept" class="button note-send">參加<span class="visually-hidden">：{{ $m->meet_on->format('Y/n/j') }} {{ $who?->name ?? '已註銷會員' }} 的邀請</span></button>
                            </form>
                            <p class="meetup-more"><a href="{{ route('meetups.show', $m) }}">想先寫一句話再回覆<span class="visually-hidden">（{{ $who?->name ?? '已註銷會員' }}）</span></a></p>
                        @else
                            <p class="gift-letter-actions"><a class="button note-send" href="{{ route('meetups.show', $m) }}">看詳細<span class="visually-hidden">：{{ $m->meet_on->format('Y/n/j') }} {{ $m->meet_time }}・{{ $who?->name ?? '已註銷會員' }}</span></a></p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
<script src="{{ asset('js/letters-delete.js') }}?v={{ $appVersion }}" defer></script>
@endsection
