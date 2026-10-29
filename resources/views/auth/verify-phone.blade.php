@extends('layouts.app')

@section('title', '手機認證')

@section('content')
<h1>{{ $changing ? '更換手機號碼' : '手機認證' }}</h1>
@unless ($changing)
    <p>電子郵件驗證好了！最後一步：請驗證您的手機，驗證好就可以開始使用愛諾園。</p>
@endunless
<p class="hint">收不到簡訊的話，請寫信到 <a href="mailto:{{ $contact }}">{{ $contact }}</a> 跟我們聯繫，信裡寫您註冊用的電子郵件。</p>

@if ($pending)
    <h2>輸入認證碼</h2>
    <p>認證碼已經用簡訊寄到 <strong>{{ $pending }}</strong>，{{ $minutes }} 分鐘內有效。</p>
    <form method="POST" action="{{ route('phone.verify') }}" class="form" novalidate>
        @csrf
        @include('partials.errors', ['title' => '還沒有認證成功'])
        <div class="field">
            <label for="code">認證碼（6 個數字）</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required
                   @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
            @error('code')<p id="code-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="button">確定送出</button>
    </form>
    <form method="POST" action="{{ route('phone.reset') }}" class="phone-reset">
        @csrf
        <button type="submit" class="link-button">{{ $changing ? '不換了（原本的號碼還是認證過的）' : '號碼打錯了，或要重寄認證碼' }}</button>
    </form>
@else
    <form method="POST" action="{{ route('phone.send') }}" class="form" novalidate>
        @csrf
        @include('partials.errors', ['title' => '認證碼還沒寄出'])
        <div class="field">
            <label for="phone">手機號碼</label>
            <p id="phone-hint" class="hint">台灣的手機號碼，10 碼，09 開頭，例如 0912345678。一支手機只能認證一個帳號，不會顯示給其他會員。</p>
            <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel-national" maxlength="12" value="{{ old('phone') }}" required
                   aria-describedby="phone-hint @error('phone') phone-error @enderror" @error('phone') aria-invalid="true" @enderror>
            @error('phone')<p id="phone-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="button">寄認證碼</button>
    </form>
@endif

@unless ($changing)
    <form method="POST" action="{{ route('logout') }}" class="phone-reset">
        @csrf
        <button type="submit" class="link-button">先登出，等一下再來</button>
    </form>
@endunless
@endsection
