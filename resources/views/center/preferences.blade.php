@extends('layouts.app')

@section('title', '交友條件')

@section('content')
@include('center.tabs', ['current' => 'preferences'])

<h2>交友條件</h2>
<p class="hint">寫下您想認識什麼樣的朋友。沒填的就是不限。</p>

<form method="POST" action="{{ route('center.preferences.update') }}" class="form" novalidate>
    @csrf
    @method('PUT')
    @include('partials.errors', ['title' => '交友條件還沒存好'])

    <div class="field">
        <label for="pref_gender">想認識</label>
        <select id="pref_gender" name="pref_gender">
            <option value="">不限</option>
            @foreach (['男' => '弟兄', '女' => '姊妹'] as $v => $l)
                <option value="{{ $v }}" @selected(old('pref_gender', $profile->pref_gender) === $v)>{{ $l }}</option>
            @endforeach
        </select>
    </div>

    <fieldset id="pref_age_min" tabindex="-1" class="field">
        <legend>年齡</legend>
        <div class="age-range">
            <label for="pref_age_min_input">從</label>
            <input id="pref_age_min_input" name="pref_age_min" type="number" inputmode="numeric" min="18" max="99"
                   value="{{ old('pref_age_min', $profile->pref_age_min) }}"
                   @error('pref_age_min') aria-invalid="true" aria-describedby="pref_age_min-error" @enderror>
            <label for="pref_age_max">到</label>
            <input id="pref_age_max" name="pref_age_max" type="number" inputmode="numeric" min="18" max="99"
                   value="{{ old('pref_age_max', $profile->pref_age_max) }}"
                   @error('pref_age_max') aria-invalid="true" aria-describedby="pref_age_max-error" @enderror>
            <span>歲</span>
        </div>
        @error('pref_age_min')<p id="pref_age_min-error" class="field-error">{{ $message }}</p>@enderror
        @error('pref_age_max')<p id="pref_age_max-error" class="field-error">{{ $message }}</p>@enderror
    </fieldset>

    <div class="field">
        <label for="pref_place">居住地</label>
        <select id="pref_place" name="pref_place">
            <option value="">不限</option>
            @foreach ($places as $pl)
                <option value="{{ $pl }}" @selected(old('pref_place', $profile->pref_place) === $pl)>{{ $pl }}</option>
            @endforeach
        </select>
    </div>

    <button type="submit" class="button">存檔</button>
</form>
@endsection
