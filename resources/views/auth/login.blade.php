@extends('layouts.app')

@section('title', '會員登入')
@section('bare', 'auth')
@section('main_class', 'auth-page')

@section('content')
{{-- 照設計稿 Figma Login 209:21707（電腦版：左邊插圖、右邊登入框）／[MOBILE] Login 209:21677（只有登入框）。
     熊叔叔 2026-11-05 TG 傳截圖「這個是登入頁」。設計稿沒有網站頁首頁尾；Logo 連回首頁、繁簡切換放右上。 --}}
<div class="auth-illustration" aria-hidden="true"><img src="{{ asset('images/welcome-hero.svg') }}" alt="" width="763" height="389"></div>
<div class="auth-panel">
    <div class="auth-box">
        <p class="auth-script">@include('partials.script-toggle')</p>
        <a class="auth-logo" href="{{ route('home') }}"><img src="{{ asset('images/brand/logo-mark.svg') }}" alt="愛諾園 首頁" width="90" height="90"></a>
        <h1>登入帳號</h1>
        <p class="auth-sub">舊站的帳號可以直接登入</p>
        @if (config('loveuno.registration_open'))
            <nav class="auth-tabs" aria-label="登入或註冊">
                <a href="{{ route('login') }}" aria-current="page">登入</a>
                <a href="{{ route('register') }}">註冊</a>
            </nav>
        @endif

        <form method="POST" action="{{ route('login.store') }}" novalidate>
            @csrf

            @if ($errors->any())
                <div class="error-summary" role="alert" aria-labelledby="error-title">
                    <h2 id="error-title">登入沒有成功</h2>
                    <ul>
                        @foreach ($errors->messages() as $field => $messages)
                            @foreach ($messages as $message)
                                <li><a href="#{{ $field }}">{{ $message }}</a></li>
                            @endforeach
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="auth-field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" inputmode="email" autocomplete="username"
                       value="{{ old('email') }}" required
                       @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            </div>
            @error('email')<p id="email-error" class="field-error">{{ $message }}</p>@enderror

            <div class="auth-field auth-field-password">
                <label for="password">密碼</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                       @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                {{-- 顯示密碼（設計稿 24px/Show 眼睛）；沒有 JS 時不出現 --}}
                <button type="button" class="password-show" aria-controls="password" aria-pressed="false" hidden>
                    <img src="{{ asset('images/brand/eye.svg') }}" alt="" width="24" height="24"><span class="visually-hidden">顯示密碼</span>
                </button>
            </div>
            @error('password')<p id="password-error" class="field-error">{{ $message }}</p>@enderror

            <div class="auth-row">
                <span class="auth-remember"><input id="remember" name="remember" type="checkbox" value="1"> <label for="remember">記住我</label></span>
                <a href="{{ route('password.request') }}">忘記密碼</a>
            </div>

            <button type="submit" class="button auth-submit">登入</button>
        </form>
    </div>
    <p class="auth-links"><a href="{{ route('feedback.create', ['from' => request()->getRequestUri()]) }}" target="_blank" rel="noopener">回報問題／建議<span class="visually-hidden">（開新分頁）</span></a>　<a href="{{ route('privacy') }}">隱私權政策</a></p>
    <p class="auth-serve">網站服務由 中華基督教網路發展協會 提供<br><span lang="en">Serve by Chinese Christian Network Development Association</span></p>
</div>
<script src="{{ asset('js/password-show.js') }}?v={{ $appVersion }}" defer></script>
@endsection
