@extends('layouts.app')

@section('title', '加入會員')
@section('bare', 'auth')
@section('main_class', 'auth-page')

@section('content')
{{-- 照設計稿 Register 209:21701（電腦：左插圖、右註冊框）／[MOBILE] Register 209:21680；版型跟登入頁共用（小鹿 11/6 #2）。
     欄位順序照設計 Email → 暱稱 → 密碼 → 確認密碼；性別、出生年、會員規範是愛諾園原本就要的（設計稿沒畫），放在後面。 --}}
<div class="auth-illustration" aria-hidden="true"><img src="{{ asset('images/welcome-hero.svg') }}" alt="" width="763" height="389"></div>
<div class="auth-panel">
    <div class="auth-box">
        <p class="auth-script">@include('partials.script-toggle')</p>
        <a class="auth-logo" href="{{ route('home') }}"><img src="{{ asset('images/brand/logo-mark.svg') }}" alt="愛諾園 首頁" width="90" height="90"></a>
        <h1>註冊會員</h1>
        <p class="auth-sub">填好資料，我們會寄驗證信給您。只開放 18 歲以上。</p>
        <nav class="auth-tabs" aria-label="登入或註冊">
            <a href="{{ route('login') }}">登入</a>
            <a href="{{ route('register') }}" aria-current="page">註冊</a>
        </nav>

        <form method="POST" action="{{ route('register.store') }}" novalidate>
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

            <div class="auth-field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" inputmode="email" autocomplete="email" maxlength="100"
                       value="{{ old('email') }}" required
                       @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            </div>
            @error('email')<p id="email-error" class="field-error">{{ $message }}</p>@enderror

            <div class="auth-field">
                <label for="name">暱稱</label>
                <input id="name" name="name" type="text" autocomplete="nickname" maxlength="20" value="{{ old('name') }}" required
                       aria-describedby="name-hint @error('name') name-error @enderror"
                       @error('name') aria-invalid="true" @enderror>
            </div>
            <p id="name-hint" class="auth-hint">會顯示給其他會員看，不用寫真實姓名。</p>
            @error('name')<p id="name-error" class="field-error">{{ $message }}</p>@enderror

            <div class="auth-field auth-field-password">
                <label for="password">密碼</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required
                       aria-describedby="password-hint @error('password') password-error @enderror"
                       @error('password') aria-invalid="true" @enderror>
                <button type="button" class="password-show" aria-controls="password" aria-pressed="false" hidden>
                    <img src="{{ asset('images/brand/eye.svg') }}" alt="" width="24" height="24"><span class="visually-hidden">顯示密碼</span>
                </button>
            </div>
            <p id="password-hint" class="auth-hint">至少 10 個字元。</p>
            @error('password')<p id="password-error" class="field-error">{{ $message }}</p>@enderror

            <div class="auth-field">
                <label for="password_confirmation">確認密碼</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            </div>

            {{-- 錯誤摘要的 #gender 連到整組 --}}
            <fieldset id="gender" tabindex="-1" class="auth-choice">
                <legend>性別</legend>
                @foreach (['男' => '弟兄', '女' => '姊妹'] as $value => $label)
                    <span class="auth-remember">
                        <input id="gender-{{ $loop->index }}" name="gender" type="radio" value="{{ $value }}" @checked(old('gender') === $value)
                               @error('gender') aria-invalid="true" aria-describedby="gender-error" @enderror>
                        <label for="gender-{{ $loop->index }}">{{ $label }}</label>
                    </span>
                @endforeach
            </fieldset>
            @error('gender')<p id="gender-error" class="field-error">{{ $message }}</p>@enderror

            <div class="auth-field auth-field-select">
                <label for="birth_year">出生年</label>
                <select id="birth_year" name="birth_year" required @error('birth_year') aria-invalid="true" aria-describedby="birth_year-error" @enderror>
                    <option value="">請選擇</option>
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected((string) old('birth_year') === (string) $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            @error('birth_year')<p id="birth_year-error" class="field-error">{{ $message }}</p>@enderror

            <div class="auth-agree">
                <input id="agree" name="agree" type="checkbox" value="1" @checked(old('agree'))
                       @error('agree') aria-invalid="true" aria-describedby="agree-error" @enderror>
                <label for="agree">已閱讀並同意<a href="{{ route('rules') }}" target="_blank" rel="noopener">「會員規範」<span class="visually-hidden">（另開新分頁）</span></a></label>
            </div>
            @error('agree')<p id="agree-error" class="field-error">{{ $message }}</p>@enderror

            <button type="submit" class="button auth-submit">註冊</button>
        </form>
    </div>
    <p class="auth-links"><a href="{{ route('feedback.create', ['from' => request()->getRequestUri()]) }}" target="_blank" rel="noopener">回報問題／建議<span class="visually-hidden">（開新分頁）</span></a>　<a href="{{ route('privacy') }}">隱私權政策</a></p>
    <p class="auth-serve">網站服務由 中華基督教網路發展協會 提供<br><span lang="en">Serve by Chinese Christian Network Development Association</span></p>
</div>
<script src="{{ asset('js/password-show.js') }}?v={{ $appVersion }}" defer></script>
@endsection
