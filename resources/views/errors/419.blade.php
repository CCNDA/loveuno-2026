@extends('layouts.app')

{{-- 錯誤頁也要能回報（年長會友最常卡在這裡；回應表單兩面審查 11/2） --}}
@section('title', '頁面放太久了')

@section('content')
<h1>頁面放太久了</h1>
<p>這一頁放太久沒有動，為了安全，剛剛送出的內容沒有收到。請回上一頁重新整理後再送一次。</p>
{{-- 瀏覽器自己說 cookie 關掉才顯示（Asana 1220224661011603）。不從「有沒有帶 session cookie」推：
     登入頁放超過 2 小時 cookie 會過期、送出時一樣沒帶，那是真的放太久（小布 11/23 審查） --}}
<div class="error-summary" id="cookie-warn" role="alert" hidden>您的瀏覽器沒有開啟 cookie，所以登入狀態留不住。請到瀏覽器設定允許 cookie（不要用無痕分頁），再重新登入。</div>
<p><a class="button" href="{{ route('home') }}">回首頁</a></p>
<p><a href="{{ route('feedback.create', ['from' => request()->getRequestUri()]) }}" target="_blank" rel="noopener">回報問題<span class="visually-hidden">（開新分頁）</span></a>：告訴我們您剛剛在做什麼、按了什麼，我們來查。</p>
<script src="{{ asset('js/cookie-check.js') }}?v={{ $appVersion ?? '' }}" defer></script>
@endsection
