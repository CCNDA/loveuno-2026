@extends('layouts.app')

@section('title', '認識朋友')

@section('content')
<h1>認識朋友</h1>

@if ($errors->any())
    <div class="error-summary" role="alert" aria-labelledby="filter-error-title">
        <h2 id="filter-error-title">篩選條件有問題，先顯示全部會員</h2>
        <ul>
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="GET" action="{{ route('members.index') }}" class="filters" role="search" aria-label="篩選會員">
    <div class="field">
        <label for="gender">性別</label>
        <select id="gender" name="gender">
            <option value="">不限</option>
            @foreach (['男' => '弟兄', '女' => '姊妹'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['gender'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="place">居住地</label>
        <select id="place" name="place">
            <option value="">不限</option>
            @foreach ($places as $place)
                <option value="{{ $place }}" @selected(($filters['place'] ?? '') === $place)>{{ $place }}</option>
            @endforeach
        </select>
    </div>
    <details class="more-filters"@if($moreOpen) open @endif>
        <summary>更多條件</summary>
        <fieldset class="field">
            <legend>年齡</legend>
            <label for="age_min">從</label>
            <input id="age_min" name="age_min" type="number" min="18" max="99" inputmode="numeric" value="{{ $filters['age_min'] ?? '' }}">
            <label for="age_max">到</label>
            <input id="age_max" name="age_max" type="number" min="18" max="99" inputmode="numeric" value="{{ $filters['age_max'] ?? '' }}"> 歲
        </fieldset>
        @foreach (['marital' => ['婚姻狀況', \App\Support\ProfileOptions::MARITAL], 'faith' => ['信仰', \App\Support\ProfileOptions::FAITH]] as $key => [$label, $options])
            <div class="field">
                <label for="{{ $key }}">{{ $label }}</label>
                <select id="{{ $key }}" name="{{ $key }}">
                    <option value="">不限</option>
                    @foreach ($options as $o)
                        <option value="{{ $o }}" @selected(($filters[$key] ?? '') === $o)>{{ $o }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach
        <div class="field">
            <label for="name">暱稱裡有</label>
            <input id="name" name="name" type="search" maxlength="20" value="{{ $filters['name'] ?? '' }}">
        </div>
        <div class="field-check">
            <input id="photo" name="photo" type="checkbox" value="1" @checked(! empty($filters['photo']))>
            <label for="photo">只看有大頭照的</label>
        </div>
        <fieldset class="field">
            <legend>通過這些認證的</legend>
            @foreach (['job' => '職業認證', 'id' => '身分認證', 'church' => '教會認證'] as $kind => $label)
                <div class="field-check">
                    <input id="verified-{{ $kind }}" name="verified[]" type="checkbox" value="{{ $kind }}" @checked(in_array($kind, $filters['verified'] ?? [], true))>
                    <label for="verified-{{ $kind }}">{{ $label }}</label>
                </div>
            @endforeach
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
