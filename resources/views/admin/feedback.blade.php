@extends('layouts.app')

@section('title', '回報問題／建議')

@section('content')
<h1>回報問題／建議</h1>
<p class="meta">會員在每一頁下面按「回報問題／建議」送來的。處理好了按「已處理」。</p>

<section aria-labelledby="open-title">
    <h2 id="open-title">還沒處理、處理中（{{ $open->count() }}）</h2>
    @forelse ($open as $f)
        <article class="report-item" aria-labelledby="f-{{ $f->id }}">
            <h3 id="f-{{ $f->id }}">第 {{ $f->id }} 則：{{ $f->kindLabel() }}（{{ \App\Models\Feedback::STATUSES[$f->status] }}）</h3>
            <dl class="report-meta">
                <dt>會員</dt><dd><a href="{{ route('admin.members.show', $f->user_id) }}">{{ $f->user?->name }}（#{{ $f->user_id }}）</a></dd>
                <dt>頁面</dt><dd>@if ($f->page !== '')<a href="{{ $f->page }}">{{ $f->page }}</a>@else（沒有記到）@endif</dd>
                <dt>裝置</dt><dd>{{ $f->deviceLabel() }}</dd>
                <dt>時間</dt><dd>{{ $f->created_at->shown()->format('Y/n/j H:i') }}</dd>
            </dl>
            <p class="prose">{!! nl2br(e($f->body)) !!}</p>
            @if ($f->screenshot_path)
                <p><a href="{{ route('admin.feedback.screenshot', $f) }}">看截圖<span class="visually-hidden">（第 {{ $f->id }} 則）</span></a></p>
            @endif
            <form method="POST" action="{{ route('admin.feedback.status', $f) }}" class="review-actions">
                @csrf
                @if ($f->status === 'open')
                    <button type="submit" name="status" value="doing" class="button button-secondary">處理中<span class="visually-hidden">（第 {{ $f->id }} 則）</span></button>
                @endif
                <button type="submit" name="status" value="done" class="button">已處理<span class="visually-hidden">（第 {{ $f->id }} 則）</span></button>
            </form>
        </article>
    @empty
        <p>目前沒有要處理的回報。</p>
    @endforelse
</section>

<section aria-labelledby="done-title">
    <h2 id="done-title">最近處理過的</h2>
    @if ($done->isEmpty())
        <p>還沒有。</p>
    @else
        <ul class="plain-list">
            @foreach ($done as $f)
                <li>第 {{ $f->id }} 則 {{ $f->kindLabel() }}・{{ $f->user?->name }}・{{ \Illuminate\Support\Str::limit($f->body, 30) }}・{{ $f->handler?->name }} {{ $f->handled_at?->shown()->format('Y/n/j') }}
                    <form method="POST" action="{{ route('admin.feedback.status', $f) }}" class="inline-form">
                        @csrf
                        <button type="submit" name="status" value="open" class="link-button">改回還沒處理<span class="visually-hidden">（第 {{ $f->id }} 則）</span></button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endsection
