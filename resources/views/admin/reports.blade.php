@extends('layouts.app')

@section('title', '檢舉')

@section('content')
<h1>檢舉</h1>

<section aria-labelledby="open-title">
    <h2 id="open-title">還沒處理</h2>
    <p class="meta">{{ $open->count() }} 件</p>
    @if ($open->isEmpty())
        <p>目前沒有要處理的檢舉。</p>
    @else
        <ol class="report-list">
            @foreach ($open as $r)
                <li class="report-item">
                    <article aria-labelledby="report-{{ $r->id }}">
                        <h3 id="report-{{ $r->id }}">第 {{ $r->id }} 號：{{ $r->reasonLabel() }}</h3>
                        <dl class="report-meta">
                            <dt>被檢舉</dt>
                            <dd>@if ($r->reported)<a href="{{ route('members.show', $r->reported) }}">{{ $r->reported->name }}</a>（#{{ $r->reported->id }}）@else（帳號已刪除）@endif</dd>
                            <dt>檢舉人</dt>
                            <dd>@if ($r->reporter){{ $r->reporter->name }}（#{{ $r->reporter->id }}）@else（帳號已刪除）@endif</dd>
                            <dt>時間</dt>
                            <dd><time datetime="{{ $r->created_at->toIso8601String() }}">{{ $r->created_at->shown()->format('Y/n/j H:i') }}</time></dd>
                        </dl>
                        <p class="prose">{!! nl2br(e($r->description)) !!}</p>
                        @if ($r->screenshot_path)
                            <p><a href="{{ route('admin.reports.screenshot', $r) }}" target="_blank" rel="noopener">看附的圖片<span class="visually-hidden">（第 {{ $r->id }} 號，開新視窗）</span></a></p>
                        @endif
                        @php($bag = $errors->getBag('report' . $r->id))
                        <form method="POST" action="{{ route('admin.reports.close', $r) }}" class="form" novalidate>
                            @csrf
                            <div class="field">
                                <label for="admin_note-{{ $r->id }}">處理方式<span class="visually-hidden">（第 {{ $r->id }} 號）</span></label>
                                <textarea id="admin_note-{{ $r->id }}" name="admin_note" rows="2" maxlength="500" required
                                          @if ($bag->has('admin_note')) aria-invalid="true" aria-describedby="admin_note-{{ $r->id }}-error" @endif>{{ $bag->any() ? old('admin_note') : '' }}</textarea>
                                @if ($bag->has('admin_note'))<p id="admin_note-{{ $r->id }}-error" class="field-error" role="alert">{{ $bag->first('admin_note') }}</p>@endif
                            </div>
                            <button type="submit" class="button">標成已處理<span class="visually-hidden">（第 {{ $r->id }} 號）</span></button>
                        </form>
                    </article>
                </li>
            @endforeach
        </ol>
    @endif
</section>

<section aria-labelledby="closed-title">
    <h2 id="closed-title">最近處理過的</h2>
    @if ($closed->isEmpty())
        <p>還沒有。</p>
    @else
        <ul class="plain-list">
            @foreach ($closed as $r)
                <li>第 {{ $r->id }} 號 {{ $r->reasonLabel() }}・{{ $r->reported?->name ?? '（帳號已刪除）' }}・{{ $r->handler?->name ?? '?' }} {{ $r->handled_at?->format('Y/n/j') }}：{{ $r->admin_note }}</li>
            @endforeach
        </ul>
    @endif
</section>
@endsection
