@extends('layouts.app')

@section('title', '戀愛履歷')

@section('content')
@include('center.tabs', ['current' => 'profile', 'sub' => 'love'])
<h2>戀愛履歷</h2>
<p class="hint">選擇題都要選（不想說可以選「保留」）；最後兩題可以不寫。填完可以得到 10 點愛諾積點。</p>

<form method="POST" action="{{ route('profile.love.update') }}" class="form form-wide" novalidate>
    @csrf
    @method('PUT')
    @include('partials.errors', ['title' => '戀愛履歷還沒存好'])

    @foreach (\App\Support\LoveHistory::QUESTIONS as $key => [$label, $options])
        @include('partials.select-field', ['name' => $key, 'label' => $label . '（必填）', 'options' => $options, 'value' => $answers[$key] ?? '', 'required' => true])
    @endforeach

    @foreach (\App\Support\LoveHistory::TEXTS as $key => [$label, $max])
        <div class="field">
            <label for="{{ $key }}">{{ $label }}（可以不寫）</label>
            <p id="{{ $key }}-hint" class="hint">{{ $max }} 字以內。</p>
            <textarea id="{{ $key }}" name="{{ $key }}" rows="4" maxlength="{{ $max }}"
                      aria-describedby="{{ $key }}-hint @error($key) {{ $key }}-error @enderror"
                      @error($key) aria-invalid="true" @enderror>{{ old($key, $answers[$key] ?? '') }}</textarea>
            @error($key)<p id="{{ $key }}-error" class="field-error">{{ $message }}</p>@enderror
        </div>
    @endforeach

    <button type="submit" class="button">存檔</button>
</form>
@endsection
