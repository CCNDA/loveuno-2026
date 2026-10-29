@extends('layouts.app')

@section('title', '我的朋友')

@section('content')
<h1>我的朋友</h1>
<p class="hint">互送禮物就是「普通好友」（<a href="{{ route('gifts.index') }}">我的禮物</a>）；互傳紙條各 {{ \App\Models\Friendship::CHAT_NOTES }} 則以上變成「聊天好友」；一起出席約會變成「真實好友」。</p>

@foreach ([3, 2, 1] as $level)
    <section aria-labelledby="friends-{{ $level }}">
        <h2 id="friends-{{ $level }}">{{ \App\Models\Friendship::LEVELS[$level] }}（{{ $groups[$level]->count() }}）</h2>
        @if ($groups[$level]->isEmpty())
            <p>還沒有。</p>
        @else
            <ul class="friend-list">
                @foreach ($groups[$level] as $row)
                    <li class="friend-item">
                        @include('partials.avatar', ['user' => $row['friend'], 'size' => 'sm'])
                        <div>
                            <p class="member-name"><a href="{{ route('members.show', $row['friend']) }}">{{ $row['friend']->name }}</a></p>
                            <p class="meta">{{ $row['since']?->format('Y/n/j') }} 成為好友</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endforeach
@endsection
