@extends('layouts.app')

@section('title', '我的禮物')
@section('main_class', 'wrap wrap-wide')

@section('content')
<h1>查看禮物</h1>
{{-- 設計稿 查看禮物 2529:20867／手機 2529:21329（熊叔叔 11/6 決定 #9）：左邊一份一列的清單（可勾選刪除），右邊禮物卡；
     手機只有清單，點一份才看卡片。卡片：上面藍灰條（頭像、名字、送你一份「…」、日期），下面禮物圖＋簡訊，珊瑚色「回禮」 --}}
@php($me = auth()->user())

<div class="notes-layout @if($other) has-thread @endif">
    <section class="notes-threads" aria-labelledby="gift-list-title">
        <form method="POST" action="{{ route('gifts.destroy') }}" id="letters-form" data-confirm="要刪除勾選的 {n} 份禮物嗎？只會從您這邊刪除，對方還看得到。">
            @csrf
            <h2 id="gift-list-title" class="letters-head"><img src="{{ asset('images/brand/gift.svg') }}" alt="" width="24" height="24">
                @if (count($newIds) > 0)<span>您有 <span class="threads-count">{{ count($newIds) }}</span> 個新禮物</span>@else<span>禮物清單</span>@endif
                @if ($letters->isNotEmpty())
                    <button type="submit" class="letters-del" aria-label="刪除勾選的禮物（只刪除您這邊）"><svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 3h6l1 2h4v2H4V5h4l1-2zm-3 6h12l-1 12H7L6 9zm4 2v8h2v-8h-2zm4 0v8h2v-8h-2z" fill="currentColor"/></svg></button>
                @endif
            </h2>
            @if ($letters->isEmpty())
                <p class="hint" style="padding: 12px 14px">還沒有禮物。到「認識朋友」找一位，在他的檔案頁按「送禮物」。</p>
            @else
                <ul class="letter-list">
                    @foreach ($letters as $g)
                        @php($mine = $g->sender_id === (int) $me->id)
                        @php($who = $mine ? $g->recipient : $g->sender)
                        <li class="letter-item @if($other && $other->is($who)) is-current @endif">
                            <input type="checkbox" name="ids[]" value="{{ $g->id }}" id="del-{{ $g->id }}" aria-label="勾選：{{ $mine ? '送給' : '來自' }} {{ $who->name }} 的「{{ $g->itemName() }}」">
                            <a href="{{ route('gifts.index', ['with' => $who->id]) }}#gift-{{ $g->id }}" class="letter-link">
                                <span class="letter-top">
                                    @include('partials.avatar', ['user' => $who, 'size' => 'sm'])
                                    <span class="letter-who">{{ $mine ? '送給 ' : '' }}{{ $who->name }}</span>
                                    <time class="letter-date" datetime="{{ $g->created_at->toIso8601String() }}">{{ $g->created_at->shown()->format('Y/m/d') }}</time>
                                    @if (in_array($g->id, $newIds, true))<span class="letter-dot" aria-hidden="true"></span><span class="visually-hidden">（新）</span>@endif
                                </span>
                                <span class="letter-title">{{ $mine ? '送出' : '送你' }}一份「{{ $g->itemName() }}」</span>
                                <span class="letter-excerpt">{{ $g->message !== '' ? $g->message : '（沒有附簡訊）' }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </form>
    </section>

    <section class="notes-thread gift-pane" aria-labelledby="gift-pane-title">
        @if ($other)
            <h2 id="gift-pane-title">和 <a href="{{ route('members.show', $other) }}">{{ $other->name }}</a> 的禮物</h2>
            <p class="notes-back"><a href="{{ route('gifts.index') }}">← 回禮物清單</a></p>
        @else
            <h2 id="gift-pane-title" class="visually-hidden">禮物內容</h2>
        @endif
        @if ($cards->isEmpty())
            <p class="hint">還沒有禮物。</p>
        @else
            <ul class="gift-cards">
                @foreach ($cards as $g)
                    @php($mine = $g->sender_id === (int) $me->id)
                    @php($who = $mine ? $g->recipient : $g->sender)
                    <li class="gift-letter @if($mine) gift-letter-mine @endif" id="gift-{{ $g->id }}">
                        <div class="note-head gift-letter-head">
                            @include('partials.avatar', ['user' => $who, 'size' => 'sm'])
                            <p class="note-who">{{ $mine ? '送給 ' : '' }}<a href="{{ route('members.show', $who) }}">{{ $who->name }}</a></p>
                            <p class="gift-letter-title">@if (in_array($g->id, $newIds, true))<span class="tag">新</span> @endif{{ $mine ? '' : '送你一份' }}「{{ $g->itemName() }}」</p>
                            <p class="note-time"><time datetime="{{ $g->created_at->toIso8601String() }}">{{ $g->created_at->shown()->format('Y-m-d H:i') }}</time></p>
                        </div>
                        <div class="gift-letter-body">
                            <figure class="gift-tile"><img src="{{ \App\Models\Gift::imageUrl($g->item) }}" alt="" width="120" height="120" loading="lazy"><figcaption>{{ $g->itemName() }}</figcaption></figure>
                            <p class="gift-letter-msg">{{ $g->message !== '' ? $g->message : '（沒有附簡訊）' }}</p>
                        </div>
                        @if (! $mine && $me->friendLevelWith($who) === 0 && \App\Http\Controllers\NoteController::canNote($me, $who))
                            <p class="gift-letter-actions"><a class="button note-send" href="{{ route('gifts.create', $who) }}">回禮<span class="visually-hidden">：回送禮物，成為好友</span></a></p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
<script src="{{ asset('js/letters-delete.js') }}?v={{ $appVersion }}" defer></script>
@endsection
