@extends('layouts.app')

@section('title', '聯絡我們')

@section('content')
{{-- 設計稿 PageContactUs（姓名、帳號、Email、聯絡事項、聯絡內容）＋舊站 info/contact.php 的提醒文字 --}}
<h1>聯絡我們</h1>
<p>請您填寫以下聯絡表格，我們的客服人員會儘快與您聯絡。</p>
<ul class="prose">
    <li>請您確實詳述您的問題，若您只寫「無法登入」，我們也無法協助處理。</li>
    <li>如果您忘記密碼，請使用<a href="{{ route('password.request') }}">忘記密碼</a>功能，客服人員無法告訴您原本的密碼。</li>
</ul>

<form method="POST" action="{{ route('contact.store') }}" class="form form-wide" novalidate>
    @csrf
    @include('partials.errors', ['title' => '還沒有送出'])
    @php($me = auth()->user())
    @foreach ([['name', '姓名', 'text', 30, $me?->name, 'name'], ['email', '回覆用的 Email', 'email', 100, $me?->email, 'email']] as [$key, $label, $type, $max, $default, $ac])
        <div class="field">
            <label for="{{ $key }}">{{ $label }}</label>
            <input id="{{ $key }}" name="{{ $key }}" type="{{ $type }}" maxlength="{{ $max }}" autocomplete="{{ $ac }}" value="{{ old($key, $default) }}" required
                   @error($key) aria-invalid="true" aria-describedby="{{ $key }}-error" @enderror>
            @error($key)<p id="{{ $key }}-error" class="field-error">{{ $message }}</p>@enderror
        </div>
    @endforeach
    @if ($me)
        <p class="hint">會一起附上您的會員編號（{{ $me->id }}），方便我們查詢。</p>
    @endif
    @include('partials.select-field', ['name' => 'subject', 'label' => '聯絡事項', 'options' => \App\Models\ContactMessage::SUBJECTS, 'value' => old('subject', request('subject')), 'required' => true])
    <div class="field">
        <label for="body">聯絡內容</label>
        <p id="body-hint" class="hint">1000 字以內。</p>
        <textarea id="body" name="body" rows="8" maxlength="1000" required
                  aria-describedby="body-hint @error('body') body-error @enderror" @error('body') aria-invalid="true" @enderror>{{ old('body') }}</textarea>
        @error('body')<p id="body-error" class="field-error">{{ $message }}</p>@enderror
    </div>
    {{-- 擋機器人：這一格人看不到，有填就當成機器人（取代舊站的「系統安全碼」圖片，不用看圖打字） --}}
    <div class="hp-field" aria-hidden="true">
        <label for="website">請不要填這一格</label>
        <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
    </div>
    <button type="submit" class="button">送出</button>
</form>
@endsection
