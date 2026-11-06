@extends('layouts.app')

@section('title', $other ? '傳紙條給 ' . $other->name : '傳紙條')

@section('main_class', 'wrap wrap-wide')

@section('content')
<h1>傳紙條</h1>
{{-- 設計稿 402:13701「情書」樣式（熊叔叔 11/6 決定）：左邊一封一封的清單（可勾選刪除）、右邊跟對方往來的信（對方藍灰、自己蜜桃）、珊瑚色回信鈕 --}}

<div class="notes-layout @if($other) has-thread @endif">
    <section class="notes-threads" aria-labelledby="threads-title">
        @php($unreadTotal = $letters->where('unread', true)->count())
        <form method="POST" action="{{ route('notes.destroy') }}" id="letters-form">
            @csrf
            <h2 id="threads-title" class="letters-head"><img src="{{ asset('images/brand/nav-notes.svg') }}" alt="" width="28" height="28">
                @if ($unreadTotal > 0)<span>您有 <span class="threads-count">{{ $unreadTotal }}</span> 封新情書未讀</span>@else<span>情書清單</span>@endif
                @if ($letters->isNotEmpty())
                    <button type="submit" class="letters-del" aria-label="刪除勾選的信（只刪除您這邊）"><svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 3h6l1 2h4v2H4V5h4l1-2zm-3 6h12l-1 12H7L6 9zm4 2v8h2v-8h-2zm4 0v8h2v-8h-2z" fill="currentColor"/></svg></button>
                @endif
            </h2>
            @if ($letters->isEmpty())
                <p class="hint" style="padding: 12px 14px">還沒有信。到「認識朋友」找一位，在他的檔案頁按「傳紙條」。</p>
            @else
                <ul class="letter-list">
                    @foreach ($letters as $l)
                        @php($n = $l->note)
                        <li class="letter-item @if($other && $other->is($l->other)) is-current @endif">
                            <input type="checkbox" name="ids[]" value="{{ $n->id }}" id="del-{{ $n->id }}" aria-label="勾選：{{ $n->displayTitle() }}（{{ $l->mine ? '寄給' : '來自' }} {{ $l->other->name }}）">
                            <a href="{{ route('notes.show', $l->other) }}#note-{{ $n->id }}" class="letter-link">
                                <span class="letter-top">
                                    @include('partials.avatar', ['user' => $l->other, 'size' => 'sm'])
                                    <span class="letter-who">{{ $l->mine ? '寄給 ' : '' }}{{ $l->other->name }}@if($l->blocked) <span class="tag">已封鎖</span>@endif</span>
                                    <time class="letter-date" datetime="{{ $n->created_at->toIso8601String() }}">{{ $n->created_at->shown()->format('Y/m/d') }}</time>
                                    @if ($l->unread)<span class="letter-dot" aria-hidden="true"></span><span class="visually-hidden">（未讀）</span>@endif
                                </span>
                                <span class="letter-title">{{ $n->displayTitle() }}</span>
                                <span class="letter-excerpt">{{ \Illuminate\Support\Str::limit($n->body, 60) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </form>
    </section>

    <section class="notes-thread" aria-labelledby="thread-title">
        @if (! $other)
            <h2 id="thread-title" class="visually-hidden">信件內容</h2>
            <p class="hint">請在左邊選一封信。</p>
        @else
            <h2 id="thread-title">和 <a href="{{ route('members.show', $other) }}">{{ $other->name }}</a> 的往來信件</h2>
            <p class="notes-back"><a href="{{ route('notes.index') }}">← 回信件清單</a></p>

            @if ($notes->isEmpty())
                <p class="hint">還沒有信，寫第一封吧。</p>
            @else
                <ol class="note-list">
                    @foreach ($notes as $n)
                        @php($mine = $n->sender_id === auth()->id())
                        <li class="note {{ $mine ? 'note-mine' : 'note-theirs' }}" id="note-{{ $n->id }}">
                            <div class="note-head">
                                @include('partials.avatar', ['user' => $mine ? auth()->user() : $other, 'size' => 'sm'])
                                <p class="note-who">{{ $mine ? '我' : $other->name }}<span class="visually-hidden">：</span></p>
                                <p class="note-title">{{ $n->displayTitle() }}</p>
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
                    <label for="title">標題</label>
                    <input id="title" name="title" type="text" maxlength="20" value="{{ old('title') }}" data-count
                           aria-describedby="title-count @error('title') title-error @enderror" @error('title') aria-invalid="true" @enderror>
                    <p id="title-count" class="char-count" aria-live="off"><span>{{ mb_strlen(old('title', '')) }}</span>/20</p>
                    @error('title')<p id="title-error" class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="body">寫給 {{ $other->name }}</label>
                    <p id="body-hint" class="hint">最多 1000 字。請不要在紙條裡留電話、住址等聯絡方式。</p>
                    <textarea id="body" name="body" rows="4" maxlength="1000" required data-count
                              aria-describedby="{{ trim('body-hint body-count' . ($errors->has('body') ? ' body-error' : '')) }}"
                              @error('body') aria-invalid="true" @enderror>{{ old('body') }}</textarea>
                    <p id="body-count" class="char-count" aria-live="off"><span>{{ mb_strlen(old('body', '')) }}</span>/1000</p>
                    @error('body')<p id="body-error" class="field-error">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="button note-send">回信</button>
            </form>
            @endif
        @endif
    </section>
</div>
<script src="{{ asset('js/char-count.js') }}?v={{ $appVersion }}" defer></script>
<script src="{{ asset('js/letters-delete.js') }}?v={{ $appVersion }}" defer></script>
@endsection
