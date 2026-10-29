@extends('layouts.app')

@section('title', '註銷帳號')

@section('content')
<h1>註銷帳號</h1>
<ul class="prose">
    <li>註銷後，您的帳號、個人檔案、照片、日記、留言、紙條、禮物、好友、愛諾積點都會刪掉，<strong>不能恢復</strong>。</li>
    <li>只是想暫時休息的話，可以到「帳號設定」把「啟用帳號」關掉，資料都會留著。</li>
</ul>

@php($bag = $errors->getBag('delete'))
<form method="POST" action="{{ route('settings.destroy') }}" class="form" novalidate>
    @csrf
    @method('DELETE')
    <div class="field">
        <label for="current_password">請輸入密碼</label>
        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required
               @if ($bag->has('current_password')) aria-invalid="true" aria-describedby="current_password-error" @endif>
        @if ($bag->has('current_password'))<p id="current_password-error" class="field-error" role="alert">{{ $bag->first('current_password') }}</p>@endif
    </div>
    <div class="field field-check">
        <input id="confirm" name="confirm" type="checkbox" value="1" @if ($bag->has('confirm')) aria-invalid="true" aria-describedby="confirm-error" @endif>
        <label for="confirm">我了解註銷後不能恢復</label>
    </div>
    @if ($bag->has('confirm'))<p id="confirm-error" class="field-error" role="alert">{{ $bag->first('confirm') }}</p>@endif
    <div class="review-actions">
        <button type="submit" class="button button-secondary">確定註銷</button>
        <a class="button" href="{{ route('center.account') }}">不要註銷</a>
    </div>
</form>
@endsection
