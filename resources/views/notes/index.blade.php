@extends('layouts.app')

@section('title', $other ? '傳紙條給 ' . $other->name : '傳紙條')

@section('content')
<h1>傳紙條</h1>

<div class="notes-layout @if($other) has-thread @endif">
    <section class="notes-threads" aria-labelledby="threads-title">
        <h2 id="threads-title" class="visually-hidden">對話清單</h2>
        @if ($threads->isEmpty())
            <p>還沒有紙條。到「認識朋友」找一位，在他的檔案頁按「傳紙條」。</p>
        @else
            <ul class="thread-list">
                @foreach ($threads as $t)
                    <li>
                        <a href="{{ route('notes.show', $t->user) }}" class="thread-item" @if($other && $other->is($t->user)) aria-current="page" @endif>
                            @include('partials.avatar', ['user' => $t->user])
                            <span class="thread-text">
                                <span class="thread-name">{{ $t->user->name }}</span>
                                <span class="thread-last">{{ \Illuminate\Support\Str::limit($t->last->body, 28) }}</span>
                            </span>
                            @if ($t->unread > 0)
                                <span class="badge">{{ $t->unread }}<span class="visually-hidden"> 則未讀</span></span>
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
                            <p class="note-who visually-hidden">{{ $mine ? '我' : $other->name }}：</p>
                            <div class="note-body">{!! nl2br(e($n->body)) !!}</div>
                            <p class="note-time"><time datetime="{{ $n->created_at->toIso8601String() }}">{{ $n->created_at->format('n/j H:i') }}</time>@if($mine && $n->read_at)・已讀@endif</p>
                        </li>
                    @endforeach
                </ol>
            @endif

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
                <button type="submit" class="button">送出</button>
            </form>
        @endif
    </section>
</div>
@endsection
