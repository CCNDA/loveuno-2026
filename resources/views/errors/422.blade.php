@extends('layouts.app')

{{-- 2026-11-17 Asana 1220377223503375：abort(422, '中文原因') 原本落到 Laravel 預設的英文頁；改顯示我們寫的中文原因 --}}
@section('title', '這個動作不能做')

@section('content')
<h1>這個動作不能做</h1>
<p>{{ ($exception ?? null)?->getMessage() ?: '這個動作目前不能做。' }}</p>
<p><a class="button" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('home') }}">回上一頁</a></p>
<p><a href="{{ route('feedback.create', ['from' => request()->getRequestUri()]) }}" target="_blank" rel="noopener">回報問題<span class="visually-hidden">（開新分頁）</span></a>：告訴我們您剛剛在做什麼、按了什麼，我們來查。</p>
@endsection
