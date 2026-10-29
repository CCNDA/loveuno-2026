@extends('layouts.app')

@section('title', '編輯個人檔案')

@section('content')
@include('center.tabs', ['current' => 'profile', 'sub' => 'info'])
<h2>個人資料與自我介紹</h2>
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

    <fieldset class="field" aria-describedby="birthday-hint">
        <legend>生日（月、日，可以不填）</legend>
        <p id="birthday-hint" class="hint">只用來提醒您的好友「生日快到了」，不會顯示日期。出生年註冊時就填了，只顯示年齡層。</p>
        <div class="birthday-selects">
            <div>
                <label for="birth_month">月</label>
                <select id="birth_month" name="birth_month" @error('birth_month') aria-invalid="true" aria-describedby="birth_month-error" @enderror>
                    <option value="">—</option>
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected((int) old('birth_month', $profile->birth_month) === $m)>{{ $m }} 月</option>
                    @endfor
                </select>
            </div>
            <div>
                <label for="birth_day">日</label>
                <select id="birth_day" name="birth_day" @error('birth_day') aria-invalid="true" aria-describedby="birth_day-error" @enderror>
                    <option value="">—</option>
                    @for ($d = 1; $d <= 31; $d++)
                        <option value="{{ $d }}" @selected((int) old('birth_day', $profile->birth_day) === $d)>{{ $d }} 日</option>
                    @endfor
                </select>
            </div>
        </div>
        @error('birth_month')<p id="birth_month-error" class="field-error">{{ $message }}</p>@enderror
        @error('birth_day')<p id="birth_day-error" class="field-error">{{ $message }}</p>@enderror
    </fieldset>

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
