@extends('layouts.app')

@section('title', '認識朋友')

@section('content')
<h1>認識朋友</h1>

@include('partials.errors', ['title' => '篩選條件有問題，先顯示全部會員'])

@php($val = fn (string $k) => old($k, $filters[$k] ?? ''))
{{-- 「更多條件」有用到、或裡面的欄位出錯，就打開 --}}
@php($moreKeys = ['age_min', 'age_max', 'marital', 'faith', 'name', 'photo', 'verified'])
@php($moreOpen = collect($moreKeys)->contains(fn ($k) => ! empty(old($k, $filters[$k] ?? null)) || $errors->has($k) || $errors->has($k . '.*')))
<form method="GET" action="{{ route('members.index') }}" class="filters" role="search" aria-label="篩選會員">
    <div class="field">
        <label for="gender">性別</label>
        <select id="gender" name="gender" @error('gender') aria-invalid="true" aria-describedby="gender-error" @enderror>
            <option value="">不限</option>
            @foreach (['男' => '弟兄', '女' => '姊妹'] as $value => $label)
                <option value="{{ $value }}" @selected($val('gender') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('gender')<p id="gender-error" class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field">
        <label for="place">居住地</label>
        <select id="place" name="place" @error('place') aria-invalid="true" aria-describedby="place-error" @enderror>
            <option value="">不限</option>
            @foreach ($places as $place)
                <option value="{{ $place }}" @selected($val('place') === $place)>{{ $place }}</option>
            @endforeach
        </select>
        @error('place')<p id="place-error" class="field-error">{{ $message }}</p>@enderror
    </div>
    <details class="more-filters"@if($moreOpen) open @endif>
        <summary>更多條件</summary>
        <fieldset class="field">
            <legend>年齡</legend>
            <p id="age-hint" class="hint">5 歲一格，跟會員卡片上的年齡層一樣。</p>
            <div class="age-range">
                <label for="age_min">從</label>
                <select id="age_min" name="age_min" aria-describedby="age-hint @error('age_min') age_min-error @enderror" @error('age_min') aria-invalid="true" @enderror>
                    <option value="">不限</option>
                    @foreach ($ageFrom as $a)<option value="{{ $a }}" @selected((string) $val('age_min') === (string) $a)>{{ $a }}</option>@endforeach
                </select>
                <label for="age_max">到</label>
                <select id="age_max" name="age_max" aria-describedby="age-hint @error('age_max') age_max-error @enderror" @error('age_max') aria-invalid="true" @enderror>
                    <option value="">不限</option>
                    @foreach ($ageTo as $a)<option value="{{ $a }}" @selected((string) $val('age_max') === (string) $a)>{{ $a }}</option>@endforeach
                </select>
                <span>歲</span>
            </div>
            @error('age_min')<p id="age_min-error" class="field-error">{{ $message }}</p>@enderror
            @error('age_max')<p id="age_max-error" class="field-error">{{ $message }}</p>@enderror
        </fieldset>
        @foreach (['marital' => ['婚姻狀況', \App\Support\ProfileOptions::MARITAL], 'faith' => ['信仰', \App\Support\ProfileOptions::FAITH]] as $key => [$label, $options])
            <div class="field">
                <label for="{{ $key }}">{{ $label }}</label>
                <select id="{{ $key }}" name="{{ $key }}" @error($key) aria-invalid="true" aria-describedby="{{ $key }}-error" @enderror>
                    <option value="">不限</option>
                    @foreach ($options as $o)
                        <option value="{{ $o }}" @selected($val($key) === $o)>{{ $o }}</option>
                    @endforeach
                </select>
                @error($key)<p id="{{ $key }}-error" class="field-error">{{ $message }}</p>@enderror
            </div>
        @endforeach
        <div class="field">
            <label for="name">暱稱裡有</label>
            <input id="name" name="name" type="search" maxlength="20" value="{{ $val('name') }}" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
            @error('name')<p id="name-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="field-check">
            <input id="photo" name="photo" type="checkbox" value="1" @checked(! empty($val('photo')))>
            <label for="photo">只看有大頭照的</label>
        </div>
        @php($picked = (array) old('verified', $filters['verified'] ?? []))
        <fieldset id="verified" tabindex="-1" class="field" @if ($errors->has('verified.*')) aria-describedby="verified-error" @endif>
            <legend>通過這些認證的</legend>
            @foreach ($verifyKinds as $kind => $label)
                <div class="field-check">
                    <input id="verified-{{ $kind }}" name="verified[]" type="checkbox" value="{{ $kind }}" @checked(in_array($kind, $picked, true))>
                    <label for="verified-{{ $kind }}">{{ $label }}</label>
                </div>
            @endforeach
            @if ($errors->has('verified.*'))<p id="verified-error" class="field-error">{{ $errors->first('verified.*') }}</p>@endif
        </fieldset>
    </details>
    <button type="submit" class="button">搜尋</button>
    @if (collect($filters)->filter()->isNotEmpty())<a href="{{ route('members.index') }}">清除條件</a>@endif
</form>

<p class="meta" role="status">共 {{ $members->total() }} 位</p>

@if ($members->isEmpty())
    <p>沒有符合條件的會員，換個條件試試看。</p>
@else
    <ul class="member-grid">
        @foreach ($members as $m)
            <li class="member-card">
                @include('partials.avatar', ['user' => $m])
                <div>
                    <p class="member-name"><a href="{{ route('members.show', $m) }}">{{ $m->name }}</a></p>
                    <p class="meta">
                        {{ collect([$m->profile->genderLabel(), $m->profile->ageGroup(), $m->profile->live_place])->filter()->implode('・') }}
                    </p>
                    @php($level = auth()->user()->friendLevelWith($m))
                    @if ($level > 0)<p class="meta"><span class="tag">{{ \App\Models\Friendship::LEVELS[$level] }}</span></p>@endif
                    @if ($m->profile->intro)
                        <p class="excerpt">{{ \Illuminate\Support\Str::limit($m->profile->intro, 40) }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>

    {{ $members->links('partials.pagination') }}
@endif
@endsection
