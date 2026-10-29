@extends('layouts.app')

@section('title', '投稿愛的見證')

@section('content')
{{-- 設計稿 PageNewLoveStory：標題、男方會員編號、女方會員編號、見證內容（1000 字）、照片上傳（JPG、PNG 5M） --}}
<h1>投稿愛的見證</h1>
<p>請用男方或女方其中一位的帳號投稿。管理員看過、跟兩位確認以後才會公開。</p>
@php($mine = $me->profile?->gender === '男' ? 'male_id' : ($me->profile?->gender === '女' ? 'female_id' : null))
<form method="POST" action="{{ route('love-stories.store') }}" enctype="multipart/form-data" class="form form-wide" novalidate>
    @csrf
    @include('partials.errors', ['title' => '還沒有投稿成功'])
    @foreach ([['title', '標題', 50, null, 'text'], ['male_id', '男方會員編號', 10, $mine === 'male_id' ? $me->id : null, 'numeric'], ['female_id', '女方會員編號', 10, $mine === 'female_id' ? $me->id : null, 'numeric']] as [$key, $label, $max, $default, $mode])
        <div class="field">
            <label for="{{ $key }}">{{ $label }}</label>
            @if ($mode === 'numeric')<p id="{{ $key }}-hint" class="hint">會員編號在「會員中心 → 帳號管理」最上面。</p>@endif
            <input id="{{ $key }}" name="{{ $key }}" type="text" @if ($mode === 'numeric') inputmode="numeric" @endif maxlength="{{ $max }}" value="{{ old($key, $default) }}" required
                   aria-describedby="@if ($mode === 'numeric'){{ $key }}-hint @endif @error($key){{ $key }}-error @enderror" @error($key) aria-invalid="true" @enderror>
            @error($key)<p id="{{ $key }}-error" class="field-error">{{ $message }}</p>@enderror
        </div>
    @endforeach
    <div class="field">
        <label for="body">見證內容</label>
        <p id="body-hint" class="hint">1000 字以內。</p>
        <textarea id="body" name="body" rows="12" maxlength="1000" required
                  aria-describedby="body-hint @error('body') body-error @enderror" @error('body') aria-invalid="true" @enderror>{{ old('body') }}</textarea>
        @error('body')<p id="body-error" class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field">
        <label for="photo">照片（可以不放）</label>
        <p id="photo-hint" class="hint">檔案格式：JPG、PNG，單一檔案不超過 5MB。</p>
        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png"
               aria-describedby="photo-hint @error('photo') photo-error @enderror" @error('photo') aria-invalid="true" @enderror>
        @error('photo')<p id="photo-error" class="field-error">{{ $message }}</p>@enderror
    </div>
    <button type="submit" class="button">送出</button>
</form>
@endsection
