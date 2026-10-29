@extends('layouts.app')

@section('title', $member->name)

@section('content')
@if (! ($iBlocked ?? false))
    @include('partials.cover', ['user' => $member])
@endif
<div class="member-head">
    @include('partials.avatar', ['user' => $member, 'size' => 'lg'])
    <div>
        @php($approved = ($iBlocked ?? false) ? collect() : $member->approvedVerifications())
        <h1>{{ $member->name }}@if ($approved->isNotEmpty()) <img src="{{ asset('images/brand/verified-badge.svg') }}" alt="已認證" width="20" height="20" class="verified-badge">@endif @if($isMe) <span class="tag">我</span>@endif</h1>
        @if (! ($iBlocked ?? false) && ($member->profile?->headline ?? '') !== '')
            <p class="headline">{{ $member->profile->headline }}</p>
        @endif
        @if (! ($iBlocked ?? false))
            <p class="meta">
                最近登入：{{ $member->last_login_at ? \App\Support\Ago::days($member->last_login_at) : '—' }}
                ・最近更新：{{ $member->profile?->updated_at ? \App\Support\Ago::days($member->profile->updated_at) : '—' }}
            </p>
        @endif
    </div>
</div>
@if (! $isMe && ! ($iBlocked ?? false))
    @php($level = auth()->user()->friendLevelWith($member))
    <p class="relationship">與您關係：<strong>{{ \App\Models\Friendship::LEVELS[$level] }}</strong>
        @if ($level > 0)・<a href="{{ route('friends.confirm', $member) }}">解除關係…</a>@endif
        ・<a href="{{ route('reports.create', $member) }}">檢舉…</a>
    </p>
@endif
@if (\App\Http\Controllers\NoteController::canNote(auth()->user(), $member))
    <p class="member-actions">
        <a class="button" href="{{ route('notes.show', $member) }}">傳紙條給 {{ $member->name }}</a>
        <a class="button button-light" href="{{ route('gifts.create', $member) }}"><img src="{{ asset('images/brand/gift.svg') }}" alt="" width="20" height="20"> 送禮物</a>
        <a class="button button-light" href="{{ route('meetups.create', $member) }}">約會<span class="visually-hidden">（邀請 {{ $member->name }}）</span></a>
    </p>
@endif
@if ($isMe)
    <p>
        <a href="{{ route('center.account') }}">會員中心</a>・<a href="{{ route('friends.index') }}">我的朋友</a>・<a href="{{ route('gifts.index') }}">我的禮物</a>・<a href="{{ route('meetups.index') }}">我的約會</a>・<a href="{{ route('profile.edit') }}">編輯個人檔案</a>
        ・<a href="{{ route('photo.edit') }}">換大頭照</a>@if ($member->profile?->photo_status === 'pending')（審核中）@endif
        ・<a href="{{ route('cover.edit') }}">換封面</a>@if ($member->profile?->cover_status === 'pending')（審核中）@endif
    </p>
@endif

@if ($iBlocked ?? false)
    <p class="notice" role="status">您已封鎖這位會員，所以看不到對方的檔案和日記，對方也看不到您的。</p>
    <form method="POST" action="{{ route('blocks.destroy', $member) }}">
        @csrf
        @method('DELETE')
        <button type="submit" class="button">解除封鎖</button>
    </form>
    <ul class="danger-links"><li><a href="{{ route('reports.create', $member) }}">檢舉這位會員…</a></li></ul>
@else
@php($p = $member->profile ?? new \App\Models\Profile(['user_id' => $member->id]))
@if (true)
    @include('members.partials.tabs')
    @if (auth()->user()->is_admin && ($p->real_name !== '' || $p->home_phone !== ''))
        <aside class="notice admin-only" aria-label="只有管理員看得到">
            <p>只有管理員看得到：真實姓名 {{ $p->real_name !== '' ? $p->real_name : '（沒填）' }}・住家電話 {{ $p->home_phone !== '' ? $p->home_phone : '（沒填）' }}</p>
        </aside>
    @endif
@endif

@if (! $isMe)
    <ul class="danger-zone danger-links">
        <li><a href="{{ route('blocks.confirm', $member) }}">封鎖這位會員…</a></li>
    </ul>
@endif
@endif
@endsection
