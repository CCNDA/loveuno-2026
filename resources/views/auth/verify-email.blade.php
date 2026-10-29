@extends('layouts.app')

@section('title', '請驗證電子郵件')

@section('content')
<h1>請到信箱收驗證信</h1>
<p>我們寄了一封信到 <strong>{{ auth()->user()->email }}</strong>，請點信裡的連結完成驗證。連結 60 分鐘內有效。</p>
<p class="hint">沒收到的話，請看一下垃圾郵件匣，或按下面的按鈕重寄。</p>

<form method="POST" action="{{ route('verification.send') }}">
    @csrf
    <button type="submit" class="button">重新寄驗證信</button>
</form>
@endsection
