@extends('layouts.app')

@section('title', '請到信箱收信')

@section('content')
<h1>請到信箱收信</h1>
<p>我們寄了一封信到您填的電子郵件，請點信裡的連結完成驗證，再回來登入。連結 60 分鐘內有效。</p>
<p class="hint">沒收到的話，請看一下垃圾郵件匣。</p>
<p><a class="button" href="{{ route('login') }}">到登入頁</a></p>
@endsection
