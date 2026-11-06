@extends('layouts.app')

@section('title', '約會')

@section('content')
@php($other = $m->otherThan($me))
<p class="meta"><a href="{{ route('meetups.index') }}">我的約會</a></p>
<h1>{{ $isRecipient ? ($other?->name ?? '已註銷會員') . ' 邀請您約會' : '您邀請 ' . ($other?->name ?? '已註銷會員') . ' 約會' }}</h1>
<p><span class="tag">{{ $m->stateLabel() }}</span></p>
<dl class="profile">
    <div><dt>日期</dt><dd>{{ $m->meet_on->format('Y/n/j') }}（{{ ['日', '一', '二', '三', '四', '五', '六'][$m->meet_on->dayOfWeek] }}）</dd></div>
    <div><dt>時間</dt><dd>{{ $m->meet_time }}</dd></div>
    <div><dt>地點</dt><dd>{{ $m->place }}</dd></div>
    <div><dt>活動類型</dt><dd>{{ \App\Models\Meetup::ACTIVITIES[$m->activity] ?? $m->activity }}</dd></div>
    <div><dt>邀請對象</dt><dd>{{ \App\Models\Meetup::COMPANY[$m->company] ?? $m->company }}</dd></div>
    @if ($m->message)<div><dt>想說的話</dt><dd class="prose">{!! nl2br(e($m->message)) !!}</dd></div>@endif
    @if ($m->reply)<div><dt>{{ $m->status === 'declined' ? '婉拒時說的話' : '接受時說的話' }}</dt><dd class="prose">{!! nl2br(e($m->reply)) !!}</dd></div>@endif
    @if ($m->closing_note)<div><dt>{{ ['cancelled' => '取消的原因', 'attended' => '約會感言', 'absent' => '沒有參加的原因'][$m->status] ?? '說明' }}</dt><dd class="prose">{!! nl2br(e($m->closing_note)) !!}</dd></div>@endif
</dl>

@php($state = $m->state())
@if ($isRecipient && $state === 'pending')
    <form method="POST" action="{{ route('meetups.respond', $m) }}" class="form" novalidate>
        @csrf
        @include('partials.errors', ['title' => '還沒有回覆'])
        <div class="field">
            <label for="reply">想對 {{ $other?->name }} 說的話<span class="hint">（可以不寫）</span></label>
            <textarea id="reply" name="reply" rows="4" maxlength="500" @error('reply') aria-invalid="true" aria-describedby="reply-error" @enderror>{{ old('reply') }}</textarea>
            @error('reply')<p id="reply-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <div id="decision" tabindex="-1" class="review-actions">
            <button type="submit" name="decision" value="decline" class="button button-secondary">不參加</button>
            <button type="submit" name="decision" value="accept" class="button">參加</button>
        </div>
    </form>
@elseif ($state === 'accepted')
    <form method="POST" action="{{ route('meetups.cancel', $m) }}" class="form" novalidate>
        @csrf
        @include('partials.errors', ['title' => '還沒有取消'])
        <div class="field">
            <label for="closing_note">要取消的話，請寫原因</label>
            <textarea id="closing_note" name="closing_note" rows="3" maxlength="500" required @error('closing_note') aria-invalid="true" aria-describedby="closing_note-error" @enderror>{{ old('closing_note') }}</textarea>
            @error('closing_note')<p id="closing_note-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="button button-secondary">取消約會</button>
    </form>
@elseif ($isRecipient && $state === 'past')
    <form method="POST" action="{{ route('meetups.attend', $m) }}" class="form" novalidate>
        @csrf
        @include('partials.errors', ['title' => '還沒有確認'])
        <div class="field">
            <label for="closing_note">約會感言，或沒有參加的原因</label>
            <textarea id="closing_note" name="closing_note" rows="4" maxlength="500" required @error('closing_note') aria-invalid="true" aria-describedby="closing_note-error" @enderror>{{ old('closing_note') }}</textarea>
            @error('closing_note')<p id="closing_note-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <div id="attended" tabindex="-1" class="review-actions">
            <button type="submit" name="attended" value="yes" class="button">已參加</button>
            <button type="submit" name="attended" value="no" class="button button-secondary">沒有參加</button>
        </div>
    </form>
@elseif (! $isRecipient && $state === 'past')
    <p>約會日過了，等 {{ $other?->name }} 確認有沒有參加。</p>
@endif
@if ($other)
    <p><a class="button button-light" href="{{ route('notes.show', $other) }}">傳紙條給 {{ $other->name }}</a></p>
@endif
@endsection
