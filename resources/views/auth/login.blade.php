@extends('layouts.app')

@section('title', '會員登入')

@section('content')
<h1>會員登入</h1>

<form method="POST" action="{{ route('login.store') }}" class="form" novalidate>
    @csrf

    @if ($errors->any())
        <div class="error-summary" role="alert" aria-labelledby="error-title">
            <h2 id="error-title">登入沒有成功</h2>
            <ul>
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="field">
        <label for="email">電子郵件</label>
        <input id="email" name="email" type="email" inputmode="email" autocomplete="username"
               value="{{ old('email') }}" required
               @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
        @error('email')<p id="email-error" class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="password">密碼</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required
               @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
        @error('password')<p id="password-error" class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field field-check">
        <input id="remember" name="remember" type="checkbox" value="1">
        <label for="remember">記住我</label>
    </div>

    <button type="submit" class="button">登入</button>
</form>
@endsection
