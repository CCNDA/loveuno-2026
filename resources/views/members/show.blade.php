@extends('layouts.app')

@section('title', $member->name)
@section('main_class', 'wrap wrap-wide')

@section('content')
@include('members.partials.head')
@if ($isMe)
    {{-- 自己的頁面：常用入口做成小膠囊鈕（小鹿 11/6：原本一長串文字連結在手機換好幾行） --}}
    <ul class="profile-mine" aria-label="我的捷徑">
        <li><a href="{{ route('center.account') }}">會員中心</a></li>
        <li><a href="{{ route('friends.index') }}">我的朋友</a></li>
        <li><a href="{{ route('gifts.index') }}">我的禮物</a></li>
        <li><a href="{{ route('meetups.index') }}">我的約會</a></li>
        <li><a href="{{ route('profile.edit') }}">編輯個人檔案</a></li>
        <li><a href="{{ route('profile.intro') }}#photo-title">換大頭照@if ($member->profile?->photo_status === 'pending')（審核中）@endif</a></li>
        <li><a href="{{ route('album.edit') }}#cover-title">換封面</a></li>
    </ul>
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
