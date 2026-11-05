@extends('layouts.app')

@section('title', '忘記密碼')

@section('main_class', 'wrap auth-card')

@section('content')
{{-- 設計稿 忘記密碼／驗證頁（209:21695、209:21692）：上面一個大圖示、置中的小卡 --}}
<img class="auth-card-icon" src="{{ asset('images/brand/auth-lock.svg') }}" alt="" width="96" height="96">
<h1>忘記密碼</h1>
<p>請輸入註冊時用的電子郵件，我們會寄一封重設密碼的信給您。</p>

<form method="POST" action="{{ route('password.email') }}" class="form" novalidate>
    @csrf

    @include('partials.errors', ['title' => '還沒有寄出'])

    <div class="field">
        <label for="email">電子郵件</label>
        <input id="email" name="email" type="email" inputmode="email" autocomplete="username"
               value="{{ old('email') }}" required
               @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
        @error('email')<p id="email-error" class="field-error">{{ $message }}</p>@enderror
    </div>

    <button type="submit" class="button">寄重設密碼的信</button>
</form>
<p class="hint">沒收到的話，請看一下垃圾郵件匣；同一個信箱 60 秒內只會寄一封。</p>
<p><a href="{{ route('login') }}">返回登入頁</a></p>
@endsection
