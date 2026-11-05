@extends('layouts.app')

@section('title', $other ? '傳紙條給 ' . $other->name : '傳紙條')

@section('main_class', 'wrap wrap-wide')

@section('content')
<h1>傳紙條</h1>
{{-- 設計稿 402:13701：左邊信件清單白卡、右邊一封一張卡（對方藍灰、自己蜜桃）、珊瑚色回信鈕 --}}

<div class="notes-layout @if($other) has-thread @endif">
    <section class="notes-threads" aria-labelledby="threads-title">
        @php($unreadTotal = $threads->sum('unread'))
        <h2 id="threads-title" class="threads-head"><img src="{{ asset('images/brand/nav-notes.svg') }}" alt="" width="28" height="28">
            @if ($unreadTotal > 0)您有 <span class="threads-count">{{ $unreadTotal }}</span> 張紙條未讀@else對話清單@endif</h2>
        @if ($threads->isEmpty())
            <p>還沒有紙條。到「認識朋友」找一位，在他的檔案頁按「傳紙條」。</p>
        @else
            <ul class="thread-list">
                @foreach ($threads as $t)
                    <li>
                        <a href="{{ route('notes.show', $t->user) }}" class="thread-item" @if($other && $other->is($t->user)) aria-current="page" @endif>
                            @include('partials.avatar', ['user' => $t->user])
                            <span class="thread-text">
                                <span class="thread-top"><span class="thread-name">{{ $t->user->name }}@if($t->blocked) <span class="tag">已封鎖</span>@endif</span>
                                    <time class="thread-date" datetime="{{ $t->last->created_at->toIso8601String() }}">{{ $t->last->created_at->shown()->format('Y/m/d') }}</time></span>
                                <span class="thread-last">{{ \Illuminate\Support\Str::limit($t->last->body, 40) }}</span>
                            </span>
                            @if ($t->unread > 0)
                                <span class="badge" aria-hidden="true">{{ $t->unread }}</span><span class="visually-hidden">，{{ $t->unread }} 則未讀</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="notes-thread" aria-labelledby="thread-title">
        @if (! $other)
            <h2 id="thread-title" class="visually-hidden">對話內容</h2>
            <p class="hint">請選擇一個對話。</p>
        @else
            <h2 id="thread-title">和 <a href="{{ route('members.show', $other) }}">{{ $other->name }}</a> 的紙條</h2>
            <p class="notes-back"><a href="{{ route('notes.index') }}">← 回對話清單</a></p>

            @if ($notes->isEmpty())
                <p class="hint">還沒有紙條，寫第一張吧。</p>
            @else
                <ol class="note-list">
                    @foreach ($notes as $n)
                        @php($mine = $n->sender_id === auth()->id())
                        <li class="note {{ $mine ? 'note-mine' : 'note-theirs' }}">
                            <div class="note-head">
                                @include('partials.avatar', ['user' => $mine ? auth()->user() : $other, 'size' => 'sm'])
                                <p class="note-who">{{ $mine ? '我' : $other->name }}<span class="visually-hidden">：</span></p>
                                <p class="note-time"><time datetime="{{ $n->created_at->toIso8601String() }}">{{ $n->created_at->shown()->format('Y-m-d H:i') }}</time>@if($mine && $n->read_at)・已讀@endif</p>
                            </div>
                            <div class="note-body">{!! nl2br(e($n->body)) !!}</div>
                        </li>
                    @endforeach
                </ol>
            @endif

            @if ($readOnly ?? false)
            <p class="notice" role="status">{{ auth()->user()->hasBlocked($other) ? '您已封鎖這位會員' : ($other->deleted_at ? '這位會員已經註銷帳號' : '這位會員暫停了帳號') }}，這裡只能看以前的紙條，不能再寄。要檢舉的話，這些紙條可以當作證據。</p>
            @else
            <form method="POST" action="{{ route('notes.store', $other) }}" class="form note-form" novalidate>
                @csrf
                @include('partials.errors', ['title' => '紙條沒有送出'])
                <div class="field">
                    <label for="body">寫給 {{ $other->name }}</label>
                    <p id="body-hint" class="hint">最多 1000 字。請不要在紙條裡留電話、住址等聯絡方式。</p>
                    <textarea id="body" name="body" rows="4" maxlength="1000" required
                              aria-describedby="{{ trim('body-hint' . ($errors->has('body') ? ' body-error' : '')) }}"
                              @error('body') aria-invalid="true" @enderror>{{ old('body') }}</textarea>
                    @error('body')<p id="body-error" class="field-error">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="button note-send">回信</button>
            </form>
            @endif
        @endif
    </section>
</div>
@endsection
