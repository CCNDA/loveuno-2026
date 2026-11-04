@extends('layouts.app')

@section('title', '回報問題／建議')

@section('content')
<h1>回報問題／建議</h1>
<p class="meta">會員（或還沒登入的人）在每一頁下面按「回報問題／建議」送來的。處理好了按「已處理」。</p>

<form method="GET" action="{{ route('admin.feedback') }}" class="filters" aria-label="篩選回報">
    <div class="field">
        <label for="show">看</label>
        <select id="show" name="show">
            @foreach (['todo' => '還沒處理、處理中', 'done' => '已處理', 'all' => '全部'] as $v => $label)
                <option value="{{ $v }}" @selected($filters['show'] === $v)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="kind">類型</label>
        <select id="kind" name="kind">
            <option value="">不限</option>
            @foreach (\App\Models\Feedback::KINDS as $v => $label)
                <option value="{{ $v }}" @selected(($filters['kind'] ?? '') === $v)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="user">會員編號</label>
        <input id="user" name="user" type="number" min="1" inputmode="numeric" value="{{ $filters['user'] ?? '' }}">
    </div>
    <button type="submit" class="button">篩選</button>
    <a href="{{ route('admin.feedback.export', request()->query()) }}">匯出這些（CSV）</a>
</form>

<p class="meta" role="status">共 {{ $items->total() }} 則</p>

@forelse ($items as $f)
    <article class="report-item" aria-labelledby="f-{{ $f->id }}">
        <h2 id="f-{{ $f->id }}">第 {{ $f->id }} 則：{{ $f->kindLabel() }}（{{ \App\Models\Feedback::STATUSES[$f->status] }}）</h2>
        <dl class="report-meta">
            <dt>誰</dt>
            <dd>
                @if ($f->user_id)
                    <a href="{{ route('admin.members.show', $f->user_id) }}">{{ $f->user?->name }}（#{{ $f->user_id }}）</a>
                    ・<a href="{{ route('admin.feedback', ['show' => 'all', 'user' => $f->user_id]) }}">他送過 {{ $perUser[$f->user_id] ?? 1 }} 則</a>
                @else
                    沒有登入{{ $f->email !== '' ? '（' . $f->email . '）' : '（沒有留 Email）' }}
                @endif
            </dd>
            {{-- 頁面只顯示文字，不做成連結：網址是送出的人給的 --}}
            <dt>頁面</dt><dd>{{ $f->page !== '' ? $f->page : '（沒有記到）' }}</dd>
            <dt>裝置</dt><dd>{{ $f->deviceLabel() }}</dd>
            @if ($f->context !== '')<dt>環境</dt><dd>{{ $f->context }}</dd>@endif
            <dt>時間</dt><dd>{{ $f->created_at->shown()->format('Y/n/j H:i') }}</dd>
            @if ($f->handled_at)<dt>處理</dt><dd>{{ $f->handler?->name }} {{ $f->handled_at->shown()->format('Y/n/j H:i') }}</dd>@endif
        </dl>
        <p class="prose">{!! nl2br(e($f->body)) !!}</p>
        @if ($f->screenshot_path)
            <p><a href="{{ route('admin.feedback.screenshot', $f) }}">看截圖<span class="visually-hidden">（第 {{ $f->id }} 則）</span></a></p>
        @endif
        <form method="POST" action="{{ route('admin.feedback.status', $f) }}" class="review-actions">
            @csrf
            @foreach (\App\Models\Feedback::STATUSES as $v => $label)
                @continue($v === $f->status)
                <button type="submit" name="status" value="{{ $v }}" class="button{{ $v === 'done' ? '' : ' button-secondary' }}">{{ $v === 'open' ? '改回還沒處理' : $label }}<span class="visually-hidden">（第 {{ $f->id }} 則）</span></button>
            @endforeach
        </form>
    </article>
@empty
    <p>{{ $filters['show'] === 'todo' ? '目前沒有要處理的回報。' : '沒有符合的回報。' }}</p>
@endforelse

{{ $items->links('partials.pagination') }}
@endsection
