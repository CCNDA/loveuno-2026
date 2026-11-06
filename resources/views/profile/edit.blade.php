@extends('layouts.app')

@section('title', '個人資料')

@section('content')
@include('center.tabs', ['current' => 'profile', 'sub' => 'info'])
<h2>個人資料</h2>
<p class="hint">性別和出生年是註冊時填的，這裡不能改；要改請找管理員。其他會員只會看到年齡層，看不到出生年。標「必填」的要選。</p>

<form method="POST" action="{{ route('profile.update') }}" class="form form-wide form-grid" novalidate
      data-autosave="{{ route('profile.patch') }}" data-autosave-groups="birth_month birth_day">
    @csrf
    @method('PUT')
    @include('partials.errors', ['title' => '個人資料還沒存好'])

    @php($fields = [
        ['name', '暱稱', 20, $user->name, '會顯示給其他會員看，不用寫真實姓名。'],
        ['real_name', '真實姓名（可以不填）', 30, $profile->real_name, '姓名不會顯示於交友檔案，只有管理員看得到。'],
        ['home_phone', '住家電話（可以不填）', 20, $profile->home_phone, '住家電話不會顯示於交友檔案，只有管理員看得到。範例：02-1234-5678'],
        ['live_place', '居住地', 20, $profile->live_place, '例如：台北市'],
        ['job', '職業', 20, $profile->job, '例如：老師、工程師、學生'],
    ])
    {{-- 迴圈變數不能叫 $hint：下面 select-field 會撿到最後一個「例如：老師、工程師、學生」（11/5 抓到） --}}
    @foreach ($fields as [$key, $label, $max, $value, $fieldHint])
        <div class="field">
            <label for="{{ $key }}">{{ $label }}</label>
            @if ($fieldHint)<p id="{{ $key }}-hint" class="hint">{{ $fieldHint }}</p>@endif
            @php($desc = trim(($fieldHint ? $key . '-hint' : '') . ($errors->has($key) ? ' ' . $key . '-error' : '')))
            <input id="{{ $key }}" name="{{ $key }}" type="{{ $key === 'home_phone' ? 'tel' : 'text' }}" maxlength="{{ $max }}" value="{{ old($key, $value) }}"
                   @if ($key === 'name') required autocomplete="nickname" @elseif ($key === 'real_name') autocomplete="name" @elseif ($key === 'home_phone') autocomplete="tel" inputmode="tel" @endif
                   @if ($desc !== '') aria-describedby="{{ $desc }}" @endif
                   @error($key) aria-invalid="true" @enderror>
            @error($key)<p id="{{ $key }}-error" class="field-error">{{ $message }}</p>@enderror
        </div>
    @endforeach

    <fieldset class="field">
        <legend>生日（月、日，可以不填）</legend>
        <p id="birthday-hint" class="hint">只用來提醒您的好友「生日快到了」，不會顯示日期。出生年註冊時就填了，只顯示年齡層。</p>
        <div class="birthday-selects">
            <div>
                <label for="birth_month">月</label>
                <select id="birth_month" name="birth_month" aria-describedby="birthday-hint @error('birth_month') birth_month-error @enderror" @error('birth_month') aria-invalid="true" @enderror>
                    <option value="">—</option>
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected((int) old('birth_month', $profile->birth_month) === $m)>{{ $m }} 月</option>
                    @endfor
                </select>
            </div>
            <div>
                <label for="birth_day">日</label>
                <select id="birth_day" name="birth_day" aria-describedby="birthday-hint @error('birth_day') birth_day-error @enderror" @error('birth_day') aria-invalid="true" @enderror>
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

    @php($O = \App\Support\ProfileOptions::class)
    @include('partials.radio-field', ['name' => 'faith', 'label' => '信仰（必填）', 'required' => true, 'options' => $O::FAITH, 'value' => $profile->faith])
    @include('partials.select-field', ['name' => 'marital', 'label' => '婚姻狀況（必填）', 'required' => true, 'options' => $O::maritalFor((string) $profile->marital), 'value' => $profile->marital])
    @include('partials.select-field', ['name' => 'children', 'label' => '子女人數（必填）', 'required' => true, 'options' => $O::CHILDREN, 'value' => $profile->children])
    @include('partials.select-field', ['name' => 'education', 'label' => '教育程度（必填）', 'required' => true, 'options' => $O::EDUCATION, 'value' => $profile->education])
    @include('partials.select-field', ['name' => 'blood_type', 'label' => '血型（必填）', 'required' => true, 'options' => $O::BLOOD, 'value' => $profile->blood_type])

    @foreach ([['height', '身高（cm）', $profile->height, 120, 230], ['weight', '體重（kg）', $profile->weight, 30, 200]] as [$key, $label, $value, $min, $max])
        <div class="field">
            <label for="{{ $key }}">{{ $label }}</label>
            <input id="{{ $key }}" name="{{ $key }}" type="number" inputmode="numeric" min="{{ $min }}" max="{{ $max }}" value="{{ old($key, $value) }}" class="input-short"
                   @error($key) aria-invalid="true" aria-describedby="{{ $key }}-error" @enderror>
            @error($key)<p id="{{ $key }}-error" class="field-error">{{ $message }}</p>@enderror
        </div>
    @endforeach

    @include('partials.select-field', ['name' => 'smoking', 'label' => '是否抽菸（必填）', 'required' => true, 'options' => $O::SMOKING, 'value' => $profile->smoking])
    @include('partials.select-field', ['name' => 'drinking', 'label' => '是否喝酒（必填）', 'required' => true, 'options' => $O::DRINKING, 'value' => $profile->drinking])
    @include('partials.radio-field', ['name' => 'family_planning', 'label' => '計劃生育', 'options' => $O::FAMILY_PLANNING, 'value' => $profile->family_planning, 'hint' => '婚後想不想要有孩子。'])

    <button type="submit" class="button">存檔</button>
</form>

<p><a href="{{ route('me') }}">← 回我的檔案</a></p>
@include('profile.partials.love-section')
@endsection
