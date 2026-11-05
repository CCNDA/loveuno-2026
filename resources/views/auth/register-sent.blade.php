@extends('layouts.app')

@section('title', '請到信箱收信')

@section('main_class', 'wrap auth-card')

@section('content')
{{-- 設計稿 忘記密碼／驗證頁（209:21695、209:21692）：上面一個大圖示、置中的小卡 --}}
<img class="auth-card-icon" src="{{ asset('images/brand/auth-mail.svg') }}" alt="" width="96" height="96">
<h1>請到信箱收信</h1>
<p>我們寄了一封信到您填的電子郵件，請點信裡的連結完成驗證，再回來登入。連結 60 分鐘內有效。</p>
<p class="hint">沒收到的話，請看一下垃圾郵件匣。</p>
@if ($canResend)
    <form method="POST" action="{{ route('register.resend') }}">
        @csrf
        <button type="submit" class="button button-secondary">還是沒收到？再寄一次</button>
    </form>
@endif
<p><a class="button" href="{{ route('login') }}">到登入頁</a></p>
@endsection
