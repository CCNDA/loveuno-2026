@extends('layouts.app')

@section('title', '請驗證電子郵件')

@section('main_class', 'wrap auth-card')

@section('content')
@if (auth()->user()->email_bounced_at)
<h1>您的電子郵件收不到信</h1>
<p>我們寄到 <strong>{{ auth()->user()->email }}</strong> 的信被退回來了，所以先暫停寄通知信給您（有人送禮物、傳紙條時，您會收不到通知）。</p>
<p>請選一個：</p>
<h2>這個信箱其實可以用</h2>
<p class="hint">按下面的按鈕，我們重寄一封驗證信；點了信裡的連結，就會恢復寄信。</p>
<form method="POST" action="{{ route('verification.send') }}">
    @csrf
    <button type="submit" class="button">重新寄驗證信</button>
</form>
<h2>改用另一個信箱</h2>
<form method="POST" action="{{ route('email.change') }}">
    @csrf
    <p><label for="new_email">新的電子郵件</label><br>
        <input type="email" id="new_email" name="new_email" value="{{ old('new_email') }}" maxlength="100" required autocomplete="email"
            @error('new_email', 'email') aria-invalid="true" aria-describedby="new_email_error" @enderror></p>
    @error('new_email', 'email')<p class="error" id="new_email_error">{{ $message }}</p>@enderror
    <p><label for="current_password">目前的密碼</label><br>
        <input type="password" id="current_password" name="current_password" required autocomplete="current-password"
            @error('current_password', 'email') aria-invalid="true" aria-describedby="current_password_error" @enderror></p>
    @error('current_password', 'email')<p class="error" id="current_password_error">{{ $message }}</p>@enderror
    <button type="submit" class="button">寄確認信到新信箱</button>
</form>
@else
{{-- 設計稿 忘記密碼／驗證頁（209:21695、209:21692）：上面一個大圖示、置中的小卡 --}}
<img class="auth-card-icon" src="{{ asset('images/brand/auth-mail.svg') }}" alt="" width="96" height="96">
<h1>請到信箱收驗證信</h1>
<p>我們寄了一封信到 <strong>{{ auth()->user()->email }}</strong>，請點信裡的連結完成驗證。連結 60 分鐘內有效。</p>
<p class="hint">沒收到的話，請看一下垃圾郵件匣，或按下面的按鈕重寄。</p>

<form method="POST" action="{{ route('verification.send') }}">
    @csrf
    <button type="submit" class="button">重新寄驗證信</button>
</form>
@endif
@endsection
