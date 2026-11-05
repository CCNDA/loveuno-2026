@extends('layouts.app')

@section('title', '愛積點')

@section('content')
@include('center.tabs', ['current' => 'points'])

<h2>愛積點</h2>
<p class="prose">在愛諾園，您只要寫日記、完成會員資料建檔，都可以累積愛諾積點，而這些積點也是您在愛諾園認識新朋友的必需品。無論是送禮、或是約網友見面，都需要使用愛諾積點喔。現在就開始努力累積您的愛諾積點吧！</p>
<p class="points-balance">我目前的愛諾積點：<strong>{{ $user->points }}</strong> 點</p>

<nav aria-label="愛積點" class="center-subtabs">
    <ul>
        <li><a href="{{ route('center.points') }}" @if($tab === 'tasks') aria-current="page" @endif>任務獎勵</a></li>
        <li><a href="{{ route('center.points', ['tab' => 'records']) }}" @if($tab === 'records') aria-current="page" @endif>使用紀錄</a></li>
    </ul>
</nav>

@if ($tab === 'tasks')
    <h3 id="earn-title">可獲得積點項目</h3>
    <table class="points-table" aria-labelledby="earn-title">
        <thead><tr><th scope="col">項目</th><th scope="col">點數</th><th scope="col">狀態</th><th scope="col">完成日期</th></tr></thead>
        <tbody>
            @foreach (\App\Support\Points::TASKS as $type => [$amount, $label])
                <tr>
                    <th scope="row">{{ $label }}</th>
                    <td>+{{ $amount }}</td>
                    @if ($status[$type])
                        <td><span class="done-check" aria-hidden="true">✓</span><span class="visually-hidden">任務已完成</span></td>
                        <td>{{ \Illuminate\Support\Carbon::parse($status[$type])->shown()->format('Y/n/j') }}</td>
                    @else
                        <td><a href="{{ route($links[$type]) }}">立即前往<span class="visually-hidden">（{{ $label }}）</span></a></td>
                        <td>—</td>
                    @endif
                </tr>
            @endforeach
            @foreach (\App\Support\Points::DAILY as $type => [$amount, $label])
                <tr>
                    <th scope="row">{{ $label }}</th>
                    <td>+{{ $amount }}</td>
                    <td colspan="2">每天一次</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3 id="spend-title">可花費積點項目</h3>
    <table class="points-table" aria-labelledby="spend-title">
        <thead><tr><th scope="col">項目</th><th scope="col">點數</th><th scope="col">說明</th></tr></thead>
        <tbody>
            <tr><th scope="row">{{ \App\Support\Points::COSTS['gift'][1] }}</th><td>-{{ \App\Support\Points::COSTS['gift'][0] }}</td><td>同一位會員每天限送一次</td></tr>
            <tr><th scope="row">{{ \App\Support\Points::COSTS['date'][1] }}</th><td>-{{ \App\Support\Points::COSTS['date'][0] }}</td><td>每次邀請</td></tr>
        </tbody>
    </table>
@else
    <h3 id="records-title">使用紀錄</h3>
    @if ($records->isEmpty())
        <p>還沒有紀錄。</p>
    @else
        <table class="points-table" aria-labelledby="records-title">
            <thead><tr><th scope="col">項目</th><th scope="col">點數</th><th scope="col">完成日期</th><th scope="col">總計</th></tr></thead>
            <tbody>
                @foreach ($records as $r)
                    <tr>
                        <th scope="row">{{ $r->label }}</th>
                        <td>{{ $r->amount > 0 ? '+' : '' }}{{ $r->amount }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($r->created_at)->shown()->format('Y/n/j') }}</td>
                        <td>{{ $r->total }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if ($records->count() >= 100)<p class="hint">只列出最近 100 筆。</p>@endif
    @endif
@endif
@endsection
