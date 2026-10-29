@extends('layouts.app')

@section('title', '聯絡我們的來信')

@section('content')
<h1>聯絡我們的來信</h1>
<p class="meta">信也會寄到 {{ config('loveuno.contact_email') }}；這裡是備份，回完信按「標成處理過了」。</p>
<section aria-labelledby="open-title">
    <h2 id="open-title">還沒處理（{{ $open->count() }}）</h2>
    @forelse ($open as $m)
        <article class="report-item" aria-labelledby="c-{{ $m->id }}">
            <h3 id="c-{{ $m->id }}">第 {{ $m->id }} 封：{{ $m->subjectLabel() }}</h3>
            <dl class="report-meta">
                <dt>姓名</dt><dd>{{ $m->name }}</dd>
                <dt>Email</dt><dd>{{ $m->email }}</dd>
                <dt>會員</dt><dd>@if ($m->user_id)<a href="{{ route('members.show', $m->user_id) }}">#{{ $m->user_id }}</a>@else（沒有登入）@endif</dd>
                <dt>時間</dt><dd>{{ $m->created_at->shown()->format('Y/n/j H:i') }}</dd>
            </dl>
            <p class="prose">{!! nl2br(e($m->body)) !!}</p>
            <form method="POST" action="{{ route('admin.contacts.handle', $m) }}">
                @csrf
                <button type="submit" class="button">標成處理過了<span class="visually-hidden">（第 {{ $m->id }} 封）</span></button>
            </form>
        </article>
    @empty
        <p>目前沒有要處理的來信。</p>
    @endforelse
</section>
<section aria-labelledby="done-title">
    <h2 id="done-title">最近處理過的</h2>
    <ul class="plain-list">
        @foreach ($done as $m)
            <li>第 {{ $m->id }} 封 {{ $m->subjectLabel() }}・{{ $m->name }}・{{ $m->handled_at->shown()->format('Y/n/j') }}</li>
        @endforeach
    </ul>
</section>
@endsection
