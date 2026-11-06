@extends('layouts.app')

@section('title', '送禮物')

@section('main_class', 'wrap airmail')

@section('content')
<h1>送禮物給 {{ $member->name }}</h1>
<p class="prose">送一份禮物給陌生網友是對他(她)表達好感的第一步。若對方回送給您一個禮物，雙方就成為「普通好友」囉。</p>
<ul class="prose">
    <li>送禮物需要使用 {{ $cost }} 點愛諾積點（您的積點餘額：<strong>{{ $balance }}</strong> 點）</li>
    <li>每天只可送一份禮物給同一個會員</li>
</ul>

@include('gifts.partials.form')
<p><a href="{{ route('members.show', $member) }}">← 回 {{ $member->name }} 的檔案</a></p>
<script src="{{ asset('js/char-count.js') }}?v={{ $appVersion }}" defer></script>
@endsection
