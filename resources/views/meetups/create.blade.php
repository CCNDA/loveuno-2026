@extends('layouts.app')

@section('title', '約會')

@section('main_class', 'wrap airmail')

@section('content')
{{-- 設計稿 AskOutDialog（對話框改成一頁，跟送禮物一樣）：邀請你(妳)於、時間、地點、活動類型、邀請對象、想說的話 --}}
<h1>邀請 {{ $member->name }} 約會</h1>
@include('meetups.partials.form')
@endsection
