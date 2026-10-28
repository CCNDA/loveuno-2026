@extends('layouts.app')

@section('content')
<section class="hero" aria-labelledby="hero-title">
    <h1 id="hero-title">在信仰裡，認識另一半</h1>
    <p class="lead">愛諾園是給基督徒單身朋友的交友園地。用心情日記分享生活，慢慢認識彼此。</p>
    @auth
        <p><a class="button" href="{{ route('diaries.index') }}">看大家的心情日記</a></p>
    @else
        <p><a class="button" href="{{ route('login') }}">會員登入</a></p>
        <p class="hint">舊站的帳號可以直接登入。</p>
    @endauth
</section>

<section aria-labelledby="features-title">
    <h2 id="features-title">新版有什麼不一樣</h2>
    <ul class="cards">
        <li class="card">
            <h3>更安全</h3>
            <p>密碼不再以原文保存；個人檔案與日記要登入才看得到。</p>
        </li>
        <li class="card">
            <h3>手機好用</h3>
            <p>字大、按鈕大，手機和電腦都好讀。</p>
        </li>
        <li class="card">
            <h3>保護隱私</h3>
            <p>不公開真實姓名與生日，只顯示年齡層；日記可以設成只有自己看得到。</p>
        </li>
    </ul>
</section>
@endsection
