@extends('layouts.app')

@section('title', '送禮物')

@section('main_class', 'wrap airmail')

@section('content')
<h1>送禮物給 {{ $member->name }}</h1>
<p class="prose">送一份禮物給陌生網友是對他(她)表達好感的第一步。若對方回送給您一個禮物，雙方就成為「普通好友」囉。</p>
<ul class="prose">
    <li>送禮物需要使用 {{ $cost }} 點愛諾積點（您的積點餘額：<strong>{{ $balance }}</strong> 點）</li>
    <li>每天只可送一份禮物給同一個會員</li>
</ul>

@if ($sentToday)
    <p class="notice" role="status">今天已經送過禮物給 {{ $member->name }} 了，明天再送吧。</p>
@endif

<form method="POST" action="{{ route('gifts.store', $member) }}" class="form form-wide" novalidate>
    @csrf
    @include('partials.errors', ['title' => '禮物還沒送出'])

    <fieldset id="item" tabindex="-1" class="field">
        <legend>請選擇禮物</legend>
        <ul class="gift-grid">
            @foreach ($items as $id => $name)
                <li>
                    <input id="item-{{ $id }}" name="item" type="radio" value="{{ $id }}" class="gift-radio"
                           @checked((int) old('item') === $id)
                           @error('item') aria-invalid="true" aria-describedby="item-error" @enderror>
                    <label for="item-{{ $id }}" class="gift-card">
                        <img src="{{ \App\Models\Gift::imageUrl($id) }}" alt="" width="96" height="96" loading="lazy">
                        <span>{{ $name }}</span>
                    </label>
                </li>
            @endforeach
        </ul>
        @error('item')<p id="item-error" class="field-error">{{ $message }}</p>@enderror
    </fieldset>

    <div class="field">
        <label for="message">簡訊（可以不寫）</label>
        <p id="message-hint" class="hint">您可以寫一句 20 個中文字以內的簡訊與禮物一起傳送。</p>
        <input id="message" name="message" type="text" maxlength="20" value="{{ old('message') }}"
               @if (session('gift_edit')) autofocus @endif {{-- 從確認頁「回去修改」回來：游標直接在簡訊欄 --}}
               aria-describedby="message-hint @error('message') message-error @enderror"
               @error('message') aria-invalid="true" @enderror>
        @error('message')<p id="message-error" class="field-error">{{ $message }}</p>@enderror
    </div>

    <button type="submit" class="button">送出</button>
</form>
<p><a href="{{ route('members.show', $member) }}">← 回 {{ $member->name }} 的檔案</a></p>
@endsection
