@extends('layouts.app')

@section('title', '重設密碼')

@section('content')
<h1>重設密碼</h1>

<form method="POST" action="{{ route('password.update') }}" class="form" novalidate>
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    @include('partials.errors', ['title' => '密碼還沒有換好'])

    <div class="field">
        <label for="email">電子郵件</label>
        <input id="email" name="email" type="email" inputmode="email" autocomplete="username"
               value="{{ old('email', $email) }}" required
               @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
        @error('email')<p id="email-error" class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="password">新密碼</label>
        <p id="password-hint" class="hint">至少 10 個字元。換好之後，其他裝置上的登入會被登出。</p>
        <input id="password" name="password" type="password" autocomplete="new-password" required
               aria-describedby="password-hint @error('password') password-error @enderror"
               @error('password') aria-invalid="true" @enderror>
        @error('password')<p id="password-error" class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="password_confirmation">再輸入一次新密碼</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
    </div>

    <button type="submit" class="button">重設密碼</button>
</form>
<p><a href="{{ route('login') }}">返回登入頁</a></p>
@endsection
