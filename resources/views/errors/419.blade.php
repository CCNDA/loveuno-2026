@extends('layouts.app')

{{-- 錯誤頁也要能回報（年長會友最常卡在這裡；回應表單兩面審查 11/2） --}}
@section('title', '頁面放太久了')

@section('content')
<h1>頁面放太久了</h1>
<p>這一頁放太久沒有動，為了安全，剛剛送出的內容沒有收到。請回上一頁重新整理後再送一次。</p>
<p><a class="button" href="{{ route('home') }}">回首頁</a></p>
<p><a href="{{ route('feedback.create', ['from' => request()->getRequestUri()]) }}" target="_blank" rel="noopener">回報問題<span class="visually-hidden">（開新分頁）</span></a>：告訴我們您剛剛在做什麼、按了什麼，我們來查。</p>
@endsection
