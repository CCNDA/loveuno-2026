@extends('layouts.app')

@section('title', '編輯個人檔案')

@section('content')
<h1>編輯個人檔案</h1>
<p class="hint">性別和出生年是註冊時填的，這裡不能改；要改請找管理員。其他會員只會看到年齡層，看不到出生年。</p>

<form method="POST" action="{{ route('profile.update') }}" class="form form-wide" novalidate>
    @csrf
    @method('PUT')
    @include('partials.errors', ['title' => '個人檔案還沒存好'])

    @php($fields = [
        ['name', '暱稱', 'text', 20, $user->name, '會顯示給其他會員看，不用寫真實姓名。'],
        ['live_place', '居住地', 'text', 20, $profile->live_place, '例如：台北市'],
        ['job', '職業', 'text', 20, $profile->job, null],
        ['church', '教會', 'text', 100, $profile->church, '要不要給別人看，可以在下面選。'],
    ])
    @foreach ($fields as [$key, $label, $type, $max, $value, $hint])
        <div class="field">
            <label for="{{ $key }}">{{ $label }}</label>
            @if ($hint)<p id="{{ $key }}-hint" class="hint">{{ $hint }}</p>@endif
            @php($desc = trim(($hint ? $key . '-hint' : '') . ($errors->has($key) ? ' ' . $key . '-error' : '')))
            <input id="{{ $key }}" name="{{ $key }}" type="{{ $type }}" maxlength="{{ $max }}" value="{{ old($key, $value) }}"
                   @if ($key === 'name') required autocomplete="nickname" @endif
                   @if ($desc !== '') aria-describedby="{{ $desc }}" @endif
                   @error($key) aria-invalid="true" @enderror>
            @error($key)<p id="{{ $key }}-error" class="field-error">{{ $message }}</p>@enderror
        </div>
    @endforeach

    <div class="field field-check">
        {{-- 送出失敗回來時，沒勾的 checkbox 不會出現在 old()，要用這次送的值，不是資料庫的舊值 --}}
        <input id="church_visible" name="church_visible" type="checkbox" value="1" @checked($errors->any() ? old('church_visible') : $profile->church_visible)>
        <label for="church_visible">讓其他會員看到我的教會</label>
    </div>

    @foreach ([['intro', '自我介紹', 2000, $profile->intro], ['hope', '期待認識的人', 1000, $profile->hope]] as [$key, $label, $max, $value])
        <div class="field">
            <label for="{{ $key }}">{{ $label }}</label>
            <p id="{{ $key }}-hint" class="hint">最多 {{ $max }} 字。請不要寫電話、住址等聯絡方式。</p>
            <textarea id="{{ $key }}" name="{{ $key }}" rows="6" maxlength="{{ $max }}"
                      aria-describedby="{{ trim($key . '-hint' . ($errors->has($key) ? ' ' . $key . '-error' : '')) }}"
                      @error($key) aria-invalid="true" @enderror>{{ old($key, $value) }}</textarea>
            @error($key)<p id="{{ $key }}-error" class="field-error">{{ $message }}</p>@enderror
        </div>
    @endforeach

    <button type="submit" class="button">存檔</button>
</form>

<p><a href="{{ route('me') }}">← 回我的檔案</a></p>
@endsection
