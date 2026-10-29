@extends('layouts.app')

@section('title', '網路檢測')

@section('content')
<h1>網路檢測</h1>
<p class="notice" role="status">連線正常：網站和資料庫都連得到。</p>
<dl class="profile">
    <div><dt>伺服器處理時間</dt><dd>{{ $ms }} 毫秒</dd></div>
    <div><dt>伺服器時間</dt><dd>{{ $now->format('Y/n/j H:i:s') }}（台北）</dd></div>
</dl>
<p class="hint">如果網站一直很慢或打不開，請寫信到 <a href="mailto:{{ config('loveuno.contact_email') }}">{{ config('loveuno.contact_email') }}</a>，告訴我們您用的是手機還是電腦、哪一個瀏覽器。</p>
<p><a href="{{ route('center.account') }}#settings-title">← 回帳號設定</a></p>
@endsection
