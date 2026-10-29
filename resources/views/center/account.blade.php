@extends('layouts.app')

@section('title', '帳號管理')

@section('content')
@include('center.tabs', ['current' => 'account'])

<h2>帳號</h2>
<dl class="profile">
    <div><dt>電子郵件</dt><dd>{{ $user->email }}</dd></div>
    <div><dt>暱稱</dt><dd>{{ $user->name }}</dd></div>
</dl>

<h2 id="pw-title">換密碼</h2>
<form method="POST" action="{{ route('center.password') }}" class="form" aria-labelledby="pw-title" novalidate>
    @csrf
    @method('PUT')
    @include('partials.errors', ['title' => '密碼沒有換成功'])

    <div class="field">
        <label for="current_password">目前的密碼</label>
        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required
               @error('current_password') aria-invalid="true" aria-describedby="current_password-error" @enderror>
        @error('current_password')<p id="current_password-error" class="field-error">{{ $message }}</p>@enderror
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
    <button type="submit" class="button">換密碼</button>
</form>

<h2 id="blocked-title">封鎖名單</h2>
@if ($blocked->isEmpty())
    <p class="hint">您沒有封鎖任何人。</p>
@else
    <ul class="plain-list" aria-labelledby="blocked-title">
        @foreach ($blocked as $b)
            <li class="blocked-item">
                {{ $b->name }}
                <form method="POST" action="{{ route('blocks.destroy', $b) }}" class="inline-form">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="link-button">解除封鎖<span class="visually-hidden">：{{ $b->name }}</span></button>
                </form>
            </li>
        @endforeach
    </ul>
@endif
@endsection
