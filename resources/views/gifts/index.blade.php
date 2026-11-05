@extends('layouts.app')

@section('title', '我的禮物')
@section('main_class', 'wrap wrap-wide')

@section('content')
<h1>查看禮物</h1>
{{-- 設計稿 查看禮物 2529:20867：一份一張卡——上面藍灰條（頭像、名字、送你一份「…」、日期），下面禮物圖＋簡訊，珊瑚色「回禮」 --}}

<section aria-labelledby="received-title" class="gift-board">
    <h2 id="received-title" class="threads-head"><img src="{{ asset('images/brand/gift.svg') }}" alt="" width="24" height="24">
        @if (count($newIds) > 0)您有 <span class="threads-count">{{ count($newIds) }}</span> 個新禮物@else收到的禮物（{{ $received->count() }}）@endif</h2>
    @if ($received->isEmpty())
        <p>還沒有收到禮物。</p>
    @else
        <ul class="gift-cards">
            @foreach ($received as $g)
                <li class="gift-letter">
                    <div class="note-head gift-letter-head">
                        @include('partials.avatar', ['user' => $g->sender, 'size' => 'sm'])
                        <p class="note-who"><a href="{{ route('members.show', $g->sender) }}">{{ $g->sender->name }}</a></p>
                        <p class="gift-letter-title">@if (in_array($g->id, $newIds, true))<span class="tag">新</span> @endif送你一份「{{ $g->itemName() }}」</p>
                        <p class="note-time"><time datetime="{{ $g->created_at->toIso8601String() }}">{{ $g->created_at->shown()->format('Y-m-d H:i') }}</time></p>
                    </div>
                    <div class="gift-letter-body">
                        <figure class="gift-tile"><img src="{{ \App\Models\Gift::imageUrl($g->item) }}" alt="" width="120" height="120" loading="lazy"><figcaption>{{ $g->itemName() }}</figcaption></figure>
                        <p class="gift-letter-msg">{{ $g->message !== '' ? $g->message : '（沒有附簡訊）' }}</p>
                    </div>
                    @if (auth()->user()->friendLevelWith($g->sender) === 0 && \App\Http\Controllers\NoteController::canNote(auth()->user(), $g->sender))
                        <p class="gift-letter-actions"><a class="button note-send" href="{{ route('gifts.create', $g->sender) }}">回禮<span class="visually-hidden">：回送禮物，成為好友</span></a></p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>

<section aria-labelledby="sent-title" class="gift-board">
    <h2 id="sent-title" class="threads-head">送出的禮物（{{ $sent->count() }}）</h2>
    @if ($sent->isEmpty())
        <p>還沒有送過禮物。</p>
    @else
        <ul class="gift-cards">
            @foreach ($sent as $g)
                <li class="gift-letter gift-letter-mine">
                    <div class="note-head gift-letter-head">
                        @include('partials.avatar', ['user' => $g->recipient, 'size' => 'sm'])
                        <p class="note-who">送給 <a href="{{ route('members.show', $g->recipient) }}">{{ $g->recipient->name }}</a></p>
                        <p class="gift-letter-title">「{{ $g->itemName() }}」</p>
                        <p class="note-time"><time datetime="{{ $g->created_at->toIso8601String() }}">{{ $g->created_at->shown()->format('Y-m-d H:i') }}</time></p>
                    </div>
                    <div class="gift-letter-body">
                        <figure class="gift-tile"><img src="{{ \App\Models\Gift::imageUrl($g->item) }}" alt="" width="120" height="120" loading="lazy"><figcaption>{{ $g->itemName() }}</figcaption></figure>
                        <p class="gift-letter-msg">{{ $g->message !== '' ? $g->message : '（沒有附簡訊）' }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endsection
