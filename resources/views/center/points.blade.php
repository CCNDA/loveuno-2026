@extends('layouts.app')

@section('title', '愛積點')

@section('content')
@include('center.tabs', ['current' => 'points'])

<h2>愛積點</h2>
<p class="notice" role="status">愛積點和送禮物還在準備中，年底正式開放前會上線。</p>
{{-- 「一次 3 點」「同一位會員一天一份」是 loveuno-web 版型裡的文字（送禮物需要使用3點愛諾積點／每天只可送一份禮物給同一個會員） --}}
<p>愛積點可以用來送禮物給其他會員（一次 3 點，同一位會員一天一份）。點數怎麼得到，會照舊站的規則再跟大家說明。</p>
@endsection
