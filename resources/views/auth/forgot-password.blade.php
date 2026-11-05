@extends('layouts.app')

@section('title', '忘記密碼')

@section('main_class', 'wrap auth-card forgot-card')

@section('content')
{{-- 設計稿 ForgotPassword 209:21695（電腦）／209:21686（手機）：大鎖圖示、標題 32px／34、輸入框 1px 8px 圓角、內容 24px＋上下 16px，
     下面「‹ 返回登入」（小鹿 11/6 #3） --}}
<img class="auth-card-icon" src="{{ asset('images/brand/auth-lock.svg') }}" alt="" width="96" height="96">
<h1>忘記密碼？</h1>
<p class="forgot-lead">請輸入您的帳戶電子郵件，我們會寄重設密碼的連結給您。如未收到請檢查垃圾信件匣。</p>

<form method="POST" action="{{ route('password.email') }}" class="form" novalidate>
    @csrf

    @include('partials.errors', ['title' => '還沒有寄出'])

    <div class="field forgot-field">
        <label for="email" class="visually-hidden">電子郵件</label>
        <input id="email" name="email" type="email" inputmode="email" autocomplete="username" placeholder="電子郵件"
               value="{{ old('email') }}" required
               @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
        @error('email')<p id="email-error" class="field-error">{{ $message }}</p>@enderror
    </div>

    <button type="submit" class="button">寄重設密碼的信</button>
</form>
<p class="hint">同一個信箱 60 秒內只會寄一封。</p>
<p class="forgot-back"><a href="{{ route('login') }}"><svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M10 3 5 8l5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>返回登入</a></p>
@endsection
