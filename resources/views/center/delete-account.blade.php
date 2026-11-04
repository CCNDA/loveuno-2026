@extends('layouts.app')

@section('title', '註銷帳號')

@section('content')
<h1>註銷帳號</h1>
<ul class="prose">
    <li>註銷後，您的暱稱、Email、個人檔案、照片、日記、留言、好友、愛諾積點都會刪掉，<strong>不能恢復</strong>。</li>
    <li>您傳給別人的紙條、送出的禮物會留在對方那邊（顯示「已註銷會員」），檢舉紀錄也會留著。</li>
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
    <div class="field">
        <label for="reason">為什麼要註銷？</label>
        <p id="reason-hint" class="hint">至少 10 個字。會寄給愛諾園的管理員，讓我們知道可以怎麼改進。</p>
        <textarea id="reason" name="reason" rows="4" maxlength="1000" required
                  aria-describedby="reason-hint{{ $bag->has('reason') ? ' reason-error' : '' }}" @if ($bag->has('reason')) aria-invalid="true" @endif>{{ old('reason') }}</textarea>
        @if ($bag->has('reason'))<p id="reason-error" class="field-error" role="alert">{{ $bag->first('reason') }}</p>@endif
    </div>
    <div class="field">
        <div class="field-check">
            <input id="confirm" name="confirm" type="checkbox" value="1" @if ($bag->has('confirm')) aria-invalid="true" aria-describedby="confirm-error" @endif>
            <label for="confirm">我了解註銷後不能恢復</label>
        </div>
        @if ($bag->has('confirm'))<p id="confirm-error" class="field-error" role="alert">{{ $bag->first('confirm') }}</p>@endif
    </div>
    <div class="review-actions">
        <button type="submit" class="button button-secondary">確定註銷</button>
        <a class="button" href="{{ route('center.account') }}">不要註銷</a>
    </div>
</form>
@endsection
