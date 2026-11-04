@extends('layouts.app')

@section('title', '回報問題／建議')

@section('content')
<h1>回報問題／建議</h1>
<p class="meta">新版愛諾園還在試用，遇到不順、看不懂、或是有想法，都歡迎告訴我們。我們會自動記下您是在哪一頁按的、用什麼手機或電腦，不用另外寫。這一頁是另外開的，寫完可以直接關掉，原本那一頁不會不見。</p>

<form method="POST" action="{{ route('feedback.store') }}" class="form form-wide" enctype="multipart/form-data" novalidate>
    @csrf
    @include('partials.errors', ['title' => '還沒有送出'])
    <input type="hidden" name="page" value="{{ old('page', $from) }}">
    <input type="hidden" name="screen" id="feedback-screen" value="{{ old('screen') }}">

    <fieldset id="kind" tabindex="-1" class="field" @error('kind') aria-describedby="kind-error" @enderror>
        <legend>是什麼？</legend>
        @foreach (\App\Models\Feedback::KINDS as $value => $label)
            <div class="field-check">
                <input id="kind-{{ $value }}" name="kind" type="radio" value="{{ $value }}" @checked(old('kind') === $value)>
                <label for="kind-{{ $value }}">{{ $label }}</label>
            </div>
        @endforeach
        @error('kind')<p id="kind-error" class="field-error">{{ $message }}</p>@enderror
    </fieldset>

    <div class="field">
        <label for="body">請告訴我們</label>
        <p id="body-hint" class="hint">例如：在哪一頁、按了什麼、看到什麼、希望怎麼改。1000 字以內。</p>
        <textarea id="body" name="body" rows="6" maxlength="1000" required
                  aria-describedby="body-hint @error('body') body-error @enderror" @error('body') aria-invalid="true" @enderror>{{ old('body') }}</textarea>
        @error('body')<p id="body-error" class="field-error">{{ $message }}</p>@enderror
    </div>

    @guest
        <div class="field">
            <label for="email">Email（可以不填）</label>
            <p id="email-hint" class="hint">您還沒有登入。想要我們回覆的話，請留 Email。</p>
            <input id="email" name="email" type="email" maxlength="100" autocomplete="email" value="{{ old('email') }}"
                   aria-describedby="email-hint @error('email') email-error @enderror" @error('email') aria-invalid="true" @enderror>
            @error('email')<p id="email-error" class="field-error">{{ $message }}</p>@enderror
        </div>
    @endguest

    <div class="field">
        <label for="screenshot">截圖（可以不放）</label>
        <p id="screenshot-hint" class="hint">檔案格式：JPG、PNG，不超過 {{ $maxMb }}MB。只有管理員看得到。</p>
        <input id="screenshot" name="screenshot" type="file" accept="image/jpeg,image/png"
               aria-describedby="screenshot-hint @error('screenshot') screenshot-error @enderror"
               @error('screenshot') aria-invalid="true" @enderror>
        @error('screenshot')<p id="screenshot-error" class="field-error">{{ $message }}</p>@enderror
    </div>

    {{-- 擋機器人：這一格人看不到，有填就當成機器人（跟聯絡我們一樣） --}}
    <div class="hp-field" aria-hidden="true">
        <label for="website">請不要填這一格</label>
        <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
    </div>
    <button type="submit" class="button">送出</button>
</form>
@if ($from !== '')<p><a href="{{ $from }}">← 回剛剛那一頁</a></p>@endif
<script src="{{ asset('js/feedback.js') }}" defer></script>
@endsection
