@extends('layouts.app')

{{-- 錯誤頁也要能回報（年長會友最常卡在這裡；回應表單兩面審查 11/2） --}}
@section('title', request()->hasCookie(config('session.cookie')) ? '頁面放太久了' : '瀏覽器沒有開啟 cookie')

@section('content')
@if (! request()->hasCookie(config('session.cookie')))
{{-- 送出時沒有帶 session cookie：多半是瀏覽器不存 cookie（Asana 1220224661011603，熊叔叔 11/5 選 C1） --}}
<h1>瀏覽器沒有開啟 cookie</h1>
<p>您的瀏覽器沒有開啟 cookie，所以登入狀態留不住。請到瀏覽器設定允許 cookie（不要用無痕分頁），再重新登入。</p>
@else
<h1>頁面放太久了</h1>
<p>這一頁放太久沒有動，為了安全，剛剛送出的內容沒有收到。請回上一頁重新整理後再送一次。</p>
@endif
<p><a class="button" href="{{ route('home') }}">回首頁</a></p>
<p><a href="{{ route('feedback.create', ['from' => request()->getRequestUri()]) }}" target="_blank" rel="noopener">回報問題<span class="visually-hidden">（開新分頁）</span></a>：告訴我們您剛剛在做什麼、按了什麼，我們來查。</p>
@endsection
