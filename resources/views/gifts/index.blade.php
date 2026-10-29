@extends('layouts.app')

@section('title', '我的禮物')

@section('content')
<h1>我的禮物</h1>

<section aria-labelledby="received-title">
    <h2 id="received-title">收到的禮物（{{ $received->count() }}）</h2>
    @if ($received->isEmpty())
        <p>還沒有收到禮物。</p>
    @else
        <ul class="gift-list">
            @foreach ($received as $g)
                <li class="gift-item">
                    <img src="{{ \App\Models\Gift::imageUrl($g->item) }}" alt="" width="56" height="56" class="gift-thumb" loading="lazy">
                    <div>
                        <p class="gift-title">
                            @if (in_array($g->id, $newIds, true))<span class="tag">新</span> @endif
                            <a href="{{ route('members.show', $g->sender) }}">{{ $g->sender->name }}</a> 送您「{{ $g->itemName() }}」
                        </p>
                        @if ($g->message !== '')<p class="gift-message">{{ $g->message }}</p>@endif
                        <p class="meta"><time datetime="{{ $g->created_at->toIso8601String() }}">{{ $g->created_at->format('Y/n/j H:i') }}</time>
                            @if (auth()->user()->friendLevelWith($g->sender) === 0 && \App\Http\Controllers\NoteController::canNote(auth()->user(), $g->sender))・<a href="{{ route('gifts.create', $g->sender) }}">回送禮物，成為好友</a>@endif
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>

<section aria-labelledby="sent-title">
    <h2 id="sent-title">送出的禮物（{{ $sent->count() }}）</h2>
    @if ($sent->isEmpty())
        <p>還沒有送過禮物。</p>
    @else
        <ul class="gift-list">
            @foreach ($sent as $g)
                <li class="gift-item">
                    <img src="{{ \App\Models\Gift::imageUrl($g->item) }}" alt="" width="56" height="56" class="gift-thumb" loading="lazy">
                    <div>
                        <p class="gift-title">送 <a href="{{ route('members.show', $g->recipient) }}">{{ $g->recipient->name }}</a>「{{ $g->itemName() }}」</p>
                        @if ($g->message !== '')<p class="gift-message">{{ $g->message }}</p>@endif
                        <p class="meta"><time datetime="{{ $g->created_at->toIso8601String() }}">{{ $g->created_at->format('Y/n/j H:i') }}</time></p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endsection
