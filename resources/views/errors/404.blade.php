@extends('layouts.app')

{{-- 錯誤頁也要能回報（年長會友最常卡在這裡；回應表單兩面審查 11/2） --}}
@section('title', '找不到這一頁')

@section('content')
<div class="error-hero">
    {{-- 設計稿 404（378:19030）的插圖和「該網頁不存在！ Page Not Found」 --}}
    <img src="{{ asset('images/errors/404.svg') }}" alt="" width="738" height="547">
    <h1>該網頁不存在！<span lang="en">Page Not Found</span></h1>
    <p class="visually-hidden">找不到這一頁。</p>
    <p>這個網址找不到東西。可能是連結打錯了，或是那一篇已經刪掉了。</p>
    <p><a class="button pill-submit" href="{{ route('home') }}">回首頁</a></p>
</div>
<p><a href="{{ route('feedback.create', ['from' => request()->getRequestUri()]) }}" target="_blank" rel="noopener">回報問題<span class="visually-hidden">（開新分頁）</span></a>：告訴我們您剛剛在做什麼、按了什麼，我們來查。</p>
@endsection
