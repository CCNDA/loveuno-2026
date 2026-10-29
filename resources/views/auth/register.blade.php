@extends('layouts.app')

@section('title', '加入會員')

@section('content')
<h1>加入會員</h1>
<p>填好下面的資料，我們會寄一封驗證信給您。愛諾園只開放 18 歲以上的會員。</p>

<form method="POST" action="{{ route('register.store') }}" class="form" novalidate>
    @csrf

    @if ($errors->any())
        <div class="error-summary" role="alert" aria-labelledby="error-title">
            <h2 id="error-title">還有 {{ $errors->count() }} 個地方要改</h2>
            <ul>
                @foreach ($errors->messages() as $field => $messages)
                    @foreach ($messages as $message)
                        <li><a href="#{{ $field }}">{{ $message }}</a></li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    @endif

    <div class="field">
        <label for="email">電子郵件</label>
        <input id="email" name="email" type="email" inputmode="email" autocomplete="email" maxlength="100"
               value="{{ old('email') }}" required
               @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
        @error('email')<p id="email-error" class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="password">密碼</label>
        <p id="password-hint" class="hint">至少 10 個字元。</p>
        <input id="password" name="password" type="password" autocomplete="new-password" required
               aria-describedby="password-hint @error('password') password-error @enderror"
               @error('password') aria-invalid="true" @enderror>
        @error('password')<p id="password-error" class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="password_confirmation">再輸入一次密碼</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
    </div>

    <div class="field">
        <label for="name">暱稱</label>
        <p id="name-hint" class="hint">會顯示給其他會員看，不用寫真實姓名。</p>
        <input id="name" name="name" type="text" autocomplete="nickname" maxlength="20" value="{{ old('name') }}" required
               aria-describedby="name-hint @error('name') name-error @enderror"
               @error('name') aria-invalid="true" @enderror>
        @error('name')<p id="name-error" class="field-error">{{ $message }}</p>@enderror
    </div>

    {{-- 錯誤摘要的 #gender 連到整組 --}}
    <fieldset id="gender" tabindex="-1" class="field">
        <legend>性別</legend>
        @foreach (['男' => '弟兄', '女' => '姊妹'] as $value => $label)
            <div class="field-check">
                <input id="gender-{{ $loop->index }}" name="gender" type="radio" value="{{ $value }}" @checked(old('gender') === $value)
                       @error('gender') aria-invalid="true" aria-describedby="gender-error" @enderror>
                <label for="gender-{{ $loop->index }}">{{ $label }}</label>
            </div>
        @endforeach
        @error('gender')<p id="gender-error" class="field-error">{{ $message }}</p>@enderror
    </fieldset>

    <div class="field">
        <label for="birth_year">出生年</label>
        <select id="birth_year" name="birth_year" required @error('birth_year') aria-invalid="true" aria-describedby="birth_year-error" @enderror>
            <option value="">請選擇</option>
            @foreach ($years as $y)
                <option value="{{ $y }}" @selected((string) old('birth_year') === (string) $y)>{{ $y }}</option>
            @endforeach
        </select>
        @error('birth_year')<p id="birth_year-error" class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field field-check">
        <input id="agree" name="agree" type="checkbox" value="1" @checked(old('agree'))
               @error('agree') aria-invalid="true" aria-describedby="agree-error" @enderror>
        <label for="agree">我已經讀過並同意愛諾園的<a href="{{ route('rules') }}" target="_blank" rel="noopener">會員規範（另開新分頁）</a></label>
    </div>
    @error('agree')<p id="agree-error" class="field-error">{{ $message }}</p>@enderror

    <button type="submit" class="button">加入會員</button>
</form>

<p>已經是會員了？<a href="{{ route('login') }}">直接登入</a></p>
@endsection
